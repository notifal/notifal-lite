<?php

namespace Notifal\Modules\OnPageNotification\Helpers;

defined('ABSPATH') || exit;

/**
 * Stats Helper Class
 *
 * Common utility functions for statistics calculations and date handling.
 *
 * @package Notifal\Modules\OnPageNotification\Helpers
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class StatsHelper
{
    /**
     * Get current date in WordPress timezone.
     *
     * @return string Current date in Y-m-d format
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function getCurrentDate(): string
    {
        return current_time('Y-m-d');
    }

    /**
     * Calculate start date for a given number of days back.
     *
     * @param int $days Number of days to go back
     * @return string Start date in Y-m-d format
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function getStartDate(int $days): string
    {
        $currentDate = self::getCurrentDate();
        return date('Y-m-d', strtotime("-{$days} days", strtotime($currentDate)));
    }

    /**
     * Validate if a date string is in correct Y-m-d format.
     *
     * @param string $date Date string to validate
     * @return bool True if valid, false otherwise
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function isValidDate(string $date): bool
    {
        $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
        return $dateObj && $dateObj->format('Y-m-d') === $date && $dateObj->format('Y') > 1970;
    }

    /**
     * Aggregate statistics by field across multiple daily records.
     *
     * Helper method to sum up specific fields from daily statistics arrays.
     *
     * @param array $stats Daily statistics array
     * @param string $field Field name to aggregate
     * @return int Aggregated total for the field
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function aggregateStatsByField(array $stats, string $field): int
    {
        $total = 0;

        foreach ($stats as $day) {
            $total += (int) ($day[$field] ?? 0);
        }

        return $total;
    }
}
