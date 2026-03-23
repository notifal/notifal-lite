<?php

namespace Notifal\Modules\Campaign\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Campaign\Infrastructure\WordPress\Repositories\CampaignQuery;
use Notifal\Modules\OnPageNotification\Application\Support\ScheduleDateTimeHelper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CampaignSettingsService
 *
 * Handles campaign settings defaults (sanitization), sanitization, and schedule validation.
 * Campaign schedules use `start_date`/`end_date` stored as UTC (`Z`), same as on-page timing.
 * Eligibility uses {@see ScheduleDateTimeHelper::isNowWithinBoundaries()}.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class CampaignSettingsService
{
    /**
     * Default settings stored in the `_notifal_campaign_settings` meta.
     *
     * @since 2.0.0
     * @var array<string, mixed>
     */
    private const DEFAULT_SETTINGS = [
        'description' => '',
        'status'      => 'active',
        'ended'       => false,
        'start_date'  => '',
        'end_date'    => '',
        'priority'    => 5,
    ];

    /**
     * Get default campaign settings.
     *
     * @since 2.0.0
     * @return array<string, mixed>
     */
    public function getDefaultSettings(): array
    {
        return self::DEFAULT_SETTINGS;
    }

    /**
     * Sanitize campaign settings before persisting them.
     *
     * @since 2.0.0
     * @param array $settings Raw settings received from admin UI.
     * @return array<string, mixed> Sanitized settings ready for storage.
     */
    public function sanitizeSettings(array $settings): array
    {
        $defaults = $this->getDefaultSettings();
        $sanitized = [];

        $sanitized['description'] = isset($settings['description'])
            ? sanitize_text_field( (string) $settings['description'] )
            : (string) $defaults['description'];

        $rawStatus = isset( $settings['status'] ) ? (string) $settings['status'] : (string) $defaults['status'];
        if ( $rawStatus === 'inactive' ) {
            $rawStatus = 'paused';
        }
        $allowedStatuses = [ 'active', 'paused' ];
        $sanitized['status'] = in_array( $rawStatus, $allowedStatuses, true ) ? $rawStatus : (string) $defaults['status'];

        $sanitized['ended'] = ! empty( $settings['ended'] );

        $sanitized['priority'] = $this->sanitizePriority(
            isset($settings['priority']) ? $settings['priority'] : $defaults['priority']
        );

        $sanitized['start_date'] = $this->sanitizeDateTime( $settings['start_date'] ?? '' );
        $sanitized['end_date'] = $this->sanitizeDateTime( $settings['end_date'] ?? '' );

        // Schedule validation: ensure `start_date` is not after `end_date`.
        $startTimestamp = ! empty( $sanitized['start_date'] ) ? ScheduleDateTimeHelper::boundaryToUnixTimestamp( (string) $sanitized['start_date'] ) : null;
        $endTimestamp = ! empty( $sanitized['end_date'] ) ? ScheduleDateTimeHelper::boundaryToUnixTimestamp( (string) $sanitized['end_date'] ) : null;

        if ( $startTimestamp !== null && $endTimestamp !== null && $startTimestamp > $endTimestamp ) {
            // Keep start_date valid and clear end_date to avoid invalid schedules.
            $sanitized['end_date'] = '';
        }

        $sanitized = $this->applyScheduleEndedFlags( $sanitized );

        /**
         * Filter campaign settings before they are stored/used.
         *
         * @hook notifal/campaign/settings
         * @since 2.0.0
         * @param array<string, mixed> $sanitized Sanitized settings.
         * @param array $settings Raw settings input.
         */
        return apply_filters( FilterHooks::CAMPAIGN_SETTINGS, $sanitized, $settings );
    }

    /**
     * Maps legacy stored `inactive` user pause value to `paused` for UI and schedule checks.
     *
     * @since 2.2.0
     * @param string $status Raw stored status from meta.
     * @return string Normalized status (`active` or `paused`).
     */
    public function normalizeStoredStatus( string $status ): string {
        return $status === 'inactive' ? 'paused' : $status;
    }

    /**
     * Whether the campaign should appear “ended” in admin UI: end instant is in the past, or no end date with `ended` set.
     *
     * Does not treat `ended` meta alone as authoritative when a parseable `end_date` is still in the future
     * (fixes stale `ended` after timezone / parsing fixes).
     *
     * @since 2.2.0
     * @param array<string, mixed> $settings   Campaign settings meta.
     * @param int                    $campaignId Optional campaign ID (reserved for callers).
     * @return bool True when the schedule is considered ended for display.
     */
    public function isCampaignScheduleEndedForDisplay( array $settings, int $campaignId = 0 ): bool {
        $end = isset( $settings['end_date'] ) ? trim( (string) $settings['end_date'] ) : '';
        $now = time();
        $endedMeta = ! empty( $settings['ended'] );

        if ( $end === '' ) {
            return $endedMeta;
        }

        $endTs = ScheduleDateTimeHelper::boundaryToUnixTimestamp( $end );
        if ( $endTs === null ) {
            return $endedMeta;
        }

        return $now > $endTs;
    }

    /**
     * Sets the `ended` flag from the schedule window: past end means ended; future or empty end clears it.
     *
     * @since 2.2.0
     * @param array<string, mixed> $settings Sanitized campaign settings.
     * @return array<string, mixed> Settings with `ended` aligned to `end_date` vs now.
     */
    private function applyScheduleEndedFlags( array $settings ): array {
        $end = isset( $settings['end_date'] ) ? (string) $settings['end_date'] : '';
        if ( $end === '' ) {
            $settings['ended'] = false;

            return $settings;
        }

        $end_timestamp = ScheduleDateTimeHelper::boundaryToUnixTimestamp( $end );
        if ( $end_timestamp === null ) {
            return $settings;
        }

        if ( time() > $end_timestamp ) {
            $settings['ended'] = true;
        } else {
            $settings['ended'] = false;
        }

        return $settings;
    }

    /**
     * Check if a campaign is within its configured schedule.
     *
     * @since 2.0.0
     * @param int $campaignId Campaign post ID.
     * @return bool True if within schedule, false otherwise.
     */
    public function isWithinSchedule( int $campaignId ): bool
    {
        if ( $campaignId <= 0 ) {
            return false;
        }

        $campaignPost = get_post( $campaignId );
        if ( ! $campaignPost || $campaignPost->post_status !== 'publish' ) {
            return false;
        }

        $settings = get_post_meta( $campaignId, '_notifal_campaign_settings', true );
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }

        $status = isset( $settings['status'] ) ? (string) $settings['status'] : 'active';
        $status = $this->normalizeStoredStatus( $status );
        if ( $status !== 'active' ) {
            return false;
        }

        if ( $this->isCampaignScheduleEndedForDisplay( $settings, $campaignId ) ) {
            return false;
        }

        return ScheduleDateTimeHelper::isNowWithinBoundaries(
            (string) ( $settings['start_date'] ?? '' ),
            (string) ( $settings['end_date'] ?? '' )
        );
    }

    /**
     * Resolve the admin list badge key for a campaign row (order matches list table rules).
     *
     * @since 2.2.0
     * @param array<string, mixed> $settings Campaign settings meta.
     * @param \WP_Post               $post    Campaign post.
     * @return string Badge key: inactive|ended|paused|scheduled|active.
     */
    public function getDisplayBadgeKey( array $settings, \WP_Post $post ): string {
        if ( $post->post_status !== 'publish' ) {
            return 'inactive';
        }

        if ( $this->isCampaignScheduleEndedForDisplay( $settings, (int) $post->ID ) ) {
            return 'ended';
        }

        $status = isset( $settings['status'] ) ? (string) $settings['status'] : 'active';
        $status = $this->normalizeStoredStatus( $status );
        if ( $status !== 'active' ) {
            return 'paused';
        }

        $now = time();
        $startDate = ! empty( $settings['start_date'] ) ? (string) $settings['start_date'] : '';
        if ( $startDate !== '' ) {
            $startTs = ScheduleDateTimeHelper::boundaryToUnixTimestamp( $startDate );
            if ( $startTs !== null && $now < $startTs ) {
                return 'scheduled';
            }
        }

        return 'active';
    }

    /**
     * Human-readable label for a campaign status badge key.
     *
     * @since 2.2.0
     * @param string $badgeKey Badge key from {@see self::getDisplayBadgeKey()}.
     * @return string Localized label.
     */
    public function getStatusBadgeLabel( string $badgeKey ): string {
        switch ( $badgeKey ) {
            case 'scheduled':
                return __( 'Scheduled', 'notifal' );
            case 'ended':
                return __( 'Ended', 'notifal' );
            case 'inactive':
                return __( 'Inactive', 'notifal' );
            case 'paused':
                return __( 'Paused', 'notifal' );
            case 'active':
            default:
                return __( 'Active', 'notifal' );
        }
    }

    /**
     * Marks published campaigns as ended when their end datetime is already past.
     *
     * Sets `ended` to true (keeps user `status` so pause vs expiry stay distinguishable).
     * Used by the hourly cron so list status and notification eligibility stay aligned without manual saves.
     *
     * @since 2.2.0
     * @return int Number of campaigns updated.
     */
    public function markExpiredCampaigns(): int {
        $campaigns = CampaignQuery::getActive();
        $updated   = 0;

        foreach ( $campaigns as $post ) {
            if ( ! $post instanceof \WP_Post ) {
                continue;
            }

            $campaignId = (int) $post->ID;
            $settings   = get_post_meta( $campaignId, '_notifal_campaign_settings', true );

            if ( ! is_array( $settings ) ) {
                continue;
            }

            $end = isset( $settings['end_date'] ) ? (string) $settings['end_date'] : '';
            if ( $end === '' ) {
                continue;
            }

            $endTs = ScheduleDateTimeHelper::boundaryToUnixTimestamp( $end );
            if ( $endTs === null ) {
                continue;
            }

            if ( time() <= $endTs ) {
                continue;
            }

            if ( ! empty( $settings['ended'] ) ) {
                continue;
            }

            $previousSettings = $settings;
            $settings['ended'] = true;

            update_post_meta( $campaignId, '_notifal_campaign_settings', $settings );

            do_action( ActionHooks::CAMPAIGN_STATUS_CHANGED, $campaignId, $previousSettings, $settings );

            $updated++;
        }

        return $updated;
    }

    /**
     * Sanitize a datetime-local value into canonical UTC storage (`Y-m-d\TH:i:s\Z`).
     *
     * @since 2.0.0
     * @param mixed $value Raw date value from admin UI.
     * @return string Stored UTC string or empty when invalid.
     */
    private function sanitizeDateTime( $value ): string
    {
        if ( empty( $value ) ) {
            return '';
        }

        return ScheduleDateTimeHelper::sanitizeIncomingToStoredUtc( sanitize_text_field( (string) $value ) );
    }

    /**
     * Sanitize campaign priority within range 1-10.
     *
     * @since 2.0.0
     * @param mixed $value Raw priority input.
     * @return int Sanitized priority.
     */
    private function sanitizePriority( $value ): int
    {
        $priority = (int) $value;
        if ( $priority < 1 ) {
            return 1;
        }
        if ( $priority > 10 ) {
            return 10;
        }
        return $priority;
    }
}

