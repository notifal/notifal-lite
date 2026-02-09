<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Core;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;

defined('ABSPATH') || exit;

/**
 * Manages cache clearing operations for notification data pools (orders and products).
 *
 * This service provides centralized cache management for pool-based data caching,
 * ensuring that when notification content sources change, related caches are properly
 * invalidated to reflect the latest data.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class PoolCacheManager
{
    /**
     * Clear all order pool caches.
     *
     * Removes all cached order data pools to ensure fresh data is used
     * when notification content sources or filters are modified.
     *
     * @since 2.0.0
     */
    public function clearOrderPoolCaches(): void
    {
        $this->clearPoolCachesByPattern('_transient_notifal_order_pool_%', ActionHooks::ONPAGE_ORDER_POOL_CACHE_CLEARED);
    }

    /**
     * Clear all product pool caches.
     *
     * Removes all cached product data pools to ensure fresh data is used
     * when notification content sources, categories, or sale filters are modified.
     *
     * @since 2.0.0
     */
    public function clearProductPoolCaches(): void
    {
        $this->clearPoolCachesByPattern('_transient_notifal_product_pool_%', ActionHooks::ONPAGE_PRODUCT_POOL_CACHE_CLEARED);
    }

    /**
     * Clear pool caches by SQL pattern.
     *
     * Queries the WordPress options table for transients matching the given pattern
     * and deletes them individually using WordPress transient functions to ensure
     * proper cache invalidation across all cache layers.
     *
     * @param string $pattern The SQL LIKE pattern for transient names (e.g., '_transient_notifal_order_pool_%')
     * @param string $actionHook The action hook constant to fire after clearing completes
     * @since 2.0.0
     */
    private function clearPoolCachesByPattern(string $pattern, string $actionHook): void
    {
        global $wpdb;

        // Get all matching transients (use prepare to avoid SQL injection if pattern ever comes from input)
        $transients = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            )
        );

        foreach ($transients as $transient) {
            $key = str_replace('_transient_', '', $transient);
            delete_transient($key);
        }

        /**
         * Fires after pool caches are cleared.
         *
         * @since 2.0.0
         */
        do_action($actionHook);
    }
}
