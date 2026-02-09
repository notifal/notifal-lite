<?php

namespace Notifal\Shared\Traits;

use Notifal\Shared\Utils\Helper;
use Notifal\Modules\OnPageNotification\Application\Services\Core\NotificationActivationGuard;

defined('ABSPATH') || exit;

/**
 * Integrity Verification Trait
 *
 * Provides shared integrity verification methods for ServiceProviders.
 * Contains integrity checks for critical security components to prevent tampering
 * and ensure the plugin operates in a secure environment.
 *
 * This trait verifies that essential security classes exist and have required methods,
 * providing a basic integrity check against file tampering or corruption.
 *
 * @package Notifal\Shared\Traits
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
trait IntegrityVerificationTrait
{
    /**
     * Verify NotificationActivationGuard integrity.
     *
     * Performs integrity verification on the NotificationActivationGuard class to ensure
     * it exists, can be loaded, and contains the required security methods. This prevents
     * tampering with critical security components that protect against activation limit bypasses.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    protected function verify_activation_guard_integrity(): void
    {
        // Verify the NotificationActivationGuard class exists and is autoloadable
        if (!class_exists(NotificationActivationGuard::class)) {
            Helper::securityLog('NotificationActivationGuard class not found - plugin deactivated', [
                'class_name' => NotificationActivationGuard::class,
                'user_id' => get_current_user_id(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            $this->deactivate_plugin();
            return;
        }

        // Verify the init method exists and is callable
        if (!method_exists(NotificationActivationGuard::class, 'init')) {
            Helper::securityLog('NotificationActivationGuard::init method missing - plugin deactivated', [
                'class_name' => NotificationActivationGuard::class,
                'method_name' => 'init',
                'user_id' => get_current_user_id(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            $this->deactivate_plugin();
            return;
        }

        // Verify critical security methods exist
        $required_methods = [
            'validateActivationOnMetaUpdate',
            'validateActivationOnMetaAdd',
            'syncPostStatusWithMetaKey',
            'cleanupMultipleActiveNotifications'
        ];

        foreach ($required_methods as $method) {
            if (!method_exists(NotificationActivationGuard::class, $method)) {
                Helper::securityLog('Critical NotificationActivationGuard method missing - plugin deactivated', [
                    'class_name' => NotificationActivationGuard::class,
                    'missing_method' => $method,
                    'user_id' => get_current_user_id(),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                $this->deactivate_plugin();
                return;
            }
        }
    }

    /**
     * Deactivate the plugin on integrity violation.
     *
     * Silently deactivates the plugin when integrity violations are detected.
     * This method uses WordPress core functions to safely deactivate the plugin
     * and prevents further execution to maintain security.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    protected function deactivate_plugin(): void
    {
        // Use the proper constant for plugin basename
        if (function_exists('deactivate_plugins') && defined('NOTIFAL_BASENAME')) {
            deactivate_plugins(NOTIFAL_BASENAME);
        }

        // Prevent further execution outside of AJAX context
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            exit;
        }
    }
}
