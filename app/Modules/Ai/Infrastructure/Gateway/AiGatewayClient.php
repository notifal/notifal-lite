<?php

namespace Notifal\Modules\Ai\Infrastructure\Gateway;

use Notifal\Infrastructure\WordPress\HttpClientTrait;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Ai\Application\Services\GatewayUrlResolver;
use Notifal\Modules\Ai\Config\Constants;

defined('ABSPATH') || exit;

/**
 * Server-to-server HTTP client for the hosted Notifal AI gateway.
 *
 * @since 3.0.0
 * @updated 3.0.0 Raise per-request timeout ceiling so large completed generation payloads can transfer.
 * @author Hossein <hossein@notifal.com>
 */
class AiGatewayClient
{
    use HttpClientTrait;

    /**
     * Gateway URL resolver dependency.
     *
     * @var GatewayUrlResolver
     */
    private GatewayUrlResolver $urlResolver;

    /**
     * Default HTTP timeout for gateway calls in seconds.
     *
     * Status polls for completed OnPage jobs may override this up to REQUEST_TIMEOUT_MAX.
     *
     * @var int
     */
    private int $timeout = 45;

    /**
     * Maximum allowed per-request HTTP timeout in seconds.
     *
     * Bound transfer time for large completed outputs without waiting on remote generation
     * (generation wait happens via client-side polling, not one long HTTP call).
     *
     * @since 3.0.0
     * @var int
     */
    private const REQUEST_TIMEOUT_MAX = 90;

    /**
     * Create the gateway HTTP client.
     *
     * @since 3.0.0
     * @param GatewayUrlResolver $urlResolver Gateway base URL resolver.
     */
    public function __construct(GatewayUrlResolver $urlResolver)
    {
        // Store the resolver used to build route URLs.
        $this->urlResolver = $urlResolver;
    }

    /**
     * Perform an unauthenticated GET request against the gateway.
     *
     * @since 3.0.0
     * @param string   $route          Route suffix.
     * @param int|null $timeoutSeconds Optional per-request timeout override.
     * @return GatewayResponse Normalized gateway response.
     */
    public function get(string $route, ?int $timeoutSeconds = null): GatewayResponse
    {
        // Delegate to the shared request helper without auth headers.
        return $this->request('GET', $route, [], '', [], $timeoutSeconds);
    }

    /**
     * Perform an unauthenticated POST request against the gateway.
     *
     * @since 3.0.0
     * @param string $route Route suffix.
     * @param array<string, mixed> $body JSON request body.
     * @return GatewayResponse Normalized gateway response.
     */
    public function post(string $route, array $body = []): GatewayResponse
    {
        // Delegate to the shared request helper without auth headers.
        return $this->request('POST', $route, $body);
    }

    /**
     * Perform an authenticated GET request with a bearer access token.
     *
     * @since 3.0.0
     * @param string   $route          Route suffix.
     * @param string   $accessToken    Short-lived bearer token.
     * @param int|null $timeoutSeconds Optional per-request timeout override.
     * @return GatewayResponse Normalized gateway response.
     */
    public function getAuthenticated(string $route, string $accessToken, ?int $timeoutSeconds = null): GatewayResponse
    {
        // Attach bearer authorization for protected routes.
        return $this->request('GET', $route, [], $accessToken, [], $timeoutSeconds);
    }

    /**
     * Perform an authenticated POST request with optional extra headers.
     *
     * @since 3.0.0
     * @param string $route Route suffix.
     * @param array<string, mixed> $body JSON request body.
     * @param string $accessToken Short-lived bearer token.
     * @param array<string, string> $headers Additional HTTP headers.
     * @param int|null $timeoutSeconds Optional per-request timeout override.
     * @return GatewayResponse Normalized gateway response.
     * @updated 3.0.0 Optional timeout so cancel can fail fast while status keeps a longer budget.
     */
    public function postAuthenticated(
        string $route,
        array $body,
        string $accessToken,
        array $headers = [],
        ?int $timeoutSeconds = null
    ): GatewayResponse {
        // Attach bearer authorization and custom headers.
        return $this->request('POST', $route, $body, $accessToken, $headers, $timeoutSeconds);
    }

