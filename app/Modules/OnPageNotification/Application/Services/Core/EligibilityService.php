<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Core;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Repositories\NotificationQuery;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class EligibilityService
 *
 * Determines which OnPage notifications are eligible to be shown
 * based on display rules, user context, and other factors.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class EligibilityService
{
    /**
     * @var NotificationEligibilityChecker
     */
    private $eligibilityChecker;

    /**
     * @var NotificationDataPreparer
     */
    private $dataPreparer;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->eligibilityChecker = notifal_app(NotificationEligibilityChecker::class);
        $this->dataPreparer = notifal_app(NotificationDataPreparer::class);
    }

    /**
     * Get eligible notifications for the current context.
     *
     * Filters active notifications based on display rules, user eligibility,
     * frequency caps, and schedule constraints.
     *
     * @param array $context Current page context
     * @return array Array of eligible notifications
     * @since 2.0.0
     */
    public function getEligibleNotifications(array $context): array
    {
        /**
         * Fires before processing OnPage notification eligibility request.
         *
         * @since 2.0.0
         * @param array $context Current page context
         */
        do_action(ActionHooks::ONPAGE_ELIGIBILITY_BEFORE_PROCESS, $context);

        // Get all active notifications
        $notifications = $this->getActiveNotifications();

        // Handle forced fresh content for specific notification (retrigger case)
        $forceFreshNotificationId = $context['force_fresh_notification_id'] ?? null;
        if ($forceFreshNotificationId && $context['force_fresh_content'] ?? false) {
            // Find the specific notification to refresh
            $targetNotification = null;
            foreach ($notifications as $notification) {
                if ($notification->ID == $forceFreshNotificationId) {
                    $targetNotification = $notification;
                    break;
                }
            }

            if ($targetNotification) {
                // Prepare the specific notification with fresh content
                $preparedNotification = $this->dataPreparer->prepareForFrontend($targetNotification, $context);
                if ($preparedNotification !== null) {
                    // Return only this notification with fresh content
                    $eligibleNotifications = [$preparedNotification];

                    // Apply final filters for extensibility
                    $eligibleNotifications = apply_filters(
                        FilterHooks::ONPAGE_ELIGIBILITY_DATA,
                        $eligibleNotifications,
                        $context
                    );

                    /**
                     * Fires after processing OnPage notification eligibility request.
                     *
                     * @since 2.0.0
                     * @param array $eligibleNotifications Eligible notifications
                     * @param array $context Current page context
                     */
                    do_action(ActionHooks::ONPAGE_ELIGIBILITY_AFTER_PROCESS, $eligibleNotifications, $context);

                    return $eligibleNotifications;
                }
            }
        }

        // Enforce single notification limit for free users at runtime
        $notifications = $this->enforceActivationLimit($notifications);

        // Filter notifications by eligibility criteria
        $eligibleNotifications = [];

        foreach ($notifications as $notification) {
            if ($this->eligibilityChecker->isEligible($notification, $context)) {
                $preparedNotification = $this->dataPreparer->prepareForFrontend($notification, $context);

                // Only add notifications that have valid prepared data
                if ($preparedNotification !== null) {
                    $eligibleNotifications[] = $preparedNotification;
                }
            }
        }

        // Sort by priority score (higher priority first)
        $eligibleNotifications = $this->sortByPriority($eligibleNotifications);

        // Apply final filters for extensibility
        $eligibleNotifications = apply_filters(
            FilterHooks::ONPAGE_ELIGIBILITY_DATA,
            $eligibleNotifications,
            $context
        );

        /**
         * Fires after processing OnPage notification eligibility request.
         *
         * @since 2.0.0
         * @param array $eligibleNotifications Eligible notifications
         * @param array $context Current page context
         */
        do_action(ActionHooks::ONPAGE_ELIGIBILITY_AFTER_PROCESS, $eligibleNotifications, $context);

        return $eligibleNotifications;
    }

    /**
     * Get all active notifications.
     *
     * @return array Array of notification posts
     * @since 2.0.0
     */
    private function getActiveNotifications(): array
    {
        return NotificationQuery::getAll();
    }

    /**
     * Enforce activation limit for free users at runtime.
     *
     * @param array $notifications Array of notification posts
     * @return array Filtered array (single notification for free users)
     * @since 2.0.0
     */
    private function enforceActivationLimit(array $notifications): array
    {
        if (empty($notifications) || apply_filters('notifal_pro_multiple_notifications_allowed', false)) {
            return $notifications;
        }

        if (count($notifications) > 1) {
            // Return only the first notification (oldest by default from query)
            return [array_shift($notifications)];
        }

        return $notifications;
    }


    /**
     * Sort notifications by priority.
     *
     * @param array $notifications Array of notifications
     * @return array Sorted notifications
     * @since 2.0.0
     */
    private function sortByPriority(array $notifications): array
    {
        usort($notifications, function ($a, $b) {
            $priorityA = $a['priority'] ?? 0;
            $priorityB = $b['priority'] ?? 0;

            return $priorityB <=> $priorityA; // Higher priority first
        });

        return $notifications;
    }
} 
