<?php


namespace Notifal\Infrastructure\WordPress\Admin\Assets;

use Notifal\Core\Support\Helpers\UrlHelper;
use Notifal\Infrastructure\WordPress\Admin\Support\NasSupportWidgetRegistrar;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Shared\AdminUI\Toast\ToastRenderer;
use Notifal\Shared\Config\Paths;
use Notifal\Shared\Helpers\AdminScreenDetector;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class AdminAssetsRegistrar
 * Registers admin-side scripts and styles globally.
 *
 * @since 2.0.0
 * @package Notifal\Infrastructure\WordPress\Admin\Assets
 */
class AssetsRegistrar
{
    /**
     * Register WordPress hooks for admin assets.
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        NasSupportWidgetRegistrar::register();

        add_action('admin_enqueue_scripts', [self::class, 'enqueueGlobalBackend'], 1);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue'], 1);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueDashboardWidget'], 1);
        add_action('admin_footer', [self::class, 'injectGlobalToast']);
    }

    /**
     * Enqueue global backend CSS and JS on all admin pages.
     *
     * Mirrors the GlobalBackendStyle / GlobalBackendScript Vite pair used for
     * admin-wide UI that is not limited to Notifal screens.
     *
     * @return void
     * @since 2.0.0
     */
    public static function enqueueGlobalBackend(): void
    {
        // Global backend stylesheet (icons + admin-wide UI helpers).
        notifal_enqueue_style(
            'notifal-global-backend',
            Paths::cssAdminBuildUrl().'GlobalBackendStyle.css',
            []
        );

        notifal_enqueue_style(
            'notifal-icons',
            Paths::cssAdminBuildUrl().'IconsAdminStyle.css',
            []
        );

        // Allow modules to inject localized data for the global backend script.
        $globalData = apply_filters(
            FilterHooks::ADMIN_GLOBAL_BACKEND_DATA,
            [
                'rtl' => is_rtl(),
            ]
        );

        // Global backend script (menu flyouts and other admin-wide behaviors).
        notifal_enqueue_script(
            'notifal-global-backend',
            Paths::jsAdminBuildUrl().'GlobalBackendScript.js',
            [],
            is_array($globalData) ? $globalData : [],
            'NotifalGlobalBackend'
        );
    }

    /**
     * Enqueue admin scripts and styles only on Notifal admin pages.
     *
     * @return void
     * @since 2.0.0
     */
    public static function enqueue(): void
    {
        if (!AdminScreenDetector::isNotifalPage()) {
            return;
        }

        notifal_enqueue_style(
            'notifal-shared-admin-css',
            Paths::cssAdminBuildUrl().'SharedAdminStyle.css',
            []
        );

        // Register and enqueue tags script first
        notifal_enqueue_script(
            'notifal-tags-js',
            Paths::jsAdminBuildUrl().'TagsAdminScript.js',
            ['jquery']
        );

        notifal_enqueue_script(
            'notifal-shared-admin-js',
            Paths::jsAdminBuildUrl().'SharedAdminScript.js',
            ['notifal-tags-js'],
            [
                'ajax_url' => UrlHelper::baseAjax(),
                'rtl' => is_rtl(),
                'nasSupport' => NasSupportWidgetRegistrar::localized_config(),
            ],
            'notifalSharedAdminJs'
        );
    }

    /**
     * Injects the global toast container into the admin footer.
     *
     * @return void
     * @since 2.0.0
     */
    public static function injectGlobalToast(): void
    {
        // Always render on Notifal pages
        if (!AdminScreenDetector::isNotifalPage()) {
            return;
        }

        // Render the global container for JavaScript toasts
        ToastRenderer::renderGlobalContainer();

        // Render any PHP-based toasts from URL parameters
        ToastRenderer::render();
    }

    /**
     * Enqueue the dashboard widget stylesheet on the WordPress Dashboard screen.
     *
     * @return void
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function enqueueDashboardWidget(): void
    {
        // Only load on the WordPress Dashboard screen (index.php)
        $screen = get_current_screen();

        if (!$screen || $screen->id !== 'dashboard') {
            return;
        }

        // Enqueue shared admin style so widget inherits Notifal design tokens
        notifal_enqueue_style(
            'notifal-shared-admin-css',
            Paths::cssAdminBuildUrl() . 'SharedAdminStyle.css',
            []
        );

        // Enqueue dedicated dashboard widget stylesheet
        notifal_enqueue_style(
            'notifal-dashboard-widget',
            Paths::cssAdminBuildUrl() . 'DashboardWidgetStyle.css',
            ['notifal-shared-admin-css', 'notifal-icons']
        );
    }
}
