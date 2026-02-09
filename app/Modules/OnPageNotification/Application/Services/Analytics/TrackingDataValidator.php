<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Analytics;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class TrackingDataValidator
 *
 * Validates tracking data for OnPage notification events.
 * Provides centralized validation logic used by both TrackingService and EventQueue.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services\Analytics
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class TrackingDataValidator
{
    /**
     * Valid event types for tracking.
     */
    private const VALID_EVENT_TYPES = ['impression', 'click', 'close', 'dismiss'];

    /**
     * Required fields for tracking data.
     */
    private const REQUIRED_FIELDS = ['notification_id', 'event_type'];

    /**
     * Validate tracking data from frontend or queue.
     *
     * @param array $trackingData Raw tracking data
     * @param array $additionalFields Optional additional fields to include in validation
     * @param string $timestampFormat Format for timestamp: 'unix' or 'mysql'
     * @return array Validation result with 'valid' boolean and 'data' array or 'message' string
     * @since 2.0.0
     */
    public function validate(array $trackingData, array $additionalFields = [], string $timestampFormat = 'unix'): array
    {
        /**
         * Fires before tracking data validation begins.
         *
         * @since 2.0.0
         * @param array $trackingData Raw tracking data being validated
         */
        do_action(ActionHooks::ONPAGE_TRACKING_VALIDATION_BEFORE, $trackingData);

        // Get validation rules with filter support
        $validationRules = apply_filters(
            FilterHooks::ONPAGE_TRACKING_VALIDATION_RULES,
            [
                'required_fields' => self::REQUIRED_FIELDS,
                'valid_event_types' => self::VALID_EVENT_TYPES,
            ]
        );

        // Check required fields
        foreach ($validationRules['required_fields'] as $field) {
            if (empty($trackingData[$field])) {
                return [
                    'valid' => false,
                    'message' => sprintf(__('Missing required field: %s', 'notifal'), $field),
                ];
            }
        }

        // Validate notification ID
        $notificationId = absint($trackingData['notification_id']);
        if ($notificationId <= 0) {
            return [
                'valid' => false,
                'message' => __('Invalid notification ID', 'notifal'),
            ];
        }

        // Validate event type
        if (!in_array($trackingData['event_type'], $validationRules['valid_event_types'], true)) {
            return [
                'valid' => false,
                'message' => __('Invalid event type', 'notifal'),
            ];
        }

        // Check if notification exists
        $notification = Helper::getPostSafe($notificationId, 'notifal_onpage_notif');
        if (!$notification) {
            return [
                'valid' => false,
                'message' => __('Notification not found', 'notifal'),
            ];
        }

        // Validate and sanitize timestamp
        $timestamp = $this->validateAndSanitizeTimestamp($trackingData['timestamp'] ?? '');

        // Build validated data
        $validatedData = $this->buildValidatedData($trackingData, $notificationId, $timestamp, $additionalFields, $timestampFormat);

        $result = [
            'valid' => true,
            'data' => $validatedData,
        ];

        /**
         * Fires after tracking data validation completes successfully.
         *
         * @since 2.0.0
         * @param array $result Validation result with 'valid' and 'data' keys
         * @param array $trackingData Original tracking data
         */
        do_action(ActionHooks::ONPAGE_TRACKING_VALIDATION_AFTER, $result, $trackingData);

        return $result;
    }

    /**
     * Validate and sanitize timestamp.
     *
     * @param mixed $timestamp Raw timestamp from input
     * @return int Sanitized Unix timestamp
     * @since 2.0.0
     */
    private function validateAndSanitizeTimestamp($timestamp): int
    {
        if (!empty($timestamp) && is_string($timestamp)) {
            $parsedTimestamp = strtotime($timestamp);
        } else {
            $parsedTimestamp = time();
        }

        // Ensure timestamp is valid - fallback to current time if invalid
        if ($parsedTimestamp === false || $parsedTimestamp <= 0) {
            $parsedTimestamp = current_time('timestamp');
        }

        return absint($parsedTimestamp);
    }

    /**
     * Build validated and sanitized data array.
     *
     * @param array $trackingData Raw tracking data
     * @param int $notificationId Validated notification ID
     * @param int $timestamp Validated timestamp
     * @param array $additionalFields Additional fields to include
     * @param string $timestampFormat Format for timestamp: 'unix' or 'mysql'
     * @return array Sanitized tracking data
     * @since 2.0.0
     */
    private function buildValidatedData(array $trackingData, int $notificationId, int $timestamp, array $additionalFields = [], string $timestampFormat = 'unix'): array
    {
        // Format timestamp based on required format
        $formattedTimestamp = ($timestampFormat === 'mysql') ? date('Y-m-d H:i:s', $timestamp) : $timestamp;

        $baseData = [
            'notification_id' => $notificationId,
            'event_type' => Helper::sanitizeInput($trackingData['event_type'], 'text'),
            'timestamp' => $formattedTimestamp,
            'user_id' => get_current_user_id(),
            'user_agent' => Helper::sanitizeInput($trackingData['user_agent'] ?? '', 'text'),
            'referrer' => Helper::sanitizeInput($trackingData['referrer'] ?? '', 'url'),
            'page_url' => Helper::sanitizeInput($trackingData['page_url'] ?? '', 'url'),
            'ip_address' => Helper::getClientIpAddress(),
            'session_id' => Helper::getSessionId(),
        ];

        // Add additional fields if provided
        foreach ($additionalFields as $field => $defaultValue) {
            $baseData[$field] = Helper::sanitizeInput($trackingData[$field] ?? $defaultValue, 'text');
        }

        return $baseData;
    }
}
