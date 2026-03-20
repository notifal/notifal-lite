<?php

namespace Notifal\Modules\OnPageNotification;

use Notifal\Core\Foundation\AbstractServiceProvider;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Registration\PostTypeRegistrar;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Repositories\NotificationQuery;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Repositories\DatabaseRepository;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Registration\ApiRegistrar;
use Notifal\Modules\OnPageNotification\Presentation\Admin\Assets\EditorAssets;
use Notifal\Modules\OnPageNotification\Presentation\Admin\Assets\ListAssets;
use Notifal\Modules\OnPageNotification\Presentation\Admin\Assets\AnalyticsAssets;
use Notifal\Modules\OnPageNotification\Presentation\Frontend\Assets\FrontendAssetsRegistrar;
use Notifal\Modules\OnPageNotification\Presentation\Admin\Menu\MenuController;
use Notifal\Modules\OnPageNotification\Presentation\Admin\ListTable\ColumnsController;
use Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax\EditPageAjaxController;
use Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax\ImportController;
use Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\ExportController;
use Notifal\Modules\OnPageNotification\Presentation\Admin\Routes\AdminRouteController;
use Notifal\Modules\OnPageNotification\Presentation\Frontend\Routes\OnPagePreviewRouteController;
use Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax\LoadMoreTemplatesController;
use Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax\GetFilteredTemplatesController;
use Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax\AnalyticsTableController;
use Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax\PreCreatedNotificationsAjaxController;
use Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax\PreCreatedNotificationsImportController;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\DisplayRulesService;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\LabelService;
use Notifal\Modules\OnPageNotification\Contracts\LabelProviderInterface;
use Notifal\Modules\OnPageNotification\Application\Services\Tag\TagCategoryDetector;
use Notifal\Modules\OnPageNotification\Application\Services\Template\TemplateFilterService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\ContentSourceService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\ContentSourceFilterBuilder;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\AppearanceSettingsService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\BehaviorSettingsService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\TimingSettingsService;
use Notifal\Modules\OnPageNotification\Application\Services\Core\NotificationSaveService;
use Notifal\Infrastructure\WordPress\Admin\Helpers\AdminStatsService;
use Notifal\Modules\OnPageNotification\Application\Services\Analytics\AnalyticsService;
use Notifal\Modules\OnPageNotification\Application\Services\Analytics\ConversionTracker;
use Notifal\Modules\OnPageNotification\Helpers\AnalyticsHelper;
use Notifal\Modules\OnPageNotification\Helpers\NotificationHelper;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\UrlService;
use Notifal\Modules\OnPageNotification\Application\Services\Core\EligibilityService;
use Notifal\Modules\OnPageNotification\Application\Services\Core\NotificationDataPreparer;
use Notifal\Modules\OnPageNotification\Application\Services\Core\NotificationEligibilityChecker;
use Notifal\Modules\OnPageNotification\Application\Services\Analytics\TrackingService;
use Notifal\Modules\OnPageNotification\Application\Services\Core\EventQueue;
use Notifal\Modules\OnPageNotification\Application\Services\Analytics\TrackingDataValidator;
use Notifal\Modules\OnPageNotification\Application\Services\Core\GeolocationService;
use Notifal\Modules\OnPageNotification\Application\Services\Analytics\EventProcessor;
use Notifal\Modules\OnPageNotification\Application\Services\Core\CronService;
use Notifal\Modules\OnPageNotification\Application\Services\Template\FrontendTemplateRenderer;
use Notifal\Modules\OnPageNotification\Application\Services\Template\ElementorTemplateRenderer;
use Notifal\Modules\OnPageNotification\Application\Services\Template\BlockEditorTemplateRenderer;
use Notifal\Modules\OnPageNotification\Application\Services\Template\TemplateContextBuilder;
use Notifal\Modules\OnPageNotification\Application\Services\Tag\FrontendTagContextBuilder;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\WidgetContextProvider;
use Notifal\Modules\OnPageNotification\Application\Services\Core\CacheManager;
use Notifal\Modules\OnPageNotification\Application\Services\Core\PoolCacheManager;
use Notifal\Modules\OnPageNotification\Application\Services\Core\ElementorCacheManager;
use Notifal\Modules\OnPageNotification\Application\Services\Core\WordPressCacheManager;
use Notifal\Shared\Traits\IntegrityVerificationTrait;
use Notifal\Modules\OnPageNotification\Application\Services\API\PreCreatedNotificationsApiService;
use Notifal\Modules\OnPageNotification\Application\Services\Core\NotificationActivationGuard;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Database\MigrationService;
use Notifal\Core\Foundation\Container;
use Notifal\Domain\Orders\OrderFetcherInterface;
use Notifal\Domain\Products\ProductFetcherInterface;
use Notifal\Domain\Users\UserFetcherInterface;
use Notifal\Infrastructure\WordPress\WooCommerce\Services\OrderFetcher;
use Notifal\Infrastructure\WordPress\WooCommerce\Services\ProductFetcher;
use Notifal\Infrastructure\WordPress\Services\UserFetcher;
use Notifal\Infrastructure\WordPress\Services\NullOrderFetcher;
use Notifal\Infrastructure\WordPress\Services\NullProductFetcher;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Shared\Utils\Helper;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;

