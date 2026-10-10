<?php

namespace Notifal\Modules\Forms\Application\Services;

defined('ABSPATH') || exit;

/**
 * Normalizes submitted field values for case-insensitive unique checks.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class UniqueValueNormalizer
{
    /**
     * Normalize a scalar stored/submitted value before unique comparison.
     *
     * Trims outer whitespace and lowercases using multibyte when available so
     * email local parts and text values match regardless of submission casing.
     *
     * @param mixed $value Sanitized scalar or checkbox array value.
     * @return string Empty string when the value is empty after normalization.
     * @since 3.0.0
     */
    public static function toCompareString($value): string
    {
        // Checkbox groups are persisted as JSON; encode before scalar normalization.
        if (is_array($value)) {
            $encoded = wp_json_encode($value);
            $value = is_string($encoded) ? $encoded : '';
        } elseif (is_float($value) || is_int($value)) {
            // Numbers are stored as plain strings in the submission fields table.
            $value = (string) $value;
        } else {
            $value = (string) $value;
        }

        // Trim leading/trailing whitespace before case folding.
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        // Prefer UTF-8 aware lowercasing when the mbstring extension is loaded.
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($trimmed, 'UTF-8');
        }

        return strtolower($trimmed);
    }
}
