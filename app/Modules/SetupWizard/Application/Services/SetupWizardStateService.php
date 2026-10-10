<?php
namespace Notifal\Modules\SetupWizard\Application\Services;
use Notifal\Infrastructure\WordPress\WhatsNewPopup\Domain\WhatsNewPopup;
use Notifal\Modules\SetupWizard\Domain\Constants\SetupWizardOptions;
defined('ABSPATH') || exit;
/**
 * Reads and writes local Setup Wizard completion state.
 *
 * @since 3.0.0
 * @updated 3.0.0 Never regress a completed wizard when a late step save arrives.
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardStateService
{
    /**
     * Return the current wizard status slug.
     *
     * @since 3.0.0
     * @return string Empty string when never started.
     */
    public function getStatus(): string
    {
        // Read the persisted status option.
        $status = get_option(SetupWizardOptions::STATUS, '');
        // Return a sanitized status token.
        return sanitize_key((string) $status);
    }
    /**
     * Return true when the wizard is fully completed.
     *
     * @since 3.0.0
     * @return bool
     */
    public function isCompleted(): bool
    {
        // Completed sites should never show the resume menu item.
        return 'completed' === $this->getStatus();
    }
    /**
     * Return true when the wizard was skipped but not completed.
     *
     * @since 3.0.0
     * @return bool
     */
    public function isSkipped(): bool
    {
        // Skipped sites keep the resume menu item available.
        return 'skipped' === $this->getStatus();
    }
    /**
     * Return true when the wizard still needs attention.
     *
     * @since 3.0.0
     * @return bool
     */
    public function isIncomplete(): bool
    {
        // Treat empty, in_progress, and skipped states as incomplete.
        $status = $this->getStatus();
        // Completed is the only terminal done state.
        return 'completed' !== $status;
    }
    /**
     * Return true when the Setup Wizard submenu should appear.
     *
     * @since 3.0.0
     * @return bool
     */
    public function shouldShowMenuItem(): bool
    {
        // Hide the menu once the wizard is fully completed.
        if ($this->isCompleted()) {
            return false;
        }
        // Show while eligible, in progress, or skipped.
        return $this->isIncomplete();
    }
    /**
     * Mark the wizard as in progress and store the last reached step.
     *
     * Completed is a terminal local state. A late save_step AJAX (for example the
     * fire-and-forget success step save) must not reopen the submenu after Finish.
     *
     * @since 3.0.0
     * @updated 3.0.0 Ignore step writes once the wizard is already completed.
     * @param string $step Step slug.
     * @return void
     */
    public function markStepReached(string $step): void
    {
        // Keep completed as the only terminal done state for menu eligibility.
        if ($this->isCompleted()) {
            return;
        }
        // Persist the in-progress status locally.
        update_option(SetupWizardOptions::STATUS, 'in_progress', false);
        // Remember the latest step for resume flows.
        update_option(SetupWizardOptions::LAST_STEP, sanitize_key($step), false);
    }
    /**
     * Mark the wizard as completed.
     *
     * @since 3.0.0
     * @return void
     */
    public function markCompleted(): void
    {
        // Persist the completed terminal state.
        update_option(SetupWizardOptions::STATUS, 'completed', false);
        // Clear first-run eligibility after completion.
        update_option(SetupWizardOptions::ELIGIBLE, false, false);
        // Ensure fresh installs do not see the install-version What's New popup later.
        $this->seedWhatsNewIfNeeded();
    }
    /**
     * Mark the wizard as skipped.
     *
     * @since 3.0.0
     * @return void
     */
    public function markSkipped(): void
    {
        // Persist the skipped state so the menu item remains available.
        update_option(SetupWizardOptions::STATUS, 'skipped', false);
        // Clear first-run eligibility after an explicit skip.
        update_option(SetupWizardOptions::ELIGIBLE, false, false);
        // Ensure fresh installs do not see the install-version What's New popup later.
        $this->seedWhatsNewIfNeeded();
    }
    /**
     * Return the last locally stored step slug.
     *
     * @since 3.0.0
     * @return string
     */
    public function getLastStep(): string
    {
        // Read the last persisted step option.
        $step = get_option(SetupWizardOptions::LAST_STEP, '');
        // Return a sanitized step slug.
        return sanitize_key((string) $step);
    }
    /**
     * Seed What's New when a fresh install finishes onboarding without a stored version.
     *
     * @since 3.0.0
     * @return void
     */
    private function seedWhatsNewIfNeeded(): void
    {
        // Skip when a What's New version is already stored.
        if ('' !== (string) get_option(WhatsNewPopup::LAST_SHOWN_VERSION_KEY, '')) {
            return;
        }
        // Mark the current release as seen for this fresh install.
        (new WhatsNewPopup())->markCurrentVersionAsShown();
    }
}
