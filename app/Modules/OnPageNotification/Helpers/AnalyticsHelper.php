<?php

namespace Notifal\Modules\OnPageNotification\Helpers;

use Notifal\Infrastructure\WordPress\ActivationPopup\Domain\ActivationPopup;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Analytics Helper Class
 *
 * Provides common analytics utility functions used across free and pro analytics services.
 * Handles date range calculations, time series data processing, filtering logic, and data normalization
 * for consistent analytics reporting across the application.
 *
 * @package Notifal\Modules\OnPageNotification\Helpers
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class AnalyticsHelper
{
    /**
     * Calculate date range based on filters.
     *
     * @param array $filters Analytics filters
     * @return array Date range with start and end dates
     * @since 2.0.0
     * @since 2.3.0 Added `all_time` preset (install / earliest analytics through today).
     */
    public static function getDateRange(array $filters): array
    {
        // Read the selected preset from filters (defaults to last 30 days).
        $range = $filters["date_range"] ?? "last_30_days";
        // Use site-local "today" as the inclusive end date for rolling ranges.
        $today = current_time("Y-m-d");

        // All time: from first install / earliest analytics through today.
        if ($range === "all_time") {
            return [
                "start" => self::getAllTimeStartDate(),
                "end"   => $today,
            ];
        }

        // Define date range configurations
        $rangeConfigs = [
            "today" => ["days" => 0, "same_day" => true],
            "yesterday" => ["days" => 1, "same_day" => true],
            "last_7_days" => ["days" => 7, "same_day" => false],
            "last_30_days" => ["days" => 30, "same_day" => false],
            "last_90_days" => ["days" => 90, "same_day" => false],
        ];

        $config = $rangeConfigs[$range] ?? $rangeConfigs["last_30_days"];

        if ($config["same_day"]) {
            $targetDate = date("Y-m-d", strtotime("-{$config["days"]} day", strtotime($today)));
            return ["start" => $targetDate, "end" => $targetDate];
        }

        return [
            "start" => date("Y-m-d", strtotime("-{$config["days"]} days", strtotime($today))),
            "end" => $today
        ];
    }

    /**
     * Resolve the start date for the "All Time" analytics preset.
     *
     * Uses the earliest of: plugin activation time, first daily stat, or first tracking event.
     * Sites without stored history fall back to today. Result is filterable for extensions.
     *
     * @return string Start date in Y-m-d format.
     * @since 2.3.0
     */
    public static function getAllTimeStartDate(): string
    {
        // Cache per request to avoid repeated MIN() queries on the same page load.
        static $cachedStartDate = null;

        if ($cachedStartDate !== null) {
            return $cachedStartDate;
        }

        $today = current_time("Y-m-d");
        $candidates = [];

        // First activation on this site (fresh installs and popup completion).
        $activationTime = get_option(ActivationPopup::ACTIVATION_TIME_KEY);
        if ($activationTime) {
            $candidates[] = wp_date("Y-m-d", (int) $activationTime);
        }

        global $wpdb;

        // Earliest row in aggregated daily stats.
        $dailyStatsTable = $wpdb->prefix . "notifal_onpage_daily_stats";
        if ($wpdb->get_var("SHOW TABLES LIKE '{$dailyStatsTable}'") === $dailyStatsTable) {
            $earliestDaily = $wpdb->get_var("SELECT MIN(date) FROM {$dailyStatsTable}");
            if (is_string($earliestDaily) && $earliestDaily !== "") {
                $candidates[] = $earliestDaily;
            }
        }

        // Earliest raw tracking event (covers sites with data before daily aggregation).
        $trackingTable = $wpdb->prefix . "notifal_onpage_tracking";
        if ($wpdb->get_var("SHOW TABLES LIKE '{$trackingTable}'") === $trackingTable) {
            $earliestTracking = $wpdb->get_var(
                "SELECT MIN(DATE(COALESCE(NULLIF(timestamp, '0000-00-00 00:00:00'), created_at))) FROM {$trackingTable}"
            );
            if (is_string($earliestTracking) && $earliestTracking !== "") {
                $candidates[] = $earliestTracking;
            }
        }

        $startDate = !empty($candidates) ? min($candidates) : $today;

        // Never allow a future start date relative to today.
        if ($startDate > $today) {
            $startDate = $today;
        }

        /**
         * Filters the resolved start date for the all_time analytics range.
         *
         * @param string $startDate Start date (Y-m-d).
         * @since 2.3.0
         */
        $cachedStartDate = (string) apply_filters(FilterHooks::ONPAGE_ANALYTICS_ALL_TIME_START_DATE, $startDate);

        return $cachedStartDate;
    }

    /**
     * Get date range label for display.
     *
     * @param array $filters Analytics filters
     * @return string Date range label
     * @since 2.0.0
     * @since 2.3.0 Added `all_time` label.
     */
    public static function getDateRangeLabel(array $filters): string
    {
        $range = $filters["date_range"] ?? "last_30_days";

        $labels = [
            "today" => __("Today", "notifal"),
            "yesterday" => __("Yesterday", "notifal"),
            "last_7_days" => __("Last 7 Days", "notifal"),
            "last_30_days" => __("Last 30 Days", "notifal"),
            "last_90_days" => __("Last 90 Days", "notifal"),
            "all_time" => __("All Time", "notifal"),
        ];

        return $labels[$range] ?? $labels["last_30_days"];
    }

    /**
     * Fill missing dates in time series data with zero values.
     *
     * @param array $data Existing data with date and value
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array Complete data array with all dates
     * @since 2.0.0
     */
    public static function fillMissingDates(array $data, string $startDate, string $endDate): array
    {
        // Validate date range
        if ($startDate > $endDate) {
            return [];
        }

        // Create array indexed by date for fast lookup using array_column for better performance
        $dataByDate = [];
        if (!empty($data)) {
            $dates = array_column($data, 'date');
            $values = array_column($data, 'value');
            $dataByDate = array_combine($dates, array_map('floatval', $values));
        }

        // Generate all dates in range using DateTimeImmutable for better performance
        $result = [];
        $currentDate = new \DateTimeImmutable($startDate);
        $endDateTime = new \DateTimeImmutable($endDate);
        $interval = new \DateInterval('P1D');

        while ($currentDate <= $endDateTime) {
            $dateString = $currentDate->format('Y-m-d');
            $result[] = [
                'date' => $dateString,
                'value' => $dataByDate[$dateString] ?? 0.0
            ];
            $currentDate = $currentDate->add($interval);
        }

        return $result;
    }

    /**
     * Get filtered notification IDs based on filters.
     *
     * Resolves which OnPage notifications are included in analytics using AND logic.
     * A specific `notification_id` limits to that post. When `campaign_id` is set,
     * only notifications assigned to that campaign (`_notifal_campaign_id`) are included.
     * When `status` is set, only matching post statuses are included. If `notification_id`
     * and `campaign_id` are both set, the notification must belong to that campaign.
     *
     * @param array $filters Analytics filters (`notification_id`, `campaign_id`, `status`, …).
     * @return array<int> Notification post IDs.
     * @since 2.0.0
     * @since 2.2.0 Added `campaign_id` filtering and validation against `notification_id`.
     */
    public static function getFilteredNotificationIds(array $filters): array
    {
        $status = isset($filters['status']) && $filters['status'] !== '' ? $filters['status'] : '';

        $campaignId = isset($filters['campaign_id']) ? (int) $filters['campaign_id'] : 0;

        $notificationId = isset($filters['notification_id']) ? (int) $filters['notification_id'] : 0;

        if ($notificationId > 0) {
            // AND logic: when a campaign is selected, the notification must belong to that campaign.
            if ($campaignId > 0) {
                $assignedCampaignId = (int) get_post_meta($notificationId, '_notifal_campaign_id', true);
                if ($assignedCampaignId !== $campaignId) {
                    return apply_filters(FilterHooks::ONPAGE_ANALYTICS_FILTERED_NOTIFICATION_IDS, [], $filters);
                }
            }

            // AND logic: when a status is selected, the notification post must match it.
            if ($status !== '') {
                $postStatus = get_post_status($notificationId);
                if ($postStatus !== $status) {
                    return apply_filters(FilterHooks::ONPAGE_ANALYTICS_FILTERED_NOTIFICATION_IDS, [], $filters);
                }
            }

            $ids = [ $notificationId ];

            return apply_filters(FilterHooks::ONPAGE_ANALYTICS_FILTERED_NOTIFICATION_IDS, $ids, $filters);
        }

        $args = [
            'post_type' => 'notifal_onpage_notif',
            'post_status' => [ 'publish', 'draft' ],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        if ($status !== '') {
            $args['post_status'] = $status;
        }

        if ($campaignId > 0) {
            // AND logic: limit notifications to those assigned to the selected campaign.
            $args['meta_query'] = [
                [
                    'key'     => '_notifal_campaign_id',
                    'value'   => $campaignId,
                    'compare' => '=',
                ],
            ];
        }

        $ids = get_posts($args);
        $ids = is_array($ids) ? array_map('absint', $ids) : [];

        return apply_filters(FilterHooks::ONPAGE_ANALYTICS_FILTERED_NOTIFICATION_IDS, $ids, $filters);
    }

    /**
     * Option key storing the last analytics queue processing timestamp.
     *
     * @return string WordPress option name.
     * @since 2.3.0
     */
    public static function getLastProcessingOptionKey(): string
    {
        // Shared option used by manual refresh and scheduled cron processing.
        return 'notifal_onpage_analytics_last_processed';
    }

    /**
     * Cron hook name for OnPage analytics event processing.
     *
     * @return string WordPress cron hook.
     * @since 2.3.0
     */
    public static function getProcessEventsCronHook(): string
    {
        // Matches CronService::CRON_HOOK_PROCESS for next-run calculations.
        return 'notifal_onpage_process_events';
    }

    /**
     * Persist the latest analytics processing time.
     *
     * @return void
     * @since 2.3.0
     */
    public static function recordLastProcessingTime(): void
    {
        // Store site-local timestamp without autoload to keep options table lean.
        update_option(self::getLastProcessingOptionKey(), current_time('timestamp'), false);
    }

    /**
     * Build dashboard "Updated at / Next update" metadata.
     *
     * @param int|null $lastRunTimestamp Optional override for the last processing timestamp.
     * @return array Last update information for the analytics dashboard.
     * @since 2.3.0
     */
    public static function buildLastUpdateInfo(?int $lastRunTimestamp = null): array
    {
        // Resolve the last run from the stored option when no explicit timestamp is passed.
        if ($lastRunTimestamp === null) {
            $lastRunTimestamp = (int) get_option(self::getLastProcessingOptionKey(), 0);
        }

        // Fall back to the estimated previous cron run when nothing has been recorded yet.
        if ($lastRunTimestamp <= 0) {
            $nextScheduled = wp_next_scheduled(self::getProcessEventsCronHook());
            if ($nextScheduled) {
                $lastRunTimestamp = (int) ($nextScheduled - (5 * HOUR_IN_SECONDS));
            } else {
                $lastRunTimestamp = (int) current_time('timestamp');
            }
        }

        // Read the next scheduled cron run for the "Next update" label.
        $nextScheduled = wp_next_scheduled(self::getProcessEventsCronHook());
        $unknownLabel  = __('Unknown', 'notifal');

        return [
            'timestamp'           => $lastRunTimestamp,
            'formatted'           => wp_date(get_option('date_format') . ' ' . get_option('time_format'), $lastRunTimestamp),
            'human_diff'          => human_time_diff($lastRunTimestamp, current_time('timestamp')) . ' ' . __('ago', 'notifal'),
            'next_update'         => $nextScheduled
                ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $nextScheduled)
                : $unknownLabel,
            'next_update_human'   => $nextScheduled
                ? human_time_diff(current_time('timestamp'), $nextScheduled)
                : $unknownLabel,
        ];
    }
}
