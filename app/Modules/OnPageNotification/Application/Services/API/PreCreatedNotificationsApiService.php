<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\API;

use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Infrastructure\WordPress\HttpClientTrait;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Shared\Utils\Helper;

/**
 * Pre-created Notifications API Service
 *
 * Handles communication with notifal.com API to fetch pre-created notification templates.
 * Provides methods to query, filter, and retrieve notification data for the archive view.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Application\Services
 * @uses HttpClientTrait For common HTTP functionality
 */
class PreCreatedNotificationsApiService
{
    use HttpClientTrait;
    /**
     * API base URL for notifal.com
     *
     * @since 2.0.0
     * @var string
     */
    private const API_BASE_URL = Urls::API_BASE_MARKETPLACE;

    /**
     * API namespace
     *
     * @since 2.0.0
     * @var string
     */
    private const API_NAMESPACE = 'notifal-mp/v1';

    /**
     * HTTP timeout for API requests (in seconds)
     *
     * @since 2.0.0
     * @var int
     */
    private const API_TIMEOUT = 30;

    /**
     * User agent string for API requests
     *
     * @since 2.0.0
     * @var string
     */
    private const USER_AGENT = 'Notifal-Client/' . NOTIFAL_VERSION;

    /**
     * Cache expiration time for notifications in seconds (1 hour)
     *
     * @since 2.0.0
     * @var int
     */
    private const CACHE_EXPIRATION_NOTIFICATIONS = 3600; // 1 hour

    /**
     * Cache expiration time for taxonomies in seconds (1 hour)
     *
     * @since 2.0.0
     * @var int
     */
    private const CACHE_EXPIRATION_TAXONOMIES = 3600; // 1 hour

    /**
     * Rate limit: Maximum requests per minute per user
     *
     * @since 2.0.0
     * @var int
     */
    private const RATE_LIMIT_REQUESTS_PER_MINUTE = 10;

    /**
     * Rate limit: Time window in seconds
     *
     * @since 2.0.0
     * @var int
     */
    private const RATE_LIMIT_WINDOW = 60; // 1 minute

