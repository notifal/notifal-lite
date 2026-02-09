<?php

namespace Notifal\Modules\OnPageNotification\Application\Traits;

defined('ABSPATH') || exit;

/**
 * Trait NotificationDataTrait
 *
 * Provides common functionality for accessing notification data
 * across different services that need notification data retrieval.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Traits
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
trait NotificationDataTrait
{
    /**
     * Get notification data from save service.
     *
     * Retrieves complete notification configuration data including settings,
     * appearance, behavior, timing, and content information from the
     * NotificationSaveService. Returns empty array if notification data
     * cannot be retrieved or is invalid.
     *
     * @param \WP_Post $notification Notification post object
     * @return array Notification data array or empty array on failure
     * @since 2.0.0
     */
    protected function getNotificationData(\WP_Post $notification): array
    {
        // Validate notification post object
        if (!$notification instanceof \WP_Post || empty($notification->ID)) {
            return [];
        }

        $saveService = notifal_app(\Notifal\Modules\OnPageNotification\Application\Services\Core\NotificationSaveService::class);
        return $saveService->getNotificationData($notification->ID) ?: [];
    }
}
