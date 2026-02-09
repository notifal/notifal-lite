<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\Registration;

use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class TaxonomyRegistrar
 *
 * Registers custom taxonomies for Notifal Templates module.
 * Provides categorization capabilities for organizing notification templates.
 *
 * This class handles the registration of the 'notifal_template_category' taxonomy
 * and ensures default categories are available for template organization.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\Registration
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class TaxonomyRegistrar {

    /**
     * Register custom taxonomies for Notifal Templates.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register(): void {
        self::register_category_taxonomy();
    }

    /**
     * Register the 'notifal_template_category' taxonomy for template grouping.
     *
     * Creates a hierarchical taxonomy that allows users to categorize
     * their notification templates for better organization and filtering.
     *
     * @since 2.0.0
     * @return void
     */
    private static function register_category_taxonomy(): void {
        $labels = [
            'name'              => __( 'Template Categories', 'notifal' ),
            'singular_name'     => __( 'Template Category', 'notifal' ),
            'search_items'      => __( 'Search Categories', 'notifal' ),
            'all_items'         => __( 'All Categories', 'notifal' ),
            'edit_item'         => __( 'Edit Category', 'notifal' ),
            'update_item'       => __( 'Update Category', 'notifal' ),
            'add_new_item'      => __( 'Add New Category', 'notifal' ),
            'new_item_name'     => __( 'New Category Name', 'notifal' ),
            'menu_name'         => __( 'Categories', 'notifal' ),
        ];

        $args = [
            'labels'            => $labels,
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'hierarchical'      => true,
            'show_in_rest'      => true,
            'rewrite'           => false,
        ];

        register_taxonomy( 'notifal_template_category', [ 'notifal_template' ], $args );

        self::insert_default_terms();
    }

    /**
     * Insert default taxonomy terms for template categories if not already present.
     *
     * Ensures essential categorization terms are available for users to organize
     * their notification templates. Terms are only inserted if they don't exist
     * to prevent duplicates and maintain data integrity.
     *
     * @since 2.0.0
     * @return void
     */
    private static function insert_default_terms(): void {
        $defaults = [
            'promotions'  => __( 'Promotions', 'notifal' ),
            'seasonal'    => __( 'Seasonal', 'notifal' ),
            'announcements' => __( 'Announcements', 'notifal' ),
            'default'     => __( 'General', 'notifal' ),
        ];

        foreach ( $defaults as $slug => $name ) {
            if ( ! term_exists( $slug, 'notifal_template_category' ) ) {
                wp_insert_term(
                    Helper::sanitizeInput( $name, 'text' ),
                    'notifal_template_category',
                    [
                        'slug' => sanitize_title( $slug ),
                    ]
                );
            }
        }
    }
}
