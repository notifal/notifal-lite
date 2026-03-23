<?php

namespace Notifal\Modules\Campaign\Presentation\Admin\ListTable;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Campaign\Application\Services\CampaignSettingsService;
use Notifal\Modules\Campaign\Application\Services\CampaignService;
use Notifal\Modules\OnPageNotification\Application\Support\ScheduleDateTimeHelper;
use Notifal\Shared\Utils\Helper;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Class ColumnsController
 *
 * Registers custom column renderers for the Campaign admin list table.
 * Columns:
 * - status: Active/Paused/Scheduled/Ended/Inactive badge (active and paused are toggle buttons)
 * - notifications: Assigned notifications count (linked to filtered list)
 * - schedule: Formatted schedule or "No schedule"
 *
 * The `notifal_campaign` post type uses `show_ui` => false, so WordPress clears `_edit_link` and core
 * returns no edit URL. {@see self::modifyEditPostLink()} points editors to the Notifal campaign screen.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\Campaign\Presentation\Admin\ListTable
 */
class ColumnsController
{
    /**
     * Register all filters for custom list table columns.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register(): void
    {
        add_filter( FilterHooks::ADMIN_LIST_CUSTOM_COLUMN, [ self::class, 'renderStatusColumn' ], 10, 4 );
        add_filter( FilterHooks::ADMIN_LIST_CUSTOM_COLUMN, [ self::class, 'renderNotificationsColumn' ], 10, 4 );
        add_filter( FilterHooks::ADMIN_LIST_CUSTOM_COLUMN, [ self::class, 'renderScheduleColumn' ], 10, 4 );
        add_filter( FilterHooks::ADMIN_LIST_ROW_ACTIONS, [ self::class, 'modifyRowActions' ], 10, 3 );
        add_filter( 'get_edit_post_link', [ self::class, 'modifyEditPostLink' ], 10, 3 );
    }

    /**
     * Render the `status` column for Campaign list.
     *
     * @since 2.0.0
     * @param string $content Default column content.
     * @param string $columnKey Current column key.
     * @param \WP_Post $post Campaign post.
     * @param string $postType Post type.
     * @return string Rendered HTML.
     */
    public static function renderStatusColumn( string $content, string $columnKey, \WP_Post $post, string $postType ): string
    {
        if ( $postType !== 'notifal_campaign' || $columnKey !== 'status' ) {
            return $content;
        }

        $settings = get_post_meta( $post->ID, '_notifal_campaign_settings', true );
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }

        $settingsService = notifal_app( CampaignSettingsService::class );
        $badgeKey        = $settingsService->getDisplayBadgeKey( $settings, $post );
        $badgeLabel      = $settingsService->getStatusBadgeLabel( $badgeKey );
        $badgeClass      = 'notifal-status-' . $badgeKey;

        if ( $badgeKey === 'active' || $badgeKey === 'paused' ) {
            $isActiveMeta = $badgeKey === 'active';

            return sprintf(
                '<button type="button" class="notifal-status-badge notifal-status-toggle notifal-campaign-status-toggle %s" data-campaign-id="%d" data-current-active="%s" title="%s" aria-label="%s">%s</button>',
                esc_attr( $badgeClass ),
                (int) $post->ID,
                $isActiveMeta ? '1' : '0',
                esc_attr__( 'Click to change campaign active or paused state.', 'notifal' ),
                esc_attr(
                    sprintf(
                        /* translators: %s: Current campaign status label. */
                        __( 'Toggle campaign status. Current: %s', 'notifal' ),
                        $badgeLabel
                    )
                ),
                esc_html( $badgeLabel )
            );
        }

