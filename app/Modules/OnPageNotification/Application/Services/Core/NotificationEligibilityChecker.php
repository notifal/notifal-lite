<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Core;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Campaign\Application\Services\CampaignSettingsService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\DisplayRulesDataNormalizer;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\DisplayRulesService;
use Notifal\Modules\OnPageNotification\Application\Support\ScheduleDateTimeHelper;
use Notifal\Modules\OnPageNotification\Application\Traits\NotificationDataTrait;
defined('ABSPATH') || exit;

/**
 * Class NotificationEligibilityChecker
 *
 * Handles individual eligibility checks for OnPage notifications.
 * Validates notification status, display rules, user eligibility,
 * frequency caps, and scheduling constraints.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class NotificationEligibilityChecker
{
    use NotificationDataTrait;

    /**
     * @var DisplayRulesService
     */
    private $displayRulesService;

    /**
     * @var CampaignSettingsService
     */
    private $campaignSettingsService;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->displayRulesService = notifal_app(DisplayRulesService::class);
        $this->campaignSettingsService = notifal_app(CampaignSettingsService::class);
    }

    /**
     * Check if a notification is eligible for the current context.
     *
     * Validates notification status, display rules, user eligibility,
     * frequency caps, and scheduling constraints.
     *
     * @param \WP_Post $notification Notification post
     * @param array $context Current page context
     * @return bool True if eligible, false otherwise
     * @since 2.0.0
     */
    public function isEligible(\WP_Post $notification, array $context): bool
    {
        $enabledMeta = get_post_meta($notification->ID, '_notifal_notif_enabled', true);
        if ($enabledMeta !== '1') {
            return false;
        }

        if ($notification->post_status !== 'publish') {
            return false;
        }

        $notificationData = $this->getNotificationData($notification);
        if (empty($notificationData) || !($notificationData['notif_enabled'] ?? false)) {
            return false;
        }

        if (!$this->checkDisplayRules($notification, $context)) {
            return false;
        }

        if (!$this->checkUserEligibility($notification, $context)) {
            return false;
        }

        if (!$this->checkFrequencyCaps($notification, $context)) {
            return false;
        }

        if (!$this->checkSchedule($notification)) {
            return false;
        }

        return true;
    }

    /**
     * Check display rules for a notification.
     *
     * @param \WP_Post $notification Notification post
     * @param array $context Current page context
     * @return bool True if display rules pass, false otherwise
     * @since 2.0.0
     */
    private function checkDisplayRules(\WP_Post $notification, array $context): bool
    {
        $displayRules = get_post_meta($notification->ID, '_notifal_display_rules_data', true);
        $combinationLogic = get_post_meta($notification->ID, '_notifal_rule_combination_logic', true) ?: 'OR';
        $visibilityMode = get_post_meta($notification->ID, '_notifal_display_rules_visibility_mode', true) ?: 'show_if';

        if (!DisplayRulesDataNormalizer::hasActiveRules($displayRules)) {
            return true;
        }

        $currentPostId = $context['page_id'] ?? null;

        return $this->displayRulesService->shouldDisplay(
            $displayRules,
            $combinationLogic,
            $currentPostId,
            $context,
            $visibilityMode
        );
    }

    /**
     * Check user eligibility for a notification.
     *
     * @param \WP_Post $notification Notification post
     * @param array $context Current page context
     * @return bool True if user is eligible, false otherwise
     * @since 2.0.0
     */
    private function checkUserEligibility(\WP_Post $notification, array $context): bool
    {
        $userId = $context['user_id'] ?? 0;
        $userContext = [
            'user_id' => $userId,
            'is_logged_in' => $userId > 0,
            'user_roles' => $userId > 0 ? wp_get_current_user()->roles : [],
        ];

        if ($this->hasUserReachedFrequencyCap($notification->ID, $userId)) {
            return false;
        }

        $isEligible = apply_filters(
            FilterHooks::ONPAGE_USER_ELIGIBILITY,
            true,
            $this->getNotificationData($notification),
            $userContext
        );

        return $isEligible;
    }

    /**
     * Check frequency caps for a notification.
     *
     * @param \WP_Post $notification Notification post
     * @param array $context Current page context
     * @return bool True if frequency caps allow, false otherwise
     * @since 2.0.0
     */
    private function checkFrequencyCaps(\WP_Post $notification, array $context): bool
    {
        $behaviorSettings = get_post_meta($notification->ID, '_notifal_behavior_settings', true) ?: [];

        $dailyCap = $behaviorSettings['frequency_cap_daily'] ?? 0;
        if ($dailyCap > 0 && function_exists('is_notifal_pro_active') && is_notifal_pro_active()) {
            $proStatsService = \notifal_pro_app(\NotifalPro\Modules\OnPageNotification\Application\Services\Analytics\StatsService::class);
            $dailyCount = $proStatsService->getDailyImpressionCount($notification->ID);
            if ($dailyCount >= $dailyCap) {
                return false;
            }
        }

        $totalCap = $behaviorSettings['frequency_cap_total'] ?? 0;
        if ($totalCap > 0 && function_exists('is_notifal_pro_active') && is_notifal_pro_active()) {
            $proStatsService = \notifal_pro_app(\NotifalPro\Modules\OnPageNotification\Application\Services\Analytics\StatsService::class);
            $totalCount = $proStatsService->getTotalImpressionCount($notification->ID);
            if ($totalCount >= $totalCap) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check schedule for a notification.
     *
     * @param \WP_Post $notification Notification post
     * @return bool True if within schedule, false otherwise
     * @since 2.0.0
     */
    private function checkSchedule(\WP_Post $notification): bool
    {
        $campaignId = get_post_meta($notification->ID, '_notifal_campaign_id', true);
        $startDate = '';
        $endDate = '';

        if (!empty($campaignId)) {
            $within = $this->campaignSettingsService->isWithinSchedule((int) $campaignId);

            return (bool) apply_filters(
                FilterHooks::CAMPAIGN_SCHEDULE_CHECK,
                $within,
                (int) $campaignId,
                $notification
            );
        }

        $timingSettings = get_post_meta($notification->ID, '_notifal_timing_settings', true) ?: [];
        $scheduleEnabled = (bool) ($timingSettings['schedule_enabled'] ?? false);

        if (!$scheduleEnabled) {
            return true;
        }

        $startDate = (string) ($timingSettings['start_date'] ?? '');
        $endDate = (string) ($timingSettings['end_date'] ?? '');

        return ScheduleDateTimeHelper::isNowWithinBoundaries($startDate, $endDate);
    }

    /**
     * Check if user has reached frequency cap for a notification.
     *
     * @param int $notificationId Notification ID
     * @param int $userId User ID
     * @return bool True if user has reached cap, false otherwise
     * @since 2.0.0
     */
    private function hasUserReachedFrequencyCap(int $notificationId, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $behaviorSettings = get_post_meta($notificationId, '_notifal_behavior_settings', true) ?: [];

        $userDailyCap = $behaviorSettings['user_frequency_cap_daily'] ?? 0;
        if ($userDailyCap > 0 && function_exists('is_notifal_pro_active') && is_notifal_pro_active()) {
            $proStatsService = \notifal_pro_app(\NotifalPro\Modules\OnPageNotification\Application\Services\Analytics\StatsService::class);
            $userDailyCount = $proStatsService->getUserDailyImpressionCount($notificationId, $userId);
            if ($userDailyCount >= $userDailyCap) {
                return true;
            }
        }

        $userTotalCap = $behaviorSettings['user_frequency_cap_total'] ?? 0;
        if ($userTotalCap > 0 && function_exists('is_notifal_pro_active') && is_notifal_pro_active()) {
            $proStatsService = \notifal_pro_app(\NotifalPro\Modules\OnPageNotification\Application\Services\Analytics\StatsService::class);
            $userTotalCount = $proStatsService->getUserTotalImpressionCount($notificationId, $userId);
            if ($userTotalCount >= $userTotalCap) {
                return true;
            }
        }

        return false;
    }
}
