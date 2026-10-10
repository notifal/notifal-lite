<?php

namespace Notifal\Modules\Ai\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Ai\Config\Constants;
use Notifal\Modules\Ai\Domain\GatewayErrorMapper;
use Notifal\Modules\Ai\Domain\SiteIdentityBuilder;
use Notifal\Modules\Ai\Infrastructure\Gateway\AiGatewayClient;
use Notifal\Modules\Ai\Infrastructure\Gateway\GatewayResponse;

defined('ABSPATH') || exit;

/**
 * Passwordless account connection workflow for the hosted AI gateway.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class AccountConnectionService
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
     * Access token cache dependency.
     *
     * @var AccessTokenManager
     */
    private AccessTokenManager $accessTokenManager;

    /**
     * Site identity builder dependency.
     *
     * @var SiteIdentityBuilder
     */
    private SiteIdentityBuilder $siteIdentityBuilder;

    /**
     * Create the account connection service.
     *
     * @since 3.0.0
     * @param AiGatewayClient $gatewayClient Gateway HTTP client.
     * @param InstallationCredentialStore $credentialStore Credential store.
     * @param AccessTokenManager $accessTokenManager Access token manager.
     * @param SiteIdentityBuilder $siteIdentityBuilder Site identity builder.
     */
    public function __construct(
        AiGatewayClient $gatewayClient,
        InstallationCredentialStore $credentialStore,
        AccessTokenManager $accessTokenManager,
        SiteIdentityBuilder $siteIdentityBuilder
    ) {
        // Store service dependencies for connection workflows.
        $this->gatewayClient = $gatewayClient;
        $this->credentialStore = $credentialStore;
        $this->accessTokenManager = $accessTokenManager;
        $this->siteIdentityBuilder = $siteIdentityBuilder;
    }

    /**
     * Return the current mandatory disclosure version for client UI.
     *
     * @since 3.0.0
     * @return string Disclosure version string.
     */
    public function getDisclosureVersion(): string
    {
        /**
         * Filter the mandatory AI disclosure version shown to administrators.
         *
         * @since 3.0.0
         * @param string $version Disclosure version slug.
         */
        return (string) apply_filters(FilterHooks::AI_DISCLOSURE_VERSION, Constants::DEFAULT_DISCLOSURE_VERSION);
    }

    /**
     * Request a passwordless verification code, or connect when the account already exists.
     *
     * @since 3.0.0
     * @param string $email Administrator email address.
     * @param string $contactName Submitted display name for the notifal.com account.
     * @param bool   $marketingOptIn Whether the user opted into CRO tips email.
     * @return array<string, mixed> Challenge metadata or connected state for the UI.
     */
    public function requestCode(string $email, string $contactName = '', bool $marketingOptIn = false): array
    {
        // Sanitize the submitted email before sending it upstream.
        $email = sanitize_email($email);

        // Sanitize the contact name collected on the connect form.
        $contactName = sanitize_text_field(trim($contactName));

        // Split the contact name into WordPress first and last name fields.
        $profileNames = $this->parseContactName($contactName);

        // Build the server-to-server request body expected by the gateway.
        $body = [
            'email'               => $email,
            'site'                => $this->siteIdentityBuilder->build(),
            'disclosure_version'  => $this->getDisclosureVersion(),
            'scopes'              => Constants::DEFAULT_SCOPES,
            'disclosure_source'   => 'notifal_plugin',
            'first_name'          => $profileNames['first_name'],
            'last_name'           => $profileNames['last_name'],
            'marketing_opt_in'    => $marketingOptIn,
        ];

        // Execute the passwordless request route (may connect known accounts directly).
        $response = $this->gatewayClient->post('auth/email/request-code', $body);

        // Convert gateway failures into UI-friendly arrays.
        if (!$response->isSuccess()) {
            return $this->normalizeResponse($response);
        }

        // Read the gateway payload for either a challenge or immediate credentials.
        $data = $response->getData();

        // Persist credentials when the gateway skipped OTP for an existing account.
        if ($this->responseIncludesCredentials($data)) {
            return $this->persistConnectedResponse($data, $contactName);
        }

        // Return challenge metadata so the UI can open the verification step.
        return [
            'success' => true,
            'data'    => [
                'verification_required' => true,
                'transaction_id'        => sanitize_text_field((string) ($data['transaction_id'] ?? '')),
                'expires_in'            => absint($data['expires_in'] ?? 0),
                'resend_after'          => absint($data['resend_after'] ?? 120),
                'message'               => sanitize_text_field((string) ($data['message'] ?? '')),
            ],
        ];
    }

    /**
     * Verify a six-digit code and persist local connection credentials.
     *
     * @since 3.0.0
     * @param string $transactionId Opaque challenge transaction ID.
     * @param string $code Six-digit verification code.
     * @param string $contactName Submitted display name for the notifal.com account.
     * @param bool   $marketingOptIn Whether the user opted into CRO tips email.
     * @return array<string, mixed> Safe connected state for the UI.
     */
    public function verifyCode(string $transactionId, string $code, string $contactName = '', bool $marketingOptIn = false): array
    {
        // Sanitize transaction and code inputs.
        $transactionId = sanitize_text_field($transactionId);
        $code = preg_replace('/\D+/', '', $code);
        $contactName = sanitize_text_field(trim($contactName));

        // Split the contact name into WordPress first and last name fields.
        $profileNames = $this->parseContactName($contactName);

        // Execute the verification route server-to-server.
        $response = $this->gatewayClient->post('auth/email/verify-code', [
            'transaction_id'   => $transactionId,
            'code'             => (string) $code,
            'first_name'       => $profileNames['first_name'],
            'last_name'        => $profileNames['last_name'],
            'marketing_opt_in' => $marketingOptIn,
        ]);

        // Return normalized failure payloads without storing credentials.
        if (!$response->isSuccess()) {
            return $this->normalizeResponse($response);
        }

        // Persist credentials from the verified connection response.
        return $this->persistConnectedResponse($response->getData(), $contactName);
    }

    /**
     * Detect whether a gateway payload contains installation credentials.
     *
     * @since 3.0.0
     * @param array<string, mixed> $data Gateway success payload.
     * @return bool True when access and refresh tokens are present.
     */
    private function responseIncludesCredentials(array $data): bool
    {
        // Require both token envelopes before treating the payload as connected.
        $access = is_array($data['access'] ?? null) ? $data['access'] : [];
        $refresh = is_array($data['refresh'] ?? null) ? $data['refresh'] : [];

        return !empty($access['access_token']) && !empty($refresh['refresh_token']);
    }

    /**
     * Persist gateway credentials and return a browser-safe connected snapshot.
     *
     * @since 3.0.0
     * @param array<string, mixed> $data Gateway connected payload.
     * @param string               $contactName Submitted display name fallback.
     * @return array<string, mixed> AJAX success envelope for the UI.
     */
    private function persistConnectedResponse(array $data, string $contactName): array
    {
        // Extract credential payloads from the gateway response.
        $account = is_array($data['account'] ?? null) ? $data['account'] : [];
        $installation = is_array($data['installation'] ?? null) ? $data['installation'] : [];
        $access = is_array($data['access'] ?? null) ? $data['access'] : [];
        $refresh = is_array($data['refresh'] ?? null) ? $data['refresh'] : [];

        // Persist encrypted refresh credentials and public installation metadata.
        $this->credentialStore->saveConnection(
            $account,
            $installation,
            $refresh,
            $this->getDisclosureVersion()
        );

        // Cache the short-lived access token for immediate authenticated calls.
        $this->accessTokenManager->storeAccessToken($access);

        // Persist the greeting name locally for Noti and future wizard runs.
        $this->syncContactNameToSiteProfile($account, $contactName);

        // Return a browser-safe connected snapshot.
        return [
            'success' => true,
            'data'    => [
                'verification_required' => false,
                'connection'            => $this->credentialStore->getPublicState(),
            ],
        ];
    }

    /**
     * Refresh remote installation status and return safe local state.
     *
     * @since 3.0.0
     * @return array<string, mixed> Connection and usage snapshot.
     */
    public function getStatus(): array
    {
        // Start from locally stored public metadata.
        $state = [
            'connection' => $this->credentialStore->getPublicState(),
            'usage'      => null,
        ];

        // Return early when the site is not connected locally.
        if (!$this->credentialStore->isConnected()) {
            return [
                'success' => true,
                'data'    => $state,
            ];
        }

        try {
            // Resolve a valid bearer token, refreshing when needed.
            $token = $this->accessTokenManager->getValidToken();

            // Ask the gateway for current installation status.
            $installationResponse = $this->gatewayClient->getAuthenticated('installation', $token);

            // Drop stale local credentials when the gateway rejects or revokes the installation.
            if (!$installationResponse->isSuccess()) {
                $code = sanitize_key($installationResponse->getErrorCode());
                if (in_array($code, ['account_connection_required', 'invalid_token', 'unauthorized', 'installation_revoked'], true)) {
                    $this->invalidateLocalConnection();
                    $state['connection'] = $this->credentialStore->getPublicState();
                    return [
                        'success' => true,
                        'data'    => $state,
                    ];
                }
            } elseif ($installationResponse->isSuccess()) {
                $remote = $installationResponse->getData();
                if (empty($remote['connected'])) {
                    $this->invalidateLocalConnection();
                    $state['connection'] = $this->credentialStore->getPublicState();
                    return [
                        'success' => true,
                        'data'    => $state,
                    ];
                }

                // Merge remote status when the gateway confirms the connection.
                $state['connection']['remote'] = [
                    'connected' => !empty($remote['connected']),
                    'account'   => is_array($remote['account'] ?? null) ? $remote['account'] : [],
                    'installation' => is_array($remote['installation'] ?? null) ? $remote['installation'] : [],
                ];

                // Keep the local greeting name aligned with the gateway account profile.
                $remoteAccount = is_array($remote['account'] ?? null) ? $remote['account'] : [];
                $this->syncContactNameToSiteProfile($remoteAccount, '');
            }

            // Fetch current quota summary for UI badges.
            $usageResponse = $this->gatewayClient->getAuthenticated('usage', $token);
            if ($usageResponse->isSuccess()) {
                $state['usage'] = $usageResponse->getData();
            }
        } catch (\Throwable $exception) {
            // Clear revoked credentials and surface a stable reconnect code.
            if ('account_connection_required' === $exception->getMessage()) {
                $this->invalidateLocalConnection();
            }

            $state['connection'] = $this->credentialStore->getPublicState();
            $state['connection']['error_code'] = sanitize_key($exception->getMessage());
        }

        // Return the combined local and remote snapshot.
        return [
            'success' => true,
            'data'    => $state,
        ];
    }

    /**
     * Disconnect the local installation and revoke remote credentials.
     *
     * @since 3.0.0
     * @return array<string, mixed> Disconnect result for the UI.
     */
    public function disconnect(): array
    {
        // Attempt remote revocation when local credentials still exist.
        if ($this->credentialStore->isConnected()) {
            try {
                $token = $this->accessTokenManager->getValidToken();
                $this->gatewayClient->postAuthenticated('installation/revoke', [], $token);
            } catch (\Throwable $exception) {
                // Continue with local cleanup even if remote revoke fails.
            }
        }

        // Clear local credentials and cached access tokens.
        $this->credentialStore->clearConnection();
        $this->accessTokenManager->clearCache();

        // Return a disconnected state snapshot.
        return [
            'success' => true,
            'data'    => [
                'connection' => $this->credentialStore->getPublicState(),
            ],
        ];
    }

    /**
     * Remove local AI credentials after gateway revocation or refresh failure.
     *
     * @since 3.0.0
     * @return void
     */
    private function invalidateLocalConnection(): void
    {
        // Clear encrypted refresh credentials and cached bearer tokens.
        $this->credentialStore->clearConnection();
        $this->accessTokenManager->clearCache();
    }

    /**
     * Split a contact name into WordPress first and last name parts.
     *
     * @since 3.0.0
     * @param string $contactName Raw contact name from the admin UI.
     * @return array{first_name:string,last_name:string} Parsed name parts.
     */
    private function parseContactName(string $contactName): array
    {
        // Normalize whitespace in the submitted label.
        $contactName = trim(preg_replace('/\s+/', ' ', $contactName));

        // Return empty parts when no name was supplied.
        if ('' === $contactName) {
            return [
                'first_name' => '',
                'last_name'  => '',
            ];
        }

        // Split on the first space so "Jane Doe" maps like theme registration fields.
        $parts = explode(' ', $contactName, 2);

        return [
            'first_name' => sanitize_text_field((string) ($parts[0] ?? '')),
            'last_name'  => sanitize_text_field((string) ($parts[1] ?? '')),
        ];
    }

    /**
     * Save a greeting name into the local site profile for Noti personalization.
     *
     * @since 3.0.0
     * @param array<string, mixed> $account Gateway account payload.
     * @param string               $fallback Raw contact name when account names are empty.
     * @return void
     */
    private function syncContactNameToSiteProfile(array $account, string $fallback): void
    {
        // Prefer explicit gateway first name, then submitted fallback, then first token of display data.
        $first = sanitize_text_field((string) ($account['first_name'] ?? ''));
        $last = sanitize_text_field((string) ($account['last_name'] ?? ''));
        $label = trim($first . ' ' . $last);

        if ('' === $label && '' !== trim($fallback)) {
            $label = sanitize_text_field(trim($fallback));
        }

        if ('' === $label) {
            return;
        }

        try {
            notifal_app(SiteProfileService::class)->saveContactName($label);
        } catch (\InvalidArgumentException $exception) {
            // Ignore invalid name payloads without blocking connection success.
        }
    }

    /**
     * Normalize a gateway response into AJAX-friendly arrays.
     *
     * @since 3.0.0
     * @param GatewayResponse $response Gateway response object.
     * @return array<string, mixed> Normalized array payload.
     */
    private function normalizeResponse(GatewayResponse $response): array
    {
        // Return success payloads unchanged.
        if ($response->isSuccess()) {
            return [
                'success' => true,
                'data'    => $response->getData(),
            ];
        }

        // Map stable codes to localized admin messages.
        $code = $response->getErrorCode();
        $message = GatewayErrorMapper::message($code, $response->getErrorMessage());

        // Compose a structured error payload for JavaScript consumers.
        $error = [
            'code'    => $code,
            'message' => $message,
            'retry_after' => $response->getRetryAfter(),
            'requires_reconnect' => GatewayErrorMapper::requiresReconnect($code),
            'quota_exceeded' => GatewayErrorMapper::isQuotaExceeded($code),
            'request_id' => $response->getRequestId(),
        ];

        // Return a failure envelope understood by local AJAX helpers.
        return [
            'success' => false,
            'error'   => $error,
        ];
    }
}
