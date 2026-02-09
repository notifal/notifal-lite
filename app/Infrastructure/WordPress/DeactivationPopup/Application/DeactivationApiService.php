<?php
/**
 * Deactivation API Service
 *
 * Handles communication with notifal.com API for deactivation feedback.
 *
 * @package Notifal\Infrastructure\WordPress\DeactivationPopup\Application
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\DeactivationPopup\Application;

use Notifal\Core\Support\Helpers\UrlHelper;
use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Infrastructure\WordPress\DeactivationPopup\Domain\DeactivationData;
use Notifal\Infrastructure\WordPress\HttpClientTrait;

defined('ABSPATH') || exit;

/**
 * Class DeactivationApiService
 *
 * @uses HttpClientTrait For common HTTP functionality
 */
class DeactivationApiService
{
    use HttpClientTrait;
    /**
     * API base URL
     *
     * @var string
     */
    private const API_BASE_URL = Urls::API_BASE_NOTIFAL;

    /**
     * API endpoint for deactivation feedback
     *
     * @var string
     */
    private const DEACTIVATION_FEEDBACK_ENDPOINT = '/deactivation-feedback';

    /**
     * HTTP timeout for API requests
     *
     * @var int
     */
    private const API_TIMEOUT = 15;

    /**
     * Send deactivation feedback to notifal.com API.
     *
     * @param DeactivationData $deactivation_data Deactivation data to send
     * @return bool True on success, false on failure
     * @since 2.0.0
     */
    public function sendDeactivationFeedback(DeactivationData $deactivation_data): bool
    {
        try {
            $endpoint = self::API_BASE_URL . self::DEACTIVATION_FEEDBACK_ENDPOINT;

            $response = $this->makeHttpRequest($endpoint, [
                'method' => 'POST',
                'timeout' => self::API_TIMEOUT,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Notifal-WordPress/' . NOTIFAL_VERSION,
                ],
                'body' => wp_json_encode($deactivation_data->toArray()),
            ]);

            // Check for WP_Error
            if (is_wp_error($response)) {
                return false;
            }

            // Validate HTTP status
            if (!$this->validateHttpStatus($response, 200, 'deactivation feedback for domain: ' . $deactivation_data->getDomain())) {
                return false;
            }

            // Parse JSON response
            $response_data = $this->parseJsonResponse($response, 'deactivation feedback for domain: ' . $deactivation_data->getDomain());
            if ($response_data === null) {
                return false;
            }

            // Check API response success
            if (!isset($response_data['success']) || $response_data['success'] !== true) {
                $error_message = $response_data['message'] ?? 'Unknown API error';
                $this->logHttpError(
                    sprintf('API returned error (Domain: %s)', $deactivation_data->getDomain()),
                    sprintf('Error: %s, Full Response: %s', $error_message, wp_json_encode($response_data))
                );
                return false;
            }

        

            return true;

        } catch (\Exception $e) {
            $this->logHttpError(
                sprintf('Exception during API call (Domain: %s)', $deactivation_data->getDomain()),
                sprintf('Exception: %s, File: %s, Line: %s', $e->getMessage(), $e->getFile(), $e->getLine())
            );
            return false;
        }
    }

    /**
     * Send deactivation feedback asynchronously (for delayed calls)
     *
     * @param DeactivationData $deactivation_data Deactivation data to send
     * @return void
     * @since 2.0.0
     */
    public function sendDeactivationFeedbackAsync(DeactivationData $deactivation_data): void
    {
        // Use WordPress HTTP API with background processing
        wp_remote_post(
            UrlHelper::baseAjax(),
            [
                'method' => 'POST',
                'timeout' => 1, // Short timeout since this is async
                'body' => [
                    'action' => 'notifal_send_deactivation_feedback',
                    'deactivation_data' => wp_json_encode($deactivation_data->toArray()),
                    'nonce' => wp_create_nonce('notifal_deactivation_async'),
                ],
                'blocking' => false, // Don't wait for response
            ]
        );
    }

    /**
     * Send deactivation feedback asynchronously without blocking (for immediate sends during deactivation)
     *
     * @param DeactivationData $deactivation_data Deactivation data to send
     * @return void
     * @since 2.0.0
     */
    public function sendDeactivationFeedbackNonBlocking(DeactivationData $deactivation_data): void
    {
        $endpoint = self::API_BASE_URL . self::DEACTIVATION_FEEDBACK_ENDPOINT;

        // Use non-blocking request with proper timeout for immediate deactivation feedback
        $this->makeHttpRequest($endpoint, [
            'method' => 'POST',
            'timeout' => 1, // Reasonable timeout for non-blocking request
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Notifal-WordPress/' . NOTIFAL_VERSION,
            ],
            'body' => wp_json_encode($deactivation_data->toArray()),
            'blocking' => false, // Non-blocking to avoid slowing deactivation
        ]);
    }


    /**
     * Check if API is reachable (for testing)
     *
     * @return bool True if API is reachable
     * @since 2.0.0
     */
    public function isApiReachable(): bool
    {
        return $this->isEndpointReachable(self::API_BASE_URL, 5);
    }

    /**
     * Get API status information
     *
     * @return array API status information
     * @since 2.0.0
     */
    public function getApiStatus(): array
    {
        return [
            'api_url' => self::API_BASE_URL,
            'endpoint' => self::DEACTIVATION_FEEDBACK_ENDPOINT,
            'timeout' => self::API_TIMEOUT,
            'is_reachable' => $this->isApiReachable(),
        ];
    }
}
