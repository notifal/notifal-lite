<?php

namespace Notifal\Core\Modules;

use Notifal\Domain\Settings\Constants\SettingsKeys;
use Notifal\Domain\Settings\Services\SettingsService;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Central registry for Notifal addons shown on the Addons admin page.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ModuleRegistry
{
    /**
     * Build the addon catalog for the Addons admin screen.
     *
     * @return array<string, array<string, mixed>>
     * @since 3.0.0
     */
    public static function getModules(): array
    {
        // Base catalog before third-party filters extend it.
        $modules = [
            'forms' => [
                'slug'         => 'forms',
                'title'        => __('Forms', 'notifal'),
                'description'  => __('Build lead capture forms, manage submissions, and connect email notifications.', 'notifal'),
                'icon'         => 'feedback',
                'status'       => 'available',
                'settings_key' => SettingsKeys::FORMS_ENABLED,
                'manage_url'   => admin_url('admin.php?page=notifal-forms'),
            ],
            'gamification' => [
                'slug'        => 'gamification',
                'title'       => __('Gamification', 'notifal'),
                'description' => __('Engage visitors with points, badges, rewards, and interactive experiences.', 'notifal'),
                'icon'        => 'awards',
                'status'      => 'coming_soon',
            ],
            'push_notification' => [
                'slug'        => 'push_notification',
                'title'       => __('Push Notification', 'notifal'),
                'description' => __('Reach visitors with browser push alerts for offers, updates, and reminders.', 'notifal'),
                'icon'        => 'bell',
                'status'      => 'coming_soon',
            ],
        ];

        /**
         * Filter the addon catalog displayed on the Addons admin page.
         *
         * @since 3.0.0
         *
         * @param array<string, array<string, mixed>> $modules Addon definitions.
         */
        return apply_filters(FilterHooks::MODULE_REGISTRY, $modules);
    }

    /**
     * Resolve whether an addon is currently enabled.
     *
     * @param array<string, mixed> $module          Addon definition from getModules().
     * @param SettingsService      $settingsService Settings accessor.
     * @return bool
     * @since 3.0.0
     */
    public static function isModuleEnabled(array $module, SettingsService $settingsService): bool
    {
        // Coming soon addons are never toggleable.
        if (($module['status'] ?? '') === 'coming_soon') {
            return false;
        }

        // Available addons must declare a settings key.
        $settingsKey = isset($module['settings_key']) ? (string) $module['settings_key'] : '';
        if ($settingsKey === '') {
            return false;
        }

        // Read persisted option with addon-specific default.
        $default = SettingsKeys::getDefaultFormsSettings()[$settingsKey] ?? false;
        $value = $settingsService->get($settingsKey, $default);

        // Normalize stored option values to a real boolean.
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
                return true;
            }

            if (in_array($normalized, ['', '0', 'false', 'off', 'no'], true)) {
                return false;
            }
        }

        return (bool) $value;
    }

    /**
     * Return settings keys that can be toggled from the Addons page.
     *
     * @return string[]
     * @since 3.0.0
     */
    public static function getToggleableSettingKeys(): array
    {
        // Collect keys only from addons that are available today.
        $keys = [];

        foreach (self::getModules() as $module) {
            if (($module['status'] ?? '') !== 'available') {
                continue;
            }

            if (empty($module['settings_key'])) {
                continue;
            }

            $keys[] = (string) $module['settings_key'];
        }

        return $keys;
    }
}
