<?php

namespace Notifal\Modules\Campaign\Infrastructure\WordPress\Repositories;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Read-only queries for on-page notifications shown in the campaign assignment picker.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\Campaign\Infrastructure\WordPress\Repositories
 */
class OnpageNotificationForCampaignPickerQuery
{
    /**
     * Search on-page notifications by keyword for admin AJAX picker.
     *
     * @since 2.0.0
     * @param string $search Raw search string from the request.
     * @param int    $limit  Maximum number of posts (capped internally).
     * @return array<int, array<string, int|string>> Rows with id and title.
     */
    public static function search( string $search, int $limit = 20 ): array
    {
        $search = sanitize_text_field( $search );
        $items = [];

        if ( strlen( $search ) >= 2 ) {
            $safe_limit = min( 50, max( 1, $limit ) );

            $query = new \WP_Query(
                [
                    'post_type'              => 'notifal_onpage_notif',
                    'post_status'            => 'any',
                    's'                      => $search,
                    'posts_per_page'         => $safe_limit,
                    'orderby'                => 'title',
                    'order'                  => 'ASC',
                    'no_found_rows'          => true,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                ]
            );

            if ( isset( $query->posts ) && is_array( $query->posts ) ) {
                foreach ( $query->posts as $post ) {
                    if ( ! $post instanceof \WP_Post ) {
                        continue;
                    }

                    $items[] = [
                        'id'    => (int) $post->ID,
                        'title' => (string) get_the_title( $post ),
                    ];
                }
            }
        }

        /**
         * Filters on-page notification search results for the campaign picker.
         *
         * @since 2.0.0
         * @param array<int, array<string, int|string>> $items  Result rows.
         * @param string                                $search Sanitized search string.
         */
        return apply_filters( FilterHooks::CAMPAIGN_ONPAGE_PICKER_SEARCH_RESULTS, $items, $search );
    }
}
