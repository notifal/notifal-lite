<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return [
    // Shared UI.
    'ajax_search_placeholder'              => __( 'Type at least 3 letters to start searching...', 'notifal' ),
    'loading'                              => __( 'Loading', 'notifal' ),
    'no_results_found'                     => __( 'No results found', 'notifal' ),
    'search_meta_fields_placeholder'       => __( 'Search meta fields...', 'notifal' ),
    'remove'                               => __( 'Remove', 'notifal' ),
    'date_type'                            => __( 'Date Type', 'notifal' ),
    'date_range'                           => __( 'Date Range', 'notifal' ),
    'start_date'                           => __( 'Start Date', 'notifal' ),
    'end_date'                             => __( 'End Date', 'notifal' ),
    'publish_date'                         => __( 'Publish Date', 'notifal' ),
    'modified_date'                        => __( 'Modified Date', 'notifal' ),
    'date_last_24h'                        => __( 'Last 24 Hours', 'notifal' ),
    'date_last_7d'                         => __( 'Last 7 Days', 'notifal' ),
    'date_last_30d'                        => __( 'Last 30 Days', 'notifal' ),
    'date_last_90d'                        => __( 'Last 90 Days', 'notifal' ),
    'custom_range'                         => __( 'Custom Range', 'notifal' ),
    'custom_meta_filter'                   => __( 'Custom Meta Filter', 'notifal' ),
    'custom_meta_operators_help'           => __( 'Use operators: >, <, =, !=, >=, <=. Combine with && (AND) or || (OR).', 'notifal' ),
    'validation_error_template'            => __( '{type} filter {index} (\'{name}\'): {message}', 'notifal' ),

    // Filter context labels (validation prefix).
    'filter_context_product'               => __( 'Product', 'notifal' ),
    'filter_context_order'                 => __( 'Order', 'notifal' ),
    'filter_context_user'                  => __( 'User', 'notifal' ),
    'filter_context_post'                  => __( 'Post', 'notifal' ),
    'filter_context_page'                  => __( 'Page', 'notifal' ),
    'filter_context_comment'               => __( 'Comment', 'notifal' ),
    'filter_context_custom_posttype'       => __( 'Custom post type', 'notifal' ),

    // Product filter types.
    'filter_all_products'                  => __( 'All Products', 'notifal' ),
    'filter_specific_categories'           => __( 'Specific Categories', 'notifal' ),
    'filter_specific_products'             => __( 'Specific Products', 'notifal' ),
    'filter_sale_products_only'            => __( 'Sale Products Only', 'notifal' ),
    'filter_featured_products_only'        => __( 'Featured Products Only', 'notifal' ),
    'filter_date_range'                    => __( 'Date Range', 'notifal' ),
    'filter_custom_meta'                   => __( 'Custom Meta', 'notifal' ),
    'cart_products_filter_label'           => __( 'Cart Products', 'notifal' ),

    // Order filter types.
    'filter_all_orders'                    => __( 'All Orders', 'notifal' ),
    'filter_order_status'                  => __( 'Order Status', 'notifal' ),
    'filter_orders_with_products'          => __( 'Orders with Products', 'notifal' ),
    'filter_custom_filter'                 => __( 'Custom Filter', 'notifal' ),

    // User filter types.
    'filter_all_users'                     => __( 'All Users', 'notifal' ),
    'filter_user_roles'                    => __( 'User Roles', 'notifal' ),
    'filter_specific_users'                => __( 'Specific Users', 'notifal' ),
    'filter_registration_date'             => __( 'Registration Date', 'notifal' ),

    // Post filter types.
    'filter_all_posts'                     => __( 'All Posts', 'notifal' ),
    'filter_specific_posts'                => __( 'Specific Posts', 'notifal' ),
    'filter_post_authors'                  => __( 'Post Authors', 'notifal' ),

    // Page filter types.
    'filter_all_pages'                     => __( 'All Pages', 'notifal' ),
    'filter_specific_pages'                => __( 'Specific Pages', 'notifal' ),
    'filter_page_status'                   => __( 'Page Status', 'notifal' ),
    'filter_page_authors'                  => __( 'Page Authors', 'notifal' ),
    'filter_page_template'                 => __( 'Page Template', 'notifal' ),

    // Comment filter types.
    'filter_all_comments'                  => __( 'All Comments', 'notifal' ),
    'filter_comment_status'                => __( 'Comment Status', 'notifal' ),
    'filter_post_types'                    => __( 'Post Types', 'notifal' ),
    'filter_comment_authors'               => __( 'Comment Authors', 'notifal' ),

    // Custom post type filter types.
    'filter_all_items'                     => __( 'All Items', 'notifal' ),
    'filter_specific_items'                => __( 'Specific Items', 'notifal' ),
    'filter_status'                        => __( 'Status', 'notifal' ),
    'filter_authors'                       => __( 'Authors', 'notifal' ),
    'label_categories'                     => __( 'Categories', 'notifal' ),

    // Product fields.
    'label_product_categories'             => __( 'Product Categories', 'notifal' ),
    'label_specific_products'              => __( 'Specific Products', 'notifal' ),
    'info_sale_products_only'              => __( 'Shows only products that are currently on sale.', 'notifal' ),
    'info_featured_products_only'          => __( 'Shows only featured products.', 'notifal' ),
    'info_all_products_included'           => __( 'All products will be included.', 'notifal' ),
    'placeholder_product_custom_meta'      => __( '_regular_price>100', 'notifal' ),
    'available_product_meta_fields'        => __( 'Available Product Meta Fields', 'notifal' ),

    // Cart product sources.
    'cart_products_label'                  => __( 'Cart products', 'notifal' ),
    'cart_products_description'            => __( 'Use products currently in the visitor cart.', 'notifal' ),
    'related_cart_products_label'        => __( 'Related cart products', 'notifal' ),
    'related_cart_products_description'    => __( 'Use products related to items in the visitor cart.', 'notifal' ),
    'upsell_cart_products_label'           => __( 'Upsell cart products', 'notifal' ),
    'upsell_cart_products_description'     => __( 'Use upsell products configured on cart items.', 'notifal' ),
    'cross_sell_cart_products_label'       => __( 'Cross-sell cart products', 'notifal' ),
    'cross_sell_cart_products_description' => __( 'Use cross-sell products configured on cart items.', 'notifal' ),
    'cart_sources_info_message'            => __( 'Select one or more cart sources. Multiple selections are combined with OR logic inside this filter.', 'notifal' ),
    'cart_enable_one_source'               => __( 'Please enable at least one cart product source.', 'notifal' ),

    // Order fields.
    'label_order_statuses'                 => __( 'Order Statuses', 'notifal' ),
    'label_orders_containing_products'     => __( 'Orders Containing Products', 'notifal' ),
    'info_all_orders_included'             => __( 'All orders will be included.', 'notifal' ),
    'placeholder_order_custom_meta'        => __( '_order_total>200', 'notifal' ),
    'available_order_meta_fields'          => __( 'Available Order Meta Fields', 'notifal' ),
    'order_status_completed'               => __( 'Completed', 'notifal' ),
    'order_status_processing'              => __( 'Processing', 'notifal' ),
    'order_status_on_hold'                 => __( 'On Hold', 'notifal' ),
    'order_status_pending'                 => __( 'Pending', 'notifal' ),
    'order_status_cancelled'               => __( 'Cancelled', 'notifal' ),
    'order_status_refunded'                => __( 'Refunded', 'notifal' ),

    // User fields.
    'label_user_roles'                     => __( 'User Roles', 'notifal' ),
    'label_specific_users'                 => __( 'Specific Users', 'notifal' ),
    'info_all_users_included'              => __( 'All users will be included.', 'notifal' ),
    'placeholder_user_custom_meta'         => __( 'meta_key:value', 'notifal' ),
    'user_custom_meta_help'                => __( 'Enter a custom meta key and value (e.g., user_type:premium) to filter users.', 'notifal' ),
    'available_user_meta_fields'           => __( 'Available User Meta Fields', 'notifal' ),
    'role_administrator'                   => __( 'Administrator', 'notifal' ),
    'role_editor'                          => __( 'Editor', 'notifal' ),
    'role_author'                          => __( 'Author', 'notifal' ),
    'role_contributor'                     => __( 'Contributor', 'notifal' ),
    'role_subscriber'                      => __( 'Subscriber', 'notifal' ),
    'role_customer'                        => __( 'Customer', 'notifal' ),

    // Post fields.
    'label_post_categories'                => __( 'Post Categories', 'notifal' ),
    'label_specific_posts'                 => __( 'Specific Posts', 'notifal' ),
    'label_post_authors'                   => __( 'Post Authors', 'notifal' ),
    'info_all_posts_included'              => __( 'All posts will be included.', 'notifal' ),
    'placeholder_post_custom_meta'         => __( '_thumbnail_id>0', 'notifal' ),
    'available_post_meta_fields'           => __( 'Available Post Meta Fields', 'notifal' ),

    // Page fields.
    'label_specific_pages'                 => __( 'Specific Pages', 'notifal' ),
    'label_page_status'                    => __( 'Page Status', 'notifal' ),
    'label_page_authors'                   => __( 'Page Authors', 'notifal' ),
    'label_page_templates'                 => __( 'Page Templates', 'notifal' ),
    'info_all_pages_included'              => __( 'All pages will be included.', 'notifal' ),
    'page_templates_theme_note'            => __( 'Note: Available templates depend on your theme.', 'notifal' ),
    'placeholder_page_custom_meta'         => __( '_wp_page_template:page-fullwidth.php', 'notifal' ),
    'available_page_meta_fields'           => __( 'Available Page Meta Fields', 'notifal' ),
    'page_template_default'                => __( 'Default Template', 'notifal' ),
    'page_template_full_width'             => __( 'Full Width', 'notifal' ),
    'page_template_with_sidebar'           => __( 'With Sidebar', 'notifal' ),

    // Comment fields.
    'label_comment_status'                 => __( 'Comment Status', 'notifal' ),
    'label_comment_authors'                => __( 'Comment Authors', 'notifal' ),
    'info_all_comments_included'           => __( 'All comments will be included.', 'notifal' ),
    'placeholder_comment_authors'          => __( 'Author name, email, or multiple separated by comma', 'notifal' ),
    'comment_authors_help'                 => __( 'Enter author name(s), email(s), or multiple separated by comma. Leave empty for all authors.', 'notifal' ),
    'placeholder_comment_custom_meta'      => __( 'rating=5', 'notifal' ),
    'available_comment_meta_fields'          => __( 'Available Comment Meta Fields', 'notifal' ),
    'post_type_posts'                      => __( 'Posts', 'notifal' ),
    'post_type_pages'                      => __( 'Pages', 'notifal' ),
    'post_type_products'                   => __( 'Products', 'notifal' ),
    'comment_status_approved'              => __( 'Approved', 'notifal' ),
    'comment_status_unapproved'            => __( 'Unapproved', 'notifal' ),
    'comment_status_spam'                  => __( 'Spam', 'notifal' ),
    'comment_status_trash'                 => __( 'Trash', 'notifal' ),

    // Custom post type fields.
    'label_specific_items'                 => __( 'Specific Items', 'notifal' ),
    'info_all_custom_posttype_included'    => __( 'All custom post type items will be included.', 'notifal' ),
    'custom_posttype_categories_note'      => __( 'Note: Available categories depend on the custom post type\'s taxonomy configuration.', 'notifal' ),
    'placeholder_custom_posttype_meta'     => __( '_custom_field=value', 'notifal' ),
    'available_custom_posttype_meta_fields' => __( 'Available Custom Post Type Meta Fields', 'notifal' ),

    // Shared post/page/custom status labels.
    'status_published'                     => __( 'Published', 'notifal' ),
    'status_draft'                         => __( 'Draft', 'notifal' ),
    'status_pending'                       => __( 'Pending', 'notifal' ),
    'status_private'                       => __( 'Private', 'notifal' ),
    'status_trash'                         => __( 'Trash', 'notifal' ),

    // Validation messages.
    'validate_select_order_status'         => __( 'Please select at least one order status.', 'notifal' ),
    'validate_select_start_end_dates'      => __( 'Please select both start and end dates.', 'notifal' ),
    'validate_select_product'              => __( 'Please select at least one product.', 'notifal' ),
    'validate_custom_filter_empty'         => __( 'Custom filter value cannot be empty.', 'notifal' ),
    'validate_select_category'             => __( 'Please select at least one category.', 'notifal' ),
    'validate_select_user_role'            => __( 'Please select at least one user role.', 'notifal' ),
    'validate_select_user'                 => __( 'Please select at least one user.', 'notifal' ),
    'validate_meta_key_value'              => __( 'Please provide a meta key and value.', 'notifal' ),
    'validate_select_post'                 => __( 'Please select at least one post.', 'notifal' ),
    'validate_select_author'               => __( 'Please select at least one author.', 'notifal' ),
    'validate_select_page'                 => __( 'Please select at least one page.', 'notifal' ),
    'validate_select_page_status'          => __( 'Please select at least one page status.', 'notifal' ),
    'validate_select_template'             => __( 'Please select at least one template.', 'notifal' ),
    'validate_select_comment_status'       => __( 'Please select at least one comment status.', 'notifal' ),
    'validate_select_post_type'            => __( 'Please select at least one post type.', 'notifal' ),
    'validate_enter_author'                => __( 'Please enter an author name or email.', 'notifal' ),
    'validate_select_item'                 => __( 'Please select at least one item.', 'notifal' ),
    'validate_select_status'               => __( 'Please select at least one status.', 'notifal' ),
    'validate_end_date_after_start'        => __( 'End date must be after start date', 'notifal' ),
];
