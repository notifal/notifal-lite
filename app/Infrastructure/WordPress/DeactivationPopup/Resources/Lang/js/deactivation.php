<?php
/**
 * Deactivation Popup JavaScript Language Strings
 *
 * Contains all translatable strings used in the deactivation popup JavaScript.
 *
 * @package Notifal\Infrastructure\WordPress\DeactivationPopup\Resources\Lang\js
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return [
    // Modal strings
    'title' => __( "Before you go, please share why you're deactivating Notifal", 'notifal' ),

    // Button strings
    'submitButton' => __( 'Submit & Deactivate', 'notifal' ),
    'skipButton' => __( 'Skip & Deactivate', 'notifal' ),

    // Status strings
    'processing' => __( 'Processing...', 'notifal' ),
    'deactivating' => __( 'Deactivating...', 'notifal' ),

    // Error strings
    'errorMessage' => __( 'An error occurred. Please try again.', 'notifal' ),
    'validationError' => __( 'Please fill in all required fields.', 'notifal' ),
    'apiWarning' => __( 'Unable to send feedback to our server, but deactivation will proceed.', 'notifal' ),
    'networkWarning' => __( 'Network issue detected, but deactivation will proceed.', 'notifal' ),

    // Prevention message
    'preventionMessage' => __( "Wait! Don't deactivate Notifal. You have to activate both Notifal and Notifal Pro in order for the plugin to work.", 'notifal' ),
];
