<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Template;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\OnPageNotification\Application\Traits\TagProcessingTrait;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\WidgetContextProvider;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Abstract base class for template renderers.
 *
 * Provides common functionality for rendering templates with context processing,
 * widget context management, asset-queue snapshotting and error handling.
 *
 * Asset capture works by snapshotting wp_styles()->queue and wp_scripts()->queue
 * before content rendering, then diffing after rendering to find every CSS/JS
 * handle that was newly enqueued by widgets, blocks, or third-party plugins
 * during the render pass.  The captured URLs (and inline CSS/JS) are merged
 * with whatever builder-specific assets the subclass returns from getAssets().
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
abstract class AbstractTemplateRenderer
{
    use TagProcessingTrait;

    /**
     * Styles queue snapshot taken before content rendering.
     *
     * @var string[]
     */
    protected $preRenderStylesQueue = [];

    /**
     * Scripts queue snapshot taken before content rendering.
     *
     * @var string[]
     */
    protected $preRenderScriptsQueue = [];

    /**
     * Registered style handles snapshot taken before content rendering.
     *
     * @var string[]
     */
    protected $preRenderStylesRegistered = [];

    /**
     * Registered script handles snapshot taken before content rendering.
     *
     * @var string[]
     */
    protected $preRenderScriptsRegistered = [];

    /**
     * Render a template with frontend context.
     *
     * Orchestrates the full render pipeline:
     *   1. Set widget context for dynamic tags.
     *   2. Allow the subclass to initialise builder-specific subsystems.
     *   3. Ensure third-party theme/plugin scripts are registered (REST).
     *   4. Snapshot the WP asset queues.
     *   5. Render content via builder-specific logic.
     *   6. Process Notifal tags.
     *   7. Collect builder-specific assets + captured queue delta.
     *
     * @param \WP_Post $template Template post.
     * @param array    $frontendContext Already-built frontend context.
     * @return array Rendered template data with html, assets, builder_type.
     * @since 2.0.0
     */
    public function render(\WP_Post $template, array $frontendContext): array
    {
        try {
            WidgetContextProvider::setContext($frontendContext);

            $this->ensureShortcodeContext($template);

            $this->initializeForRendering($template);

            $this->ensureThirdPartyAssetsRegistered();

            $this->snapshotAssetQueues();

            $this->enqueuePerPostAssets($template);

            $content = $this->renderContent($template, $frontendContext);

            $content = $this->processTagsWithContext($content, $frontendContext);

            // Hydrate class-based placeholders (featured image, close, action button).
            $content = TemplateClassPlaceholderProcessor::process($content, $frontendContext);

            // Ensure shortcodes that survived builder rendering are
            // expanded.  On a normal page load the_content filter runs
            // do_shortcode() at priority 11, but in REST/AJAX context
            // some plugins skip shortcode registration entirely.  This
            // explicit call acts as a safety net so [shortcode] tags
            // never appear as raw text inside a notification.
            if (strpos($content, '[') !== false) {
                $content = do_shortcode($content);
            }

            $builderAssets = $this->getAssets($template);

            $this->enqueueThirdPartyCssFromContent($content);

            $capturedAssets = $this->captureNewlyEnqueuedAssets();
            $assets         = $this->mergeAssetArrays($builderAssets, $capturedAssets);

            WidgetContextProvider::clearContext();

            return [
                'html'         => $content,
                'assets'       => $assets,
                'builder_type' => $this->getBuilderType(),
            ];

        } catch (\Exception $e) {
            WidgetContextProvider::clearContext();

            return $this->handleRenderError($template, $e);
        }
    }

    /**
     * Initialise builder-specific subsystems before rendering.
     *
     * Override in subclasses for builder-specific initialisation such as
     * Elementor frontend init, widget registration, etc.
     *
     * @param \WP_Post $template Template post being rendered.
     * @return void
     * @since 2.0.0
     */
    protected function initializeForRendering(\WP_Post $template): void
    {
        // Default no-op. Subclasses override as needed.
    }

