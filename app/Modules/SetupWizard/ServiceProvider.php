<?php
namespace Notifal\Modules\SetupWizard;
use Notifal\Core\Foundation\AbstractServiceProvider;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardBrandColorExtractor;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardBrandColorNormalizer;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardConfigService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardDomainHostNormalizer;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardDomainPackageService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardEligibilityService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardGatewayService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardHeuristicAnalysisService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardNotificationImportService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardPackageUrlValidator;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardProInstallDestinationRecovery;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardProInstallFailureDescriptor;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardProInstallFilesystemGate;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardProInstallService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardProLicenseConnectService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardRecommendationService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardRequestGuard;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardSessionService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardSiteAnalysisStoreService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardSiteScrapeService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardStateService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardUpgradeService;
use Notifal\Modules\SetupWizard\Presentation\Admin\Assets\SetupWizardAssets;
use Notifal\Modules\SetupWizard\Presentation\Admin\Controllers\Ajax\SetupWizardAjaxController;
use Notifal\Modules\SetupWizard\Presentation\Admin\Menu\MenuController;
use Notifal\Modules\SetupWizard\Presentation\Admin\Routes\SetupWizardRedirectController;
defined('ABSPATH') || exit;
/**
 * Service provider for the Setup Wizard onboarding module.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ServiceProvider extends AbstractServiceProvider
{
    /**
     * Services registered by the Setup Wizard module.
     *
     * @var array<int, string>
     */
    protected static array $services = [
        SetupWizardStateService::class,
        SetupWizardEligibilityService::class,
        SetupWizardSessionService::class,
        SetupWizardSiteScrapeService::class,
        SetupWizardBrandColorNormalizer::class,
        SetupWizardBrandColorExtractor::class,
        SetupWizardSiteAnalysisStoreService::class,
        SetupWizardHeuristicAnalysisService::class,
        SetupWizardRequestGuard::class,
        SetupWizardDomainHostNormalizer::class,
        SetupWizardDomainPackageService::class,
        SetupWizardPackageUrlValidator::class,
        SetupWizardProInstallFailureDescriptor::class,
        SetupWizardProInstallFilesystemGate::class,
        SetupWizardProInstallDestinationRecovery::class,
        SetupWizardGatewayService::class,
        SetupWizardRecommendationService::class,
        SetupWizardNotificationImportService::class,
        SetupWizardProInstallService::class,
        SetupWizardProLicenseConnectService::class,
        SetupWizardConfigService::class,
        SetupWizardAssets::class,
        MenuController::class,
        SetupWizardRedirectController::class,
    ];
    /**
     * Filter hook allowing external modification of Setup Wizard services.
     *
     * @var string
     */
    protected const FILTER_HOOK = FilterHooks::SETUP_WIZARD_SERVICES;
    /**
     * Boot AJAX controllers and activation listeners.
     *
     * @since 3.0.0
     * @return void
     */
    public function boot(): void
    {
        // Register local AJAX boundaries for the full wizard orchestration flow.
        SetupWizardAjaxController::register();
        // Backfill wizard state for sites upgrading below 3.0.0.
        SetupWizardUpgradeService::register();
        // Mark first-install eligibility during the shared activation hook.
        add_action(ActionHooks::PLUGIN_ACTIVATED, [self::class, 'handlePluginActivated']);
    }
    /**
     * Handle shared plugin activation for Setup Wizard eligibility.
     *
     * @since 3.0.0
     * @return void
     */
    public static function handlePluginActivated(): void
    {
        // Resolve eligibility service from the module container.
        $eligibilityService = notifal_app(SetupWizardEligibilityService::class);
        // Record first-install markers only once.
        $eligibilityService->markFirstInstallIfNeeded();
    }
}
