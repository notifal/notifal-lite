<?php

namespace Notifal\Modules\Ai\Domain;

use Notifal\Modules\Ai\Application\Services\InstallationCredentialStore;

defined('ABSPATH') || exit;

/**
 * Builds the canonical site identity payload required by the AI gateway.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SiteIdentityBuilder
{
    /**
     * Credential store used to resolve the persistent installation UUID.
     *
     * @var InstallationCredentialStore
     */
    private InstallationCredentialStore $credentialStore;

    /**
     * Create the site identity builder.
     *
     * @since 3.0.0
     * @param InstallationCredentialStore $credentialStore Installation metadata store.
     */
    public function __construct(InstallationCredentialStore $credentialStore)
    {
        // Store the credential repository dependency.
        $this->credentialStore = $credentialStore;
    }

    /**
     * Build the gateway-compatible site identity array.
     *
     * @since 3.0.0
     * @return array<string, mixed> Normalized site identity.
     */
    public function build(): array
    {
        // Resolve the persistent installation UUID for this WordPress site.
        $siteUuid = $this->credentialStore->getOrCreateSiteUuid();

        // Read canonical WordPress URLs without query strings.
        $homeUrl = untrailingslashit(home_url());
        $siteUrl = untrailingslashit(site_url());

        // Compose the identity payload expected by the gateway validator.
        $identity = [
            'site_uuid'   => $siteUuid,
            'home_url'    => $homeUrl,
            'site_url'    => $siteUrl,
            'environment' => $this->resolveEnvironment(),
        ];

        // Attach multisite identifiers when the installation runs in a network.
        if (is_multisite()) {
            $identity['network_id'] = get_current_network_id();
            $identity['blog_id']    = get_current_blog_id();
        }

        // Return the complete identity contract.
        return $identity;
    }

    /**
     * Resolve the environment label sent to the gateway.
     *
     * @since 3.0.0
     * @return string One of production, staging, development, or local.
     */
    private function resolveEnvironment(): string
    {
        // Prefer WordPress core environment typing when available.
        if (function_exists('wp_get_environment_type')) {
            $type = wp_get_environment_type();

            // Map WordPress environment labels to gateway values.
            if (in_array($type, ['local', 'development', 'staging', 'production'], true)) {
                return $type;
            }
        }

        // Infer local development from common host patterns.
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        $host = is_string($host) ? strtolower($host) : '';

        // Treat localhost-like hosts as local environments.
        if (
            $host === 'localhost'
            || substr($host, -6) === '.local'
            || substr($host, -5) === '.test'
            || filter_var($host, FILTER_VALIDATE_IP)
        ) {
            return 'local';
        }

        // Infer staging from common subdomain or path markers.
        if (strpos($host, 'staging') !== false || strpos($host, 'stage') !== false) {
            return 'staging';
        }

        // Default to production for publicly reachable hosts.
        return 'production';
    }
}
