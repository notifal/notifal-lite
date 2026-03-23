<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return [
    'campaign_title_required' => __( 'Campaign title is required.', 'notifal' ),
    'validation_start_before_end' => __( 'Start date must be before end date', 'notifal' ),
    'validation_start_must_be_future' => __( 'Start date and time must be in the future when you first set the schedule.', 'notifal' ),

    'save_success' => __( 'Campaign saved successfully.', 'notifal' ),
    'save_error' => __( 'An error occurred while saving the campaign. Please try again.', 'notifal' ),
    'saving' => __( 'Saving…', 'notifal' ),

    'onpage_search_placeholder' => __( 'Type to search on-page notifications…', 'notifal' ),
    'onpage_search_aria' => __( 'Search on-page notifications to assign', 'notifal' ),
    'onpage_search_loading' => __( 'Searching…', 'notifal' ),
    'onpage_search_no_results' => __( 'No notifications match your search.', 'notifal' ),
    'onpage_search_type_more' => __( 'Type at least 2 characters to search.', 'notifal' ),
    'onpage_selected_heading' => __( 'Selected notifications', 'notifal' ),
    'onpage_remove_aria' => __( 'Remove from campaign', 'notifal' ),
    'onpage_open_edit_aria' => __( 'Edit notification in a new tab', 'notifal' ),
    'onpage_search_error' => __( 'Search failed. Please try again.', 'notifal' ),
];
