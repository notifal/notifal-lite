<?php

namespace Notifal\Infrastructure\WordPress\Admin\Helpers;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Class AdminStatsService
 *
 * Provides statistics and helper data for admin list views across different post types.
 * Handles total counts and status tabs generation for custom post types.
 *
 * This service eliminates duplication by providing a generic interface for
 * post type statistics that can be used by any module requiring admin stats.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Infrastructure\WordPress\Admin\Helpers
 */
class AdminStatsService
{
    /**
     * Get total number of posts for a given post type (with supported statuses).
     *
     * Counts posts across publish, draft, and trash statuses for the specified post type.
     * Applies filter for external customization of total count.
     *
     * @param string $postType The post type to count posts for
     * @return int Total post count across supported statuses
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public function getTotalPosts(string $postType): int
    {
        $counts = wp_count_posts($postType);
        $total = 0;

        foreach (['publish', 'draft', 'trash'] as $status) {
            $total += isset($counts->$status) ? (int)$counts->$status : 0;
        }

        /**
         * Allow external code to override total post count for a specific post type.
         *
         * @param int    $total    Current calculated total
         * @param string $postType The post type being counted
         * @since 2.0.0
         * @author Hossein <hossein@notifal.com>
         */
        return apply_filters(FilterHooks::ADMIN_LIST_COUNT_QUERY_ARGS, $total, $postType);
    }

    /**
     * Get post status tabs for a given post type's admin list view.
     *
     * Uses PostStatusTabBuilder to generate status filter tabs for the admin list.
     * Applies filter for external customization of status tabs.
     *
     * @param string $postType The post type to generate status tabs for
     * @return array Status tab configuration array
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public function getStatusTabs(string $postType): array
    {
        $tabs = PostStatusTabBuilder::build($postType);

        /**
         * Allow external code to modify or replace status tabs for a specific post type.
         *
         * @param array  $tabs     Generated status tabs
         * @param string $postType The post type the tabs are for
         * @since 2.0.0
         * @author Hossein <hossein@notifal.com>
         */
        return apply_filters(sprintf(FilterHooks::ADMIN_STATUS_TABS, $postType), $tabs, $postType);
    }
}