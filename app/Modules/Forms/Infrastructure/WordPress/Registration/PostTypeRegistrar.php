<?php

namespace Notifal\Modules\Forms\Infrastructure\WordPress\Registration;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Registers the notifal_form custom post type (secrets / identity, not field painter).
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class PostTypeRegistrar
{
    /**
     * Register hooks.
     *
     * @return void
     * @since 3.0.0
     */
    public static function register(): void
    {
        add_action('init', [self::class, 'registerPostType']);
    }

    /**
     * Register CPT.
     *
     * @return void
     * @since 3.0.0
     */
    public static function registerPostType(): void
    {
        $labels = [
            'name'               => __('Forms', 'notifal'),
            'singular_name'      => __('Form', 'notifal'),
            'add_new'            => __('Add New Form', 'notifal'),
            'add_new_item'       => __('Add New Form', 'notifal'),
            'edit_item'          => __('Edit Form', 'notifal'),
            'new_item'           => __('New Form', 'notifal'),
            'view_item'          => __('View Form', 'notifal'),
            'search_items'       => __('Search Forms', 'notifal'),
            'not_found'          => __('No Forms found', 'notifal'),
            'not_found_in_trash' => __('No Forms found in Trash', 'notifal'),
            'menu_name'          => __('Forms', 'notifal'),
        ];

        $args = [
            'labels'            => $labels,
            'public'            => false,
            'show_ui'           => false,
            'show_in_menu'      => false,
            'capability_type'   => 'post',
            'supports'          => ['title'],
            'has_archive'       => false,
            'hierarchical'      => false,
            'menu_position'     => null,
            'show_in_rest'      => false,
        ];

        $args = apply_filters(FilterHooks::POST_TYPE_ARGS, $args);
        register_post_type('notifal_form', $args);
        do_action(ActionHooks::POST_TYPE_REGISTERED, $args);
    }
}
