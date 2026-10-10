<?php

namespace Notifal\Modules\Ai\Application\Services;

use Notifal\Modules\Ai\Config\Constants;
use Notifal\Modules\Ai\Domain\SiteIdentityBuilder;
use Notifal\Modules\Ai\Infrastructure\Gateway\AiGatewayClient;
use Notifal\Modules\Ai\Infrastructure\Gateway\GatewayResponse;

defined('ABSPATH') || exit;

/**
 * Provisions hosted AI credentials from an active Notifal Pro license.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class LicenseConnectionProvisioner
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
     * Account connection service dependency.
     *
     * @var AccountConnectionService
     */
    private AccountConnectionService $connectionService;

    /**
     * Create the license connection provisioner.
     *
     * @since 3.0.0
     * @param AiGatewayClient $gatewayClient Gateway HTTP client.
     * @param InstallationCredentialStore $credentialStore Credential store.
     * @param AccessTokenManager $accessTokenManager Access token manager.
     * @param SiteIdentityBuilder $siteIdentityBuilder Site identity builder.
     * @param AccountConnectionService $connectionService Account connection service.
     */
    public function __construct(
        AiGatewayClient $gatewayClient,
        InstallationCredentialStore $credentialStore,
        AccessTokenManager $accessTokenManager,
        SiteIdentityBuilder $siteIdentityBuilder,
        AccountConnectionService $connectionService
    ) {
        // Store dependencies used by license-backed provisioning.
        $this->gatewayClient = $gatewayClient;
        $this->credentialStore = $credentialStore;
        $this->accessTokenManager = $accessTokenManager;
        $this->siteIdentityBuilder = $siteIdentityBuilder;
        $this->connectionService = $connectionService;
    }

    /**
     * Determine whether the current site can auto-connect from Pro license data.
     *
     * @since 3.0.0
     * @return bool True when Pro is active and license proof exists locally.
     */
    public function isProLicenseActive(): bool
    {
        // Require the Pro plugin to be active and licensed via the centralized checker.
        return $this->isNotifalProBooted() && is_notifal_pro_active();
    }

    /**
     * Build wizard UI flags for skipping or auto-provisioning the Connect step.
     *
     * @since 3.0.0
     * @param bool $isConnected Whether the site has a valid local or gateway-verified connection.
     * @return array<string, bool> Wizard connection policy flags.
     */
    public function buildWizardPolicy(bool $isConnected): array
    {
        // Determine whether Pro license data can silently establish credentials.
        $canAutoConnect = $this->canAutoConnectFromLicense();

        // Skip manual Connect only when linked or Pro can auto-connect with a valid license.
        return [
            'skip_connect_step'    => $isConnected || $canAutoConnect,
            'license_auto_connect' => $canAutoConnect && !$isConnected,
            'license_active'     => $this->isProLicenseActive(),
        ];
    }

    /**
     * Determine whether the current site can auto-connect from Pro license data.
     *
     * @since 3.0.0
     * @return bool True when Pro is active and license proof exists locally.
     */
    public function canAutoConnectFromLicense(): bool
    {
        // Reject auto-connect when Pro is not booted or the license is not valid.
        if (!$this->isProLicenseActive()) {
            return false;
        }

        // Require license metadata helpers from Notifal Pro.
        if (!function_exists('notifal_pro_get_license_info')) {
            return false;
        }

        // Read the locally stored Pro license snapshot.
        $license = notifal_pro_get_license_info();
        $licenseKey = sanitize_text_field((string) ($license['license_key'] ?? ''));
        $domain = $this->resolveLicenseDomain((string) ($license['domain'] ?? ''));

        // Allow auto-connect only when both proof fields exist.
        return '' !== $licenseKey && '' !== $domain;
    }

    /**
     * Determine whether the Notifal Pro plugin is active in WordPress.
     *
     * @since 3.0.0
     * @return bool True when the Pro plugin is loaded and marked active.
     */
    private function isNotifalProBooted(): bool
    {
        // Load plugin API helpers when they are not already available.
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Require Pro helpers and an active plugin registration.
        return function_exists('is_notifal_pro_active')
            && is_plugin_active('notifal-pro/notifal-pro.php');
    }

    /**
     * Provision hosted AI credentials from the active Pro license when needed.
     *
     * @since 3.0.0
     * @return array<string, mixed> Normalized provision result.
     */
    public function provisionFromActiveLicense(): array
    {
        // Reject provisioning when Pro auto-connect is unavailable.
        if (!$this->canAutoConnectFromLicense()) {
            return [
                'success' => false,
                'error'   => [
                    'code'    => 'license_required',
                    'message' => __('An active Notifal Pro license is required for automatic AI connection.', 'notifal'),
                ],
            ];
        }

        // Read the license proof from Notifal Pro.
        $license = notifal_pro_get_license_info();
        $licenseKey = sanitize_text_field((string) ($license['license_key'] ?? ''));
        $domain = $this->resolveLicenseDomain((string) ($license['domain'] ?? ''));

        // Build the trusted server-to-server license connect payload.
        $body = [
            'license_key'        => $licenseKey,
            'domain'             => $domain,
            'site'               => $this->siteIdentityBuilder->build(),
            'disclosure_version' => $this->connectionService->getDisclosureVersion(),
            'scopes'             => Constants::DEFAULT_SCOPES,
            'disclosure_source'  => 'notifal_pro_license',
        ];

        // Execute the license-backed connection route on the gateway.
        $response = $this->gatewayClient->post('auth/license/connect', $body);

        // Convert gateway failures into UI-friendly arrays.
        return $this->normalizeProvisionResponse($response);
    }

    /**
     * Normalize a gateway response and persist credentials on success.
     *
     * @since 3.0.0
     * @param GatewayResponse $response Gateway response object.
     * @return array<string, mixed> Normalized array payload.
     */
    private function normalizeProvisionResponse(GatewayResponse $response): array
    {
        // Return structured failures without storing credentials.
        if (!$response->isSuccess()) {
            return [
                'success' => false,
                'error'   => [
                    'code'    => sanitize_key($response->getErrorCode()),
                    'message' => sanitize_text_field($response->getErrorMessage()),
                ],
            ];
        }

        // Extract credential payloads from the gateway response.
        $data = $response->getData();
        $account = is_array($data['account'] ?? null) ? $data['account'] : [];
        $installation = is_array($data['installation'] ?? null) ? $data['installation'] : [];
        $access = is_array($data['access'] ?? null) ? $data['access'] : [];
        $refresh = is_array($data['refresh'] ?? null) ? $data['refresh'] : [];

        // Persist encrypted refresh credentials and public installation metadata.
        $this->credentialStore->saveConnection(
            $account,
            $installation,
            $refresh,
            $this->connectionService->getDisclosureVersion()
        );

        // Cache the short-lived access token for immediate authenticated calls.
        $this->accessTokenManager->storeAccessToken($access);

        // Persist greeting name from the license owner account when available.
        $first = sanitize_text_field((string) ($account['first_name'] ?? ''));
        $last = sanitize_text_field((string) ($account['last_name'] ?? ''));
        $label = trim($first . ' ' . $last);
        if ('' !== $label) {
            try {
                notifal_app(SiteProfileService::class)->saveContactName($label);
            } catch (\InvalidArgumentException $exception) {
                // Ignore invalid labels without blocking license provisioning.
            }
        }

        // Return a browser-safe connected snapshot.
        return [
            'success' => true,
            'data'    => [
                'connection' => $this->credentialStore->getPublicState(),
            ],
        ];
    }

    /**
     * Normalize a stored Pro license domain to a canonical host.
     *
     * @since 3.0.0
     * @param string $domain Stored license domain or URL.
     * @return string Canonical hostname.
     */
    private function resolveLicenseDomain(string $domain): string
    {
        // Trim and lowercase the stored domain value.
        $domain = strtolower(trim($domain));

        // Return early when no domain was stored.
        if ('' === $domain) {
            return '';
        }

        // Parse bare domains and full URLs consistently.
        $parsed = wp_parse_url(strpos($domain, '://') === false ? 'https://' . $domain : $domain);
        if (!is_array($parsed) || empty($parsed['host'])) {
            return preg_replace('/^www\./', '', $domain);
        }

        // Remove a conventional www alias before returning the host.
        return preg_replace('/^www\./', '', strtolower((string) $parsed['host']));
    }
}
