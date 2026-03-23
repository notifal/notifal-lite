<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Analytics;

use Notifal\Modules\Campaign\Application\Services\CampaignSettingsService;

defined('ABSPATH') || exit;

/**
 * Resolve campaign attribution for OnPage analytics events.
 *
 * @since 2.2.0
 * @author Hossein <hossein@notifal.com>
 */
class CampaignAttributionResolver
{
    /**
     * Resolve campaign ID for tracking/conversion attribution.
     *
     * Returns campaign ID only when:
     * - Notification is assigned to a campaign via `_notifal_campaign_id`
     * - Campaign is currently active and within schedule
     *
     * @param int $notificationId Notification post ID.
     * @return int Campaign ID or 0 when not attributable.
     * @since 2.2.0
     */
    public function resolveCampaignIdForNotification(int $notificationId): int
    {
        if ($notificationId <= 0) {
            return 0;
        }

        $campaignId = (int) get_post_meta($notificationId, '_notifal_campaign_id', true);
        if ($campaignId <= 0) {
            return 0;
        }

        if (!class_exists(CampaignSettingsService::class)) {
            return 0;
        }

        $campaignSettingsService = notifal_app(CampaignSettingsService::class);
        if (!$campaignSettingsService || !method_exists($campaignSettingsService, 'isWithinSchedule')) {
            return 0;
        }

        return $campaignSettingsService->isWithinSchedule($campaignId) ? $campaignId : 0;
    }
}

