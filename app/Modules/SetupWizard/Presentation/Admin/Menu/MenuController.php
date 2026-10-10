<?php
namespace Notifal\Modules\SetupWizard\Presentation\Admin\Menu;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardStateService;
defined('ABSPATH') || exit;
/**
 * Registers the conditional Setup Wizard admin submenu.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class MenuController
{
    /**
     * Admin page slug for the fullscreen Setup Wizard.
     *
     * @var string
     */
    public const PAGE_SLUG = 'notifal-setup-wizard';
    /**
     * Register menu hooks.
     *
     * @since 3.0.0
     * @return void
     */
    public static function register(): void
    {
        // Register the conditional submenu after core Notifal menus exist.
        add_action('admin_menu', [self::class, 'addMenu'], 25);
        // Apply fullscreen body class so wizard CSS can hide WP admin chrome.
        add_filter('admin_body_class', [self::class, 'filterAdminBodyClass']);
        // Hide the front-end admin bar while the fullscreen wizard is open.
        add_filter('show_admin_bar', [self::class, 'filterShowAdminBar']);
    }
    /**
     * Add the Setup Wizard submenu when the flow is incomplete.
     *
     * @since 3.0.0
     * @return void
     */
    public static function addMenu(): void
    {
        // Resolve local wizard state from the module container.
        $stateService = notifal_app(SetupWizardStateService::class);
        // Hide the submenu once the wizard is fully completed.
        if (!$stateService->shouldShowMenuItem()) {
            return;
        }
        // Register the fullscreen wizard admin page.
        add_submenu_page(
            'notifal',
            __('Setup Wizard', 'notifal'),
            __('Setup Wizard', 'notifal'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'renderPage']
        );
    }
    /**
     * Render the fullscreen Setup Wizard shell view.
     *
     * @since 3.0.0
     * @return void
     */
    public static function renderPage(): void
    {
        // Render the wizard page through the shared view renderer.
        notifal_view('SetupWizard.Admin.setup-wizard-page');
    }
    /**
     * Determine whether the current request is the Setup Wizard screen.
     *
     * @since 3.0.0
     * @return bool True when the wizard admin page is loading.
     */
    public static function isCurrentScreen(): bool
    {
        // Fall back to the page query arg when the screen object is not ready yet.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (!is_admin() || self::PAGE_SLUG !== $page) {
            return false;
        }
        // Prefer the screen API when available for more reliable detection.
        if (!function_exists('get_current_screen')) {
            return true;
        }
        // Resolve the current admin screen when WordPress exposes it.
        $screen = get_current_screen();
        if (!$screen) {
            return true;
        }
        // Match both screen id and base for submenu pages.
        $expectedBase = 'notifal_page_' . self::PAGE_SLUG;
        return $screen->id === $expectedBase || $screen->base === $expectedBase;
    }
    /**
     * Append the fullscreen body class on the Setup Wizard screen.
     *
     * @since 3.0.0
     * @param string $classes Existing admin body classes.
     * @return string Updated body classes.
     */
    public static function filterAdminBodyClass(string $classes): string
    {
        // Only alter the body class list on the wizard page.
        if (!self::isCurrentScreen()) {
            return $classes;
        }
        // Append the fullscreen marker used by Setup Wizard CSS.
        return trim($classes . ' notifal-setup-wizard-fullscreen');
    }
    /**
     * Disable the WP admin bar on the fullscreen wizard screen.
     *
     * @since 3.0.0
     * @param bool $show Whether the admin bar should render.
     * @return bool Filtered visibility flag.
     */
    public static function filterShowAdminBar(bool $show): bool
    {
        // Keep the default behavior on every other screen.
        if (!self::isCurrentScreen()) {
            return $show;
        }
        // Hide admin bar chrome on the dedicated wizard screen.
        return false;
    }
}
