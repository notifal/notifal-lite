<?php

namespace Notifal\Modules\Forms\Application\Services\HtmlBuilder;

use Notifal\Core\Foundation\Container;
use Notifal\Domain\Settings\Services\SettingsService;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Security\NonceManager;
use Notifal\Modules\Forms\Application\Services\ConnectedFormMarkupGuard;
use Notifal\Modules\Forms\Application\Services\FormFieldSanitizer;
use Notifal\Modules\Forms\Application\Services\FormLicenseGuard;
use Notifal\Modules\Forms\Application\Services\FormsSettingsService;
use Notifal\Modules\Forms\Config\AttributeContract;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormFieldsRepository;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormMetaRepository;
use Notifal\Shared\Config\Paths;

defined('ABSPATH') || exit;

/**
 * Registers the HTML Builder form widget and runtime Forms config.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormsHtmlBuilderIntegration
{
    /**
     * Register filters.
     *
     * @return void
     * @since 3.0.0
     * @updated 3.0.0 Strip deleted Form CPT references from builder HTML.
     */
    public static function register(): void
    {
        add_filter(FilterHooks::TEMPLATE_HTML_BUILDER_WIDGETS, [self::class, 'addFormWidget']);
        add_filter(FilterHooks::TEMPLATE_HTML_KSES_ALLOWED, [self::class, 'extendKsesAllowlist']);
        // Expose form picker / inline-create data to the builder Content panel.
        add_filter(FilterHooks::TEMPLATE_HTML_BUILDER_RUNTIME_CONFIG, [self::class, 'appendRuntimeConfig']);
        // Persist cleanup whenever HTML Builder sanitizes (save / paste / import).
        add_filter(FilterHooks::TEMPLATE_HTML_SANITIZE_AFTER, [self::class, 'stripMissingConnectedForms'], 10, 2);
    }

    /**
     * Remove connected form roots whose Form CPT no longer exists.
     *
     * @param string               $html     Sanitized HTML.
     * @param array<int, string>   $warnings Sanitizer warnings (unused).
     * @return string Cleaned HTML.
     * @since 3.0.0
     */
    public static function stripMissingConnectedForms(string $html, $warnings = []): string
    {
        unset($warnings);

        $guard = new ConnectedFormMarkupGuard();
        $cleaned = $guard->removeMissingConnectedForms($html);

        return $cleaned['html'];
    }

    /**
     * Append Forms picker config for the HTML Builder Content panel.
     *
     * @param array<string, mixed> $config Existing runtime config.
     * @return array<string, mixed>
     * @since 3.0.0
     * @updated 3.0.0 Clean stale Form connections from initial_html on builder load.
     */
    public static function appendRuntimeConfig(array $config): array
    {
        // Drop deleted Form CPT roots before the canvas hydrates saved HTML.
        $removedMissingIds = [];
        if (!empty($config['initial_html']) && is_string($config['initial_html'])) {
            $guard = new ConnectedFormMarkupGuard();
            $cleaned = $guard->removeMissingConnectedForms($config['initial_html']);
            $config['initial_html'] = $cleaned['html'];
            $removedMissingIds = $cleaned['removed_ids'];
        }

        // Load published/draft forms for the connect dropdown.
        $posts = get_posts([
            'post_type'              => 'notifal_form',
            'post_status'            => ['publish', 'draft', 'private'],
            'posts_per_page'         => 100,
            'orderby'                => 'title',
            'order'                  => 'ASC',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        $items = [];
        $fieldsRepo = new FormFieldsRepository(new FormFieldSanitizer());
        $metaRepo = new FormMetaRepository();

        foreach ($posts as $post) {
            // Skip unexpected post types defensively.
            if (!$post instanceof \WP_Post) {
                continue;
            }

            $items[] = [
                'id'           => (int) $post->ID,
                'title'        => (string) $post->post_title,
                'edit_url'     => add_query_arg(
                    [
                        'page'    => 'notifal-form',
                        'form_id' => (int) $post->ID,
                    ],
                    admin_url('admin.php')
                ),
                'fields'       => $fieldsRepo->get((int) $post->ID),
                // Presentation defaults applied when connecting in the builder.
                'presentation' => $metaRepo->getPresentation((int) $post->ID),
            ];
        }

        // Builder uses this block for select + inline create (no raw CPT ID typing).
        $formsSettings = new FormsSettingsService(Container::getInstance()->get(SettingsService::class));
        $config['forms'] = [
            'enabled'         => true,
            'can_create'      => FormLicenseGuard::canCreate(),
            'items'           => $items,
            'list_url'        => admin_url('admin.php?page=notifal-forms'),
            'nonce'           => NonceManager::create('notifal_form_save'),
            'ajax_action'     => 'notifal_save_form',
            'sync_action'     => 'notifal_sync_form_fields',
            'field_types'     => AttributeContract::FIELD_TYPES,
            'conditional_ops' => AttributeContract::CONDITIONAL_OPERATORS,
            // Built frontend stylesheet so the canvas matches public form chrome.
            'preview_css_url' => Paths::cssFrontendBuildUrl() . 'FormsFrontendStyle.css',
            // Site brand accent used as the default form primary color.
            'default_primary_color' => self::resolveDefaultPrimaryColor($config),
            // Show per-form captcha skip only when Settings → Forms has a provider.
            'captcha_enabled' => $formsSettings->isGlobalCaptchaEnabled(),
            // Builder shows a toast when deleted Form roots were stripped on load.
            'removed_missing_ids' => $removedMissingIds,
        ];

        return $config;
    }

    /**
     * Append the Form widget definition.
     *
     * @param array<int, array<string, string>> $widgets Existing widgets.
     * @return array<int, array<string, string>>
     * @since 3.0.0
     */
    public static function addFormWidget(array $widgets): array
    {
        $html = self::getStarterHtml();

        $widgets[] = [
            'id'             => 'form',
            'label'          => __('Form', 'notifal'),
            'category'       => 'actions',
            'category_label' => __('Actions', 'notifal'),
            'icon'           => 'layers',
            'description'    => __('Lead form with Notifal fields, success actions, and brand styling.', 'notifal'),
            'default_html'   => $html,
            'ai_snippet'     => $html,
        ];

        return $widgets;
    }

    /**
     * Extend restricted kses allowlist for form tags and data attributes.
     *
     * @param array<string, array<string, bool>> $allowed Allowed tags.
     * @return array<string, array<string, bool>>
     * @since 3.0.0
     */
    public static function extendKsesAllowlist(array $allowed): array
    {
        $formDataAttrs = AttributeContract::getKsesDataAttributes();

        $formTags = [
            'form',
            'input',
            'textarea',
            'select',
            'option',
            'label',
            'fieldset',
            'legend',
            'button',
            'div',
            'span',
        ];

        foreach ($formTags as $tag) {
            if (!isset($allowed[$tag]) || !is_array($allowed[$tag])) {
                $allowed[$tag] = [];
            }
            $allowed[$tag] = array_merge($allowed[$tag], $formDataAttrs, [
                'class'        => true,
                'id'           => true,
                'name'         => true,
                'type'         => true,
                'value'        => true,
                'placeholder'  => true,
                'required'     => true,
                'disabled'     => true,
                'checked'      => true,
                'selected'     => true,
                'hidden'       => true,
                'method'       => true,
                'action'       => true,
                'novalidate'   => true,
                'autocomplete' => true,
                'rows'         => true,
                'cols'         => true,
                'for'          => true,
                'style'        => true,
                'aria-hidden'  => true,
                'tabindex'     => true,
            ]);
        }

        return $allowed;
    }

    /**
     * Starter form markup with brand accent and a clear single-step layout.
     *
     * @return string
     * @since 3.0.0
     * @updated 3.0.0 Include designable Success and Failed result panels.
     */
    public static function getStarterHtml(): string
    {
        // Prefer the builder brand color so new forms match the site look.
        $primary = self::resolveDefaultPrimaryColor([]);
        $primaryAttr = esc_attr($primary);

        return '<form class="notifal-form" style="--notifal-primary-color:' . $primaryAttr . ';"'
            . ' data-notifal-widget="form" data-form-id="0" data-form-multi-step="false"'
            . ' data-success-behavior="message"'
            . ' data-success-message="Thanks! We will be in touch."'
            . ' data-error-message="Something went wrong. Please try again."'
            . ' data-primary-color="' . $primaryAttr . '"'
            . ' data-submitter-email-field="email" data-captcha="inherit">'
            . '<label class="notifal-form-field-wrap">'
            . '<span class="notifal-form-label">Name</span>'
            . '<input class="notifal-form-field" type="text" name="name" placeholder="Your name"'
            . ' data-field-type="text" data-field-name="name" data-label="Name" data-required="true" />'
            . '</label>'
            . '<label class="notifal-form-field-wrap">'
            . '<span class="notifal-form-label">Email</span>'
            . '<input class="notifal-form-field" type="email" name="email" placeholder="you@example.com"'
            . ' data-field-type="email" data-field-name="email" data-label="Email" data-required="true" />'
            . '</label>'
            . '<button type="submit" class="notifal-form-nav" data-form-action="submit">Send</button>'
            . '<div class="notifal-form-success" data-form-result="success" hidden>'
            . '<p class="notifal-form-success-message">Thanks! We will be in touch.</p>'
            . '</div>'
            . '<div class="notifal-form-error" data-form-result="failed" hidden role="alert">'
            . '<p class="notifal-form-error-message">Something went wrong. Please try again.</p>'
            . '</div>'
            . '</form>';
    }

    /**
     * Resolve the default form brand color from builder config or filter.
     *
     * @param array<string, mixed> $config Runtime config (may already include primary_color).
     * @return string Sanitized hex color.
     * @since 3.0.0
     */
    private static function resolveDefaultPrimaryColor(array $config): string
    {
        $candidate = '';

        // Prefer the already-resolved builder primary color when available.
        if (!empty($config['primary_color']) && is_string($config['primary_color'])) {
            $candidate = $config['primary_color'];
        }

        // Allow a hex override from the same filter the builder uses.
        if ($candidate === '') {
            $candidate = (string) apply_filters(FilterHooks::TEMPLATE_HTML_BUILDER_PRIMARY_COLOR, '#7e2bd2');
        }

        $sanitized = sanitize_hex_color($candidate);

        return is_string($sanitized) && $sanitized !== '' ? $sanitized : '#7e2bd2';
    }
}
