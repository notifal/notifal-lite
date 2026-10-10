<?php
namespace Notifal\Modules\SetupWizard\Presentation\Admin\Routes;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardEligibilityService;
use Notifal\Modules\SetupWizard\Presentation\Admin\Menu\MenuController;
defined('ABSPATH') || exit;
/**
 * Handles first-install redirect into the fullscreen Setup Wizard.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardRedirectController
{
    /**
     * Register redirect hooks.
     *
     * @since 3.0.0
     * @return void
     */
    public static function register(): void
    {
        // Run before the legacy activation redirect so first-run onboarding wins.
        add_action('admin_init', [self::class, 'maybeRedirect'], 0);
    }
    /**
     * Redirect eligible first installs to the fullscreen wizard page.
     *
     * @since 3.0.0
     * @return void
     */
    public static function maybeRedirect(): void
    {
        // Skip non-admin, AJAX, and network admin requests.
        if (!is_admin() || wp_doing_ajax() || is_network_admin()) {
            return;
        }
        // Resolve eligibility from the module container.
        $eligibilityService = notifal_app(SetupWizardEligibilityService::class);
        // Exit when no redirect is pending.
        if (!$eligibilityService->shouldRedirectToWizard()) {
            return;
        }
        // Avoid redirect loops when already on the wizard screen.
        $currentPage = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
        if (MenuController::PAGE_SLUG === $currentPage) {
            $eligibilityService->clearPendingRedirect();
            return;
        }
        // Clear the transient before redirecting.
        $eligibilityService->clearPendingRedirect();
        // Send the administrator to the fullscreen wizard route.
        wp_safe_redirect(admin_url('admin.php?page=' . MenuController::PAGE_SLUG));
        exit;
    }
    
}
