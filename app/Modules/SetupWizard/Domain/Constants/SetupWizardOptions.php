<?php
namespace Notifal\Modules\SetupWizard\Domain\Constants;
defined('ABSPATH') || exit;
/**
 * WordPress option and transient keys for the Setup Wizard.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
final class SetupWizardOptions
{
    /**
     * Set once on the first-ever plugin activation timestamp.
     *
     * @var string
     */
    public const FIRST_INSTALL = 'notifal_setup_wizard_first_install';
    /**
     * True while the site is eligible for the first-run fullscreen wizard.
     *
     * @var string
     */
    public const ELIGIBLE = 'notifal_setup_wizard_eligible';
    /**
     * Current wizard status: in_progress, completed, or skipped.
     *
     * @var string
     */
    public const STATUS = 'notifal_setup_wizard_status';
    /**
     * Last step reached locally before completion or skip.
     *
     * @var string
     */
    public const LAST_STEP = 'notifal_setup_wizard_last_step';
    /**
     * Short-lived redirect flag after first activation.
     *
     * @var string
     */
    public const PENDING_REDIRECT_TRANSIENT = 'notifal_pending_setup_wizard_redirect';
    /**
     * In-progress wizard selections stored between steps.
     *
     * @var string
     */
    public const SESSION = 'notifal_setup_wizard_session';
    /**
     * Transient prefix for cached home page scrape snapshots.
     *
     * @var string
     */
    public const SCRAPE_CACHE_TRANSIENT = 'notifal_setup_wizard_scrape_';
    /**
     * Persisted site scrape and analysis snapshot for wizard reuse.
     *
     * @var string
     */
    public const SITE_ANALYSIS = 'notifal_setup_wizard_site_analysis';
}
