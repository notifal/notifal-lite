<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax;

use Notifal\Modules\OnPageNotification\Application\Services\API\PreCreatedNotificationsApiService;
use Notifal\Modules\OnPageNotification\Helpers\PreCreatedNotificationBuilderTypes;
use Notifal\Modules\OnPageNotification\Helpers\PreCreatedNotificationFilterHelper;
use Notifal\Modules\OnPageNotification\Helpers\PreCreatedNotificationRequirementsHelper;

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
        // Template request disabled for now (marketplace still supports requests server-side).
        // add_action('wp_ajax_notifal_submit_template_request', [self::class, 'submitTemplateRequest']);
        add_action('wp_ajax_notifal_precreated_archive_fragment', [self::class, 'archiveFragment']);

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
            $notificationData = is_array($apiResponse['data'] ?? null) ? $apiResponse['data'] : [];

            // Evaluate minimum Notifal version requirement for import eligibility.
            $versionRequirement = PreCreatedNotificationRequirementsHelper::evaluateNotifalVersionRequirement($notificationData);
            $requirements = isset($notificationData['requirements']) && is_array($notificationData['requirements'])
                ? $notificationData['requirements']
                : [];

            // Format data structure for popup display
            $formattedData = [
                'id' => $notificationData['id'] ?? 0,
                'title' => $notificationData['title'] ?? '',
                'content' => $notificationData['content'] ?? '',
                'taxonomies' => $notificationData['taxonomies'] ?? [],
                'requirements' => $requirements,
                'min_notifal_version' => $versionRequirement['min_notifal_version'],
                'meets_notifal_version' => $versionRequirement['meets_notifal_version'],
                'version_requirement_message' => $versionRequirement['message'],
                'current_notifal_version' => PreCreatedNotificationRequirementsHelper::getCurrentNotifalVersion(),
                'plugins_url' => admin_url('plugins.php'),
                'files' => $notificationData['files'] ?? [
                    'elementor_available' => false,
                    'block_editor_available' => false,
                    'html_builder_available' => false,
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

    /**
     * Handle submit template request AJAX.
     *
     * Disabled: template request flow is not used in the client plugin for now.
     *
     * @since 2.0.0
     * @return void
     */
    /*
    public static function submitTemplateRequest(): void
    {
        try {
            notifal_verify_ajax_request('notifal_admin_ajax_nonce', 'edit_posts', 'nonce');

            $notificationId = absint($_POST['notification_id'] ?? 0);
            $builderType    = sanitize_text_field($_POST['builder_type'] ?? '');

            if ($notificationId <= 0) {
                notifal_json_error(__('Invalid notification ID.', 'notifal'));
                return;
            }

            if (!PreCreatedNotificationBuilderTypes::isValidImportFileType($builderType)) {
                notifal_json_error(__('Invalid builder type.', 'notifal'));
                return;
            }

            if (self::hasUserAlreadyRequestedTemplate($notificationId, $builderType)) {
                notifal_json_error(self::getDuplicateRequestMessage());
                return;
            }

            $apiService = self::getApiService();
            $result     = $apiService->submitTemplateRequest($notificationId, $builderType);

            if (!empty($result['success'])) {
                self::markTemplateRequestedForUser($notificationId, $builderType);
                notifal_json_success([
                    'message' => $result['message'] ?? __('We got your request. We will create the template within two days so you can check again and import it. We will inform you via email', 'notifal'),
                ]);
                return;
            }

            notifal_json_error($result['error'] ?? __('Request could not be submitted. Please try again.', 'notifal'));

        } catch (\Exception $e) {
            notifal_json_error(__('An unexpected error occurred.', 'notifal'));
        }
    }

    private const USER_META_TEMPLATE_REQUESTS = '_notifal_template_requests';

    private static function hasUserAlreadyRequestedTemplate(int $notificationId, string $builderType): bool
    {
        $userId = get_current_user_id();
        if ($userId <= 0) {
            return false;
        }

        $raw = get_user_meta($userId, self::USER_META_TEMPLATE_REQUESTS, true);
        if (!is_array($raw)) {
            return false;
        }

        $validTypes = PreCreatedNotificationBuilderTypes::getImportFileTypes();
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $nid = isset($item['notification_id']) ? absint($item['notification_id']) : 0;
            $builder = isset($item['builder_type']) ? sanitize_text_field($item['builder_type']) : '';
            if ($nid === $notificationId && $builder !== '' && in_array($builder, $validTypes, true) && $builder === $builderType) {
                return true;
            }
        }

        return false;
    }

    private static function getDuplicateRequestMessage(): string
    {
        $email = get_option('admin_email', '');
        $email = is_string($email) ? sanitize_email($email) : '';
        if ($email !== '') {
            return sprintf(
                __('Request already submitted. We will notify %s when it is ready.', 'notifal'),
                $email
            );
        }
        return __('Request already submitted. We will notify you when it is ready.', 'notifal');
    }

    private static function markTemplateRequestedForUser(int $notificationId, string $builderType): void
    {
        $userId = get_current_user_id();
        if ($userId <= 0) {
            return;
        }

        $raw = get_user_meta($userId, self::USER_META_TEMPLATE_REQUESTS, true);
        $list = is_array($raw) ? $raw : [];

        foreach ($list as $item) {
            if (is_array($item) && isset($item['notification_id'], $item['builder_type'])
                && (int) $item['notification_id'] === $notificationId && $item['builder_type'] === $builderType) {
                return;
            }
        }

        $list[] = [
            'notification_id' => $notificationId,
            'builder_type'    => $builderType,
        ];

        update_user_meta($userId, self::USER_META_TEMPLATE_REQUESTS, $list);
    }
    */

    /**
     * Handle AJAX request for pre-created archive HTML fragment.
     *
     * Fetches taxonomies and notifications from notifal.com (with 15s timeout)
     * and returns the archive markup so the list page can load without blocking.
     *
     * @since 2.0.0
     * @return void
     */
    public static function archiveFragment(): void
    {
        try {
            notifal_verify_ajax_request('notifal_admin_ajax_nonce', 'edit_posts', 'nonce');

            $rawFilters = isset($_POST['filters']) && is_array($_POST['filters']) ? $_POST['filters'] : [];
            $filters = self::sanitizeFilters($rawFilters);
            $filters['page'] = 1;

            $apiService = self::getApiService();

            // Warm taxonomy + trending caches first (often cached); notifications call is the heaviest.
            $taxonomies = $apiService->getTaxonomies();
            $preloaded_trending = $apiService->getTrendingCategories();
            $apiResponse = $apiService->getNotifications($filters);

            $currentFilters = [
                'search'    => $filters['search'],
                'orderby'   => $filters['orderby'],
                'use_case'  => $filters['use_cases'] ?? [],
                'event'     => $filters['events'] ?? [],
                'industry'  => $filters['industries'] ?? [],
                'layout'    => $filters['layouts'] ?? [],
                'plugin'    => $filters['used_plugins'] ?? [],
                'is_pro'    => $filters['is_pro'] ?? '',
            ];

            $preloaded_taxonomies = $taxonomies;
            $preloaded_api_response = $apiResponse;
            $preloaded_filters = $currentFilters;

            // Modal context: hide duplicate title/wrapper (modal already has its own header).
            $archiveContext = 'page';
            if (isset($_POST['archive_context'])) {
                $archiveContext = sanitize_key(wp_unslash((string) $_POST['archive_context']));
            }
            if ($archiveContext === 'modal') {
                $hide_header = true;
                $hide_wrapper = true;
                $component_id = 'precreated-notifications-modal-archive';
            }

            $viewPath = dirname(__DIR__, 2) . '/Views/components/precreated-notifications-archive.php';
            if (!is_readable($viewPath)) {
                notifal_json_error(__('Archive view not found.', 'notifal'));
                return;
            }

            ob_start();
            include $viewPath;
            $html = ob_get_clean();

            if ($html === false) {
                notifal_json_error(__('Failed to render archive.', 'notifal'));
                return;
            }

            notifal_json_success(['html' => $html]);
        } catch (\Exception $e) {
            notifal_json_error(__('Unable to load pre-created notifications. Please try again.', 'notifal'));
        }
    }

}
