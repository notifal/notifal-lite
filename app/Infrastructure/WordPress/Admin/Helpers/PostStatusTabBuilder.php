<?php

namespace Notifal\Infrastructure\WordPress\Admin\Helpers;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Class PostStatusTabBuilder
 * Builds status tab data (e.g. published, draft, trash) for admin views.
 *
 * Useful for generating filter tabs in custom post type admin UIs.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Infrastructure\WordPress\Admin\Helpers
 */
class PostStatusTabBuilder
{
    /**
     * Build status tab data for a given post type.
     *
     * Includes an "All" tab at the beginning with the total count of all statuses.
     *
     * @param string $postType The post type to query status counts for.
     * @param array  $only     Array of statuses to include (default: publish, draft, trash).
     * @return array<string, array{label: string, count: int}> Array of tab info keyed by status name.
     *
     * @since 2.0.1 Updated to include "all" tab at the beginning.
     */
    public static function build(string $postType, array $only = ['publish', 'draft', 'trash']): array
    {
        $counts = wp_count_posts($postType);
        $tabs = [];

        // Build individual status tabs
        foreach ((array) $counts as $status => $count) {
            if (!empty($only) && !in_array($status, $only, true)) {
                continue;
            }

            $statusObj = get_post_status_object($status);

            if (!$statusObj || !is_object($statusObj)) {
                continue;
            }

            $tabs[$status] = [
                'label' => $statusObj->label ?? ucfirst($status),
                'count' => (int) $count,
            ];
        }

        // Add "All" tab at the beginning with total count
        $tabs = ['all' => [
                'label' => __('All', 'notifal'),
                'count' => array_sum(array_column($tabs, 'count')),
            ]] + $tabs;

        /**
         * Filters the admin status tab data for the given post type.
         *
         * @param array  $tabs     The list of tab data.
         * @param string $postType The post type queried.
         *
         * @since 2.0.0
         */
        return apply_filters(sprintf(FilterHooks::ADMIN_STATUS_TABS, $postType), $tabs, $postType);
    }
}