    /**
     * Render the actual template content using builder-specific logic.
     *
     * @param \WP_Post $template Template post.
     * @param array    $frontendContext Frontend context.
     * @return string Rendered content.
     * @since 2.0.0
     */
    abstract protected function renderContent(\WP_Post $template, array $frontendContext): string;

    /**
     * Get builder-specific assets for the template.
     *
     * Return only assets that are specific to the builder (e.g. Elementor
     * Post-CSS, base Elementor JS).  Generic captured assets are merged
     * automatically by the base class.
     *
     * @param \WP_Post $template Template post.
     * @return array Asset URLs or inline content keyed by 'css'/'js'.
     * @since 2.0.0
     */
    abstract protected function getAssets(\WP_Post $template): array;

    /**
     * Get the builder type identifier.
     *
     * @return string Builder type string ('elementor' or 'block_editor').
     * @since 2.0.0
     */
    abstract protected function getBuilderType(): string;

    // =========================================================================
    // Asset queue snapshotting & capture
    // =========================================================================

    /**
     * Snapshot the current WP styles/scripts queues and registered handles.
     *
     * Called immediately before renderContent() so we can diff afterwards.
     *
     * @return void
     * @since 2.0.0
     */
    protected function snapshotAssetQueues(): void
    {
        $this->preRenderStylesQueue      = wp_styles()->queue;
        $this->preRenderScriptsQueue     = wp_scripts()->queue;
        $this->preRenderStylesRegistered = array_keys(wp_styles()->registered);
        $this->preRenderScriptsRegistered = array_keys(wp_scripts()->registered);
    }

    /**
     * Capture CSS/JS assets that were newly enqueued during content rendering.
     *
     * Compares the WP styles/scripts queues after rendering against the
     * snapshot taken before rendering.  For each new handle it resolves
     * the absolute URL and collects any inline CSS/JS attached to it.
     *
     * Also picks up newly REGISTERED (but not enqueued) handles, because
     * some widgets register stylesheets during rendering without enqueuing.
     *
     * @return array{css: string[], js: string[]}
     * @since 2.0.0
     */
    protected function captureNewlyEnqueuedAssets(): array
    {
        $css = [];
        $js  = [];

        // --- Newly enqueued styles ---
        $newStyleHandles = array_diff(wp_styles()->queue, $this->preRenderStylesQueue);
        foreach ($newStyleHandles as $handle) {
            $style = wp_styles()->registered[$handle] ?? null;
            if (!$style) {
                continue;
            }

            $src = $this->resolveAssetUrl($style->src, wp_styles());
            if ($src) {
                $css[] = $src;
            }

            // Inline CSS attached via wp_add_inline_style()
            if (!empty($style->extra['after'])) {
                $inline = implode("\n", (array) $style->extra['after']);
                if (!empty(trim($inline))) {
                    $css[] = $inline;
                }
            }
        }

        // --- Newly registered styles (widgets may register without enqueuing) ---
        $newStyleRegistered = array_diff(
            array_keys(wp_styles()->registered),
            $this->preRenderStylesRegistered
        );
        foreach ($newStyleRegistered as $handle) {
            if (in_array($handle, $newStyleHandles, true)) {
                continue;
            }
            $style = wp_styles()->registered[$handle] ?? null;
            if (!$style || empty($style->src)) {
                continue;
            }
            $src = $this->resolveAssetUrl($style->src, wp_styles());
            if ($src) {
                $css[] = $src;
            }
        }

        // --- Newly enqueued scripts ---
        $newScriptHandles = array_diff(wp_scripts()->queue, $this->preRenderScriptsQueue);
        foreach ($newScriptHandles as $handle) {
            $script = wp_scripts()->registered[$handle] ?? null;
            if (!$script) {
                continue;
            }

            $src = $this->resolveAssetUrl($script->src, wp_scripts());
            if ($src) {
                $js[] = $src;
            }

            $this->collectInlineScriptData($script, $js);
        }

        // --- Newly registered scripts (some widgets register without enqueuing) ---
        $newScriptRegistered = array_diff(
            array_keys(wp_scripts()->registered),
            $this->preRenderScriptsRegistered
        );
        foreach ($newScriptRegistered as $handle) {
            if (in_array($handle, $newScriptHandles, true)) {
                continue;
            }
            $script = wp_scripts()->registered[$handle] ?? null;
            if (!$script || empty($script->src)) {
                continue;
            }
            $src = $this->resolveAssetUrl($script->src, wp_scripts());
            if ($src) {
                $js[] = $src;
            }
        }

        return [
            'css' => array_values(array_unique($css)),
            'js'  => array_values(array_unique($js)),
        ];
    }

