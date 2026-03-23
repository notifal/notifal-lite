<?php

namespace Notifal\Modules\Campaign\Presentation\Admin\Presenters;

use Notifal\Modules\OnPageNotification\Application\Services\Utility\UrlService as OnPageUrlService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Builds presentation data for the campaign on-page notification picker (admin edit screen).
 *
 * Keeps markup views free of mapping and URL rules.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\Campaign\Presentation\Admin\Presenters
 */
class CampaignOnpagePickerPresenter
{
    /**
     * Map assigned notification posts to JSON-safe picker items.
     *
     * @since 2.0.0
     * @param array<int, \WP_Post> $assigned_notifications Assigned on-page notification posts.
     * @param OnPageUrlService     $onpage_url_service     URL helper for edit links.
     * @return array<int, array<string, int|string>> List of items with id, title, edit_url.
     */
    public static function buildInitialItems( array $assigned_notifications, OnPageUrlService $onpage_url_service ): array
    {
        $items = [];

        foreach ( $assigned_notifications as $notification ) {
            if ( ! $notification instanceof \WP_Post ) {
                continue;
            }

            $id = (int) $notification->ID;
            if ( $id <= 0 ) {
                continue;
            }

            $items[] = [
                'id'       => $id,
                'title'    => (string) get_the_title( $notification ),
                'edit_url' => $onpage_url_service->getEditNotificationUrl( $id ),
            ];
        }

        return $items;
    }
}
