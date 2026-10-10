<?php

namespace Notifal\Infrastructure\WordPress\Admin\Menu;

use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;

defined('ABSPATH') || exit;

/**
 * Class MenuServiceProvider
 * Handles the registration of Notifal's main admin menu and submenus.
 *
 * @since 2.0.0
 * @package Notifal\Infrastructure\WordPress\Admin\Menu
 * @author Hossein <hossein@notifal.com>
 */
class MenuServiceProvider
{
    /**
     * Register menu-related WordPress hooks.
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        // Register the top-level Notifal admin menu and module submenu hooks.
        add_action('admin_menu', [self::class, 'registerMenu']);
        // Offer the Pro upgrade CTA when Notifal Pro is not installed.
        add_action('admin_menu', [self::class, 'addUpgradeMenuIfNeeded'], 30);
        // Remove WordPress duplicate submenu that points at the parent slug.
        add_action('admin_menu', [self::class, 'cleanupDuplicateSubmenu'], 999);
    }

    /**
     * Register main and sub-menu pages.
     *
     * @return void
     * @since 2.0.0
     */
    public static function registerMenu(): void
    {
        do_action(ActionHooks::ADMIN_MENU_BEFORE);

        add_menu_page(
            __('Notifal', 'notifal'),
            __('Notifal', 'notifal'),
            'manage_options',
            'notifal',
            '',
            'dashicons-admin-generic',
            5
        );

        // Submenus will be added by modules dynamically
        do_action(ActionHooks::ADMIN_MAIN_MENU_AFTER);
    }

    /**
     * Add upgrade menu item if Notifal Pro is not installed.
     *
     * @return void
     * @since 2.0.0
     */
    public static function addUpgradeMenuIfNeeded(): void
    {
        // Check if Notifal Pro is installed
        if (!PluginDetector::isNotifalProInstalled()) {
            add_submenu_page(
                'notifal',
                __('Upgrade to Notifal Pro', 'notifal'),
                '<span class="notifal-menu-upgrade-btn">' . __('Upgrade for Free!', 'notifal') . '</span>',
                'manage_options',
                'notifal-upgrade-pro',
                [self::class, 'renderUpgradePage']
            );
        }
    }

    /**
     * Render the upgrade to pro page - redirects immediately to the license manager.
     *
     * @return void
     * @since 2.0.0
     */
    public static function renderUpgradePage(): void
    {
        // Build license manager URL with UTM tracking for the admin upgrade menu click.
        $license_manager_url = Urls::withCustomUtm(Urls::LICENSE_MANAGER, [
            'utm_medium' => 'upgrade_menu',
            'utm_campaign' => 'notifal_pro_upgrade',
            'utm_content' => 'upgrade_menu_link',
        ]);

        // Send the user to the external license manager page.
        wp_redirect($license_manager_url);
        exit;
    }

    /**
     * Prevents WordPress from adding duplicate submenu linking to main page.
     *
     * @return void
     * @since 2.0.0
     */
    public static function cleanupDuplicateSubmenu(): void
    {
        global $submenu;
        if (isset($submenu['notifal'])) {
            foreach ($submenu['notifal'] as $index => $item) {
                if ($item[2] === 'notifal') {
                    unset($submenu['notifal'][$index]);
                    break;
                }
            }
        }
    }
}
