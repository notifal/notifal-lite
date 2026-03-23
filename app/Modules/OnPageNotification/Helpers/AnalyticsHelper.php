<?php

namespace Notifal\Modules\OnPageNotification\Helpers;

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
     */
    public static function getDateRange(array $filters): array
    {
        $range = $filters["date_range"] ?? "last_30_days";
        $today = current_time("Y-m-d");

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
     * Get date range label for display.
     *
     * @param array $filters Analytics filters
     * @return string Date range label
     * @since 2.0.0
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
     * Resolves which OnPage notifications are included in analytics. A specific
     * `notification_id` limits to that post; when `campaign_id` is set, only
     * notifications linked to that campaign (`_notifal_campaign_id`) are included,
     * and counts/rates for the dashboard are aggregated across those IDs. If both
     * are set, the notification must belong to the campaign or no IDs are returned.
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
            if ($campaignId > 0) {
                global $wpdb;
                $trackingTable = $wpdb->prefix . 'notifal_onpage_tracking';
                $conversionsTable = $wpdb->prefix . 'notifal_onpage_conversions';

                $hasCampaignTracking = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$trackingTable} WHERE notification_id = %d AND campaign_id = %d",
                        $notificationId,
                        $campaignId
                    )
                );

                $hasCampaignConversions = 0;
                $conversionsExists = $wpdb->get_var("SHOW TABLES LIKE '{$conversionsTable}'") === $conversionsTable;
                if ($conversionsExists) {
                    $hasCampaignConversions = (int) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COUNT(*) FROM {$conversionsTable} WHERE notification_id = %d AND campaign_id = %d",
                            $notificationId,
                            $campaignId
                        )
                    );
                }

                if ($hasCampaignTracking <= 0 && $hasCampaignConversions <= 0) {
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
            global $wpdb;

            $trackingTable = $wpdb->prefix . 'notifal_onpage_tracking';
            $conversionsTable = $wpdb->prefix . 'notifal_onpage_conversions';

            $trackingIds = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT notification_id FROM {$trackingTable} WHERE campaign_id = %d",
                    $campaignId
                )
            );
            $trackingIds = is_array($trackingIds) ? array_map('absint', $trackingIds) : [];

            $conversionIds = [];
            $conversionsExists = $wpdb->get_var("SHOW TABLES LIKE '{$conversionsTable}'") === $conversionsTable;
            if ($conversionsExists) {
                $conversionIds = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT DISTINCT notification_id FROM {$conversionsTable} WHERE campaign_id = %d",
                        $campaignId
                    )
                );
                $conversionIds = is_array($conversionIds) ? array_map('absint', $conversionIds) : [];
            }

            $campaignNotificationIds = array_values(array_unique(array_merge($trackingIds, $conversionIds)));
            if (empty($campaignNotificationIds)) {
                return apply_filters(FilterHooks::ONPAGE_ANALYTICS_FILTERED_NOTIFICATION_IDS, [], $filters);
            }

            $args['post__in'] = $campaignNotificationIds;
        }

        $ids = get_posts($args);
        $ids = is_array($ids) ? array_map('absint', $ids) : [];

        return apply_filters(FilterHooks::ONPAGE_ANALYTICS_FILTERED_NOTIFICATION_IDS, $ids, $filters);
    }
}
