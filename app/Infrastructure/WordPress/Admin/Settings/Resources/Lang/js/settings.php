<?php

defined('ABSPATH') || exit;

return [
    // General settings strings
    'save_success' => __('Settings saved successfully!', 'notifal'),
    'save_error' => __('Error saving settings. Please try again.', 'notifal'),
    'reset_success' => __('Settings reset to defaults successfully!', 'notifal'),
    'reset_error' => __('Error resetting settings. Please try again.', 'notifal'),
    'confirm_reset' => __('Are you sure you want to reset all settings to defaults?', 'notifal'),
    'save_button' => __('Save Settings', 'notifal'),
    'reset_button' => __('Reset to Defaults', 'notifal'),
    'saving' => __('Saving...', 'notifal'),
    'resetting' => __('Resetting...', 'notifal'),
    'saved' => __('Saved!', 'notifal'),
    'reset' => __('Reset!', 'notifal'),
    'security_error' => __('Security check failed. Please refresh the page and try again.', 'notifal'),
    'permission_error' => __('You do not have permission to save settings.', 'notifal'),
    'network_error' => __('Network error. Please check your connection and try again.', 'notifal'),

    // Post Type Generator strings
    'save_to_add_tags' => sprintf( __( 'To add these tags to current notifal tags, please click %s at the bottom of the page.', 'notifal' ), '"' . __( 'Save Settings', 'notifal' ) . '"' ),
    'select_all_tags' => __('Select All Tags', 'notifal'),
    'deselect_all_tags' => __('Deselect All Tags', 'notifal'),
    'select_all_category' => __('Select All', 'notifal'),
    'deselect_all_category' => __('Deselect All', 'notifal'),
    'confirm_remove_tags' => __('Are you sure you want to remove all generated tags for this post type? This action cannot be undone.', 'notifal'),
    'tags_removed_success' => __('Generated tags removed successfully.', 'notifal'),
    'tags_saved_success' => __('Generated tags saved successfully.', 'notifal'),
    'no_tags_selected' => __('Please select at least one tag to save.', 'notifal'),
    'already_generated' => __('Tags already generated for this post type. Remove existing tags first to generate new ones.', 'notifal'),
    'remove_generated_tags' => __('Remove Generated Tags', 'notifal'),
    'view_generated_tags' => __('View Generated Tags', 'notifal'),
    'no_posttypes_selected' => __('Please select at least one post type to generate tags.', 'notifal'),
    'tags_generated_success' => __('Tags generated and saved successfully!', 'notifal'),
    'generation_error' => __('Error generating tags. Please try again.', 'notifal'),
    'view_tags_error' => __('Error loading tags. Please try again.', 'notifal'),
    'tags_label' => __('Tags', 'notifal'),

    // Field categories
    'standard_fields' => __('Standard Fields', 'notifal'),
    'meta_fields' => __('Meta Fields', 'notifal'),
    'taxonomy_fields' => __('Taxonomy Fields', 'notifal'),

    // Custom post type tags description
    'custom_posttype_tags_description' => __('Tags for {post_type} like {{post_type_name}_title}, {{post_type_name}_content}, etc.', 'notifal'),

    // Reset feedback
    'settings_reset_to_defaults' => __('Settings reset to defaults.', 'notifal'),
    'click_save_to_apply' => sprintf( __( 'Click %s to apply changes.', 'notifal' ), '"' . __( 'Save Settings', 'notifal' ) . '"' ),

    // Post types loading
    'failed_to_load_post_types' => __('Failed to load post types', 'notifal'),
    'network_error_loading_posttypes' => __('Network error while loading post types', 'notifal'),
    'refresh_page' => __('Refresh Page', 'notifal'),

    // Modal strings
    'loading_tags' => __('Loading tags...', 'notifal'),
    'close' => __('Close', 'notifal'),
    'no_tags_found' => __('No tags found for this post type.', 'notifal'),
    'processing' => __('Processing...', 'notifal'),

    // Tag generation warnings
    'complete_tag_generation_first' => __('Complete tag generation first!', 'notifal'),
    'selected_post_types_not_generated' => __("You have selected post types for tag generation but haven't generated them yet. Please either:", 'notifal'),
    'click_generate_tags_option' => __( '• First option: Click "Generate Tags" to create and save them.', 'notifal' ),
    'uncheck_post_types_option' => __( "• Second option: Uncheck the post types if you don't want to generate tags.", 'notifal' ),
    'then_try_saving_again' => __('Then try saving again.', 'notifal'),

    // Generating tags button states
    'generating' => __('Generating...', 'notifal'),
    'generate_tags' => __('Generate Tags', 'notifal'),

    'notifal_pro_required' => '🚀 ' . __('Notifal Pro Required', 'notifal'),
    'upgrade_to_pro' => '🚀 ' . __('Upgrade to Pro', 'notifal'),
    'maybe_later' => __('Maybe Later', 'notifal'),
    'unlock_advanced_features' => __('Upgrade to Notifal Pro to unlock:', 'notifal'),
    'advanced_tag_generation' => '✨ ' . __('Advanced Tag Generation', 'notifal'),
    'multiple_display_rules' => '🎯 ' . __('Multiple Display Rules', 'notifal'),
    'enhanced_analytics' => '📊 ' . __('Enhanced Analytics', 'notifal'),
    'custom_css_styling' => '🔧 ' . __('Custom CSS & Advanced Styling', 'notifal'),
    'unlimited_notifications' => '🚀 ' . __('Unlimited Notifications', 'notifal'),
    'comment_tags_more' => '💬 ' . __('Comment Tags & More', 'notifal'),
];
