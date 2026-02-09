<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Core;

use Notifal\Shared\Utils\Helper;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Repositories\NotificationQuery;

defined('ABSPATH') || exit;

/**
 * Class NotificationActivationGuard
 *
 * Database-level protection to enforce single active notification limit for free users.
 * Prevents bypassing activation limits through direct database manipulation, REST API calls,
 * and import/export operations. Serves as the final security layer in the activation pipeline.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Application\Services
 */
class NotificationActivationGuard
{
    /**
     * Initialize the guard by registering WordPress hooks.
     *
     * @since 2.0.0
     * @return void
     */
    public static function init(): void
    {
        add_action('updated_post_meta', [__CLASS__, 'validateActivationOnMetaUpdate'], 10, 4);
        add_action('added_post_meta', [__CLASS__, 'validateActivationOnMetaAdd'], 10, 4);
        add_action('transition_post_status', [__CLASS__, 'syncPostStatusWithMetaKey'], 10, 3);
        add_action('init', [__CLASS__, 'cleanupMultipleActiveNotifications'], 5);
        add_action('admin_init', [__CLASS__, 'cleanupMultipleActiveNotifications'], 5);
    }

    /**
     * Validate activation when meta key is updated.
     * Prevents direct database manipulation to activate multiple notifications.
     *
     * @since 2.0.0
     * @param int $metaId Meta ID
     * @param int $postId Post ID
     * @param string $metaKey Meta key
     * @param mixed $metaValue Meta value
     * @return void
     */
    public static function validateActivationOnMetaUpdate(int $metaId, int $postId, string $metaKey, $metaValue): void
    {
        if ($metaKey !== '_notifal_notif_enabled') {
            return;
        }

        if ($metaValue !== '1') {
            return;
        }

        $post = Helper::getPostSafe($postId, 'notifal_onpage_notif');
        if (!$post) {
            return;
        }

        self::enforceActivationLimit($postId);
    }

    /**
     * Validate activation when meta key is added.
     * Prevents direct database manipulation to activate multiple notifications.
     *
     * @since 2.0.0
     * @param int $metaId Meta ID
     * @param int $postId Post ID
     * @param string $metaKey Meta key
     * @param mixed $metaValue Meta value
     * @return void
     */
    public static function validateActivationOnMetaAdd(int $metaId, int $postId, string $metaKey, $metaValue): void
    {
        if ($metaKey !== '_notifal_notif_enabled') {
            return;
        }

        if ($metaValue !== '1') {
            return;
        }

        $post = Helper::getPostSafe($postId, 'notifal_onpage_notif');
        if (!$post) {
            return;
        }

        self::enforceActivationLimit($postId);
    }

    /**
     * Get active notification IDs excluding specified posts.
     *
     * @since 2.0.0
     * @param array $exclude Array of post IDs to exclude
     * @return array Array of active notification IDs
     */
    private static function getActiveNotificationIds(array $exclude = []): array
    {
        return NotificationQuery::getActiveNotificationIds($exclude);
    }

    /**
     * Enforce activation limit for free users.
     * Core protection logic that disables other notifications when limit is exceeded.
     *
     * @since 2.0.0
     * @param int $currentPostId The post being activated
     * @return void
     */
    private static function enforceActivationLimit(int $currentPostId): void
    {
        if (apply_filters('notifal_pro_multiple_notifications_allowed', false)) {
            return;
        }

        $activeNotifications = self::getActiveNotificationIds([$currentPostId]);

        if (!empty($activeNotifications)) {
            Helper::securityLog('Multiple active notifications detected - enforcing limit', [
                'action' => 'enforce_limit',
                'current_notification' => $currentPostId,
                'disabled_count' => count($activeNotifications),
                'disabled_notifications' => $activeNotifications
            ]);

            foreach ($activeNotifications as $notificationId) {
                update_post_meta($notificationId, '_notifal_notif_enabled', '0');
                wp_update_post([
                    'ID' => $notificationId,
                    'post_status' => 'draft'
                ]);
            }
        }
    }

