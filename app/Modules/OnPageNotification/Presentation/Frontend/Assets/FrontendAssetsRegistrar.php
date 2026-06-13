<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Frontend\Assets;

use Notifal\Core\Support\Helpers\UrlHelper;
use Notifal\Infrastructure\WordPress\Elementor\Helpers\ElementorHelper;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\OnPageNotification\Application\Services\Core\ClientUserRulesBuilder;
use Notifal\Modules\OnPageNotification\Application\Services\Rules\CartDisplayRulesUsageChecker;
use Notifal\Modules\OnPageNotification\Application\Services\Rules\VisitorAuthContextResolver;
use Notifal\Modules\OnPageNotification\Application\Services\Core\EligibilityService;
use Notifal\Infrastructure\WordPress\WooCommerce\Support\WooCommerceVariationScriptSupport;
use Notifal\Modules\OnPageNotification\Application\Traits\NotificationDataTrait;
use Notifal\Modules\OnPageNotification\Application\Support\PageContextEnricher;
use Notifal\Modules\OnPageNotification\Application\Support\PageContextHelper;
use Notifal\Modules\OnPageNotification\Config\Paths as ModulePaths;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Repositories\NotificationQuery;
use Notifal\Shared\Config\Paths;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class FrontendAssetsRegistrar
 *
 * Handles registration and enqueuing of OnPageNotification module frontend scripts and styles.
 * Manages asset loading for public-facing pages where OnPage notifications are displayed.
 *
 * @package Notifal\Modules\OnPageNotification\Presentation\Frontend\Assets
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FrontendAssetsRegistrar
{
    /**
     * Register WordPress hooks for OnPageNotification frontend assets.
     *
     * Uses appropriate hooks to load assets only when needed for optimal performance.
     * Integrates with WordPress asset management and dependency system.
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        // Register assets early but don't enqueue them yet
        add_action('wp_enqueue_scripts', [self::class, 'registerAssets'], 5);
        
        // Enqueue assets conditionally based on page content
        add_action('wp_enqueue_scripts', [self::class, 'enqueueConditionalAssets'], 10);
        
        // Wrap Elementor frontend enqueue so TypeError (illegal offset) in CSS generation does not fatal the page
        add_action('wp_enqueue_scripts', [self::class, 'wrapElementorEnqueueStyles'], 19);
        
        // Enqueue assets when specific OnPage notification content is detected
        add_action('wp_footer', [self::class, 'enqueueOnDemandAssets'], 5);
        
        // Hook to trigger Templates frontend assets loading when notifications use templates
        add_filter('notifal_has_frontend_content_injection', [self::class, 'maybeEnableTemplatesAssets'], 10, 1);
    }

    /**
     * Wrap Elementor frontend enqueue_styles in try-catch so CSS generation errors do not fatal.
     * On catch, base Elementor frontend style is enqueued. Priority 19 so wrapper runs at 20.
     *
     * @return void
     * @since 2.0.0
     */
    public static function wrapElementorEnqueueStyles(): void
    {
        if (!PluginDetector::isElementorActive()) {
            return;
        }

        $frontend = \Elementor\Plugin::$instance->frontend;
        $priority = \Elementor\Frontend::ENQUEUED_STYLES_PRIORITY;

        if (!has_action('wp_enqueue_scripts', [$frontend, 'enqueue_styles'])) {
            return;
        }

        remove_action('wp_enqueue_scripts', [$frontend, 'enqueue_styles'], $priority);

        add_action('wp_enqueue_scripts', static function () use ($frontend) {
            try {
                $frontend->enqueue_styles();
            } catch (\Throwable $e) {
                Helper::log('Elementor enqueue_styles failed: ' . $e->getMessage());
                wp_enqueue_style('elementor-frontend');
            }
        }, $priority);
    }

    /**
     * Register all OnPageNotification frontend assets without enqueuing them.
     *
     * Registers scripts and styles early so they can be enqueued later as needed.
     * This approach allows for conditional loading based on page content.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function registerAssets(): void
    {
        /**
         * Fires before registering Notifal OnPageNotification frontend assets.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::FRONTEND_ASSETS_BEFORE_REGISTER);

        // Register OnPageNotification frontend bundle
        wp_register_script(
            'notifal-onpage-frontend-bundle',
            Paths::jsFrontendBuildUrl() . 'OnPageFrontendBundle.js',
            WooCommerceVariationScriptSupport::getFrontendBundleDependencies(),
            NOTIFAL_VERSION,
            true
        );

        // Register OnPageNotification frontend styles
        wp_register_style(
            'notifal-onpage-frontend-style',
            Paths::cssFrontendBuildUrl() . 'OnPageFrontendStyle.css',
            [],
            NOTIFAL_VERSION
        );

        // Localize frontend bundle with audio configuration
        wp_localize_script(
            'notifal-onpage-frontend-bundle',
            'notifalOnPageAudioConfig',
            [
                'audioBaseUrl' => apply_filters(
                    FilterHooks::ONPAGE_APPEARANCE_AUDIO_FILE_URL,
                    plugin_dir_url(NOTIFAL_FILE) . 'app/Modules/OnPageNotification/Resources/Assets/audios/',
                    ''
                ),
                'nonce' => wp_create_nonce('notifal_onpage_audio_nonce'),
            ]
        );

        /**
         * Fires after registering Notifal OnPageNotification frontend assets.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::FRONTEND_ASSETS_AFTER_REGISTER);
    }

    /**
     * Enqueue assets conditionally based on page context.
     *
     * Analyzes the current page to determine if OnPageNotification assets should be loaded.
     * Checks for notification usage, page context, and other indicators.
     *
     * @return void
     * @since 2.0.0
     */
    public static function enqueueConditionalAssets(): void
    {
        // Check if we're on a page that might contain OnPage notifications
        if (self::shouldLoadAssets()) {
            /**
             * Fires before conditionally enqueuing Notifal OnPageNotification frontend assets.
             *
             * @since 2.0.0
             */
            do_action(ActionHooks::FRONTEND_ASSETS_BEFORE_ENQUEUE);

            wp_enqueue_script('notifal-onpage-frontend-bundle');
            wp_enqueue_style('notifal-onpage-frontend-style');

            self::addTopbarAboveHeaderCompatInlineStyle();

            WooCommerceVariationScriptSupport::ensureWpUtilOnSingularProduct();

            // Localize the script with configuration data
            self::localizeScript();

            /**
             * Fires after conditionally enqueuing Notifal OnPageNotification frontend assets.
             *
             * @since 2.0.0
             */
            do_action(ActionHooks::FRONTEND_ASSETS_AFTER_ENQUEUE);
        }
    }

    /**
     * Enqueue assets on-demand in the footer if OnPage notification content is detected.
     *
     * This is a fallback method to ensure assets are loaded even if
     * conditional checks missed OnPage notification content in the page.
     *
     * @return void
     * @since 2.0.0
     */
    public static function enqueueOnDemandAssets(): void
    {
        // Check if OnPage notification bundle is already enqueued
        $onpage_bundle_enqueued = wp_script_is('notifal-onpage-frontend-bundle', 'enqueued');
        
        if ($onpage_bundle_enqueued) {
            return;
        }

        // Check if page contains any OnPage notification elements
        if (self::pageContainsOnPageNotificationElements()) {
            wp_enqueue_script('notifal-onpage-frontend-bundle');
            wp_enqueue_style('notifal-onpage-frontend-style');

            self::addTopbarAboveHeaderCompatInlineStyle();

            WooCommerceVariationScriptSupport::ensureWpUtilOnSingularProduct();

            // Localize the script with configuration data
            self::localizeScript();
        }
    }

    /**
     * Appends filterable CSS for sticky header / above-header bar compatibility (theme-specific padding reserves).
     *
     * @return void
     * @since 2.0.0
     * @see FilterHooks::ONPAGE_TOPBAR_ABOVE_HEADER_COMPAT_CSS Hook constant and add_filter() example in docblock.
     */
    private static function addTopbarAboveHeaderCompatInlineStyle(): void
    {
        if (!wp_style_is('notifal-onpage-frontend-style', 'enqueued')) {
            return;
        }

        $css = apply_filters(FilterHooks::ONPAGE_TOPBAR_ABOVE_HEADER_COMPAT_CSS, '');
        if (!is_string($css)) {
            return;
        }

        $css = trim($css);
        if ($css === '') {
            return;
        }

        wp_add_inline_style('notifal-onpage-frontend-style', wp_strip_all_tags($css));
    }

    /**
     * Determine if OnPageNotification assets should be loaded on the current page.
     *
     * Analyzes various factors to decide whether to load frontend assets.
     * Uses multiple detection methods for comprehensive coverage.
     * Excludes Elementor editor mode and template preview to prevent unwanted notifications.
     *
     * @return bool True if assets should be loaded, false otherwise.
     * @since 2.0.0
     */
    private static function shouldLoadAssets(): bool
    {
        // Never load in admin area
        if (is_admin()) {
            return false;
        }

        // Never load in Elementor editor mode
        if (self::isElementorEditorMode()) {
            return false;
        }

        // Never load in template preview mode
        if (self::isTemplatePreviewMode()) {
            return false;
        }

        // Never load during REST API requests for Elementor or template previews
        if (self::isPreviewRelatedRestRequest()) {
            return false;
        }

        // Always load when OnPage preview URL is present (valid param + nonce + capability)
        if (self::isPreviewMode()) {
            return true;
        }

        global $post;

        // Load on pages where notifications might appear
        if (is_front_page() || is_home() || is_singular() || is_archive() || is_404()) {
            return true;
        }

        // Check if current post contains notification-related content
        if ($post && self::postContainsNotificationContent($post)) {
            return true;
        }

        // Check if page is built with Elementor and might contain notifications
        if (self::isElementorPage()) {
            return true;
        }

        // Check if theme or plugins inject notification content
        if (self::hasNotificationContentInjection()) {
            return true;
        }

        return false;
    }

    /**
     * Check if a post contains OnPage notification content.
     *
     * Searches post content for notification-related markers to determine
     * if our frontend assets are needed.
     *
     * @param \WP_Post $post The post to check.
     * @return bool True if post contains notification content, false otherwise.
     * @since 2.0.0
     */
    private static function postContainsNotificationContent(\WP_Post $post): bool
    {
        // Check for notification-related content markers
        $notification_markers = [
            'notifal-notification',
            'notifal-onpage',
            'notification',
            'popup',
            'modal'
        ];

        foreach ($notification_markers as $marker) {
            if (stripos($post->post_content, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if we're currently in Elementor editor mode.
     *
     * Detects various Elementor editor states to prevent notifications from showing
     * during page editing or preview within Elementor.
     *
     * @return bool True if in Elementor editor mode, false otherwise.
     * @since 2.0.0
     */
    private static function isElementorEditorMode(): bool
    {
        // Check if Elementor is active
        if (!PluginDetector::isElementorActive()) {
            return false;
        }

        // Check URL parameters that indicate Elementor editor mode (sanitized per WordPress guidelines)
        $get_action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
        if ( $get_action === 'elementor' ) {
            return true;
        }

        // Check if we're in Elementor preview mode
        if ( isset( $_GET['elementor-preview'] ) ) {
            return true;
        }

        // Check if we're in Elementor editor iframe
        if ( isset( $_GET['elementor-preview'] ) || ( isset( $_GET['preview'] ) && isset( $_GET['preview_id'] ) ) ) {
            return true;
        }

        // Check for REST API requests related to Elementor
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
            if ( $request_uri !== '' && strpos( $request_uri, 'elementor' ) !== false ) {
                return true;
            }
        }

        // Check if Elementor is in preview mode
        if (PluginDetector::isElementorActive() && \Elementor\Plugin::$instance->preview->is_preview_mode()) {
            return true;
        }

        return false;
    }

    /**
     * Check if we're currently in template preview mode.
     *
     * Detects if we're viewing a notifal template preview to prevent
     * OnPage notifications from interfering with template preview.
     *
     * @return bool True if in template preview mode, false otherwise.
     * @since 2.0.0
     */
    private static function isTemplatePreviewMode(): bool
    {
        // Check for notifal template preview parameter (parameter presence only; value not output)
        if ( isset( $_GET['notifal_template_preview'] ) ) {
            return true;
        }

        // Check if current post is a notifal_template being previewed
        global $post;
        if ( $post && $post->post_type === 'notifal_template' ) {
            // Additional check for preview context
            if ( isset( $_GET['preview'] ) || isset( $_GET['preview_id'] ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if we're in a REST API request related to previews.
     *
     * Prevents OnPage notifications from loading during REST API requests
     * that are used for editor previews or template operations.
     *
     * @return bool True if preview-related REST request, false otherwise.
     * @since 2.0.0
     */
    private static function isPreviewRelatedRestRequest(): bool
    {
        // Only check if this is a REST API request
        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            return false;
        }

        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Check for Elementor-related REST endpoints
        $elementor_endpoints = [
            'elementor',
            'preview',
            'autosave',
            'revisions'
        ];

        foreach ($elementor_endpoints as $endpoint) {
            if (strpos($request_uri, $endpoint) !== false) {
                return true;
            }
        }

        // Check for notifal template-related REST endpoints
        if (strpos($request_uri, 'notifal_template') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Check if current page is built with Elementor.
     *
     * Integrates with Elementor to detect if the page is built with Elementor,
     * which might contain notifications.
     *
     * @return bool True if Elementor page, false otherwise.
     * @since 2.0.0
     */
    private static function isElementorPage(): bool
    {
        // Check if Elementor is active
        if (!PluginDetector::isElementorActive()) {
            return false;
        }

        global $post;
        if (!$post) {
            return false;
        }

        // Check if post is built with Elementor
        return ElementorHelper::hasBuilder($post);
    }

    /**
     * Check if other plugins or theme inject OnPage notification content.
     *
     * Provides a hook-based system for other components to indicate
     * that OnPageNotification frontend assets should be loaded.
     *
     * @return bool True if content injection is detected, false otherwise.
     * @since 2.0.0
     */
    private static function hasNotificationContentInjection(): bool
    {
        /**
         * Filter to indicate if OnPageNotification frontend assets should be loaded.
         *
         * Other plugins or theme components can use this filter to force
         * loading of OnPageNotification frontend assets when they inject notification content.
         *
         * @since 2.0.0
         * @param bool $has_injection Whether OnPage notification content is injected.
         */
        return apply_filters('notifal_has_onpage_notification_content_injection', false);
    }

    /**
     * Check if the current page contains any OnPage notification elements in DOM.
     *
     * This is a fallback method that checks if notification elements
     * are present in the page content after rendering.
     *
     * @return bool True if notification elements are found, false otherwise.
     * @since 2.0.0
     */
    private static function pageContainsOnPageNotificationElements(): bool
    {
        /**
         * Filter to force loading OnPageNotification frontend assets.
         *
         * Components can use this filter to indicate that OnPageNotification
         * JavaScript should be loaded even if not detected earlier.
         *
         * @since 2.0.0
         * @param bool $contains_notifications Whether page contains OnPage notification elements.
         */
        return apply_filters('notifal_page_contains_onpage_notification_elements', false);
    }

    /**
     * Localize the frontend script with necessary configuration data.
     *
     * Provides REST API endpoints, nonces, and localized strings
     * to the frontend JavaScript for proper functionality.
     * Immediate notifications are preloaded during page generation so they can
     * render before the eligibility REST request completes.
     *
     * @return void
     * @since 2.0.0
     */
    private static function localizeScript(): void
    {
        // Get current page context for display rules
        $currentPageContext = self::getCurrentPageContext();

        $config = [
            'apiEndpoint' => rest_url('notifal/v1/onpage/eligible'),
            'trackingEndpoint' => rest_url('notifal/v1/onpage/track'),
            'preferencesEndpoint' => rest_url('notifal/v1/onpage/preferences'),
            'nonce' => wp_create_nonce('wp_rest'),
            'productClickNonce' => wp_create_nonce('notifal_product_click_tracking'),
            'url' => UrlHelper::baseAjax(),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'locale' => get_locale(),
            'rtl' => is_rtl(),
            'strings' => self::getFrontendStrings(),
            'context' => $currentPageContext,
            // @since 2.3.10 Cached Users rule payload map for cache-safe client login checks.
            'clientUserRulesIndex' => ClientUserRulesBuilder::buildActiveNotificationsIndex(),
            'immediateNotifications' => self::getImmediateNotificationsForPreload($currentPageContext),
            'siteName' => get_bloginfo('name'),
            'analyticsTrackClickClass' => sanitize_html_class(
                (string) apply_filters(FilterHooks::ONPAGE_ANALYTICS_TRACK_CLICK_CLASS, 'notifal-track-click')
            ),
        ];

        if (self::isPreviewMode()) {
            $previewId = isset($_GET['notifal_onpage_preview']) ? absint($_GET['notifal_onpage_preview']) : 0;
            if ($previewId > 0) {
                $config['isPreview'] = true;
                $config['previewNotificationId'] = $previewId;
                $config['previewEndpoint'] = self::getPreviewEndpointWithToken($previewId);
                $config['immediateNotifications'] = [];
            }
        }

        if (PluginDetector::isWooCommerceActive()) {
            $config['ajaxAddToCartUrl'] = admin_url('admin-ajax.php');
            $config['ajaxAddToCartNonce'] = wp_create_nonce('notifal_ajax_add_to_cart');

            // @since 2.3.10 WooCommerce system page IDs for client-side display rule evaluation.
            $config['woocommercePageIds'] = PageContextHelper::getWooCommerceSystemPageIds();

            // @since 2.3.7 Only expose cart REST refresh when an active notification uses cart display rules.
            $requiresCartContext = CartDisplayRulesUsageChecker::anyActiveNotificationUsesCartRules();
            $config['requiresCartContext'] = $requiresCartContext;

            if ($requiresCartContext) {
                // @since 2.3.5 REST endpoint to refresh cart snapshot after Ajax cart changes.
                $config['cartContextEndpoint'] = rest_url('notifal/v1/onpage/cart-context');
            }
        }

        wp_localize_script('notifal-onpage-frontend-bundle', 'notifalOnPageConfig', $config);
    }

    /**
     * Preload eligible notifications configured for immediate display.
     *
     * @param array $context Current page context for eligibility checks.
     * @return array Prepared notification payloads for instant frontend rendering.
     * @since 2.3.7
     */
    private static function getImmediateNotificationsForPreload(array $context): array
    {
        if (self::isPreviewMode()) {
            return [];
        }

        try {
            $eligibilityService = notifal_app(EligibilityService::class);
            $eligibleNotifications = $eligibilityService->getEligibleNotifications($context);
            $immediateNotifications = [];

            foreach ($eligibleNotifications as $notification) {
                $showTiming = isset($notification['timing']['show_timing'])
                    ? (string) $notification['timing']['show_timing']
                    : '';

                if ($showTiming !== 'immediate') {
                    continue;
                }

                $immediateNotifications[] = $notification;
            }


            return $immediateNotifications;
        } catch (\Throwable $exception) {
            Helper::log(
                '[Notifal OnPage] Failed to preload immediate notifications: ' . $exception->getMessage()
            );

            return [];
        }
    }

    /**
     * Build preview REST URL with a short-lived token so the endpoint can authenticate without cookie.
     *
     * @param int $previewId Notification post ID
     * @return string Preview endpoint URL with _preview_token query arg
     * @since 2.0.0
     */
    private static function getPreviewEndpointWithToken(int $previewId): string
    {
        $token = wp_generate_password(32, false);
        set_transient('notifal_onpage_preview_token_' . $token, $previewId, 60);
        $url = add_query_arg('id', $previewId, rest_url('notifal/v1/onpage/preview'));
        return add_query_arg('_preview_token', $token, $url);
    }

    /**
     * Check if the current request is a valid OnPage notification preview (admin, nonce, param).
     *
     * @return bool
     * @since 2.0.0
     */
    private static function isPreviewMode(): bool
    {
        $previewId = isset($_GET['notifal_onpage_preview']) ? absint($_GET['notifal_onpage_preview']) : 0;
        if ($previewId <= 0) {
            return false;
        }
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        return wp_verify_nonce($nonce, 'notifal_onpage_preview') && current_user_can('edit_posts');
    }

    /**
     * Get frontend language strings.
     *
     * @return array Localized strings for frontend
     * @since 2.0.0
     */
    private static function getFrontendStrings(): array
    {
        // Load frontend language strings
        $langPath = ModulePaths::jsLangPath() . 'frontend.php';
        
        if (file_exists($langPath)) {
            return include $langPath;
        }
        
        // Return default strings if language file doesn't exist
        return [
            'notification' => 'Notification',
            'close' => 'Close',
            'loading' => 'Loading...',
            'error' => 'Error',
            'success' => 'Success',
        ];
    }

    /**
     * Get current page context data for frontend display rules checking.
     *
     * Provides page ID, URL, post type, and other context data needed
     * for proper display rules evaluation on the frontend.
     *
     * @return array Current page context data
     * @since 2.0.0
     */
    private static function getCurrentPageContext(): array
    {
        // Get current page/post ID
        $pageId = null;
        $postType = null;
        $url = '';
        
        // Try to get the current post/page
        if (is_singular()) {
            $pageId = get_the_ID();
            $postType = get_post_type($pageId);
        } elseif (is_home() && !is_front_page()) {
            // Blog home page
            $pageId = get_option('page_for_posts');
            $postType = 'page';
        } elseif (is_front_page()) {
            // Front page
            if (get_option('show_on_front') === 'page') {
                $pageId = get_option('page_on_front');
                $postType = 'page';
            }
        } elseif (is_category()) {
            // Category archive
            $pageId = get_queried_object_id();
            $postType = 'category';
        } elseif (is_tag()) {
            // Tag archive
            $pageId = get_queried_object_id();
            $postType = 'tag';
        } elseif (function_exists('is_product_category') && is_product_category()) {
            // WooCommerce product category archive
            $pageId = get_queried_object_id();
            $postType = 'product_category';
        } elseif (function_exists('is_product_tag') && is_product_tag()) {
            // WooCommerce product tag archive
            $pageId = get_queried_object_id();
            $postType = 'product_tag';
        } elseif (function_exists('is_shop') && is_shop()) {
            // WooCommerce shop uses a product archive query but is assigned to a Page post.
            if (function_exists('wc_get_page_id')) {
                $shopPageId = absint(wc_get_page_id('shop'));
                if ($shopPageId > 0) {
                    $pageId = $shopPageId;
                    $postType = 'page';
                }
            }
        } elseif (is_tax()) {
            // Custom post type and other taxonomy archives.
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $pageId = (int) $term->term_id;
                $postType = sanitize_key($term->taxonomy);
            }
        } elseif (is_archive()) {
            // Other archives
            $pageId = get_queried_object_id();
            $postType = 'archive';
        }

        // Get current URL
        if (isset($_SERVER['REQUEST_URI'])) {
            $url = esc_url_raw($_SERVER['REQUEST_URI']);
        }

        // Detect device type (basic detection)
        $deviceType = 'desktop';
        if (wp_is_mobile()) {
            $deviceType = 'mobile';
        }

        // Build base context; taxonomy fields are enriched for all singular post types below.
        $archiveTaxonomy = '';
        if (function_exists('is_tax') && is_tax()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $archiveTaxonomy = sanitize_key($term->taxonomy);
            }
        }

        $context = [
            'page_id' => $pageId,
            'url' => $url,
            'post_type' => $postType,
            'archive_taxonomy' => $archiveTaxonomy,
            'device_type' => $deviceType,
            'is_admin' => current_user_can('manage_options'),
            'timestamp' => current_time('timestamp'),
            'locale' => get_locale(),
            'categories' => [],
            'product_categories' => [],
            'is_front_page' => is_front_page(),
            'is_posts_home' => is_home() && !is_front_page(),
            'is_shop_page' => function_exists('is_shop') && is_shop(),
            'is_cart_page' => function_exists('is_cart') && is_cart(),
            'is_checkout_page' => function_exists('is_checkout') && is_checkout(),
            'is_account_page' => function_exists('is_account_page') && is_account_page(),
            'is_singular_query' => is_singular()
                && !is_page()
                && !is_front_page()
                && !(is_home() && !is_front_page())
                && !(function_exists('is_shop') && is_shop()),
        ];

        // @since 2.3.10 Align page-load auth context with REST eligibility enforcement.
        $authContext = VisitorAuthContextResolver::resolve($context);
        $context['user_id'] = $authContext['user_id'];
        $context['is_logged_in'] = $authContext['is_logged_in'];
        $context['user_roles'] = $authContext['user_roles'];

        $context = (new PageContextEnricher())->enrich($context);
        $context = PageContextHelper::attachSmartTargetingViewFlags($context);

        /**
         * Filter the current page context data passed to frontend JavaScript.
         *
         * Allows developers to modify or extend the context data used
         * for display rules evaluation on the frontend.
         *
         * @since 2.0.0
         * @param array $context Current page context data
         */
        return apply_filters(FilterHooks::ONPAGE_FRONTEND_CONTEXT, $context);
    }

    /**
     * Check if Templates frontend assets should be loaded due to OnPage notifications.
     *
     * Uses a lightweight meta query to check if any active notification references a template,
     * without triggering the expensive eligibility pipeline or template rendering.
     *
     * @param bool $has_injection Current injection status
     * @return bool Whether Templates frontend assets should be loaded
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function maybeEnableTemplatesAssets(bool $has_injection): bool
    {
        if ($has_injection) {
            return true;
        }

        if (!self::shouldLoadAssets()) {
            return false;
        }

        try {
            // Lightweight check: query active notification meta for template_id
            // without triggering expensive template rendering or eligibility pipeline
            $activeNotifications = NotificationQuery::getAll();

            foreach ($activeNotifications as $notification) {
                $templateId = get_post_meta($notification->ID, '_notifal_template_id', true);

                if (!empty($templateId)) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            return true;
        }

        return false;
    }
}
