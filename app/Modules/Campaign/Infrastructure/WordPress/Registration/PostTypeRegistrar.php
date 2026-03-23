<?php

namespace Notifal\Modules\Campaign\Infrastructure\WordPress\Registration;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class PostTypeRegistrar
 *
 * Registers the `notifal_campaign` custom post type.
 * Campaigns are internal objects used to control scheduling overrides.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\Campaign\Infrastructure\WordPress\Registration
 */
class PostTypeRegistrar
{
    /**
     * Register WordPress hooks for post type initialization.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function register(): void
    {
        add_action( 'init', [ self::class, 'register_post_type' ] );
    }

    /**
     * Register the custom post type.
     *
     * The CPT is non-public and UI-less because Notifal provides its own admin screens.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function register_post_type(): void
    {
        $labels = [
            'name'               => __( 'Campaigns', 'notifal' ),
            'singular_name'      => __( 'Campaign', 'notifal' ),
            'add_new'            => __( 'Add New Campaign', 'notifal' ),
            'add_new_item'      => __( 'Add New Campaign', 'notifal' ),
            'edit_item'         => __( 'Edit Campaign', 'notifal' ),
            'new_item'          => __( 'New Campaign', 'notifal' ),
            'view_item'         => __( 'View Campaign', 'notifal' ),
            'search_items'      => __( 'Search Campaigns', 'notifal' ),
            'not_found'         => __( 'No Campaigns found', 'notifal' ),
            'not_found_in_trash'=> __( 'No Campaigns found in Trash', 'notifal' ),
            'menu_name'         => __( 'Campaigns', 'notifal' ),
        ];

        $args = [
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => false,
            'show_in_menu'        => false,
            'capability_type'    => 'post',
            'supports'            => [ 'title' ],
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'show_in_rest'       => false,
        ];

        $args = apply_filters( FilterHooks::POST_TYPE_ARGS, $args );

        register_post_type( 'notifal_campaign', $args );

        do_action( ActionHooks::POST_TYPE_REGISTERED, $args );
    }
}

