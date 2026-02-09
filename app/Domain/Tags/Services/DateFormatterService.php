<?php

namespace Notifal\Domain\Tags\Services;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Service for formatting dates in tag system.
 * 
 * Handles date parsing, custom formatting, and relative date calculations
 * for both product and order date tags.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class DateFormatterService
{
    /**
     * Default date format used when no custom format is specified.
     */
    private const DEFAULT_FORMAT = 'Y-m-d';
    
    /**
     * Relative date identifier suffix.
     */
    private const RELATIVE_SUFFIX = 'diff';

    /**
     * Parse and format a date based on tag key format specification.
     *
     * Supports both custom format and relative date display.
     * 
     * @param string $dateValue Raw date value (Y-m-d H:i:s format)
     * @param string $tagKey Full tag key (e.g., 'product_publish_date_Y/m/d')
     * @return string Formatted date string
     * @since 2.0.0
     */
    public function formatDate(string $dateValue, string $tagKey): string
    {
        // Validate date value
        if (empty($dateValue) || $dateValue === '0000-00-00 00:00:00') {
            return '';
        }

        // Extract format from tag key
        $format = $this->extractFormatFromTagKey($tagKey);
        
        // Handle relative date formatting
        if ($format === self::RELATIVE_SUFFIX) {
            return $this->formatRelativeDate($dateValue);
        }
        
        // Handle custom format or use default
        $dateFormat = $format ?: self::DEFAULT_FORMAT;
        
        // Apply WordPress timezone and format
        $timestamp = $this->convertToWordPressTimestamp($dateValue);
        
        /**
         * Filter to modify date format before formatting.
         *
         * @param string $dateFormat The date format string
         * @param string $tagKey The original tag key
         * @param string $dateValue The raw date value
         * @since 2.0.0
         */
        $dateFormat = apply_filters(FilterHooks::FILTER_DATE_TAG_FORMAT, $dateFormat, $tagKey, $dateValue);
        
        // Format the date using WordPress date functions for localization
        $formattedDate = date_i18n($dateFormat, $timestamp);
        
        /**
         * Filter to modify the final formatted date.
         *
         * @param string $formattedDate The formatted date string
         * @param string $dateFormat The date format used
         * @param string $tagKey The original tag key
         * @param string $dateValue The raw date value
         * @since 2.0.0
         */
        return apply_filters(FilterHooks::FILTER_DATE_TAG_RESULT, $formattedDate, $dateFormat, $tagKey, $dateValue);
    }

    /**
     * Extract format specification from tag key.
     *
     * Parses tag keys like 'product_publish_date_Y/m/d' to extract 'Y/m/d'.
     * 
     * @param string $tagKey Full tag key
     * @return string|null Format string or null if no format specified
     * @since 2.0.0
     */
    private function extractFormatFromTagKey(string $tagKey): ?string
    {
        // Pattern matches: product_publish_date_FORMAT or order_created_date_FORMAT
        if (preg_match('/_date_(.+)$/', $tagKey, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Format date as relative time (e.g., "2 days ago").
     *
     * Uses WordPress human_time_diff function for consistency.
     * 
     * @param string $dateValue Raw date value
     * @return string Relative date string
     * @since 2.0.0
     */
    private function formatRelativeDate(string $dateValue): string
    {
        $timestamp = $this->convertToWordPressTimestamp($dateValue);
        $currentTime = current_time('timestamp', true);
        
        // Use WordPress built-in function for human-readable time difference
        $timeDiff = human_time_diff($timestamp, $currentTime);
        
        // Determine if date is in past or future
        if ($timestamp < $currentTime) {
            /* translators: %s: time difference (e.g., "2 days") */
            return sprintf(__('%s ago', 'notifal'), $timeDiff);
        } else {
            /* translators: %s: time difference (e.g., "2 days") */
            return sprintf(__('in %s', 'notifal'), $timeDiff);
        }
    }

    /**
     * Convert date string to WordPress timestamp.
     *
     * Handles timezone conversion according to WordPress settings.
     * 
     * @param string $dateValue Date string to convert
     * @return int Unix timestamp
     * @since 2.0.0
     */
    private function convertToWordPressTimestamp(string $dateValue): int
    {
        // Convert to timestamp considering WordPress timezone
        $timestamp = strtotime($dateValue);
        
        if ($timestamp === false) {
            // Fallback to current time if parsing fails
            return current_time('timestamp', true);
        }
        
        return $timestamp;
    }

    /**
     * Get list of supported date formats for admin UI.
     *
     * Returns common date format examples for user selection.
     * 
     * @return array Array of format => example pairs
     * @since 2.0.0
     */
    public function getSupportedFormats(): array
    {
        $now = current_time('timestamp');
        
        return [
            'Y-m-d'     => date_i18n('Y-m-d', $now),     // 2024-01-15
            'Y/m/d'     => date_i18n('Y/m/d', $now),     // 2024/01/15
            'd.m.Y'     => date_i18n('d.m.Y', $now),     // 15.01.2024
            'F j, Y'    => date_i18n('F j, Y', $now),    // January 15, 2024
            'M j, Y'    => date_i18n('M j, Y', $now),    // Jan 15, 2024
            'd/m/Y'     => date_i18n('d/m/Y', $now),     // 15/01/2024
            'j F Y'     => date_i18n('j F Y', $now),     // 15 January 2024
            self::RELATIVE_SUFFIX => __('Relative (2 days ago)', 'notifal'),
        ];
    }

    /**
     * Validate if a date format string is safe to use.
     *
     * Prevents potential security issues with arbitrary format strings.
     * 
     * @param string $format Date format to validate
     * @return bool True if format is safe, false otherwise
     * @since 2.0.0
     */
    public function isValidFormat(string $format): bool
    {
        // Allow relative format
        if ($format === self::RELATIVE_SUFFIX) {
            return true;
        }
        
        // Check if format contains only allowed PHP date format characters
        $allowedChars = 'YyMmFndjlDSwzWtLGgGHhisuUIOPTZcrU\\/-., :';
        
        return preg_match('/^[' . preg_quote($allowedChars, '/') . ']+$/', $format) === 1;
    }
}
