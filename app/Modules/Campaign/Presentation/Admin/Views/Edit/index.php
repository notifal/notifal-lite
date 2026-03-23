<?php

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\Campaign\Infrastructure\WordPress\Repositories\CampaignQuery;
use Notifal\Modules\Campaign\Application\Services\CampaignSettingsService;
use Notifal\Modules\Campaign\Application\Services\CampaignService;
use Notifal\Modules\Campaign\Presentation\Admin\Presenters\CampaignOnpagePickerPresenter;
use Notifal\Modules\OnPageNotification\Application\Support\ScheduleDateTimeHelper;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\UrlService as OnPageUrlService;
use Notifal\Shared\AdminUI\Fields\FieldRenderer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Campaign Edit Page
 *
 * Allows admins to create and edit Campaigns including their scheduling window.
 * When a Campaign is assigned to a notification, the Campaign schedule overrides
 * the notification schedule.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

wp_enqueue_script( 'common' );

$is_edit = false;
$campaign_id = 0;
$campaign_post = null;

$campaign_settings_service = notifal_app( CampaignSettingsService::class );
$default_settings = $campaign_settings_service->getDefaultSettings();

$get_action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( (string) $_GET['action'] ) ) : '';
$get_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

if ( $get_action === 'edit' && $get_id > 0 ) {
    $is_edit = true;
    $campaign_id = $get_id;
    $campaign_post = CampaignQuery::get( $campaign_id );
}

$settings = $default_settings;
if ( $is_edit && $campaign_post ) {
    $stored = get_post_meta( $campaign_id, '_notifal_campaign_settings', true );
    if ( is_array( $stored ) ) {
        $settings = array_merge( $settings, $stored );
    }
}

$campaign_status_raw = isset( $settings['status'] ) ? (string) $settings['status'] : 'active';
$campaign_status_normalized = ( $campaign_status_raw === 'inactive' ) ? 'paused' : $campaign_status_raw;
if ( $campaign_status_normalized !== 'active' && $campaign_status_normalized !== 'paused' ) {
    $campaign_status_normalized = 'active';
}

$campaign_schedule_start_value = '';
$campaign_schedule_end_value   = '';
if ( ! empty( $settings['start_date'] ) ) {
	$campaign_schedule_start_value = ScheduleDateTimeHelper::storedToDatetimeLocalForAdmin( (string) $settings['start_date'] );
}
if ( ! empty( $settings['end_date'] ) ) {
	$campaign_schedule_end_value = ScheduleDateTimeHelper::storedToDatetimeLocalForAdmin( (string) $settings['end_date'] );
}

/**
 * Current moment in the WordPress site timezone, using General → date/time formats.
 *
 * Campaign schedule boundaries use the same timezone as {@see ScheduleDateTimeHelper} (`wp_timezone()`).
 *
 * @var string
 */
$campaign_site_now_display = wp_date(
	sprintf( '%s %s', get_option( 'date_format' ), get_option( 'time_format' ) ),
	null,
	wp_timezone()
);

/**
 * Site timezone identifier or offset string (Settings → General), shown next to the current time for context.
 *
 * @var string
 */
$campaign_site_timezone_label = wp_timezone_string();

$onpage_url_service = notifal_app( OnPageUrlService::class );
$assigned_notifications = [];
if ( $is_edit && $campaign_id > 0 ) {
    $campaign_service = notifal_app( CampaignService::class );
    $assigned_notifications = $campaign_service->getNotifications( $campaign_id );
}

$onpage_picker_initial = CampaignOnpagePickerPresenter::buildInitialItems( $assigned_notifications, $onpage_url_service );

$campaign_show_ended_ui = $is_edit && $campaign_post
    ? $campaign_settings_service->isCampaignScheduleEndedForDisplay( $settings, $campaign_id )
    : false;

$tab = 'campaign';

?>

