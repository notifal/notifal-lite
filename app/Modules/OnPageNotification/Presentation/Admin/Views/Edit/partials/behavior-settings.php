<?php

use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Shared\AdminUI\Fields\FieldRenderer;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\BehaviorSettingsService;
use Notifal\Modules\OnPageNotification\Application\Support\OnPageNotificationSettingsLimits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Behavior Settings Tab
 *
 * Handles the display and configuration of notification behavior settings
 * including user interactions, accessibility options, and advanced behaviors.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Views\Edit\partials
 */

/**
 * Initialize behavior settings service and retrieve merged settings
 * Merges default settings with saved notification data if in edit mode
 */
$behaviorService = notifal_app(BehaviorSettingsService::class);

// Retrieve default behavior settings from service
$behavior_settings = $behaviorService->getDefaultSettings();

// Merge with saved notification settings if editing an existing notification
if ($is_edit && isset($notification_data['behavior_settings']) && is_array($notification_data['behavior_settings'])) {
    $behavior_settings = array_merge($behavior_settings, $notification_data['behavior_settings']);
}

// Define current tab identifier for hooks and styling
$tab = 'behavior';

// Check if Pro version is activated for conditional feature display
$is_pro_active = PluginDetector::isNotifalProActive();

do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_BEFORE, $tab));
?>

