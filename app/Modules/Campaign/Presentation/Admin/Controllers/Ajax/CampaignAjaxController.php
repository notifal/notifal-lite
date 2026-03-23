<?php

namespace Notifal\Modules\Campaign\Presentation\Admin\Controllers\Ajax;

use Notifal\Modules\Campaign\Application\Services\CampaignService;
use Notifal\Modules\Campaign\Infrastructure\WordPress\Repositories\CampaignQuery;
use Notifal\Modules\Campaign\Infrastructure\WordPress\Repositories\OnpageNotificationForCampaignPickerQuery;
use Notifal\Modules\OnPageNotification\Application\Support\ScheduleDateTimeHelper;

defined('ABSPATH') || exit;

/**
 * Class CampaignAjaxController
 *
 * Handles Campaign-related AJAX actions:
 * - notifal_save_campaign
 * - notifal_get_campaign_data
 * - notifal_get_campaign_options
 * - notifal_search_onpage_notifications_for_campaign
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\Campaign\Presentation\Admin\Controllers\Ajax
 */
class CampaignAjaxController
{
    /**
     * Register AJAX handlers.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register(): void
    {
        add_action( 'wp_ajax_notifal_save_campaign', [ self::class, 'handleSaveCampaign' ] );
        add_action( 'wp_ajax_notifal_get_campaign_data', [ self::class, 'handleGetCampaignData' ] );
        add_action( 'wp_ajax_notifal_get_campaign_options', [ self::class, 'handleGetCampaignOptions' ] );
        add_action( 'wp_ajax_notifal_search_onpage_notifications_for_campaign', [ self::class, 'handleSearchOnpageNotificationsForCampaign' ] );
    }

    /**
     * AJAX: Save campaign (create/update).
     *
     * @since 2.0.0
     * @return void
     */
    public static function handleSaveCampaign(): void
    {
        try {
            // SECURITY: Verify nonce and capability.
            notifal_verify_ajax_request( 'notifal_campaign_save', 'manage_options' );

            $service = notifal_app( CampaignService::class );
            $postId = $service->save( $_POST );

            notifal_json_success([
                'campaign_id' => $postId,
            ]);
        } catch ( \RuntimeException $e ) {
            notifal_json_error( $e->getMessage() );
        } catch ( \Throwable $e ) {
            notifal_json_error( __( 'An unexpected error occurred. Please try again.', 'notifal' ) );
        }
    }

    /**
     * AJAX: Get campaign data for notification schedule UI.
     *
     * @since 2.0.0
     * @return void
     */
    public static function handleGetCampaignData(): void
    {
        try {
            notifal_verify_ajax_request( 'notifal_campaign_save', 'manage_options' );

            $campaignId = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
            if ( $campaignId <= 0 ) {
                notifal_json_error( __( 'Invalid campaign ID.', 'notifal' ) );
                return;
            }

            $campaignPost = CampaignQuery::get( $campaignId );
            if ( ! $campaignPost ) {
                notifal_json_error( __( 'Campaign not found.', 'notifal' ) );
                return;
            }

            $settings = get_post_meta( $campaignId, '_notifal_campaign_settings', true );
            if ( ! is_array( $settings ) ) {
                $settings = [];
            }

            $startDate = ! empty( $settings['start_date'] ) ? (string) $settings['start_date'] : '';
            $endDate = ! empty( $settings['end_date'] ) ? (string) $settings['end_date'] : '';

            $formattedStart = '';
            $formattedEnd = '';

            if ( ! empty( $startDate ) ) {
                $formattedStart = ScheduleDateTimeHelper::formatStoredUtcForAdminDisplay( $startDate );
            }

            if ( ! empty( $endDate ) ) {
                $formattedEnd = ScheduleDateTimeHelper::formatStoredUtcForAdminDisplay( $endDate );
            }

            notifal_json_success([
                'campaign_id' => $campaignId,
                'campaign_name' => (string) $campaignPost->post_title,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'formatted_start_date' => $formattedStart,
                'formatted_end_date' => $formattedEnd,
            ]);
        } catch ( \RuntimeException $e ) {
            notifal_json_error( $e->getMessage() );
        } catch ( \Throwable $e ) {
            notifal_json_error( __( 'An unexpected error occurred. Please try again.', 'notifal' ) );
        }
    }

    /**
     * AJAX: Get campaigns as options.
     *
     * @since 2.0.0
     * @return void
     */
    public static function handleGetCampaignOptions(): void
    {
        try {
            notifal_verify_ajax_request( 'notifal_campaign_save', 'manage_options' );

            $options = CampaignQuery::getCampaignOptions();
            notifal_json_success([
                'options' => $options,
            ]);
        } catch ( \Throwable $e ) {
            notifal_json_error( __( 'An unexpected error occurred. Please try again.', 'notifal' ) );
        }
    }

    /**
     * AJAX: Search on-page notifications for the campaign assignment picker.
     *
     * @since 2.0.0
     * @return void
     */
    public static function handleSearchOnpageNotificationsForCampaign(): void
    {
        try {
            notifal_verify_ajax_request( 'notifal_campaign_save', 'manage_options' );

            $search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['search'] ) ) : '';

            $items = OnpageNotificationForCampaignPickerQuery::search( $search, 20 );

            notifal_json_success(
                [
                    'items' => $items,
                ]
            );
        } catch ( \RuntimeException $e ) {
            notifal_json_error( $e->getMessage() );
        } catch ( \Throwable $e ) {
            notifal_json_error( __( 'An unexpected error occurred. Please try again.', 'notifal' ) );
        }
    }
}