defined('ABSPATH') || exit;

/**
 * Service provider for OnPage Notification module.
 *
 * Registers all services, controllers, assets, and infrastructure components
 * required for the OnPage notification functionality including post types,
 * admin interfaces, frontend assets, analytics, and security constraints.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ServiceProvider extends AbstractServiceProvider
{
    use IntegrityVerificationTrait;

    protected static array $services = [
        PostTypeRegistrar::class,
        EditorAssets::class,
        ListAssets::class,
        AnalyticsAssets::class,
        FrontendAssetsRegistrar::class,
        ApiRegistrar::class,
        OnPagePreviewRouteController::class,
        MenuController::class,
        ColumnsController::class,
        AdminRouteController::class,
        EditPageAjaxController::class,
        ImportController::class,
        ExportController::class,
        LoadMoreTemplatesController::class,
        GetFilteredTemplatesController::class,
        AnalyticsTableController::class,
        PreCreatedNotificationsApiService::class,
        DisplayRulesService::class,
        LabelService::class,
        TagCategoryDetector::class,
        TemplateFilterService::class,
        ContentSourceFilterBuilder::class,
        ContentSourceService::class,
        AppearanceSettingsService::class,
        BehaviorSettingsService::class,
        TimingSettingsService::class,
        NotificationSaveService::class,
        AdminStatsService::class,
        AnalyticsService::class,
        AnalyticsHelper::class,
        NotificationHelper::class,
        ConversionTracker::class,
        UrlService::class,
        EligibilityService::class,
        NotificationDataPreparer::class,
        NotificationEligibilityChecker::class,
        TrackingService::class,
        TrackingDataValidator::class,
        GeolocationService::class,
        EventQueue::class,
        EventProcessor::class,
        CronService::class,
        MigrationService::class,
        FrontendTemplateRenderer::class,
        ElementorTemplateRenderer::class,
        BlockEditorTemplateRenderer::class,
        TemplateContextBuilder::class,
        FrontendTagContextBuilder::class,
        WidgetContextProvider::class,
        CacheManager::class,
        PoolCacheManager::class,
        ElementorCacheManager::class,
        WordPressCacheManager::class,
    ];

    protected const FILTER_HOOK = FilterHooks::ONPAGE_SERVICES;

    /**
     * Boot the service provider.
     *
     * @return void
     * @since 2.0.0
     */
    public function boot(): void
    {
        $container = Container::getInstance();
        
        $migrationService = $container->get(MigrationService::class);
        $migrationService->init();

        // Bind interfaces to concrete implementations based on WooCommerce availability
        if (PluginDetector::isWooCommerceActive()) {
            // WooCommerce is active - use WooCommerce-specific implementations
            $container->singleton(OrderFetcherInterface::class, OrderFetcher::class);
            $container->singleton(ProductFetcherInterface::class, ProductFetcher::class);
        } else {
            // WooCommerce is not active - use null object implementations
            $container->singleton(OrderFetcherInterface::class, NullOrderFetcher::class);
            $container->singleton(ProductFetcherInterface::class, NullProductFetcher::class);
        }
        
        // UserFetcher is WordPress-specific, not WooCommerce-dependent
        $container->singleton(UserFetcherInterface::class, UserFetcher::class);

        // LabelProviderInterface for label taxonomy options
        $container->singleton(LabelProviderInterface::class, LabelService::class);

        // DatabaseRepository for data access operations
        $container->singleton(DatabaseRepository::class, function () {
            return new DatabaseRepository();
        });

        // Register widget context provider hooks
        $widgetContextProvider = $container->get(WidgetContextProvider::class);
        $widgetContextProvider->register();

        // Initialize cache manager hooks
        $cacheManager = $container->get(CacheManager::class);
        $cacheManager->register();

        // Initialize cron service
        CronService::init();

        // Initialize conversion tracking
        ConversionTracker::register();

        // Register AJAX controllers
        AnalyticsTableController::register();
        PreCreatedNotificationsAjaxController::register();
        PreCreatedNotificationsImportController::register();


        $this->verify_activation_guard_integrity();

        // SECURITY: Initialize activation guard for database-level protection
        NotificationActivationGuard::init();

        // Register database constraints for security
        $this->register_database_constraints();

        // Register geolocation filter for tracking data
        $this->registerGeolocationFilter();

        // Register label service hooks for cache management
        $labelService = $container->get(LabelService::class);
        $labelService->register();

        /**
         * Clear OnPage caches on plugin lifecycle events (activation/update).
         *
         * Using core hooks ensures that any cached ACTIVE notification data is
         * invalidated when the plugin is first activated or updated to a new version.
         */
        add_action(ActionHooks::PLUGIN_ACTIVATED, [$this, 'clearOnPageCachesOnLifecycleEvent']);
        add_action(
            ActionHooks::DATABASE_MIGRATIONS_AFTER_RUN,
            [$this, 'clearOnPageCachesOnLifecycleEvent'],
            10,
            2
        );
    }

    /**
     * Clear OnPage notification caches on plugin activation or update.
     *
     * Ensures that cached notification pools, templates, and WordPress
     * object cache are refreshed so ACTIVE notifications always reflect
     * the latest configuration after lifecycle events.
     *
     * @since 2.1.5
     * @return void
     */
    public function clearOnPageCachesOnLifecycleEvent(): void
    {
        try {
            $container = Container::getInstance();
            /** @var CacheManager $cacheManager */
            $cacheManager = $container->get(CacheManager::class);
            $cacheManager->clearAllCaches();
        } catch (\Throwable $exception) {
            Helper::log(
                'OnPage ServiceProvider: Failed to clear caches on lifecycle event - ' .
                $exception->getMessage()
            );
        }
    }

    /**
     * Register database-level constraints for notification security.
     * SECURITY: Prevents multiple active notifications for free users at database level.
     *
     * @return void
     * @since 2.0.0
     */
    protected function register_database_constraints(): void
    {
        // Hook into post status transitions to enforce constraints
        add_action('transition_post_status', [$this, 'enforce_notification_constraints'], 10, 3);
    }

    /**
     * Enforce notification constraints during post status transitions.
     * SECURITY: Database-level protection against multiple active notifications.
     *
     * @param string $new_status New post status
     * @param string $old_status Old post status
     * @param \WP_Post $post Post object
     * @return void
     * @since 2.0.0
     */
    public function enforce_notification_constraints(string $new_status, string $old_status, \WP_Post $post): void
    {
        // Only check for onpage notification posts
        if ($post->post_type !== 'notifal_onpage_notif') {
            return;
        }

        // Only check when activating (publishing) a notification
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }

        // Check if multiple notifications are allowed (includes license validation)
        if (apply_filters('notifal_pro_multiple_notifications_allowed', false)) {
            return; // Pro allows multiple notifications
        }

        // SECURITY: Count current active notifications excluding this one
        try {
            $active_notifications = NotificationQuery::getActiveNotificationIds([$post->ID]);

            if (!empty($active_notifications)) {
                // SECURITY: Block the transition and log the attempt
                Helper::log(sprintf(
                    'Notifal Security: Database constraint blocked multiple active notifications. User: %d, Post: %d, Active Count: %d',
                    get_current_user_id(),
                    $post->ID,
                    count($active_notifications)
                ));

                // Force the post back to draft status
                wp_update_post([
                    'ID' => $post->ID,
                    'post_status' => 'draft',
                ]);

                // Notify admin (could be extended to show user-friendly message)
                if (function_exists('wp_admin_notice')) {
                    wp_admin_notice(
                        __('Cannot activate notification: Only one active notification is allowed in the free version.', 'notifal'),
                        ['type' => 'error']
                    );
                }
            }
        } catch (\Exception $e) {
            // On error, allow the transition for safety but log it
            Helper::log('Notifal Security: Error in database constraint check: ' . $e->getMessage());
        }
    }

    /**
     * Register geolocation filter for tracking data.
     * Populates country_code and city fields using GeolocationService.
     *
     * @return void
     * @since 2.0.0
     */
    protected function registerGeolocationFilter(): void
    {
        add_filter(FilterHooks::ONPAGE_TRACKING_DATA, [$this, 'addGeolocationToTrackingData'], 10, 1);
        add_filter(FilterHooks::ONPAGE_EVENT_QUEUE_DATA, [$this, 'addGeolocationToTrackingData'], 10, 1);
    }

    /**
     * Add geolocation data to tracking data array.
     *
     * @param array $trackingData Tracking data array
     * @return array Modified tracking data with geolocation
     * @since 2.0.0
     */
    public function addGeolocationToTrackingData(array $trackingData): array
    {
        if (!isset($trackingData['ip_address'])) {
            return $trackingData;
        }

        $geolocationService = notifal_app(GeolocationService::class);
        $geolocationData = $geolocationService->getGeolocationData($trackingData['ip_address']);

        $trackingData['country_code'] = $geolocationData['country_code'];
        $trackingData['city'] = $geolocationData['city'];

        return $trackingData;
    }
}
