<?php

namespace Notifal\Modules\Ai\Application\Services;

use Notifal\Core\Support\Helpers\UrlHelper;
use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Security\NonceManager;
use Notifal\Modules\Ai\Config\AiCharacter;
use Notifal\Modules\Ai\Config\Constants;

defined('ABSPATH') || exit;

/**
 * Builds browser-safe AI configuration localized into admin scripts.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class AiPublicConfigService
{
    /**
     * Account connection service dependency.
     *
     * @var AccountConnectionService
     */
    private AccountConnectionService $connectionService;

    /**
     * Credential store dependency.
     *
     * @var InstallationCredentialStore
     */
    private InstallationCredentialStore $credentialStore;

    /**
     * Site profile service dependency.
     *
     * @var SiteProfileService
     */
    private SiteProfileService $siteProfileService;

    /**
     * License-backed connection provisioner dependency.
     *
     * @var LicenseConnectionProvisioner
     */
    private LicenseConnectionProvisioner $licenseProvisioner;

    /**
     * Create the public config service.
     *
     * @since 3.0.0
     * @param AccountConnectionService $connectionService Connection service.
     * @param InstallationCredentialStore $credentialStore Credential store.
     * @param SiteProfileService $siteProfileService Site profile service.
     * @param LicenseConnectionProvisioner $licenseProvisioner License provisioner.
     */
    public function __construct(
        AccountConnectionService $connectionService,
        InstallationCredentialStore $credentialStore,
        SiteProfileService $siteProfileService,
        LicenseConnectionProvisioner $licenseProvisioner
    ) {
        // Store dependencies used to compose public config.
        $this->connectionService = $connectionService;
        $this->credentialStore = $credentialStore;
        $this->siteProfileService = $siteProfileService;
        $this->licenseProvisioner = $licenseProvisioner;
    }

    /**
     * Build the configuration object exposed to admin JavaScript.
     *
     * @since 3.0.0
     * @param array<string, string> $strings Localized UI strings.
     * @return array<string, mixed> Public AI config array.
     */
    public function build(array $strings = []): array
    {
        // Prefill the connection email from the sanitized admin email option.
        $prefillEmail = sanitize_email((string) get_option('admin_email', ''));

        // Privacy policy URL linked from AI disclosure copy.
        $privacyUrl = Urls::PRIVACY_POLICY;

        // Read whether the site already has stored AI credentials locally.
        $isConnected = $this->credentialStore->isConnected();

        // Build wizard skip and auto-connect flags from license and connection state.
        $wizardPolicy = $this->licenseProvisioner->buildWizardPolicy($isConnected);

        // Compose the browser-safe configuration contract.
        $config = [
            'ajax_url'           => UrlHelper::baseAjax(),
            'nonce'              => NonceManager::create(Constants::AJAX_NONCE_ACTION),
            'disclosure_version' => $this->connectionService->getDisclosureVersion(),
            'privacy_url'        => esc_url($privacyUrl),
            'prefill_email'      => $prefillEmail,
            'skip_connect_step'  => (bool) ($wizardPolicy['skip_connect_step'] ?? false),
            'connection'         => $this->credentialStore->getPublicState(),
            'license'            => [
                'active'       => (bool) ($wizardPolicy['license_active'] ?? false),
                'domain'       => '',
                'email'        => '',
                'auto_connect' => (bool) ($wizardPolicy['license_auto_connect'] ?? false),
            ],
            'site_profile'       => $this->siteProfileService->get(),
            'character'          => AiCharacter::getPublicMeta(),
            'prompt_version'     => Constants::PROMPT_VERSION,
            'schema_version'     => Constants::SCHEMA_VERSION,
            'actions'            => [
                'request_code'       => 'notifal_ai_request_code',
                'verify_code'        => 'notifal_ai_verify_code',
                'connection_status'  => 'notifal_ai_connection_status',
                'disconnect'         => 'notifal_ai_disconnect',
                'provision_license'  => 'notifal_ai_provision_from_license',
                'usage'              => 'notifal_ai_usage',
                'readiness'          => 'notifal_ai_readiness',
                'save_site_profile'  => 'notifal_ai_save_site_profile',
                'start_generation'   => 'notifal_ai_start_generation',
                'generation_status'  => 'notifal_ai_generation_status',
                'cancel_generation'  => 'notifal_ai_cancel_generation',
                'generation_feedback'=> 'notifal_ai_generation_feedback',
                'sanitize_html'      => 'notifal_ai_sanitize_html',
                'import_onpage'      => 'notifal_ai_import_onpage',
            ],
            'strings'            => $strings,
        ];

        /**
         * Filter the public AI configuration localized into admin scripts.
         *
         * @since 3.0.0
         * @param array<string, mixed> $config Public config array.
         */
        return (array) apply_filters(FilterHooks::AI_PUBLIC_CONFIG, $config);
    }
}
