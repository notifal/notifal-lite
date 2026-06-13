<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return [
    // Timing Preview Messages
    'preview_loading'                     => __( 'Loading...', 'notifal' ),
    'preview_trigger_immediate'           => __( 'Trigger: Immediately on page load', 'notifal' ),
    'preview_trigger_delay'               => __( 'Trigger: After {seconds} seconds delay', 'notifal' ),
    'preview_trigger_scroll'              => __( 'Trigger: When user scrolls {percentage}% of the page', 'notifal' ),
    'preview_trigger_exit'                => __( 'Trigger: When user tries to leave the page', 'notifal' ),
    'preview_trigger_idle'                => __( 'Trigger: After {seconds} seconds of user inactivity', 'notifal' ),
    'preview_trigger_custom'              => __( 'Trigger: Custom trigger condition', 'notifal' ),
    'preview_trigger_custom_delay'        => __( 'Trigger: {condition} (with {delay}s delay)', 'notifal' ),
    
    'preview_duration_until_dismissed'    => __( 'Duration: Until user dismisses the notification', 'notifal' ),
    'preview_duration_auto_hide'          => __( 'Duration: Auto-hide after {seconds} seconds', 'notifal' ),
    'preview_duration_persistent'         => __( 'Duration: Persistent for {seconds} seconds', 'notifal' ),
    'preview_duration_persistent_forever' => __( 'Duration: Persistent until manually dismissed', 'notifal' ),
    
    'preview_frequency_always'            => __( 'Frequency: Show every time', 'notifal' ),
    'preview_frequency_once_session'      => __( 'Frequency: Per user session (shared across tabs)', 'notifal' ),
    // Removed deprecated frequency previews (per day/week/month and custom interval)

    // Schedule Preview Messages
    'preview_schedule_active' => __( 'Schedule: Active from {start_date} to {end_date}', 'notifal' ),
    'preview_schedule_start_only' => __( 'Schedule: Starts on {start_date}', 'notifal' ),
    'preview_schedule_end_only' => __( 'Schedule: Ends on {end_date}', 'notifal' ),
    'preview_schedule_none' => __( 'Schedule: Always active (no schedule set)', 'notifal' ),
    'preview_schedule_pending_dates' => __( 'Schedule: Active — set a start and/or end date and time', 'notifal' ),
    'preview_schedule_campaign' => __( 'Schedule: Managed by campaign "{campaign_name}"', 'notifal' ),

    // Schedule Validation
    'validation_start_before_end' => __( 'Start date must be before end date', 'notifal' ),
    'validation_start_must_be_future' => __( 'Start date and time must be in the future when you first set the schedule.', 'notifal' ),
    
    // Field Dependencies
    'field_depends_on_timing'             => __( 'This setting depends on the "Show Timing" selection', 'notifal' ),
    'field_depends_on_duration'           => __( 'This setting depends on the "Display Duration" selection', 'notifal' ),
    'field_depends_on_frequency'          => __( 'This setting depends on the "Show Frequency" selection', 'notifal' ),
    
    // Validation Messages
    // translators: {count} is replaced in JavaScript with the number of issues (integer).
    'validation_errors_found'             => __( 'We found {count} issue(s) with your timing settings', 'notifal' ),
    'validation_delay_required'           => __( 'Delay is required when "After Delay" is selected', 'notifal' ),
    'validation_scroll_required'          => __( 'Scroll percentage is required when "On Scroll" is selected', 'notifal' ),
    'validation_idle_required'            => __( 'Idle time is required when "After User Idle" is selected', 'notifal' ),
    'validation_auto_hide_required'       => __( 'Auto-hide duration is required when "Auto Hide" is selected', 'notifal' ),
    'validation_persistent_required'      => __( 'Persistent duration is required when "Persistent" is selected', 'notifal' ),
    'validation_custom_frequency_required' => __( 'Custom frequency days is required when "Custom Frequency" is selected', 'notifal' ),
    // Removed validation messages for deprecated frequency options
    'validation_custom_trigger_type_required' => __( 'Custom trigger type is required when "Custom Trigger" is selected', 'notifal' ),
    'validation_custom_element_selector_required' => __( 'Element selector is required for JavaScript event triggers', 'notifal' ),
    'validation_visibility_element_required' => __( 'Element to monitor is required for visibility triggers', 'notifal' ),
    'validation_custom_condition_required' => __( 'Custom JavaScript condition is required', 'notifal' ),
    'validation_multiple_triggers_required' => __( 'Multiple triggers configuration is required', 'notifal' ),
    'validation_multiple_triggers_invalid_json' => __( 'Multiple triggers configuration must be valid JSON format', 'notifal' ),
    
    // Success Messages
    'timing_settings_saved'               => __( 'Timing settings saved successfully.', 'notifal' ),
    'timing_settings_updated'             => __( 'Timing settings updated successfully.', 'notifal' ),
    
    // Error Messages
    'timing_settings_save_error'          => __( 'Error saving timing settings. Please try again.', 'notifal' ),
    'timing_settings_validation_error'    => __( 'Please fix the validation errors before saving.', 'notifal' ),
    
    // Help Text
    'help_show_timing'                    => __( 'Choose when the notification should appear on the page. Different options provide various user experience approaches.', 'notifal' ),
    'help_delay_seconds'                  => __( 'The number of seconds to wait before showing the notification after the page loads.', 'notifal' ),
    'help_scroll_percentage'              => __( 'The percentage of the page the user must scroll before the notification appears.', 'notifal' ),
    'help_idle_seconds'                   => __( 'The number of seconds the user must be inactive before the notification appears.', 'notifal' ),
    'help_display_duration'               => __( 'Choose how long the notification should remain visible to the user.', 'notifal' ),
    'help_auto_hide_seconds'              => __( 'The number of seconds before the notification automatically disappears.', 'notifal' ),
    'help_persistent_duration'            => __( 'The number of seconds to keep the notification persistent. Set to 0 for forever.', 'notifal' ),
    'help_show_frequency'                 => __( 'Control how often this notification is shown to users to avoid overwhelming them.', 'notifal' ),
    'help_custom_frequency_days'          => __( 'The number of days between showing this notification to the same user.', 'notifal' ),
    'help_max_shows_per_session'          => __( 'Maximum times to show per browser session (shared across tabs; count persists across page refreshes). After the limit is reached, the notification stays hidden until 30 minutes pass without a show or manual close.', 'notifal' ),
    // Removed help texts for deprecated frequency options
    'help_respect_user_preferences'       => __( 'Check if the user has disabled notifications in their browser and respect that choice.', 'notifal' ),
    'help_pause_on_tab_inactive'          => __( 'Pause the notification when the browser tab is not active to avoid interrupting users.', 'notifal' ),
    'help_resume_on_tab_active'           => __( 'Resume the notification when the browser tab becomes active again.', 'notifal' ),
    'resume_requires_pause'               => __( 'Resume on Tab Active requires Pause on Tab Inactive to be enabled.', 'notifal' ),
    'help_prevent_multiple_instances'     => __( 'Ensure only one instance of this notification is shown at a time.', 'notifal' ),
    'help_enable_priority'                => __( 'Control notification display order when multiple notifications have "Prevent Multiple Instances" enabled. When disabled, the system automatically prioritizes based on content type and features.', 'notifal' ),
    'help_priority_level'                 => __( 'Set notification priority (1-10, where 10 is highest). When multiple notifications compete for display, the highest priority notification will be shown. Equal priorities use automatic smart logic.', 'notifal' ),

    'help_clear_session_on_logout'        => __( 'Clear notification session data when the user logs out for privacy.', 'notifal' ),

    // Schedule Help Text
    'help_schedule_enabled' => __( 'Turn on to set start and end dates for this notification only. If a campaign is linked (General tab), it controls the schedule instead and this option stays off.', 'notifal' ),
    'help_start_date' => __( 'The notification will not be shown before this date and time.', 'notifal' ),
    'help_end_date' => __( 'The notification will stop showing after this date and time. Leave empty for no end date.', 'notifal' ),

    'campaign_schedule_intro' => __( 'A campaign controls when this notification may run. Enable schedule stays off here on purpose.', 'notifal' ),
    'campaign_schedule_loading_detail' => __( 'Loading campaign dates…', 'notifal' ),
    'campaign_schedule_toggle_title' => __( 'Unavailable while a campaign controls the schedule. Change or remove the campaign under General.', 'notifal' ),
    
    // Custom Trigger Help Text
    'help_custom_trigger_type'            => __( 'Choose the type of custom trigger you want to configure. Each type provides different ways to trigger notifications.', 'notifal' ),
    'help_custom_js_event'                => __( 'Select the JavaScript event that should trigger the notification when it occurs on the specified element.', 'notifal' ),
    'help_custom_element_selector'        => __( 'CSS selector for the element that should trigger the notification. Use # for IDs, . for classes, or [attr] for attributes.', 'notifal' ),
    'help_visibility_element_selector'     => __( 'CSS selector for the element to monitor for visibility. The notification will trigger when this element becomes visible.', 'notifal' ),
    'help_visibility_threshold'            => __( 'Percentage of the element that must be visible in the viewport to trigger the notification.', 'notifal' ),
    'help_custom_js_condition'            => __( 'Write custom JavaScript code that returns true or false. The notification will trigger when the condition returns true.', 'notifal' ),
    'help_multiple_triggers_config'       => __( 'Define multiple triggers in JSON format. Each trigger should have type, selector, and optional conditions.', 'notifal' ),
    'help_custom_trigger_delay'           => __( 'Additional delay after the custom trigger occurs before showing the notification.', 'notifal' ),
]; 