    /**
     * Get notifications from the API with optional filtering.
     *
     * @since 2.0.0
     * @param array<string, mixed> $args Query arguments for filtering and pagination
     * @return array<string, mixed> API response data or error array
     */
    public function getNotifications(array $args = []): array
    {
        // Build query parameters
        $queryParams = $this->buildQueryParameters($args);

        $cacheKey = $this->generateCacheKey('notifications', $queryParams);
        $cached = $this->getCachedResponse($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $rateLimitCheck = $this->checkRateLimit('notifications');
        if (!$rateLimitCheck['allowed']) {
            return [
                'success' => false,
                'error' => __('Too many requests. Please wait a moment and try again.', 'notifal'),
                'rate_limit' => true,
            ];
        }

        // Make API request
        $response = $this->makeApiRequest('notifications', $queryParams);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        if (!$this->validateHttpStatus($response, 200, 'notifications list')) {
            return [
                'success' => false,
                'error' => sprintf(
                    /* translators: %d: HTTP status code */
                    __('API request failed with status %d', 'notifal'),
                    wp_remote_retrieve_response_code($response)
                ),
            ];
        }

        $data = $this->parseJsonResponse($response, 'notifications list');

        if ($data === null) {
            return [
                'success' => false,
                'error' => __('Invalid JSON response from API', 'notifal'),
            ];
        }

        // Cache successful response with 1 hour expiration
        $this->setCachedResponse($cacheKey, $data, self::CACHE_EXPIRATION_NOTIFICATIONS);

        /**
         * Fires after successfully fetching notifications from API.
         *
         * @since 2.0.0
         * @param array $data API response data
         * @param array $args Original query arguments
         */
        do_action(ActionHooks::PRE_CREATED_NOTIFICATIONS_API_FETCHED, $data, $args);

        return $data;
    }

    /**
     * Get single notification details from the API.
     *
     * @since 2.0.0
     * @param int $notificationId Notification ID
     * @return array<string, mixed> API response data or error array
     */
    public function getSingleNotification(int $notificationId): array
    {
        $cacheKey = $this->generateCacheKey('notification_' . $notificationId, []);
        $cached = $this->getCachedResponse($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $rateLimitCheck = $this->checkRateLimit('single_notification');
        if (!$rateLimitCheck['allowed']) {
            return [
                'success' => false,
                'error' => __('Too many requests. Please wait a moment and try again.', 'notifal'),
                'rate_limit' => true,
            ];
        }

        // Make API request
        $response = $this->makeApiRequest('notifications/' . $notificationId, []);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        if (!$this->validateHttpStatus($response, 200, 'single notification ' . $notificationId)) {
            return [
                'success' => false,
                'error' => sprintf(
                    /* translators: %d: HTTP status code */
                    __('API request failed with status %d', 'notifal'),
                    wp_remote_retrieve_response_code($response)
                ),
            ];
        }

        $data = $this->parseJsonResponse($response, 'single notification ' . $notificationId);

        if ($data === null) {
            return [
                'success' => false,
                'error' => __('Invalid JSON response from API', 'notifal'),
            ];
        }

        // Do not cache error responses so that when the server recovers, the next request gets fresh data
        $isApiError = isset($data['success']) && $data['success'] === false;
        if (!$isApiError) {
            $this->setCachedResponse($cacheKey, $data, self::CACHE_EXPIRATION_NOTIFICATIONS);
        }

        /**
         * Fires after successfully fetching a single notification from API.
         *
         * @since 2.0.0
         * @param array $data API response data
         * @param int $notificationId Notification ID
         */
        do_action(ActionHooks::PRE_CREATED_NOTIFICATIONS_API_SINGLE_FETCHED, $data, $notificationId);

        return $data;
    }

    /**
     * Get taxonomy terms for filtering options from notifal.com API.
     *
     * @since 2.0.0
     * @return array<string, array> Taxonomy terms organized by taxonomy
     */
    public function getTaxonomies(): array
    {
        $cacheKey = $this->generateCacheKey('taxonomies', []);
        $cached = $this->getCachedResponse($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        // Fetch taxonomies from notifal.com API
        $response = $this->makeApiRequest('taxonomies', []);

        if (is_wp_error($response) || !$this->validateHttpStatus($response, 200, 'taxonomies')) {
            // Return empty array on error
            return [
                'use_cases' => [],
                'events' => [],
                'industries' => [],
                'layouts' => [],
                'used_plugins' => [],
            ];
        }

        $data = $this->parseJsonResponse($response, 'taxonomies');

        if ($data === null) {
            // Return empty array on error
            return [
                'use_cases' => [],
                'events' => [],
                'industries' => [],
                'layouts' => [],
                'used_plugins' => [],
            ];
        }


        // Extract taxonomies from API response
        $result = [
            'use_cases' => $data['data']['use_cases'] ?? [],
            'events' => $data['data']['events'] ?? [],
            'industries' => $data['data']['industries'] ?? [],
            'layouts' => $data['data']['layouts'] ?? [],
            'used_plugins' => $data['data']['plugins'] ?? [], // API returns 'plugins' key
        ];


        // Cache successful response with 1 hour expiration
        $this->setCachedResponse($cacheKey, $result, self::CACHE_EXPIRATION_TAXONOMIES);

        return $result;
    }

    /**
     * Build query parameters for API request.
     *
     * @since 2.0.0
     * @param array<string, mixed> $args Raw arguments
     * @return array<string, mixed> Sanitized query parameters
     */
    private function buildQueryParameters(array $args): array
    {
        $defaults = [
            'page' => 1,
            'per_page' => 12,
            'orderby' => 'recent',
            'search' => '',
            'use_cases' => [],
            'events' => [],
            'industries' => [],
            'layouts' => [],
            'used_plugins' => [],
            'is_pro' => '',
        ];

        $args = array_merge($defaults, $args);

        // Sanitize and validate parameters
        $params = [
            'page' => max(1, intval($args['page'])),
            'per_page' => max(1, min(50, intval($args['per_page']))), // Limit to 50 per page
            'orderby' => Helper::sanitizeInput($args['orderby'], 'text'),
        ];

        // Add search if provided
        if (!empty($args['search'])) {
            $params['search'] = Helper::sanitizeInput($args['search'], 'text');
        }

        // Add taxonomy filters
        $taxonomies = ['use_cases', 'events', 'industries', 'layouts', 'used_plugins'];
        foreach ($taxonomies as $taxonomy) {
            if (!empty($args[$taxonomy]) && is_array($args[$taxonomy])) {
                $params[$taxonomy] = array_map(function($value) {
                    return Helper::sanitizeInput($value, 'text');
                }, $args[$taxonomy]);
            }
        }

        // Add license filter
        if (isset($args['is_pro']) && $args['is_pro'] !== '') {
            $params['is_pro'] = Helper::sanitizeInput($args['is_pro'], 'text');
        }

        /**
         * Filter the API query parameters before making the request.
         *
         * @since 2.0.0
         * @param array $params Sanitized query parameters
         * @param array $args Original arguments
         */
        return apply_filters(FilterHooks::PRE_CREATED_NOTIFICATIONS_API_PARAMS, $params, $args);
    }

    /**
     * Make HTTP request to the API.
     *
     * @since 2.0.0
     * @param string $endpoint API endpoint (without leading slash)
     * @param array<string, mixed> $params Query parameters
     * @param string $method HTTP method (GET or POST)
     * @return array|\WP_Error Response array or WP_Error
     */
    private function makeApiRequest(string $endpoint, array $params = [], string $method = 'GET')
    {
        $url = self::API_BASE_URL . '/' . ltrim($endpoint, '/');

        // Build query string manually to handle arrays properly
        if (!empty($params)) {
            $queryParts = [];
            foreach ($params as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $item) {
                        $queryParts[] = urlencode($key) . '[]=' . urlencode($item);
                    }
                } else {
                    $queryParts[] = urlencode($key) . '=' . urlencode($value);
                }
            }
            $url .= '?' . implode('&', $queryParts);
        }

        $args = [
            'timeout' => self::API_TIMEOUT,
            'user-agent' => self::USER_AGENT,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ];

        /**
         * Filter the HTTP request arguments before making the API call.
         *
         * @since 2.0.0
         * @param array $args HTTP request arguments
         * @param string $url Full API URL
         * @param array $params Original query parameters
         */
        $args = apply_filters(FilterHooks::PRE_CREATED_NOTIFICATIONS_API_REQUEST_ARGS, $args, $url, $params);

        if ($method === 'POST') {
            return wp_remote_post($url, $args);
        }

        return wp_remote_get($url, $args);
    }

    /**
     * Generate cache key for API responses.
     *
     * @since 2.0.0
     * @param string $endpoint API endpoint
     * @param array<string, mixed> $params Query parameters
     * @return string Cache key
     */
    private function generateCacheKey(string $endpoint, array $params): string
    {
        // Sort parameters for consistent cache keys
        ksort($params);
        $paramsHash = md5(serialize($params));

        return 'notifal_pre_created_' . $endpoint . '_' . $paramsHash;
    }

    /**
     * Get cached API response.
     *
     * @since 2.0.0
     * @param string $cacheKey Cache key
     * @return mixed Cached data or false if not found
     */
    private function getCachedResponse(string $cacheKey)
    {
        return get_transient($cacheKey);
    }

    /**
     * Cache API response.
     *
     * @since 2.0.0
     * @param string $cacheKey Cache key
     * @param mixed $data Data to cache
     * @param int $expiration Expiration time in seconds
     * @return bool Success status
     */
    private function setCachedResponse(string $cacheKey, $data, int $expiration = 3600): bool
    {
        return set_transient($cacheKey, $data, $expiration);
    }

    /**
     * Clear all cached API responses.
     *
     * @since 2.0.0
     * @return void
     */
    public function clearCache(): void
    {
        global $wpdb;

        $pattern = 'notifal_pre_created_%';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . $pattern
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_' . $pattern
            )
        );

