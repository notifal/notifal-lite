<?php

namespace Notifal\Infrastructure\WordPress\Admin\Modules\Controllers;

use Notifal\Core\Modules\ModuleRegistry;
use Notifal\Domain\Settings\Services\SettingsSanitizationService;
use Notifal\Infrastructure\WordPress\Security\NonceManager;

defined('ABSPATH') || exit;

/**
 * AJAX handlers for the Addons admin page.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ModulesAjaxController
{
    /**
     * Nonce action for addon AJAX toggles.
     *
     * @var string
     */
    public const NONCE_ACTION = 'notifal_addons_toggle';

    /**
     * Register AJAX hooks.
     *
     * @return void
     * @since 3.0.0
     */
    public static function register(): void
    {
        add_action('wp_ajax_notifal_toggle_addon', [self::class, 'handleToggleAddon']);
    }

    /**
     * Enable or disable a single addon via AJAX.
     *
     * @return void
     * @since 3.0.0
     */
    public static function handleToggleAddon(): void
    {
        // Capability gate before reading request data.
        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                [
                    'message' => __('You do not have sufficient permissions to perform this action.', 'notifal'),
                ],
                403
            );
        }

        $nonce = isset($_POST['nonce'])
            ? sanitize_text_field(wp_unslash((string) $_POST['nonce']))
            : '';

        // Validate CSRF token.
        if (!NonceManager::verify($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(
                [
                    'message' => __('Security check failed. Please try again.', 'notifal'),
                ],
                403
            );
        }

        $settingsKey = isset($_POST['settings_key'])
            ? sanitize_key(wp_unslash((string) $_POST['settings_key']))
            : '';

        // Only allow keys that belong to toggleable addons.
        $allowedKeys = ModuleRegistry::getToggleableSettingKeys();
        if ($settingsKey === '' || !in_array($settingsKey, $allowedKeys, true)) {
            wp_send_json_error(
                [
                    'message' => __('Invalid addon selected.', 'notifal'),
                ],
                400
            );
        }

        $enabledRaw = isset($_POST['enabled'])
            ? sanitize_text_field(wp_unslash((string) $_POST['enabled']))
            : '0';
        $enabled = ($enabledRaw === '1');

        $sanitizationService = notifal_app(SettingsSanitizationService::class);
        $saved = $sanitizationService->saveSettings(
            [
                $settingsKey => $enabled,
            ]
        );

        if (!$saved) {
            wp_send_json_error(
                [
                    'message' => __('Unable to update addon. Please try again.', 'notifal'),
                ],
                500
            );
        }

        $message = $enabled
            ? __('Addon enabled successfully.', 'notifal')
            : __('Addon disabled successfully.', 'notifal');

        wp_send_json_success(
            [
                'message'      => $message,
                'settings_key' => $settingsKey,
                'enabled'      => $enabled,
            ]
        );
    }
}
