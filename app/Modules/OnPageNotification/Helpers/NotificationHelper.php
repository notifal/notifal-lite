<?php

namespace Notifal\Modules\OnPageNotification\Helpers;

defined('ABSPATH') || exit;

/**
 * Notification Helper Class
 *
 * Provides common utility functions for OnPage notification operations.
 * Centralizes frequently used logic to reduce duplication and ensure consistency.
 *
 * @package Notifal\Modules\OnPageNotification\Helpers
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class NotificationHelper
{
    /**
     * Check if a notification is enabled based on its meta value.
     *
     * This method centralizes the logic for determining notification enabled status,
     * which was previously duplicated across multiple classes. The enabled status
     * is stored in the '_notifal_notif_enabled' meta key with value '1' for enabled
     * and '0' or missing for disabled.
     *
     * @param int|\WP_Post $notification Notification ID or post object
     * @return bool True if notification is enabled, false otherwise
     * @since 2.0.0
     */
    public static function isNotificationEnabled($notification): bool
    {
        $postId = is_a($notification, \WP_Post::class) ? $notification->ID : $notification;

        if (!is_int($postId) || $postId <= 0) {
            return false;
        }

        $enabledMeta = get_post_meta($postId, '_notifal_notif_enabled', true);
        return $enabledMeta === '1';
    }

    /**
     * Get the enabled status label for a notification.
     *
     * @param int|\WP_Post $notification Notification ID or post object
     * @return string Localized status label ('Enabled' or 'Disabled')
     * @since 2.0.0
     */
    public static function getNotificationStatusLabel($notification): string
    {
        $isEnabled = self::isNotificationEnabled($notification);
        return $isEnabled ? __('Enabled', 'notifal') : __('Disabled', 'notifal');
    }

    /**
     * Get the CSS class for notification enabled status.
     *
     * @param int|\WP_Post $notification Notification ID or post object
     * @return string CSS class ('notifal-status-enabled' or 'notifal-status-disabled')
     * @since 2.0.0
     */
    public static function getNotificationStatusClass($notification): string
    {
        $isEnabled = self::isNotificationEnabled($notification);
        return $isEnabled ? 'notifal-status-enabled' : 'notifal-status-disabled';
    }
}