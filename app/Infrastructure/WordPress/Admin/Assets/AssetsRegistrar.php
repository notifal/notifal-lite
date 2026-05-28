<?php


namespace Notifal\Infrastructure\WordPress\Admin\Assets;

use Notifal\Core\Support\Helpers\UrlHelper;
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

        add_action('admin_enqueue_scripts', [self::class, 'enqueueIcons'], 1);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue'], 1);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueDashboardWidget'], 1);
        add_action('admin_footer', [self::class, 'injectGlobalToast']);
    }

    /**
     * Enqueue notifal-icons.css globally on all admin pages.
     */
    public static function enqueueIcons(): void
    {
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
            ]
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
