<?php

namespace Notifal\Modules\OnPageNotification\Helpers;

defined('ABSPATH') || exit;

/**
 * Class UrlParameterParser
 *
 * Provides helper methods for parsing URL parameters with proper sanitization.
 * Handles comma-separated values commonly used in filter systems.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class UrlParameterParser
{
    /**
     * Parse a comma-separated value from URL parameters.
     *
     * Safely extracts and sanitizes comma-separated values from $_GET parameters,
     * commonly used for taxonomy filters and other multi-value selections.
     *
     * @param string $param_name The name of the URL parameter to parse
     * @return array Array of sanitized, non-empty values
     *
     * @since 2.0.0
     */
    public static function parseCommaSeparated(string $param_name): array
    {
        if (!isset($_GET[$param_name])) {
            return [];
        }

        $value = sanitize_text_field(wp_unslash($_GET[$param_name]));

        if (empty($value)) {
            return [];
        }

        // Split by comma and trim whitespace
        $values = array_map('trim', explode(',', $value));

        // Remove empty values and re-index array
        return array_values(array_filter($values, function($val) {
            return !empty($val);
        }));
    }

    /**
     * Get a sanitized text field from URL parameters.
     *
     * @param string $param_name The name of the URL parameter
     * @param string $default Default value if parameter is not set
     * @return string Sanitized parameter value
     *
     * @since 2.0.0
     */
    public static function getTextField(string $param_name, string $default = ''): string
    {
        return isset($_GET[$param_name])
            ? sanitize_text_field(wp_unslash($_GET[$param_name]))
            : $default;
    }
}
