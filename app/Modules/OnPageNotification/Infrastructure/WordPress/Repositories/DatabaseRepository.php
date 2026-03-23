<?php

namespace Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Repositories;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Database\MigrationService;

defined('ABSPATH') || exit;

/**
 * Class DatabaseRepository
 *
 * Handles all database operations for OnPage notifications.
 * Manages tracking, statistics, user preferences, and frequency capping.
 *
 * @package Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Repositories
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class DatabaseRepository
{
    /**
     * Get table names with prefix.
     *
     * @return array Array of table names
     * @since 2.0.0
     */
    public function getTableNames(): array
    {
        return MigrationService::getTableNames();
    }

    // =========================================================================
    // 📊 TRACKING OPERATIONS
    // =========================================================================

    /**
     * Store tracking event data.
     *
     * @param array $trackingData Tracking data
     * @return int|false Insert ID or false on failure
     * @since 2.0.0
     */
    public function storeTrackingEvent(array $trackingData)
    {
        global $wpdb;
        
        $tables = $this->getTableNames();
        $table = $tables['tracking'];
        
        // Fix timestamp - ensure we have a valid timestamp, fallback to current time
        $timestamp = $trackingData['timestamp'] ?? 0;
        if ($timestamp <= 0 || !is_numeric($timestamp)) {
            $timestamp = current_time('timestamp');
        }
        
        $result = $wpdb->insert(
            $table,
            [
                'notification_id' => $trackingData['notification_id'],
                'event_type' => $trackingData['event_type'],
                'user_id' => $trackingData['user_id'],
                'timestamp' => date('Y-m-d H:i:s', $timestamp),
                'user_agent' => $trackingData['user_agent'],
                'referrer' => $trackingData['referrer'],
                'page_url' => $trackingData['page_url'],
                'ip_address' => $trackingData['ip_address'],
                'session_id' => $trackingData['session_id'],
                'device_type' => $trackingData['device_type'] ?? 'desktop',
                'campaign_id' => isset($trackingData['campaign_id']) ? (int) $trackingData['campaign_id'] : 0,
                'country_code' => $trackingData['country_code'] ?? null,
                'city' => $trackingData['city'] ?? null,
            ],
            [
                '%d', // notification_id
                '%s', // event_type
                '%d', // user_id
                '%s', // timestamp
                '%s', // user_agent
                '%s', // referrer
                '%s', // page_url
                '%s', // ip_address
                '%s', // session_id
                '%s', // device_type
                '%d', // campaign_id
                '%s', // country_code
                '%s', // city
            ]
        );

        if ($result === false) {
            return false;
        }

        $insertId = $wpdb->insert_id;

        /**
         * Fires after storing a tracking event.
         *
         * @since 2.0.0
         * @param int $insertId Insert ID
         * @param array $trackingData Tracking data
         */
        do_action(ActionHooks::ONPAGE_TRACKING_EVENT_STORED, $insertId, $trackingData);

        return $insertId;
    }

    /**
     * Get tracking events for a notification.
     *
     * @param int $notificationId Notification ID
     * @param string $eventType Event type (optional)
     * @param int $limit Limit number of results
     * @param int $offset Offset for pagination
     * @return array Array of tracking events
     * @since 2.0.0
     */
    public function getTrackingEvents(int $notificationId, string $eventType = '', int $limit = 100, int $offset = 0): array
    {
        global $wpdb;
        
        $tables = $this->getTableNames();
        $table = $tables['tracking'];
        
        $where = ['notification_id = %d'];
        $args = [$notificationId];
        
        if (!empty($eventType)) {
            $where[] = 'event_type = %s';
            $args[] = $eventType;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table WHERE $whereClause ORDER BY timestamp DESC LIMIT %d OFFSET %d",
            array_merge($args, [$limit, $offset])
        );
        
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    // =========================================================================
    // 📈 STATISTICS OPERATIONS
    // =========================================================================

    /**
     * Update daily statistics for a notification.
     *
     * @param int $notificationId Notification ID
     * @param string $eventType Event type
     * @param string $date Date (Y-m-d format)
     * @return bool True on success, false on failure
     * @since 2.0.0
     */
    public function updateDailyStats(int $notificationId, string $eventType, string $date): bool
    {
        global $wpdb;
        
        $tables = $this->getTableNames();
        $table = $tables['daily_stats'];
        
        // Try to update existing record
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE $table SET 
                {$eventType}s = {$eventType}s + 1,
                updated_at = CURRENT_TIMESTAMP
            WHERE notification_id = %d AND date = %s",
            $notificationId,
            $date
        ));
        
        // If no rows were updated, insert new record
        if ($result === 0) {
            $result = $wpdb->insert(
                $table,
                [
                    'notification_id' => $notificationId,
                    'date' => $date,
                    $eventType . 's' => 1,
                ],
                [
                    '%d', // notification_id
                    '%s', // date
                    '%d', // event count
                ]
            );
        }
        
        return $result !== false;
    }

    /**
     * Get daily statistics for a notification.
     *
     * @param int $notificationId Notification ID
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array Array of daily statistics
     * @since 2.0.0
     */
    public function getDailyStats(int $notificationId, string $startDate, string $endDate): array
    {
        global $wpdb;
        
        $tables = $this->getTableNames();
        $table = $tables['daily_stats'];
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table 
            WHERE notification_id = %d 
            AND date BETWEEN %s AND %s 
            ORDER BY date ASC",
            $notificationId,
            $startDate,
            $endDate
        );
        
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    /**
     * Get total statistics for a notification.
     *
     * @param int $notificationId Notification ID
     * @return array Array of total statistics
     * @since 2.0.0
     */
    public function getTotalStats(int $notificationId): array
    {
        global $wpdb;
        
        $tables = $this->getTableNames();
        $dailyStatsTable = $tables['daily_stats'];
        
        // Get aggregated totals directly from daily stats
        $sql = $wpdb->prepare(
            "SELECT 
                COALESCE(SUM(impressions), 0) as total_impressions,
                COALESCE(SUM(clicks), 0) as total_clicks,
                COALESCE(SUM(closes), 0) as total_closes,
                COALESCE(SUM(dismisses), 0) as total_dismisses,
                COALESCE(SUM(conversions), 0) as total_conversions,
                COALESCE(SUM(revenue), 0.00) as total_revenue
            FROM $dailyStatsTable 
            WHERE notification_id = %d",
            $notificationId
        );
        
        $result = $wpdb->get_row($sql, ARRAY_A);
        
        // Return zeros if no daily stats found
        if (!$result) {
            return [
                'total_impressions' => 0,
                'total_clicks' => 0,
                'total_closes' => 0,
                'total_dismisses' => 0,
                'total_conversions' => 0,
                'total_revenue' => 0.00,
            ];
        }
        
        return $result;
    }


    // =========================================================================
    // 👤 USER STATISTICS OPERATIONS
    // =========================================================================

    /**
     * Update user statistics for a notification.
     *
     * @param int $notificationId Notification ID
     * @param int $userId User ID
     * @param string $eventType Event type
     * @return bool True on success, false on failure
     * @since 2.0.0
     */
    public function updateUserStats(int $notificationId, int $userId, string $eventType): bool
    {
        global $wpdb;
        
        $tables = $this->getTableNames();
        $table = $tables['user_stats'];
        
        $now = current_time('mysql');
        
        // Try to update existing record
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE $table SET 
                total_{$eventType}s = total_{$eventType}s + 1,
                last_seen = %s,
                updated_at = CURRENT_TIMESTAMP
            WHERE notification_id = %d AND user_id = %d",
            $now,
            $notificationId,
            $userId
        ));
        
        // If no rows were updated, insert new record
        if ($result === 0) {
            $result = $wpdb->insert(
                $table,
                [
                    'notification_id' => $notificationId,
                    'user_id' => $userId,
                    'total_' . $eventType . 's' => 1,
                    'first_seen' => $now,
                    'last_seen' => $now,
                ],
                [
                    '%d', // notification_id
                    '%d', // user_id
                    '%d', // event count
                    '%s', // first_seen
                    '%s', // last_seen
                ]
            );
        }
        
        return $result !== false;
    }

    /**
     * Get user statistics for a notification.
     *
     * @param int $notificationId Notification ID
     * @param int $userId User ID
     * @return array|null User statistics or null if not found
     * @since 2.0.0
     */
    public function getUserStats(int $notificationId, int $userId): ?array
    {
        global $wpdb;
        
        $tables = $this->getTableNames();
        $table = $tables['user_stats'];
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table 
            WHERE notification_id = %d AND user_id = %d",
            $notificationId,
            $userId
        );
        
        $result = $wpdb->get_row($sql, ARRAY_A);
        
        return $result ?: null;
    }

    // =========================================================================
    // ⚙️ USER PREFERENCES OPERATIONS
    // =========================================================================

    /**
     * Store user preferences.
     *
     * @param int $userId User ID
     * @param array $preferences User preferences
     * @param bool $consentGiven Whether user has given consent
     * @return bool True on success, false on failure
     * @since 2.0.0
     */
    public function storeUserPreferences(int $userId, array $preferences, bool $consentGiven = false): bool
    {
        global $wpdb;
        
        $tables = $this->getTableNames();
        $table = $tables['user_preferences'];
        
        $now = current_time('mysql');
        
        $result = $wpdb->replace(
            $table,
            [
                'user_id' => $userId,
                'preferences' => json_encode($preferences),
                'consent_given' => $consentGiven ? 1 : 0,
                'consent_timestamp' => $consentGiven ? $now : null,
            ],
            [
                '%d', // user_id
                '%s', // preferences
                '%d', // consent_given
                '%s', // consent_timestamp
            ]
        );
        
        return $result !== false;
    }

    /**
     * Get user preferences.
     *
     * @param int $userId User ID
     * @return array|null User preferences or null if not found
     * @since 2.0.0
     */
    public function getUserPreferences(int $userId): ?array
    {
        global $wpdb;
        
        $tables = $this->getTableNames();
        $table = $tables['user_preferences'];
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $userId
        );
        
        $result = $wpdb->get_row($sql, ARRAY_A);
        
        if (!$result) {
            return null;
        }
        
        $result['preferences'] = json_decode($result['preferences'], true) ?: [];
        
        return $result;
    }

    // =========================================================================
    // 🚫 FREQUENCY CAPPING OPERATIONS
    // =========================================================================

    /**
     * Check if user has reached frequency cap.
     *
     * @param int $notificationId Notification ID
     * @param int $userId User ID
     * @param string $sessionId Session ID
     * @param string $capType Cap type (daily, total, etc.)
     * @return bool True if cap reached, false otherwise
     * @since 2.0.0
     */
    public function hasReachedFrequencyCap(int $notificationId, int $userId, string $sessionId, string $capType): bool
    {
        global $wpdb;
        
        $tables = $this->getTableNames();
        $table = $tables['frequency_caps'];
        
        $sql = $wpdb->prepare(
            "SELECT current_count, cap_value FROM $table 
            WHERE notification_id = %d 
            AND user_id = %d 
            AND session_id = %s 
            AND cap_type = %s",
            $notificationId,
            $userId,
            $sessionId,
            $capType
        );
        
        $result = $wpdb->get_row($sql, ARRAY_A);
        
        if (!$result) {
            return false; // No cap set
        }
        
        return $result['current_count'] >= $result['cap_value'];
    }

    /**
     * Increment frequency cap counter.
     *
     * @param int $notificationId Notification ID
     * @param int $userId User ID
     * @param string $sessionId Session ID
     * @param string $capType Cap type
     * @param int $capValue Cap value
     * @param string $resetDate Reset date
     * @return bool True on success, false on failure
     * @since 2.0.0
     */
    public function incrementFrequencyCap(int $notificationId, int $userId, string $sessionId, string $capType, int $capValue, string $resetDate): bool
    {
        global $wpdb;
        
        $tables = $this->getTableNames();
        $table = $tables['frequency_caps'];
        
        $result = $wpdb->replace(
            $table,
            [
                'notification_id' => $notificationId,
                'user_id' => $userId,
                'session_id' => $sessionId,
                'cap_type' => $capType,
                'cap_value' => $capValue,
                'current_count' => 1,
                'reset_date' => $resetDate,
            ],
            [
                '%d', // notification_id
                '%d', // user_id
                '%s', // session_id
                '%s', // cap_type
                '%d', // cap_value
                '%d', // current_count
                '%s', // reset_date
            ]
        );
        
        if ($result === false) {
            return false;
        }
        
        // If record already existed, increment the counter
        if ($wpdb->insert_id === 0) {
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE $table SET 
                    current_count = current_count + 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE notification_id = %d 
                AND user_id = %d 
                AND session_id = %s 
                AND cap_type = %s",
                $notificationId,
                $userId,
                $sessionId,
                $capType
            ));
        }
        
        return $result !== false;
    }




    // =========================================================================
    // 🧹 MAINTENANCE OPERATIONS
    // =========================================================================

    /**
     * Clean up old data.
     *
     * @param int $daysOld Number of days old to consider for cleanup
     * @return int Number of rows deleted
     * @since 2.0.0
     */
    public function cleanupOldData(int $daysOld = 90): int
    {
        global $wpdb;
        
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));
        $deletedRows = 0;
        
        $tables = $this->getTableNames();
        
        // Clean up old tracking data
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$tables['tracking']} WHERE timestamp < %s",
            $cutoffDate
        ));
        $deletedRows += $result !== false ? $result : 0;
        
        // Clean up old frequency cap data
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$tables['frequency_caps']} WHERE reset_date < %s",
            $cutoffDate
        ));
        $deletedRows += $result !== false ? $result : 0;
        
        /**
         * Fires after cleaning up old data.
         *
         * @since 2.0.0
         * @param int $deletedRows Number of rows deleted
         * @param int $daysOld Number of days old that were cleaned up
         */
        do_action(ActionHooks::ONPAGE_DATABASE_CLEANUP_COMPLETED, $deletedRows, $daysOld);
        
        return $deletedRows;
    }

    /**
     * Get database statistics.
     *
     * @return array Database statistics
     * @since 2.0.0
     */
    public function getDatabaseStats(): array
    {
        global $wpdb;
        
        $tables = $this->getTableNames();
        $stats = [];
        
        foreach ($tables as $name => $table) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
            $stats[$name] = (int) $count;
        }
        
        return $stats;
    }


} 