        return sprintf(
            '<span class="notifal-status-badge %s">%s</span>',
            esc_attr( $badgeClass ),
            esc_html( $badgeLabel )
        );
    }

    /**
     * Render the `notifications` column for Campaign list.
     *
     * @since 2.0.0
     * @param string $content Default column content.
     * @param string $columnKey Current column key.
     * @param \WP_Post $post Campaign post.
     * @param string $postType Post type.
     * @return string Rendered HTML.
     */
    public static function renderNotificationsColumn( string $content, string $columnKey, \WP_Post $post, string $postType ): string
    {
        if ( $postType !== 'notifal_campaign' || $columnKey !== 'notifications' ) {
            return $content;
        }

        $campaignId = (int) $post->ID;
        $campaignService = notifal_app( CampaignService::class );
        $count = $campaignService->getNotificationCount( $campaignId );

        $baseUrl = admin_url( 'admin.php' );
        $urlArgs = [
            'page'          => 'notifal-onpage-notifications',
            'campaign_id'  => $campaignId,
        ];

        if ( isset( $_GET['status'] ) ) {
            $status = Helper::sanitizeInput( wp_unslash( $_GET['status'] ), 'key' );
            if ( ! empty( $status ) ) {
                $urlArgs['status'] = $status;
            }
        }

        $url = add_query_arg( $urlArgs, $baseUrl );

        return sprintf(
            '<a href="%s" class="notifal-list-link" data-campaign-id="%d">%d</a>',
            esc_url( $url ),
            esc_attr( $campaignId ),
            (int) $count
        );
    }

    /**
     * Remove front-end "View" from row actions; campaigns are not public.
     *
     * @since 2.2.0
     * @param array<string, string> $actions  Action key => HTML.
     * @param WP_Post               $post     Current row post.
     * @param string                $postType List table post type.
     * @return array<string, string>
     */
    public static function modifyRowActions( array $actions, WP_Post $post, string $postType ): array {
        if ( $postType !== 'notifal_campaign' ) {
            return $actions;
        }

        unset( $actions['view'] );

        return $actions;
    }

    /**
     * Return the Notifal admin URL for editing a campaign (hidden CPT without core edit UI).
     *
     * @since 2.2.0
     * @param string          $link    Default URL from WordPress (empty when `show_ui` is false).
     * @param int|string      $post_id Campaign post ID.
     * @param string          $context `display` or `raw` (unused; callers escape on output).
     * @return string
     */
    public static function modifyEditPostLink( string $link, $post_id, $context = 'display' ): string {
        $post = get_post( (int) $post_id );
        if ( ! $post instanceof WP_Post || $post->post_type !== 'notifal_campaign' ) {
            return $link;
        }

        return add_query_arg(
            [
                'page'   => 'notifal-campaign',
                'action' => 'edit',
                'id'     => (int) $post_id,
            ],
            admin_url( 'admin.php' )
        );
    }

    /**
     * Render the `schedule` column for Campaign list.
     *
     * @since 2.0.0
     * @param string $content Default column content.
     * @param string $columnKey Current column key.
     * @param \WP_Post $post Campaign post.
     * @param string $postType Post type.
     * @return string Rendered HTML.
     */
    public static function renderScheduleColumn( string $content, string $columnKey, \WP_Post $post, string $postType ): string
    {
        if ( $postType !== 'notifal_campaign' || $columnKey !== 'schedule' ) {
            return $content;
        }

        $settings = get_post_meta( $post->ID, '_notifal_campaign_settings', true );
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }

        $startDate = ! empty( $settings['start_date'] ) ? (string) $settings['start_date'] : '';
        $endDate = ! empty( $settings['end_date'] ) ? (string) $settings['end_date'] : '';

        if ( empty( $startDate ) && empty( $endDate ) ) {
            return '<span class="notifal-no-schedule">' . esc_html__( 'No schedule', 'notifal' ) . '</span>';
        }

        $startText = '';
        $endText = '';

        if ( ! empty( $startDate ) ) {
            $startText = ScheduleDateTimeHelper::formatStoredUtcForAdminDisplay( $startDate );
        }

        if ( ! empty( $endDate ) ) {
            $endText = ScheduleDateTimeHelper::formatStoredUtcForAdminDisplay( $endDate );
        }

        if ( ! empty( $startText ) && ! empty( $endText ) ) {
            return sprintf(
                '<span>%s - %s</span>',
                esc_html( $startText ),
                esc_html( $endText )
            );
        }

        if ( ! empty( $startText ) ) {
            return sprintf(
                '<span>%s %s</span>',
                esc_html__( 'Starts on', 'notifal' ),
                esc_html( $startText )
            );
        }

        return sprintf(
            '<span>%s %s</span>',
            esc_html__( 'Ends on', 'notifal' ),
            esc_html( $endText )
        );
    }

}