    /**
     * Resolve an asset source to an absolute URL.
     *
     * Handles relative paths (e.g. '/wp-content/...') by prepending the
     * site URL, and WP_Dependencies base_url resolution.
     *
     * @param string           $src  The source as stored in WP_Dependencies.
     * @param \WP_Dependencies $deps The dependencies instance (scripts or styles).
     * @return string|null Absolute URL or null if src is empty/invalid.
     * @since 2.0.0
     */
    protected function resolveAssetUrl(string $src, $deps): ?string
    {
        if (empty($src)) {
            return null;
        }

        // Already absolute
        if (strpos($src, '//') === 0 || strpos($src, 'http') === 0) {
            return $src;
        }

        // Relative to WP base URL
        $baseUrl = $deps->base_url ?? site_url();

        return rtrim($baseUrl, '/') . '/' . ltrim($src, '/');
    }

    /**
     * Collect inline script data (localize / before / after) for a handle.
     *
     * @param object $script Registered script dependency.
     * @param array  &$js    JS assets array (passed by reference).
     * @return void
     * @since 2.0.0
     */
    protected function collectInlineScriptData(object $script, array &$js): void
    {
        // wp_localize_script() stores data in extra['data']
        if (!empty($script->extra['data'])) {
            $js[] = $script->extra['data'];
        }

        // wp_add_inline_script( $handle, $code, 'before' )
        if (!empty($script->extra['before'])) {
            foreach ((array) $script->extra['before'] as $inline) {
                if (!empty(trim($inline))) {
                    $js[] = $inline;
                }
            }
        }

        // wp_add_inline_script( $handle, $code, 'after' )
        if (!empty($script->extra['after'])) {
            foreach ((array) $script->extra['after'] as $inline) {
                if (!empty(trim($inline))) {
                    $js[] = $inline;
                }
            }
        }
    }

    /**
     * Initialise plugin subsystems that shortcodes depend on but that
     * are skipped in REST / AJAX context.
     *
     * On a normal frontend page load, WooCommerce calls wc_load_cart()
     * to set up the session, customer and cart objects.  In REST context
     * that call is skipped, so shortcodes like [woocommerce_cart] or
     * [woocommerce_checkout] silently return empty HTML.
     *
     * This method:
     *  1. Initialises the WC cart when WooCommerce is active.
     *  2. Fires a custom action so other plugins / themes can perform
     *     their own REST-context bootstrapping.
     *
     * Runs once per request (static guard).
     *
     * @param \WP_Post $template Template post being rendered.
     *
     * @return void
     * @since 2.0.3
     */
    protected function ensureShortcodeContext(\WP_Post $template): void
    {
        // Only needed in REST / AJAX where plugins skip frontend init.
        if (!defined('REST_REQUEST') && !wp_doing_ajax()) {
            return;
        }

        static $initialized = false;

        if ($initialized) {
            return;
        }

        $initialized = true;

        // WooCommerce skips wc_load_cart() in REST context, which
        // leaves WC()->cart as null.  Without it every WC shortcode
        // that touches the cart renders empty output.
        if (function_exists('WC') && function_exists('wc_load_cart')) {
            $wc = WC();

            if ($wc && is_null($wc->cart)) {
                wc_load_cart();
            }
        }

        /**
         * Fires after built-in shortcode context is ready, before
         * any template content is rendered in REST / AJAX context.
         *
         * Plugins and themes can hook here to bootstrap subsystems
         * that are normally skipped outside a standard page load.
         *
         * @param \WP_Post $template The notification template post.
         *
         * @since 2.0.3
         */
        do_action(ActionHooks::TEMPLATE_RENDERER_SHORTCODE_CONTEXT, $template);
    }

