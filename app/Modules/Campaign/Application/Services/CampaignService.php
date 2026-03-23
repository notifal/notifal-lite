<?php

namespace Notifal\Modules\Campaign\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CampaignService
 *
 * Provides CRUD operations for the `notifal_campaign` post type.
 * Campaigns store their scheduling configuration in `_notifal_campaign_settings`
 * meta and can be assigned to notifications via `_notifal_campaign_id` meta.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class CampaignService
{
    /**
     * @var CampaignSettingsService
     */
    private $settingsService;

    /**
     * Constructor.
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->settingsService = notifal_app( CampaignSettingsService::class );
    }

    /**
     * Save (create or update) a campaign post and its settings meta.
     *
     * @since 2.0.0
     * @param array $data Raw input coming from AJAX/form data.
     * @return int Campaign post ID.
     * @throws \RuntimeException When validation fails.
     */
    public function save( array $data ): int
    {
        // SECURITY: Validate nonce inside service as requested by feature plan.
        $nonce = isset( $data['nonce'] ) ? (string) $data['nonce'] : '';
        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'notifal_campaign_save' ) ) {
            throw new \RuntimeException( __( 'Security check failed. Please refresh the page and try again.', 'notifal' ) );
        }

        $campaignId = isset( $data['campaign_id'] ) ? absint( $data['campaign_id'] ) : 0;
        $title = isset( $data['campaign_title'] ) ? sanitize_text_field( (string) $data['campaign_title'] ) : '';
        if ( empty( $title ) ) {
            throw new \RuntimeException( __( 'Campaign title is required.', 'notifal' ) );
        }

        $priority = isset( $data['priority'] ) ? $data['priority'] : 5;

        $existingSettings = [];
        if ( $campaignId > 0 ) {
            $stored = get_post_meta( $campaignId, '_notifal_campaign_settings', true );
            if ( is_array( $stored ) ) {
                $existingSettings = $stored;
            }
        }

        $statusForSanitize = $this->resolveStatusFromSavePayload( $data, $existingSettings, $campaignId );

        $settings = $this->settingsService->sanitizeSettings([
            'description' => $data['campaign_description'] ?? '',
            'start_date'  => $data['start_date'] ?? '',
            'end_date'    => $data['end_date'] ?? '',
            'priority'    => $priority,
            'status'      => $statusForSanitize,
            'ended'       => isset( $existingSettings['ended'] ) ? $existingSettings['ended'] : false,
        ]);

        $postArgs = [
            'post_type'   => 'notifal_campaign',
            'post_title'  => $title,
            'post_status' => 'publish',
        ];

        if ( $campaignId > 0 ) {
            $postArgs['ID'] = $campaignId;
        }

        $postId = wp_insert_post( $postArgs, true );
        if ( is_wp_error( $postId ) ) {
            throw new \RuntimeException( $postId->get_error_message() );
        }

        update_post_meta( $postId, '_notifal_campaign_settings', $settings );

        $notification_ids = self::parseNotificationIdsFromPayload( $data );
        $this->syncOnpageNotificationsToCampaign( (int) $postId, $notification_ids );

        do_action( ActionHooks::CAMPAIGN_SAVED, $postId, $settings );

        return (int) $postId;
    }

    /**
     * Resolve the `status` value passed into {@see CampaignSettingsService::sanitizeSettings()} from the save request.
     *
     * The edit form sends a hidden `campaign_status=paused` plus a checkbox with the same name and `value="active"`.
     * When the box is checked, both submit and the last value is `active`; when unchecked, only `paused` is sent.
     *
     * @since 2.2.0
     * @param array<string, mixed> $data             Raw save payload.
     * @param array<string, mixed> $existingSettings Current meta when updating.
     * @param int                  $campaignId       Campaign ID (0 when creating).
     * @return string `active` or `paused`.
     */
    private function resolveStatusFromSavePayload( array $data, array $existingSettings, int $campaignId ): string {
        $defaults = $this->settingsService->getDefaultSettings();

        if ( isset( $data['campaign_status'] ) ) {
            $value = $data['campaign_status'];
            if ( is_array( $value ) ) {
                $raw = (string) end( $value );
            } else {
                $raw = (string) $value;
            }

            if ( $raw === 'active' || $raw === 'on' || $raw === '1' ) {
                return 'active';
            }

            return 'paused';
        }

        if ( $campaignId > 0 ) {
            $prev = isset( $existingSettings['status'] ) ? (string) $existingSettings['status'] : (string) $defaults['status'];
            $prev = $this->settingsService->normalizeStoredStatus( $prev );

            return in_array( $prev, [ 'active', 'paused' ], true ) ? $prev : 'active';
        }

        return 'paused';
    }

    /**
     * Normalize notification ID list from request data (FormData array or scalar values).
     *
     * @since 2.0.0
     * @param array<string, mixed> $data Raw request payload.
     * @return array<int, int> Unique positive notification IDs.
     */
    private static function parseNotificationIdsFromPayload( array $data ): array
    {
        $raw = [];

        if ( isset( $data['notification_ids'] ) && is_array( $data['notification_ids'] ) ) {
            $raw = $data['notification_ids'];
        }

        $out = [];

        foreach ( $raw as $value ) {
            $id = absint( $value );
            if ( $id > 0 ) {
                $out[] = $id;
            }
        }

        return array_values( array_unique( $out ) );
    }

    /**
     * Assign `_notifal_campaign_id` on selected on-page notifications and unlink removed ones.
     *
     * @since 2.0.0
     * @param int        $campaignId Campaign post ID.
     * @param array<int, int> $desiredIds Notification IDs that should reference this campaign.
     * @return void
     */
    private function syncOnpageNotificationsToCampaign( int $campaignId, array $desiredIds ): void
    {
        if ( $campaignId <= 0 ) {
            return;
        }

        $previous_ids = $this->getNotificationIds( $campaignId );

        foreach ( $previous_ids as $notification_id ) {
            if ( ! in_array( $notification_id, $desiredIds, true ) ) {
                $current = (int) get_post_meta( $notification_id, '_notifal_campaign_id', true );
                if ( $current === $campaignId ) {
                    delete_post_meta( $notification_id, '_notifal_campaign_id' );
                }
            }
        }

        foreach ( $desiredIds as $notification_id ) {
            if ( $notification_id <= 0 ) {
                continue;
            }

            $post = get_post( $notification_id );
            if ( ! $post || $post->post_type !== 'notifal_onpage_notif' ) {
                continue;
            }

            update_post_meta( $notification_id, '_notifal_campaign_id', $campaignId );
        }
    }

    /**
     * Delete a campaign by trashing it and unlinking assigned notifications.
     *
     * @since 2.0.0
     * @param int $id Campaign post ID.
     * @return bool True on success, false otherwise.
     */
    public function delete( int $id ): bool
    {
        if ( $id <= 0 ) {
            return false;
        }

        $campaignPost = get_post( $id );
        if ( ! $campaignPost || $campaignPost->post_type !== 'notifal_campaign' ) {
            return false;
        }

        // Unlink notifications assigned to this campaign.
        $notificationIds = $this->getNotificationIds( $id );
        foreach ( $notificationIds as $notificationId ) {
            delete_post_meta( $notificationId, '_notifal_campaign_id' );
        }

        $result = wp_trash_post( $id );
        if ( ! $result ) {
            return false;
        }

        do_action( ActionHooks::CAMPAIGN_DELETED, $id );

        return true;
    }

    /**
     * Get notifications assigned to a specific campaign.
     *
     * @since 2.0.0
     * @param int $campaignId Campaign post ID.
     * @return array<int, \WP_Post> Assigned notifications.
     */
    public function getNotifications( int $campaignId ): array
    {
        if ( $campaignId <= 0 ) {
            return [];
        }

        $posts = get_posts([
            'post_type'      => 'notifal_onpage_notif',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'     => '_notifal_campaign_id',
                    'value'   => $campaignId,
                    'compare' => '=',
                ],
            ],
        ]);

        return is_array( $posts ) ? $posts : [];
    }

    /**
     * Get the count of notifications assigned to a campaign.
     *
     * @since 2.0.0
     * @param int $campaignId Campaign post ID.
     * @return int Notification count.
     */
    public function getNotificationCount( int $campaignId ): int
    {
        if ( $campaignId <= 0 ) {
            return 0;
        }

        $query = new WP_Query([
            'post_type'      => 'notifal_onpage_notif',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => '_notifal_campaign_id',
                    'value'   => $campaignId,
                    'compare' => '=',
                ],
            ],
        ]);

        return isset( $query->found_posts ) ? (int) $query->found_posts : 0;
    }

    /**
     * Get assigned notification IDs for internal operations.
     *
     * @since 2.0.0
     * @param int $campaignId Campaign post ID.
     * @return array<int, int> List of notification IDs.
     */
    private function getNotificationIds( int $campaignId ): array
    {
        if ( $campaignId <= 0 ) {
            return [];
        }

        $query = new WP_Query([
            'post_type'      => 'notifal_onpage_notif',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => '_notifal_campaign_id',
                    'value'   => $campaignId,
                    'compare' => '=',
                ],
            ],
        ]);

        $ids = isset( $query->posts ) && is_array( $query->posts ) ? $query->posts : [];
        return array_map( 'absint', $ids );
    }
}

