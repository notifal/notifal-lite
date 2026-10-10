<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\API;

use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Infrastructure\WordPress\HttpClientTrait;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Helpers\PreCreatedNotificationBuilderTypes;
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
     * Cache expiration time for trending categories in seconds (1 hour)
     *
     * @since 2.0.0
     * @var int
     */
    private const CACHE_EXPIRATION_TRENDING = 3600; // 1 hour

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

        if ($cached !== false && is_array($cached)) {
            $cached = $this->attachCacheExpiresAt($cached, $cacheKey);
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

        $data = $this->attachCacheExpiresAt($data, $cacheKey, self::CACHE_EXPIRATION_NOTIFICATIONS);
        return $data;
    }

    /**
     * Submit a template request to notifal.com for a builder type.
     *
     * Disabled: template request flow is not used in the client plugin for now.
     *
     * @since 2.0.0
     * @param int    $notificationId Notification (template) ID on notifal.com
     * @param string $builderType    Builder type: elementor, block-editor, or html-builder
     * @return array<string, mixed>  Result with 'success' and optional 'error' or 'message'
     */
    /*
    public function submitTemplateRequest(int $notificationId, string $builderType): array
    {
        if (!PreCreatedNotificationBuilderTypes::isValidImportFileType($builderType)) {
            return [
                'success' => false,
                'error'   => __('Invalid builder type.', 'notifal'),
            ];
        }

        if ($notificationId <= 0) {
            return [
                'success' => false,
                'error'   => __('Invalid notification ID.', 'notifal'),
            ];
        }

        $siteUrl  = home_url('/');
        $adminEmail = get_option('admin_email', '');
        $body = [
            'notification_id' => $notificationId,
            'builder_type'    => $builderType,
            'site_url'        => $siteUrl,
            'admin_email'     => sanitize_email($adminEmail),
        ];

        $url  = self::API_BASE_URL . '/template-requests';
        $args = [
            'timeout'  => self::API_TIMEOUT,
            'body'     => wp_json_encode($body),
            'headers'  => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent'   => self::USER_AGENT,
            ],
        ];

        $args = apply_filters(FilterHooks::PRE_CREATED_NOTIFICATIONS_API_REQUEST_ARGS, $args, $url, $body);

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return [
                'success' => false,
                'error'   => sprintf(
                    __('Request failed with status %d. Please try again later.', 'notifal'),
                    $code
                ),
            ];
        }

        $data = $this->parseJsonResponse($response, 'template-requests');
        if ($data === null) {
            return [
                'success' => false,
                'error'   => __('Invalid response from server. Please try again later.', 'notifal'),
            ];
        }

        if (isset($data['success']) && $data['success'] === false) {
            return [
                'success' => false,
                'error'   => isset($data['error']['message']) ? $data['error']['message'] : __('Request could not be submitted.', 'notifal'),
            ];
        }

        return [
            'success' => true,
            'message' => isset($data['message']) ? $data['message'] : __('We got your request. We will create the template within two days so you can check again and import it. We will send you an email when it is ready.', 'notifal'),
        ];
    }
    */

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
     * Get trending categories from notifal.com (from precreate-archive-trending-notification menu).
     *
     * Each item contains title and use_case_slug for filtering the archive by use case.
     *
     * @since 2.0.0
     * @return array<int, array{title: string, use_case_slug: string}> List of trending items
     */
    public function getTrendingCategories(): array
    {
        $cacheKey = $this->generateCacheKey('trending_categories', []);
        $cached = $this->getCachedResponse($cacheKey);

        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $response = $this->makeApiRequest('trending-categories', []);

        if (is_wp_error($response) || !$this->validateHttpStatus($response, 200, 'trending-categories')) {
            return [];
        }

        $data = $this->parseJsonResponse($response, 'trending-categories');

        if ($data === null || empty($data['data']['trending']) || !is_array($data['data']['trending'])) {
            return [];
        }

        $trending = [];
        foreach ($data['data']['trending'] as $item) {
            if (!empty($item['title']) && !empty($item['use_case_slug'])) {
                $trending[] = [
                    'title' => is_string($item['title']) ? sanitize_text_field($item['title']) : '',
                    'use_case_slug' => is_string($item['use_case_slug']) ? sanitize_text_field($item['use_case_slug']) : '',
                ];
            }
        }

        $this->setCachedResponse($cacheKey, $trending, self::CACHE_EXPIRATION_TRENDING);

        return $trending;
    }

    /**
     * Fetch archive bootstrap payloads (taxonomies, trending, notifications) for the admin archive.
     *
     * Uses parallel HTTP when multiple endpoints are cache-cold to avoid 3x sequential timeouts.
     *
     * @since 3.0.0
     * @param array<string, mixed> $args Notification query arguments.
     * @return array<string, mixed> {
     *     @type array<string, array> $taxonomies Taxonomy groups for filters.
     *     @type array<int, array<string, string>> $trending Trending use-case tags.
     *     @type array<string, mixed> $api_response Notifications list API payload or error shape.
     * }
     */
    public function fetchArchiveBootstrap(array $args = []): array
    {
        // Build sanitized notification query params once for cache key + remote request.
        $queryParams = $this->buildQueryParameters($args);

        // Resolve transient keys for the three bootstrap endpoints.
        $taxonomiesCacheKey = $this->generateCacheKey('taxonomies', []);
        $trendingCacheKey = $this->generateCacheKey('trending_categories', []);
        $notificationsCacheKey = $this->generateCacheKey('notifications', $queryParams);

        // Read any warm cache entries first.
        $taxonomies = $this->getCachedResponse($taxonomiesCacheKey);
        $trending = $this->getCachedResponse($trendingCacheKey);
        $notificationsResponse = $this->getCachedResponse($notificationsCacheKey);

        // Determine which remote endpoints still need fetching.
        $needsTaxonomies = ($taxonomies === false);
        $needsTrending = ($trending === false || !is_array($trending));
        $needsNotifications = ($notificationsResponse === false || !is_array($notificationsResponse));

        // Build parallel request map only for cache misses.
        $parallelJobs = [];
        if ($needsTaxonomies) {
            $parallelJobs['taxonomies'] = [
                'endpoint' => 'taxonomies',
                'params'   => [],
            ];
        }
        if ($needsTrending) {
            $parallelJobs['trending'] = [
                'endpoint' => 'trending-categories',
                'params'   => [],
            ];
        }
        if ($needsNotifications) {
            // Respect local rate limiting before scheduling notifications fetch.
            $rateLimitCheck = $this->checkRateLimit('notifications');
            if (!$rateLimitCheck['allowed']) {
                $notificationsResponse = [
                    'success' => false,
                    'error'   => __('Too many requests. Please wait a moment and try again.', 'notifal'),
                    'rate_limit' => true,
                ];
                $needsNotifications = false;
            } else {
                $parallelJobs['notifications'] = [
                    'endpoint' => 'notifications',
                    'params'   => $queryParams,
                ];
            }
        }

        // Execute remaining remote calls concurrently when caches are cold.
        if (!empty($parallelJobs)) {
            $parallelResponses = $this->fetchEndpointsParallel($parallelJobs);

            if ($needsTaxonomies) {
                $taxonomies = $this->buildTaxonomiesFromHttpResponse($parallelResponses['taxonomies'] ?? null);
                $this->setCachedResponse($taxonomiesCacheKey, $taxonomies, self::CACHE_EXPIRATION_TAXONOMIES);
            }

            if ($needsTrending) {
                $trending = $this->buildTrendingFromHttpResponse($parallelResponses['trending'] ?? null);
                $this->setCachedResponse($trendingCacheKey, $trending, self::CACHE_EXPIRATION_TRENDING);
            }

            if ($needsNotifications) {
                $notificationsResponse = $this->buildNotificationsFromHttpResponse(
                    $parallelResponses['notifications'] ?? null,
                    $queryParams
                );
                if (is_array($notificationsResponse)
                    && (!isset($notificationsResponse['success']) || $notificationsResponse['success'] !== false)
                ) {
                    $this->setCachedResponse(
                        $notificationsCacheKey,
                        $notificationsResponse,
                        self::CACHE_EXPIRATION_NOTIFICATIONS
                    );
                }
            }
        }

        // Guarantee taxonomy shape even when remote fetch failed.
        if (!is_array($taxonomies)) {
            $taxonomies = [
                'use_cases'    => [],
                'events'       => [],
                'industries'   => [],
                'layouts'      => [],
                'used_plugins' => [],
            ];
        }

        // Guarantee trending list shape.
        if (!is_array($trending)) {
            $trending = [];
        }

        // Guarantee notifications response shape for the archive view.
        if (!is_array($notificationsResponse)) {
            $notificationsResponse = [
                'success' => false,
                'error'   => __('Failed to load notifications.', 'notifal'),
            ];
        }

        return [
            'taxonomies'    => $taxonomies,
            'trending'      => $trending,
            'api_response'  => $this->attachCacheExpiresAt(
                $notificationsResponse,
                $notificationsCacheKey,
                self::CACHE_EXPIRATION_NOTIFICATIONS
            ),
        ];
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
            'html_builder_available' => '',
            'elementor_available' => '',
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

        // Add builder availability filters for Setup Wizard recommendations.
        if (isset($args['html_builder_available']) && $args['html_builder_available'] !== '') {
            $params['html_builder_available'] = Helper::sanitizeInput($args['html_builder_available'], 'text');
        }
        if (isset($args['elementor_available']) && $args['elementor_available'] !== '') {
            $params['elementor_available'] = Helper::sanitizeInput($args['elementor_available'], 'text');
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

        $args = [
            'timeout' => self::API_TIMEOUT,
            // Align connect timeout with request timeout (Requests defaults to 10s).
            'connect_timeout' => self::API_TIMEOUT,
            'user-agent' => self::USER_AGENT,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ];

        // Handle parameters based on request method
        if ($method === 'POST' && !empty($params)) {
            // For POST requests, send data in body as JSON
            $args['body'] = wp_json_encode($params);
        } elseif ($method === 'GET' && !empty($params)) {
            // For GET requests, build query string manually to handle arrays properly
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
     * Build a full marketplace endpoint URL with optional query parameters.
     *
     * @since 3.0.0
     * @param string               $endpoint API endpoint slug.
     * @param array<string, mixed> $params   Optional query parameters.
     * @return string Fully qualified marketplace URL.
     */
    private function buildEndpointUrl(string $endpoint, array $params = []): string
    {
        // Start from the marketplace REST base URL.
        $url = self::API_BASE_URL . '/' . ltrim($endpoint, '/');

        // Append query string when params are provided.
        if (!empty($params)) {
            $queryParts = [];
            foreach ($params as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $item) {
                        $queryParts[] = urlencode($key) . '[]=' . urlencode((string) $item);
                    }
                } else {
                    $queryParts[] = urlencode($key) . '=' . urlencode((string) $value);
                }
            }
            $url .= '?' . implode('&', $queryParts);
        }

        return $url;
    }

    /**
     * Execute multiple marketplace GET requests in parallel via WordPress Requests library.
     *
     * @since 3.0.0
     * @param array<string, array{endpoint:string,params:array<string,mixed>}> $jobs Endpoint jobs keyed by label.
     * @return array<string, array|\WP_Error> Normalized wp_remote-style responses keyed by label.
     */
    private function fetchEndpointsParallel(array $jobs): array
    {
        // Abort early when there is nothing to fetch.
        if (empty($jobs)) {
            return [];
        }

        // Build Requests-compatible job map.
        $requests = [];
        foreach ($jobs as $key => $job) {
            $requests[$key] = [
                'url'     => $this->buildEndpointUrl($job['endpoint'], $job['params']),
                'type'    => 'GET',
                'headers' => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ];
        }

        // Fire all requests concurrently.
        $rawResponses = \WpOrg\Requests\Requests::request_multiple(
            $requests,
            [
                'timeout'         => self::API_TIMEOUT,
                'connect_timeout' => self::API_TIMEOUT,
                'useragent'       => self::USER_AGENT,
            ]
        );

        // Normalize each response into wp_remote-style arrays for shared parsers.
        $normalized = [];
        foreach ($rawResponses as $key => $rawResponse) {
            $normalized[$key] = $this->normalizeParallelResponse($rawResponse, (string) $key);
        }

        return $normalized;
    }

    /**
     * Convert a Requests library response into wp_remote-compatible structure.
     *
     * @since 3.0.0
     * @param mixed  $rawResponse Requests response or exception instance.
     * @param string $label       Endpoint label for error context.
     * @return array<string, mixed>|\WP_Error Normalized response.
     */
    private function normalizeParallelResponse($rawResponse, string $label)
    {
        // Handle transport-level exceptions from Requests.
        if ($rawResponse instanceof \WpOrg\Requests\Exception) {
            return new \WP_Error('http_request_failed', $rawResponse->getMessage());
        }

        // Unexpected payload types are treated as hard failures.
        if (!$rawResponse instanceof \WpOrg\Requests\Response) {
            return new \WP_Error(
                'http_request_failed',
                sprintf(__('Unexpected parallel response for %s.', 'notifal'), $label)
            );
        }

        // Map Requests response fields to wp_remote_retrieve_* compatible shape.
        return [
            'headers'  => $rawResponse->headers->getAll(),
            'body'     => $rawResponse->body,
            'response' => [
                'code'    => (int) $rawResponse->status_code,
                'message' => get_status_header_desc((int) $rawResponse->status_code),
            ],
        ];
    }

    /**
     * Parse taxonomies payload from a normalized HTTP response.
     *
     * @since 3.0.0
     * @param array<string, mixed>|\WP_Error|null $response Normalized HTTP response.
     * @return array<string, array<int, mixed>> Taxonomy groups for archive filters.
     */
    private function buildTaxonomiesFromHttpResponse($response): array
    {
        // Default empty taxonomy groups used by the archive sidebar.
        $empty = [
            'use_cases'    => [],
            'events'       => [],
            'industries'   => [],
            'layouts'      => [],
            'used_plugins' => [],
        ];

        if (is_wp_error($response) || !$this->validateHttpStatus($response, 200, 'taxonomies')) {
            return $empty;
        }

        $data = $this->parseJsonResponse($response, 'taxonomies');
        if ($data === null) {
            return $empty;
        }

        return [
            'use_cases'    => $data['data']['use_cases'] ?? [],
            'events'       => $data['data']['events'] ?? [],
            'industries'   => $data['data']['industries'] ?? [],
            'layouts'      => $data['data']['layouts'] ?? [],
            'used_plugins' => $data['data']['plugins'] ?? [],
        ];
    }

    /**
     * Parse trending categories payload from a normalized HTTP response.
     *
     * @since 3.0.0
     * @param array<string, mixed>|\WP_Error|null $response Normalized HTTP response.
     * @return array<int, array{title:string,use_case_slug:string}> Trending tags.
     */
    private function buildTrendingFromHttpResponse($response): array
    {
        if (is_wp_error($response) || !$this->validateHttpStatus($response, 200, 'trending-categories')) {
            return [];
        }

        $data = $this->parseJsonResponse($response, 'trending-categories');
        if ($data === null || empty($data['data']['trending']) || !is_array($data['data']['trending'])) {
            return [];
        }

        $trending = [];
        foreach ($data['data']['trending'] as $item) {
            if (!empty($item['title']) && !empty($item['use_case_slug'])) {
                $trending[] = [
                    'title'         => is_string($item['title']) ? sanitize_text_field($item['title']) : '',
                    'use_case_slug' => is_string($item['use_case_slug']) ? sanitize_text_field($item['use_case_slug']) : '',
                ];
            }
        }

        return $trending;
    }

    /**
     * Parse notifications list payload from a normalized HTTP response.
     *
     * @since 3.0.0
     * @param array<string, mixed>|\WP_Error|null $response Normalized HTTP response.
     * @param array<string, mixed>                  $args      Original query arguments.
     * @return array<string, mixed> Notifications API payload or error array.
     */
    private function buildNotificationsFromHttpResponse($response, array $args): array
    {
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        if (!$this->validateHttpStatus($response, 200, 'notifications list')) {
            return [
                'success' => false,
                'error'   => sprintf(
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
                'error'   => __('Invalid JSON response from API', 'notifal'),
            ];
        }

        /**
         * Fires after successfully fetching notifications from API.
         *
         * @since 3.0.0
         * @param array $data API response data
         * @param array $args Original query arguments
         */
        do_action(ActionHooks::PRE_CREATED_NOTIFICATIONS_API_FETCHED, $data, $args);

        return $data;
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
     * Attach cache expiration timestamp to response for UI display.
     *
     * @since 2.0.0
     * @param array<string, mixed> $response Response array (must be array)
     * @param string $cacheKey Transient key used for caching
     * @param int|null $expirationSeconds If set, used when timeout option is not found (e.g. fresh set)
     * @return array<string, mixed> Response with cache_expires_at key added
     */
    private function attachCacheExpiresAt(array $response, string $cacheKey, ?int $expirationSeconds = null): array
    {
        $expires = get_option('_transient_timeout_' . $cacheKey, false);
        if ($expires !== false) {
            $response['cache_expires_at'] = (int) $expires;
            return $response;
        }
        if ($expirationSeconds !== null) {
            $response['cache_expires_at'] = time() + $expirationSeconds;
        }
        return $response;
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
     * Get single notification details from marketplace API.
     *
     *
     * @since 2.0.0
     * @param int $notificationId Notification ID
     * @return array<string, mixed> API response data or error
     */
    public function getSingleNotification(int $notificationId): array
    {
        if ($notificationId <= 0) {
            return [
                'success' => false,
                'error' => __('Invalid notification ID.', 'notifal'),
            ];
        }

        $cacheKey = $this->generateCacheKey('single_notification_' . $notificationId, []);

        // Try cache first
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

        // Build tracking context
        $requestParams = [];
        $siteUrl = home_url('/');
        $parsedUrl = function_exists('wp_parse_url') ? wp_parse_url($siteUrl) : parse_url($siteUrl);
        $domain = is_array($parsedUrl) && isset($parsedUrl['host']) ? $parsedUrl['host'] : '';
        $adminEmail = get_option('admin_email', '');

        if ($domain !== '') {
            $requestParams['domain'] = sanitize_text_field($domain);
        }

        if ($adminEmail !== '') {
            $requestParams['admin_email'] = sanitize_email($adminEmail);
        }

        // Make API request
        $response = $this->makeApiRequest('notifications/' . $notificationId, $requestParams);

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
     * Get download URL for a notification file.
     *
     * @since 2.0.0
     * @param int    $notificationId Notification ID
     * @param string $fileType       File type: elementor, block-editor, or html-builder
     * @return array<string, mixed>  API response with download URL or error
     */
    public function getDownloadUrl(int $notificationId, string $fileType): array
    {
        if (!PreCreatedNotificationBuilderTypes::isValidImportFileType($fileType)) {
            return [
                'success' => false,
                'error'   => __('Invalid file type.', 'notifal'),
            ];
        }

        // Build tracking context
        $requestBody = [];
        $siteUrl     = home_url('/');
        $parsedUrl   = function_exists('wp_parse_url') ? wp_parse_url($siteUrl) : parse_url($siteUrl);
        $domain      = is_array($parsedUrl) && isset($parsedUrl['host']) ? $parsedUrl['host'] : '';
        $adminEmail  = get_option('admin_email', '');

        if ($domain !== '') {
            $requestBody['domain'] = sanitize_text_field($domain);
        }

        if ($adminEmail !== '') {
            $requestBody['admin_email'] = sanitize_email($adminEmail);
        }

        // Make API request to get download URL
        $endpoint = 'downloads/' . $notificationId . '/' . $fileType;
        $response = $this->makeApiRequest($endpoint, $requestBody, 'POST');

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        if (!$this->validateHttpStatus($response, 200, 'download URL for ' . $notificationId . '/' . $fileType)) {
            return [
                'success' => false,
                'error'   => sprintf(
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
                'error'   => __('Invalid JSON response from API', 'notifal'),
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
