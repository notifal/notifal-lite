<?php
/**
 * Main plugin bootstrap loader.
 *
 * Coordinates the initialization of all Notifal components including
 * infrastructure services, domain services, and feature modules.
 *
 * @package Notifal\Bootstrap
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Bootstrap;

use Notifal\Domain\Tags\ServiceProvider;
use Notifal\Domain\Settings\ServiceProvider as SettingsServiceProvider;
use Notifal\Infrastructure\WordPress\Admin\Assets\AssetsRegistrar;
use Notifal\Infrastructure\WordPress\Admin\Menu\MenuServiceProvider;
use Notifal\Infrastructure\WordPress\StickyMenu\StickyMenuServiceProvider;
use Notifal\Infrastructure\WordPress\Bootstrap\Maintenance;
use Notifal\Shared\Services\NoticeService;
use Notifal\Infrastructure\WordPress\Database\DatabaseMigrationManager;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\DeactivationPopup\DeactivationPopupService;
use Notifal\Infrastructure\WordPress\ActivationPopup\ActivationPopupService;
use Notifal\Infrastructure\WordPress\WhatsNewPopup\WhatsNewPopupService;
use Notifal\Infrastructure\WordPress\ChangelogPopup\ChangelogPopupService;

defined('ABSPATH') || exit;

class Plugin
{
    /**
     * Register WordPress plugin hooks.
     *
     * Sets up the primary initialization hook that runs after WordPress
     * has finished loading plugins.
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        add_action('init', [self::class, 'init'], 0);
        Maintenance::register();
    }

    /**
     * Initialize Notifal after WordPress plugins are loaded.
     *
     * Orchestrates the bootstrap sequence: infrastructure first, then core domains,
     * followed by feature modules. Ensures proper dependency order.
     *
     * @return void
     * @since 2.0.0
     */
    public static function init(): void
    {
        self::boot_infrastructure();
        self::init_tags();
        self::init_settings();
        self::boot_modules();

        /**
         * Fires when Notifal is fully initialized and all modules are booted.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::PLUGIN_INIT);
    }

    /**
     * Initialize Tag system.
     *
     * Tags are a core dependency required by other modules.
     *
     * @return void
     * @since 2.0.0
     */
    protected static function init_tags(): void
    {
        ServiceProvider::register();
    }

    /**
     * Initialize Settings system.
     *
     * Bootstraps the domain-level settings functionality including
     * admin interface, repositories, and services.
     *
     * @return void
     * @since 2.0.0
     */
    protected static function init_settings(): void
    {
        SettingsServiceProvider::register();
    }

    /**
     * Boot shared infrastructure components.
     *
     * Initializes global services that are not module-specific,
     * including admin menus, assets, and database migrations.
     *
     * @return void
     * @since 2.0.0
     */
    protected static function boot_infrastructure(): void
    {
        DatabaseMigrationManager::init();

        $services = [
            MenuServiceProvider::class,
            AssetsRegistrar::class,
            NoticeService::class,
            DeactivationPopupService::class,
            ActivationPopupService::class,
            WhatsNewPopupService::class,
            ChangelogPopupService::class,
            StickyMenuServiceProvider::class,
        ];

        /**
         * Filter the list of global infrastructure services.
         *
         * @hook notifal/infrastructure/services
         * @param string[] $services List of FQCNs with register() method.
         * @return string[]
         */
        $services = apply_filters(FilterHooks::INFRASTRUCTURE_SERVICES, $services);

        foreach ($services as $service) {
            if (class_exists($service) && method_exists($service, 'register')) {
                $service::register();
            }
        }
    }

    /**
     * Load all ServiceProviders from active modules.
     *
     * Dynamically discovers and initializes all feature modules
     * by scanning the Modules directory for ServiceProvider classes.
     *
     * @return void
     * @since 2.0.0
     */
    protected static function boot_modules(): void
    {
        $modules_dir = NOTIFAL_PATH . 'app/Modules';

        foreach (glob($modules_dir . '/*/ServiceProvider.php') as $provider_file) {
            $module_name = basename(dirname($provider_file));
            $fqcn = "Notifal\\Modules\\{$module_name}\\ServiceProvider";

            if (class_exists($fqcn)) {
                (new $fqcn)->register();
            }
        }
    }
}
