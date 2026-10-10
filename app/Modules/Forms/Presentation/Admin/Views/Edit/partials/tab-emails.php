<?php

/**
 * Form edit: emails tab (admin + visitor notification panels).
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 *
 * @var array<string, mixed> $meta Form meta for admin.
 * @var array<int, array<string, mixed>> $fields Form field definitions.
 * @var array<string, mixed> $emailDefaults Default template bundle.
 * @var array<string, mixed> $emailVariables Merge tags for UI.
 */

defined('ABSPATH') || exit;
?>

<div class="notifal-tab notifal-tab-panel notifal-form-tab-panel" id="notifal-form-tab-emails" data-tab-panel="emails">
    <div class="notifal-form-section notifal-form-section--flush">
        <h2 class="notifal-form-section__title"><?php echo esc_html__('Emails', 'notifal'); ?></h2>
        <p class="notifal-form-section__description">
            <?php echo esc_html__('Configure who receives submission alerts and optional confirmation emails to visitors. Customize subjects, message templates, and use field variables.', 'notifal'); ?>
        </p>
    </div>

    <div class="notifal-form-panels">
        <?php
        $panelId = 'notifal-form-email-admin';
        $panelRole = 'admin';
        $panelTitle = __('Admin notification', 'notifal');
        $panelDesc = __('Sent to you when someone submits this form. Includes all submitted field values.', 'notifal');
        $panelEnabled = !isset($meta['admin_email_enabled']) || !empty($meta['admin_email_enabled']);
        $enableFieldName = 'admin_email_enabled';
        include __DIR__ . '/email-template-panel.php';

        $panelId = 'notifal-form-email-visitor';
        $panelRole = 'visitor';
        $panelTitle = __('Visitor confirmation', 'notifal');
        $panelDesc = __('Optional thank-you email sent to the person who submitted the form.', 'notifal');
        $panelEnabled = !empty($meta['confirmation_email_enabled']);
        $enableFieldName = 'confirmation_email_enabled';
        include __DIR__ . '/email-template-panel.php';
        ?>
    </div>
</div>
