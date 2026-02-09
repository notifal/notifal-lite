<?php
/**
 * HTTP Client Trait
 *
 * Provides common HTTP request functionality for API services.
 *
 * @package Notifal\Infrastructure\WordPress
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress;

use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Trait HttpClientTrait
 *
 * Common HTTP client functionality for API services.
 */
trait HttpClientTrait
{
    /**
     * Default HTTP timeout for API requests.
     *
     * @var int
     */
    protected int $defaultTimeout = 30;

    /**
     * Make HTTP request with common error handling.
     *
     * @param string $url Request URL
     * @param array $args Request arguments
     * @return array|\WP_Error Response or error
     * @since 2.0.0
     */
    protected function makeHttpRequest(string $url, array $args = [])
    {
        // Set default timeout if not provided
        if (!isset($args['timeout'])) {
            $args['timeout'] = $this->defaultTimeout;
        }

        // Ensure SSL verification is enabled by default
        if (!isset($args['sslverify'])) {
            $args['sslverify'] = true;
        }

        $response = wp_remote_request($url, $args);

        // Handle WP_Error
        if (is_wp_error($response)) {
            $this->logHttpError(
                sprintf('WP_Error during HTTP request to %s', $url),
                sprintf('Error: %s, Error Data: %s', $response->get_error_message(), wp_json_encode($response->get_error_data()))
            );
            return $response;
        }

        return $response;
    }

    /**
     * Parse JSON response with error handling.
     *
     * @param array|\WP_Error $response HTTP response
     * @param string $context Context for error logging
     * @return array|null Parsed data or null on error
     * @since 2.0.0
     */
    protected function parseJsonResponse($response, string $context = ''): ?array
    {
        if (is_wp_error($response)) {
            return null;
        }

        $response_body = wp_remote_retrieve_body($response);

        if (empty($response_body)) {
            $this->logHttpError(
                sprintf('Empty response body from API (%s)', $context),
                'Response body was empty'
            );
            return null;
        }

        $data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logHttpError(
                sprintf('Invalid JSON response from API (%s)', $context),
                sprintf('JSON Error: %s, Response Body: %s', json_last_error_msg(), $response_body)
            );
            return null;
        }

        return $data;
    }

    /**
     * Check HTTP response status and log errors.
     *
     * @param array|\WP_Error $response HTTP response
     * @param int $expected_status Expected HTTP status code
     * @param string $context Context for error logging
     * @return bool True if status matches expected
     * @since 2.0.0
     */
    protected function validateHttpStatus($response, int $expected_status = 200, string $context = ''): bool
    {
        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== $expected_status) {
            $this->logHttpError(
                sprintf('HTTP %d error from API (%s)', $response_code, $context),
                sprintf('Expected: %d, Response: %s, Headers: %s', $expected_status, $response_body, wp_json_encode(wp_remote_retrieve_headers($response)))
            );
            return false;
        }

        return true;
    }

    /**
     * Log HTTP-related errors.
     *
     * @param string $message Error message
     * @param string $details Error details
     * @return void
     * @since 2.0.0
     */
    protected function logHttpError(string $message, string $details): void
    {
        Helper::logAdvanced(
            sprintf('HTTP Error - %s: %s', $message, $details),
            'ERROR'
        );
    }

    /**
     * Check if API endpoint is reachable.
     *
     * @param string $url URL to check
     * @param int $timeout Timeout in seconds
     * @return bool True if reachable
     * @since 2.0.0
     */
    protected function isEndpointReachable(string $url, int $timeout = 5): bool
    {
        $response = wp_remote_head($url, [
            'timeout' => $timeout,
            'sslverify' => true,
            'redirection' => 0, // Don't follow redirects for health checks
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code >= 200 && $response_code < 400;
    }
}
