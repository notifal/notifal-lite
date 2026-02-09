<?php

namespace Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Registration;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PostTypeRegistrar
 *
 * Registers the `notifal_onpage_notif` custom post type for On-Page notifications.
 * This post type is used internally for storing notification configurations.
 *
 * @package Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Registration
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class PostTypeRegistrar
{
    /**
     * Hook post type registration into WordPress.
     *
     * Registers the custom post type and its associated taxonomy during WordPress initialization.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function register(): void
    {
        add_action('init', [self::class, 'register_post_type']);
        add_action('init', [TaxonomyRegistrar::class, 'register_taxonomies']);
    }

    /**
     * Register the custom post type for On-Page notifications.
     *
     * Creates a non-public post type specifically designed for internal notification management.
     * The post type is not visible in the admin UI as we use our own custom interface.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function register_post_type(): void
    {
        $labels = [
            'name' => __('On-Page Notifications', 'notifal'),
            'singular_name' => __('On-Page Notification', 'notifal'),
            'add_new' => __('Add New On-Page Notification', 'notifal'),
            'add_new_item' => __('Add New On-Page Notification', 'notifal'),
            'edit_item' => __('Edit On-Page Notification', 'notifal'),
            'new_item' => __('New On-Page Notification', 'notifal'),
            'view_item' => __('View On-Page Notification', 'notifal'),
            'search_items' => __('Search On-Page Notifications', 'notifal'),
            'not_found' => __('No On-Page Notifications found', 'notifal'),
            'not_found_in_trash' => __('No On-Page Notifications found in Trash', 'notifal'),
            'menu_name' => __('On-Page Notifications', 'notifal'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => false, // We use our own custom UI
            'show_in_menu' => false, // We handle menu manually
            'capability_type' => 'post',
            'supports' => ['title'],
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => null,
            'show_in_rest' => false,
        ];

        $args = apply_filters(FilterHooks::POST_TYPE_ARGS, $args);

        register_post_type('notifal_onpage_notif', $args);

        /**
         * Fires after the On-Page notification post type is registered.
         *
         * @param array $args Post type registration arguments
         */
        do_action(ActionHooks::POST_TYPE_REGISTERED, $args);
    }
}
