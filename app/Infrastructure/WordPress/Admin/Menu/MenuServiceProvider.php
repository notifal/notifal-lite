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
     * @since 2.3.5 Registers Free Configuration submenu hooks (priorities 1000–1001) to append and pin the CTA as the last item.
     */
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'registerMenu']);
        add_action('admin_menu', [self::class, 'addUpgradeMenuIfNeeded'], 30);
        add_action('admin_menu', [self::class, 'addFreeConfigurationMenu'], 1000);
        add_action('admin_menu', [self::class, 'cleanupDuplicateSubmenu'], 999);
        add_action('admin_menu', [self::class, 'moveFreeConfigurationMenuToLast'], 1001);
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
     * Add Free Configuration submenu item (external landing page).
     *
     * @return void
     * @since 2.3.5 Adds a purple-styled "Free Configuration" item linking to Urls::FREE_CONFIGURATION with admin-menu UTM params.
     */
    public static function addFreeConfigurationMenu(): void
    {
        $menu_label = '<span class="notifal-menu-free-configuration">'
            . esc_html__('Free Configuration', 'notifal')
            . '</span>';

        add_submenu_page(
            'notifal',
            __('Free Configuration', 'notifal'),
            $menu_label,
            'manage_options',
            'notifal-free-configuration',
            [self::class, 'renderFreeConfigurationPage']
        );
    }

    /**
     * Redirect Free Configuration menu click to the external landing page.
     *
     * @return void
     * @since 2.3.5 Redirects to the free setup landing page (same-traffic-more-sales) with tracked UTM parameters.
     */
    public static function renderFreeConfigurationPage(): void
    {
        $configuration_url = Urls::withCustomUtm(Urls::FREE_CONFIGURATION, [
            'utm_medium' => 'admin_menu',
            'utm_campaign' => 'notifal_free_configuration',
            'utm_content' => 'free_configuration_menu_link',
        ]);

        wp_redirect($configuration_url);
        exit;
    }

    /**
     * Ensure Free Configuration stays the last Notifal submenu item.
     *
     * @return void
     * @since 2.3.5 Reorders $submenu so Free Configuration remains after Pro/License items registered at priority 999.
     */
    public static function moveFreeConfigurationMenuToLast(): void
    {
        global $submenu;

        if (!isset($submenu['notifal']) || !is_array($submenu['notifal'])) {
            return;
        }

        $menu_slug = 'notifal-free-configuration';
        $menu_item = null;
        $menu_key = null;

        foreach ($submenu['notifal'] as $key => $item) {
            if (!isset($item[2]) || $item[2] !== $menu_slug) {
                continue;
            }

            $menu_item = $item;
            $menu_key = $key;
            break;
        }

        if ($menu_item === null || $menu_key === null) {
            return;
        }

        unset($submenu['notifal'][$menu_key]);
        $submenu['notifal'][] = $menu_item;
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
