<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Analytics;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
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

        // Free version: return current time
        return [
            'timestamp' => current_time('timestamp'),
            'formatted' => wp_date(get_option('date_format') . ' ' . get_option('time_format'), current_time('timestamp')),
            'human_diff' => __('Just now', 'notifal'),
            'next_update' => __('Upgrade to Pro', 'notifal'),
            'next_update_human' => __('Upgrade to Pro', 'notifal'),
        ];
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
     */
    private function getFreeDashboardOverview(array $filters = []): array
    {
        $dateRange = AnalyticsHelper::getDateRange($filters);
        $startDate = $dateRange["start"];
        $endDate = $dateRange["end"];

        // Get all notification IDs
        $notificationIds = AnalyticsHelper::getFilteredNotificationIds($filters);

        // Calculate only total revenue
        $totalRevenue = $this->calculateTotalRevenue($notificationIds, $startDate, $endDate);

        return [
            "current_period" => [
                "total_impressions" => 0,
                "total_clicks" => 0,
                "total_closes" => 0,
                "total_dismisses" => 0,
                "total_conversions" => 0,
                "total_revenue" => $totalRevenue,
                "total_unique_users" => 0,
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

        $revenue = $this->calculateProductRevenue($notificationId, $startDate, $endDate);

        return [
            "notification_id" => $notificationId,
            "notification_title" => get_the_title($notificationId),
            "total_stats" => [
                "total_impressions" => 0,
                "total_clicks" => 0,
                "total_closes" => 0,
                "total_dismisses" => 0,
                "total_conversions" => 0,
                "total_revenue" => $revenue,
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

        $revenueData = $this->getRevenueTimeSeriesData($notificationIds, $startDate, $endDate);

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
        $notificationIds = array_column($notifications, 'ID');
        $revenueData = $this->getBulkRevenueData($notificationIds, $startDate, $endDate);

        $analyticsData = [];
        foreach ($notifications as $notification) {
            $revenue = $revenueData[$notification->ID] ?? 0;

            $analyticsData[] = [
                "notification_id" => $notification->ID,
                "title" => $notification->post_title,
                "status" => $notification->post_status,
                "created_date" => $notification->post_date,
                "stats" => [
                    "total_impressions" => 0,
                    "total_clicks" => 0,
                    "total_closes" => 0,
                    "total_dismisses" => 0,
                    "total_conversions" => 0,
                    "total_revenue" => $revenue,
                    "total_unique_users" => 0,
                ],
                "period_stats" => [
                    "total_impressions" => 0,
                    "total_clicks" => 0,
                    "total_closes" => 0,
                    "total_dismisses" => 0,
                    "total_conversions" => 0,
                    "total_revenue" => $revenue,
                    "total_unique_users" => 0,
                ],
                "ctr" => 0,
                "conversion_rate" => 0,
                "revenue" => $revenue,
                "is_pro_upsell" => true,
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
        $notificationIds = array_column($notifications['items'], 'ID');
        $revenueData = $this->getBulkRevenueData($notificationIds, $startDate, $endDate);

        // Build analytics data for paginated items
        $analyticsData = [];
        foreach ($notifications['items'] as $notification) {
            $revenue = $revenueData[$notification->ID] ?? 0;

            $analyticsData[] = [
                "notification_id" => $notification->ID,
                "title" => $notification->post_title,
                "status" => $notification->post_status,
                "created_date" => $notification->post_date,
                "stats" => [
                    "total_impressions" => 0,
                    "total_clicks" => 0,
                    "total_closes" => 0,
                    "total_dismisses" => 0,
                    "total_conversions" => 0,
                    "total_revenue" => $revenue,
                    "total_unique_users" => 0,
                ],
                "period_stats" => [
                    "total_impressions" => 0,
                    "total_clicks" => 0,
                    "total_closes" => 0,
                    "total_dismisses" => 0,
                    "total_conversions" => 0,
                    "total_revenue" => $revenue,
                    "total_unique_users" => 0,
                ],
                "ctr" => 0,
                "conversion_rate" => 0,
                "revenue" => $revenue,
                "is_pro_upsell" => true,
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
    private function calculateTotalRevenue(array $notificationIds, string $startDate, string $endDate): float
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
            $sql = $wpdb->prepare(
                "SELECT SUM(product_revenue) as total_revenue
                FROM $conversionsTable
                WHERE notification_id IN ($placeholders)
                AND conversion_timestamp >= %s
                AND conversion_timestamp <= %s",
                array_merge($notificationIds, [$startDate . " 00:00:00", $endDate . " 23:59:59"])
            );

            $result = $wpdb->get_var($sql);
            if ($result !== null) {
                return (float)$result;
            }
        }

        // Fallback: calculate from daily stats
        $totalRevenue = 0.0;
        foreach ($notificationIds as $notificationId) {
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
    public function calculateProductRevenue(int $notificationId, string $startDate, string $endDate): float
    {
        global $wpdb;
        $tables = $this->getTableNames();
        $conversionsTable = $tables["conversions"] ?? "";

        // Check if conversions table exists and has data
        if (!empty($conversionsTable) && $wpdb->get_var("SHOW TABLES LIKE \"$conversionsTable\"") === $conversionsTable) {
            $sql = $wpdb->prepare(
                "SELECT SUM(product_revenue) as total_revenue
                FROM $conversionsTable
                WHERE notification_id = %d
                AND conversion_timestamp >= %s
                AND conversion_timestamp <= %s",
                $notificationId,
                $startDate . " 00:00:00",
                $endDate . " 23:59:59"
            );

            $result = $wpdb->get_var($sql);
            if ($result !== null) {
                return (float)$result;
            }
        }

        // Fallback to daily stats revenue column
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
    public function getRevenueTimeSeriesData(array $notificationIds, string $startDate, string $endDate): array
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

            $sql = $wpdb->prepare(
                "SELECT DATE(conversion_timestamp) as date, SUM(product_revenue) as value
                FROM $conversionsTable
                WHERE notification_id IN ($placeholders)
                AND conversion_timestamp >= %s
                AND conversion_timestamp <= %s
                GROUP BY DATE(conversion_timestamp)
                ORDER BY date ASC",
                array_merge($notificationIds, [$startDate . " 00:00:00", $endDate . " 23:59:59"])
            );

            $results = $wpdb->get_results($sql, ARRAY_A);

            // Fill in missing dates with zero values
            return AnalyticsHelper::fillMissingDates($results, $startDate, $endDate);
        }

        // Fallback to daily stats revenue column
        $allRevenueData = [];
        foreach ($notificationIds as $notificationId) {
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
    private function getBulkRevenueData(array $notificationIds, string $startDate, string $endDate): array
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
            $sql = $wpdb->prepare(
                "SELECT notification_id, SUM(product_revenue) as total_revenue
                FROM $conversionsTable
                WHERE notification_id IN ($placeholders)
                AND conversion_timestamp >= %s
                AND conversion_timestamp <= %s
                GROUP BY notification_id",
                array_merge($notificationIds, [$startDate . " 00:00:00", $endDate . " 23:59:59"])
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
            $dailyStats = $this->getDailyStats($notificationId, $startDate, $endDate);
            $aggregated = $this->aggregateDaily($dailyStats);
            $revenueData[$notificationId] = (float)($aggregated["total_revenue"] ?? 0);
        }

        return $revenueData;
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
