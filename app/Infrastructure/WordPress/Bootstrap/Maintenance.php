<?php

namespace Notifal\Infrastructure\WordPress\Bootstrap;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardEligibilityService;

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
     * Records Setup Wizard first-run state on activation.
     *
     * @since 2.0.0
     */
    public static function activate(): void
    {
        // Flush rewrite rules once
        flush_rewrite_rules();

        // Record Setup Wizard first-run state before modules boot on init.
        SetupWizardEligibilityService::markFirstInstallOnActivation();

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
        // Clean up legacy activation popup options from older releases.
        delete_option('notifal_activation_popup_shown');
        delete_option('notifal_activation_time');

        // Clean up deactivation tracking options
        delete_option('notifal_deactivation_count');
        delete_option('notifal_last_deactivation_time');
        delete_option('notifal_first_deactivation_time');
        delete_option('notifal_deactivation_daily_reports');

        // Clean up transients
        delete_transient('notifal_pending_activation_redirect');
        delete_transient('notifal_pending_setup_wizard_redirect');
        delete_option('notifal_setup_wizard_session');

        // Clean up Setup Wizard options.
        delete_option('notifal_setup_wizard_first_install');
        delete_option('notifal_setup_wizard_eligible');
        delete_option('notifal_setup_wizard_status');
        delete_option('notifal_setup_wizard_last_step');

        /**
         * Fires on plugin uninstall.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::PLUGIN_UNINSTALLED);
    }
}
