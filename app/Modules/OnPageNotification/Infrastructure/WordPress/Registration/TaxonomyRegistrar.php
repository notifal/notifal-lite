<?php
/**
 * Taxonomy Registration for On-Page Notifications Module
 *
 * This file handles the registration of custom taxonomies for the
 * On-Page Notifications module, providing categorization capabilities for notifications.
 *
 * @package Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Registration
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Registration;

use Notifal\Shared\Utils\Helper;

// Prevent direct access for security
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles registration of custom taxonomies for On-Page Notifications.
 *
 * This class is responsible for registering the 'notifal_label' taxonomy
 * which allows categorizing notifications into different types such as
 * sales notifications, stock alerts, announcements, etc.
 *
 * The taxonomy is registered during WordPress initialization and includes
 * default terms that are automatically inserted if they don't exist.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class TaxonomyRegistrar
{
    /**
     * Registers all taxonomies required by the On-Page Notifications module.
     *
     * This method serves as the main entry point for taxonomy registration.
     * It calls individual taxonomy registration methods to ensure all
     * required taxonomies are properly registered with WordPress.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register_taxonomies(): void
    {
        // Register the label taxonomy for notification categorization
        self::register_label_taxonomy();
    }

    /**
     * Registers the 'notifal_label' taxonomy for categorizing notification types.
     *
     * This taxonomy provides a way to categorize notifications into different
     * types, making it easier for users to organize and filter their notifications.
     *
     * The taxonomy is configured as:
     * - Non-public (not visible on frontend)
     * - No UI in admin (managed programmatically)
     * - Non-hierarchical (flat structure)
     * - Not shown in REST API
     *
     * Default terms include:
     * - Sales Notification: For promotional sales notifications
     * - Stock Alert: For low stock or out of stock alerts
     * - Discount Offer: For special discount promotions
     * - Informative: For general information notifications
     * - Announcement: For important announcements
     *
     * @since 2.0.0
     * @return void
     */
    private static function register_label_taxonomy(): void
    {
        // Define taxonomy labels for WordPress admin interface
        $labels = [
            'name'              => __('Notification Labels', 'notifal'),
            'singular_name'     => __('Notification Label', 'notifal'),
            'search_items'      => __('Search Labels', 'notifal'),
            'all_items'         => __('All Labels', 'notifal'),
            'edit_item'         => __('Edit Label', 'notifal'),
            'update_item'       => __('Update Label', 'notifal'),
            'add_new_item'      => __('Add New Label', 'notifal'),
            'new_item_name'     => __('New Label Name', 'notifal'),
            'menu_name'         => __('Labels', 'notifal'),
        ];

        // Configure taxonomy arguments
        $args = [
            'labels'            => $labels,
            'public'            => false,              // Not visible on frontend
            'show_ui'           => false,              // No admin UI
            'show_in_menu'      => false,              // Not in admin menu
            'show_admin_column' => false,              // No admin column
            'hierarchical'      => false,              // Flat structure
            'show_in_rest'      => false,              // Not in REST API
            'meta_box_cb'       => false,              // No meta box
        ];

        // Register the taxonomy with WordPress
        register_taxonomy('notifal_label', ['notifal_onpage_notif'], $args);

        // Insert default terms if they don't exist
        self::insert_default_terms();
    }

    /**
     * Inserts default taxonomy terms for 'notifal_label' if not already present.
     *
     * This method ensures that essential categorization terms are available
     * for users to organize their notifications. The terms are inserted only
     * if they don't already exist to avoid duplicates.
     *
     * Each term is sanitized using the Helper class to ensure data integrity
     * and prevent potential security issues.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     * @return void
     */
    public static function insert_default_terms(): void
    {
        // Define default terms with their slugs and localized names
        $defaults = [
            'sales-notification' => __('Sales Notification', 'notifal'),
            'stock-alert'        => __('Stock Alert', 'notifal'),
            'discount-offer'     => __('Discount Offer', 'notifal'),
            'informative'        => __('Informative', 'notifal'),
            'announcement'       => __('Announcement', 'notifal'),
        ];

        // Iterate through each default term
        foreach ($defaults as $slug => $name) {
            // Check if term already exists to prevent duplicates
            if (!term_exists($slug, 'notifal_label')) {
                // Insert the term with sanitized data
                wp_insert_term(
                    Helper::sanitizeInput($name, 'text'), // Sanitize term name
                    'notifal_label',
                    [
                        'slug' => sanitize_title($slug), // Sanitize slug
                    ]
                );
            }
        }
    }
}

