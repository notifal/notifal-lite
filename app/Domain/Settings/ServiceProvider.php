<?php

namespace Notifal\Domain\Settings;

defined('ABSPATH') || exit;

use Notifal\Core\Foundation\AbstractServiceProvider;
use Notifal\Core\Foundation\Container;
use Notifal\Domain\Settings\Services\SettingsService;
use Notifal\Domain\Settings\Services\SettingsSanitizationService;
use Notifal\Domain\Settings\Repositories\SettingsRepository;
use Notifal\Infrastructure\WordPress\Admin\Settings\Controllers\Ajax\SettingsAjaxController;
use Notifal\Infrastructure\WordPress\Admin\Settings\Controllers\SettingsAssetsController;
use Notifal\Infrastructure\WordPress\Admin\Settings\Controllers\SettingsMenuController;
use Notifal\Infrastructure\WordPress\Admin\Settings\Services\PostTypeDiscoveryService;
use Notifal\Infrastructure\WordPress\Security\NonceManager;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\UrlService;
use Notifal\Shared\Traits\IntegrityVerificationTrait;

/**
 * Settings service provider
 * 
 * Bootstraps all settings-related services and controllers.
 * Registers settings functionality as singleton services.
 * 
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ServiceProvider extends AbstractServiceProvider
{
    use IntegrityVerificationTrait;
    /**
     * List of services to be registered
     * 
     * @var array
     * @since 2.0.0
     */
    protected static array $services = [
        SettingsAjaxController::class,
        SettingsAssetsController::class,
        SettingsMenuController::class,
        PostTypeDiscoveryService::class,
    ];

    /**
     * Boot the service provider
     * 
     * Registers singleton services for settings management.
     * Sets up dependency injection for settings functionality.
     * 
     * @return void
     * @since 2.0.0
     */
    public function boot(): void
    {
        $container = Container::getInstance();

        // Register SettingsRepository as singleton
        $container->singleton(SettingsRepository::class, function () {
            return new SettingsRepository();
        });

        // Register SettingsService as singleton
        $container->singleton(SettingsService::class, function () use ($container) {
            return new SettingsService(
                $container->get(SettingsRepository::class)
            );
        });

        // Register SettingsSanitizationService as singleton
        $container->singleton(SettingsSanitizationService::class, function () use ($container) {
            return new SettingsSanitizationService(
                $container->get(SettingsService::class)
            );
        });

        // Register PostTypeDiscoveryService as singleton
        $container->singleton(PostTypeDiscoveryService::class, function () {
            return new PostTypeDiscoveryService();
        });

        // Register SettingsAjaxController as singleton
        $container->singleton(SettingsAjaxController::class, function () use ($container) {
            $ajaxController = new SettingsAjaxController(
                $container->get(SettingsService::class),
                $container->get(SettingsSanitizationService::class),
                $container->get(PostTypeDiscoveryService::class),
                $container->get(NonceManager::class)
            );

            // Register WordPress AJAX hooks
            $ajaxController->register();

            return $ajaxController;
        });

        // Register SettingsAssetsController as singleton
        $container->singleton(SettingsAssetsController::class, function () use ($container) {
            $assetsController = new SettingsAssetsController(
                $container->get(SettingsService::class),
                $container->get(NonceManager::class),
                $container->get(UrlService::class)
            );

            // Register WordPress asset hooks
            $assetsController->register();

            return $assetsController;
        });

        // Register SettingsMenuController as singleton
        $container->singleton(SettingsMenuController::class, function () use ($container) {
            $menuController = new SettingsMenuController(
                $container->get(SettingsService::class),
                $container->get(SettingsSanitizationService::class),
                $container->get(NonceManager::class)
            );

            // Register WordPress menu hooks
            $menuController->register();

            return $menuController;
        });

        // Initialize default settings on first plugin activation
        $this->initializeDefaultSettings();
        $this->verify_activation_guard_integrity();
    }

    /**
     * Initialize default settings if they don't exist
     * 
     * Sets up default settings values on first plugin activation.
     * Only creates missing settings, preserves existing values.
     * 
     * @return void
     * @since 2.0.0
     */
    private function initializeDefaultSettings(): void
    {
        $container = Container::getInstance();
        $repository = $container->get(SettingsRepository::class);
        
        // Initialize defaults if this is first time
        $repository->initializeDefaults();
    }


    /**
     * Hook to filter services if needed
     * 
     * @var string
     * @since 2.0.0
     */
    protected const FILTER_HOOK = FilterHooks::SETTINGS_SERVICES;
}

