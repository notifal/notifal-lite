<?php

namespace Notifal\Modules\Ai\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Ai\Config\Constants;
use Notifal\Modules\Ai\Infrastructure\Gateway\AiGatewayClient;

defined('ABSPATH') || exit;

/**
 * Resolves whether hosted AI generation is currently ready for client wizards.
 *
 * Uses the public gateway /health endpoint so disconnected sites can still
 * detect a paused generation switch without authenticated usage calls.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class GatewayReadinessService
{
    /**
     * Short timeout for the lightweight health probe (seconds).
     *
     * @var int
     */
    private const HEALTH_TIMEOUT_SECONDS = 8;

    /**
     * Gateway HTTP client dependency.
     *
     * @var AiGatewayClient
     */
    private AiGatewayClient $gatewayClient;

    /**
     * Create the readiness service.
     *
     * @since 3.0.0
     * @param AiGatewayClient $gatewayClient Gateway HTTP client.
     */
    public function __construct(AiGatewayClient $gatewayClient)
    {
        // Store the client used for the public health probe.
        $this->gatewayClient = $gatewayClient;
    }

    /**
     * Return a browser-safe readiness snapshot for AI entry points.
     *
     * @since 3.0.0
     * @param bool $forceRefresh When true, bypass the short transient cache.
     * @return array<string, mixed> Readiness payload for AJAX consumers.
     */
    public function getReadiness(bool $forceRefresh = false): array
    {
        // Reuse a warm cache to avoid repeated health hits on notifal.com.
        if (!$forceRefresh) {
            $cached = get_transient(Constants::TRANSIENT_GENERATION_READINESS);
            if (is_array($cached) && isset($cached['generation_ready'])) {
                return [
                    'success' => true,
                    'data'    => $this->normalizeSnapshot($cached, true),
                ];
            }
        }

        // Probe the public health route with a short timeout.
        $response = $this->gatewayClient->get('health', self::HEALTH_TIMEOUT_SECONDS);

        // Fail closed when the gateway cannot be reached or returns an error.
        if (!$response->isSuccess()) {
            $snapshot = [
                'generation_ready'   => false,
                'generation_enabled' => false,
                'service_status'     => 'unreachable',
                'reason'             => 'offline',
                'cached'             => false,
            ];

            // Cache offline briefly so a flaky network does not flood the gateway.
            $this->storeSnapshot($snapshot);

            return [
                'success' => true,
                'data'    => $snapshot,
            ];
        }

        // Read the lightweight health payload from the gateway.
        $data = $response->getData();
        $data = is_array($data) ? $data : [];

        // Prefer the dedicated generation_ready flag when present.
        $generationReady = !empty($data['generation_ready']);
        $generationEnabled = array_key_exists('generation_enabled', $data)
            ? !empty($data['generation_enabled'])
            : $generationReady;

        // Map a stable client reason without exposing internal settings.
        $reason = 'ready';
        if (!$generationReady) {
            $reason = $generationEnabled ? 'unavailable' : 'paused';
        }

        // Compose the public snapshot returned to admin JavaScript.
        $snapshot = [
            'generation_ready'   => $generationReady,
            'generation_enabled' => $generationEnabled,
            'service_status'     => sanitize_key((string) ($data['status'] ?? 'unavailable')),
            'reason'             => $reason,
            'cached'             => false,
        ];

        // Persist a short-lived cache for subsequent open attempts.
        $this->storeSnapshot($snapshot);

        return [
            'success' => true,
            'data'    => $snapshot,
        ];
    }

    /**
     * Persist a readiness snapshot in a short transient.
     *
     * @since 3.0.0
     * @param array<string, mixed> $snapshot Readiness snapshot.
     * @return void
     */
    private function storeSnapshot(array $snapshot): void
    {
        /**
         * Filter the cache lifetime for hosted AI generation readiness.
         *
         * @since 3.0.0
         * @param int $ttl Cache lifetime in seconds.
         */
        $ttl = (int) apply_filters(
            FilterHooks::AI_READINESS_CACHE_TTL,
            Constants::READINESS_CACHE_TTL
        );

        // Keep TTL inside a safe bounded range.
        $ttl = max(5, min(300, $ttl));

        // Store without autoload pressure via a transient.
        set_transient(Constants::TRANSIENT_GENERATION_READINESS, $snapshot, $ttl);
    }

    /**
     * Normalize a cached snapshot for the AJAX response.
     *
     * @since 3.0.0
     * @param array<string, mixed> $snapshot Cached snapshot.
     * @param bool                 $cached   Whether this came from cache.
     * @return array<string, mixed> Normalized snapshot.
     */
    private function normalizeSnapshot(array $snapshot, bool $cached): array
    {
        // Preserve only the public readiness contract.
        return [
            'generation_ready'   => !empty($snapshot['generation_ready']),
            'generation_enabled' => !empty($snapshot['generation_enabled']),
            'service_status'     => sanitize_key((string) ($snapshot['service_status'] ?? 'unavailable')),
            'reason'             => sanitize_key((string) ($snapshot['reason'] ?? 'unavailable')),
            'cached'             => $cached,
        ];
    }
}
