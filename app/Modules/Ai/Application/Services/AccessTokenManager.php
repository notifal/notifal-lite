<?php

namespace Notifal\Modules\Ai\Application\Services;

use Notifal\Modules\Ai\Config\Constants;
use Notifal\Modules\Ai\Infrastructure\Gateway\AiGatewayClient;

defined('ABSPATH') || exit;

/**
 * Caches short-lived access tokens and refreshes them using local credentials.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class AccessTokenManager
{
    /**
     * Gateway HTTP client dependency.
     *
     * @var AiGatewayClient
     */
    private AiGatewayClient $gatewayClient;

    /**
     * Local credential store dependency.
     *
     * @var InstallationCredentialStore
     */
    private InstallationCredentialStore $credentialStore;

    /**
     * Create the access token manager.
     *
     * @since 3.0.0
     * @param AiGatewayClient $gatewayClient Gateway HTTP client.
     * @param InstallationCredentialStore $credentialStore Local credential store.
     */
    public function __construct(AiGatewayClient $gatewayClient, InstallationCredentialStore $credentialStore)
    {
        // Store gateway and credential dependencies.
        $this->gatewayClient = $gatewayClient;
        $this->credentialStore = $credentialStore;
    }

    /**
     * Return a valid bearer access token, refreshing when required.
     *
     * @since 3.0.0
     * @return string Access token string.
     *
     * @throws \RuntimeException When refresh credentials are missing or invalid.
     */
    public function getValidToken(): string
    {
        // Read cached access token material from a transient.
        $cached = get_transient(Constants::TRANSIENT_ACCESS_TOKEN);

        // Reuse the cached token when it has not expired.
        if (is_array($cached) && !empty($cached['access_token']) && !empty($cached['expires_at'])) {
            $expiresAt = strtotime((string) $cached['expires_at']);
            if ($expiresAt !== false && ($expiresAt - 60) > time()) {
                return (string) $cached['access_token'];
            }
        }

        // Refresh credentials when cache is stale or missing.
        return $this->refreshToken();
    }

    /**
     * Store a freshly issued access token in the transient cache.
     *
     * @since 3.0.0
     * @param array<string, mixed> $access Access payload from gateway verify/refresh.
     * @return void
     */
    public function storeAccessToken(array $access): void
    {
        // Ignore incomplete access payloads.
        if (empty($access['access_token'])) {
            return;
        }

        // Calculate transient lifetime from gateway expiry metadata.
        $expiresIn = isset($access['expires_in']) ? absint($access['expires_in']) : 900;
        $expiresAt = sanitize_text_field((string) ($access['expires_at'] ?? gmdate('c', time() + $expiresIn)));

        // Cache only the bearer token and expiry timestamp.
        set_transient(
            Constants::TRANSIENT_ACCESS_TOKEN,
            [
                'access_token' => (string) $access['access_token'],
                'expires_at'   => $expiresAt,
            ],
            max(60, $expiresIn - 30)
        );
    }

    /**
     * Clear cached access token material.
     *
     * @since 3.0.0
     * @return void
     */
    public function clearCache(): void
    {
        // Remove the cached bearer token transient.
        delete_transient(Constants::TRANSIENT_ACCESS_TOKEN);
    }

    /**
     * Rotate refresh credentials and issue a new access token.
     *
     * @since 3.0.0
     * @return string Fresh access token.
     *
     * @throws \RuntimeException When refresh fails.
     */
    private function refreshToken(): string
    {
        // Read the encrypted refresh token from local storage.
        $refreshToken = $this->credentialStore->getRefreshToken();

        // Require stored refresh material for authenticated calls.
        if ($refreshToken === '') {
            throw new \RuntimeException('account_connection_required');
        }

        // Call the gateway refresh endpoint server-to-server.
        $response = $this->gatewayClient->post('auth/token/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        // Clear local credentials when refresh is rejected.
        if (!$response->isSuccess()) {
            if (in_array($response->getErrorCode(), ['invalid_refresh_token', 'refresh_token_reuse', 'account_connection_required'], true)) {
                $this->credentialStore->clearConnection();
            }

            throw new \RuntimeException($response->getErrorCode());
        }

        // Extract replacement credential payloads.
        $data = $response->getData();
        $access = is_array($data['access'] ?? null) ? $data['access'] : [];
        $refresh = is_array($data['refresh'] ?? null) ? $data['refresh'] : [];

        // Persist rotated refresh credentials when supplied.
        if (!empty($refresh['refresh_token'])) {
            $this->credentialStore->updateRefreshToken($refresh);
        }

        // Cache the replacement access token.
        $this->storeAccessToken($access);

        // Return the fresh bearer token to callers.
        if (empty($access['access_token'])) {
            throw new \RuntimeException('account_connection_required');
        }

        return (string) $access['access_token'];
    }
}
