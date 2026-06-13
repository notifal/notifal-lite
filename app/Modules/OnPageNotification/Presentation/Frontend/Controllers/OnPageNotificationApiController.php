<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Frontend\Controllers;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\OnPageNotification\Application\Services\Core\CacheManager;
use Notifal\Modules\OnPageNotification\Application\Support\PageContextEnricher;
use Notifal\Modules\OnPageNotification\Application\Support\PageContextHelper;
use Notifal\Modules\OnPageNotification\Application\Services\Rules\CartDisplayRulesUsageChecker;
use Notifal\Modules\OnPageNotification\Application\Services\Rules\WooCommerceCartContextBuilder;
use Notifal\Modules\OnPageNotification\Application\Services\Core\NotificationDataPreparer;
use Notifal\Modules\OnPageNotification\Application\Services\Template\FrontendTemplateRenderer;
use Notifal\Modules\OnPageNotification\Application\Services\Core\EligibilityService;
use Notifal\Modules\OnPageNotification\Application\Services\Analytics\TrackingService;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Repositories\NotificationQuery;
use Notifal\Shared\Utils\Helper;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * OnPage Notification API Controller
 *
 * Handles REST API endpoints for frontend notification functionality including
 * eligibility checking, event tracking, and user preferences management.
 *
 * @package Notifal\Modules\OnPageNotification\Presentation\Frontend\Controllers
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class OnPageNotificationApiController
{
    /**
     * @var EligibilityService
     */
    private $eligibilityService;

    /**
     * @var TrackingService
     */
    private $trackingService;

    /**
     * @var FrontendTemplateRenderer
     */
    private $templateRenderer;

    /**
     * @var NotificationDataPreparer
     */
    private $dataPreparer;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->eligibilityService = notifal_app(EligibilityService::class);
        $this->trackingService = notifal_app(TrackingService::class);
        $this->templateRenderer = notifal_app(FrontendTemplateRenderer::class);
        $this->dataPreparer = notifal_app(NotificationDataPreparer::class);
    }

    /**
     * Get eligible notifications for the current context.
     *
     * Retrieves notifications that are eligible for display based on current
     * user context, display rules, and timing settings.
     *
     * No nonce required: this is a read-only GET endpoint returning only content
     * intended for public display. Omitting nonce allows the endpoint to work with
     * full-page cache, static HTML, and when wp_rest nonce is unavailable or stale,
     * avoiding 403 responses that can hurt load performance and user experience.
     *
     * @param WP_REST_Request $request Request object containing context parameters
     * @return WP_REST_Response|WP_Error Response object with notification data or error
     * @since 2.0.0
     */
    public function getEligibleNotifications(WP_REST_Request $request)
    {
        try {
            // Check if we should serve notifications in the current context
            if (!$this->shouldServeNotifications($request)) {
                return new WP_REST_Response([
                    'success' => true,
                    'notifications' => [],
                    'count' => 0,
                    'timestamp' => current_time('timestamp'),
                    'context' => ['editor_mode' => true],
                ], 200);
            }

            // Build context from request parameters
            $context = $this->buildContextFromRequest($request);

            // Check if this is a retrigger request and clear context cache for fresh content
            $isRetrigger = $request->get_header('X-Notifal-Retrigger') === '1' ||
                          $request->get_param('refresh') ||
                          $request->get_param('cache_bust');

            // Check for forced fresh content request for specific notification
            $forceFreshNotificationId = $request->get_header('X-Notifal-Force-Fresh') ?: $request->get_param('refresh_notification_id');
            $forceFreshContent = $request->get_param('force_fresh_content') === '1';

            if ($isRetrigger || $forceFreshNotificationId || $forceFreshContent) {
                // Clear caches to ensure fresh random content
                $cacheManager = notifal_app(CacheManager::class);
                $cacheManager->clearAllCaches();

                // If forcing fresh content for a specific notification, add it to context
                if ($forceFreshNotificationId) {
                    $context['force_fresh_notification_id'] = $forceFreshNotificationId;
                    $context['force_fresh_content'] = true;
                }

                // Load notification settings so smart targeting level 0 can block contextual retrigger widening.
                $retriggerContentSourceSettings = [];
                if ($forceFreshNotificationId) {
                    $retriggerContentSourceSettings = $this->loadContentSourceSettingsForNotification(
                        absint($forceFreshNotificationId)
                    );
                }

                // On singular pages only, retrigger rotates within the contextual taxonomy pool when level > 0.
                if (
                    !empty($context['force_fresh_content'])
                    && PageContextHelper::shouldUseContextPoolForRetrigger($context, $retriggerContentSourceSettings)
                    && !PageContextHelper::isArchiveContext($context)
                ) {
                    $context['smart_targeting_forced_phase'] = 'context';
                }

                // Archive retriggers must stay inside the contextual taxonomy pool and bypass stale entity caches.
                if (
                    !empty($context['force_fresh_content'])
                    && PageContextHelper::isArchiveContext($context)
                ) {
                    $context['smart_targeting_forced_phase'] = 'context';
                }

                // Rotation seed so API retrigger picks a different pool member than the first paint.
                $cacheBust = $request->get_param('cache_bust');
                if ($cacheBust !== null && $cacheBust !== '') {
                    $context['retrigger_rotation'] = sanitize_text_field((string) $cacheBust);
                }
            }

            // Get eligible notifications
            $notifications = $this->eligibilityService->getEligibleNotifications($context);

            // Prepare response data
            $responseData = [
                'success' => true,
                'notifications' => $notifications,
                'count' => count($notifications),
                'timestamp' => current_time('timestamp'),
                'context' => $context,
            ];

            // Apply response filter
            $responseData = apply_filters(
                FilterHooks::ONPAGE_ELIGIBILITY_DATA,
                $responseData,
                $context
            );

            return new WP_REST_Response($responseData, 200);

        } catch (\Exception $e) {
            return notifal_wp_error(
                'notifal_eligibility_error',
                __('Error retrieving eligible notifications. Please try again.', 'notifal'),
                ['status' => 500]
            );
        }
    }

    /**
     * Get a single notification for admin preview (same shape as eligible response).
     * Admin-only; bypasses display rules, frequency, and schedule.
     *
     * @param WP_REST_Request $request Request with id (notification ID)
     * @return WP_REST_Response|WP_Error
     * @since 2.0.0
     */
    public function getPreviewNotification(WP_REST_Request $request)
    {
        $id = absint($request->get_param('id'));
        if ($id <= 0) {
            return notifal_wp_error(
                'notifal_preview_invalid_id',
                __('Invalid notification ID.', 'notifal'),
                ['status' => 400]
            );
        }

        if (!$this->canAccessPreview($request, $id)) {
            return new \WP_REST_Response(
                ['code' => 'notifal_preview_forbidden', 'message' => __('Sorry, you are not allowed to view this preview.', 'notifal')],
                403
            );
        }

        $post = NotificationQuery::get($id);
        if (!$post || $post->post_type !== 'notifal_onpage_notif') {
            return notifal_wp_error(
                'notifal_preview_not_found',
                __('Notification not found.', 'notifal'),
                ['status' => 404]
            );
        }

        $context = $this->buildContextFromRequest($request);
        $context['is_preview'] = true;

        $prepared = $this->dataPreparer->prepareForFrontend($post, $context);
        if ($prepared === null) {
            return notifal_wp_error(
                'notifal_preview_render_error',
                __('Unable to prepare notification for preview.', 'notifal'),
                ['status' => 500]
            );
        }

        return new WP_REST_Response([
            'success' => true,
            'notifications' => [$prepared],
            'count' => 1,
            'is_preview' => true,
            'timestamp' => current_time('timestamp'),
            'context' => $context,
        ], 200);
    }

    /**
     * Track a notification event.
     *
     * Records user interactions with notifications for analytics and
     * behavioral targeting purposes.
     *
     * @param WP_REST_Request $request Request object containing tracking data
     * @return WP_REST_Response|WP_Error Response object with tracking result or error
     * @since 2.0.0
     */
    public function trackEvent(WP_REST_Request $request)
    {
        try {
            // Do not track when request is from preview (admin preview mode)
            if ($this->isPreviewRequest($request)) {
                return new WP_REST_Response([
                    'success' => true,
                    'message' => __('Preview mode: analytics not recorded.', 'notifal'),
                ], 200);
            }

            // Verify nonce for security
            $nonce = $request->get_header('X-WP-Nonce') ?: $request->get_param('nonce');
            if (!wp_verify_nonce($nonce, 'wp_rest')) {
                return notifal_wp_error(
                    'rest_forbidden',
                    __('Invalid nonce. Please refresh the page and try again.', 'notifal'),
                    ['status' => 403]
                );
            }

            // Get and sanitize tracking data from request
            $trackingData = Helper::sanitizeArray($request->get_params());

            // Track the event
            $result = $this->trackingService->trackEvent($trackingData);

            if ($result['success']) {
                return new WP_REST_Response($result, 200);
            } else {
                return notifal_wp_error(
                    'notifal_tracking_error',
                    $result['message'] ?: __('Event tracking failed.', 'notifal'),
                    ['status' => 400]
                );
            }

        } catch (\Exception $e) {
            return notifal_wp_error(
                'notifal_tracking_error',
                __('Error tracking event. Please try again.', 'notifal'),
                ['status' => 500]
            );
        }
    }

    /**
     * Get user preferences for notifications.
     *
     * Retrieves notification display preferences for the current user,
     * falling back to defaults for anonymous users.
     *
     * @param WP_REST_Request $request Request object (may be unused but required by interface)
     * @return WP_REST_Response|WP_Error Response object with preferences data or error
     * @since 2.0.0
     */
    public function getUserPreferences(WP_REST_Request $request)
    {
        try {
            // Verify nonce for security
            $nonce = $request->get_header('X-WP-Nonce') ?: $request->get_param('nonce');
            if (!wp_verify_nonce($nonce, 'wp_rest')) {
                return notifal_wp_error(
                    'rest_forbidden',
                    __('Invalid nonce. Please refresh the page and try again.', 'notifal'),
                    ['status' => 403]
                );
            }

            $userId = get_current_user_id();

            if ($userId <= 0) {
                // Return default preferences for anonymous users
                $preferences = $this->getDefaultPreferences();
            } else {
                // Get user-specific preferences
                $preferences = $this->getUserSpecificPreferences($userId);
            }

            $responseData = [
                'success' => true,
                'preferences' => $preferences,
                'user_id' => $userId,
                'is_logged_in' => $userId > 0,
            ];

            return new WP_REST_Response($responseData, 200);

        } catch (\Exception $e) {
            return notifal_wp_error(
                'notifal_preferences_error',
                __('Error retrieving user preferences. Please try again.', 'notifal'),
                ['status' => 500]
            );
        }
    }

    /**
     * Update user preferences for notifications.
     *
     * Saves notification display preferences for the current user,
     * using session storage for anonymous users and user meta for logged-in users.
     *
     * @param WP_REST_Request $request Request object containing preferences data
     * @return WP_REST_Response|WP_Error Response object with update result or error
     * @since 2.0.0
     */
    public function updateUserPreferences(WP_REST_Request $request)
    {
        try {
            // Verify nonce for security
            $nonce = $request->get_header('X-WP-Nonce') ?: $request->get_param('nonce');
            if (!wp_verify_nonce($nonce, 'wp_rest')) {
                return notifal_wp_error(
                    'rest_forbidden',
                    __('Invalid nonce. Please refresh the page and try again.', 'notifal'),
                    ['status' => 403]
                );
            }

            $userId = get_current_user_id();
            $rawPreferences = $request->get_param('preferences');

            // Validate that preferences is an array
            if (!is_array($rawPreferences)) {
                return notifal_wp_error(
                    'invalid_preferences',
                    __('Invalid preferences format. Please provide a valid preferences object.', 'notifal'),
                    ['status' => 400]
                );
            }

            if ($userId <= 0) {
                // Store preferences in session for anonymous users
                $result = $this->updateAnonymousUserPreferences(Helper::sanitizeArray($rawPreferences));
            } else {
                // Update user-specific preferences
                $result = $this->updateUserSpecificPreferences($userId, Helper::sanitizeArray($rawPreferences));
            }

            if ($result['success']) {
                return new WP_REST_Response($result, 200);
            } else {
                return notifal_wp_error(
                    'notifal_preferences_error',
                    $result['message'] ?: __('Failed to update preferences.', 'notifal'),
                    ['status' => 400]
                );
            }

        } catch (\Exception $e) {
            return notifal_wp_error(
                'notifal_preferences_error',
                __('Error updating user preferences. Please try again.', 'notifal'),
                ['status' => 500]
            );
        }
    }

    /**
     * Check if we should serve notifications in the current context.
     *
     * Prevents serving notifications in editor modes or preview contexts
     * to avoid conflicts with page builders and template editing.
     *
     * @param WP_REST_Request $request Request object containing context information
     * @return bool True if notifications should be served, false otherwise
     * @since 2.0.0
     */
    private function shouldServeNotifications(WP_REST_Request $request): bool
    {
        // Check if we're in Elementor editor mode
        if ($this->isElementorEditorMode($request)) {
            return false;
        }

        // Check if we're in template preview mode
        if ($this->isTemplatePreviewMode($request)) {
            return false;
        }

        // Check if this is a preview-related REST request
        if ($this->isPreviewRelatedRestRequest($request)) {
            return false;
        }

        return true;
    }

    /**
     * Check if the request is from Elementor editor mode.
     *
     * Detects when the user is editing content in Elementor to prevent
     * notifications from interfering with the editing experience.
     *
     * @param WP_REST_Request $request Request object containing headers and parameters
     * @return bool True if in Elementor editor mode, false otherwise
     * @since 2.0.0
     */
    private function isElementorEditorMode(WP_REST_Request $request): bool
    {
        // Check request headers and parameters for Elementor editor indicators
        $referer = $request->get_header('Referer') ?: '';
        
        // Check for Elementor editor URLs in referer
        if (strpos($referer, 'action=elementor') !== false) {
            return true;
        }

        if (strpos($referer, 'elementor-preview') !== false) {
            return true;
        }

        // Check for Elementor-specific parameters
        $context = $request->get_param('context');
        if (is_array($context) && isset($context['elementor_editor'])) {
            return (bool) $context['elementor_editor'];
        }

        // Check if URL contains Elementor indicators
        $url = $request->get_param('url');
        if (!empty($url)) {
            if (strpos($url, 'action=elementor') !== false) {
                return true;
            }
            if (strpos($url, 'elementor-preview') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the request is from template preview mode.
     *
     * Detects when templates are being previewed to prevent notifications
     * from appearing during template development and testing.
     *
     * @param WP_REST_Request $request Request object containing headers and parameters
     * @return bool True if in template preview mode, false otherwise
     * @since 2.0.0
     */
    private function isTemplatePreviewMode(WP_REST_Request $request): bool
    {
        // Check request headers and parameters for template preview indicators
        $referer = $request->get_header('Referer') ?: '';
        
        // Check for template preview URLs in referer
        if (strpos($referer, 'notifal_template_preview') !== false) {
            return true;
        }

        // Check for template preview-specific parameters
        $context = $request->get_param('context');
        if (is_array($context) && isset($context['template_preview'])) {
            return (bool) $context['template_preview'];
        }

        // Check if URL contains template preview indicators
        $url = $request->get_param('url');
        if (!empty($url)) {
            if (strpos($url, 'notifal_template_preview') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if this is a preview-related REST request.
     *
     * Identifies REST API calls that are part of preview or editing workflows
     * where notifications should not be served.
     *
     * @param WP_REST_Request $request Request object containing route information
     * @return bool True if preview-related, false otherwise
     * @since 2.0.0
     */
    private function isPreviewRelatedRestRequest(WP_REST_Request $request): bool
    {
        $route = $request->get_route();
        
        // Check for preview-related routes
        $preview_routes = [
            'elementor',
            'preview',
            'autosave',
            'revisions',
            'notifal_template'
        ];

        foreach ($preview_routes as $preview_route) {
            if (strpos($route, $preview_route) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the current request is allowed to access the preview endpoint.
     * Allows: (1) user with edit_posts, or (2) valid short-lived preview token.
     *
     * @param WP_REST_Request $request REST request
     * @param int             $id     Notification ID being previewed
     * @return bool True if access is allowed
     * @since 2.0.0
     */
    private function canAccessPreview(WP_REST_Request $request, int $id): bool
    {
        if (current_user_can('edit_posts')) {
            return true;
        }
        $token = $request->get_param('_preview_token');
        if (!is_string($token) || $token === '') {
            return false;
        }
        $token = sanitize_text_field($token);
        $stored_id = get_transient('notifal_onpage_preview_token_' . $token);
        return $stored_id !== false && (int) $stored_id === $id;
    }

    /**
     * Check if the request is from the admin preview context (no analytics).
     *
     * @param WP_REST_Request $request REST request
     * @return bool True if request is a preview request
     * @since 2.0.0
     */
    private function isPreviewRequest(WP_REST_Request $request): bool
    {
        if ($request->get_header('X-Notifal-Preview') === '1') {
            return true;
        }
        $referer = $request->get_header('Referer') ?: '';
        return strpos($referer, 'notifal_onpage_preview') !== false;
    }

    /**
     * Load stored content source settings for a notification post.
     *
     * @param int $notificationId Notification post ID.
     * @return array<string, mixed>
     * @since 2.3.7
     */
    private function loadContentSourceSettingsForNotification(int $notificationId): array
    {
        // Bail when the notification ID is invalid.
        if ($notificationId <= 0) {
            return [];
        }

        // Read persisted content source settings from notification meta.
        $settings = get_post_meta($notificationId, '_notifal_content_source_settings', true);

        return is_array($settings) ? $settings : [];
    }

    /**
     * Build context from request parameters.
     *
     * Constructs a context array containing page, user, and device information
     * needed for notification eligibility checking and display rules evaluation.
     *
     * @param WP_REST_Request $request Request object containing context parameters
     * @return array Context data with sanitized and validated parameters
     * @since 2.0.0
     */
    private function buildContextFromRequest(WP_REST_Request $request): array
    {
        $context = [
            'page_id' => $request->get_param('page_id'),
            'url' => $request->get_param('url'),
            'user_id' => $request->get_param('user_id'),
            'device_type' => $request->get_param('device_type') ?: 'desktop',
        ];

        // If no page_id provided, try to get current page
        if (empty($context['page_id'])) {
            $context['page_id'] = get_queried_object_id();
        }

        // If no URL provided, use current URL (never the REST endpoint path).
        if (empty($context['url'])) {
            $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
            if ($requestUri !== '' && strpos($requestUri, '/wp-json/') === false) {
                $context['url'] = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                    . '://' . $_SERVER['HTTP_HOST'] . $requestUri;
            }
        }

        // Parse URL query parameters for UTM/query-parameter display rules
        $context['query_params'] = $this->parseQueryParamsForContext($context['url']);

        // If no user_id provided, use current user.
        if (empty($context['user_id'])) {
            $context['user_id'] = get_current_user_id();
        }

        // @since 2.3.10 Resolve logged-in visitors from auth cookie on public REST requests.
        if ((int) $context['user_id'] === 0) {
            $cookieUserId = $this->resolveUserIdFromAuthCookie();

            if ($cookieUserId > 0) {
                $context['user_id'] = $cookieUserId;
                wp_set_current_user($cookieUserId);
            }
        }

        // Detect device type if not provided
        if ($context['device_type'] === 'desktop') {
            $context['device_type'] = $this->detectDeviceType();
        }

        // Parse additional context parameters for display rules
        if (!empty($request->get_param('post_type'))) {
            $context['post_type'] = Helper::sanitizeInput($request->get_param('post_type'), 'text');
        }

        if (!empty($request->get_param('archive_taxonomy'))) {
            $context['archive_taxonomy'] = sanitize_key((string) $request->get_param('archive_taxonomy'));
        }

        // Parse categories for category-based rules
        if (!empty($request->get_param('categories'))) {
            $categoriesString = Helper::sanitizeInput($request->get_param('categories'), 'text');
            $context['categories'] = array_map('absint', explode(',', $categoriesString));
        }

        if (!empty($request->get_param('product_categories'))) {
            $productCategoriesString = Helper::sanitizeInput($request->get_param('product_categories'), 'text');
            $context['product_categories'] = array_map('absint', explode(',', $productCategoriesString));
        }

        // Parse user roles for user-based rules
        if (!empty($request->get_param('user_roles'))) {
            $userRolesString = Helper::sanitizeInput($request->get_param('user_roles'), 'text');
            $context['user_roles'] = array_map(function($role) {
                return Helper::sanitizeInput($role, 'text');
            }, explode(',', $userRolesString));
        }

        // Add additional context
        $context['timestamp'] = current_time('timestamp');
        $context['timezone'] = wp_timezone_string();
        $context['locale'] = get_locale();
        $context['is_admin'] = current_user_can('manage_options');
        $context['is_logged_in'] = ((int) ($context['user_id'] ?? 0)) > 0 || is_user_logged_in();

        if ($context['is_logged_in'] && empty($context['user_roles'])) {
            $context['user_roles'] = wp_get_current_user()->roles;
        }

        // @since 2.3.5 Attach WooCommerce cart snapshot for cart display rules.
        if (PluginDetector::isWooCommerceActive() && CartDisplayRulesUsageChecker::anyActiveNotificationUsesCartRules()) {
            $context = WooCommerceCartContextBuilder::mergeIntoContext($context);
        }

        // @since 2.3.7 Merge browser session seen sources so pool selection skips client-only retriggers.
        $clientSeenSources = $this->parseClientSeenSourcesFromRequest($request);
        if (!empty($clientSeenSources)) {
            $context['client_seen_sources'] = $clientSeenSources;
        }

        $context = $this->applySmartTargetingViewFlagsFromRequest($context, $request);

        return (new PageContextEnricher())->enrich($context);
    }

    /**
     * Parse a boolean smart targeting view flag from the eligibility API request.
     *
     * @param WP_REST_Request $request REST request.
     * @param string          $param   Query parameter name.
     * @return bool
     * @since 2.3.7
     */
    private function parseContextBooleanFlag(WP_REST_Request $request, string $param): bool
    {
        $value = $request->get_param($param);

        return $value === true || $value === 1 || $value === '1';
    }

    /**
     * Apply smart targeting view flags supplied by the frontend visitor context.
     *
     * @param array<string, mixed> $context Request context.
     * @param WP_REST_Request      $request REST request.
     * @return array<string, mixed>
     * @since 2.3.7
     */
    private function applySmartTargetingViewFlagsFromRequest(array $context, WP_REST_Request $request): array
    {
        foreach ([
            'is_front_page',
            'is_posts_home',
            'is_shop_page',
            'is_cart_page',
            'is_checkout_page',
            'is_account_page',
            'is_singular_query',
        ] as $flag) {
            if ($request->get_param($flag) !== null) {
                $context[$flag] = $this->parseContextBooleanFlag($request, $flag);
            }
        }

        return $context;
    }

    /**
     * Parse client-side shown source map from the eligibility request.
     *
     * @param WP_REST_Request $request REST request.
     * @return array<int, array{entityIds: array<int, int>}> Map keyed by notification ID.
     * @since 2.3.7
     */
    private function parseClientSeenSourcesFromRequest(WP_REST_Request $request): array
    {
        // Read raw JSON payload from the query string.
        $raw = $request->get_param('client_seen_sources');
        if ($raw === null || $raw === '') {
            return [];
        }

        // Accept pre-decoded arrays when WordPress passes structured input.
        if (is_array($raw)) {
            return $this->sanitizeClientSeenSourcesMap($raw);
        }

        // Decode JSON string sent by the frontend sessionStorage sync.
        $decoded = json_decode(wp_unslash((string) $raw), true);
        if (!is_array($decoded)) {
            return [];
        }

        return $this->sanitizeClientSeenSourcesMap($decoded);
    }

    /**
     * Sanitize client seen source map entries.
     *
     * @param array $raw Raw decoded map.
     * @return array<int, array{entityIds: array<int, int>}>
     * @since 2.3.7
     */
    private function sanitizeClientSeenSourcesMap(array $raw): array
    {
        $sanitized = [];

        foreach ($raw as $notificationId => $entry) {
            // Normalize notification ID keys from string or integer input.
            $id = absint($notificationId);
            if ($id <= 0 || !is_array($entry)) {
                continue;
            }

            $entityIds = $entry['entityIds'] ?? [];
            if (!is_array($entityIds)) {
                continue;
            }

            // Keep only positive integer entity IDs.
            $entityIds = array_values(array_unique(array_filter(array_map('intval', $entityIds))));
            if (empty($entityIds)) {
                continue;
            }

            $sanitized[$id] = ['entityIds' => $entityIds];
        }

        return $sanitized;
    }

    /**
     * Parse query parameters from URL or current request for display rule evaluation.
     *
     * @param string $url Full URL or path with query string
     * @return array<string, string> Map of parameter name to value
     * @since 2.0.0
     */
    private function parseQueryParamsForContext(string $url): array
    {
        if ($url !== '' && strpos($url, '?') !== false) {
            $query = wp_parse_url($url, PHP_URL_QUERY);
            if (is_string($query) && $query !== '') {
                wp_parse_str($query, $parsed);
                if (is_array($parsed)) {
                    return array_map(function ($v) {
                        return is_array($v) ? (string) reset($v) : (string) $v;
                    }, $parsed);
                }
            }
        }

        if (!empty($_GET) && is_array($_GET)) {
            return array_map(function ($v) {
                return is_array($v) ? (string) reset($v) : (string) $v;
            }, $_GET);
        }

        return [];
    }

    /**
     * Detect device type from user agent.
     *
     * Determines whether the user is accessing the site from a desktop,
     * tablet, or mobile device based on the User-Agent header.
     *
     * @return string Device type ('desktop', 'tablet', or 'mobile')
     * @since 2.0.0
     */
    private function detectDeviceType(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (empty($userAgent)) {
            return 'desktop';
        }

        // Check for mobile devices
        if (preg_match('/(android|webos|iphone|ipad|ipod|blackberry|windows phone)/i', $userAgent)) {
            // Check for tablets
            if (preg_match('/(ipad|android(?!.*mobile)|tablet)/i', $userAgent)) {
                return 'tablet';
            }
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * Get default preferences for anonymous users.
     *
     * Returns the standard notification preferences used for users
     * who are not logged in or haven't customized their settings.
     *
     * @return array Default preferences with standard notification settings
     * @since 2.0.0
     */
    private function getDefaultPreferences(): array
    {
        return [
            'enabled' => true,
            'frequency' => 'medium',
            'categories' => [],
            'display_type' => 'modal',
            'sound_enabled' => false,
            'animation_enabled' => true,
        ];
    }

    /**
     * Get user-specific preferences.
     *
     * Retrieves notification preferences stored in user meta,
     * falling back to defaults if no custom preferences exist.
     *
     * @param int $userId WordPress user ID
     * @return array User preferences merged with defaults
     * @since 2.0.0
     */
    private function getUserSpecificPreferences(int $userId): array
    {
        $preferences = get_user_meta($userId, '_notifal_onpage_preferences', true);
        
        if (empty($preferences)) {
            return $this->getDefaultPreferences();
        }

        // Merge with defaults to ensure all keys exist
        return array_merge($this->getDefaultPreferences(), $preferences);
    }

    /**
     * Update anonymous user preferences.
     *
     * Stores notification preferences in the current session for
     * non-logged-in users who want to customize their experience.
     *
     * @param array $preferences Sanitized preferences array
     * @return array Update result with success status and message
     * @since 2.0.0
     */
    private function updateAnonymousUserPreferences(array $preferences): array
    {
        try {
            // Store in session
            if (!session_id()) {
                session_start();
            }
            
            $_SESSION['notifal_onpage_preferences'] = $preferences;
            
            return [
                'success' => true,
                'message' => __('Preferences updated successfully', 'notifal'),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('Error updating preferences', 'notifal'),
            ];
        }
    }

    /**
     * Update user-specific preferences.
     *
     * Saves notification preferences to user meta for logged-in users,
     * with validation to ensure data integrity.
     *
     * @param int $userId WordPress user ID
     * @param array $preferences Sanitized preferences array
     * @return array Update result with success status and message
     * @since 2.0.0
     */
    private function updateUserSpecificPreferences(int $userId, array $preferences): array
    {
        try {
            // Validate preferences
            $validatedPreferences = $this->validatePreferences($preferences);
            
            if (!$validatedPreferences['valid']) {
                return [
                    'success' => false,
                    'message' => $validatedPreferences['message'],
                ];
            }

            // Update user meta
            $result = update_user_meta($userId, '_notifal_onpage_preferences', $validatedPreferences['data']);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => __('Preferences updated successfully', 'notifal'),
                ];
            } else {
                return [
                    'success' => false,
                    'message' => __('No changes made to preferences', 'notifal'),
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('Error updating preferences', 'notifal'),
            ];
        }
    }

    /**
     * Validate user preferences.
     *
     * Ensures preference values are within acceptable ranges and formats,
     * sanitizing input and providing defaults for invalid values.
     *
     * @param array $preferences Raw preferences array from user input
     * @return array Validation result with 'valid', 'data', and optional 'message' keys
     * @since 2.0.0
     */
    private function validatePreferences(array $preferences): array
    {
        $validated = [];
        
        // Validate enabled flag
        $validated['enabled'] = isset($preferences['enabled']) ? (bool) $preferences['enabled'] : true;
        
        // Validate frequency
        $validFrequencies = ['low', 'medium', 'high'];
        $validated['frequency'] = in_array($preferences['frequency'] ?? 'medium', $validFrequencies, true) 
            ? $preferences['frequency'] 
            : 'medium';
        
        // Validate categories
        $validated['categories'] = is_array($preferences['categories'] ?? [])
            ? array_map(function($category) {
                return Helper::sanitizeInput($category, 'text');
            }, $preferences['categories'])
            : [];
        
        // Validate display type
        $validDisplayTypes = ['modal', 'toast', 'bar', 'inline'];
        $validated['display_type'] = in_array($preferences['display_type'] ?? 'modal', $validDisplayTypes, true) 
            ? $preferences['display_type'] 
            : 'modal';
        
        // Validate sound enabled
        $validated['sound_enabled'] = isset($preferences['sound_enabled']) ? (bool) $preferences['sound_enabled'] : false;
        
        // Validate animation enabled
        $validated['animation_enabled'] = isset($preferences['animation_enabled']) ? (bool) $preferences['animation_enabled'] : true;

        return [
            'valid' => true,
            'data' => $validated,
        ];
    }

    /**
     * Resolve the current visitor user ID from the WordPress logged-in auth cookie.
     *
     * Public eligibility REST requests intentionally omit nonce auth for cache safety,
     * so cookie validation is used when get_current_user_id() is still zero.
     *
     * @return int Logged-in user ID or 0 when no valid cookie exists.
     * @since 2.3.10
     */
    private function resolveUserIdFromAuthCookie(): int
    {
        if (!function_exists('wp_validate_auth_cookie')) {
            return 0;
        }

        $validatedUserId = wp_validate_auth_cookie('', 'logged_in');

        if ($validatedUserId === false) {
            return 0;
        }

        return absint($validatedUserId);
    }

    /**
     * Return the current WooCommerce cart snapshot for client-side rule evaluation.
     *
     * @param WP_REST_Request $request REST request (no parameters required).
     * @return WP_REST_Response Cart snapshot JSON response.
     * @since 2.3.5
     */
    public function getCartContext(WP_REST_Request $request): WP_REST_Response
    {
        // Respond with empty snapshot when WooCommerce is unavailable.
        if (!PluginDetector::isWooCommerceActive()) {
            return new WP_REST_Response([
                'success' => true,
                'cart'    => WooCommerceCartContextBuilder::emptySnapshot(),
            ], 200);
        }

        return new WP_REST_Response([
            'success'   => true,
            'cart'      => WooCommerceCartContextBuilder::build(),
            'timestamp' => current_time('timestamp'),
        ], 200);
    }
}
