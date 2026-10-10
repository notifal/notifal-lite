<?php

defined('ABSPATH') || exit;

/**
 * Frontend Forms i18n strings.
 *
 * @since 3.0.0
 * @updated 3.0.0 Add retryHint for Failed result panel accessibility.
 * @author Hossein <hossein@notifal.com>
 */

return [
    'success'    => __('Thank you. Your submission was received.', 'notifal'),
    'error'      => __('Unable to submit the form. Please try again.', 'notifal'),
    'validation' => __('Please complete the required fields.', 'notifal'),
    'captcha'    => __('Please complete the captcha.', 'notifal'),
    'duplicate'      => __('%s has already been submitted.', 'notifal'),
    'duplicateField' => __('%1$s has already been submitted. Please use a different value.', 'notifal'),
    'submitting' => __('Submitting…', 'notifal'),
    // Spoken hint when Failed is visible and the panel is the retry control.
    'retryHint'  => __('Click to try again.', 'notifal'),
];
