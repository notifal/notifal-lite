<?php

namespace Notifal\Infrastructure\WordPress\WooCommerce\Support;

use Notifal\Infrastructure\WordPress\Support\PluginDetector;

defined('ABSPATH') || exit;

/**
 * Aligns Notifal frontend script dependencies with WooCommerce single product pages so WordPress and WooCommerce
 * scripts that share the same page stay in a predictable order.
 *
 * @package Notifal\Infrastructure\WordPress\WooCommerce\Support
 * @since   2.2.0
 * @author  Hossein <hossein@notifal.com>
 */
final class WooCommerceVariationScriptSupport
{
    /**
     * Whether the current request is a single product screen where WooCommerce may output variation forms.
     *
     * @return bool True when WooCommerce is active and {@see is_product()} is true.
     */
    private static function isSingularProductContext(): bool
    {
        if (!PluginDetector::isWooCommerceActive()) {
            return false;
        }

        return function_exists('is_product') && is_product();
    }

    /**
     * Script handles to register as dependencies for Notifal frontend bundles (`notifal-onpage-frontend-bundle`,
     * `notifal-templates-frontend-bundle`).
     *
     * @return string[] Script handles in dependency order.
     */
    public static function getFrontendBundleDependencies(): array
    {
        $dependencies = ['jquery'];

        if (self::isSingularProductContext()) {
            $dependencies[] = 'wp-util';
        }

        return $dependencies;
    }

    /**
     * Enqueues the WordPress `wp-util` script on single product pages when Notifal frontend assets are loaded.
     *
     * Safe to call repeatedly; WordPress deduplicates queued scripts.
     *
     * @return void
     */
    public static function ensureWpUtilOnSingularProduct(): void
    {
        if (!self::isSingularProductContext()) {
            return;
        }

        wp_enqueue_script('wp-util');
    }
}