        /**
         * Fires after clearing pre-created notifications API cache.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::PRE_CREATED_NOTIFICATIONS_API_CACHE_CLEARED);
    }

    /**
     * Check if the API service is available and healthy.
     *
     * @since 2.0.0
     * @return array<string, mixed> Health check result
     */
    public function healthCheck(): array
    {
        $startTime = microtime(true);

        $response = $this->makeApiRequest('notifications', ['page' => 1, 'per_page' => 1]);

        $responseTime = microtime(true) - $startTime;

        if (is_wp_error($response)) {
            return [
                'status' => 'error',
                'message' => $response->get_error_message(),
                'response_time' => round($responseTime, 3),
            ];
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        $isValidStatus = $this->validateHttpStatus($response, 200, 'health check');

        return [
            'status' => $isValidStatus ? 'healthy' : 'error',
            'response_code' => $responseCode,
            'response_time' => round($responseTime, 3),
        ];
    }

    /**
     * Get download URL for a notification file.
     *
     * @since 2.0.0
     * @param int $notificationId Notification ID
     * @param string $fileType File type: 'elementor' or 'block-editor'
     * @return array<string, mixed> API response with download URL or error
     */
    public function getDownloadUrl(int $notificationId, string $fileType): array
    {
        $validTypes = ['elementor', 'block-editor'];
        if (!in_array($fileType, $validTypes, true)) {
            return [
                'success' => false,
                'error' => __('Invalid file type. Must be elementor or block-editor.', 'notifal'),
            ];
        }

        // Make API request to get download URL
        $endpoint = 'downloads/' . $notificationId . '/' . $fileType;
        $response = $this->makeApiRequest($endpoint, [], 'POST');

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        if (!$this->validateHttpStatus($response, 200, 'download URL for ' . $notificationId . '/' . $fileType)) {
            return [
                'success' => false,
                'error' => sprintf(
                    /* translators: %d: HTTP status code */
                    __('API request failed with status %d', 'notifal'),
                    wp_remote_retrieve_response_code($response)
                ),
            ];
        }

        $data = $this->parseJsonResponse($response, 'download URL for ' . $notificationId . '/' . $fileType);

        if ($data === null) {
            return [
                'success' => false,
                'error' => __('Invalid JSON response from API', 'notifal'),
            ];
        }

        return $data;
    }

    /**
     * Download notification file from URL.
     *
     * Preserves file extension from Content-Disposition so ZIP files (with bundled
     * media) are detected correctly by the importer and use the bundle approach.
     *
     * @since 2.0.0
     * @param string $downloadUrl Download URL from API
     * @return array<string, mixed> Downloaded file path or error
     */
    public function downloadFile(string $downloadUrl): array
    {
        // Validate URL
        if (!filter_var($downloadUrl, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'error' => __('Invalid download URL.', 'notifal'),
            ];
        }

        // Download file
        $response = wp_remote_get($downloadUrl, [
            'timeout' => self::API_TIMEOUT,
            'user-agent' => self::USER_AGENT,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $responseCode = wp_remote_retrieve_response_code($response);

        if ($responseCode !== 200) {
            return [
                'success' => false,
                'error' => sprintf(
                    /* translators: %d: HTTP status code */
                    __('Download failed with status %d', 'notifal'),
                    $responseCode
                ),
            ];
        }

        // Get response body
        $body = wp_remote_retrieve_body($response);

        if (empty($body)) {
            return [
                'success' => false,
                'error' => __('Downloaded file is empty.', 'notifal'),
            ];
        }

        // Preserve extension from Content-Disposition so importer detects ZIP (bundle) vs JSON
        $extension = $this->getDownloadFileExtension($response);
        $tempFile = $this->createTempFileWithExtension($extension);

        if (!$tempFile) {
            return [
                'success' => false,
                'error' => __('Failed to create temporary file.', 'notifal'),
            ];
        }

        // Write content to temporary file
        $written = @file_put_contents($tempFile, $body);

        if ($written === false) {
            @unlink($tempFile);
            return [
                'success' => false,
                'error' => __('Failed to write downloaded file.', 'notifal'),
            ];
        }

        // Verify file exists and is readable
        if (!file_exists($tempFile) || !is_readable($tempFile)) {
            @unlink($tempFile);
            return [
                'success' => false,
                'error' => __('Downloaded file is not accessible.', 'notifal'),
            ];
        }

        return [
            'success' => true,
            'file_path' => $tempFile,
        ];
    }

    /**
     * Get file extension from download response headers.
     *
     * Parses Content-Disposition filename so ZIP (bundle) vs JSON is preserved
     * and the importer uses the correct path (bundled media vs URL download).
     *
     * @since 2.0.0
     * @param array|\WP_Error $response wp_remote_get response
     * @return string Extension without dot (e.g. 'zip' or 'json'), empty if unknown
     */
    private function getDownloadFileExtension($response): string
    {
        $headers = wp_remote_retrieve_headers($response);
        if (!is_array($headers) && !is_object($headers)) {
            return '';
        }

        $contentDisposition = isset($headers['content-disposition'])
            ? $headers['content-disposition']
            : (isset($headers['Content-Disposition']) ? $headers['Content-Disposition'] : '');

        if (empty($contentDisposition)) {
            return '';
        }

        // Match filename="..." or filename*=...
        if (preg_match('/filename\s*=\s*["\']?([^"\';]+)["\']?/i', $contentDisposition, $m)) {
            $filename = trim($m[1]);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if ($ext === 'zip' || $ext === 'json') {
                return $ext;
            }
        }

        return '';
    }

    /**
     * Create a temporary file with optional extension for import.
     *
     * Uses wp_tempnam then renames to add .zip or .json so the importer
     * detects format and uses bundled media when applicable.
     *
     * @since 2.0.0
     * @param string $extension Extension without dot (zip, json, or empty)
     * @return string|false Temp file path or false on failure
     */
    private function createTempFileWithExtension(string $extension)
    {
        $tempFile = wp_tempnam('notifal_import_');

        if (!$tempFile) {
            return false;
        }

        if ($extension !== '' && rename($tempFile, $tempFile . '.' . $extension)) {
            return $tempFile . '.' . $extension;
        }

        return $tempFile;
    }


    /**
     * Check rate limit for API requests.
     *
     * Implements intelligent rate limiting that allows reasonable browsing
     * but prevents rapid-fire requests and abusive patterns.
     *
     * @since 2.0.0
     * @param string $endpoint Endpoint identifier for tracking
     * @return array<string, mixed> Rate limit check result
     */
    private function checkRateLimit(string $endpoint): array
    {
        // Get user identifier (use session ID for logged-out users, user ID for logged-in)
        $userId = get_current_user_id();
        $userIdentifier = $userId > 0 ? 'user_' . $userId : 'session_' . $this->getUserSessionId();

        // Generate rate limit key
        $rateLimitKey = 'notifal_rate_limit_' . $userIdentifier . '_' . $endpoint;

        // Get current request count
        $requestData = get_transient($rateLimitKey);

        if ($requestData === false) {
            // First request in the window
            $requestData = [
                'count' => 1,
                'first_request' => time(),
            ];
            set_transient($rateLimitKey, $requestData, self::RATE_LIMIT_WINDOW);

            return [
                'allowed' => true,
                'remaining' => self::RATE_LIMIT_REQUESTS_PER_MINUTE - 1,
            ];
        }

        // Check if we're still within the time window
        $timeSinceFirst = time() - $requestData['first_request'];

        if ($timeSinceFirst > self::RATE_LIMIT_WINDOW) {
            // Window expired, reset counter
            $requestData = [
                'count' => 1,
                'first_request' => time(),
            ];
            set_transient($rateLimitKey, $requestData, self::RATE_LIMIT_WINDOW);

            return [
                'allowed' => true,
                'remaining' => self::RATE_LIMIT_REQUESTS_PER_MINUTE - 1,
            ];
        }

        // Check if limit exceeded
        if ($requestData['count'] >= self::RATE_LIMIT_REQUESTS_PER_MINUTE) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'retry_after' => self::RATE_LIMIT_WINDOW - $timeSinceFirst,
            ];
        }

        // Increment counter
        $requestData['count']++;
        set_transient($rateLimitKey, $requestData, self::RATE_LIMIT_WINDOW);

        return [
            'allowed' => true,
            'remaining' => self::RATE_LIMIT_REQUESTS_PER_MINUTE - $requestData['count'],
        ];
    }

    /**
     * Get or create user session ID for rate limiting.
     *
     * Uses WordPress session or creates a cookie-based identifier.
     *
     * @since 2.0.0
     * @return string Session identifier
     */
    private function getUserSessionId(): string
    {
        // Check if we have a session cookie
        $cookieName = 'notifal_session_id';

        if (isset($_COOKIE[$cookieName])) {
            return Helper::sanitizeInput($_COOKIE[$cookieName], 'text');
        }

        // Generate new session ID
        $sessionId = wp_generate_password(32, false, false);

        // Set cookie (expires in 24 hours)
        if (!headers_sent()) {
            setcookie(
                $cookieName,
                $sessionId,
                time() + DAY_IN_SECONDS,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true // httponly
            );
        }

        return $sessionId;
    }

}
