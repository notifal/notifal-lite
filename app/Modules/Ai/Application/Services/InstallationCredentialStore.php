<?php

namespace Notifal\Modules\Ai\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\Ai\Config\Constants;

defined('ABSPATH') || exit;

/**
 * Persists installation metadata and encrypted refresh credentials locally.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class InstallationCredentialStore
{
    /**
     * Secure storage helper for refresh token encryption.
     *
     * @var SecureOptionStorage
     */
    private SecureOptionStorage $secureStorage;

    /**
     * Create the installation credential store.
     *
     * @since 3.0.0
     * @param SecureOptionStorage $secureStorage Encryption helper.
     */
    public function __construct(SecureOptionStorage $secureStorage)
    {
        // Store the encryption dependency.
        $this->secureStorage = $secureStorage;
    }

    /**
     * Return or create the persistent public site UUID.
     *
     * @since 3.0.0
     * @return string UUID v4 string.
     */
    public function getOrCreateSiteUuid(): string
    {
        // Read current installation metadata without autoloading.
        $installation = $this->getInstallation();

        // Reuse an existing UUID when already persisted.
        if (!empty($installation['site_uuid']) && wp_is_uuid($installation['site_uuid'], 4)) {
            return (string) $installation['site_uuid'];
        }

        // Generate a new UUID for first-time setup.
        $installation['site_uuid'] = wp_generate_uuid4();

        // Persist the UUID separately from encrypted credentials.
        update_option(Constants::OPTION_INSTALLATION, $installation, false);

        // Return the newly created identifier.
        return (string) $installation['site_uuid'];
    }

    /**
     * Persist a successful account connection locally.
     *
     * @since 3.0.0
     * @param array<string, mixed> $account Connected account payload from gateway.
     * @param array<string, mixed> $installation Connected installation payload.
     * @param array<string, mixed> $refresh Refresh token payload from gateway.
     * @param string $disclosureVersion Accepted disclosure version.
     * @return void
     */
    public function saveConnection(array $account, array $installation, array $refresh, string $disclosureVersion): void
    {
        // Sanitize public account metadata safe for admin display.
        $publicInstallation = [
            'site_uuid'           => $this->getOrCreateSiteUuid(),
            'installation_uuid'   => sanitize_text_field((string) ($installation['uuid'] ?? '')),
            'account_id'          => absint($account['id'] ?? 0),
            'account_email'       => sanitize_email((string) ($account['email'] ?? '')),
            'account_first_name'  => sanitize_text_field((string) ($account['first_name'] ?? '')),
            'disclosure_version'  => sanitize_text_field($disclosureVersion),
            'connected_at'        => gmdate('c'),
            'scopes'              => array_values(array_map('sanitize_text_field', (array) ($installation['scopes'] ?? []))),
            'status'              => sanitize_key((string) ($installation['status'] ?? 'active')),
        ];

        // Store public metadata in a non-autoloaded option.
        update_option(Constants::OPTION_INSTALLATION, $publicInstallation, false);

        // Encrypt the opaque refresh token before persistence.
        $encryptedRefresh = $this->secureStorage->encrypt((string) ($refresh['refresh_token'] ?? ''));

        // Store encrypted credentials separately from public metadata.
        update_option(
            Constants::OPTION_CREDENTIALS,
            [
                'refresh_token' => $encryptedRefresh,
                'expires_at'    => sanitize_text_field((string) ($refresh['expires_at'] ?? '')),
                'updated_at'    => gmdate('c'),
            ],
            false
        );

        /**
         * Fires after a Notifal AI account connection is stored locally.
         *
         * @since 3.0.0
         * @param array<string, mixed> $publicInstallation Safe installation metadata.
         */
        do_action(ActionHooks::AI_ACCOUNT_CONNECTED, $publicInstallation);
    }

    /**
     * Clear all locally stored AI connection material.
     *
     * @since 3.0.0
     * @return void
     */
    public function clearConnection(): void
    {
        // Remove encrypted refresh credentials.
        delete_option(Constants::OPTION_CREDENTIALS);

        // Remove cached access tokens immediately.
        delete_transient(Constants::TRANSIENT_ACCESS_TOKEN);

        // Preserve site UUID but remove account binding metadata.
        $installation = $this->getInstallation();
        $siteUuid = isset($installation['site_uuid']) ? (string) $installation['site_uuid'] : $this->getOrCreateSiteUuid();

        // Rewrite installation metadata to a disconnected state.
        update_option(
            Constants::OPTION_INSTALLATION,
            [
                'site_uuid' => $siteUuid,
                'status'    => 'disconnected',
            ],
            false
        );

        /**
         * Fires after the local Notifal AI connection is cleared.
         *
         * @since 3.0.0
         */
        do_action(ActionHooks::AI_ACCOUNT_DISCONNECTED);
    }

    /**
     * Determine whether the site currently has stored connection credentials.
     *
     * @since 3.0.0
     * @return bool True when refresh credentials exist locally.
     */
    public function isConnected(): bool
    {
        // Treat presence of encrypted refresh material as connected.
        $credentials = get_option(Constants::OPTION_CREDENTIALS, false);

        // Require a non-empty encrypted refresh token envelope.
        return is_array($credentials) && !empty($credentials['refresh_token']);
    }

    /**
     * Return sanitized installation metadata safe for admin localization.
     *
     * @since 3.0.0
     * @return array<string, mixed> Public installation state.
     */
    public function getPublicState(): array
    {
        // Read persisted installation metadata.
        $installation = $this->getInstallation();

        // Return a browser-safe snapshot without secrets.
        return [
            'connected'          => $this->isConnected(),
            'site_uuid'          => sanitize_text_field((string) ($installation['site_uuid'] ?? '')),
            'installation_uuid'  => sanitize_text_field((string) ($installation['installation_uuid'] ?? '')),
            'account_email'      => sanitize_email((string) ($installation['account_email'] ?? '')),
            'account_first_name' => sanitize_text_field((string) ($installation['account_first_name'] ?? '')),
            'disclosure_version' => sanitize_text_field((string) ($installation['disclosure_version'] ?? '')),
            'connected_at'       => sanitize_text_field((string) ($installation['connected_at'] ?? '')),
            'status'             => sanitize_key((string) ($installation['status'] ?? 'disconnected')),
        ];
    }

    /**
     * Decrypt and return the stored refresh token.
     *
     * @since 3.0.0
     * @return string Raw refresh token.
     */
    public function getRefreshToken(): string
    {
        // Read encrypted credential envelope.
        $credentials = get_option(Constants::OPTION_CREDENTIALS, false);

        // Reject missing or malformed credential payloads.
        if (!is_array($credentials) || empty($credentials['refresh_token'])) {
            return '';
        }

        // Decrypt and return the opaque refresh token.
        return $this->secureStorage->decrypt((string) $credentials['refresh_token']);
    }

    /**
     * Update encrypted refresh token material after gateway rotation.
     *
     * @since 3.0.0
     * @param array<string, mixed> $refresh Refresh payload from gateway.
     * @return void
     */
    public function updateRefreshToken(array $refresh): void
    {
        // Ignore incomplete refresh payloads.
        if (empty($refresh['refresh_token'])) {
            return;
        }

        // Encrypt and persist the replacement refresh token.
        update_option(
            Constants::OPTION_CREDENTIALS,
            [
                'refresh_token' => $this->secureStorage->encrypt((string) $refresh['refresh_token']),
                'expires_at'    => sanitize_text_field((string) ($refresh['expires_at'] ?? '')),
                'updated_at'    => gmdate('c'),
            ],
            false
        );
    }

    /**
     * Read installation metadata from the non-autoloaded option.
     *
     * @since 3.0.0
     * @return array<string, mixed> Installation metadata array.
     */
    private function getInstallation(): array
    {
        // Fetch the option without forcing autoload.
        $installation = get_option(Constants::OPTION_INSTALLATION, []);

        // Normalize malformed stored values to an empty array.
        return is_array($installation) ? $installation : [];
    }
}
