<?php
namespace Notifal\Modules\SetupWizard\Application\Services;
use Notifal\Modules\SetupWizard\Domain\Constants\SetupWizardOptions;
defined('ABSPATH') || exit;
/**
 * Detects true first-install eligibility for the Setup Wizard.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardEligibilityService
{
    /**
     * Legacy plugin version option from releases below 2.0.0.
     *
     * @var string
     */
    private const LEGACY_PLUGIN_VERSION_OPTION = 'notifal_plugin_version';
    /**
     * Database version option managed by DatabaseMigrationManager.
     *
     * @var string
     */
    private const DB_VERSION_OPTION = 'notifal_db_version';
    /**
     * What's New last shown version option.
     *
     * @var string
     */
    private const WHATSNEW_LAST_SHOWN_OPTION = 'notifal_whatsnew_last_shown_version';
    /**
     * Setup wizard state service.
     *
     * @var SetupWizardStateService
     */
    private SetupWizardStateService $stateService;
    /**
     * Create the eligibility service.
     *
     * @since 3.0.0
     * @param SetupWizardStateService $stateService Local wizard state service.
     */
    public function __construct(SetupWizardStateService $stateService)
    {
        // Retain the state collaborator.
        $this->stateService = $stateService;
    }
    /**
     * Record first-install markers during plugin activation.
     *
     * Callable from Maintenance before module ServiceProviders boot.
     *
     * @since 3.0.0
     * @return void
     */
    public static function markFirstInstallOnActivation(): void
    {
        // Build the service without the module container during activation.
        $service = new self(new SetupWizardStateService());
        // Persist first-run markers when this site has not seen them yet.
        $service->markFirstInstallIfNeeded();
    }
    /**
     * Record first-install markers once for fresh installs or existing sites.
     *
     * @since 3.0.0
     * @return void
     */
    public function markFirstInstallIfNeeded(): void
    {
        // Skip when this site has already seen a first install marker.
        if (get_option(SetupWizardOptions::FIRST_INSTALL, false)) {
            return;
        }
        // Detect existing usage before persisting the first-install marker.
        $isExistingSite = $this->isExistingNotifalSite();
        // Persist the first-install timestamp once for every site.
        update_option(SetupWizardOptions::FIRST_INSTALL, time(), false);
        // Existing sites keep optional wizard access without a forced redirect.
        if ($isExistingSite) {
            update_option(SetupWizardOptions::ELIGIBLE, false, false);
            return;
        }
        // Mark the site eligible for the fullscreen first-run wizard.
        update_option(SetupWizardOptions::ELIGIBLE, true, false);
        // Queue a short-lived admin redirect transient.
        set_transient(SetupWizardOptions::PENDING_REDIRECT_TRANSIENT, true, 5 * MINUTE_IN_SECONDS);
        // Seed What's New so fresh installs do not see an update popup for the install version.
        self::seedWhatsNewForFreshInstall();
    }
    /**
     * Record the install version as already shown in What's New for fresh installs.
     *
     * @since 3.0.0
     * @return void
     */
    private static function seedWhatsNewForFreshInstall(): void
    {
        // Resolve the active plugin version for the What's New tracker.
        $currentVersion = defined('NOTIFAL_VERSION') ? NOTIFAL_VERSION : '3.0.0';
        // Mark the install version as seen so update popups only appear after upgrades.
        update_option('notifal_whatsnew_last_shown_version', $currentVersion, false);
        // Store the timestamp for analytics and diagnostics.
        update_option('notifal_whatsnew_last_shown_time', time(), false);
    }
    /**
     * Return true when the site should redirect to the fullscreen wizard.
     *
     * @since 3.0.0
     * @return bool
     */
    public function shouldRedirectToWizard(): bool
    {
        // Never redirect completed sites.
        if ($this->stateService->isCompleted()) {
            return false;
        }
        // Require the pending redirect transient from first activation.
        if (!get_transient(SetupWizardOptions::PENDING_REDIRECT_TRANSIENT)) {
            return false;
        }
        // Require first-run eligibility to still be active.
        return (bool) get_option(SetupWizardOptions::ELIGIBLE, false);
    }
    /**
     * Return true when the site already used Notifal before Setup Wizard shipped.
     *
     * @since 3.0.0
     * @return bool
     */
    public function isExistingNotifalSite(): bool
    {
        // Treat legacy pre-2.0 installs as existing sites.
        if (get_option(self::LEGACY_PLUGIN_VERSION_OPTION, false)) {
            return true;
        }
        // Treat migrated databases as existing sites.
        $dbVersion = get_option(self::DB_VERSION_OPTION, '0.0.0');
        if (is_string($dbVersion) && version_compare($dbVersion, '0.0.0', '>')) {
            return true;
        }
        // Treat prior What's New views as existing usage.
        if ('' !== (string) get_option(self::WHATSNEW_LAST_SHOWN_OPTION, '')) {
            return true;
        }
        return false;
    }

    
    /**
     * Return true while first-run wizard onboarding is still active.
     *
     * @since 3.0.0
     * @return bool
     */
    public function isFirstRunWizardActive(): bool
    {
        // First-run eligibility must still be enabled locally.
        if (!(bool) get_option(SetupWizardOptions::ELIGIBLE, false)) {
            return false;
        }
        // Completed and skipped flows are no longer first-run onboarding.
        $status = $this->stateService->getStatus();
        return 'completed' !== $status && 'skipped' !== $status;
    }
    /**
     * Clear the pending redirect transient after handling it once.
     *
     * @since 3.0.0
     * @return void
     */
    public function clearPendingRedirect(): void
    {
        // Remove the short-lived redirect flag.
        delete_transient(SetupWizardOptions::PENDING_REDIRECT_TRANSIENT);
    }
}
