<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\Assets;

use Notifal\Core\Support\Helpers\UrlHelper;
use Notifal\Infrastructure\WordPress\Admin\Localization\LangLoader;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Security\NonceManager;
use Notifal\Shared\Config\Paths;

defined('ABSPATH') || exit;

/**
 * Class ListAssets
 *
 * Handles admin enqueueing of scripts and styles specifically for the OnPage Notification list screen.
 * Manages assets for status toggle, import functionality, and pre-created notifications archive.
 *
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Assets
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ListAssets
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
     * Enqueue all styles and scripts required for the OnPage list screen.
     * Validates screen context and loads assets for import, status toggle, and archive functionality.
     *
     * @return void
     * @since 2.0.0
     */
    public static function enqueue(): void
    {
        // Validate screen context
        $screen = get_current_screen();
        $validScreens = [
            'notifal_page_notifal-onpage-notifications',
            'toplevel_page_notifal-onpage-notifications',
            'notifal_page_notifal-onpage-notification'
        ];

        if (!$screen || !in_array($screen->id, $validScreens)) {
            return;
        }

        /**
         * Fires before enqueuing Notifal OnPage list admin assets.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::ONPAGE_ADMIN_LIST_ASSETS_BEFORE_ENQUEUE);

        // Enqueue assets
        self::enqueueImportAssets();
        self::enqueueStatusToggleAssets();
        self::enqueueArchiveAssets();

        /**
         * Fires after enqueuing Notifal OnPage list admin assets.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::ONPAGE_ADMIN_LIST_ASSETS_AFTER_ENQUEUE);
    }

    /**
     * Enqueue import functionality assets.
     * Loads translations and AJAX configuration for notification import.
     *
     * @return void
     * @since 2.0.0
     */
    private static function enqueueImportAssets(): void
    {
        // Load import translations
        $importTranslations = LangLoader::load(__NAMESPACE__, 'import.php');

        // Localize import translations for shared admin script
        wp_localize_script(
            'notifal-shared-admin-js',
            'notifalL10n',
            $importTranslations
        );

        // Localize AJAX configuration for import functionality
        wp_localize_script(
            'notifal-shared-admin-js',
            'NotifalOnPageImportAjax',
            self::getImportAjaxConfig()
        );
    }

    /**
     * Enqueue status toggle functionality assets.
     * Loads script and AJAX configuration for notification status toggling.
     *
     * @return void
     * @since 2.0.0
     */
    private static function enqueueStatusToggleAssets(): void
    {
        // Load translations for status toggle functionality
        $translations = LangLoader::load(__NAMESPACE__, 'global.php');

        // Enqueue status toggle script with translations
        notifal_enqueue_script(
            'notifal-onpage-status-toggle',
            Paths::jsAdminBuildUrl() . 'OnPageStatusToggleScript.js',
            ['notifal-shared-admin-js'],
            $translations,
            'NotifalOnPageListStrings'
        );

        // Localize AJAX configuration for status toggle
        wp_localize_script(
            'notifal-onpage-status-toggle',
            'NotifalOnPageToggleAjax',
            self::getStatusToggleAjaxConfig()
        );
    }

    /**
     * Enqueue pre-created notifications archive assets.
     * Loads styles, scripts, and configuration for the archive functionality.
     *
     * @return void
     * @since 2.0.0
     */
    private static function enqueueArchiveAssets(): void
    {
        // Enqueue archive styles
        notifal_enqueue_style(
            'notifal-precreated-archive-admin',
            Paths::cssAdminBuildUrl() . 'OnPagePrecreatedArchiveStyle.css',
            []
        );

        // Load archive translations
        $archiveTranslations = LangLoader::load(__NAMESPACE__, 'import.php');

        // Enqueue archive script with translations
        notifal_enqueue_script(
            'notifal-precreated-archive-admin',
            Paths::jsAdminBuildUrl() . 'OnPagePrecreatedArchiveScript.js',
            ['notifal-shared-admin-js'],
            self::getArchiveTranslations($archiveTranslations),
            'notifalPreCreatedArchive'
        );
    }

    /**
     * Get AJAX configuration for import functionality.
     *
     * @return array AJAX configuration with nonce and URL
     * @since 2.0.0
     */
    private static function getImportAjaxConfig(): array
    {
        return [
            'nonce' => [
                'import' => NonceManager::create('notifal_import_onpage_notification_ajax_nonce'),
            ],
            'ajax_url' => UrlHelper::baseAjax(),
        ];
    }

    /**
     * Get AJAX configuration for status toggle functionality.
     *
     * @return array AJAX configuration with nonces and URL
     * @since 2.0.0
     */
    private static function getStatusToggleAjaxConfig(): array
    {
        return [
            'nonce' => [
                'toggle_status' => NonceManager::create('notifal_toggle_notification_status'),
                'check_multiple_allowed' => NonceManager::create('notifal_check_multiple_notifications_allowed'),
            ],
            'ajax_url' => UrlHelper::baseAjax(),
        ];
    }

    /**
     * Get archive translations with AJAX configuration.
     * Merges translation strings with nonce and AJAX URL for archive functionality.
     *
     * @param array $translations Translation strings from language file
     * @return array Complete configuration with translations and AJAX data
     * @since 2.0.0
     */
    private static function getArchiveTranslations(array $translations): array
    {
        return [
            'nonce' => NonceManager::create('notifal_admin_ajax_nonce'),
            'ajax_url' => UrlHelper::baseAjax(),
            'strings' => [
                'loading' => __('Loading...', 'notifal'),
                'error' => __('An error occurred. Please try again.', 'notifal'),
                'no_results' => __('No notifications found matching your criteria.', 'notifal'),
                'load_more' => __('Load More', 'notifal'),
                'search' => __('Search', 'notifal'),
                'clear_filters' => __('Clear All Filters', 'notifal'),
                'close' => __('Close', 'notifal'),
                'view_details' => __('View Details', 'notifal'),
                'import' => $translations['importNotifications'] ?? __('Import', 'notifal'),
                'importing' => $translations['importing'] ?? __('Importing...', 'notifal'),
                'importSuccess' => $translations['importSuccess'] ?? __('Successfully imported {count} notification(s).', 'notifal'),
                'importPartialSuccess' => $translations['importPartialSuccess'] ?? __('Import completed: {success} succeeded, {failed} failed.', 'notifal'),
                'importFailed' => $translations['importFailed'] ?? __('Import failed.', 'notifal'),
                'networkError' => $translations['networkError'] ?? __('Network error. Please try again.', 'notifal'),
            ],
        ];
    }
} 
