<?php

namespace Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Repositories;

use Notifal\Shared\Utils\Helper;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Class NotificationQuery
 *
 * Provides data access methods for notifal_onpage_notif post type.
 * CRITICAL: All queries for "active" notifications must use _notifal_notif_enabled meta key
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Repositories
 */
class NotificationQuery
{
    /**
     * Get all active OnPage Notifications
     * CRITICAL: Filters by _notifal_notif_enabled=1 meta key, not post_status
     *
     * @return WP_Post[]
     * @since 2.0.0
     */
    public static function getAll(): array
    {
        return get_posts([
            'post_type'      => 'notifal_onpage_notif',
            'post_status'    => 'publish', // Keep for performance (active notifs should be published)
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_notifal_notif_enabled',
                    'value'   => '1',
                    'compare' => '='
                ]
            ]
        ]);
    }

    /**
     * Get a single notification by post ID (regardless of activation status)
     *
     * @param int $id
     * @return WP_Post|null
     * @since 2.0.0
     */
    public static function get(int $id): ?WP_Post
    {
        return Helper::getPostSafe($id, 'notifal_onpage_notif');
    }

    /**
     * Get active notifications filtered by taxonomy term.
     * CRITICAL: Filters by _notifal_notif_enabled=1 meta key, not post_status
     *
     * @param string      $term     The term slug to filter by.
     * @param string|null $taxonomy Optional taxonomy (null = skip filter).
     * @return WP_Post[]
     * @since 2.0.0
     */
    public static function getByTerm(string $term, ?string $taxonomy = null): array
    {
        $args = [
            'post_type'      => 'notifal_onpage_notif',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_notifal_notif_enabled',
                    'value'   => '1',
                    'compare' => '='
                ]
            ]
        ];

        if ($taxonomy !== null) {
            $args['tax_query'] = [[
                'taxonomy' => $taxonomy,
                'field'    => 'slug',
                'terms'    => $term,
            ]];
        }

        return get_posts($args);
    }

    /**
     * Get active notification IDs excluding specified posts.
     * Used for activation limit enforcement to check existing active notifications.
     *
     * @param array $exclude Array of post IDs to exclude from results
     * @return int[] Array of active notification IDs
     * @since 2.0.0
     */
    public static function getActiveNotificationIds(array $exclude = []): array
    {
        $activeNotifications = get_posts([
            'post_type' => 'notifal_onpage_notif',
            'post_status' => 'any',
            'numberposts' => -1,
            'exclude' => $exclude,
            'meta_query' => [
                [
                    'key' => '_notifal_notif_enabled',
                    'value' => '1',
                    'compare' => '='
                ]
            ],
            'fields' => 'ids'
        ]);

        return is_array($activeNotifications) ? $activeNotifications : [];
    }
}
