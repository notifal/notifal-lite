<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Analytics;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Services\Analytics\EventProcessor;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Repositories\DatabaseRepository;
use Notifal\Modules\OnPageNotification\Helpers\AnalyticsHelper;

defined('ABSPATH') || exit;

/**
 * Class AnalyticsService
 *
 * Main analytics service for free version - only handles revenue analytics.
 * All other analytics functionality is delegated to Notifal Pro.
 * Provides UI structure to showcase Pro features while keeping revenue visible.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class AnalyticsService
{

    /**
     * Get dashboard overview data with key metrics.
     *
     * @param array $filters Analytics filters
     * @return array Dashboard overview data
     * @since 2.0.0
     */
    public function getDashboardOverview(array $filters = []): array
    {
        // Check if Pro analytics is active
        $isProActive = apply_filters(FilterHooks::IS_PRO_ANALYTICS_ACTIVE, false);

        if ($isProActive) {
            // Pro is active, get data from Pro service
            $proService = apply_filters(FilterHooks::GET_PRO_ANALYTICS_SERVICE, null);
            if ($proService && method_exists($proService, 'getDashboardOverview')) {
                return $proService->getDashboardOverview($filters);
            }
        }

        return $this->getFreeDashboardOverview($filters);
    }

    /**
     * Get detailed analytics for a specific notification.
     *
     * @param int $notificationId Notification ID
     * @param array $filters Analytics filters
     * @return array Detailed notification analytics
     * @since 2.0.0
     */
    public function getNotificationAnalytics(int $notificationId, array $filters = []): array
    {
        // Check if Pro analytics is active
        $isProActive = apply_filters(FilterHooks::IS_PRO_ANALYTICS_ACTIVE, false);

        if ($isProActive) {
            // Pro is active, get data from Pro service
            $proService = apply_filters(FilterHooks::GET_PRO_ANALYTICS_SERVICE, null);
            if ($proService && method_exists($proService, 'getNotificationAnalytics')) {
                return $proService->getNotificationAnalytics($notificationId, $filters);
            }
        }

        return $this->getFreeNotificationAnalytics($notificationId, $filters);
    }

    /**
     * Get analytics data for multiple notifications (comparison view).
     *
     * @param array $notificationIds Array of notification IDs
     * @param array $filters Analytics filters
     * @return array Comparison analytics data
     * @since 2.0.0
     */
    public function getComparisonAnalytics(array $notificationIds, array $filters = []): array
    {
        // Check if Pro analytics is active
        $isProActive = apply_filters(FilterHooks::IS_PRO_ANALYTICS_ACTIVE, false);

        if ($isProActive) {
            // Pro is active, get data from Pro service
            $proService = apply_filters(FilterHooks::GET_PRO_ANALYTICS_SERVICE, null);
            if ($proService && method_exists($proService, 'getComparisonAnalytics')) {
                return $proService->getComparisonAnalytics($notificationIds, $filters);
            }
        }

        return $this->getFreeComparisonAnalytics($notificationIds, $filters);
    }

    /**
     * Get time-series data for charts (Pro feature).
     *
     * @param array $filters Analytics filters
     * @return array Time-series chart data
     * @since 2.0.0
     */
    public function getChartData(array $filters = []): array
    {
        // Check if Pro analytics is active
        $isProActive = apply_filters(FilterHooks::IS_PRO_ANALYTICS_ACTIVE, false);

        if ($isProActive) {
            // Pro is active, get data from Pro service
            $proService = apply_filters(FilterHooks::GET_PRO_ANALYTICS_SERVICE, null);
            if ($proService && method_exists($proService, 'getChartData')) {
                return $proService->getChartData($filters);
            }
        }

        return $this->getFreeChartData($filters);
    }

    /**
     * Get all notifications with basic analytics data for list view.
     *
     * @param array $filters Analytics filters
     * @return array Notifications list with analytics
     * @since 2.0.0
     */
    public function getAllNotificationsAnalytics(array $filters = []): array
    {
        // Check if Pro analytics is active
        $isProActive = apply_filters(FilterHooks::IS_PRO_ANALYTICS_ACTIVE, false);

        if ($isProActive) {
            // Pro is active, get data from Pro service
            $proService = apply_filters(FilterHooks::GET_PRO_ANALYTICS_SERVICE, null);
            if ($proService && method_exists($proService, 'getAllNotificationsAnalytics')) {
                return $proService->getAllNotificationsAnalytics($filters);
            }
        }

        // Free version: show basic list with revenue only
        return $this->getFreeAllNotificationsAnalytics($filters);
    }

    /**
     * Get paginated notifications analytics with sorting.
     *
     * @param array $filters Analytics filters including pagination params
     * @return array Paginated notifications data with total count
     * @since 2.0.0
     */
    public function getPaginatedNotificationsAnalytics(array $filters = []): array
    {
        // Check if Pro analytics is active
        $isProActive = apply_filters(FilterHooks::IS_PRO_ANALYTICS_ACTIVE, false);

        if ($isProActive) {
            // Pro is active, get data from Pro service
            $proService = apply_filters(FilterHooks::GET_PRO_ANALYTICS_SERVICE, null);
            if ($proService && method_exists($proService, 'getPaginatedNotificationsAnalytics')) {
                return $proService->getPaginatedNotificationsAnalytics($filters);
            }
        }

        return $this->getFreePaginatedNotificationsAnalytics($filters);
    }

    /**
     * Get the last time analytics data was updated.
     *
     * @return array Last update information
     * @since 2.0.0
     */
    public function getLastUpdateTime(): array
    {
        // Check if Pro analytics is active
        $isProActive = apply_filters(FilterHooks::IS_PRO_ANALYTICS_ACTIVE, false);

        if ($isProActive) {
            // Pro is active, get data from Pro service
            $proService = apply_filters(FilterHooks::GET_PRO_ANALYTICS_SERVICE, null);
            if ($proService && method_exists($proService, 'getLastUpdateTime')) {
                return $proService->getLastUpdateTime();
            }
        }

        // Lite and Pro-unavailable states share the same last-update helper.
        return AnalyticsHelper::buildLastUpdateInfo();
    }

    /**
     * Process pending analytics events and prepare the dashboard for reload.
     *
     * @return array Processing result
     * @since 2.3.0
     */
    public function refreshDashboardAnalytics(): array
    {
        // Delegate to Pro when enhanced analytics processing is available.
        $result = $this->forceProcessPendingEvents();

        // Lite installs process queued events directly through the core processor.
        if (! empty($result['is_pro_required'])) {
            $result = $this->processPendingEventsForRefresh();
        } elseif (empty($result['success'])) {
            // Fall back to core processing when Pro delegation fails during manual refresh.
            $fallbackResult = $this->processPendingEventsForRefresh();
            if (! empty($fallbackResult['success'])) {
                $result = $fallbackResult;
            }
        }

        // Persist the manual refresh timestamp whenever processing succeeds.
        if (! empty($result['success'])) {
            AnalyticsHelper::recordLastProcessingTime();

            /**
             * Fires after analytics dashboard refresh processing completes.
             *
             * @since 2.3.0
             * @param array $result Processing result payload.
             */
            do_action(ActionHooks::ONPAGE_ANALYTICS_REFRESH_COMPLETED, $result);
        }

        return $result;
    }

    /**
     * Process queued analytics events for manual dashboard refresh.
     *
     * @return array Processing result
     * @since 2.3.0
     */
    private function processPendingEventsForRefresh(): array
    {
        // Resolve the shared event processor from the application container.
        $eventProcessor = notifal_app(EventProcessor::class);

        // Drain the queue in bounded batches so refresh reflects the latest metrics.
        return $eventProcessor->forceProcessAllEvents();
    }

    /**
     * Force process all pending analytics events.
     *
     * @return array Processing result
     * @since 2.0.0
     */
    public function forceProcessPendingEvents(): array
    {
        // Check if Pro analytics is active
        $isProActive = apply_filters(FilterHooks::IS_PRO_ANALYTICS_ACTIVE, false);

        if ($isProActive) {
            // Pro is active, get data from Pro service
            $proService = apply_filters(FilterHooks::GET_PRO_ANALYTICS_SERVICE, null);
            if ($proService && method_exists($proService, 'forceProcessPendingEvents')) {
                return $proService->forceProcessPendingEvents();
            }
        }

        return [
            'success' => false,
            'message' => __('Upgrade to Notifal Pro to access advanced analytics processing', 'notifal'),
            'is_pro_required' => true,
        ];
    }

    /**
     * Get analytics system health status.
     *
     * @return array System health information
     * @since 2.0.0
     */
    public function getSystemHealthStatus(): array
    {
        // Check if Pro analytics is active
        $isProActive = apply_filters(FilterHooks::IS_PRO_ANALYTICS_ACTIVE, false);

        if ($isProActive) {
            // Pro is active, get data from Pro service
            $proService = apply_filters(FilterHooks::GET_PRO_ANALYTICS_SERVICE, null);
            if ($proService && method_exists($proService, 'getSystemHealthStatus')) {
                return $proService->getSystemHealthStatus();
            }
        }

        // Free version: basic health info
        return [
            'cron_active' => false,
            'queue_size' => 0,
            'last_processing' => null,
            'database_tables_exist' => false,
            'total_notifications' => wp_count_posts('notifal_onpage_notif')->publish ?? 0,
            'total_events_today' => 0,
            'is_pro_required' => true,
            'message' => __('Upgrade to Pro for detailed system health analytics', 'notifal'),
        ];
    }

    /**
     * Check if user can access detailed analytics.
     *
     * @return bool True if user has Pro access, false otherwise
     * @since 2.0.0
     */
    public function canAccessDetailedAnalytics(): bool
    {
        return apply_filters(FilterHooks::IS_PRO_ANALYTICS_ACTIVE, false);
    }

    /**
     * Get dashboard overview for free version (revenue only).
     *
     * @param array $filters Analytics filters
     * @return array Dashboard overview data with only revenue
     * @since 2.0.0
     * @since 2.3.0 Also exposes influenced_revenue and influenced_orders.
     */
    private function getFreeDashboardOverview(array $filters = []): array
    {
        $dateRange = AnalyticsHelper::getDateRange($filters);
        $startDate = $dateRange["start"];
        $endDate = $dateRange["end"];

        // Get all notification IDs
        $notificationIds = AnalyticsHelper::getFilteredNotificationIds($filters);

        // Calculate clicked product revenue (original) and influenced order revenue (new, since 2.3.0)
        $campaignId         = isset($filters['campaign_id']) ? (int) $filters['campaign_id'] : 0;
        $totalRevenue       = $this->calculateTotalRevenue($notificationIds, $startDate, $endDate, $campaignId);
        $influencedRevenue       = $this->calculateTotalInfluencedRevenue($notificationIds, $startDate, $endDate, $campaignId);
        $influencedOrdersPaid    = $this->calculateTotalInfluencedOrders($notificationIds, $startDate, $endDate, $campaignId);
        $influencedOrdersPending = $this->calculateTotalPendingInfluencedOrders($notificationIds, $startDate, $endDate, $campaignId);
        $influencedOrdersTotal   = $influencedOrdersPaid + $influencedOrdersPending;

        return [
            "current_period" => [
                "total_impressions"   => 0,
                "total_clicks"        => 0,
                "total_closes"        => 0,
                "total_dismisses"     => 0,
                "total_conversions"   => 0,
                "total_revenue"       => $totalRevenue,
                "influenced_revenue"  => $influencedRevenue,
                "influenced_orders"   => $influencedOrdersTotal,
                "influenced_orders_paid" => $influencedOrdersPaid,
                "influenced_orders_pending" => $influencedOrdersPending,
                "total_unique_users"  => 0,
            ],
            "previous_period" => [
                "total_impressions" => 0,
                "total_clicks" => 0,
                "total_closes" => 0,
                "total_dismisses" => 0,
                "total_conversions" => 0,
                "total_revenue" => 0, // Previous period not available in free version
                "total_unique_users" => 0,
            ],
            "growth_rates" => [
                "total_impressions" => 0,
                "total_clicks" => 0,
                "total_closes" => 0,
                "total_dismisses" => 0,
                "total_conversions" => 0,
                "total_revenue" => 0,
                "total_unique_users" => 0,
            ],
            "top_performing" => [],
            "recent_activity" => [],
            "date_range" => [
                "start" => $startDate,
                "end" => $endDate,
                "label" => AnalyticsHelper::getDateRangeLabel($filters)
            ],
            "is_pro_upsell" => true,
            "upsell_message" => __("Upgrade to Notifal Pro for detailed analytics insights", "notifal"),
        ];
    }

    /**
     * Get notification analytics for free version (revenue only).
     *
     * @param int $notificationId Notification ID
     * @param array $filters Analytics filters
     * @return array Notification analytics with only revenue
     * @since 2.0.0
     */
    private function getFreeNotificationAnalytics(int $notificationId, array $filters = []): array
    {
        $dateRange = AnalyticsHelper::getDateRange($filters);
        $startDate = $dateRange["start"];
        $endDate = $dateRange["end"];

        $campaignId       = isset($filters['campaign_id']) ? (int) $filters['campaign_id'] : 0;
        $revenue          = $this->calculateProductRevenue($notificationId, $startDate, $endDate, $campaignId);
        $influencedRev    = $this->calculateInfluencedRevenue($notificationId, $startDate, $endDate, $campaignId);
        $influencedOrdersPaid    = $this->calculateInfluencedOrders($notificationId, $startDate, $endDate, $campaignId);
        $influencedOrdersPending = $this->calculatePendingInfluencedOrders($notificationId, $startDate, $endDate, $campaignId);

        return [
            "notification_id"    => $notificationId,
            "notification_title" => get_the_title($notificationId),
            "total_stats" => [
                "total_impressions"  => 0,
                "total_clicks"       => 0,
                "total_closes"       => 0,
                "total_dismisses"    => 0,
                "total_conversions"  => 0,
                "total_revenue"      => $revenue,
                "influenced_revenue" => $influencedRev,
                "influenced_orders"  => $influencedOrdersPaid + $influencedOrdersPending,
                "influenced_orders_paid" => $influencedOrdersPaid,
                "influenced_orders_pending" => $influencedOrdersPending,
                "total_unique_users" => 0,
            ],
            "daily_stats" => [],
            "hourly_distribution" => [],
            "device_breakdown" => [],
            "conversion_funnel" => [],
            "geographic_data" => [],
            "is_pro_upsell" => true,
            "upsell_message" => __("Upgrade to Notifal Pro for detailed notification analytics", "notifal"),
        ];
    }

    /**
     * Get comparison analytics for free version (upsell only).
     *
     * @param array $notificationIds Array of notification IDs
     * @param array $filters Analytics filters
     * @return array Upsell message for comparison analytics
     * @since 2.0.0
     */
    private function getFreeComparisonAnalytics(array $notificationIds, array $filters = []): array
    {
        return [
            "notifications" => [],
            "summary" => [],
            "is_pro_upsell" => true,
            "upsell_message" => __("Upgrade to Notifal Pro to compare notification performance", "notifal"),
        ];
    }

    /**
     * Get chart data for free version (revenue chart only).
     *
     * @param array $filters Analytics filters
     * @return array Chart data with only revenue
     * @since 2.0.0
     */
    private function getFreeChartData(array $filters = []): array
    {
        $dateRange = AnalyticsHelper::getDateRange($filters);
        $startDate = $dateRange["start"];
        $endDate = $dateRange["end"];
        $notificationIds = AnalyticsHelper::getFilteredNotificationIds($filters);

        $campaignId = isset($filters['campaign_id']) ? (int) $filters['campaign_id'] : 0;
        $revenueData = $this->getRevenueTimeSeriesData($notificationIds, $startDate, $endDate, $campaignId);

        return [
            "impressions_over_time" => [],
            "clicks_over_time" => [],
            "conversions_over_time" => [],
            "revenue_over_time" => $revenueData,
            "ctr_over_time" => [],
            "is_pro_upsell" => true,
            "upsell_message" => __("Upgrade to Notifal Pro for detailed time-series analytics", "notifal"),
        ];
    }

    /**
     * Get all notifications analytics for free version (basic list with revenue).
     *
     * @param array $filters Analytics filters
     * @return array Notifications list with only revenue
     * @since 2.0.0
     */
    private function getFreeAllNotificationsAnalytics(array $filters = []): array
    {
        $dateRange = AnalyticsHelper::getDateRange($filters);
        $startDate = $dateRange["start"];
        $endDate = $dateRange["end"];

        // Get notifications
        $notifications = $this->getFilteredNotifications($filters);

        // Get revenue data for all notifications in one query if possible
        $notificationIds    = array_column($notifications, 'ID');
        $campaignId         = isset($filters['campaign_id']) ? (int) $filters['campaign_id'] : 0;
        $revenueData        = $this->getBulkRevenueData($notificationIds, $startDate, $endDate, $campaignId);
        $influencedRevData  = $this->getBulkInfluencedRevenueData($notificationIds, $startDate, $endDate, $campaignId);
        $influencedOrdData  = $this->getBulkInfluencedOrdersData($notificationIds, $startDate, $endDate, $campaignId);

        $analyticsData = [];
        foreach ($notifications as $notification) {
            $revenue          = $revenueData[$notification->ID] ?? 0;
            $influencedRev    = $influencedRevData[$notification->ID] ?? 0;
            $influencedOrders = $influencedOrdData[$notification->ID] ?? 0;

            $analyticsData[] = [
                "notification_id" => $notification->ID,
                "title"           => $notification->post_title,
                "status"          => $notification->post_status,
                "created_date"    => $notification->post_date,
                "stats" => [
                    "total_impressions"  => 0,
                    "total_clicks"       => 0,
                    "total_closes"       => 0,
                    "total_dismisses"    => 0,
                    "total_conversions"  => 0,
                    "total_revenue"      => $revenue,
                    "influenced_revenue" => $influencedRev,
                    "influenced_orders"  => $influencedOrders,
                    "total_unique_users" => 0,
                ],
                "period_stats" => [
                    "total_impressions"  => 0,
                    "total_clicks"       => 0,
                    "total_closes"       => 0,
                    "total_dismisses"    => 0,
                    "total_conversions"  => 0,
                    "total_revenue"      => $revenue,
                    "influenced_revenue" => $influencedRev,
                    "influenced_orders"  => $influencedOrders,
                    "total_unique_users" => 0,
                ],
                "ctr"              => 0,
                "conversion_rate"  => 0,
                "revenue"          => $revenue,
                "influenced_revenue" => $influencedRev,
                "influenced_orders"  => $influencedOrders,
                "is_pro_upsell"    => true,
            ];
        }

        return $analyticsData;
    }

    /**
     * Get paginated notifications analytics for free version.
     *
     * @param array $filters Analytics filters including pagination params
     * @return array Paginated notifications data with total count
     * @since 2.0.0
     */
    private function getFreePaginatedNotificationsAnalytics(array $filters = []): array
    {
        $dateRange = AnalyticsHelper::getDateRange($filters);
        $startDate = $dateRange["start"];
        $endDate = $dateRange["end"];

        // Get notifications with pagination
        $notifications = $this->getFilteredNotificationsPaginated($filters);

        // Get revenue data for the paginated notifications
        $notificationIds   = array_column($notifications['items'], 'ID');
        $campaignId        = isset($filters['campaign_id']) ? (int) $filters['campaign_id'] : 0;
        $revenueData       = $this->getBulkRevenueData($notificationIds, $startDate, $endDate, $campaignId);
        $influencedRevData = $this->getBulkInfluencedRevenueData($notificationIds, $startDate, $endDate, $campaignId);
        $influencedOrdData = $this->getBulkInfluencedOrdersData($notificationIds, $startDate, $endDate, $campaignId);

        // Build analytics data for paginated items
        $analyticsData = [];
        foreach ($notifications['items'] as $notification) {
            $revenue          = $revenueData[$notification->ID] ?? 0;
            $influencedRev    = $influencedRevData[$notification->ID] ?? 0;
            $influencedOrders = $influencedOrdData[$notification->ID] ?? 0;

            $analyticsData[] = [
                "notification_id" => $notification->ID,
                "title"           => $notification->post_title,
                "status"          => $notification->post_status,
                "created_date"    => $notification->post_date,
                "stats" => [
                    "total_impressions"  => 0,
                    "total_clicks"       => 0,
                    "total_closes"       => 0,
                    "total_dismisses"    => 0,
                    "total_conversions"  => 0,
                    "total_revenue"      => $revenue,
                    "influenced_revenue" => $influencedRev,
                    "influenced_orders"  => $influencedOrders,
                    "total_unique_users" => 0,
                ],
                "period_stats" => [
                    "total_impressions"  => 0,
                    "total_clicks"       => 0,
                    "total_closes"       => 0,
                    "total_dismisses"    => 0,
                    "total_conversions"  => 0,
                    "total_revenue"      => $revenue,
                    "influenced_revenue" => $influencedRev,
                    "influenced_orders"  => $influencedOrders,
                    "total_unique_users" => 0,
                ],
                "ctr"               => 0,
                "conversion_rate"   => 0,
                "revenue"           => $revenue,
                "influenced_revenue" => $influencedRev,
                "influenced_orders"  => $influencedOrders,
                "is_pro_upsell"     => true,
            ];
        }

        return [
            "notifications" => $analyticsData,
            "pagination" => $notifications['pagination']
        ];
    }

    /**
     * Calculate total revenue across multiple notifications.
     *
     * @param array $notificationIds Array of notification IDs
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return float Total revenue
     * @since 2.0.0
     */
    private function calculateTotalRevenue(array $notificationIds, string $startDate, string $endDate, int $campaignId = 0): float
    {
        if (empty($notificationIds)) {
            return 0.0;
        }

        global $wpdb;
        $tables = $this->getTableNames();
        $conversionsTable = $tables["conversions"] ?? "";

        // Check if conversions table exists and has data
        if (!empty($conversionsTable) && $wpdb->get_var("SHOW TABLES LIKE \"$conversionsTable\"") === $conversionsTable) {
            // Get total revenue from conversions table for all notifications
            $placeholders = implode(",", array_fill(0, count($notificationIds), "%d"));
            $campaignSql = $campaignId > 0 ? " AND campaign_id = %d" : '';
            $params = array_merge($notificationIds, [$startDate . " 00:00:00", $endDate . " 23:59:59"]);
            if ($campaignId > 0) {
                $params[] = $campaignId;
            }
            $sql = $wpdb->prepare(
                "SELECT SUM(product_revenue) as total_revenue
                FROM $conversionsTable
                WHERE notification_id IN ($placeholders)
                AND conversion_timestamp >= %s
                AND conversion_timestamp <= %s{$campaignSql}",
                $params
            );

            $result = $wpdb->get_var($sql);
            if ($result !== null) {
                return (float)$result;
            }
        }

        // Fallback: calculate from daily stats
        $totalRevenue = 0.0;
        foreach ($notificationIds as $notificationId) {
            if ($campaignId > 0) {
                continue;
            }
            $dailyStats = $this->getDailyStats($notificationId, $startDate, $endDate);
            $aggregated = $this->aggregateDaily($dailyStats);
            $totalRevenue += (float)($aggregated["total_revenue"] ?? 0);
        }

        return $totalRevenue;
    }

    /**
     * Calculate product-specific revenue for a notification.
     *
     * @param int $notificationId Notification ID
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return float Total product revenue
     * @since 2.0.2
     * @author Hossein <hossein@notifal.com>
     */
    public function calculateProductRevenue(int $notificationId, string $startDate, string $endDate, int $campaignId = 0): float
    {
        global $wpdb;
        $tables = $this->getTableNames();
        $conversionsTable = $tables["conversions"] ?? "";

        // Check if conversions table exists and has data
        if (!empty($conversionsTable) && $wpdb->get_var("SHOW TABLES LIKE \"$conversionsTable\"") === $conversionsTable) {
            $campaignSql = $campaignId > 0 ? " AND campaign_id = %d" : '';
            $params = [$notificationId, $startDate . " 00:00:00", $endDate . " 23:59:59"];
            if ($campaignId > 0) {
                $params[] = $campaignId;
            }
            $sql = $wpdb->prepare(
                "SELECT SUM(product_revenue) as total_revenue
                FROM $conversionsTable
                WHERE notification_id = %d
                AND conversion_timestamp >= %s
                AND conversion_timestamp <= %s{$campaignSql}",
                $params
            );

            $result = $wpdb->get_var($sql);
            if ($result !== null) {
                return (float)$result;
            }
        }

        // Fallback to daily stats revenue column
        if ($campaignId > 0) {
            return 0.0;
        }
        $dailyStats = $this->getDailyStats($notificationId, $startDate, $endDate);
        $aggregated = $this->aggregateDaily($dailyStats);
        return (float)($aggregated["total_revenue"] ?? 0);
    }

    /**
     * Get revenue time series data.
     *
     * @param array $notificationIds Array of notification IDs
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Revenue time series data
     * @since 2.0.2
     */
    public function getRevenueTimeSeriesData(array $notificationIds, string $startDate, string $endDate, int $campaignId = 0): array
    {
        if (empty($notificationIds)) {
            return [];
        }

        global $wpdb;

        $tables = $this->getTableNames();
        $conversionsTable = $tables["conversions"] ?? "";

        // Check if conversions table exists
        $tableExists = $wpdb->get_var("SHOW TABLES LIKE \"$conversionsTable\"") === $conversionsTable;

        if (!empty($conversionsTable) && $tableExists) {
            // Use new product-specific conversion tracking
            $placeholders = implode(",", array_fill(0, count($notificationIds), "%d"));

            $campaignSql = $campaignId > 0 ? " AND campaign_id = %d" : '';
            $params = array_merge($notificationIds, [$startDate . " 00:00:00", $endDate . " 23:59:59"]);
            if ($campaignId > 0) {
                $params[] = $campaignId;
            }
            $sql = $wpdb->prepare(
                "SELECT DATE(conversion_timestamp) as date, SUM(product_revenue) as value
                FROM $conversionsTable
                WHERE notification_id IN ($placeholders)
                AND conversion_timestamp >= %s
                AND conversion_timestamp <= %s{$campaignSql}
                GROUP BY DATE(conversion_timestamp)
                ORDER BY date ASC",
                $params
            );

            $results = $wpdb->get_results($sql, ARRAY_A);

            // Fill in missing dates with zero values
            return AnalyticsHelper::fillMissingDates($results, $startDate, $endDate);
        }

        // Fallback to daily stats revenue column
        $allRevenueData = [];
        foreach ($notificationIds as $notificationId) {
            if ($campaignId > 0) {
                continue;
            }
            $dailyStats = $this->getDailyStats($notificationId, $startDate, $endDate);

            foreach ($dailyStats as $day) {
                $date = $day["date"];
                $revenue = (float)($day["revenue"] ?? 0);

                if (!isset($allRevenueData[$date])) {
                    $allRevenueData[$date] = 0;
                }
                $allRevenueData[$date] += $revenue;
            }
        }

        // Convert to expected format
        $results = array_map(function($date, $value) {
            return [
                "date" => $date,
                "value" => $value
            ];
        }, array_keys($allRevenueData), array_values($allRevenueData));

        // Fill in missing dates with zero values
        return AnalyticsHelper::fillMissingDates($results, $startDate, $endDate);
    }


    /**
     * Build query arguments for notification filtering.
     *
     * @param array $filters Analytics filters
     * @param array $overrides Additional query overrides
     * @return array Query arguments for get_posts
     * @since 2.0.0
     * @since 2.2.0 Supports `campaign_id` via `_notifal_campaign_id` meta.
     */
    private function buildNotificationQueryArgs(array $filters = [], array $overrides = []): array
    {
        $queryArgs = [
            "post_type" => "notifal_onpage_notif",
            "post_status" => ["publish", "draft"],
            "orderby" => "date",
            "order" => "DESC"
        ];

        if (isset($filters["status"]) && !empty($filters["status"])) {
            $queryArgs["post_status"] = $filters["status"];
        }

        $campaignId = isset($filters['campaign_id']) ? (int) $filters['campaign_id'] : 0;
        if ($campaignId > 0) {
            $queryArgs['meta_query'] = [
                [
                    'key' => '_notifal_campaign_id',
                    'value' => $campaignId,
                    'compare' => '=',
                ],
            ];
        }

        return array_merge($queryArgs, $overrides);
    }

    /**
     * Get filtered notifications based on provided filters.
     *
     * @param array $filters Analytics filters
     * @return array Array of notification posts
     * @since 2.0.0
     */
    private function getFilteredNotifications(array $filters = []): array
    {
        $queryArgs = $this->buildNotificationQueryArgs($filters, [
            "posts_per_page" => -1
        ]);

        return get_posts($queryArgs);
    }

    /**
     * Get filtered notifications with pagination.
     *
     * @param array $filters Analytics filters including pagination params
     * @return array Array with 'items' and 'pagination' keys
     * @since 2.0.0
     */
    private function getFilteredNotificationsPaginated(array $filters = []): array
    {
        // Pagination parameters
        $limit = isset($filters["limit"]) ? (int)$filters["limit"] : 10;
        $offset = isset($filters["offset"]) ? (int)$filters["offset"] : 0;

        $queryArgs = $this->buildNotificationQueryArgs($filters, [
            "posts_per_page" => $limit,
            "offset" => $offset
        ]);

        $items = get_posts($queryArgs);

        // Get total count for pagination
        $countQuery = $this->buildNotificationQueryArgs($filters, [
            "posts_per_page" => -1,
            "fields" => "ids"
        ]);
        $total = count(get_posts($countQuery));

        return [
            'items' => $items,
            'pagination' => [
                "total" => $total,
                "limit" => $limit,
                "offset" => $offset,
                "current_page" => floor($offset / $limit) + 1,
                "total_pages" => ceil($total / $limit),
                "has_more" => ($offset + $limit) < $total
            ]
        ];
    }

    /**
     * Get bulk revenue data for multiple notifications.
     *
     * @param array $notificationIds Array of notification IDs
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Revenue data keyed by notification ID
     * @since 2.0.0
     */
    private function getBulkRevenueData(array $notificationIds, string $startDate, string $endDate, int $campaignId = 0): array
    {
        if (empty($notificationIds)) {
            return [];
        }

        global $wpdb;
        $tables = $this->getTableNames();
        $conversionsTable = $tables["conversions"] ?? "";

        // Check if conversions table exists and has data
        if (!empty($conversionsTable) && $wpdb->get_var("SHOW TABLES LIKE \"$conversionsTable\"") === $conversionsTable) {
            $placeholders = implode(",", array_fill(0, count($notificationIds), "%d"));
            $campaignSql = $campaignId > 0 ? " AND campaign_id = %d" : '';
            $params = array_merge($notificationIds, [$startDate . " 00:00:00", $endDate . " 23:59:59"]);
            if ($campaignId > 0) {
                $params[] = $campaignId;
            }
            $sql = $wpdb->prepare(
                "SELECT notification_id, SUM(product_revenue) as total_revenue
                FROM $conversionsTable
                WHERE notification_id IN ($placeholders)
                AND conversion_timestamp >= %s
                AND conversion_timestamp <= %s{$campaignSql}
                GROUP BY notification_id",
                $params
            );

            $results = $wpdb->get_results($sql, ARRAY_A);
            if ($results) {
                $revenueData = [];
                foreach ($results as $row) {
                    $revenueData[$row['notification_id']] = (float)$row['total_revenue'];
                }
                return $revenueData;
            }
        }

        // Fallback: get revenue from daily stats
        $revenueData = [];
        foreach ($notificationIds as $notificationId) {
            if ($campaignId > 0) {
                $revenueData[$notificationId] = 0.0;
                continue;
            }
            $dailyStats = $this->getDailyStats($notificationId, $startDate, $endDate);
            $aggregated = $this->aggregateDaily($dailyStats);
            $revenueData[$notificationId] = (float)($aggregated["total_revenue"] ?? 0);
        }

        return $revenueData;
    }

    /**
     * Calculate total influenced revenue (full order totals) across multiple notifications.
     *
     * Sums the `influenced_revenue` column from daily_stats for all given notification IDs.
     *
     * @param array  $notificationIds Array of notification IDs
     * @param string $startDate       Start date (Y-m-d)
     * @param string $endDate         End date (Y-m-d)
     * @param int    $campaignId      Optional campaign ID filter (0 = no filter)
     * @return float Total influenced revenue
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public function calculateTotalInfluencedRevenue(array $notificationIds, string $startDate, string $endDate, int $campaignId = 0): float
    {
        if (empty($notificationIds)) {
            return 0.0;
        }

        global $wpdb;

        $tables      = $this->getTableNames();
        $dailyStats  = $tables['daily_stats'] ?? '';

        if (empty($dailyStats)) {
            return 0.0;
        }

        // Campaign-level influenced revenue is tracked via the conversions table for precision
        if ($campaignId > 0) {
            $conversionsTable = $tables['conversions'] ?? '';
            if (empty($conversionsTable) || $wpdb->get_var("SHOW TABLES LIKE \"{$conversionsTable}\"") !== $conversionsTable) {
                return 0.0;
            }
            $placeholders = implode(',', array_fill(0, count($notificationIds), '%d'));
            $params       = array_merge($notificationIds, [$startDate . ' 00:00:00', $endDate . ' 23:59:59', $campaignId]);
            // SUM distinct order totals per notification+order to avoid multi-product double-count
            $sql = $wpdb->prepare(
                "SELECT SUM(t.total_order_value) FROM (
                    SELECT MAX(total_order_value) as total_order_value
                    FROM {$conversionsTable}
                    WHERE notification_id IN ({$placeholders})
                    AND conversion_timestamp >= %s
                    AND conversion_timestamp <= %s
                    AND campaign_id = %d
                    GROUP BY order_id
                ) t",
                $params
            );
            $result = $wpdb->get_var($sql);
            return (float) ($result ?? 0.0);
        }

        $bulkData = $this->getBulkInfluencedRevenueData($notificationIds, $startDate, $endDate, $campaignId);

        return array_sum($bulkData);
    }

    /**
     * Calculate total influenced orders count across multiple notifications.
     *
     * @param array  $notificationIds Array of notification IDs
     * @param string $startDate       Start date (Y-m-d)
     * @param string $endDate         End date (Y-m-d)
     * @param int    $campaignId      Optional campaign ID filter (0 = no filter)
     * @return int Total influenced orders
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public function calculateTotalInfluencedOrders(array $notificationIds, string $startDate, string $endDate, int $campaignId = 0): int
    {
        if (empty($notificationIds)) {
            return 0;
        }

        global $wpdb;

        $tables      = $this->getTableNames();
        $dailyStats  = $tables['daily_stats'] ?? '';

        if (empty($dailyStats)) {
            return 0;
        }

        if ($campaignId > 0) {
            $conversionsTable = $tables['conversions'] ?? '';
            if (empty($conversionsTable) || $wpdb->get_var("SHOW TABLES LIKE \"{$conversionsTable}\"") !== $conversionsTable) {
                return 0;
            }
            $placeholders = implode(',', array_fill(0, count($notificationIds), '%d'));
            $params       = array_merge($notificationIds, [$startDate . ' 00:00:00', $endDate . ' 23:59:59', $campaignId]);
            // Count distinct order IDs across all notifs (avoid multi-notif-same-order double-count)
            $sql = $wpdb->prepare(
                "SELECT COUNT(DISTINCT order_id) FROM {$conversionsTable}
                WHERE notification_id IN ({$placeholders})
                AND conversion_timestamp >= %s AND conversion_timestamp <= %s
                AND campaign_id = %d",
                $params
            );
            $result = $wpdb->get_var($sql);
            return (int) ($result ?? 0);
        }

        if ($campaignId > 0) {
            return $this->countInfluencedOrdersFromConversions($notificationIds, $startDate, $endDate, $campaignId);
        }

        $bulkData = $this->getBulkInfluencedOrdersData($notificationIds, $startDate, $endDate, 0);

        return array_sum($bulkData);
    }

    /**
     * Calculate influenced revenue for a single notification.
     *
     * @param int    $notificationId Notification ID
     * @param string $startDate      Start date (Y-m-d)
     * @param string $endDate        End date (Y-m-d)
     * @param int    $campaignId     Optional campaign ID (0 = no filter)
     * @return float Influenced revenue
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public function calculateInfluencedRevenue(int $notificationId, string $startDate, string $endDate, int $campaignId = 0): float
    {
        $bulkData = $this->getBulkInfluencedRevenueData([$notificationId], $startDate, $endDate, $campaignId);

        return (float) ($bulkData[(int) $notificationId] ?? 0.0);
    }

    /**
     * Calculate influenced orders for a single notification.
     *
     * @param int    $notificationId Notification ID
     * @param string $startDate      Start date (Y-m-d)
     * @param string $endDate        End date (Y-m-d)
     * @param int    $campaignId     Optional campaign ID (0 = no filter)
     * @return int Influenced orders count
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public function calculateInfluencedOrders(int $notificationId, string $startDate, string $endDate, int $campaignId = 0): int
    {
        $bulkData = $this->getBulkInfluencedOrdersData([$notificationId], $startDate, $endDate, $campaignId);

        return (int) ($bulkData[(int) $notificationId] ?? 0);
    }

    /**
     * Get bulk influenced revenue data for multiple notifications (public proxy for Pro use).
     *
     * @param array  $notificationIds Array of notification IDs
     * @param string $startDate       Start date (Y-m-d)
     * @param string $endDate         End date (Y-m-d)
     * @param int    $campaignId      Optional campaign ID filter (0 = no filter)
     * @return array Influenced revenue values keyed by notification ID
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public function getBulkInfluencedRevenueDataPublic(array $notificationIds, string $startDate, string $endDate, int $campaignId = 0): array
    {
        return $this->getBulkInfluencedRevenueData($notificationIds, $startDate, $endDate, $campaignId);
    }

    /**
     * Get bulk influenced orders data for multiple notifications (public proxy for Pro use).
     *
     * @param array  $notificationIds Array of notification IDs
     * @param string $startDate       Start date (Y-m-d)
     * @param string $endDate         End date (Y-m-d)
     * @param int    $campaignId      Optional campaign ID filter (0 = no filter)
     * @return array Influenced orders counts keyed by notification ID
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public function getBulkInfluencedOrdersDataPublic(array $notificationIds, string $startDate, string $endDate, int $campaignId = 0): array
    {
        return $this->getBulkInfluencedOrdersData($notificationIds, $startDate, $endDate, $campaignId);
    }

    /**
     * Get bulk influenced revenue data for multiple notifications.
     *
     * @param array  $notificationIds Array of notification IDs
     * @param string $startDate       Start date (Y-m-d)
     * @param string $endDate         End date (Y-m-d)
     * @param int    $campaignId      Optional campaign ID filter (0 = no filter)
     * @return array Influenced revenue values keyed by notification ID
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    private function getBulkInfluencedRevenueData(array $notificationIds, string $startDate, string $endDate, int $campaignId = 0): array
    {
        if (empty($notificationIds)) {
            return [];
        }

        global $wpdb;

        $tables   = $this->getTableNames();
        $result   = [];

        foreach ($notificationIds as $notificationId) {
            $result[(int) $notificationId] = 0.0;
        }

        $conversionsTable = $tables['conversions'] ?? '';

        if (empty($conversionsTable) || $wpdb->get_var("SHOW TABLES LIKE \"{$conversionsTable}\"") !== $conversionsTable) {
            return $result;
        }

        $placeholders = implode(',', array_fill(0, count($notificationIds), '%d'));
        $params       = array_merge($notificationIds, [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $campaignSql  = '';

        if ($campaignId > 0) {
            $campaignSql = ' AND campaign_id = %d';
            $params[]    = $campaignId;
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT notification_id, order_id, MAX(total_order_value) AS order_total
                FROM `{$conversionsTable}`
                WHERE notification_id IN ({$placeholders})
                AND conversion_timestamp >= %s
                AND conversion_timestamp <= %s
                AND order_id > 0
                {$campaignSql}
                GROUP BY notification_id, order_id",
                $params
            ),
            ARRAY_A
        );

        foreach ($rows as $row) {
            $notificationId = (int) ($row['notification_id'] ?? 0);
            $orderTotal     = (float) ($row['order_total'] ?? 0);

            if ($notificationId <= 0) {
                continue;
            }

            if ($orderTotal <= 0 && function_exists('wc_get_order')) {
                $order = wc_get_order((int) ($row['order_id'] ?? 0));

                if ($order) {
                    $orderTotal = (float) $order->get_total('edit');
                }
            }

            $result[$notificationId] = ($result[$notificationId] ?? 0.0) + $orderTotal;
        }

        return $result;
    }

    /**
     * Get bulk influenced orders count for multiple notifications.
     *
     * @param array  $notificationIds Array of notification IDs
     * @param string $startDate       Start date (Y-m-d)
     * @param string $endDate         End date (Y-m-d)
     * @param int    $campaignId      Optional campaign ID filter (0 = no filter)
     * @return array Influenced orders counts keyed by notification ID
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    private function getBulkInfluencedOrdersData(array $notificationIds, string $startDate, string $endDate, int $campaignId = 0): array
    {
        if (empty($notificationIds)) {
            return [];
        }

        global $wpdb;

        $tables = $this->getTableNames();
        $result = [];

        foreach ($notificationIds as $notificationId) {
            $result[(int) $notificationId] = 0;
        }

        $conversionsTable = $tables['conversions'] ?? '';

        if (empty($conversionsTable) || $wpdb->get_var("SHOW TABLES LIKE \"{$conversionsTable}\"") !== $conversionsTable) {
            return $result;
        }

        $placeholders = implode(',', array_fill(0, count($notificationIds), '%d'));
        $params       = array_merge($notificationIds, [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $campaignSql  = '';

        if ($campaignId > 0) {
            $campaignSql = ' AND campaign_id = %d';
            $params[]    = $campaignId;
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT notification_id, COUNT(DISTINCT order_id) AS io
                FROM `{$conversionsTable}`
                WHERE notification_id IN ({$placeholders})
                AND conversion_timestamp >= %s
                AND conversion_timestamp <= %s
                AND order_id > 0
                {$campaignSql}
                GROUP BY notification_id",
                $params
            ),
            ARRAY_A
        );

        foreach ($rows as $row) {
            $notificationId = (int) ($row['notification_id'] ?? 0);

            if ($notificationId > 0) {
                $result[$notificationId] = (int) ($row['io'] ?? 0);
            }
        }

        return $result;
    }

    /**
     * Sum influenced revenue from conversions table (one total per order).
     *
     * @param array  $notificationIds Notification IDs
     * @param string $startDate       Start date (Y-m-d)
     * @param string $endDate         End date (Y-m-d)
     * @param int    $campaignId      Campaign filter (0 = all)
     * @return float
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    private function sumInfluencedRevenueFromConversions(array $notificationIds, string $startDate, string $endDate, int $campaignId = 0): float
    {
        global $wpdb;

        $tables           = $this->getTableNames();
        $conversionsTable = $tables['conversions'] ?? '';

        if (empty($conversionsTable) || $wpdb->get_var("SHOW TABLES LIKE \"{$conversionsTable}\"") !== $conversionsTable) {
            return 0.0;
        }

        $placeholders = implode(',', array_fill(0, count($notificationIds), '%d'));
        $params       = array_merge(
            $notificationIds,
            [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
        );

        $campaignSql = '';
        if ($campaignId > 0) {
            $campaignSql = ' AND campaign_id = %d';
            $params[]    = $campaignId;
        }

        $sql = $wpdb->prepare(
            "SELECT COALESCE(SUM(t.order_total), 0) FROM (
                SELECT MAX(total_order_value) AS order_total
                FROM `{$conversionsTable}`
                WHERE notification_id IN ({$placeholders})
                AND conversion_timestamp >= %s
                AND conversion_timestamp <= %s
                {$campaignSql}
                GROUP BY notification_id, order_id
            ) t",
            $params
        );

        $sum = (float) ($wpdb->get_var($sql) ?? 0.0);

        if ($sum > 0 || !function_exists('wc_get_order')) {
            return $sum;
        }

        return $this->sumInfluencedRevenueFromWooOrders($notificationIds, $startDate, $endDate, $campaignId);
    }

    /**
     * Sum order totals from WooCommerce for influenced orders (legacy rows missing total_order_value).
     *
     * @param array  $notificationIds Notification IDs
     * @param string $startDate       Start date (Y-m-d)
     * @param string $endDate         End date (Y-m-d)
     * @param int    $campaignId      Campaign filter (0 = all)
     * @return float
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    private function sumInfluencedRevenueFromWooOrders(array $notificationIds, string $startDate, string $endDate, int $campaignId = 0): float
    {
        global $wpdb;

        $tables           = $this->getTableNames();
        $conversionsTable = $tables['conversions'] ?? '';

        if (empty($conversionsTable)) {
            return 0.0;
        }

        $placeholders = implode(',', array_fill(0, count($notificationIds), '%d'));
        $params       = array_merge(
            $notificationIds,
            [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
        );

        $campaignSql = '';
        if ($campaignId > 0) {
            $campaignSql = ' AND campaign_id = %d';
            $params[]    = $campaignId;
        }

        $orderIds = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT order_id FROM `{$conversionsTable}`
                WHERE notification_id IN ({$placeholders})
                AND conversion_timestamp >= %s
                AND conversion_timestamp <= %s
                AND order_id > 0
                {$campaignSql}",
                $params
            )
        );

        if (empty($orderIds)) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($orderIds as $orderId) {
            $order = wc_get_order((int) $orderId);

            if (!$order) {
                continue;
            }

            // Prefer revenue locked at payment time (unaffected by later refunds/cancellations)
            $lockedRevenue = (float) $order->get_meta('_notifal_influenced_revenue_locked', true);

            if ($lockedRevenue > 0) {
                $total += $lockedRevenue;
                continue;
            }

            // Use stored conversion total when available
            $storedConversionTotal = (float) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT MAX(total_order_value) FROM `{$conversionsTable}`
                    WHERE order_id = %d",
                    (int) $orderId
                )
            );

            if ($storedConversionTotal > 0) {
                $total += $storedConversionTotal;
                continue;
            }

            // Only use live order total for paid statuses when no locked value exists
            if (AnalyticsHelper::isPaidWooCommerceOrderStatus((string) $order->get_status())) {
                $total += (float) $order->get_total('edit');
            }
        }

        return $total;
    }

    /**
     * Calculate total pending influenced orders (unpaid) in a date range.
     *
     * @param array  $notificationIds Notification IDs
     * @param string $startDate       Start date (Y-m-d)
     * @param string $endDate         End date (Y-m-d)
     * @param int    $campaignId      Campaign filter (0 = all)
     * @return int Pending influenced order count
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public function calculateTotalPendingInfluencedOrders(array $notificationIds, string $startDate, string $endDate, int $campaignId = 0): int
    {
        if (empty($notificationIds) || !function_exists('wc_get_orders')) {
            return 0;
        }

        $notificationIds = array_map('intval', $notificationIds);
        $matchedOrderIds = [];

        $orderIds = wc_get_orders([
            'limit' => -1,
            'return' => 'ids',
            'date_created' => $startDate . '...' . $endDate,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_notifal_pending_attribution',
                    'compare' => 'EXISTS',
                ],
                [
                    'key' => '_notifal_conversion_processed',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ]);

        if (empty($orderIds)) {
            return 0;
        }

        foreach ($orderIds as $orderId) {
            $order = wc_get_order((int) $orderId);

            if (!$order) {
                continue;
            }

            $pending = $order->get_meta('_notifal_pending_attribution', true);

            if (is_string($pending)) {
                $pending = json_decode($pending, true);
            }

            if (!is_array($pending)) {
                continue;
            }

            foreach ($pending as $row) {
                if (!is_array($row)) {
                    continue;
                }

                if (!in_array((int) ($row['notification_id'] ?? 0), $notificationIds, true)) {
                    continue;
                }

                $matchedOrderIds[(int) $orderId] = true;
                break;
            }
        }

        return count($matchedOrderIds);
    }

    /**
     * Calculate pending influenced orders for a single notification.
     *
     * @param int    $notificationId Notification ID
     * @param string $startDate      Start date (Y-m-d)
     * @param string $endDate        End date (Y-m-d)
     * @param int    $campaignId     Campaign filter (0 = all)
     * @return int
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public function calculatePendingInfluencedOrders(int $notificationId, string $startDate, string $endDate, int $campaignId = 0): int
    {
        return $this->calculateTotalPendingInfluencedOrders([$notificationId], $startDate, $endDate, $campaignId);
    }

    /**
     * Count influenced orders from conversions table (distinct orders).
     *
     * @param array  $notificationIds Notification IDs
     * @param string $startDate       Start date (Y-m-d)
     * @param string $endDate         End date (Y-m-d)
     * @param int    $campaignId      Campaign filter (0 = all)
     * @return int
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    private function countInfluencedOrdersFromConversions(array $notificationIds, string $startDate, string $endDate, int $campaignId = 0): int
    {
        global $wpdb;

        $tables           = $this->getTableNames();
        $conversionsTable = $tables['conversions'] ?? '';

        if (empty($conversionsTable) || $wpdb->get_var("SHOW TABLES LIKE \"{$conversionsTable}\"") !== $conversionsTable) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($notificationIds), '%d'));
        $params       = array_merge(
            $notificationIds,
            [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
        );

        $campaignSql = '';
        if ($campaignId > 0) {
            $campaignSql = ' AND campaign_id = %d';
            $params[]    = $campaignId;
        }

        $sql = $wpdb->prepare(
            "SELECT COUNT(DISTINCT order_id) FROM `{$conversionsTable}`
            WHERE notification_id IN ({$placeholders})
            AND conversion_timestamp >= %s
            AND conversion_timestamp <= %s
            AND order_id > 0
            {$campaignSql}",
            $params
        );

        return (int) ($wpdb->get_var($sql) ?? 0);
    }

    /**
     * Get table names from database repository.
     *
     * @return array Table names
     * @since 2.0.0
     */
    private function getTableNames(): array
    {
        $repository = notifal_app(DatabaseRepository::class);
        return $repository->getTableNames();
    }

    /**
     * Get daily stats for a notification.
     *
     * @param int $notificationId Notification ID
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Daily statistics
     * @since 2.0.0
     */
    private function getDailyStats(int $notificationId, string $startDate, string $endDate): array
    {
        $repository = notifal_app(DatabaseRepository::class);
        return $repository->getDailyStats($notificationId, $startDate, $endDate);
    }

    /**
     * Aggregate daily statistics into totals.
     *
     * @param array $dailyStats Array of daily statistics
     * @return array Aggregated totals
     * @since 2.0.0
     */
    private function aggregateDaily(array $dailyStats): array
    {
        $totals = [
            "total_impressions" => 0,
            "total_clicks" => 0,
            "total_closes" => 0,
            "total_dismisses" => 0,
            "total_conversions" => 0,
            "total_revenue" => 0,
        ];

        foreach ($dailyStats as $day) {
            $totals["total_impressions"] += (int)($day["impressions"] ?? 0);
            $totals["total_clicks"] += (int)($day["clicks"] ?? 0);
            $totals["total_closes"] += (int)($day["closes"] ?? 0);
            $totals["total_dismisses"] += (int)($day["dismisses"] ?? 0);
            $totals["total_conversions"] += (int)($day["conversions"] ?? 0);
            $totals["total_revenue"] += (float)($day["revenue"] ?? 0);
        }

        return $totals;
    }

}
