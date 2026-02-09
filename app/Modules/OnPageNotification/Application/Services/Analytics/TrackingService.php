<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Analytics;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\OnPageNotification\Application\Services\Core\EventQueue;
use Notifal\Modules\OnPageNotification\Application\Services\Analytics\TrackingDataValidator;
use Notifal\Modules\OnPageNotification\Application\Services\Core\GeolocationService;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Repositories\DatabaseRepository;

defined('ABSPATH') || exit;

/**
 * Class TrackingService
 *
 * Handles tracking of OnPage notification events for analytics
 * and frequency capping purposes. Manages event queuing,
 * validation, and storage with performance optimizations.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services\Analytics
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class TrackingService
{
    /**
     * @var EventQueue
     */
    private $eventQueue;

    /**
     * @var TrackingDataValidator
     */
    private $validator;

    /**
     * @var GeolocationService
     */
    private $geolocationService;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->eventQueue = notifal_app(EventQueue::class);
        $this->validator = notifal_app(TrackingDataValidator::class);
        $this->geolocationService = notifal_app(GeolocationService::class);
    }

    /**
     * Track a notification event.
     *
     * @param array $trackingData Tracking data from frontend
     * @return array Tracking result
     * @since 2.0.0
     */
    public function trackEvent(array $trackingData): array
    {
        /**
         * Fires before tracking OnPage notification event.
         *
         * @since 2.0.0
         * @param array $trackingData Tracking data from frontend
         */
        do_action(ActionHooks::ONPAGE_TRACKING_BEFORE_PROCESS, $trackingData);

        // Validate and sanitize tracking data
        $validationResult = $this->validator->validate($trackingData);

        if (!$validationResult['valid']) {
            return [
                'success' => false,
                'message' => $validationResult['message'],
            ];
        }

        $validatedData = $validationResult['data'];

        // Apply tracking data filter
        $filteredData = apply_filters(
            FilterHooks::ONPAGE_TRACKING_DATA,
            $validatedData
        );

        // Process the tracking event
        $result = $this->processTrackingEvent($filteredData);

        /**
         * Fires after tracking OnPage notification event.
         *
         * @since 2.0.0
         * @param array $filteredData Filtered tracking data
         * @param array $result Tracking result
         */
        do_action(ActionHooks::ONPAGE_TRACKING_AFTER_PROCESS, $filteredData, $result);

        return $result;
    }


    /**
     * Process the tracking event.
     *
     * @param array $trackingData Validated tracking data
     * @return array Processing result
     * @since 2.0.0
     */
    private function processTrackingEvent(array $trackingData): array
    {
        try {
            // Check if queue processing is enabled
            $useQueue = apply_filters(
                FilterHooks::ONPAGE_TRACKING_USE_QUEUE,
                get_option('notifal_onpage_use_queue', true)
            );

            if ($useQueue) {
                // Use queue system for better performance
                return $this->processEventViaQueue($trackingData);
            } else {
                // Use legacy immediate processing
                return $this->processEventImmediately($trackingData);
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('Error processing tracking event', 'notifal'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process event via queue system (recommended for performance).
     *
     * @param array $trackingData Validated tracking data
     * @return array Processing result
     * @since 2.0.0
     */
    private function processEventViaQueue(array $trackingData): array
    {
        try {
            // Queue the event for background processing
            $result = $this->eventQueue->queueEvent($trackingData);
            
            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => __('Event queued for processing', 'notifal'),
                    'event_type' => $trackingData['event_type'],
                    'queue_id' => $result['queue_id'] ?? null,
                ];
            } else {
                // Fallback to immediate processing if queue fails
                return $this->processEventImmediately($trackingData);
            }

        } catch (\Exception $e) {
            return $this->processEventImmediately($trackingData);
        }
    }

    /**
     * Process event immediately (legacy behavior).
     *
     * @param array $trackingData Validated tracking data
     * @return array Processing result
     * @since 2.0.0
     */
    private function processEventImmediately(array $trackingData): array
    {
        try {
            $eventType = $trackingData['event_type'];
            $notificationId = $trackingData['notification_id'];

            switch ($eventType) {
                case 'impression':
                    $result = $this->trackImpression($trackingData);
                    break;
                    
                case 'click':
                    $result = $this->trackClick($trackingData);
                    break;
                    
                case 'close':
                    $result = $this->trackClose($trackingData);
                    break;
                    
                case 'dismiss':
                    $result = $this->trackDismiss($trackingData);
                    break;
                    
                default:
                    $result = [
                        'success' => false,
                        'message' => __('Unknown event type', 'notifal'),
                    ];
            }

            // Store tracking data in database immediately
            $this->storeTrackingData($trackingData);

            return $result;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('Error processing tracking event', 'notifal'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Track notification impression.
     *
     * @param array $trackingData Tracking data
     * @return array Tracking result
     * @since 2.0.0
     */
    private function trackImpression(array $trackingData): array
    {
        $notificationId = $trackingData['notification_id'];

        // Track analytics only if Pro is active
        if (PluginDetector::isNotifalProActive()) {
            // Get Pro StatsService
            $proStatsService = notifal_pro_app(\NotifalPro\Modules\OnPageNotification\Application\Services\Analytics\StatsService::class);

            // Increment impression count
            $proStatsService->incrementImpressionCount($notificationId);

            // Track user-specific impression if logged in
            if ($trackingData['user_id'] > 0) {
                $proStatsService->incrementUserImpressionCount($notificationId, $trackingData['user_id']);
            }
        }

        return [
            'success' => true,
            'message' => __('Impression tracked successfully', 'notifal'),
            'event_type' => 'impression',
        ];
    }

    /**
     * Track notification click.
     *
     * @param array $trackingData Tracking data
     * @return array Tracking result
     * @since 2.0.0
     */
    private function trackClick(array $trackingData): array
    {
        $notificationId = $trackingData['notification_id'];

        // Track analytics only if Pro is active
        if (PluginDetector::isNotifalProActive()) {
            // Get Pro StatsService
            $proStatsService = notifal_pro_app(\NotifalPro\Modules\OnPageNotification\Application\Services\Analytics\StatsService::class);

            // Increment click count
            $proStatsService->incrementClickCount($notificationId);

            // Track user-specific click if logged in
            if ($trackingData['user_id'] > 0) {
                $proStatsService->incrementUserClickCount($notificationId, $trackingData['user_id']);
            }
        }

        return [
            'success' => true,
            'message' => __('Click tracked successfully', 'notifal'),
            'event_type' => 'click',
        ];
    }

    /**
     * Track notification close.
     *
     * @param array $trackingData Tracking data
     * @return array Tracking result
     * @since 2.0.0
     */
    private function trackClose(array $trackingData): array
    {
        $notificationId = $trackingData['notification_id'];

        // Track analytics only if Pro is active
        if (PluginDetector::isNotifalProActive()) {
            // Get Pro StatsService
            $proStatsService = notifal_pro_app(\NotifalPro\Modules\OnPageNotification\Application\Services\Analytics\StatsService::class);

            // Increment close count
            $proStatsService->incrementCloseCount($notificationId);

            // Track user-specific close if logged in
            if ($trackingData['user_id'] > 0) {
                $proStatsService->incrementUserCloseCount($notificationId, $trackingData['user_id']);
            }
        }

        return [
            'success' => true,
            'message' => __('Close tracked successfully', 'notifal'),
            'event_type' => 'close',
        ];
    }

    /**
     * Track notification dismiss.
     *
     * @param array $trackingData Tracking data
     * @return array Tracking result
     * @since 2.0.0
     */
    private function trackDismiss(array $trackingData): array
    {
        $notificationId = $trackingData['notification_id'];

        // Track analytics only if Pro is active
        if (PluginDetector::isNotifalProActive()) {
            // Get Pro StatsService
            $proStatsService = notifal_pro_app(\NotifalPro\Modules\OnPageNotification\Application\Services\Analytics\StatsService::class);

            // Increment dismiss count
            $proStatsService->incrementDismissCount($notificationId);

            // Track user-specific dismiss if logged in
            if ($trackingData['user_id'] > 0) {
                $proStatsService->incrementUserDismissCount($notificationId, $trackingData['user_id']);
            }
        }

        return [
            'success' => true,
            'message' => __('Dismiss tracked successfully', 'notifal'),
            'event_type' => 'dismiss',
        ];
    }

    /**
     * Store tracking data in database.
     *
     * @param array $trackingData Tracking data
     * @return void
     * @since 2.0.0
     */
    private function storeTrackingData(array $trackingData): void
    {
        $databaseRepository = notifal_app(DatabaseRepository::class);
        $databaseRepository->storeTrackingEvent($trackingData);
    }



} 
