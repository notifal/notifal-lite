<?php

namespace Notifal\Shared\Utils;

defined('ABSPATH') || exit;

/**
 * Class JsonImportValidator
 *
 * Validates pasted JSON payloads for admin import operations.
 * JSON must not be passed through text sanitizers because that corrupts the payload.
 *
 * @since 2.4.1
 * @author Hossein <hossein@notifal.com>
 */
class JsonImportValidator
{
    /**
     * Maximum allowed pasted JSON size in bytes (10MB).
     *
     * @since 2.4.1
     */
    const MAX_JSON_SIZE = 10485760;

    /**
     * Maximum JSON decode depth to limit nested payload attacks.
     *
     * @since 2.4.1
     */
    const MAX_JSON_DEPTH = 64;

    /**
     * Validate and decode pasted JSON from a POST field.
     *
     * @since 2.4.1
     * @param string $postKey POST field name that holds the raw JSON string.
     * @return array Decoded JSON as an associative array.
     * @throws \Exception When input is missing, too large, or invalid JSON.
     */
    public static function validateAndDecodeFromPost(string $postKey = 'notifal_import_json'): array
    {
        // Ensure the POST field exists before reading it.
        if (!isset($_POST[$postKey])) {
            throw new \Exception(__('No JSON data provided.', 'notifal'));
        }

        // Unslash only; do not sanitize JSON with text-field functions.
        $rawJson = wp_unslash($_POST[$postKey]);

        // Reject non-string payloads early.
        if (!is_string($rawJson)) {
            throw new \Exception(__('Invalid JSON input.', 'notifal'));
        }

        // Trim whitespace so empty-looking payloads are rejected.
        $rawJson = trim($rawJson);
        if ($rawJson === '') {
            throw new \Exception(__('Please paste valid JSON export data.', 'notifal'));
        }

        // Enforce the same size cap used for file uploads.
        if (strlen($rawJson) > self::MAX_JSON_SIZE) {
            throw new \Exception(__('JSON data is too large. Maximum size is 10MB.', 'notifal'));
        }

        // Decode with a depth limit; structure is validated by the importer after decode.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON must remain intact; decoded structure is validated separately.
        $decoded = json_decode($rawJson, true, self::MAX_JSON_DEPTH);

        // Surface decode failures with a user-friendly message.
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new \Exception(__('Invalid JSON format. Please check your data and try again.', 'notifal'));
        }

        return $decoded;
    }

    /**
     * Validate a raw JSON string (used by tests and non-POST callers).
     *
     * @since 2.4.1
     * @param string $rawJson Raw JSON string.
     * @return array Decoded JSON as an associative array.
     * @throws \Exception When input is empty, too large, or invalid JSON.
     */
    public static function validateAndDecodeString(string $rawJson): array
    {
        // Trim and reject empty strings.
        $rawJson = trim($rawJson);
        if ($rawJson === '') {
            throw new \Exception(__('Please paste valid JSON export data.', 'notifal'));
        }

        // Enforce maximum payload size.
        if (strlen($rawJson) > self::MAX_JSON_SIZE) {
            throw new \Exception(__('JSON data is too large. Maximum size is 10MB.', 'notifal'));
        }

        // Decode with depth limit.
        $decoded = json_decode($rawJson, true, self::MAX_JSON_DEPTH);

        // Validate decode result.
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new \Exception(__('Invalid JSON format. Please check your data and try again.', 'notifal'));
        }

        return $decoded;
    }
}
