<?php

namespace Notifal\Infrastructure\WordPress\Bootstrap;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;

defined('ABSPATH') || exit;

/**
 * Class Maintenance
 * Handles plugin lifecycle events: activation, deactivation, and uninstall.
 *
 * @package Notifal\Infrastructure\WordPress\Bootstrap
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class Maintenance
{
    /**
     * Register plugin lifecycle hooks.
     *
     * @since 2.0.0
     */
    public static function register(): void
    {
        register_activation_hook(NOTIFAL_FILE, [self::class, 'activate']);
        register_deactivation_hook(NOTIFAL_FILE, [self::class, 'deactivate']);
        register_uninstall_hook(NOTIFAL_FILE, [self::class, 'uninstall']);
    }

    /**
     * Plugin activation logic.
     *
     * Sets up activation popup for first-time users and records activation time.
     *
     * @since 2.0.0
     */
    public static function activate(): void
    {
        // Flush rewrite rules once
        flush_rewrite_rules();

        // Show activation popup only for first-time activation
        if (!get_option('notifal_activation_popup_shown', false)) {
            set_transient('notifal_pending_activation_redirect', true, 5 * MINUTE_IN_SECONDS);
            update_option('notifal_activation_time', time());
        }

        /**
         * Fires on plugin activation.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::PLUGIN_ACTIVATED);
    }

    /**
     * Plugin deactivation logic.
     *
     * @since 2.0.0
     */
    public static function deactivate(): void
    {
        /**
         * Fires on plugin deactivation.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::PLUGIN_DEACTIVATED);
    }

    /**
     * Plugin uninstall logic.
     *
     * Cleans up all plugin-related options and transients during uninstall.
     *
     * @since 2.0.0
     */
    public static function uninstall(): void
    {
        // Clean up activation popup options
        delete_option('notifal_activation_popup_shown');
        delete_option('notifal_activation_time');

        // Clean up deactivation tracking options
        delete_option('notifal_deactivation_count');
        delete_option('notifal_last_deactivation_time');
        delete_option('notifal_first_deactivation_time');
        delete_option('notifal_deactivation_daily_reports');

        // Clean up transients
        delete_transient('notifal_activation_popup_pending');
        delete_transient('notifal_pending_activation_redirect');

        /**
         * Fires on plugin uninstall.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::PLUGIN_UNINSTALLED);
    }
}