<div class="wrap notifal-admin-page">
    <?php do_action( ActionHooks::ADMIN_PAGE_CONTENT_BEFORE ); ?>
    <div class="notifal-list-header">
        <h1 class="notifal-page-title">
            <?php echo esc_html( $is_edit ? __( 'Edit Campaign', 'notifal' ) : __( 'Add New Campaign', 'notifal' ) ); ?>
        </h1>
    </div>

    <form
        method="post"
        id="notifal-campaign-edit-form"
        class="notifal-campaign-edit-form notifal-mt-20"
        data-campaign-ended="<?php echo $campaign_show_ended_ui ? '1' : '0'; ?>"
    >
        <?php wp_nonce_field( 'notifal_campaign_save', 'nonce' ); ?>
        <input type="hidden" name="campaign_id" value="<?php echo esc_attr( $campaign_id ); ?>">

        <div class="notifal-card notifal-content notifal-mt-20">
            <?php
            if ( $campaign_show_ended_ui ) {
                echo '<div class="notifal-campaign-ended-banner" role="status">';
                echo '<p class="notifal-campaign-ended-banner-text">';
                esc_html_e(
                    'This campaign has ended. Set the end date to a future moment and save to clear the ended state and run notifications again.',
                    'notifal'
                );
                echo '</p>';
                echo '</div>';
            }

            echo '<input type="hidden" name="campaign_status" value="paused" />';
            FieldRenderer::toggle(
                'campaign_status',
                $campaign_status_normalized === 'active',
                __( 'Campaign active', 'notifal' ),
                __( 'When disabled, notifications in this campaign will not be shown.', 'notifal' ),
                [
                    'input' => [
                        'value' => 'active',
                    ],
                ]
            );

            FieldRenderer::textInput(
                'campaign_title',
                $is_edit ? (string) ( $campaign_post ? $campaign_post->post_title : '' ) : '',
                __( 'Campaign title', 'notifal' ),
                __( 'Name shown in your campaign list. Only you and other admins see it.', 'notifal' )
            );

            FieldRenderer::textarea(
                'campaign_description',
                $settings['description'] ?? '',
                __( 'Description (optional)', 'notifal' ),
                __( 'Short note for your team. Not shown to website visitors.', 'notifal' ),
                [ 'rows' => 4 ]
            );

            echo '<div class="notifal-campaign-current-site-time" role="status">';
            echo '<p class="notifal-campaign-current-site-time-text">';
            echo esc_html(
                sprintf(
                    /* translators: 1: Current date and time (site timezone). 2: Timezone name or offset from Settings → General. */
                    __( 'Current site time: %1$s (%2$s)', 'notifal' ),
                    $campaign_site_now_display,
                    $campaign_site_timezone_label
                )
            );
            echo '</p>';
            echo '</div>';

            FieldRenderer::datetimeInput(
                'start_date',
                $campaign_schedule_start_value,
                __( 'Campaign start', 'notifal' ),
                __( 'Date and time when this campaign window begins. Assigned notifications follow this schedule from then on.', 'notifal' )
            );

            FieldRenderer::datetimeInput(
                'end_date',
                $campaign_schedule_end_value,
                __( 'Campaign end (optional)', 'notifal' ),
                __( 'Leave empty to run until you stop it manually. If set, assigned notifications stop following this campaign after this moment.', 'notifal' )
            );

            FieldRenderer::numberInput(
                'priority',
                (int) ( $settings['priority'] ?? 5 ),
                __( 'Priority (1–10)', 'notifal' ),
                __( 'Higher numbers win when several campaigns could apply. Use it to pick which campaign rules matter most in edge cases.', 'notifal' ),
                [ 'min' => 1, 'max' => 10, 'step' => 1 ]
            );
            ?>

            <div class="notifal-field-wrapper notifal-direction-column notifal-mt-20">
                <div class="notifal-campaign-onpage-label-row">
                    <label class="notifal-campaign-onpage-label" for="notifal-campaign-onpage-search-input">
                        <?php esc_html_e( 'On-page notifications', 'notifal' ); ?>
                    </label>
                    <?php
                    FieldRenderer::tooltip(
                        __( 'Search and add notifications. Their timing will follow this campaign while they stay in the list below.', 'notifal' )
                    );
                    ?>
                </div>

                <div
                    id="notifal-campaign-onpage-picker-root"
                    class="notifal-campaign-onpage-picker"
                    data-initial-items="<?php echo esc_attr( wp_json_encode( $onpage_picker_initial ) ); ?>"
                >
                    <div class="notifal-campaign-onpage-search-wrap">
                        <input
                            type="search"
                            id="notifal-campaign-onpage-search-input"
                            class="notifal-campaign-onpage-search-input"
                            autocomplete="off"
                            placeholder="<?php echo esc_attr( __( 'Type to search on-page notifications…', 'notifal' ) ); ?>"
                            aria-label="<?php echo esc_attr( __( 'Search on-page notifications to assign', 'notifal' ) ); ?>"
                        />
                        <div
                            id="notifal-campaign-onpage-search-results"
                            class="notifal-campaign-onpage-search-results notifal-hidden"
                            role="listbox"
                            aria-label="<?php echo esc_attr( __( 'Search results', 'notifal' ) ); ?>"
                        ></div>
                    </div>

                    <p id="notifal-campaign-onpage-selected-heading" class="notifal-campaign-onpage-selected-heading notifal-hidden">
                        <?php esc_html_e( 'Selected notifications', 'notifal' ); ?>
                    </p>
                    <div
                        id="notifal-campaign-onpage-selected-list"
                        class="notifal-campaign-onpage-selected-list"
                        role="list"
                        aria-labelledby="notifal-campaign-onpage-selected-heading"
                    ></div>
                </div>
            </div>

            <div class="notifal-form-actions notifal-mt-20 notifal-justify-center">
                <button
                    type="button"
                    class="notifal-button notifal-button-primary notifal-save-campaign-btn"
                    id="notifal-save-campaign-btn"
                    data-action="notifal_save_campaign"
                >
                    <?php esc_html_e( 'Save Campaign', 'notifal' ); ?>
                </button>
            </div>
        </div>
    </form>
</div>
