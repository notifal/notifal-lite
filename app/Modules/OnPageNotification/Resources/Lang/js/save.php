<?php

if (!defined('ABSPATH')) {
    exit;
}

return [
    // Save Status Messages
    'saving'                    => __( 'Saving notification...', 'notifal' ),
    'save_success'              => __( 'Notification saved successfully!', 'notifal' ),
    'save_error'                => __( 'Error saving notification. Please try again.', 'notifal' ),
    'save_updated'              => __( 'Notification updated successfully!', 'notifal' ),
    'save_created'              => __( 'Notification created successfully!', 'notifal' ),
    
    // Validation Messages
    'validation_error'          => __( 'Please fix the validation errors before saving.', 'notifal' ),
    'required_field'            => __( 'This field is required.', 'notifal' ),
    'invalid_title'             => __( 'Notification title must be less than 255 characters.', 'notifal' ),
    'invalid_json'              => __( 'Invalid data format. Please refresh the page and try again.', 'notifal' ),
    'title_required'            => __( 'Notification title is required.', 'notifal' ),
    'title_too_long'            => __( 'Notification title must be less than 255 characters.', 'notifal' ),
    'labels_required'            => __( 'At least one notification label must be selected.', 'notifal' ),
    
    // Error Messages
    'security_error'            => __( 'Security check failed. Please refresh the page and try again.', 'notifal' ),
    'permission_error'          => __( 'You do not have permission to save notifications.', 'notifal' ),
    'network_error'             => __( 'Network error. Please check your connection and try again.', 'notifal' ),
    'unknown_error'             => __( 'An unexpected error occurred. Please try again.', 'notifal' ),
    'notification_not_found'    => __( 'Notification not found.', 'notifal' ),
    'invalid_notification_id'   => __( 'Invalid notification ID.', 'notifal' ),
    'load_data_error'           => __( 'Failed to load notification data.', 'notifal' ),
    
    // Auto-save Messages
    'auto_save_enabled'         => __( 'Auto-save enabled', 'notifal' ),
    'auto_save_disabled'        => __( 'Auto-save disabled', 'notifal' ),
    'auto_save_success'         => __( 'Auto-saved successfully', 'notifal' ),
    'auto_save_error'           => __( 'Auto-save failed', 'notifal' ),
    
    // Form Messages
    'form_has_changes'          => __( 'You have unsaved changes. Are you sure you want to leave?', 'notifal' ),
    'form_saved'                => __( 'All changes have been saved.', 'notifal' ),
    'form_unsaved'              => __( 'You have unsaved changes.', 'notifal' ),
    
    // Button States
    'save_button_text'          => __( 'Save Notification', 'notifal' ),
    'saving_button_text'        => __( 'Saving...', 'notifal' ),
    'saved_button_text'         => __( 'Saved!', 'notifal' ),
    'save_draft_button_text'    => __( 'Save as Draft', 'notifal' ),
    'publish_button_text'       => __( 'Publish', 'notifal' ),
    
    // Confirmation Messages
    'confirm_leave'             => __( 'You have unsaved changes. Are you sure you want to leave this page?', 'notifal' ),
    'confirm_delete'            => __( 'Are you sure you want to delete this notification? This action cannot be undone.', 'notifal' ),
    'confirm_discard'           => __( 'Are you sure you want to discard all changes?', 'notifal' ),
    
    // Success Messages
    'draft_saved'               => __( 'Draft saved successfully.', 'notifal' ),
    'published_success'         => __( 'Notification published successfully!', 'notifal' ),
    'unpublished_success'       => __( 'Notification unpublished successfully.', 'notifal' ),
    'duplicated_success'        => __( 'Notification duplicated successfully.', 'notifal' ),
    'draft_saved_no_template'   => __( 'Notification saved as draft. You can select a template later and enable the notification when ready.', 'notifal' ),
    
    // Field Validation Messages
    'appearance_settings_invalid' => __( 'Appearance settings contain invalid data.', 'notifal' ),
    'behavior_settings_invalid'   => __( 'Behavior settings contain invalid data.', 'notifal' ),
    'timing_settings_invalid'     => __( 'Timing settings contain invalid data.', 'notifal' ),
    'content_source_invalid'      => __( 'Content source settings contain invalid data.', 'notifal' ),
    'display_rules_invalid'       => __( 'Display rules contain invalid data.', 'notifal' ),
    
    // Help Text
    'help_auto_save'            => __( 'Changes are automatically saved every 30 seconds.', 'notifal' ),
    'help_manual_save'          => sprintf( __( 'Click %s to manually save your changes.', 'notifal' ), '"' . __( 'Save Notification', 'notifal' ) . '"' ),
    'help_draft_mode'           => __( 'Draft mode allows you to save without publishing the notification.', 'notifal' ),
    'help_publish_mode'         => __( 'Publish mode makes the notification live and visible to users.', 'notifal' ),
    
    // Loading Messages
    'loading_notification'      => __( 'Loading notification data...', 'notifal' ),
    'loading_settings'          => __( 'Loading settings...', 'notifal' ),
    'loading_templates'         => __( 'Loading templates...', 'notifal' ),
    
    // Network Messages
    'connection_lost'           => __( 'Connection lost. Please check your internet connection.', 'notifal' ),
    'connection_restored'       => __( 'Connection restored. You can now save your changes.', 'notifal' ),
    'retry_connection'          => __( 'Retrying connection...', 'notifal' ),
    
    // Debug Messages (for development)
    'debug_save_data'           => __( 'Save data prepared successfully.', 'notifal' ),
    'debug_validation_passed'   => __( 'Validation passed successfully.', 'notifal' ),
    'debug_ajax_sent'           => __( 'AJAX request sent successfully.', 'notifal' ),
    'debug_response_received'   => __( 'Response received from server.', 'notifal' ),
]; 
