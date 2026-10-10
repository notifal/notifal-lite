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
     * Returns false when Pro remains in the plugins list but is paused because
     * the installed base Notifal version is below the Pro minimum.
     *
     * @return bool
     * @since 2.0.0
     * @since 3.0.0 Respects NOTIFAL_PRO_PAUSED so paused Pro cannot look fully active.
     */
    public static function isNotifalProActive(): bool
    {
        if (!self::isNotifalProInstalled()) {
            return false;
        }

        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Pro may stay activated in WordPress while feature bootstrap is paused.
        if (defined('NOTIFAL_PRO_PAUSED') && NOTIFAL_PRO_PAUSED) {
            return false;
        }

        return is_plugin_active('notifal-pro/notifal-pro.php');
    }

    /**
     * Check if WP Armour (Honeypot Anti Spam) plugin is installed.
     *
     * @return bool
     * @since 3.0.0
     */
    public static function isWpArmourInstalled(): bool
    {
        // The free WP Armour plugin lives in the "honeypot" directory with "wp-armour.php" as main file.
        return self::isPluginInstalled('honeypot/wp-armour.php');
    }

    /**
     * Check if WP Armour (Honeypot Anti Spam) plugin is installed and active.
     *
     * Combines the installed check with a runtime function check, because
     * wpa_check_is_spam() is only defined when the plugin is loaded by WordPress.
     *
     * @return bool
     * @since 3.0.0
     */
    public static function isWpArmourActive(): bool
    {
        // Require the plugin files to exist before considering it active.
        if (!self::isWpArmourInstalled()) {
            return false;
        }

        // The central spam validator function only exists when the plugin is active and loaded.
        return function_exists('wpa_check_is_spam');
    }

    /**
     * Get the human-readable names of all currently active plugins.
     *
     * Merges per-site active plugins with network-activated plugins on
     * multisite installs and resolves each slug to its display name.
     *
     * @return string[] Sorted list of unique active plugin names.
     * @since 2.4.0
     */
    public static function getActivePluginNames(): array
    {
        // Ensure the plugin admin API is available outside admin context.
        if (!function_exists('get_plugins')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Collect per-site active plugin files.
        $activePluginFiles = (array) get_option('active_plugins', []);

        // Include network-activated plugins on multisite installs.
        if (is_multisite()) {
            $networkPlugins = (array) get_site_option('active_sitewide_plugins', []);
            $activePluginFiles = array_merge($activePluginFiles, array_keys($networkPlugins));
        }

        // Map each active plugin file to its registered display name.
        $allPlugins = get_plugins();
        $names = [];

        foreach ($activePluginFiles as $pluginFile) {
            if (isset($allPlugins[$pluginFile]['Name']) && $allPlugins[$pluginFile]['Name'] !== '') {
                $names[] = $allPlugins[$pluginFile]['Name'];
            }
        }

        // Remove duplicates and sort alphabetically for stable output.
        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    /**
     * Get sanitized plugin directory slugs for all currently active plugins.
     *
     * Returns the first path segment of each active plugin file, for example
     * "woocommerce" from "woocommerce/woocommerce.php".
     *
     * @return string[] Sorted list of unique active plugin slugs.
     * @since 3.0.0
     */
    public static function getActivePluginSlugs(): array
    {
        // Ensure the plugin admin API is available outside admin context.
        if (!function_exists('get_plugins')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Collect per-site active plugin files.
        $activePluginFiles = (array) get_option('active_plugins', []);

        // Include network-activated plugins on multisite installs.
        if (is_multisite()) {
            $networkPlugins = (array) get_site_option('active_sitewide_plugins', []);
            $activePluginFiles = array_merge($activePluginFiles, array_keys($networkPlugins));
        }

        // Extract the plugin directory slug from each active plugin file path.
        $slugs = [];
        foreach ($activePluginFiles as $pluginFile) {
            $pluginFile = sanitize_text_field((string) $pluginFile);
            if ('' === $pluginFile) {
                continue;
            }

            // Use the directory name before the main plugin file as the slug.
            $slug = sanitize_key(strtok($pluginFile, '/'));
            if ('' !== $slug) {
                $slugs[$slug] = $slug;
            }
        }

        // Return a sorted unique slug list for stable outbound payloads.
        $slugs = array_values($slugs);
        sort($slugs);

        return $slugs;
    }
}
