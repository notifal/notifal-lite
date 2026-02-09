<?php

namespace Notifal\Modules\OnPageNotification\Helpers;

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
     * @param array $filters Analytics filters
     * @return array Array of notification IDs
     * @since 2.0.0
     */
    public static function getFilteredNotificationIds(array $filters): array
    {
        if (isset($filters["notification_id"])) {
            return [(int)$filters["notification_id"]];
        }

        $args = [
            "post_type" => "notifal_onpage_notif",
            "post_status" => ["publish", "draft"],
            "posts_per_page" => -1,
            "fields" => "ids"
        ];

        if (isset($filters["status"]) && !empty($filters["status"])) {
            $args["post_status"] = $filters["status"];
        }

        return get_posts($args);
    }
}
