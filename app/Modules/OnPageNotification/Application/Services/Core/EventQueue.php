<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Core;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Shared\Utils\Helper;
use Notifal\Modules\OnPageNotification\Application\Services\Analytics\TrackingDataValidator;

defined('ABSPATH') || exit;

/**
 * Class EventQueue
 *
 * Handles lightweight event queueing for improved performance.
 * Events are stored in a queue and processed in background.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services\Core
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class EventQueue
{
    /**
     * Queue a notification event for background processing.
     *
     * @param array $eventData Event data
     * @return array Result of queue operation
     * @since 2.0.0
     */
    public function queueEvent(array $eventData): array
    {
        /**
         * Fires before queuing OnPage notification event.
         *
         * @since 2.0.0
         * @param array $eventData Event data
         */
        do_action(ActionHooks::ONPAGE_EVENT_BEFORE_QUEUE, $eventData);

        $validator = notifal_app(TrackingDataValidator::class);
        $additionalFields = [
            'device_type' => 'desktop',
            'campaign_id' => 0,
            'timezone' => '',
            'screen_resolution' => '',
            'viewport_size' => '',
        ];
        $validatedData = $validator->validate($eventData, $additionalFields, 'mysql');
        
        if (!$validatedData['valid']) {
            return [
                'success' => false,
                'message' => $validatedData['message'],
            ];
        }

        $filteredData = apply_filters(
            FilterHooks::ONPAGE_EVENT_QUEUE_DATA,
            $validatedData['data']
        );

        $queueId = $this->storeInQueue($filteredData);

        if ($queueId === false) {
            return [
                'success' => false,
                'message' => __('Failed to queue event', 'notifal'),
            ];
        }

        /**
         * Fires after queuing OnPage notification event.
         *
         * @since 2.0.0
         * @param int $queueId Queue ID
         * @param array $filteredData Filtered event data
         */
        do_action(ActionHooks::ONPAGE_EVENT_AFTER_QUEUE, $queueId, $filteredData);

        return [
            'success' => true,
            'message' => __('Event queued successfully', 'notifal'),
            'queue_id' => $queueId,
        ];
    }

    /**
     * Get unprocessed events from the queue.
     *
     * @param int $limit Maximum number of events to retrieve
     * @return array Array of unprocessed events
     * @since 2.0.0
     */
    public function getUnprocessedEvents(int $limit = 100): array
    {
        global $wpdb;
        
        $table = $this->getQueueTableName();
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table 
            WHERE processed = 0 
            ORDER BY created_at ASC 
            LIMIT %d",
            $limit
        );
        
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    /**
     * Mark events as processed.
     *
     * @param array $eventIds Array of event IDs to mark as processed
     * @return bool True on success, false on failure
     * @since 2.0.0
     */
    public function markEventsAsProcessed(array $eventIds): bool
    {
        if (empty($eventIds)) {
            return true;
        }

        global $wpdb;
        
        $table = $this->getQueueTableName();
        $placeholders = implode(',', array_fill(0, count($eventIds), '%d'));
        
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE $table SET processed = 1 WHERE id IN ($placeholders)",
            $eventIds
        ));
        
        return $result !== false;
    }

    /**
     * Clean up old processed events.
     *
     * @param int $daysOld Number of days old to consider for cleanup
     * @return int Number of events cleaned up
     * @since 2.0.0
     */
    public function cleanupOldEvents(int $daysOld = 7): int
    {
        global $wpdb;
        
        $table = $this->getQueueTableName();
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE processed = 1 AND created_at < %s",
            $cutoffDate
        ));
        
        return $result !== false ? $result : 0;
    }

    /**
     * Get queue statistics.
     *
     * @return array Queue statistics
     * @since 2.0.0
     */
    public function getQueueStats(): array
    {
        global $wpdb;
        
        $table = $this->getQueueTableName();
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_events,
                SUM(CASE WHEN processed = 0 THEN 1 ELSE 0 END) as pending_events,
                SUM(CASE WHEN processed = 1 THEN 1 ELSE 0 END) as processed_events,
                MIN(created_at) as oldest_event,
                MAX(created_at) as newest_event
            FROM $table",
            ARRAY_A
        );
        
        return $stats ?: [
            'total_events' => 0,
            'pending_events' => 0,
            'processed_events' => 0,
            'oldest_event' => null,
            'newest_event' => null,
        ];
    }

    /**
     * Store event in queue table.
     *
     * @param array $eventData Validated event data
     * @return int|false Queue ID or false on failure
     * @since 2.0.0
     */
    private function storeInQueue(array $eventData)
    {
        global $wpdb;
        
        $table = $this->getQueueTableName();

        // Normalize queue row so optional button metadata columns are always present.
        $queueRow = [
            'notification_id'   => (int) ($eventData['notification_id'] ?? 0),
            'event_type'        => (string) ($eventData['event_type'] ?? ''),
            'user_id'           => (int) ($eventData['user_id'] ?? 0),
            'session_id'        => (string) ($eventData['session_id'] ?? ''),
            'timestamp'         => (string) ($eventData['timestamp'] ?? current_time('mysql')),
            'user_agent'        => (string) ($eventData['user_agent'] ?? ''),
            'referrer'          => (string) ($eventData['referrer'] ?? ''),
            'page_url'          => (string) ($eventData['page_url'] ?? ''),
            'ip_address'        => (string) ($eventData['ip_address'] ?? ''),
            'device_type'       => (string) ($eventData['device_type'] ?? 'desktop'),
            'campaign_id'       => (int) ($eventData['campaign_id'] ?? 0),
            'country_code'      => (string) ($eventData['country_code'] ?? ''),
            'city'              => (string) ($eventData['city'] ?? ''),
            'timezone'          => (string) ($eventData['timezone'] ?? ''),
            'screen_resolution' => (string) ($eventData['screen_resolution'] ?? ''),
            'viewport_size'     => (string) ($eventData['viewport_size'] ?? ''),
            'button_id'         => (string) ($eventData['button_id'] ?? ''),
            'button_action'     => (string) ($eventData['button_action'] ?? ''),
            'button_text'       => (string) ($eventData['button_text'] ?? ''),
        ];
        
        $result = $wpdb->insert(
            $table,
            $queueRow,
            [
                '%d',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );
        
        return $result !== false ? $wpdb->insert_id : false;
    }

    /**
     * Get queue table name.
     *
     * @return string Table name
     * @since 2.0.0
     */
    private function getQueueTableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'notifal_onpage_event_queue';
    }


}