    /**
     * Sync post status with meta key on post status transitions.
     * Ensures post_status always reflects _notifal_notif_enabled meta value.
     *
     * @since 2.0.0
     * @param string $newStatus New post status
     * @param string $oldStatus Old post status
     * @param \WP_Post $post Post object
     * @return void
     */
    public static function syncPostStatusWithMetaKey(string $newStatus, string $oldStatus, \WP_Post $post): void
    {
        if ($post->post_type !== 'notifal_onpage_notif') {
            return;
        }

        $enabledMeta = get_post_meta($post->ID, '_notifal_notif_enabled', true);
        $expectedStatus = ($enabledMeta === '1') ? 'publish' : 'draft';

        if ($newStatus !== $expectedStatus && $newStatus !== 'trash') {
            if ($oldStatus === 'trash' || $newStatus === 'trash') {
                return;
            }

            $newEnabledValue = ($newStatus === 'publish') ? '1' : '0';
            update_post_meta($post->ID, '_notifal_notif_enabled', $newEnabledValue);
        }
    }

    /**
     * Cleanup multiple active notifications on initialization.
     * Ensures only one notification remains active for free users.
     *
     * @since 2.0.0
     * @return void
     */
    public static function cleanupMultipleActiveNotifications(): void
    {
        if (apply_filters('notifal_pro_multiple_notifications_allowed', false)) {
            return;
        }

        $activeNotifications = self::getActiveNotificationIds();

        if (count($activeNotifications) <= 1) {
            return;
        }

        // Keep the first notification (by ID order for consistency), disable others
        $notificationToKeep = min($activeNotifications);
        $notificationsToDisable = array_diff($activeNotifications, [$notificationToKeep]);

        Helper::securityLog('Multiple active notifications found during cleanup', [
            'action' => 'cleanup_multiple',
            'total_active' => count($activeNotifications),
            'kept_notification' => $notificationToKeep,
            'disabled_count' => count($notificationsToDisable),
            'disabled_notifications' => array_values($notificationsToDisable)
        ]);

        foreach ($notificationsToDisable as $notificationId) {
            update_post_meta($notificationId, '_notifal_notif_enabled', '0');
            wp_update_post([
                'ID' => $notificationId,
                'post_status' => 'draft'
            ]);
        }

        // Ensure the kept notification is properly activated
        update_post_meta($notificationToKeep, '_notifal_notif_enabled', '1');
        wp_update_post([
            'ID' => $notificationToKeep,
            'post_status' => 'publish'
        ]);
    }

    /**
     * Get count of currently active notifications.
     * Utility method for checking activation limits.
     *
     * @since 2.0.0
     * @return int Number of active notifications
     */
    public static function getActiveNotificationCount(): int
    {
        return count(self::getActiveNotificationIds());
    }

    /**
     * Check if activation of a specific notification is allowed.
     * Central utility for all activation limit checks across the application.
     *
     * @since 2.0.0
     * @param int|null $currentNotificationId ID of notification being activated (null for new)
     * @return bool True if activation is allowed
     */
    public static function canActivateNotification(?int $currentNotificationId = null): bool
    {
        if (apply_filters('notifal_pro_multiple_notifications_allowed', false)) {
            return true;
        }

        $exclude = $currentNotificationId ? [$currentNotificationId] : [];
        $activeCount = count(self::getActiveNotificationIds($exclude));

        return $activeCount < 1;
    }

    /**
     * Check if a specific notification is active.
     *
     * @since 2.0.0
     * @param int $postId Post ID
     * @return bool True if active, false otherwise
     */
    public static function isNotificationActive(int $postId): bool
    {
        $enabledMeta = get_post_meta($postId, '_notifal_notif_enabled', true);
        return $enabledMeta === '1';
    }
}