    /**
     * Register third-party theme/plugin scripts and styles.
     *
     * Fires wp_enqueue_scripts once (if it hasn't fired yet) so that
     * theme and plugin scripts are registered in wp_scripts()/wp_styles().
     *
     * This ensures that when widgets call wp_enqueue_script('some-handle')
     * during render(), the handle resolves to a real URL.
     *
     * Only fires in REST / AJAX context where wp_enqueue_scripts would
     * not normally run.  The static flag prevents double-firing on
     * subsequent template renders within the same request.
     *
     * @return void
     * @since 2.0.0
     */
    protected function ensureThirdPartyAssetsRegistered(): void
    {
        static $fired = false;

        if ($fired) {
            return;
        }
        $fired = true;

        // Only fire if we are in a REST / AJAX context where
        // wp_enqueue_scripts would not normally run.
        if (!defined('REST_REQUEST') && !wp_doing_ajax()) {
            return;
        }

        try {
            do_action('wp_enqueue_scripts');
        } catch (\Throwable $e) {
            Helper::log('Third-party asset registration error: ' . $e->getMessage());
        }
    }

    /**
     * Re-fire wp_enqueue_scripts with the template post as global $post.
     *
     * Many themes and plugins read $GLOBALS['post'] during
     * wp_enqueue_scripts to enqueue per-post CSS/JS (e.g. block assets
     * stored in post meta).  In REST/AJAX context the global post is
     * not the template being rendered, so those callbacks miss it.
     *
     * Because wp_enqueue_script() and wp_enqueue_style() are idempotent,
     * calling them again for already-enqueued handles is a no-op.  Only
     * the per-post assets that depend on reading $GLOBALS['post'] will
     * be newly enqueued — and since this runs AFTER the snapshot, the
     * diff in captureNewlyEnqueuedAssets() picks them up automatically.
     *
     * This is theme/plugin-agnostic: any callback that reads $post
     * during wp_enqueue_scripts will see the correct template post.
     *
     * @param \WP_Post $template Template post being rendered.
     * @return void
     * @since 2.0.0
     */
    protected function enqueuePerPostAssets(\WP_Post $template): void
    {
        if (!defined('REST_REQUEST') && !wp_doing_ajax()) {
            return;
        }

        // Track which templates have already been processed so we
        // never fire wp_enqueue_scripts redundantly for the same post.
        static $processedIds = [];

        if (in_array($template->ID, $processedIds, true)) {
            return;
        }

        $processedIds[] = $template->ID;

        $savedPost       = $GLOBALS['post'] ?? null;
        $GLOBALS['post'] = $template;
        setup_postdata($template);

        try {
            do_action('wp_enqueue_scripts');
        } catch (\Throwable $e) {
            Helper::log('Per-post asset enqueue error: ' . $e->getMessage());
        }

        $GLOBALS['post'] = $savedPost;

        if ($savedPost instanceof \WP_Post) {
            setup_postdata($savedPost);
        } else {
            wp_reset_postdata();
        }
    }

    /**
     * Force-enqueue third-party CSS that was skipped during REST rendering.
     *
     * Some themes (e.g. WoodMart) check wp_is_serving_rest_request() inside
     * their CSS enqueue wrappers and silently return, leaving widget CSS
     * unloaded.  This method scans the rendered HTML for known third-party
     * element class names and calls the theme's force-enqueue function to
     * register the CSS through wp_enqueue_style(), which our snapshot diff
     * in captureNewlyEnqueuedAssets() then picks up.
     *
     * Only fires in REST / AJAX context where the CSS would be missing.
     *
     * @param string $content Rendered HTML content.
     * @return void
     * @since 2.0.0
     */
    protected function enqueueThirdPartyCssFromContent(string $content): void
    {
        if (!defined('REST_REQUEST') && !wp_doing_ajax()) {
            return;
        }

        if (empty($content)) {
            return;
        }

        $this->enqueueWoodmartCssFromContent($content);
    }

