<?php

namespace Notifal\Modules\Campaign\Presentation\Admin\Controllers\Ajax;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\Campaign\Application\Services\CampaignSettingsService;

defined( 'ABSPATH' ) || exit;

/**
 * AJAX handler for toggling campaign meta `status` between `active` and `paused` from the list table.
 *
 * Ended and scheduled rows use non-clickable badges; this endpoint only applies when the row shows Active or Paused.
 *
 * @since 2.2.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\Campaign\Presentation\Admin\Controllers\Ajax
 */
class CampaignToggleStatusController {

    /**
     * Register AJAX actions.
     *
     * @since 2.2.0
     * @return void
     */
    public static function register(): void {
        add_action( 'wp_ajax_notifal_toggle_campaign_status', [ self::class, 'handle' ] );
    }

    /**
     * Handle `notifal_toggle_campaign_status` requests.
     *
     * @since 2.2.0
     * @return void
     */
    public static function handle(): void {
        notifal_verify_ajax_request( 'notifal_toggle_campaign_status', 'manage_options' );

        $campaignId = isset( $_POST['campaign_id'] ) ? absint( wp_unslash( (string) $_POST['campaign_id'] ) ) : 0;
        if ( $campaignId <= 0 ) {
            notifal_json_error( __( 'Invalid campaign ID.', 'notifal' ) );
        }

        $post = get_post( $campaignId );
        if ( ! $post || $post->post_type !== 'notifal_campaign' ) {
            notifal_json_error( __( 'Campaign not found.', 'notifal' ) );
        }

        $settingsService = notifal_app( CampaignSettingsService::class );

        $settings = get_post_meta( $campaignId, '_notifal_campaign_settings', true );
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }

        $badgeKey = $settingsService->getDisplayBadgeKey( $settings, $post );
        if ( $badgeKey !== 'active' && $badgeKey !== 'paused' ) {
            notifal_json_error( __( 'This campaign status cannot be toggled from the list.', 'notifal' ) );
        }

        $status = isset( $settings['status'] ) ? (string) $settings['status'] : 'active';
        $status = $settingsService->normalizeStoredStatus( $status );
        $newStatus = ( $status === 'active' ) ? 'paused' : 'active';

        $previousSettings = $settings;
        $settings['status'] = $newStatus;

        update_post_meta( $campaignId, '_notifal_campaign_settings', $settings );

        do_action( ActionHooks::CAMPAIGN_STATUS_CHANGED, $campaignId, $previousSettings, $settings );

        $postRefreshed = get_post( $campaignId );
        if ( ! $postRefreshed instanceof \WP_Post ) {
            $postRefreshed = $post;
        }

        $settingsAfter = get_post_meta( $campaignId, '_notifal_campaign_settings', true );
        if ( ! is_array( $settingsAfter ) ) {
            $settingsAfter = $settings;
        }

        $newBadgeKey = $settingsService->getDisplayBadgeKey( $settingsAfter, $postRefreshed );

        notifal_json_success(
            [
                'campaign_id'   => $campaignId,
                'status_label'  => $settingsService->getStatusBadgeLabel( $newBadgeKey ),
                'status_class'  => 'notifal-status-' . $newBadgeKey,
                'is_active'     => $newStatus === 'active',
                'badge_key'     => $newBadgeKey,
            ]
        );
    }
}
