<?php

namespace Notifal\Infrastructure\WordPress\Admin\Modules\Controllers;

use Notifal\Core\Modules\ModuleRegistry;
use Notifal\Domain\Settings\Services\SettingsService;
use Notifal\Shared\AdminUI\Toast\ToastRenderer;

defined('ABSPATH') || exit;

/**
 * Registers and renders the Addons admin page under the Notifal menu.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ModulesMenuController
{
    /**
     * Admin page slug.
     *
     * @var string
     */
    private const PAGE_SLUG = 'notifal-addons';

    /**
     * Register WordPress hooks.
     *
     * @return void
     * @since 3.0.0
     */
    public static function register(): void
    {
        // After Campaigns (priority 23) so Addons sits just below it.
        add_action('admin_menu', [self::class, 'registerAdminMenu'], 24);
    }

    /**
     * Add the Addons submenu page.
     *
     * @return void
     * @since 3.0.0
     */
    public static function registerAdminMenu(): void
    {
        add_submenu_page(
            'notifal',
            __('Addons', 'notifal'),
            __('Addons', 'notifal'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'renderAddonsPage']
        );
    }

    /**
     * Render the Addons admin screen.
     *
     * @return void
     * @since 3.0.0
     */
    public static function renderAddonsPage(): void
    {
        // Block users without admin access.
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'notifal'));
        }

        // Resolve dependencies from the plugin container.
        $settingsService = notifal_app(SettingsService::class);

        // Build addon cards with current enabled state.
        $modules = ModuleRegistry::getModules();
        $moduleStates = [];

        foreach ($modules as $moduleKey => $module) {
            $moduleStates[$moduleKey] = ModuleRegistry::isModuleEnabled($module, $settingsService);
        }

        // Pass data to the view layer only.
        // Sticky menu is rendered inside the view via ADMIN_PAGE_CONTENT_BEFORE.
        $viewData = [
            'page_slug'     => self::PAGE_SLUG,
            'modules'       => $modules,
            'module_states' => $moduleStates,
        ];

        // Include the addons page template.
        extract($viewData, EXTR_SKIP);
        include __DIR__ . '/../Views/modules-page.php';

        // Ensure toast container exists for AJAX feedback and post-reload messages.
        ToastRenderer::renderGlobalContainer();
    }
}
