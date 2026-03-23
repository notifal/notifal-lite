<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\Assets;

use Notifal\Core\Support\Helpers\UrlHelper;
use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Infrastructure\WordPress\Admin\Localization\LangLoader;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Security\NonceManager;
use Notifal\Shared\Config\Paths;

defined('ABSPATH') || exit;

/**
 * Class EditorAssets
 *
 * Handles admin enqueueing of scripts and styles specifically for the OnPage Notification editor screen.
 *
 * @since 2.0.0
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Assets
 * @author Hossein <hossein@notifal.com>
 */
class EditorAssets
{
    /**
     * Register WordPress hook to enqueue assets.
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    /**
     * Enqueue all styles and scripts required for the OnPage editor screen.
     *
     * @return void
     * @since 2.0.0
     */
    public static function enqueue(): void
    {
        $screen = get_current_screen();

        // Only enqueue on OnPage editor screens (edit/add pages)
        $validEditorScreens = [
            'notifal_page_notifal-onpage-notification', // Edit page
            'post', // When editing a notification post
        ];

        if (! $screen || !in_array($screen->id, $validEditorScreens)) return;
        
        /**
         * Fires before enqueuing Notifal OnPage admin assets.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::ONPAGE_ADMIN_ASSETS_BEFORE_ENQUEUE);

        $version   = NOTIFAL_VERSION;
        $cssUrl    = Paths::cssAdminBuildUrl();
        $jsUrl     = Paths::jsAdminBuildUrl();

        // Enqueue WordPress media scripts for media upload functionality
        wp_enqueue_media();

        // Enqueue main admin stylesheet
        notifal_enqueue_style(
            'notifal-onpage-editor-style',
            $cssUrl . 'OnPageAdminEditorStyle.css',
            []
        );

        $translations = LangLoader::load(__NAMESPACE__);

        notifal_enqueue_script(
            'notifal-onpage-editor-script',
            $jsUrl . 'OnPageAdminEditorScript.js',
            ['notifal-shared-admin-js'],
            $translations,
            'NotifalOnPageStrings'
        );

        wp_localize_script('notifal-onpage-editor-script', 'NotifalOnPageAjax', self::getAjaxConfig());
        wp_localize_script('notifal-onpage-editor-script', 'NotifalOnPageConfig', self::getNotifalConfig());

        /**
         * Fires after enqueuing Notifal OnPage admin assets.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::ONPAGE_ADMIN_ASSETS_AFTER_ENQUEUE);
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
            'nonce' => [
                'add_label'   => NonceManager::create('notifal_add_label'),
                'remove_label'=> NonceManager::create('notifal_remove_label'),
                'load_more_templates' => NonceManager::create('notifal_load_more_templates'),
                'search' => NonceManager::create('notifal_search_nonce'),
                'get_template_content' => NonceManager::create('notifal_get_template_content'),
                'get_filtered_templates' => NonceManager::create('notifal_get_filtered_templates'),
                'save_notification' => NonceManager::create('notifal_save_notification'),
                'get_notification_data' => NonceManager::create('notifal_get_notification_data'),
                'get_campaign_data' => NonceManager::create('notifal_campaign_save'),
            ],
            'ajax_url' => UrlHelper::baseAjax(),
        ];
    }

    /**
     * Get Notifal configuration for JavaScript.
     *
     * @return array Notifal configuration data
     * @since 2.0.0
     * @since 2.2.0 Added `scheduleDisplayTimezone` and `scheduleGmtOffsetHours` for admin schedule fields.
     */
    private static function getNotifalConfig(): array
    {
        // Add WooCommerce detection and generated post types for JavaScript
        $settingsService = notifal_app(\Notifal\Domain\Settings\Services\SettingsService::class);
        $generatedPostTypes = $settingsService->get('generated_posttype_list', []);

        // Get post type metadata for each generated post type
        $postTypesData = [];
        foreach ($generatedPostTypes as $postType) {
            $postTypeObject = get_post_type_object($postType);
            if ($postTypeObject) {
                $taxonomies = get_object_taxonomies($postType, 'names');
                $postTypesData[$postType] = [
                    'label' => $postTypeObject->labels->singular_name,
                    'plural_label' => $postTypeObject->labels->name,
                    'taxonomies' => array_values($taxonomies)
                ];
            }
        }

        return [
            'isWooCommerceActive' => PluginDetector::isWooCommerceActive(),
            'generatedPostTypes' => $generatedPostTypes,
            'postTypes' => $postTypesData,
            // Use secure hook that only the legitimate pro plugin can provide
            'is_pro_active' => apply_filters('notifal_pro_enhanced_analytics_allowed', false),
            'upgrade_url' => Urls::withPluginUtm(Urls::PRICING, 'wordpress_plugin', 'notifal_pro_upgrade'),
            'plugin_url' => plugin_dir_url(NOTIFAL_FILE),
            /**
             * IANA timezone from Settings → General. Used to format stored UTC schedule
             * values for datetime-local without using the browser's local zone.
             */
            'scheduleDisplayTimezone' => (string) get_option( 'timezone_string', '' ),
            /**
             * Floating offset in hours when no `timezone_string` is set (Settings → General).
             * Used as fallback for schedule display formatting only.
             */
            'scheduleGmtOffsetHours' => (float) get_option( 'gmt_offset', 0.0 ),
        ];
    }

}
