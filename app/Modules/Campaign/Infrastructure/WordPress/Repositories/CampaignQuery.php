<?php

namespace Notifal\Modules\Campaign\Infrastructure\WordPress\Repositories;

use Notifal\Shared\Utils\Helper;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Class CampaignQuery
 *
 * Repository-like query helpers for the `notifal_campaign` post type.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\Campaign\Infrastructure\WordPress\Repositories
 */
class CampaignQuery
{
    /**
     * Get all campaigns (including draft/trash).
     *
     * @since 2.0.0
     * @return array<int, WP_Post>
     */
    public static function getAll(): array
    {
        $campaigns = get_posts([
            'post_type'      => 'notifal_campaign',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        return is_array( $campaigns ) ? $campaigns : [];
    }

    /**
     * Get active campaigns (published).
     *
     * Uses `post_status = publish` only. Campaign pause/expiry is stored in `_notifal_campaign_settings`
     * (`status`, `ended`) and is not mapped to draft/trash.
     *
     * @since 2.0.0
     * @return array<int, WP_Post>
     */
    public static function getActive(): array
    {
        $campaigns = get_posts([
            'post_type'      => 'notifal_campaign',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        return is_array( $campaigns ) ? $campaigns : [];
    }

    /**
     * Get a single campaign by ID.
     *
     * @since 2.0.0
     * @param int $id Campaign post ID.
     * @return WP_Post|null Campaign post or null if not found.
     */
    public static function get( int $id ): ?WP_Post
    {
        return Helper::getPostSafe( $id, 'notifal_campaign' );
    }

    /**
     * Get campaign options for select dropdowns.
     *
     * @since 2.0.0
     * @return array<int, string> Associative list of campaign_id => campaign_title.
     */
    public static function getCampaignOptions(): array
    {
        $campaigns = self::getActive();
        $options = [];

        foreach ( $campaigns as $campaign ) {
            if ( ! $campaign instanceof WP_Post ) {
                continue;
            }
            $options[ (int) $campaign->ID ] = (string) $campaign->post_title;
        }

        return $options;
    }
}