<div class="notifal-settings-section notifal-<?php echo esc_attr( $tab ); ?>-settings">

    <h1><?php esc_html_e( 'Behavior Settings', 'notifal' ); ?></h1>

    <div class="notifal-tab-panel-fields notifal-mt-20">

        <!-- User Interaction Settings - PRO FEATURES -->
        <div class="notifal-field-group">
            <h3><?php esc_html_e( 'User Interaction', 'notifal' ); ?><?php if (! $is_pro_active): ?> <span class="notifal-pro-badge notifal-pro-badge-inline"><?php esc_html_e('PRO', 'notifal'); ?></span><?php endif; ?></h3>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_BEFORE, $tab, 'user_interaction')); ?>

            <?php
            if (!$is_pro_active):
            ?>
            <div class="notifal-pro-upsell-section">
                <h4 class="notifal-text-center"><?php esc_html_e('Advanced User Interaction', 'notifal'); ?></h4>
                <p class="notifal-text-description"><?php esc_html_e('Enhance user experience with advanced interaction controls available in Notifal Pro.', 'notifal'); ?></p>
                <div class="notifal-pro-upsell-grid">
                    <div>✓ <?php esc_html_e('Dismiss on Click Outside', 'notifal'); ?></div>
                    <div>✓ <?php esc_html_e('Dismiss on Escape Key', 'notifal'); ?></div>
                    <div>✓ <?php esc_html_e('Dismiss on Scroll', 'notifal'); ?></div>
                    <div>✓ <?php esc_html_e('Swipe to Dismiss', 'notifal'); ?></div>
                </div>
                <a href="<?php echo esc_url(Urls::withCustomUtm(Urls::getPricingUrl(parse_url(home_url(), PHP_URL_HOST)), ['utm_medium' => 'behavior_settings', 'utm_campaign' => 'notifal_pro_upgrade', 'utm_content' => 'upgrade_button_behavior_user_interaction'])); ?>" class="notifal-button notifal-button-primary" target="_blank"><?php esc_html_e('Upgrade to Pro', 'notifal'); ?></a>
            </div>
            <?php endif; ?>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_AFTER, $tab, 'user_interaction')); ?>
        </div>

        <!-- Accessibility Settings -->
        <div class="notifal-field-group">
            <h3><?php esc_html_e( 'Accessibility', 'notifal' ); ?></h3>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_BEFORE, $tab, 'accessibility')); ?>

            <?php
            // Enable ARIA Labels
            FieldRenderer::toggle(
                'enable_aria_labels',
                $behavior_settings['enable_aria_labels'],
                __( 'Enable ARIA Labels', 'notifal' ),
                __( 'Add proper ARIA labels and roles for screen readers. This makes the notification more accessible to users with visual impairments.', 'notifal' )
            );

            // Focus Trap
            FieldRenderer::toggle(
                'focus_trap',
                $behavior_settings['focus_trap'],
                __( 'Focus Trap', 'notifal' ),
                __( "Keep keyboard focus within the notification when it's active. This prevents users from accidentally navigating away and ensures keyboard users can interact with the notification.", 'notifal' )
            );

            // Announce to Screen Reader
            FieldRenderer::toggle(
                'announce_to_screen_reader',
                $behavior_settings['announce_to_screen_reader'],
                __( 'Announce to Screen Reader', 'notifal' ),
                __( 'Automatically announce the notification content to screen readers when it appears. This helps users with visual impairments know when a new notification is shown.', 'notifal' )
            );
            ?>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_AFTER, $tab, 'accessibility')); ?>
        </div>

        <!-- Advanced Behavior Settings -->
        <div class="notifal-field-group">
            <h3><?php esc_html_e( 'Advanced Behavior', 'notifal' ); ?></h3>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_BEFORE, $tab, 'advanced_behavior')); ?>

            <?php
            // Prevent Page Scroll
            FieldRenderer::toggle(
                'prevent_page_scroll',
                $behavior_settings['prevent_page_scroll'],
                __( 'Prevent Page Scroll', 'notifal' ),
                __( 'Prevent the page from scrolling when notification is active.', 'notifal' )
            );

            // Close on Form Submit
            FieldRenderer::toggle(
                'close_on_form_submit',
                $behavior_settings['close_on_form_submit'],
                __( 'Close on Form Submit', 'notifal' ),
                __( 'Automatically close notification when a form within it is submitted.', 'notifal' )
            );

            // Close delay after form submit (conditional: only when Close on Form Submit is on)
            // Wrapper for conditional visibility via JS based on close_on_form_submit toggle
            ?>
            <div class="notifal-close-on-form-submit-delay-container<?php echo ! empty( $behavior_settings['close_on_form_submit'] ) ? '' : ' notifal-hidden'; ?>" data-depends-on="close_on_form_submit">
                <?php
                FieldRenderer::numberInput(
                    'close_on_form_submit_delay_seconds',
                    isset( $behavior_settings['close_on_form_submit_delay_seconds'] ) ? (int) $behavior_settings['close_on_form_submit_delay_seconds'] : 5,
                    __( 'Close Delay After Submit (seconds)', 'notifal' ),
                    __( 'Time to keep the notification visible after form submit so users can see the success message before it closes. Default is 5 seconds.', 'notifal' ),
                    [
                        'input' => [
                            'min'  => OnPageNotificationSettingsLimits::MIN_BEHAVIOR_CLOSE_DELAY_SECONDS,
                            'step' => 1,
                        ],
                    ]
                );
                ?>
            </div>
            <?php

            // Close on Action Button Click (template action buttons: Block / Elementor)
            FieldRenderer::toggle(
                'close_on_action_button_click',
                $behavior_settings['close_on_action_button_click'],
                __( 'Close on Action Button Click', 'notifal' ),
                __( 'Automatically close the notification after a template action button is clicked (Post Link, Copy, Custom Link, Ajax Add to Cart, etc.). The Close Notification button type uses the delay below when delay is greater than zero.', 'notifal' )
            );

            // Close delay after action button click (conditional: only when Close on Action Button Click is on)
            ?>
            <div class="notifal-close-on-action-button-click-delay-container<?php echo ! empty( $behavior_settings['close_on_action_button_click'] ) ? '' : ' notifal-hidden'; ?>" data-depends-on="close_on_action_button_click">
                <?php
                FieldRenderer::numberInput(
                    'close_on_action_button_click_delay_seconds',
                    isset( $behavior_settings['close_on_action_button_click_delay_seconds'] ) ? (int) $behavior_settings['close_on_action_button_click_delay_seconds'] : 5,
                    __( 'Close Delay After Action Button (seconds)', 'notifal' ),
                    __( 'Time to wait before closing after an action button click. Use zero for an immediate close when the setting is enabled.', 'notifal' ),
                    [
                        'input' => [
                            'min'  => OnPageNotificationSettingsLimits::MIN_BEHAVIOR_CLOSE_DELAY_SECONDS,
                            'step' => 1,
                        ],
                    ]
                );
                ?>
            </div>
            <?php

            // Maintain State on Refresh
            FieldRenderer::toggle(
                'maintain_state_on_refresh',
                $behavior_settings['maintain_state_on_refresh'],
                __( 'Maintain State on Refresh', 'notifal' ),
                __( 'Remember notification state (dismissed/active) when page is refreshed.', 'notifal' )
            );
            ?>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_AFTER, $tab, 'advanced_behavior')); ?>
        </div>

        <!-- Tab Badge Behavior Settings - PRO FEATURE -->
        <div class="notifal-field-group notifal-pro-feature">
            <h3><?php esc_html_e( 'Tab Badge Behavior', 'notifal' ); ?><?php if (! $is_pro_active): ?> <span class="notifal-pro-badge notifal-pro-badge-inline"><?php esc_html_e('PRO', 'notifal'); ?></span><?php endif; ?></h3>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_BEFORE, $tab, 'tab_badge_behavior')); ?>

            <?php
            if (!$is_pro_active):
            ?>
            <div class="notifal-pro-upsell-section">
                <h4 class="notifal-text-center"><?php esc_html_e('Tab Badge Features', 'notifal'); ?></h4>
                <p class="notifal-text-description"><?php esc_html_e('Enhance user engagement with dynamic tab badges that notify users when they switch away from your page. Available in Notifal Pro.', 'notifal'); ?></p>
                <div class="notifal-pro-upsell-grid-double">
                    <div>✓ <?php esc_html_e('Tab Badge Notifications', 'notifal'); ?></div>
                    <div>✓ <?php esc_html_e('Custom Badge Styling', 'notifal'); ?></div>
                    <div>✓ <?php esc_html_e('Dynamic Badge Text', 'notifal'); ?></div>
                    <div>✓ <?php esc_html_e('Count & Alert Modes', 'notifal'); ?></div>
                </div>
                <a href="<?php echo esc_url(Urls::withCustomUtm(Urls::getPricingUrl(parse_url(home_url(), PHP_URL_HOST)), ['utm_medium' => 'behavior_settings', 'utm_campaign' => 'notifal_pro_upgrade', 'utm_content' => 'upgrade_button_behavior_tab_badge'])); ?>" class="notifal-button notifal-button-primary" target="_blank"><?php esc_html_e('Upgrade to Pro', 'notifal'); ?></a>
            </div>
            <?php endif; ?>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_AFTER, $tab, 'tab_badge_behavior')); ?>
        </div>

    </div>

</div>

<?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_AFTER, $tab)); ?> 