    /**
     * Execute one HTTP request and normalize the gateway envelope.
     *
     * @since 3.0.0
     * @param string               $method         HTTP method.
     * @param string               $route          Route suffix.
     * @param array<string, mixed> $body           Optional JSON body.
     * @param string               $accessToken    Optional bearer token.
     * @param array<string, string> $extraHeaders  Optional extra headers.
     * @param int|null             $timeoutSeconds Optional per-request timeout override.
     * @return GatewayResponse Normalized gateway response.
     */
    private function request(
        string $method,
        string $route,
        array $body = [],
        string $accessToken = '',
        array $extraHeaders = [],
        ?int $timeoutSeconds = null
    ): GatewayResponse {
        // Resolve the absolute gateway route URL.
        $url = $this->urlResolver->route($route);

        // Build standard JSON request headers without browser Origin.
        $headers = array_merge(
            [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent'   => Constants::USER_AGENT . (defined('NOTIFAL_VERSION') ? NOTIFAL_VERSION : '0.0.0'),
            ],
            $extraHeaders
        );

        // Attach bearer authorization when an access token is supplied.
        if ($accessToken !== '') {
            $headers['Authorization'] = 'Bearer ' . $accessToken;
        }

        // Prefer an explicit short timeout for lightweight probes such as /health.
        $timeout = null !== $timeoutSeconds
            ? max(1, min(self::REQUEST_TIMEOUT_MAX, (int) $timeoutSeconds))
            : $this->timeout;

        // Align connect timeout with the request timeout so the Requests default
        // of 10s does not abort long wizard/trial or generation calls early.
        $args = [
            'method'          => strtoupper($method),
            'timeout'         => $timeout,
            'connect_timeout' => $timeout,
            'headers'         => $headers,
            'sslverify'       => true,
        ];

        // Encode POST/PUT bodies as JSON when payload data exists.
        if (!empty($body)) {
            $args['body'] = wp_json_encode($body);
        }

        /**
         * Filter gateway HTTP request arguments before dispatch.
         *
         * @since 3.0.0
         * @param array<string, mixed> $args Request arguments.
         * @param string $url Absolute request URL.
         * @param string $route Route suffix.
         */
        $args = apply_filters(FilterHooks::AI_GATEWAY_REQUEST_ARGS, $args, $url, $route);

        // Execute the outbound server-to-server request.
        $response = $this->makeHttpRequest($url, $args);

        // Normalize transport failures into gateway-style errors.
        if (is_wp_error($response)) {
            return new GatewayResponse(
                false,
                0,
                null,
                'gateway_unreachable',
                $response->get_error_message()
            );
        }

        // Parse the upstream HTTP status and JSON body.
        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $parsed = $this->parseJsonResponse($response, $route);

        // Treat unparsable bodies as gateway failures.
        if ($parsed === null) {
            return new GatewayResponse(
                false,
                $statusCode,
                null,
                'invalid_gateway_response',
                __('The AI service returned an invalid response.', 'notifal')
            );
        }

        // Read optional request correlation identifier from top-level or nested data.
        $requestId = sanitize_text_field(
            (string) (
                $parsed['request_id']
                ?? ($parsed['data']['request_id'] ?? '')
            )
        );

        // Handle the stable success envelope.
        if (!empty($parsed['success']) && isset($parsed['data']) && is_array($parsed['data'])) {
            return new GatewayResponse(true, $statusCode, $parsed['data'], '', '', null, $requestId);
        }

        // Extract nested REST error metadata when present.
        $error = is_array($parsed['error'] ?? null) ? $parsed['error'] : [];
        $errorData = is_array($parsed['data']['error'] ?? null) ? $parsed['data']['error'] : $error;
        $errorCode = sanitize_key((string) ($errorData['code'] ?? ($parsed['code'] ?? 'gateway_error')));
        $errorMessage = sanitize_text_field((string) ($errorData['message'] ?? __('The AI service could not complete your request.', 'notifal')));
        $retryAfter = isset($errorData['retry_after']) ? absint($errorData['retry_after']) : null;

        // Collect optional related field slugs from structured validation failures.
        $fields = [];
        if (is_array($errorData['fields'] ?? null)) {
            foreach ($errorData['fields'] as $field) {
                $slug = sanitize_key((string) $field);
                if ('' === $slug || in_array($slug, $fields, true)) {
                    continue;
                }
                $fields[] = $slug;
                if (12 <= count($fields)) {
                    break;
                }
            }
        }

        // Return a normalized failure response object.
        return new GatewayResponse(
            false,
            $statusCode > 0 ? $statusCode : 500,
            null,
            $errorCode !== '' ? $errorCode : 'gateway_error',
            $errorMessage,
            $retryAfter,
            $requestId,
            $fields
        );
    }
}
