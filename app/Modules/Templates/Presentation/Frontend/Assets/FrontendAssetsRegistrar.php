<?php

namespace Notifal\Modules\Templates\Presentation\Frontend\Assets;

use Notifal\Shared\Config\Paths;
use Notifal\Modules\Templates\Config\Paths as ModulePaths;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Elementor\Helpers\ElementorHelper;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;

defined('ABSPATH') || exit;

/**
 * Class FrontendAssetsRegistrar
 *
 * Handles registration and enqueuing of Templates module frontend scripts and styles.
 * Manages asset loading for public-facing pages where Notifal Template content is displayed.
 * Follows Notifal Laravel-like architecture with proper module isolation.
 *
 * @package Notifal\Modules\Templates\Presentation\Frontend\Assets
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FrontendAssetsRegistrar
{
    /**
     * Register WordPress hooks for Templates frontend assets.
     *
     * Uses appropriate hooks to load assets only when needed for optimal performance.
     * Integrates with WordPress asset management and dependency system.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function register(): void
    {
        // Register assets early but don't enqueue them yet
        add_action('wp_enqueue_scripts', [self::class, 'registerAssets'], 5);
        
        // Enqueue assets conditionally based on page content
        add_action('wp_enqueue_scripts', [self::class, 'enqueueConditionalAssets'], 10);
        
        // Enqueue assets when specific Notifal Template content is detected
        add_action('wp_footer', [self::class, 'enqueueOnDemandAssets'], 5);
    }

    /**
     * Register all Templates frontend assets without enqueuing them.
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
         * Fires before registering Notifal Templates frontend assets.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::FRONTEND_ASSETS_BEFORE_REGISTER);

        // Register unified Templates frontend bundle
        wp_register_script(
            'notifal-templates-frontend-bundle',
            Paths::jsFrontendBuildUrl() . 'TemplatesFrontendBundle.js',
            [],
            NOTIFAL_VERSION,
            true
        );

        /**
         * Fires after registering Notifal Templates frontend assets.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::FRONTEND_ASSETS_AFTER_REGISTER);
    }

    /**
     * Enqueue assets conditionally based on page context.
     *
     * Analyzes the current page to determine if Templates assets should be loaded.
     * Checks for template usage, block presence, and other indicators.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function enqueueConditionalAssets(): void
    {
        // Check if we're on a page that might contain Templates content
        if (self::shouldLoadAssets()) {
            /**
             * Fires before conditionally enqueuing Notifal Templates frontend assets.
             *
             * @since 2.0.0
             */
            do_action(ActionHooks::FRONTEND_ASSETS_BEFORE_ENQUEUE);

            wp_enqueue_script('notifal-templates-frontend-bundle');

            // Localize the script with configuration data
            self::localizeScript();

            // Enqueue Elementor widget styles if they exist
            if (wp_style_is('notifal-elementor-widgets-style', 'registered')) {
                wp_enqueue_style('notifal-elementor-widgets-style');
            }

            /**
             * Fires after conditionally enqueuing Notifal Templates frontend assets.
             *
             * @since 2.0.0
             */
            do_action(ActionHooks::FRONTEND_ASSETS_AFTER_ENQUEUE);
        }
    }

    /**
     * Enqueue assets on-demand in the footer if Templates content is detected.
     *
     * This is a fallback method to ensure assets are loaded even if
     * conditional checks missed Templates content in the page.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function enqueueOnDemandAssets(): void
    {
        // Check if templates bundle is already enqueued
        $templates_bundle_enqueued = wp_script_is('notifal-templates-frontend-bundle', 'enqueued');

        if ($templates_bundle_enqueued) {
            return;
        }

        // Check if page contains any Template elements
        if (self::pageContainsTemplateElements()) {
            wp_enqueue_script('notifal-templates-frontend-bundle');

            // Localize the script with configuration data
            self::localizeScript();

            // Enqueue Elementor widget styles if they exist
            if (wp_style_is('notifal-elementor-widgets-style', 'registered')) {
                wp_enqueue_style('notifal-elementor-widgets-style');
            }
        }
    }

    /**
     * Determine if Templates assets should be loaded on the current page.
     *
     * Analyzes various factors to decide whether to load frontend assets.
     * Uses multiple detection methods for comprehensive coverage.
     * Excludes admin areas and editor contexts for optimal performance.
     *
     * @return bool True if assets should be loaded, false otherwise.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
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

        // Never load during REST API requests
        if (self::isRestApiRequest()) {
            return false;
        }

        global $post;

        // Always load on preview pages
        if (isset($_GET['notifal_template_preview'])) {
            return true;
        }

        // Check if current post contains Gutenberg blocks
        if ($post && self::postContainsNotifalBlocks($post)) {
            return true;
        }

        // Check if page is built with Elementor and contains Notifal widgets
        if (self::isElementorPageWithNotifalWidgets()) {
            return true;
        }

        // Check if theme or plugins inject Templates content
        if (self::hasNotifalContentInjection()) {
            return true;
        }

        return false;
    }

    /**
     * Check if a post contains Notifal Gutenberg blocks.
     *
     * Searches post content for Notifal block markers to determine
     * if our frontend assets are needed.
     *
     * @param \WP_Post $post The post to check.
     * @return bool True if post contains Notifal blocks, false otherwise.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function postContainsNotifalBlocks(\WP_Post $post): bool
    {
        // Check for Gutenberg block markers
        $notifal_blocks = [
            'notifal/action-button',
            'notifal/template-container'
        ];

        foreach ($notifal_blocks as $block) {
            if (strpos($post->post_content, '<!-- wp:' . $block) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if current page is built with Elementor and contains Notifal widgets.
     *
     * Integrates with Elementor to detect if the page contains any
     * Notifal widgets that require frontend JavaScript.
     *
     * @return bool True if Elementor page with Notifal widgets, false otherwise.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function isElementorPageWithNotifalWidgets(): bool
    {
        // Check if Elementor is active and page is built with Elementor
        if (!PluginDetector::isElementorActive()) {
            return false;
        }

        global $post;
        if (!$post) {
            return false;
        }

        // Check if post is built with Elementor
        if (!ElementorHelper::hasBuilder($post)) {
            return false;
        }

        // Get Elementor data to check for Notifal widgets
        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
        if (empty($elementor_data)) {
            return false;
        }

        // Check for Notifal widget types in Elementor data
        $notifal_widgets = [
            'notifal-action-button',
            'notifal-product-image',
            'notifal-close-icon'
        ];

        foreach ($notifal_widgets as $widget) {
            if (strpos($elementor_data, $widget) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if other plugins or theme inject Templates content.
     *
     * Provides a hook-based system for other components to indicate
     * that Templates frontend assets should be loaded.
     *
     * @return bool True if content injection is detected, false otherwise.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function hasNotifalContentInjection(): bool
    {
        /**
         * Filter to indicate if Templates frontend assets should be loaded.
         *
         * Other plugins or theme components can use this filter to force
         * loading of Templates frontend assets when they inject Templates content.
         *
         * @since 2.0.0
         * @param bool $has_injection Whether Templates content is injected.
         */
        return apply_filters('notifal_has_frontend_content_injection', false);
    }

    /**
     * Check if we're currently in Elementor editor mode.
     *
     * Detects various Elementor editor states to prevent assets from loading
     * during page editing within Elementor.
     *
     * @return bool True if in Elementor editor mode, false otherwise.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function isElementorEditorMode(): bool
    {
        // Check if Elementor is active
        if (!PluginDetector::isElementorActive()) {
            return false;
        }

        // Check URL parameters that indicate Elementor editor mode
        if (isset($_GET['action']) && $_GET['action'] === 'elementor') {
            return true;
        }

        // Check if we're in Elementor preview mode
        if (isset($_GET['elementor-preview'])) {
            return true;
        }

        // Check if we're in Elementor editor iframe
        if (isset($_GET['elementor-preview']) || isset($_GET['preview']) && isset($_GET['preview_id'])) {
            return true;
        }

        // Check for REST API requests related to Elementor
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $request_uri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($request_uri, 'elementor') !== false) {
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
     * Check if we're in a REST API request.
     *
     * Prevents assets from loading during REST API requests
     * that don't require frontend assets.
     *
     * @return bool True if REST API request, false otherwise.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function isRestApiRequest(): bool
    {
        // Check if this is a REST API request
        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            return false;
        }

        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Allow certain REST endpoints that might need assets
        $allowed_endpoints = [
            'notifal/v1/templates',
        ];

        foreach ($allowed_endpoints as $endpoint) {
            if (strpos($request_uri, $endpoint) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the current page contains any Template elements in DOM.
     *
     * This is a fallback method that checks if Template elements
     * are present in the page content after rendering.
     *
     * @return bool True if Template elements are found, false otherwise.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function pageContainsTemplateElements(): bool
    {
        /**
         * Filter to force loading Template frontend assets.
         *
         * Components can use this filter to indicate that Template
         * JavaScript should be loaded even if not detected earlier.
         *
         * @since 2.0.0
         * @param bool $contains_templates Whether page contains Template elements.
         */
        return apply_filters('notifal_page_contains_template_elements', false);
    }

    /**
     * Localize the frontend script with necessary configuration data.
     *
     * Provides REST API endpoints, nonces, and localized strings
     * to the frontend JavaScript for proper functionality.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function localizeScript(): void
    {
        $config = [
            'apiEndpoint' => rest_url('notifal/v1/templates/preview'),
            'nonce' => wp_create_nonce('notifal_templates_frontend_nonce'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'locale' => get_locale(),
            'rtl' => is_rtl(),
            'strings' => self::getFrontendStrings(),
        ];

        if (PluginDetector::isWooCommerceActive()) {
            $config['ajaxAddToCartUrl'] = admin_url('admin-ajax.php');
            $config['ajaxAddToCartNonce'] = wp_create_nonce('notifal_ajax_add_to_cart');
            $config['strings']['ajax_add_to_cart_select_variation_here'] = __('Please select a variation on this page, then try again.', 'notifal');
            $config['strings']['ajax_add_to_cart_select_variation'] = __('Go to the product page, select a variation, then add to cart.', 'notifal');
        }

        wp_localize_script('notifal-templates-frontend-bundle', 'notifalTemplatesConfig', $config);
    }

    /**
     * Get frontend language strings.
     *
     * @return array Localized strings for frontend
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
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
            'loading' => __('Loading...', 'notifal'),
            'error' => __('Error', 'notifal'),
            'success' => __('Success', 'notifal'),
        ];
    }
} 
