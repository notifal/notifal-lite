<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Core;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Traits\NotificationDataTrait;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\DisplayRulesService;
use Notifal\Shared\Utils\Helper;

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
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->displayRulesService = notifal_app(DisplayRulesService::class);
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
        // Verify notification is enabled
        $enabledMeta = get_post_meta($notification->ID, '_notifal_notif_enabled', true);
        if ($enabledMeta !== '1') {
            return false;
        }

        // Verify notification is published
        if ($notification->post_status !== 'publish') {
            return false;
        }

        // Get complete notification data from save service
        $notificationData = $this->getNotificationData($notification);
        if (empty($notificationData) || !($notificationData['notif_enabled'] ?? false)) {
            return false;
        }

        // Validate display rules match current context
        if (!$this->checkDisplayRules($notification, $context)) {
            return false;
        }

        // Check user-specific eligibility criteria
        if (!$this->checkUserEligibility($notification, $context)) {
            return false;
        }

        // Verify frequency caps are not exceeded
        if (!$this->checkFrequencyCaps($notification, $context)) {
            return false;
        }

        // Check scheduling constraints
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

        if (empty($displayRules)) {
            return true; // No rules means show everywhere
        }

        $currentPostId = $context['page_id'] ?? null;

        return $this->displayRulesService->shouldDisplay($displayRules, $combinationLogic, $currentPostId, $context);
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

        // Check if user has seen this notification too many times
        if ($this->hasUserReachedFrequencyCap($notification->ID, $userId)) {
            return false;
        }

        // Apply user eligibility filter
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

        // Check daily cap (only if Pro is active for impression tracking)
        $dailyCap = $behaviorSettings['frequency_cap_daily'] ?? 0;
        if ($dailyCap > 0 && function_exists('is_notifal_pro_active') && is_notifal_pro_active()) {
            $proStatsService = \notifal_pro_app(\NotifalPro\Modules\OnPageNotification\Application\Services\Analytics\StatsService::class);
            $dailyCount = $proStatsService->getDailyImpressionCount($notification->ID);
            if ($dailyCount >= $dailyCap) {
                return false;
            }
        }

        // Check total cap (only if Pro is active for impression tracking)
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
        $timingSettings = get_post_meta($notification->ID, '_notifal_timing_settings', true) ?: [];

        $now = current_time('timestamp');

        // Check start date
        if (!empty($timingSettings['start_date'])) {
            $startDate = strtotime($timingSettings['start_date']);
            if ($startDate && $now < $startDate) {
                return false;
            }
        }

        // Check end date
        if (!empty($timingSettings['end_date'])) {
            $endDate = strtotime($timingSettings['end_date']);
            if ($endDate && $now > $endDate) {
                return false;
            }
        }

        return true;
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
            return false; // Anonymous users don't have frequency caps
        }

        $behaviorSettings = get_post_meta($notificationId, '_notifal_behavior_settings', true) ?: [];

        // Check user-specific daily cap (only if Pro is active for impression tracking)
        $userDailyCap = $behaviorSettings['user_frequency_cap_daily'] ?? 0;
        if ($userDailyCap > 0 && function_exists('is_notifal_pro_active') && is_notifal_pro_active()) {
            $proStatsService = \notifal_pro_app(\NotifalPro\Modules\OnPageNotification\Application\Services\Analytics\StatsService::class);
            $userDailyCount = $proStatsService->getUserDailyImpressionCount($notificationId, $userId);
            if ($userDailyCount >= $userDailyCap) {
                return true;
            }
        }

        // Check user-specific total cap (only if Pro is active for impression tracking)
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
