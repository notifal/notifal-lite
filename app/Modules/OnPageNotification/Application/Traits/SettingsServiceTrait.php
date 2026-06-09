<?php

namespace Notifal\Modules\OnPageNotification\Application\Traits;

use Notifal\Shared\Utils\Helper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait SettingsServiceTrait
 *
 * Provides common functionality for settings services including
 * sanitization helpers, default settings handling, and pro feature checks.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Application\Traits
 */
trait SettingsServiceTrait
{
    /**
     * Cache for pro feature checks to avoid repeated filter calls
     *
     * @var array
     */
    private static $proFeatureCache = [];
    /**
     * Sanitize integer value within range
     *
     * @since 2.0.0
     * @param mixed $value Input value
     * @param int $min Minimum value
     * @param int $max Maximum value
     * @return int Sanitized integer
     */
    protected function sanitizeInteger($value, int $min, int $max): int
    {
        $value = (int) $value;
        return max($min, min($max, $value));
    }

    /**
     * Sanitize signed integer value without min/max clamping.
     *
     * Used for position offset fields where negative values are valid.
     *
     * @since 2.3.7
     * @param mixed $value Input value.
     * @return int Sanitized integer.
     */
    protected function sanitizeSignedInteger($value): int
    {
        return (int) $value;
    }

    /**
     * Sanitize float value within range
     *
     * @since 2.0.0
     * @param mixed $value Input value
     * @param float $min Minimum value
     * @param float $max Maximum value
     * @return float Sanitized float
     */
    protected function sanitizeFloat($value, float $min, float $max): float
    {
        $float_value = (float) $value;
        return max($min, min($max, $float_value));
    }

    /**
     * Sanitize select option
     *
     * @since 2.0.0
     * @param mixed $value Input value
     * @param array $allowed_values Allowed values
     * @param string $default Default value
     * @return string Sanitized select value
     */
    protected function sanitizeSelect($value, array $allowed_values, string $default): string
    {
        return in_array($value, $allowed_values, true) ? $value : $default;
    }

    /**
     * Sanitize distance value within range
     *
     * @since 2.0.0
     * @param mixed $value Input value
     * @param int $min Minimum value
     * @param int $max Maximum value
     * @return int Sanitized distance
     */
    protected function sanitizeDistance($value, int $min, int $max): int
    {
        return $this->sanitizeInteger($value, $min, $max);
    }

    /**
     * Sanitize percentage value (0-100)
     *
     * @since 2.0.0
     * @param mixed $value Input value
     * @return int Sanitized percentage
     */
    protected function sanitizePercentage($value): int
    {
        return $this->sanitizeInteger($value, 0, 100);
    }

    /**
     * Sanitize hex color value
     *
     * @since 2.0.0
     * @param mixed $value Input value
     * @param string $default Default hex color
     * @return string Sanitized hex color
     */
    protected function sanitizeHexColor($value, string $default): string
    {
        if (empty($value)) {
            return $default;
        }

        // Remove # if present
        $color = ltrim($value, '#');

        // Check if it's a valid 3 or 6 character hex color
        if (preg_match('/^[0-9A-Fa-f]{3}$|^[0-9A-Fa-f]{6}$/', $color)) {
            return '#' . $color;
        }

        return $default;
    }

    /**
     * Sanitize RGBA color value
     *
     * @since 2.0.0
     * @param mixed $value Input value
     * @param string $default Default RGBA color
     * @return string Sanitized RGBA color
     */
    protected function sanitizeRgbaColor($value, string $default): string
    {
        if (empty($value)) {
            return $default;
        }

        // Check if it's a valid RGBA color format
        if (preg_match('/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(?:,\s*[0-9]*\.?[0-9]+\s*)?\)$/i', $value)) {
            return $value;
        }

        return $default;
    }

    /**
     * Sanitize JSON string
     *
     * @since 2.0.0
     * @param string $json Raw JSON string
     * @return string Sanitized JSON string
     */
    protected function sanitizeJSON(string $json): string
    {
        if (empty(trim($json))) {
            return '';
        }

        // Validate JSON by attempting to decode
        $decoded = json_decode($json, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // Return original string if valid (avoid unnecessary re-encoding overhead)
            return $json;
        }

        // If invalid JSON, return empty string
        return '';
    }

    /**
     * Check if string is valid JSON
     *
     * @since 2.0.0
     * @param string $json JSON string
     * @return bool Whether valid JSON
     */
    protected function isValidJSON(string $json): bool
    {
        $decoded = json_decode($json, true);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Sanitize custom JavaScript code
     *
     * @since 2.0.0
     * @param string $code Raw JavaScript code
     * @return string Sanitized JavaScript code
     */
    protected function sanitizeCustomJavaScript(string $code): string
    {
        if (empty(trim($code))) {
            return '';
        }

        // Basic sanitization - remove potentially dangerous content
        $code = Helper::sanitizeInput($code, 'textarea');

        // Remove comments and normalize whitespace
        $code = preg_replace('/\/\*.*?\*\//s', '', $code);
        $code = preg_replace('/\/\/.*$/m', '', $code);
        $code = preg_replace('/\s+/', ' ', $code);
        $code = trim($code);

        // Basic validation - ensure it doesn't contain dangerous patterns
        if (!empty($code)) {
            // Block dangerous patterns instead of allowing only specific characters
            $dangerous_patterns = [
                '/<script[^>]*>.*?<\/script>/is',  // Script tags
                '/javascript:/i',                   // javascript: protocol
                '/on\w+\s*=/i',                     // Event handlers
                '/eval\s*\(/i',                     // eval calls
                '/Function\s*\(/i',                 // Function constructor
                '/setTimeout\s*\(/i',               // setTimeout (can be dangerous)
                '/setInterval\s*\(/i',              // setInterval (can be dangerous)
            ];

            foreach ($dangerous_patterns as $pattern) {
                if (preg_match($pattern, $code)) {
                    return '';
                }
            }
        }

        return $code;
    }

    /**
     * Check if pro features are allowed using a specific filter hook.
     *
     * @since 2.0.0
     * @param string $filter_hook The filter hook to check for pro features
     * @return bool True if pro features are allowed
     */
    protected function checkProFeatureAllowed(string $filter_hook): bool
    {
        // Use caching to avoid repeated expensive filter calls
        if (!isset(self::$proFeatureCache[$filter_hook])) {
            self::$proFeatureCache[$filter_hook] = apply_filters($filter_hook, null) !== null;
        }

        return self::$proFeatureCache[$filter_hook];
    }

    /**
     * Check if pro features are allowed (user has active pro license).
     *
     * Classes using this trait must implement this method with the appropriate filter hook.
     *
     * @return bool
     */
    abstract protected function isProFeatureAllowed(): bool;
}
