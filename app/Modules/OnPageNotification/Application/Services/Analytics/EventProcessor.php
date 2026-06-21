<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Analytics;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Repositories\DatabaseRepository;
use Notifal\Modules\OnPageNotification\Application\Services\Core\EventQueue;
use Notifal\Modules\OnPageNotification\Helpers\StatsHelper;

defined('ABSPATH') || exit;

/**
 * Class EventProcessor
 *
 * Processes queued events in background to maintain analytics
 * while optimizing frontend performance.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services\Analytics
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class EventProcessor
{
    /**
     * @var EventQueue
     */
    private $eventQueue;

    /**
     * @var DatabaseRepository
     */
    private $databaseRepository;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->eventQueue = notifal_app(EventQueue::class);
        $this->databaseRepository = notifal_app(DatabaseRepository::class);
    }

    /**
     * Process queued events.
     *
     * @param int $batchSize Number of events to process in one batch
     * @return array Processing result
     * @since 2.0.0
     */
    public function processQueuedEvents(int $batchSize = 50): array
    {
        /**
         * Fires before processing queued events.
         *
         * @since 2.0.0
         * @param int $batchSize Batch size
         */
        do_action(ActionHooks::ONPAGE_EVENT_PROCESSING_BEFORE, $batchSize);

        $startTime = microtime(true);
        $processedCount = 0;
        $errorCount = 0;
        $processedIds = [];

        try {
            $events = $this->eventQueue->getUnprocessedEvents($batchSize);

            if (empty($events)) {
                return [
                    'success' => true,
                    'message' => __('No events to process', 'notifal'),
                    'processed_count' => 0,
                    'error_count' => 0,
                    'processing_time' => 0,
                ];
            }

            foreach ($events as $event) {
                try {
                    $result = $this->processEvent($event);
                    
                    if ($result['success']) {
                        $processedIds[] = (int) $event['id'];
                        $processedCount++;
                    } else {
                        $errorCount++;
                    }

                } catch (\Exception $e) {
                    $errorCount++;
                }
            }

            if (!empty($processedIds)) {
                $this->eventQueue->markEventsAsProcessed($processedIds);
            }

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            /**
             * Fires after processing queued events.
             *
             * @since 2.0.0
             * @param int $processedCount Number of events processed
             * @param int $errorCount Number of errors encountered
             * @param float $processingTime Processing time in milliseconds
             */
            do_action(ActionHooks::ONPAGE_EVENT_PROCESSING_AFTER, $processedCount, $errorCount, $processingTime);

            return [
                'success' => true,
                'message' => sprintf(
                    __('Processed %d events with %d errors in %sms', 'notifal'),
                    $processedCount,
                    $errorCount,
                    $processingTime
                ),
                'processed_count' => $processedCount,
                'error_count' => $errorCount,
                'processing_time' => $processingTime,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('Error processing events', 'notifal'),
                'error' => $e->getMessage(),
                'processed_count' => $processedCount,
                'error_count' => $errorCount,
            ];
        }
    }

    /**
     * Process a single event from the queue.
     *
     * @param array $event Event data from queue
     * @return array Processing result
     * @since 2.0.0
     */
    private function processEvent(array $event): array
    {
        try {
            $trackingData = $this->convertQueueEventToTrackingData($event);

            $trackingId = $this->databaseRepository->storeTrackingEvent($trackingData);

            if ($trackingId === false) {
                return [
                    'success' => false,
                    'message' => __('Failed to store tracking event', 'notifal'),
                ];
            }

            $this->updateStatistics($trackingData);

            return [
                'success' => true,
                'message' => __('Event processed successfully', 'notifal'),
                'tracking_id' => $trackingId,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('Error processing event', 'notifal'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Convert queue event data to tracking data format.
     *
     * @param array $event Queue event data
     * @return array Tracking data
     * @since 2.0.0
     */
    private function convertQueueEventToTrackingData(array $event): array
    {
        $timestamp = isset($event['timestamp']) ? strtotime($event['timestamp']) : false;
        if ($timestamp === false) {
            $timestamp = current_time('timestamp');
        }

        return [
            'notification_id' => (int) $event['notification_id'],
            'event_type' => $event['event_type'],
            'user_id' => (int) $event['user_id'],
            'timestamp' => $timestamp,
            'user_agent' => $event['user_agent'],
            'referrer' => $event['referrer'],
            'page_url' => $event['page_url'],
            'ip_address' => $event['ip_address'],
            'session_id' => $event['session_id'],
            'device_type' => $event['device_type'],
            'campaign_id' => isset($event['campaign_id']) ? (int) $event['campaign_id'] : 0,
            'country_code' => $event['country_code'],
            'city' => $event['city'],
            'button_id' => isset($event['button_id']) ? (string) $event['button_id'] : '',
            'button_action' => isset($event['button_action']) ? (string) $event['button_action'] : '',
            'button_text' => isset($event['button_text']) ? (string) $event['button_text'] : '',
        ];
    }

    /**
     * Update statistics for the processed event.
     *
     * @param array $trackingData Tracking data
     * @return void
     * @since 2.0.0
     */
    private function updateStatistics(array $trackingData): void
    {
        $notificationId = $trackingData['notification_id'];
        $eventType = $trackingData['event_type'];
        $userId = $trackingData['user_id'];

        try {
            $timestamp = $trackingData['timestamp'];
            if (!is_numeric($timestamp) || $timestamp <= 0) {
                $timestamp = current_time('timestamp');
            }

            $date = date('Y-m-d', $timestamp);

            if (!StatsHelper::isValidDate($date)) {
                $date = current_time('Y-m-d');
            }

            $this->databaseRepository->updateDailyStats($notificationId, $eventType, $date);

            if ($userId > 0) {
                $this->databaseRepository->updateUserStats($notificationId, $userId, $eventType);
            }

        } catch (\Exception $e) {
            // Continue processing other events even if statistics update fails
        }
    }


    /**
     * Force process all pending events (for manual triggers).
     *
     * @return array Processing result
     * @since 2.0.0
     */
    public function forceProcessAllEvents(): array
    {
        $totalProcessed = 0;
        $totalErrors = 0;
        $iterations = 0;
        $maxIterations = 20; // Prevent infinite loop
        
        do {
            $result = $this->processQueuedEvents(100);
            $totalProcessed += $result['processed_count'] ?? 0;
            $totalErrors += $result['error_count'] ?? 0;
            $iterations++;
            
        } while (($result['processed_count'] ?? 0) > 0 && $iterations < $maxIterations);
        
        return [
            'success' => true,
            'message' => sprintf(
                __('Force processed %d events with %d errors in %d iterations', 'notifal'),
                $totalProcessed,
                $totalErrors,
                $iterations
            ),
            'total_processed' => $totalProcessed,
            'total_errors' => $totalErrors,
            'iterations' => $iterations,
        ];
    }
}
