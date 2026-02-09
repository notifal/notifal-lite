<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class ToggleStatusController
 *
 * Handles AJAX requests for toggling OnPage notification status (enabled/disabled).
 * Provides secure status toggling with proper validation and error handling.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax
 */
class ToggleStatusController
{
    /**
     * Register AJAX handlers.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register(): void
    {
        add_action('wp_ajax_notifal_toggle_notification_status', [self::class, 'handleToggle']);
        add_action('wp_ajax_notifal_check_multiple_notifications_allowed', [self::class, 'handleCheckMultipleAllowed']);
    }

    /**
     * Handle toggle notification status AJAX request.
     *
     * @since 2.0.0
     * @return void
     */
    public static function handleToggle(): void
    {
        $instance = new self();
        $instance->handleToggleInstance();
    }

    /**
     * Handle toggle notification status AJAX request (instance method).
     *
     * @since 2.0.0
     * @return void
     */
    private function handleToggleInstance(): void
    {
        notifal_verify_ajax_request('notifal_toggle_notification_status', 'edit_posts');

        $notificationId = absint($_POST['notification_id'] ?? 0);
        if (!$notificationId) {
            notifal_json_error(__('Invalid notification ID.', 'notifal'));
        }

        try {
            $post = Helper::getPostSafe($notificationId, 'notifal_onpage_notif');
            if (!$post) {
                notifal_json_error(__('Notification not found.', 'notifal'));
            }

            do_action(ActionHooks::ONPAGE_NOTIFICATION_STATUS_TOGGLE_BEFORE, $post);

            $currentEnabledMeta = get_post_meta($post->ID, '_notifal_notif_enabled', true);
            $currentIsEnabled = $currentEnabledMeta === '1';
            $isEnabled = !$currentIsEnabled;
            $newEnabledValue = $isEnabled ? '1' : '0';
            $newStatus = $isEnabled ? 'publish' : 'draft';

            update_post_meta($notificationId, '_notifal_notif_enabled', $newEnabledValue);

            $updateResult = wp_update_post([
                'ID' => $notificationId,
                'post_status' => $newStatus,
                'post_modified' => current_time('mysql'),
                'post_modified_gmt' => current_time('mysql', 1)
            ], true);

            if (is_wp_error($updateResult)) {
                update_post_meta($notificationId, '_notifal_notif_enabled', $currentEnabledMeta);
                notifal_json_error(__('Failed to update notification status. Please try again.', 'notifal'));
            }

            do_action(ActionHooks::ONPAGE_NOTIFICATION_STATUS_TOGGLE_AFTER, $post, $newStatus, $isEnabled);

            notifal_json_success([
                'message' => sprintf(
                    __('Notification %s successfully.', 'notifal'),
                    $isEnabled ? __('enabled', 'notifal') : __('disabled', 'notifal')
                ),
                'notification_id' => $notificationId,
                'new_status' => $newStatus,
                'is_enabled' => $isEnabled,
                'status_label' => $isEnabled ? __('Enabled', 'notifal') : __('Disabled', 'notifal'),
                'status_class' => $isEnabled ? 'notifal-status-enabled' : 'notifal-status-disabled'
            ]);

        } catch (\Exception $e) {
            notifal_json_error(__('An unexpected error occurred. Please try again.', 'notifal'));
        }
    }

    /**
     * Handle AJAX request to check if multiple notifications are allowed.
     *
     * @since 2.0.0
     * @return void
     */
    public static function handleCheckMultipleAllowed(): void
    {
        $instance = new self();
        $instance->handleCheckMultipleAllowedInstance();
    }

    /**
     * Handle AJAX request to check if multiple notifications are allowed (instance method).
     *
     * @since 2.0.0
     * @return void
     */
    private function handleCheckMultipleAllowedInstance(): void
    {
        notifal_verify_ajax_request('notifal_check_multiple_notifications_allowed', 'edit_posts');

        try {
            $allowed = apply_filters('notifal_pro_multiple_notifications_allowed', false);

            notifal_json_success([
                'allowed' => $allowed,
                'message' => $allowed ?
                    __('Multiple notifications are allowed.', 'notifal') :
                    __('Multiple notifications require Notifal Pro with active license.', 'notifal')
            ]);

        } catch (\Exception $e) {
            notifal_json_error(__('Unable to verify notification permissions.', 'notifal'));
        }
    }
}
