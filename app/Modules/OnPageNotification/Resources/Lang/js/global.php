<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return [
    'generic_error'           => __( 'Something went wrong. Please try again.', 'notifal' ),
    'unexpected_response'     => __( 'Unexpected response from server.', 'notifal' ),
    'loading'                 => __( 'Loading...', 'notifal' ),
    'analyzing_template'      => __( 'Analyzing Template...', 'notifal' ),
    'please_wait'            => __( 'Please wait while we analyze the template content.', 'notifal' ),
    'filtering_templates'     => __( 'Filtering templates...', 'notifal' ),
    'error_filtering_templates' => __( 'Error filtering templates. Please try again.', 'notifal' ),
    'error_loading_templates' => __( 'Error loading templates.', 'notifal' ),
    'refreshing_templates'    => __( 'Refreshing templates...', 'notifal' ),
    'templates_refreshed'     => __( 'Templates refreshed successfully.', 'notifal' ),
    'error_refreshing_templates' => __( 'Error refreshing templates. Please try again.', 'notifal' ),
    'template_selected'       => __( 'Template selected successfully.', 'notifal' ),
    'template_deselected'     => __( 'Template deselected.', 'notifal' ),
    'error_selecting_template' => __( 'Error selecting template. Please try again.', 'notifal' ),
    'select_post_type'       => __( 'Please select at least one post type.', 'notifal' ),
    'free_version_limit'     => __( 'You can only have one active notification in the free version. Upgrade to Notifal Pro to activate multiple notifications simultaneously.', 'notifal' ),
    'unexpected_error'       => __( 'An unexpected error occurred. Please try again.', 'notifal' ),
    'select_post_items'      => __( 'Please select at least one post item.', 'notifal' ),
    'displayTypes' => [
        'toast' => [
            'description' => __( 'A small notification that slides in from the side (usually top-right). Perfect for quick messages, success confirmations, or alerts. Users can dismiss it or it auto-hides after a few seconds.', 'notifal' ),
            'conciseDescription' => __( 'Small notification that slides in from the side. Perfect for quick confirmations and alerts.', 'notifal' ),
            'pageTitle' => __( 'Website Page', 'notifal' ),
            'message' => __( 'Order confirmed!', 'notifal' ),
            'contentArea' => __( 'Content area', 'notifal' ),
        ],
        'popup' => [
            'description' => __( 'A modal dialog that appears in the center of the screen with a backdrop. Ideal for important messages, forms, or detailed information. Requires user interaction to dismiss.', 'notifal' ),
            'conciseDescription' => __( 'Modal dialog in the center with backdrop. Ideal for important messages and forms.', 'notifal' ),
            'pageTitle' => __( 'Website Page', 'notifal' ),
            'title' => __( 'Special Offer!', 'notifal' ),
            'subtitle' => __( 'Get 20% off your next purchase', 'notifal' ),
            'button' => __( 'Claim Now', 'notifal' ),
        ],
        'topbar' => [
            'description' => __( 'A full-width banner that slides down from the top of the page. Great for site-wide announcements, promotions, or important alerts. Highly visible but can be dismissed.', 'notifal' ),
            'conciseDescription' => __( 'Full-width banner sliding from top. Great for site-wide announcements and promotions.', 'notifal' ),
            'pageTitle' => __( 'Website Page', 'notifal' ),
            'message' => __( 'Free shipping on orders over $50!', 'notifal' ),
            'contentBelow' => __( 'Page content below', 'notifal' ),
        ],
        'floating' => [
            'description' => __( 'A floating element (usually circular) that stays in a fixed position. Perfect for chat widgets, help buttons, or quick action buttons. Always visible and accessible.', 'notifal' ),
            'conciseDescription' => __( 'Fixed circular element that stays in position. Perfect for chat widgets and help buttons.', 'notifal' ),
            'pageTitle' => __( 'Website Page', 'notifal' ),
            'content' => __( 'Page content', 'notifal' ),
        ],
        'inline' => [
            'description' => __( "Content that appears within the page flow, like a banner or alert box. Integrates naturally with your content and doesn't overlay other elements. Good for contextual information or announcements.", 'notifal' ),
            'conciseDescription' => __( 'Content within page flow like banners. Integrates naturally with your content.', 'notifal' ),
            'pageTitle' => __( 'Website Page', 'notifal' ),
            'headerContent' => __( 'Header content', 'notifal' ),
            'message' => __( 'Limited time offer - 50% off selected items!', 'notifal' ),
            'mainContent' => __( 'Main content area', 'notifal' ),
        ],
        'corner' => [
            'description' => __( 'A small badge or indicator that appears in the corner of the screen. Ideal for notifications count, alerts, or status indicators. Minimal visual impact but highly noticeable.', 'notifal' ),
            'conciseDescription' => __( 'Small badge in corner for notifications count. Minimal visual impact but noticeable.', 'notifal' ),
            'pageTitle' => __( 'Website Page', 'notifal' ),
            'content' => __( 'Page content', 'notifal' ),
        ],
    ],
];
