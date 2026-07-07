<?php

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Shared\AdminUI\Fields\FieldRenderer;
use Notifal\Shared\Services\NotifalIconService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\AppearanceSettingsService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Appearance Settings Tab
 *
 * Handles the display and configuration of notification appearance settings
 * including device visibility, positioning, animations, and styling options.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Views\Edit\partials
 */

/**
 * Initialize appearance settings service and retrieve merged settings
 * Merges default settings with saved notification data if in edit mode
 */
$appearanceService = notifal_app(AppearanceSettingsService::class);

// Retrieve default appearance settings from service
$appearance_settings = $appearanceService->getDefaultSettings();

// Merge with saved notification settings if editing an existing notification
if ($is_edit && isset($notification_data['appearance_settings']) && is_array($notification_data['appearance_settings'])) {
    $appearance_settings = array_merge($appearance_settings, $notification_data['appearance_settings']);
}

// Define current tab identifier for hooks and styling
$tab = 'appearance';

// Check if Pro version is activated for conditional feature display
$is_pro_active = PluginDetector::isNotifalProActive();

do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_BEFORE, $tab));
?>

<div class="notifal-settings-section notifal-<?php echo esc_attr( $tab ); ?>-settings">

    <h1><?php esc_html_e( 'Appearance Settings', 'notifal' ); ?></h1>

    <div class="notifal-tab-panel-fields notifal-mt-20">

        <!-- Device Visibility Settings -->
        <div class="notifal-field-group">
            <h3><?php esc_html_e( 'Device Visibility', 'notifal' ); ?></h3>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_BEFORE, $tab, 'device_visibility')); ?>

            <div class="notifal-device-visibility-inline">
                <?php
                FieldRenderer::toggle(
                    'show_on_desktop',
                    $appearance_settings['show_on_desktop'],
                    __( 'Show on Desktop', 'notifal' ),
                    __( 'Display notification on desktop devices', 'notifal' )
                );

                FieldRenderer::toggle(
                    'show_on_tablet',
                    $appearance_settings['show_on_tablet'],
                    __( 'Show on Tablet', 'notifal' ),
                    __( 'Display notification on tablet devices', 'notifal' )
                );

                FieldRenderer::toggle(
                    'show_on_mobile',
                    $appearance_settings['show_on_mobile'],
                    __( 'Show on Mobile', 'notifal' ),
                    __( 'Display notification on mobile devices', 'notifal' )
                );
                ?>
            </div>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_AFTER, $tab, 'device_visibility')); ?>
        </div>

        <!-- Notification Display Type -->
        <div class="notifal-field-group">
            <h3><?php esc_html_e( 'Display Type', 'notifal' ); ?></h3>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_BEFORE, $tab, 'notification_display_type')); ?>

            <?php
            // Get current display type and its description for dynamic tooltip
            $display_type = $appearance_settings['notification_display_type'];
            $display_description = AppearanceSettingsService::getDisplayTypeDescription($display_type);

            FieldRenderer::select(
                'notification_display_type',
                AppearanceSettingsService::getDisplayTypeOptions(),
                $display_type,
                __( 'Display Type', 'notifal' ),
                $display_description,
                ['wrapper' => ['data-display-type' => $display_type]],
                true // Enable dynamic tooltip
            );
            ?>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_AFTER, $tab, 'notification_display_type')); ?>
        </div>

        <!-- Popup Backdrop Settings -->
        <div class="notifal-field-group notifal-popup-backdrop-group notifal-position-group-for-popup notifal-hidden">
            <h3><?php esc_html_e( 'Popup Backdrop Settings', 'notifal' ); ?></h3>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_BEFORE, $tab, 'popup_backdrop')); ?>

            <?php
            FieldRenderer::colorPicker(
                'backdrop_bg_color',
                $appearance_settings['backdrop_bg_color'],
                __( 'Backdrop Background Color', 'notifal' ),
                __( 'Choose the background color for the popup backdrop. Supports RGBA values for transparency.', 'notifal' ),
                [],
                true
            );

            FieldRenderer::numberInput(
                'backdrop_blur',
                $appearance_settings['backdrop_blur'],
                __( 'Backdrop Blur (px)', 'notifal' ),
                __( 'Apply blur effect to the backdrop behind the popup (0 = no blur)', 'notifal' ),
                ['input' => ['min' => 0, 'max' => 20, 'step' => 1]]
            );
            ?>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_AFTER, $tab, 'popup_backdrop')); ?>
        </div>

        <!-- Desktop Position Settings -->
        <div class="notifal-field-group notifal-desktop-position-group notifal-position-group-for-toast notifal-position-group-for-floating">
            <h3><?php esc_html_e( 'Desktop & tablet Position Settings', 'notifal' ); ?></h3>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_BEFORE, $tab, 'desktop_position')); ?>

            <?php
            FieldRenderer::select(
                'desktop_position',
                AppearanceSettingsService::getDesktopPositionOptions(),
                $appearance_settings['desktop_position'],
                __( 'Desktop Position', 'notifal' ),
                __( 'Where to display the notification on desktop devices', 'notifal' )
            );
            ?>
            <?php // @since 2.3.7 Position distance inputs allow negative values (no min/max HTML attributes). ?>
            <div class="notifal-desktop-distance-settings notifal-hidden">
                <div class="notifal-distance-controls">
                    <!-- Top Distance (for top positions) -->
                    <div class="notifal-distance-group notifal-top-distance notifal-hidden">
                        <?php
                        FieldRenderer::numberInput(
                            'desktop_top_distance',
                            $appearance_settings['desktop_top_distance'],
                            __( 'Distance from Top (px)', 'notifal' ),
                            __( 'Distance from the top edge of the screen', 'notifal' ),
                            ['input' => ['step' => 1]]
                        );
                        ?>
                    </div>

                    <!-- Bottom Distance (for bottom positions) -->
                    <div class="notifal-distance-group notifal-bottom-distance notifal-hidden">
                        <?php
                        FieldRenderer::numberInput(
                            'desktop_bottom_distance',
                            $appearance_settings['desktop_bottom_distance'],
                            __( 'Distance from Bottom (px)', 'notifal' ),
                            __( 'Distance from the bottom edge of the screen', 'notifal' ),
                            ['input' => ['step' => 1]]
                        );
                        ?>
                    </div>

                    <!-- Left Distance (for left positions) -->
                    <div class="notifal-distance-group notifal-left-distance notifal-hidden">
                        <?php
                        FieldRenderer::numberInput(
                            'desktop_left_distance',
                            $appearance_settings['desktop_left_distance'],
                            __( 'Distance from Left (px)', 'notifal' ),
                            __( 'Distance from the left edge of the screen', 'notifal' ),
                            ['input' => ['step' => 1]]
                        );
                        ?>
                    </div>

                    <!-- Right Distance (for right positions) -->
                    <div class="notifal-distance-group notifal-right-distance notifal-hidden">
                        <?php
                        FieldRenderer::numberInput(
                            'desktop_right_distance',
                            $appearance_settings['desktop_right_distance'],
                            __( 'Distance from Right (px)', 'notifal' ),
                            __( 'Distance from the right edge of the screen', 'notifal' ),
                            ['input' => ['step' => 1]]
                        );
                        ?>
                    </div>
                </div>
            </div>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_AFTER, $tab, 'desktop_position')); ?>
        </div>

        <!-- Desktop Bar Position Settings (for Floating Bar) -->
        <div class="notifal-field-group notifal-desktop-bar-position-group notifal-position-group-for-topbar notifal-hidden">
            <h3><?php esc_html_e( 'Desktop & tablet Bar Position Settings', 'notifal' ); ?></h3>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_BEFORE, $tab, 'desktop_bar_position')); ?>

            <?php
            FieldRenderer::select(
                'desktop_bar_position',
                [
                    ['value' => 'top', 'label' => __('Top', 'notifal')],
                    ['value' => 'bottom', 'label' => __('Bottom', 'notifal')],
                    ['value' => 'left', 'label' => __('Left', 'notifal')],
                    ['value' => 'right', 'label' => __('Right', 'notifal')],
                ],
                $appearance_settings['desktop_bar_position'] ?? 'top',
                __( 'Desktop Bar Position', 'notifal' ),
                __( 'Position of the floating bar on desktop devices', 'notifal' )
            );

            FieldRenderer::numberInput(
                'desktop_bar_distance',
                $appearance_settings['desktop_bar_distance'] ?? 0,
                __( 'Distance from Edge (px)', 'notifal' ),
                __( 'Distance from the selected edge of the screen', 'notifal' ),
                ['input' => ['step' => 1]]
            );
            ?>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_AFTER, $tab, 'desktop_bar_position')); ?>
        </div>
        <!-- Mobile Position Settings -->
        <div class="notifal-field-group notifal-mobile-position-group notifal-position-group-for-toast notifal-position-group-for-floating">
            <h3><?php esc_html_e( 'Mobile Position Settings', 'notifal' ); ?></h3>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_BEFORE, $tab, 'mobile_position')); ?>

            <?php
            FieldRenderer::select(
                'mobile_position',
                AppearanceSettingsService::getMobilePositionOptions(),
                $appearance_settings['mobile_position'],
                __( 'Mobile Position', 'notifal' ),
                __( 'Position for mobile devices', 'notifal' )
            );
            ?>
            <div class="notifal-mobile-distance-settings notifal-hidden">
                <div class="notifal-distance-controls">
                    <!-- Top Distance (for top position) -->
                    <div class="notifal-distance-group notifal-mobile-top-distance notifal-hidden">
                        <?php
                        FieldRenderer::numberInput(
                            'mobile_top_distance',
                            $appearance_settings['mobile_top_distance'],
                            __( 'Distance from Top (px)', 'notifal' ),
                            __( 'Distance from the top edge on mobile', 'notifal' ),
                            ['input' => ['step' => 1]]
                        );
                        ?>
                    </div>

                    <!-- Bottom Distance (for bottom position) -->
                    <div class="notifal-distance-group notifal-mobile-bottom-distance notifal-hidden">
                        <?php
                        FieldRenderer::numberInput(
                            'mobile_bottom_distance',
                            $appearance_settings['mobile_bottom_distance'],
                            __( 'Distance from Bottom (px)', 'notifal' ),
                            __( 'Distance from the bottom edge on mobile', 'notifal' ),
                            ['input' => ['step' => 1]]
                        );
                        ?>
                    </div>
                </div>
            </div>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_AFTER, $tab, 'mobile_position')); ?>
        </div>

        <!-- Mobile Bar Position Settings (for Floating Bar) -->
        <div class="notifal-field-group notifal-mobile-bar-position-group notifal-position-group-for-topbar notifal-hidden">
            <h3><?php esc_html_e( 'Mobile Bar Position Settings', 'notifal' ); ?></h3>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_BEFORE, $tab, 'mobile_bar_position')); ?>

            <?php
            FieldRenderer::select(
                'mobile_bar_position',
                [
                    ['value' => 'top', 'label' => __('Top', 'notifal')],
                    ['value' => 'bottom', 'label' => __('Bottom', 'notifal')],
                    ['value' => 'left', 'label' => __('Left', 'notifal')],
                    ['value' => 'right', 'label' => __('Right', 'notifal')],
                ],
                $appearance_settings['mobile_bar_position'] ?? 'top',
                __( 'Mobile Bar Position', 'notifal' ),
                __( 'Position of the floating bar on mobile devices', 'notifal' )
            );

            FieldRenderer::numberInput(
                'mobile_bar_distance',
                $appearance_settings['mobile_bar_distance'] ?? 0,
                __( 'Distance from Edge (px)', 'notifal' ),
                __( 'Distance from the selected edge of the screen', 'notifal' ),
                ['input' => ['step' => 1]]
            );
            ?>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_AFTER, $tab, 'mobile_bar_position')); ?>
        </div>

           <!-- Floating Bar Placement (for Top Bar display type) -->
           <div class="notifal-field-group notifal-topbar-placement-group notifal-position-group-for-topbar notifal-hidden">
            <h3><?php esc_html_e( 'Floating Bar Placement', 'notifal' ); ?></h3>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_BEFORE, $tab, 'topbar_placement')); ?>

            <?php
            FieldRenderer::select(
                'topbar_placement',
                [
                    ['value' => 'fixed_top', 'label' => __('Fixed at top of viewport', 'notifal')],
                    ['value' => 'above_header', 'label' => __('Above header (first element)', 'notifal')],
                ],
                $appearance_settings['topbar_placement'] ?? 'fixed_top',
                __( 'Bar position', 'notifal' ),
                __( 'Fixed: bar stays on top of all content including header. Above header: bar is inserted as the first element before the site header.', 'notifal' )
            );

            $is_above_header = ($appearance_settings['topbar_placement'] ?? 'fixed_top') === 'above_header';
            ?>
            <div class="notifal-topbar-sticky-wrap notifal-mt-15 <?php echo $is_above_header ? '' : 'notifal-hidden'; ?>">
                <?php
                FieldRenderer::toggle(
                    'topbar_sticky_on_scroll',
                    $appearance_settings['topbar_sticky_on_scroll'] ?? true,
                    __( 'Sticky on scroll', 'notifal' ),
                    __( 'When enabled, the bar sticks to the top when the user scrolls. When disabled, the bar scrolls away with the page.', 'notifal' )
                );
                ?>
            </div>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_AFTER, $tab, 'topbar_placement')); ?>
        </div>


        <!-- Animation Settings -->
        <div class="notifal-field-group">
            <h3><?php esc_html_e( 'Animation Settings', 'notifal' ); ?></h3>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_BEFORE, $tab, 'animation')); ?>

            <?php
            FieldRenderer::select(
                'show_animation_type',
                AppearanceSettingsService::getShowAnimationTypeOptions(),
                $appearance_settings['show_animation_type'],
                __( 'Show Animation', 'notifal' ),
                __( 'How the notification appears', 'notifal' )
            );

            FieldRenderer::select(
                'hide_animation_type',
                AppearanceSettingsService::getHideAnimationTypeOptions(),
                $appearance_settings['hide_animation_type'],
                __( 'Hide Animation', 'notifal' ),
                __( 'How the notification disappears', 'notifal' )
            );

            $is_animation_duration_applicable = AppearanceSettingsService::isAnimationDurationApplicable( $appearance_settings );
            ?>
            <div class="notifal-animation-duration-wrapper<?php echo $is_animation_duration_applicable ? '' : ' notifal-hidden'; ?>">
            <?php
            FieldRenderer::numberInput(
                'animation_duration',
                $appearance_settings['animation_duration'],
                __( 'Animation Duration (ms)', 'notifal' ),
                __( 'Duration of appear/disappear animations. Hidden when both show and hide are set to No Animation.', 'notifal' ),
                ['input' => ['min' => 0, 'max' => 2000, 'step' => 50]]
            );
            ?>
            </div>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_AFTER, $tab, 'animation')); ?>
        </div>

        <!-- Audio Settings -->
        <div class="notifal-field-group notifal-audio-settings-group">
            <h3><?php esc_html_e( 'Audio Settings', 'notifal' ); ?></h3>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_BEFORE, $tab, 'audio')); ?>

            <?php
            FieldRenderer::toggle(
                'enable_audio',
                $appearance_settings['enable_audio'],
                __( 'Enable Audio', 'notifal' ),
                __( 'Play audio when notification appears or disappears', 'notifal' )
            );
            ?>
            <div class="notifal-audio-settings-container notifal-hidden">
                <?php
                // Extract filename from audio file setting (handles both URLs and filenames)
                $audio_file = AppearanceSettingsService::extractFilename($appearance_settings['audio_file']);

                FieldRenderer::select(
                    'audio_type',
                    [
                        ['value' => 'default', 'label' => __( 'Default Sounds', 'notifal' )],
                        ['value' => 'custom', 'label' => __( 'Custom Upload', 'notifal' )],
                    ],
                    $appearance_settings['audio_type'],
                    __( 'Audio Type', 'notifal' ),
                    __( 'Choose between default sounds or upload your own audio file', 'notifal' )
                );

                // Default Audio Selection (shown when audio_type is 'default')
                $default_audio_files = [
                    ['value' => 'audio1.mp3', 'label' => __( 'Notification Sound 1', 'notifal' )],
                    ['value' => 'audio2.mp3', 'label' => __( 'Notification Sound 2', 'notifal' )],
                    ['value' => 'audio3.mp3', 'label' => __( 'Notification Sound 3', 'notifal' )],
                    ['value' => 'audio4.mp3', 'label' => __( 'Notification Sound 4', 'notifal' )],
                    ['value' => 'audio5.mp3', 'label' => __( 'Notification Sound 5', 'notifal' )],
                ];

                FieldRenderer::select(
                    'audio_file',
                    $default_audio_files,
                    $audio_file,
                    __( 'Default Audio File', 'notifal' ),
                    __( 'Select a default notification sound', 'notifal' ),
                    ['wrapper' => ['class' => 'notifal-audio-default-wrapper']]
                );
                ?>
                <div class="notifal-field-wrapper notifal-direction-column notifal-audio-preview-wrapper">
                    <div class="notifal-field-header notifal-flex notifal-flex-row">
                        <label class="notifal-form-label"><?php esc_html_e( 'Audio Preview', 'notifal' ); ?></label>
                        <?php FieldRenderer::tooltip( __( 'Click the play button to preview the selected audio sound', 'notifal' ) ); ?>
                    </div>
                    <div class="notifal-audio-preview-controls">
                        <button type="button"
                                class="notifal-audio-preview-btn"
                                data-audio="<?php echo esc_attr( $audio_file ); ?>"
                                aria-label="<?php esc_attr_e( 'Preview selected audio file', 'notifal' ); ?>"
                                title="<?php esc_attr_e( 'Click to preview the selected audio sound', 'notifal' ); ?>">
                            <span class="notifal-audio-preview-icon"><?php echo NotifalIconService::render('play-circle', 18); ?></span>
                            <span class="notifal-audio-preview-text"><?php esc_html_e( 'Preview Audio', 'notifal' ); ?></span>
                        </button>
                        <div class="notifal-audio-preview-status" role="status" aria-live="polite"></div>
                    </div>
                </div>

                <?php
                FieldRenderer::mediaUpload(
                    'custom_audio_file',
                    $appearance_settings['custom_audio_file'],
                    __( 'Custom Audio File', 'notifal' ),
                    __( 'Upload your own audio file (MP3, WAV, OGG supported)', 'notifal' ),
                    ['wrapper' => ['class' => 'notifal-audio-custom-wrapper notifal-hidden']]
                );

                FieldRenderer::rangeSlider(
                    'audio_volume',
                    $appearance_settings['audio_volume'],
                    __( 'Audio Volume', 'notifal' ),
                    __( 'Set the volume level for audio playback (0-100%)', 'notifal' ),
                    ['input' => ['min' => 0, 'max' => 100, 'step' => 5]]
                );
                ?>
                <div class="notifal-audio-events">
                    <?php
                    FieldRenderer::toggle(
                        'audio_play_on_show',
                        $appearance_settings['audio_play_on_show'],
                        __( 'Play on Show', 'notifal' ),
                        __( 'Play audio when notification appears', 'notifal' )
                    );

                    FieldRenderer::toggle(
                        'audio_play_on_hide',
                        $appearance_settings['audio_play_on_hide'],
                        __( 'Play on Hide', 'notifal' ),
                        __( 'Play audio when notification disappears', 'notifal' )
                    );
                    ?>
                </div>
            </div>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_AFTER, $tab, 'audio')); ?>
        </div>

        <!-- Advanced Styling -->
        <div class="notifal-field-group">
            <h3><?php esc_html_e( 'Advanced Styling', 'notifal' ); ?></h3>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_BEFORE, $tab, 'advanced_styling')); ?>

            <?php
            FieldRenderer::numberInput(
                'z_index',
                $appearance_settings['z_index'],
                __( 'Z-Index', 'notifal' ),
                __( 'Stacking order (higher = on top)', 'notifal' ),
                ['input' => ['min' => 1, 'max' => 99999999]]
            );

            // Custom CSS - PRO FEATURE: Only show if Notifal Pro is active
            if ($is_pro_active):
                // Generate unique ID for CSS selectors (use existing ID or 'NEW' for new notifications)
                $notification_id = $is_edit ? $notification_id : 'NEW';
                // Generate placeholder text showing ID/class selectors and @media usage.
                $css_placeholder = sprintf(
                    "/* @media example:\n" .
                    "@media (max-width: 780px) {\n" .
                    "  #notifal-onpage-notification-%s { width: 95%%; }\n" .
                    "}\n" .
                    "/* Or direct selector:\n" .
                    "#notifal-onpage-notification-%s { /* your styles */ } */",
                    $notification_id,
                    $notification_id
                );

                // Create tooltip explaining selector usage, @media support, and security restrictions.
                $css_tooltip = sprintf(
                    __('Custom CSS can use either "#notifal-onpage-notification-%s" (ID selector) or ".notifal-onpage-notification-%s" (class selector). @media queries are supported when inner rules use these prefixes. For security, only scoped selectors are allowed.', 'notifal'),
                    $notification_id,
                    $notification_id
                );

                FieldRenderer::textarea(
                    'custom_css',
                    $appearance_settings['custom_css'],
                    __( 'Custom CSS', 'notifal' ),
                    $css_tooltip,
                    ['input' => ['placeholder' => $css_placeholder, 'rows' => 8, 'cols' => 50]]
                );
            else:
                echo '<div class="notifal-field-wrapper notifal-pro-feature notifal-pro-disabled">';
                echo '<label class="notifal-form-label">' . esc_html__('Custom CSS', 'notifal') . ' <span class="notifal-pro-badge notifal-pro-badge-inline">' . esc_html__('PRO', 'notifal') . '</span></label>';
                echo '<div class="notifal-field-description"><small>' . esc_html__('Add custom CSS styles to your notifications. Available in Notifal Pro.', 'notifal') . '</small></div>';
                echo '<textarea disabled class="notifal-pro-disabled notifal-textarea-full" placeholder="/* Custom CSS styles - Available in Notifal Pro */"></textarea>';
                echo '</div>';
            endif;
            ?>
            <?php do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_AFTER, $tab, 'advanced_styling')); ?>
        </div>

    </div>

</div>

<?php
do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_AFTER, $tab));
?> 
