<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax;

use Notifal\Modules\OnPageNotification\Application\Services\Core\NotificationSaveService;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * AJAX Controller for saving and retrieving on-page notification data.
 *
 * Handles secure AJAX operations for notification CRUD operations with
 * comprehensive validation, sanitization, and error handling.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax
 */
class SaveNotificationController
{
    /**
     * Register AJAX action handlers.
     *
     * Hooks into WordPress AJAX actions for notification save and data retrieval.
     * Ensures proper security checks are applied to all operations.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register(): void
    {
        add_action('wp_ajax_notifal_save_notification', [self::class, 'handleSave']);
        add_action('wp_ajax_notifal_get_notification_data', [self::class, 'handleGetData']);
    }

    /**
     * Process notification save AJAX request.
     *
     * Validates input data, checks user permissions, and delegates
     * business logic to the NotificationSaveService for processing.
     *
     * @since 2.0.0
     * @return void
     */
    public static function handleSave(): void
    {
        try {
            // Security: Verify nonce and user capabilities before processing
            notifal_verify_ajax_request('notifal_save_notification', 'edit_posts');

            $saveService = notifal_app(NotificationSaveService::class);

            // Handle existing notification editing scenario
            $notificationId = null;
            if (!empty($_POST['notification_id'])) {
                $notificationId = absint($_POST['notification_id']);

                // Security: Validate that the notification exists before proceeding
                $post = Helper::getPostSafe($notificationId, 'notifal_onpage_notif');
                if (!$post) {
                    notifal_json_error(__('Notification not found.', 'notifal'));
                    return;
                }
            }

            // Delegate business logic to service layer
            $result = $saveService->saveNotification($_POST, $notificationId);

            if ($result['success']) {
                notifal_json_success($result);
            } else {
                notifal_json_error($result['message']);
            }

        } catch (\Exception $e) {
            // Log unexpected errors for debugging while providing user-friendly message
            error_log('Notifal SaveNotification Error: ' . $e->getMessage());
            notifal_json_error(__('An unexpected error occurred. Please try again.', 'notifal'));
        }
    }

    /**
     * Retrieve notification data for editing via AJAX.
     *
     * Fetches existing notification configuration data securely,
     * ensuring user has appropriate permissions to access the data.
     *
     * @since 2.0.0
     * @return void
     */
    public static function handleGetData(): void
    {
        try {
            // Security: Verify nonce and user capabilities for GET request
            notifal_verify_get_request('notifal_get_notification_data', 'edit_posts');

            $notificationId = absint($_GET['notification_id'] ?? 0);

            if (!$notificationId) {
                notifal_json_error(__('Invalid notification ID.', 'notifal'));
                return;
            }

            $saveService = notifal_app(NotificationSaveService::class);
            $data = $saveService->getNotificationData($notificationId);

            if (!$data) {
                notifal_json_error(__('Notification not found.', 'notifal'));
                return;
            }

            notifal_json_success($data);

        } catch (\Exception $e) {
            // Log unexpected errors for debugging while providing user-friendly message
            error_log('Notifal GetNotificationData Error: ' . $e->getMessage());
            notifal_json_error(__('An unexpected error occurred. Please try again.', 'notifal'));
        }
    }
} 
