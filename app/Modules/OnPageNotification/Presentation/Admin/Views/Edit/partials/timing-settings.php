<?php

use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\TimingSettingsService;
use Notifal\Modules\OnPageNotification\Application\Support\ScheduleDateTimeHelper;
use Notifal\Shared\AdminUI\Fields\FieldRenderer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Timing Settings Tab
 *
 * Handles the display and configuration of notification timing settings
 * including display timing, duration controls, frequency management,
 * and advanced timing options for OnPage Notifications.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Views\Edit\partials
 */

/**
 * Initialize timing settings service and retrieve merged settings
 * Merges default settings with saved notification data if in edit mode
 */
$timingService = notifal_app(TimingSettingsService::class);

// Retrieve default timing settings from service
$timing_settings = $timingService->getDefaultSettings();

// Merge with saved notification settings if editing an existing notification
if ($is_edit && isset($notification_data['timing_settings']) && is_array($notification_data['timing_settings'])) {
    $timing_settings = array_merge($timing_settings, $notification_data['timing_settings']);
}

// Stored schedule boundaries are UTC (`Z`); datetime-local expects wall time in the site timezone.
if ( ! empty( $timing_settings['start_date'] ) ) {
    $timing_settings['start_date'] = ScheduleDateTimeHelper::storedToDatetimeLocalForAdmin( (string) $timing_settings['start_date'] );
}
if ( ! empty( $timing_settings['end_date'] ) ) {
    $timing_settings['end_date'] = ScheduleDateTimeHelper::storedToDatetimeLocalForAdmin( (string) $timing_settings['end_date'] );
}

/**
 * Current moment in the WordPress site timezone, using General → date/time formats.
 *
 * Notification schedule boundaries use the same timezone as {@see ScheduleDateTimeHelper} (`wp_timezone()`).
 *
 * @var string
 */
$timing_site_now_display = wp_date(
	sprintf( '%s %s', get_option( 'date_format' ), get_option( 'time_format' ) ),
	null,
	wp_timezone()
);

/**
 * Site timezone identifier or offset string (Settings → General), shown next to the current time for context.
 *
 * @var string
 */
$timing_site_timezone_label = wp_timezone_string();

// Define current tab identifier for hooks and styling
$tab = 'timing';

// Check if Pro version is activated for conditional feature display
$is_pro_active = PluginDetector::isNotifalProActive();

do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_BEFORE, $tab));
?>

