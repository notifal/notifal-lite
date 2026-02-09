<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax;

use Notifal\Modules\OnPageNotification\Application\Services\API\PreCreatedNotificationsApiService;

defined('ABSPATH') || exit;

/**
 * Pre-created Notifications AJAX Controller
 *
 * Handles AJAX requests for the pre-created notifications archive functionality.
 * Processes requests for loading archive content, filtering, and importing notifications.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax
 */
class PreCreatedNotificationsAjaxController
{
    /**
     * Register AJAX handlers for pre-created notifications.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register(): void
    {
        add_action('wp_ajax_notifal_load_more_precreated_notifications', [self::class, 'loadMoreNotifications']);
        add_action('wp_ajax_notifal_filter_precreated_notifications', [self::class, 'filterNotifications']);
        add_action('wp_ajax_notifal_get_single_precreated_notification', [self::class, 'getSingleNotification']);

        // Register import controller
        PreCreatedNotificationsImportController::register();
    }

    /**
     * Get the API service instance.
     *
     * @since 2.0.0
     * @return PreCreatedNotificationsApiService
     */
    private static function getApiService(): PreCreatedNotificationsApiService
    {
        return notifal_app(PreCreatedNotificationsApiService::class);
    }


    /**
     * Handle filter notifications AJAX request.
     *
     * Processes filter parameters and returns paginated notification results.
     * Applies filters to the marketplace API and returns filtered results.
     *
     * @since 2.0.0
     * @return void
     */
    public static function filterNotifications(): void
    {
        try {
            // Verify nonce and user capabilities for security
            notifal_verify_ajax_request('notifal_admin_ajax_nonce', 'edit_posts', 'nonce');

            // Sanitize and validate filter parameters
            $filters = self::sanitizeFilters($_POST['filters'] ?? []);

            // Reset to page 1 when applying new filters
            $filters['page'] = 1;

            // Get data from API
            $apiService = self::getApiService();
            $apiResponse = $apiService->getNotifications($filters);

            if (isset($apiResponse['success']) && !$apiResponse['success']) {
                notifal_json_error($apiResponse['error'] ?? __('Failed to filter notifications.', 'notifal'));
                return;
            }

            $notifications = $apiResponse['data']['notifications'] ?? [];
            $pagination = $apiResponse['data']['pagination'] ?? ['current' => 1, 'pages' => 1];

            notifal_json_success([
                'notifications' => $notifications,
                'pagination' => $pagination
            ]);

        } catch (\Exception $e) {
            notifal_json_error(__('An unexpected error occurred.', 'notifal'));
        }
    }

    /**
     * Handle load more notifications AJAX request.
     *
     * Loads additional notifications for infinite scroll/pagination.
     * Uses existing filter parameters to maintain consistent results.
     *
     * @since 2.0.0
     * @return void
     */
    public static function loadMoreNotifications(): void
    {
        try {
            // Verify nonce and user capabilities for security
            notifal_verify_ajax_request('notifal_admin_ajax_nonce', 'edit_posts', 'nonce');

            // Sanitize and validate filter parameters
            $filters = self::sanitizeFilters($_POST['filters'] ?? []);

            // Get data from API
            $apiService = self::getApiService();
            $apiResponse = $apiService->getNotifications($filters);

            if (isset($apiResponse['success']) && !$apiResponse['success']) {
                notifal_json_error($apiResponse['error'] ?? __('Failed to load more notifications.', 'notifal'));
                return;
            }

            $notifications = $apiResponse['data']['notifications'] ?? [];
            $pagination = $apiResponse['data']['pagination'] ?? ['current' => 1, 'pages' => 1];

            notifal_json_success([
                'notifications' => $notifications,
                'pagination' => $pagination,
                'has_more' => ($pagination['current'] ?? 1) < ($pagination['pages'] ?? 1)
            ]);

        } catch (\Exception $e) {
            notifal_json_error(__('An unexpected error occurred.', 'notifal'));
        }
    }

    /**
     * Sanitize and validate filter data.
     *
     * @since 2.0.0
     * @param array $rawFilters Raw filter data from request
     * @return array Sanitized filter data
     */
    private static function sanitizeFilters(array $rawFilters): array
    {
        $sanitized = [];

        // Sanitize text fields
        $sanitized['search'] = sanitize_text_field($rawFilters['search'] ?? '');
        $sanitized['orderby'] = sanitize_text_field($rawFilters['orderby'] ?? 'recent');

        // Sanitize numeric fields with validation
        $sanitized['page'] = max(1, absint($rawFilters['page'] ?? 1));
        $sanitized['per_page'] = min(50, max(1, absint($rawFilters['per_page'] ?? 12)));

        // Sanitize taxonomy arrays - ensure they are arrays and sanitize each value
        $taxonomies = ['use_cases', 'events', 'industries', 'layouts', 'used_plugins'];
        foreach ($taxonomies as $taxonomy) {
            if (isset($rawFilters[$taxonomy]) && is_array($rawFilters[$taxonomy])) {
                $sanitized[$taxonomy] = array_filter(
                    array_map('sanitize_text_field', $rawFilters[$taxonomy]),
                    function($value) {
                        return !empty($value);
                    }
                );
            } else {
                $sanitized[$taxonomy] = [];
            }
        }

        // Sanitize license filter with strict validation
        $validLicenseValues = ['0', '1', ''];
        $sanitized['is_pro'] = (isset($rawFilters['is_pro']) && in_array($rawFilters['is_pro'], $validLicenseValues, true))
            ? $rawFilters['is_pro']
            : '';

        return $sanitized;
    }

    /**
     * Handle get single notification AJAX request.
     *
     * Retrieves detailed information for a specific notification to display in popup/modal.
     * Formats the API response for frontend consumption.
     *
     * @since 2.0.0
     * @return void
     */
    public static function getSingleNotification(): void
    {
        try {
            // Verify nonce and user capabilities for security
            notifal_verify_ajax_request('notifal_admin_ajax_nonce', 'edit_posts', 'nonce');

            // Sanitize and validate notification ID
            $notificationId = absint($_POST['notification_id'] ?? 0);

            if ($notificationId <= 0) {
                notifal_json_error(__('Invalid notification ID.', 'notifal'));
                return;
            }

            // Fetch notification details from marketplace API
            $apiService = self::getApiService();
            $apiResponse = $apiService->getSingleNotification($notificationId);

            if (isset($apiResponse['success']) && !$apiResponse['success']) {
                notifal_json_error($apiResponse['error'] ?? __('Failed to load notification details.', 'notifal'));
                return;
            }

            // Extract notification data from API response
            $notificationData = $apiResponse['data'] ?? [];

            // Format data structure for popup display
            $formattedData = [
                'id' => $notificationData['id'] ?? 0,
                'title' => $notificationData['title'] ?? '',
                'content' => $notificationData['content'] ?? '',
                'taxonomies' => $notificationData['taxonomies'] ?? [],
                'files' => $notificationData['files'] ?? [
                    'elementor_available' => false,
                    'block_editor_available' => false,
                ],
                'images' => [
                    'desktop' => [
                        'url' => $notificationData['desktop_image'] ?? '',
                        'alt' => $notificationData['title'] ?? '',
                    ],
                    'mobile' => [
                        'url' => $notificationData['mobile_image'] ?? '',
                        'alt' => $notificationData['title'] ?? '',
                    ],
                ],
            ];

            notifal_json_success($formattedData);

        } catch (\Exception $e) {
            notifal_json_error(__('An unexpected error occurred.', 'notifal'));
        }
    }

}
