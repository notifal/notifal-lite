<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Core;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;

defined('ABSPATH') || exit;

/**
 * Handles WordPress-specific cache operations.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class WordPressCacheManager
{
    /**
     * Clear WordPress object cache if available.
     *
     * @since 2.0.0
     */
    public function clearWordPressObjectCache(): void
    {
        // Only flush if persistent object cache is available
        if (wp_using_ext_object_cache()) {
            wp_cache_flush();
        }

        /**
         * Fires after WordPress object cache is cleared.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::ONPAGE_WP_OBJECT_CACHE_CLEARED);
    }

    /**
     * Clear WordPress object cache groups.
     *
     * @param array $groups Array of cache group names to clear
     * @since 2.0.0
     */
    public function clearWordPressObjectCacheGroups(array $groups): void
    {
        foreach ($groups as $group) {
            if (wp_using_ext_object_cache() && function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group($group);
            }
        }
    }
}
