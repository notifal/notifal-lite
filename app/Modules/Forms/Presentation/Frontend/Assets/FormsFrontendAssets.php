<?php

namespace Notifal\Modules\Forms\Presentation\Frontend\Assets;

use Notifal\Core\Foundation\Container;
use Notifal\Domain\Settings\Services\SettingsService;
use Notifal\Infrastructure\WordPress\Admin\Localization\LangLoader;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Forms\Application\Services\FormsSettingsService;
use Notifal\Shared\Config\Paths;

defined('ABSPATH') || exit;

/**
 * Conditionally enqueue Forms frontend runtime when a Notifal form is present.
 *
 * Rules:
 * - Module kill switch off → this class is never registered.
 * - Template HTML without `.notifal-form` → no Forms CSS/JS.
 * - Normal page load with a form → WordPress enqueue only (no duplicate payload).
 * - REST/AJAX template render with a form → URLs go into template_assets for JS load.
 *
 * Localize allowlist only: ajax URL, nonce action, public site key, i18n.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormsFrontendAssets
{
    /**
     * Whether assets were enqueued this request.
     *
     * @var bool
     */
    private static $enqueued = false;

    /**
     * Register hooks.
     *
     * @return void
     * @since 3.0.0
     */
    public static function register(): void
    {
        // Register handles early so enqueue can run on demand.
        add_action('wp_enqueue_scripts', [self::class, 'registerAssets'], 5);
        // Footer hook kept for future DOM-based fallbacks.
        add_action('wp_footer', [self::class, 'enqueueOnDemand'], 5);
        // Detect forms after class placeholders hydrate template HTML.
        add_filter(FilterHooks::ONPAGE_TEMPLATE_CLASS_PLACEHOLDERS_AFTER, [self::class, 'markFormPresent'], 5, 2);
        // Attach Forms URLs to REST/AJAX template payloads only when needed.
        add_filter(FilterHooks::ONPAGE_TEMPLATE_RENDERED_ASSETS, [self::class, 'appendAssetsWhenNeeded'], 10, 3);
    }

    /**
     * Register (but do not enqueue) script handles.
     *
     * @return void
     * @since 3.0.0
     */
    public static function registerAssets(): void
    {
        $js = Paths::jsFrontendBuildUrl() . 'FormsFrontendBundle.js';
        $css = Paths::cssFrontendBuildUrl() . 'FormsFrontendStyle.css';

        wp_register_style(
            'notifal-forms-frontend',
            $css,
            [],
            NOTIFAL_VERSION
        );

        wp_register_script(
            'notifal-forms-frontend',
            $js,
            [],
            NOTIFAL_VERSION,
            true
        );
    }

    /**
     * Enqueue via WordPress when a rendered template contains a Notifal form.
     *
     * Skips REST/AJAX so visitor pages are not polluted by API render side effects.
     * Those contexts rely on appendAssetsWhenNeeded() instead.
     *
     * @param string $html HTML.
     * @param array  $ctx  Context.
     * @return string
     * @since 3.0.0
     */
    public static function markFormPresent(string $html, array $ctx = []): string
    {
        unset($ctx);

        // No form markup means Forms assets must stay off the page.
        if (!self::htmlContainsNotifalForm($html)) {
            return $html;
        }

        // Page-view path only: print Forms CSS/JS through wp_enqueue_*.
        if (self::shouldEnqueueViaWordPress()) {
            self::enqueue();
        }

        return $html;
    }

    /**
     * Append Forms CSS/JS URLs to template_assets for REST/AJAX renders.
     *
     * @param array         $assets   Current asset map.
     * @param string        $html     Rendered HTML.
     * @param \WP_Post|null $template Template post.
     * @return array
     * @since 3.0.0
     */
    public static function appendAssetsWhenNeeded(array $assets, string $html, $template = null): array
    {
        unset($template);

        // Only attach when the template actually includes a Notifal form.
        if (!self::htmlContainsNotifalForm($html)) {
            return $assets;
        }

        // Normal page views already enqueued via WordPress; avoid duplicate payload.
        if (self::shouldEnqueueViaWordPress()) {
            return $assets;
        }

        $cssUrl = Paths::cssFrontendBuildUrl() . 'FormsFrontendStyle.css';
        $jsUrl = Paths::jsFrontendBuildUrl() . 'FormsFrontendBundle.js';

        $css = isset($assets['css']) && is_array($assets['css']) ? $assets['css'] : [];
        $js = isset($assets['js']) && is_array($assets['js']) ? $assets['js'] : [];

        // Append only when this URL is not already listed.
        if (!in_array($cssUrl, $css, true)) {
            $css[] = $cssUrl;
        }

        // Bootstrap public config before the bundle so AJAX URL/i18n exist without wp_localize.
        $configBootstrap = self::buildConfigBootstrapScript();
        if ($configBootstrap !== '' && !in_array($configBootstrap, $js, true)) {
            // Inline config must run before FormsFrontendBundle.js loads.
            array_unshift($js, $configBootstrap);
        }

        // Load captcha vendor before the Forms bundle so render() can run on init.
        $container = Container::getInstance();
        $formsSettings = new FormsSettingsService($container->get(SettingsService::class));
        $captchaUrl = $formsSettings->getCaptchaVendorScriptUrl();
        if ($captchaUrl !== '' && !in_array($captchaUrl, $js, true)) {
            // Place vendor script after config bootstrap and before the Forms runtime.
            $js[] = $captchaUrl;
        }

        if (!in_array($jsUrl, $js, true)) {
            $js[] = $jsUrl;
        }

        $assets['css'] = $css;
        $assets['js'] = $js;

        return $assets;
    }

    /**
     * Build an inline JS snippet that defines window.NotifalForms for dynamic loads.
     *
     * @return string Inline script source (not a URL).
     * @since 3.0.0
     */
    private static function buildConfigBootstrapScript(): string
    {
        $config = self::getPublicConfig();
        $json = wp_json_encode($config);
        if (!is_string($json) || $json === '') {
            return '';
        }

        // Merge onto any existing object so later localize calls can still enrich it.
        return 'window.NotifalForms=Object.assign(window.NotifalForms||{},' . $json . ');';
    }

    /**
     * Public localize allowlist shared by wp_localize and template_assets bootstrap.
     *
     * @return array<string, mixed>
     * @since 3.0.0
     */
    private static function getPublicConfig(): array
    {
        $container = Container::getInstance();
        $formsSettings = new FormsSettingsService($container->get(SettingsService::class));
        $translations = LangLoader::load(__NAMESPACE__, 'frontend.php');

        return [
            'ajaxUrl'         => admin_url('admin-ajax.php'),
            'action'          => 'notifal_form_submit',
            'nonceAction'     => 'notifal_form_submit',
            'captchaProvider' => $formsSettings->getCaptchaProvider(),
            'captchaSiteKey'  => $formsSettings->getCaptchaSiteKey(),
            'i18n'            => is_array($translations) ? $translations : [],
        ];
    }

    /**
     * Footer fallback reserved for future detection hooks.
     *
     * @return void
     * @since 3.0.0
     */
    public static function enqueueOnDemand(): void
    {
        // markFormPresent already enqueued when a form was rendered on this page.
        if (self::$enqueued) {
            return;
        }
    }

    /**
     * Enqueue Forms frontend assets with public localize allowlist.
     *
     * @return void
     * @since 3.0.0
     */
    public static function enqueue(): void
    {
        if (self::$enqueued) {
            return;
        }

        self::$enqueued = true;

        $container = Container::getInstance();
        $formsSettings = new FormsSettingsService($container->get(SettingsService::class));

        // Register/enqueue captcha vendor first so Forms JS can depend on it.
        $captchaHandle = self::enqueueCaptchaVendor($formsSettings);

        wp_enqueue_style('notifal-forms-frontend');

        // Ensure Forms runtime waits for the captcha vendor when one is active.
        if ($captchaHandle !== '') {
            $scripts = wp_scripts();
            if (isset($scripts->registered['notifal-forms-frontend'])) {
                $deps = $scripts->registered['notifal-forms-frontend']->deps;
                if (!in_array($captchaHandle, $deps, true)) {
                    $scripts->registered['notifal-forms-frontend']->deps[] = $captchaHandle;
                }
            }
        }

        wp_enqueue_script('notifal-forms-frontend');

        // Public allowlist only (no secrets).
        wp_localize_script(
            'notifal-forms-frontend',
            'NotifalForms',
            self::getPublicConfig()
        );
    }

    /**
     * Enqueue the captcha vendor script for the current Forms settings.
     *
     * @param FormsSettingsService $formsSettings Forms settings reader.
     * @return string Enqueued handle or empty string.
     * @since 3.0.0
     */
    private static function enqueueCaptchaVendor(FormsSettingsService $formsSettings): string
    {
        // Resolve provider-specific URL and WP handle.
        $url = $formsSettings->getCaptchaVendorScriptUrl();
        $handle = $formsSettings->getCaptchaVendorScriptHandle();

        // Nothing to load when captcha is disabled or incomplete.
        if ($url === '' || $handle === '') {
            return '';
        }

        // Register on demand so REST/AJAX paths that never hit registerAssets still work.
        wp_register_script($handle, $url, [], null, true);
        wp_enqueue_script($handle);

        return $handle;
    }

    /**
     * Whether the HTML contains a Notifal form root.
     *
     * @param string $html Template HTML.
     * @return bool
     * @since 3.0.0
     */
    private static function htmlContainsNotifalForm(string $html): bool
    {
        return strpos($html, 'notifal-form') !== false;
    }

    /**
     * Whether this request should print Forms assets through wp_enqueue_*.
     *
     * REST/AJAX template renders must not enqueue for the visitor document.
     *
     * @return bool
     * @since 3.0.0
     */
    private static function shouldEnqueueViaWordPress(): bool
    {
        // REST eligibility/preview responses are JSON, not the visitor HTML document.
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }

        // Admin-ajax / frontend ajax renders also attach assets via template_assets.
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return false;
        }

        // Never print Forms frontend assets inside wp-admin screens.
        if (is_admin()) {
            return false;
        }

        return true;
    }
}
