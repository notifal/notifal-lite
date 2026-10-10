<?php
namespace Notifal\Modules\SetupWizard\Application\Services;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\WhatsNewPopup\Domain\WhatsNewPopup;
defined('ABSPATH') || exit;
/**
 * Backfills Setup Wizard state for sites upgrading to 3.0.0.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardUpgradeService
{
    /**
     * Register upgrade hooks.
     *
     * @since 3.0.0
     * @return void
     */
    public static function register(): void
    {
        // Backfill wizard markers after database migrations finish.
        add_action(ActionHooks::DATABASE_MIGRATIONS_AFTER_RUN, [self::class, 'handleDatabaseMigration'], 10, 2);
    }
    /**
     * Initialize Setup Wizard options for sites upgrading below 3.0.0.
     *
     * @since 3.0.0
     * @param string $fromVersion Previous stored database version.
     * @param string $toVersion Target database version after migration.
     * @return void
     */
    public static function handleDatabaseMigration(string $fromVersion, string $toVersion): void
    {
        // Fresh installs use the migration manager sentinel "0.0.0" before any DB version exists.
        // Prefer strict equality: version_compare() only accepts == / eq for equality, not ===.
        if ('0.0.0' === $fromVersion) {
            // Suppress What's New on brand-new installs so Setup Wizard owns first-run UX.
            if ('' === (string) get_option(WhatsNewPopup::LAST_SHOWN_VERSION_KEY, '')) {
                (new WhatsNewPopup())->markCurrentVersionAsShown();
            }
        }

        // Skip when the site was already on Setup Wizard releases.
        if (version_compare($fromVersion, '3.0.0', '>=')) {
            return;
        }

        // Skip when the target release does not include Setup Wizard.
        if (version_compare($toVersion, '3.0.0', '<')) {
            return;
        }

        // Resolve eligibility service from the module container.
        $eligibilityService = notifal_app(SetupWizardEligibilityService::class);

        // Record first-install markers without forcing a redirect for existing sites.
        $eligibilityService->markFirstInstallIfNeeded();
    }
}
