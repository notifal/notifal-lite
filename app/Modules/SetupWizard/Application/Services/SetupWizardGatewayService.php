<?php
namespace Notifal\Modules\SetupWizard\Application\Services;
use Notifal\Modules\Ai\Application\Services\AccessTokenManager;
use Notifal\Modules\Ai\Application\Services\InstallationCredentialStore;
use Notifal\Modules\Ai\Domain\GatewayErrorMapper;
use Notifal\Modules\Ai\Infrastructure\Gateway\AiGatewayClient;
defined('ABSPATH') || exit;
/**
 * Bridges Setup Wizard actions to hosted gateway wizard routes.
 *
 * @since 3.0.0
 * @updated 3.0.0 Add S2S domain-package lookup for Setup Wizard preflight.
 * @updated 3.0.0 Add force-refresh, profile sync, and package replace for reuse UX.
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardGatewayService
{
    /**
     * Gateway HTTP client dependency.
     *
     * @var AiGatewayClient
     */
    private AiGatewayClient $gatewayClient;
    /**
     * Access token manager dependency.
     *
     * @var AccessTokenManager
     */
    private AccessTokenManager $accessTokenManager;
    /**
     * Credential store dependency.
     *
     * @var InstallationCredentialStore
     */
    private InstallationCredentialStore $credentialStore;
    /**
     * Request guard dependency.
     *
     * @var SetupWizardRequestGuard
     */
    private SetupWizardRequestGuard $requestGuard;
    /**
     * Create the Setup Wizard gateway bridge service.
     *
     * @since 3.0.0
     * @param AiGatewayClient             $gatewayClient      Gateway HTTP client.
     * @param AccessTokenManager          $accessTokenManager Access token manager.
     * @param InstallationCredentialStore $credentialStore    Credential store.
     * @param SetupWizardRequestGuard     $requestGuard       Request guard.
     */
    public function __construct(
        AiGatewayClient $gatewayClient,
        AccessTokenManager $accessTokenManager,
        InstallationCredentialStore $credentialStore,
        SetupWizardRequestGuard $requestGuard
    ) {
        // Store dependencies used by authenticated wizard routes.
        $this->gatewayClient = $gatewayClient;
        $this->accessTokenManager = $accessTokenManager;
        $this->credentialStore = $credentialStore;
        $this->requestGuard = $requestGuard;
    }
    /**
     * Look up a fresh domain analysis package without starting AI work.
     *
     * Uses the public S2S GET route so preflight works before account connect.
     * Cache hits never invoke GapGPT on the gateway (service hard guard).
     *
     * @since 3.0.0
     * @updated 3.0.0 Request full notification bodies when importing from plugin.
     * @param string $domain            Normalized domain host or home URL.
     * @param bool   $includeTemplates  Whether to request full OnPage bodies.
     * @return array{success: bool, data?: array<string, mixed>, error?: array<string, mixed>}
     */
    public function lookupDomainPackage(string $domain, bool $includeTemplates = true): array
    {
        // Bound and sanitize the domain query value before the outbound call.
        $domain = sanitize_text_field($domain);
        if ('' === $domain) {
            return $this->failure('invalid_domain');
        }

        // Build the lookup query; plugin import always needs full template bodies.
        $query = 'wizard/domain-package?domain=' . rawurlencode($domain);
        if ($includeTemplates) {
            $query .= '&include_templates=1';
        }

        // GET the public package contract; 404/410 map to soft misses for the wizard.
        $response = $this->gatewayClient->get($query, 20);

        return $this->normalizeResponse($response);
    }

    /**
     * Grant one plugin force-refresh soft-expire for the domain package.
     *
     * @since 3.0.0
     * @param string $domain Normalized domain host or home URL.
     * @return array{success: bool, data?: array<string, mixed>, error?: array<string, mixed>}
     */
    public function forceRefreshDomainPackage(string $domain): array
    {
        // Bound and sanitize the domain before the outbound call.
        $domain = sanitize_text_field($domain);
        if ('' === $domain) {
            return $this->failure('invalid_domain');
        }

        // POST the force-refresh grant without a browser Origin header.
        $response = $this->gatewayClient->post(
            'wizard/domain-package/force-refresh',
            ['domain' => $domain]
        );

        return $this->normalizeResponse($response);
    }

    /**
     * Merge editable profile fields into the hosted package analysis blob.
     *
     * @since 3.0.0
     * @param string               $packageId Package UUID.
     * @param array<string, mixed> $fields    Editable profile fields.
     * @return array{success: bool, data?: array<string, mixed>, error?: array<string, mixed>}
     */
    public function updateDomainPackageProfile(string $packageId, array $fields): array
    {
        // Require a canonical package UUID before the outbound call.
        $packageId = strtolower(sanitize_text_field($packageId));
        if (!wp_is_uuid($packageId, 4)) {
            return $this->failure('invalid_request');
        }

        // Build the dual-sync payload with only editable profile keys.
        $body = [
            'include_templates' => 1,
        ];
        if (array_key_exists('business_description', $fields)) {
            $body['business_description'] = sanitize_textarea_field(
                (string) $fields['business_description']
            );
        }
        if (array_key_exists('primary_color', $fields)) {
            $hex = sanitize_hex_color((string) $fields['primary_color']);
            if ($hex) {
                $body['primary_color'] = $hex;
            }
        }
        if (array_key_exists('brand_colors', $fields) && is_array($fields['brand_colors'])) {
            $palette = [];
            foreach ($fields['brand_colors'] as $color) {
                $hex = sanitize_hex_color((string) $color);
                if ($hex && !in_array($hex, $palette, true)) {
                    $palette[] = $hex;
                }
                if (5 <= count($palette)) {
                    break;
                }
            }
            $body['brand_colors'] = $palette;
        }

        // POST the profile merge to the gateway package row.
        $response = $this->gatewayClient->post(
            'wizard/domain-package/' . rawurlencode($packageId) . '/profile',
            $body
        );

        return $this->normalizeResponse($response);
    }

    /**
     * Replace hosted package analysis and notifications after a force rebuild.
     *
     * @since 3.0.0
     * @param string               $domain        Normalized domain host.
     * @param array<string, mixed> $analysis      Analysis snapshot.
     * @param array<int, mixed>    $notifications Importable OnPage documents.
     * @return array{success: bool, data?: array<string, mixed>, error?: array<string, mixed>}
     */
    public function replaceDomainPackage(string $domain, array $analysis, array $notifications): array
    {
        // Bound and sanitize the domain before the outbound call.
        $domain = sanitize_text_field($domain);
        if ('' === $domain || empty($notifications)) {
            return $this->failure('invalid_request');
        }

        // POST the no-AI replace so live GET shows the rebuilt package.
        $response = $this->gatewayClient->post(
            'wizard/domain-package/replace',
            [
                'domain'        => $domain,
                'analysis'      => $analysis,
                'notifications' => array_values($notifications),
            ]
        );

        return $this->normalizeResponse($response);
    }

    /**
     * Upsert Setup Wizard progress for the connected installation domain.
     *
     * @since 3.0.0
     * @param array<string, mixed> $payload Progress payload.
     * @return array{success: bool, data?: array<string, mixed>, error?: array<string, mixed>}
     */
    public function reportProgress(array $payload): array
    {
        // Require a connected installation before reporting remote progress.
        if (!$this->credentialStore->isConnected()) {
            return $this->failure('not_connected');
        }
        // Sanitize progress fields before the outbound gateway call.
        $payload = $this->requestGuard->sanitizeProgressPayload($payload);
        try {
            // Resolve a valid bearer token for authenticated wizard routes.
            $token = $this->accessTokenManager->getValidToken();
        } catch (\Throwable $exception) {
            return $this->failure(sanitize_key($exception->getMessage()));
        }
        // Submit the progress upsert to the hosted gateway over HTTPS.
        $response = $this->gatewayClient->postAuthenticated('wizard/progress', $payload, $token);
        return $this->normalizeResponse($response);
    }
    /**
     * Submit Setup Wizard experience feedback through the hosted gateway.
     *
     * @since 3.0.0
     * @param array<string, mixed> $payload Feedback payload.
     * @return array{success: bool, data?: array<string, mixed>, error?: array<string, mixed>}
     */
    public function submitFeedback(array $payload): array
    {
        // Require a connected installation before remote feedback storage.
        if (!$this->credentialStore->isConnected()) {
            return $this->failure('not_connected');
        }
        // Sanitize feedback fields before the outbound gateway call.
        $payload = $this->requestGuard->sanitizeFeedbackPayload($payload);
        if (null === $payload) {
            return $this->failure('invalid_request');
        }
        try {
            // Resolve a valid bearer token for authenticated wizard routes.
            $token = $this->accessTokenManager->getValidToken();
        } catch (\Throwable $exception) {
            return $this->failure(sanitize_key($exception->getMessage()));
        }
        // Submit the experience rating to the hosted gateway over HTTPS.
        $response = $this->gatewayClient->postAuthenticated('wizard/feedback', $payload, $token);
        return $this->normalizeResponse($response);
    }
    /**
     * Provision a Setup Wizard trial license through the hosted gateway.
     *
     * @since 3.0.0
     * @return array{success: bool, data?: array<string, mixed>, error?: array<string, mixed>}
     */
    public function provisionTrial(): array
    {
        // Require account connection before creating a trial license.
        if (!$this->credentialStore->isConnected()) {
            return $this->failure('not_connected');
        }
        try {
            // Resolve a valid bearer token for trial provisioning.
            $token = $this->accessTokenManager->getValidToken();
        } catch (\Throwable $exception) {
            return $this->failure(sanitize_key($exception->getMessage()));
        }
        // Request trial creation from the hosted gateway over HTTPS.
        $response = $this->gatewayClient->postAuthenticated('wizard/trial', [], $token);
        return $this->normalizeResponse($response);
    }
    /**
     * Normalize one gateway response for AJAX consumers.
     *
     * @since 3.0.0
     * @param \Notifal\Modules\Ai\Infrastructure\Gateway\GatewayResponse $response Gateway response.
     * @return array{success: bool, data?: array<string, mixed>, error?: array<string, mixed>}
     */
    private function normalizeResponse($response): array
    {
        if ($response->isSuccess()) {
            return [
                'success' => true,
                'data'    => $response->getData(),
            ];
        }
        return $this->failure($response->getErrorCode(), $response->getErrorMessage());
    }
    /**
     * Build a standardized failure envelope.
     *
     * @since 3.0.0
     * @param string $code Stable error code.
     * @param string $message Optional raw gateway message.
     * @return array{success: false, error: array<string, mixed>}
     */
    private function failure(string $code, string $message = ''): array
    {
        return [
            'success' => false,
            'error'   => [
                'code'    => sanitize_key($code),
                'message' => GatewayErrorMapper::message(sanitize_key($code), $message),
            ],
        ];
    }
}
