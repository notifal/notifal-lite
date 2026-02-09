<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\Assets;

use Notifal\Core\Support\Helpers\UrlHelper;
use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Infrastructure\WordPress\Admin\Localization\LangLoader;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Security\NonceManager;
use Notifal\Shared\Config\Paths;

defined('ABSPATH') || exit;

/**
 * Class AnalyticsAssets
 *
 * Handles enqueueing of CSS and JavaScript assets for the OnPage analytics dashboard.
 * Manages styles and scripts for analytics visualization, filtering, and data export functionality.
 *
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Assets
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class AnalyticsAssets
{
    /**
     * Register WordPress hook to enqueue analytics assets.
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    /**
     * Enqueue all styles and scripts required for the OnPage analytics dashboard.
     *
     * @return void
     * @since 2.0.0
     */
    public static function enqueue(): void
    {
        $screen = get_current_screen();

        // Only enqueue on OnPage analytics pages
        if (!$screen || strpos($screen->id, 'notifal-onpage-analytics') === false) {
            return;
        }

        /**
         * Fires before enqueuing Notifal OnPage admin assets.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::ONPAGE_ADMIN_ASSETS_BEFORE_ENQUEUE);

        $version = NOTIFAL_VERSION;
        $cssUrl = Paths::cssAdminBuildUrl();
        $jsUrl = Paths::jsAdminBuildUrl();

        // Enqueue main analytics stylesheet
        wp_enqueue_style(
            'notifal-analytics-dashboard',
            $cssUrl . 'OnPageAnalyticsStyle.css',
            [],
            $version
        );

        // Enqueue analytics script
        wp_enqueue_script(
            'notifal-analytics-dashboard',
            $jsUrl . 'OnPageAnalyticsScript.js',
            ['notifal-shared-admin-js'],
            $version,
            true
        );

        // Load translations
        $translations = LangLoader::load(__NAMESPACE__, 'analytics.php');

        wp_localize_script(
            'notifal-analytics-dashboard',
            'NotifalAnalyticsStrings',
            $translations
        );

        wp_localize_script('notifal-analytics-dashboard', 'NotifalAnalyticsAjax', self::getAjaxConfig());
        wp_localize_script('notifal-analytics-dashboard', 'NotifalAnalyticsConfig', self::getAnalyticsConfig());

        /**
         * Fires after enqueuing Notifal OnPage admin assets.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::ONPAGE_ADMIN_ASSETS_AFTER_ENQUEUE);
    }

    /**
     * Get analytics configuration for JavaScript.
     *
     * @return array Analytics configuration data
     * @since 2.0.0
     */
    private static function getAnalyticsConfig(): array
    {
        return [
            'charts' => self::getChartConfiguration()['charts'],
            'filters' => self::getFilterSettings()['filters'],
            'export' => self::getExportConfiguration()['export'],
            // Use secure hook that only the legitimate pro plugin can provide
            'is_pro_active' => apply_filters('notifal_pro_enhanced_analytics_allowed', false),
            'upgrade_url' => Urls::withPluginUtm(Urls::PRICING, 'wordpress_plugin', 'notifal_pro_upgrade'),
            'plugin_url' => plugin_dir_url(NOTIFAL_FILE),
        ];
    }

    /**
     * Get AJAX configuration for JavaScript.
     *
     * @return array AJAX configuration data
     * @since 2.0.0
     */
    private static function getAjaxConfig(): array
    {
        return [
            'ajaxUrl' => UrlHelper::baseAjax(),
            'nonce' => [
                'analytics' => NonceManager::create('notifal_analytics_nonce'),
                'export' => NonceManager::create('notifal_analytics_export_nonce'),
                'refresh' => NonceManager::create('notifal_analytics_refresh_nonce'),
            ],
            'restUrl' => rest_url('notifal/v1/'),
            'restNonce' => wp_create_nonce('wp_rest'),
        ];
    }


    /**
     * Get chart configuration for JavaScript.
     *
     * @return array Chart settings and colors
     * @since 2.0.0
     */
    private static function getChartConfiguration(): array
    {
        return [
            'charts' => [
                'colors' => [
                    'primary' => '#7e2bd2',
                    'secondary' => '#651bb0',
                    'success' => '#27ae60',
                    'warning' => '#f39c12',
                    'danger' => '#e74c3c',
                    'info' => '#3498db'
                ],
                'defaultHeight' => 300,
                'animation' => [
                    'enabled' => true,
                    'duration' => 1000
                ]
            ],
        ];
    }

    /**
     * Get filter settings for JavaScript.
     *
     * @return array Filter configuration
     * @since 2.0.0
     */
    private static function getFilterSettings(): array
    {
        return [
            'filters' => [
                'autoRefresh' => true,
                'refreshInterval' => 300000, // 5 minutes
                'debounceDelay' => 500
            ],
        ];
    }

    /**
     * Get export configuration for JavaScript.
     *
     * @return array Export settings
     * @since 2.0.0
     */
    private static function getExportConfiguration(): array
    {
        return [
            'export' => [
                'formats' => ['csv', 'json'],
                'maxRows' => 10000
            ]
        ];
    }
}