<div class="notifal-settings-section notifal-<?php echo esc_attr( $tab ); ?>-settings">

    <h1><?php esc_html_e( 'Timing Settings', 'notifal' ); ?></h1>

    <div class="notifal-tab-panel-fields notifal-mt-20">

        <!-- Schedule Settings -->
        <div class="notifal-field-group">
            <h3><?php esc_html_e( 'Schedule', 'notifal' ); ?></h3>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_BEFORE, $tab, 'schedule')); ?>

            <?php
            FieldRenderer::toggle(
                'schedule_enabled',
                ! empty($timing_settings['schedule_enabled']),
                __( 'Enable Schedule', 'notifal' ),
                __( 'Turn on to set start and end dates for this notification only. If a campaign is linked (General tab), it controls the schedule instead and this option stays off.', 'notifal' )
            );

            echo '<div class="notifal-campaign-current-site-time" role="status">';
            echo '<p class="notifal-campaign-current-site-time-text">';
            echo esc_html(
                sprintf(
                    /* translators: 1: Current date and time (site timezone). 2: Timezone name or offset from Settings → General. */
                    __( 'Current site time: %1$s (%2$s)', 'notifal' ),
                    $timing_site_now_display,
                    $timing_site_timezone_label
                )
            );
            echo '</p>';
            echo '</div>';

            FieldRenderer::datetimeInput(
                'start_date',
                $timing_settings['start_date'],
                __( 'Start Date', 'notifal' ),
                __( 'The notification will not be shown before this date and time.', 'notifal' ),
                [
                    'input' => [
                        'data-depends-on' => 'schedule_enabled',
                        'data-depends-value' => '1'
                    ]
                ]
            );

            FieldRenderer::datetimeInput(
                'end_date',
                $timing_settings['end_date'],
                __( 'End Date (Optional)', 'notifal' ),
                __( 'The notification will stop showing after this date and time. Leave empty for no end date.', 'notifal' ),
                [
                    'input' => [
                        'data-depends-on' => 'schedule_enabled',
                        'data-depends-value' => '1'
                    ]
                ]
            );
            ?>

            <div class="notifal-field-wrapper notifal-direction-column notifal-hidden" id="notifal-campaign-schedule-info">
                <div class="notifal-help-text notifal-campaign-schedule-banner">
                    <p class="notifal-campaign-schedule-info-lead"></p>
                    <p class="notifal-campaign-schedule-info-detail"></p>
                </div>
            </div>

            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_AFTER, $tab, 'schedule')); ?>
        </div>

        <!-- Display Timing Settings -->
        <div class="notifal-field-group">
            <h3><?php esc_html_e( 'Display Timing', 'notifal' ); ?></h3>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_BEFORE, $tab, 'display_timing')); ?>

            <?php
            $pro_label = ! $is_pro_active ? ' (PRO)' : '';

            // When to Show
            FieldRenderer::select(
                'show_timing',
                [
                    ['value' => 'immediate', 'label' => __( 'Immediately on Page Load', 'notifal' )],
                    ['value' => 'delay', 'label' => __( 'After Delay', 'notifal' )],
                    [
                        'value' => 'scroll',
                        'label' => __( 'On Scroll', 'notifal' ) . $pro_label,
                        'data-pro-feature' => 'scroll',
                        'disabled' => !$is_pro_active
                    ],
                    [
                        'value' => 'exit_intent',
                        'label' => __( 'On Exit Intent', 'notifal' ) . $pro_label,
                        'data-pro-feature' => 'exit_intent',
                        'disabled' => !$is_pro_active
                    ],
                    [
                        'value' => 'idle',
                        'label' => __( 'After User Idle', 'notifal' ) . $pro_label,
                        'data-pro-feature' => 'idle',
                        'disabled' => !$is_pro_active
                    ],
                    [
                        'value' => 'custom',
                        'label' => __( 'Custom Trigger', 'notifal' ) . $pro_label,
                        'data-pro-feature' => 'custom',
                        'disabled' => !$is_pro_active
                    ],
                ],
                $timing_settings['show_timing'],
                __( 'Show Timing', 'notifal' ),
                __( 'When should the notification appear on the page. Advanced timing options are Pro features.', 'notifal' )
            );

            // Delay Settings (conditional)
            FieldRenderer::numberInput(
                'delay_seconds',
                $timing_settings['delay_seconds'],
                __( 'Delay (seconds)', 'notifal' ),
                __( 'How long to wait before showing the notification', 'notifal' ),
                ['min' => 0, 'max' => 60, 'step' => 1, 'data-depends-on' => 'show_timing', 'data-depends-value' => 'delay']
            );

            // Scroll Percentage (conditional)
            FieldRenderer::numberInput(
                'scroll_percentage',
                $timing_settings['scroll_percentage'],
                __( 'Scroll Percentage', 'notifal' ),
                __( 'Percentage of page scrolled before showing notification', 'notifal' ),
                ['min' => 1, 'max' => 100, 'step' => 1, 'data-depends-on' => 'show_timing', 'data-depends-value' => 'scroll']
            );

            // Idle Time (conditional)
            FieldRenderer::numberInput(
                'idle_seconds',
                $timing_settings['idle_seconds'],
                __( 'Idle Time (seconds)', 'notifal' ),
                __( 'How long user should be idle before showing notification', 'notifal' ),
                ['min' => 5, 'max' => 300, 'step' => 5, 'data-depends-on' => 'show_timing', 'data-depends-value' => 'idle']
            );

            // Custom Trigger Configuration (conditional)
            FieldRenderer::select(
                'custom_trigger_type',
                [
                    ['value' => 'javascript_event', 'label' => __( 'JavaScript Event', 'notifal' )],
                    ['value' => 'element_visible', 'label' => __( 'Element Visibility', 'notifal' )],
                    ['value' => 'custom_condition', 'label' => __( 'Custom Condition', 'notifal' )],
                    ['value' => 'multiple_triggers', 'label' => __( 'Multiple Triggers', 'notifal' )],
                ],
                $timing_settings['custom_trigger_type'],
                __( 'Custom Trigger Type', 'notifal' ),
                __( 'Choose the type of custom trigger you want to configure', 'notifal' ),
                ['data-depends-on' => 'show_timing', 'data-depends-value' => 'custom']
            );

            // JavaScript Event Configuration
            FieldRenderer::select(
                'custom_js_event',
                [
                    ['value' => 'click', 'label' => __( 'Click', 'notifal' )],
                    ['value' => 'hover', 'label' => __( 'Hover', 'notifal' )],
                ],
                $timing_settings['custom_js_event'],
                __( 'JavaScript Event', 'notifal' ),
                __( 'Select the JavaScript event that should trigger the notification', 'notifal' ),
                ['data-depends-on' => 'custom_trigger_type', 'data-depends-value' => 'javascript_event']
            );

            // Element Selector for Event
            FieldRenderer::textInput(
                'custom_element_selector',
                $timing_settings['custom_element_selector'],
                __( 'Element Selector', 'notifal' ),
                __( 'CSS selector for the element (e.g., #button, .class, [data-attr])', 'notifal' ),
                [
                    'placeholder' => __( 'e.g., #buy-button, .cta-button, [data-trigger]', 'notifal' ),
                    'data-depends-on' => 'custom_trigger_type',
                    'data-depends-value' => 'javascript_event'
                ]
            );

            // Element Visibility Configuration
            FieldRenderer::textInput(
                'visibility_element_selector',
                $timing_settings['visibility_element_selector'],
                __( 'Element to Monitor', 'notifal' ),
                __( 'CSS selector for the element to monitor for visibility. Use . for class names and # for IDs (e.g., .my-class or #my-id)', 'notifal' ),
                [
                    'placeholder' => __( 'e.g., #product-image, .hero-section', 'notifal' ),
                    'data-depends-on' => 'custom_trigger_type',
                    'data-depends-value' => 'element_visible'
                ]
            );

            FieldRenderer::numberInput(
                'visibility_threshold',
                $timing_settings['visibility_threshold'],
                __( 'Visibility Threshold (%)', 'notifal' ),
                __( 'Percentage of element that must be visible to trigger', 'notifal' ),
                [
                    'min' => 1, 'max' => 100, 'step' => 1,
                    'data-depends-on' => 'custom_trigger_type',
                    'data-depends-value' => 'element_visible'
                ]
            );

            // Custom Condition Configuration
            FieldRenderer::textarea(
                'custom_js_condition',
                $timing_settings['custom_js_condition'],
                __( 'Custom JavaScript Condition', 'notifal' ),
                sprintf(
                    /* translators: %s: example JavaScript condition (do not translate the code) */
                    __( 'Enter a JavaScript condition (expression) that evaluates to true/false. Do not include "return" or a trailing semicolon (;). Example: %s', 'notifal' ),
                    'window.innerWidth < 768'
                ),
                [
                    'placeholder' => '// ' . __( 'Example', 'notifal' ) . ': window.innerWidth < 768',
                    'rows' => 4,
                    'data-depends-on' => 'custom_trigger_type',
                    'data-depends-value' => 'custom_condition'
                ]
            );

            // Multiple Triggers Configuration
            FieldRenderer::textarea(
                'multiple_triggers_config',
                $timing_settings['multiple_triggers_config'],
                __( 'Multiple Triggers Configuration', 'notifal' ),
                sprintf(
                    /* translators: %s: JSON example (do not translate the code) */
                    __( 'Define multiple triggers in JSON format. Supported types: "click" (CSS selector + optional delay in seconds), "hover" (CSS selector + optional delay in seconds), "scroll" (selector must be "window" + threshold percentage), "visibility" (CSS selector + threshold 0-1). Example: %s', 'notifal' ),
                    '[{"type": "click", "selector": "#buy-button", "delay": 1}, {"type": "hover", "selector": ".product-card", "delay": 0.5}, {"type": "scroll", "selector": "window", "threshold": 75}, {"type": "visibility", "selector": ".hero-section", "threshold": 0.5}]'
                ),
                [
                    'placeholder' => '[{"type": "click", "selector": "#buy-button", "delay": 1}, {"type": "hover", "selector": ".product-card", "delay": 0.5}, {"type": "scroll", "selector": "window", "threshold": 75}]',
                    'rows' => 6,
                    'data-depends-on' => 'custom_trigger_type',
                    'data-depends-value' => 'multiple_triggers'
                ]
            );

            // Custom Trigger Delay
            FieldRenderer::numberInput(
                'custom_trigger_delay',
                $timing_settings['custom_trigger_delay'],
                __( 'Trigger Delay (seconds)', 'notifal' ),
                __( 'Additional delay after custom trigger before showing notification', 'notifal' ),
                [
                    'min' => 0, 'max' => 60, 'step' => 0.1,
                    'data-depends-on' => 'show_timing',
                    'data-depends-value' => 'custom'
                ]
            );

            // Custom Trigger Help - This should only show when custom trigger is selected
            ?>
            <div class="notifal-field-wrapper notifal-direction-column notifal-hidden" id="custom-trigger-help">
                <div class="notifal-help-text">
                    <p><?php esc_html_e( 'Custom triggers allow you to show notifications based on specific user interactions or page conditions. Use CSS selectors to target elements and JavaScript events or conditions to define when the notification should appear.', 'notifal' ); ?></p>
                </div>
            </div>
            <?php
            ?>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_AFTER, $tab, 'display_timing')); ?>
        </div>

        <!-- Display Duration Settings -->
        <div class="notifal-field-group">
            <h3><?php esc_html_e( 'Display Duration', 'notifal' ); ?></h3>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_BEFORE, $tab, 'display_duration')); ?>
            
            <?php
            // Display Duration Type
            FieldRenderer::select(
                'display_duration',
                [
                    ['value' => 'until_dismissed', 'label' => __( 'Manual Close Only', 'notifal' )],
                    ['value' => 'auto_hide', 'label' => __( 'Auto-Hide Only', 'notifal' )],
                    ['value' => 'persistent', 'label' => __( 'Auto-Hide + Manual Close', 'notifal' )],
                ],
                $timing_settings['display_duration'],
                __( 'Display Duration', 'notifal' ),
                __( 'How long the notification should stay visible. Note: For "Manual Close Only" to work, your template must include a close icon', 'notifal' )
            );

            // Auto Hide Duration (conditional)
            FieldRenderer::numberInput(
                'auto_hide_seconds',
                $timing_settings['auto_hide_seconds'],
                __( 'Auto Hide Duration (seconds)', 'notifal' ),
                __( 'How long before automatically hiding the notification. The notification will disappear after this time without user interaction.', 'notifal' ),
                ['min' => 1, 'max' => 60, 'step' => 1, 'data-depends-on' => 'display_duration', 'data-depends-value' => 'auto_hide']
            );

            // Persistent Duration (conditional)
            FieldRenderer::numberInput(
                'persistent_duration',
                $timing_settings['persistent_duration'],
                __( 'Persistent Duration (seconds)', 'notifal' ),
                __( 'How long to keep the notification visible. Set to 0 for forever (until manually dismissed). Unlike Auto Hide, this allows users to dismiss it before the time expires.', 'notifal' ),
                ['min' => 0, 'max' => 86400, 'step' => 60, 'data-depends-on' => 'display_duration', 'data-depends-value' => 'persistent']
            );
            ?>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_AFTER, $tab, 'display_duration')); ?>
        </div>

        <!-- Frequency Control Settings -->
        <div class="notifal-field-group">
            <h3><?php esc_html_e( 'Frequency Control', 'notifal' ); ?></h3>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_BEFORE, $tab, 'frequency_control')); ?>
            
            <?php
            // Show Frequency
            FieldRenderer::select(
                'show_frequency',
                [
                    ['value' => 'always', 'label' => __( 'Always Show', 'notifal' )],
                    ['value' => 'once_per_session', 'label' => __( 'Per Session', 'notifal' )],
                ],
                $timing_settings['show_frequency'],
                __( 'Show Frequency', 'notifal' ),
                __( 'How often to show this notification to users', 'notifal' )
            );

            // Max Shows Per Session (conditional)
            FieldRenderer::numberInput(
                'max_shows_per_session',
                $timing_settings['max_shows_per_session'],
                __( 'Max Shows Per Session', 'notifal' ),
                __( 'Maximum number of times to show per browser session. Sessions expire after 30 minutes of inactivity and reset on page refresh. Example: 2 = show up to twice per session.', 'notifal' ),
                ['min' => 1, 'max' => 10, 'step' => 1, 'data-depends-on' => 'show_frequency', 'data-depends-value' => 'once_per_session']
            );

            // Clear Session on Logout
            FieldRenderer::toggle(
                'clear_session_on_logout',
                $timing_settings['clear_session_on_logout'],
                __( 'Clear Session on Logout', 'notifal' ),
                __( 'Clear session counters when the user logs out.', 'notifal' ),
                ['data-depends-on' => 'show_frequency', 'data-depends-value' => 'once_per_session']
            );

            // Respect User Dismissal
            FieldRenderer::toggle(
                'respect_user_dismissal',
                $timing_settings['respect_user_dismissal'],
                __( 'Respect User Dismissal', 'notifal' ),
                __( 'Once user manually closes the notification, do not show it again during their session.', 'notifal' )
            );

            // Allow Re-trigger After Hide
            FieldRenderer::toggle(
                'allow_retrigger_after_hide',
                $timing_settings['allow_retrigger_after_hide'],
                __( 'Allow Re-trigger After Hide', 'notifal' ),
                __( 'Allow notification to appear again on the same page after being hidden, without requiring a page refresh.', 'notifal' )
            );

            // Retrigger Delay (conditional on allow_retrigger_after_hide)
            FieldRenderer::numberInput(
                'retrigger_delay_seconds',
                $timing_settings['retrigger_delay_seconds'],
                __( 'Retrigger Delay (seconds)', 'notifal' ),
                __( 'How long to wait before allowing the notification to be triggered again after being hidden.', 'notifal' ),
                ['min' => 1, 'max' => 300, 'step' => 1, 'data-depends-on' => 'allow_retrigger_after_hide', 'data-depends-value' => '1']
            );

            // Max Retriggering Per Page (conditional on allow_retrigger_after_hide)
            FieldRenderer::numberInput(
                'max_retrigger_per_page',
                $timing_settings['max_retrigger_per_page'],
                __( 'Max Retriggering Per Page', 'notifal' ),
                __( 'Maximum number of times this notification can be retriggered on the same page. Counter resets when user navigates to another page or refreshes.', 'notifal' ),
                ['min' => 1, 'max' => 10, 'step' => 1, 'data-depends-on' => 'allow_retrigger_after_hide', 'data-depends-value' => '1']
            );
            ?>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_AFTER, $tab, 'frequency_control')); ?>
        </div>

        <!-- Advanced Timing Settings -->
        <div class="notifal-field-group">
            <h3><?php esc_html_e( 'Advanced Timing', 'notifal' ); if (!$is_pro_active): ?> <span class="notifal-pro-badge notifal-pro-badge-inline"><?php esc_html_e('PRO', 'notifal'); ?></span><?php endif; ?></h3>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_BEFORE, $tab, 'advanced_timing')); ?>

            <?php if (!$is_pro_active): ?>
            
                <div class="notifal-pro-upsell-section">
                <h3 class="notifal-text-center"><?php esc_html_e('Advanced Timing Features', 'notifal'); ?></h4>
                <p class="notifal-text-description"><?php esc_html_e('Unlock advanced timing controls including user consent, tab activity management, instance prevention, and priority settings with Notifal Pro.', 'notifal'); ?></p>
                <div class="notifal-pro-upsell-grid-double">
                    <div>✓ <?php esc_html_e('Require User Consent', 'notifal'); ?></div>
                    <div>✓ <?php esc_html_e('Pause on Tab Inactive', 'notifal'); ?></div>
                    <div>✓ <?php esc_html_e('Resume on Tab Active', 'notifal'); ?></div>
                    <div>✓ <?php esc_html_e('Prevent Multiple Instances', 'notifal'); ?></div>
                    <div>✓ <?php esc_html_e('Priority Control', 'notifal'); ?></div>
                    <div>✓ <?php esc_html_e('Priority Level Settings', 'notifal'); ?></div>
                </div>
                <a href="<?php echo esc_url(Urls::withCustomUtm(Urls::getPricingUrl(parse_url(home_url(), PHP_URL_HOST)), ['utm_medium' => 'timing_settings', 'utm_campaign' => 'notifal_pro_upgrade', 'utm_content' => 'upgrade_button_timing_advanced'])); ?>" class="notifal-button notifal-button-primary" target="_blank"><?php esc_html_e('Upgrade to Pro', 'notifal'); ?></a>
            </div>
            <?php endif; ?>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_AFTER, $tab, 'advanced_timing')); ?>
        </div>

        <!-- Timing Preview -->
        <div class="notifal-field-group">
            <h3><?php esc_html_e( 'Timing Preview', 'notifal' ); ?></h3>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_BEFORE, $tab, 'timing_preview')); ?>
            
            <div class="notifal-timing-preview">
                <div class="notifal-preview-summary">
                    <p class="notifal-preview-text">
                        <?php esc_html_e( 'Based on your current settings, this notification will:', 'notifal' ); ?>
                    </p>
                    <ul class="notifal-preview-list">
                        <li id="notifal-timing-preview-trigger"><?php esc_html_e( 'Trigger: —', 'notifal' ); ?></li>
                        <li id="notifal-timing-preview-schedule"><?php esc_html_e( 'Schedule: —', 'notifal' ); ?></li>
                        <li id="notifal-timing-preview-duration"><?php esc_html_e( 'Duration: —', 'notifal' ); ?></li>
                        <li id="notifal-timing-preview-frequency"><?php esc_html_e( 'Frequency: —', 'notifal' ); ?></li>
                    </ul>
                </div>
            </div>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_AFTER, $tab, 'timing_preview')); ?>
        </div>

    </div>

</div>

<?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_AFTER, $tab)); ?> 
