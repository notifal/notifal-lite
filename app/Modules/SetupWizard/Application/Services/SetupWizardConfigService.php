<?php
namespace Notifal\Modules\SetupWizard\Application\Services;
use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\Ai\Application\Services\AiIndustryOptions;
use Notifal\Modules\Ai\Application\Services\AiPublicConfigService;
use Notifal\Modules\Ai\Application\Services\InstallationCredentialStore;
use Notifal\Modules\Ai\Application\Services\LicenseConnectionProvisioner;
use Notifal\Modules\Ai\Application\Services\SiteProfileService;
use Notifal\Modules\SetupWizard\Presentation\Admin\Menu\MenuController;
use Notifal\Modules\Templates\Application\Services\HtmlBuilderUseCases;
defined('ABSPATH') || exit;
/**
 * Builds the browser boot configuration for the Setup Wizard screen.
 *
 * @since 3.0.0
 * @updated 3.0.0 Expose domain package preflight AJAX action for cache reuse.
 * @updated 3.0.0 Expose domain package force-refresh, profile sync, and replace actions.
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardConfigService
{
    /**
     * Local wizard state service dependency.
     *
     * @var SetupWizardStateService
     */
    private SetupWizardStateService $stateService;
    /**
     * Session service dependency.
     *
     * @var SetupWizardSessionService
     */
    private SetupWizardSessionService $sessionService;
    /**
     * Public AI config service dependency.
     *
     * @var AiPublicConfigService
     */
    private AiPublicConfigService $aiPublicConfigService;
    /**
     * Credential store dependency.
     *
     * @var InstallationCredentialStore
     */
    private InstallationCredentialStore $credentialStore;
    /**
     * License connection provisioner dependency.
     *
     * @var LicenseConnectionProvisioner
     */
    private LicenseConnectionProvisioner $licenseProvisioner;
    /**
     * Site profile service dependency.
     *
     * @var SiteProfileService
     */
    private SiteProfileService $siteProfileService;
    /**
     * Site analysis store dependency.
     *
     * @var SetupWizardSiteAnalysisStoreService
     */
    private SetupWizardSiteAnalysisStoreService $siteAnalysisStore;
    /**
     * Create the Setup Wizard config service.
     *
     * @since 3.0.0
     * @param SetupWizardStateService             $stateService Local wizard state service.
     * @param SetupWizardSessionService           $sessionService Session service.
     * @param AiPublicConfigService               $aiPublicConfigService Public AI config service.
     * @param InstallationCredentialStore         $credentialStore Credential store.
     * @param LicenseConnectionProvisioner        $licenseProvisioner License provisioner.
     * @param SiteProfileService                  $siteProfileService Site profile service.
     * @param SetupWizardSiteAnalysisStoreService $siteAnalysisStore Site analysis store.
     */
    public function __construct(
        SetupWizardStateService $stateService,
        SetupWizardSessionService $sessionService,
        AiPublicConfigService $aiPublicConfigService,
        InstallationCredentialStore $credentialStore,
        LicenseConnectionProvisioner $licenseProvisioner,
        SiteProfileService $siteProfileService,
        SetupWizardSiteAnalysisStoreService $siteAnalysisStore
    ) {
        // Store dependencies used to compose the boot payload.
        $this->stateService = $stateService;
        $this->sessionService = $sessionService;
        $this->aiPublicConfigService = $aiPublicConfigService;
        $this->credentialStore = $credentialStore;
        $this->licenseProvisioner = $licenseProvisioner;
        $this->siteProfileService = $siteProfileService;
        $this->siteAnalysisStore = $siteAnalysisStore;
    }

    /**
     * Build the Setup Wizard JavaScript boot configuration.
     *
     * @since 3.0.0
     * @param array<string, string> $strings Localized wizard UI strings.
     * @return array<string, mixed> Browser-safe boot config.
     */
    public function build(array $strings = []): array
    {
        // Read whether the site already has stored AI credentials locally.
        $isConnected = $this->credentialStore->isConnected();
        // Build wizard skip and auto-connect flags from license and connection state.
        $wizardPolicy = $this->licenseProvisioner->buildWizardPolicy($isConnected);
        // Read public WordPress site identity for the no-AI chat intro.
        $siteTitle = sanitize_text_field((string) get_bloginfo('name'));
        $siteTagline = sanitize_textarea_field((string) get_bloginfo('description'));
        // Resolve site logo early so the preview pane can show it before scrape runs.
        $logoUrl = $this->resolveSiteLogoUrl();
        // Compose Setup Wizard specific boot fields.
        return [
            'ajaxUrl'              => admin_url('admin-ajax.php'),
            'nonce'                => wp_create_nonce('notifal_setup_wizard'),
            'pageSlug'             => MenuController::PAGE_SLUG,
            'lastStep'             => $this->stateService->getLastStep(),
            'status'               => $this->stateService->getStatus(),
            'session'              => $this->sessionService->getPublicSnapshot(),
            'siteProfile'          => $this->siteProfileService->get(),
            'siteIdentity'         => [
                'site_title'     => $siteTitle,
                'tagline'        => $siteTagline,
                // Public homepage URL for MacBook live preview iframes (matches OnPage AI site_context.home_url).
                'home_url'       => esc_url_raw(home_url('/')),
                'site_url'       => esc_url_raw(home_url('/')),
                'logo_url'       => $logoUrl,
                'is_woocommerce' => PluginDetector::isWooCommerceActive(),
            ],
            'siteAnalysis'         => $this->siteAnalysisStore->getPublicSnapshot(),
            'useCases'             => HtmlBuilderUseCases::getOptions(),
            'industries'           => AiIndustryOptions::getOptions(),
            'wizardPolicy'         => $wizardPolicy,
            'isConnected'          => $isConnected,
            // Plugin boot state only (installed + activated), not license validity.
            'isProActive'          => PluginDetector::isNotifalProActive(),
            'isProInstalled'       => PluginDetector::isNotifalProInstalled(),
            // True only when Notifal Pro is booted with a valid active license on this site.
            'hasValidProLicense'   => (bool) ($wizardPolicy['license_active'] ?? false),
            'elementorActive'      => PluginDetector::isElementorActive(),
            // Pricing page for users who already consumed their free Pro license.
            'pricingUrl'           => esc_url_raw(Urls::PRICING),
            // Free plan keeps a single active notification during wizard setup.
            'freeNotificationLimit'=> 1,
            'dashboardUrl'         => admin_url('admin.php?page=notifal-onpage-notifications'),
            'strings'              => $strings,
            'actions'              => [
                'scan'                     => 'notifal_setup_wizard_scan',
                'domainPackagePreflight'   => 'notifal_setup_wizard_domain_package_preflight',
                'domainPackageForceRefresh'=> 'notifal_setup_wizard_domain_package_force_refresh',
                'domainPackageSyncProfile' => 'notifal_setup_wizard_domain_package_sync_profile',
                'domainPackageReplace'     => 'notifal_setup_wizard_domain_package_replace',
                'saveAnalysis'             => 'notifal_setup_wizard_save_analysis',
                'saveProfile'              => 'notifal_setup_wizard_save_profile',
                'recommendations'          => 'notifal_setup_wizard_recommendations',
                'import'                   => 'notifal_setup_wizard_import',
                'trial'                    => 'notifal_setup_wizard_trial',
                'installPro'               => 'notifal_setup_wizard_install_pro',
                'activatePro'              => 'notifal_setup_wizard_activate_pro',
                'reportProgress'           => 'notifal_setup_wizard_report_progress',
                'feedback'                 => 'notifal_setup_wizard_feedback',
                'saveStep'                 => 'notifal_setup_wizard_save_step',
                'skip'                     => 'notifal_setup_wizard_skip',
                'complete'                 => 'notifal_setup_wizard_complete',
            ],
            'ai'                   => $this->aiPublicConfigService->build([]),
        ];
    }

    /**
     * Resolve a public logo URL from theme custom logo or site icon.
     *
     * @since 3.0.0
     * @return string Escaped logo URL or empty string.
     */
    private function resolveSiteLogoUrl(): string
    {
        // Prefer the theme custom logo attachment when configured.
        $customLogoId = absint(get_theme_mod('custom_logo'));
        if ($customLogoId > 0) {
            $logoUrl = wp_get_attachment_image_url($customLogoId, 'full');
            if (is_string($logoUrl) && '' !== $logoUrl) {
                return esc_url_raw($logoUrl);
            }
        }
        // Fall back to the site icon when no custom logo exists.
        $siteIconId = absint(get_option('site_icon'));
        if ($siteIconId > 0) {
            $iconUrl = wp_get_attachment_image_url($siteIconId, 'full');
            if (is_string($iconUrl) && '' !== $iconUrl) {
                return esc_url_raw($iconUrl);
            }
        }
        return '';
    }
}
