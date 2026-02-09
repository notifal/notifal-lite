<?php

namespace Notifal\Infrastructure\WordPress\Support;

defined('ABSPATH') || exit;

/**
 * Class PluginDetector
 *
 * Utility class to detect the presence or activation status of third-party plugins.
 * All logic here is WordPress-specific and safe to call from anywhere in infrastructure layer.
 *
 * @since 2.0.0
 * @package Notifal\Infrastructure\WordPress\Support
 * @author Hossein <hossein@notifal.com>
 */
class PluginDetector
{
    /**
     * Check if Elementor plugin is active and fully loaded.
     *
     * @return bool
     * @since 2.0.0
     */
    public static function isElementorActive(): bool
    {
        return did_action('elementor/loaded') > 0;
    }

    /**
     * Check if WooCommerce plugin is active and available.
     *
     * @return bool
     * @since 2.0.0
     */
    public static function isWooCommerceActive(): bool
    {
        return class_exists('WooCommerce');
    }

    /**
     * Check if Easy Digital Downloads plugin is active and available.
     *
     * @return bool
     * @since 2.0.0
     */
    public static function isEDDActive(): bool
    {
        return function_exists('EDD');
    }

    /**
     * Check if a plugin is installed by checking its main plugin file.
     *
     * @param string $pluginFile The plugin file path (e.g., 'notifal-pro/notifal-pro.php').
     * @return bool
     * @since 2.0.0
     */
    public static function isPluginInstalled(string $pluginFile): bool
    {
        return file_exists(WP_PLUGIN_DIR . '/' . $pluginFile);
    }

    /**
     * Check if Notifal Pro is installed.
     *
     * @return bool
     * @since 2.0.0
     */
    public static function isNotifalProInstalled(): bool
    {
        return self::isPluginInstalled('notifal-pro/notifal-pro.php');
    }

    /**
     * Check if Notifal Pro is active (both installed and activated).
     *
     * @return bool
     * @since 2.0.0
     */
    public static function isNotifalProActive(): bool
    {
        if (!self::isNotifalProInstalled()) {
            return false;
        }

        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active('notifal-pro/notifal-pro.php');
    }
}