    /**
     * Detect WoodMart elements in rendered HTML and force-enqueue their CSS.
     *
     * WoodMart's woodmart_enqueue_inline_style() returns early when
     * wp_is_serving_rest_request() is true.  woodmart_force_enqueue_style()
     * bypasses this check and uses wp_enqueue_style() which registers the
     * CSS in wp_styles() for capture.
     *
     * The mapping covers the most common WoodMart element patterns.
     * Additional patterns can be added via the
     * 'notifal/template_renderer/woodmart_css_patterns' filter.
     *
     * @param string $content Rendered HTML content.
     * @return void
     * @since 2.0.0
     */
    private function enqueueWoodmartCssFromContent(string $content): void
    {
        if (!function_exists('woodmart_force_enqueue_style')) {
            return;
        }

        /**
         * HTML class pattern → WoodMart CSS key mapping.
         *
         * Each key is a substring to search for in the rendered HTML.
         * The value is the CSS config key passed to woodmart_force_enqueue_style().
         *
         * @param array $patterns Pattern-to-key mapping.
         * @return array
         * @since 2.0.0
         */
        $patterns = apply_filters('notifal/template_renderer/woodmart_css_patterns', [
            'wd-countdown-timer' => 'countdown',
            'wd-timer'           => 'countdown',
            'promo-banner'       => 'banner',
            'wd-banner'          => 'banner',
            'wd-button-wrapper'  => 'btn',
            'wd-image-hotspot'   => 'image-hotspot',
            'wd-instagram'       => 'instagram',
            'wd-tabs'            => 'tabs',
            'wd-accordion'       => 'accordion',
            'wd-blog-element'    => 'blog-base',
            'wd-products-element' => 'woo-prod-loop-base',
            'wd-popup'           => 'popup',
            'wd-slider'          => 'swiper',
            'wd-testimonials'    => 'testimonials',
        ]);

        $enqueued = [];

        foreach ($patterns as $htmlPattern => $cssKey) {
            if (isset($enqueued[$cssKey])) {
                continue;
            }

            if (strpos($content, $htmlPattern) !== false) {
                woodmart_force_enqueue_style($cssKey);
                $enqueued[$cssKey] = true;
            }
        }
    }

    /**
     * Merge two asset arrays, deduplicating entries.
     *
     * @param array $base      Base assets (from builder-specific getAssets).
     * @param array $additions Additional assets (from captured queue delta).
     * @return array Merged asset array with 'css' and 'js' keys.
     * @since 2.0.0
     */
    protected function mergeAssetArrays(array $base, array $additions): array
    {
        $merged = [];

        $merged['css'] = array_values(array_unique(
            array_merge($base['css'] ?? [], $additions['css'] ?? [])
        ));
        $merged['js'] = array_values(array_unique(
            array_merge($base['js'] ?? [], $additions['js'] ?? [])
        ));

        // Remove empty keys
        if (empty($merged['css'])) {
            unset($merged['css']);
        }
        if (empty($merged['js'])) {
            unset($merged['js']);
        }

        return $merged;
    }

    /**
     * Handle rendering errors with fallback to raw content.
     *
     * @param \WP_Post   $template  Template post.
     * @param \Exception $exception The caught exception.
     * @return array Error response with fallback content.
     * @since 2.0.0
     */
    private function handleRenderError(\WP_Post $template, \Exception $exception): array
    {
        return [
            'html'         => $template->post_content,
            'assets'       => [],
            'error'        => 'Error rendering ' . $this->getBuilderType() . ' template: ' . $exception->getMessage(),
            'builder_type' => $this->getBuilderType(),
        ];
    }
}
