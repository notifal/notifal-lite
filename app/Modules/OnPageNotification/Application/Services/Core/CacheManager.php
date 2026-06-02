<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Core;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\OnPageNotification\Application\Services\Template\FrontendTemplateRenderer;

defined('ABSPATH') || exit;

/**
 * Manages cache clearing for notification-related data pools when notifications are updated.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class CacheManager
{
    /**
     * @var PoolCacheManager
     */
    private $poolCacheManager;

    /**
     * @var ElementorCacheManager
     */
    private $elementorCacheManager;

    /**
     * @var WordPressCacheManager
     */
    private $wordpressCacheManager;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->poolCacheManager = notifal_app(PoolCacheManager::class);
        $this->elementorCacheManager = notifal_app(ElementorCacheManager::class);
        $this->wordpressCacheManager = notifal_app(WordPressCacheManager::class);
    }

    /**
     * Initialize cache management hooks.
     *
     * @since 2.0.0
     */
    public function register(): void
    {
        // Clear caches when notifications are saved/updated
        add_action(ActionHooks::ONPAGE_NOTIFICATION_SAVED, [$this, 'clearNotificationCaches'], 10, 2);
        add_action(ActionHooks::ONPAGE_NOTIFICATION_META_SAVED, [$this, 'clearNotificationCaches'], 10, 2);
    }

    /**
     * Clear all notification-related caches when a notification is saved.
     *
     * @param int   $postId        The notification post ID
     * @param array $sanitizedData The sanitized notification data
     * @since 2.0.0
     */
    public function clearNotificationCaches(int $postId, array $sanitizedData): void
    {
        // Clear order pool caches
        $this->poolCacheManager->clearOrderPoolCaches();

        // Clear product pool caches
        $this->poolCacheManager->clearProductPoolCaches();

        // Clear frontend template cache
        $this->clearFrontendTemplateCache();

        // Clear Elementor caches (CSS files and cache)
        $this->elementorCacheManager->clearElementorCaches($postId, $sanitizedData);

        // Clear WordPress object cache (if available)
        $this->wordpressCacheManager->clearWordPressObjectCache();

        /**
         * Fires after notification caches have been cleared.
         *
         * Allows developers to clear additional custom caches.
         *
         * @param int   $postId        The notification post ID
         * @param array $sanitizedData The sanitized notification data
         * @since 2.0.0
         */
        do_action(ActionHooks::ONPAGE_NOTIFICATION_CACHE_CLEARED, $postId, $sanitizedData);
    }


    /**
     * Clear frontend template rendering cache.
     *
     * @since 2.0.0
     */
    private function clearFrontendTemplateCache(): void
    {
        // Clear the static context cache in FrontendTemplateRenderer
        if (class_exists(FrontendTemplateRenderer::class)) {
            FrontendTemplateRenderer::clearContextCache();
        }

        /**
         * Fires after frontend template cache is cleared.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::ONPAGE_FRONTEND_TEMPLATE_CACHE_CLEARED);
    }


    /**
     * Clear WordPress object cache groups.
     *
     * @param array $groups Array of cache group names to clear
     * @since 2.0.0
     */
    public function clearWordPressObjectCacheGroups(array $groups): void
    {
        $this->wordpressCacheManager->clearWordPressObjectCacheGroups($groups);
    }

    /**
     * Manually clear all notification caches.
     *
     * @since 2.0.0
     */
    public function clearAllCaches(): void
    {
        $this->poolCacheManager->clearOrderPoolCaches();
        $this->poolCacheManager->clearProductPoolCaches();
        $this->poolCacheManager->clearContentPoolObjectCaches();
        $this->clearFrontendTemplateCache();
        $this->elementorCacheManager->clearElementorCaches(0, []); // Clear all Elementor caches
        $this->wordpressCacheManager->clearWordPressObjectCache();

        /**
         * Fires after all notification caches are manually cleared.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::ONPAGE_ALL_CACHES_CLEARED);
    }

    /**
     * Get cache statistics for debugging.
     *
     * @return array Cache statistics
     * @since 2.0.0
     */
    public function getCacheStats(): array
    {
        global $wpdb;

        // Single query to count both cache types for better performance
        $counts = $wpdb->get_row(
            "SELECT
                SUM(CASE WHEN option_name LIKE '_transient_notifal_order_pool_%' THEN 1 ELSE 0 END) as order_pools,
                SUM(CASE WHEN option_name LIKE '_transient_notifal_product_pool_%' THEN 1 ELSE 0 END) as product_pools
             FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_notifal_%_pool_%'"
        );

        return [
            'order_pools' => (int) ($counts->order_pools ?? 0),
            'product_pools' => (int) ($counts->product_pools ?? 0),
            'object_cache_enabled' => wp_using_ext_object_cache(),
            'timestamp' => current_time('mysql')
        ];
    }
}
