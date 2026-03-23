<?php

namespace Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Database;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Class MigrationService
 *
 * Handles database table creation and indexing for the OnPage Notification module.
 * Integrates with the central DatabaseMigrationManager for schema management.
 *
 * @package Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Database
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class MigrationService
{
    /**
     * Initialize the migration service.
     *
     * Hooks into the central database migration system to handle OnPage notification tables.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function init(): void
    {
        add_action(ActionHooks::DATABASE_MIGRATIONS_BEFORE_RUN, [self::class, 'onBeforeMigrations']);
        add_action(ActionHooks::DATABASE_CLEANUP_OLD_DATA, [self::class, 'onCleanupOldData']);
        add_filter(FilterHooks::DATABASE_TABLE_NAMES, [self::class, 'onGetTableNames']);
    }

    /**
     * Create OnPage notification database tables.
     *
     * Creates all required tables for tracking, analytics, and user preferences.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function createTables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Table for tracking notification events
        $table_name = $wpdb->prefix . 'notifal_onpage_tracking';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            notification_id bigint(20) unsigned NOT NULL,
            event_type varchar(50) NOT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            timestamp datetime NOT NULL,
            user_agent text,
            referrer varchar(500),
            page_url varchar(500),
            ip_address varchar(45),
            session_id varchar(100),
            device_type varchar(20) DEFAULT 'desktop',
            campaign_id bigint(20) unsigned DEFAULT 0,
            country_code varchar(2),
            city varchar(100),
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY notification_id (notification_id),
            KEY event_type (event_type),
            KEY user_id (user_id),
            KEY timestamp (timestamp),
            KEY session_id (session_id),
            KEY device_type (device_type),
            KEY campaign_id (campaign_id),
            KEY campaign_event_date (campaign_id, event_type, timestamp)
        ) $charset_collate;";

        dbDelta($sql);

        // Table for daily statistics
        $table_name = $wpdb->prefix . 'notifal_onpage_daily_stats';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            notification_id bigint(20) unsigned NOT NULL,
            date date NOT NULL,
            impressions int(11) unsigned DEFAULT 0,
            clicks int(11) unsigned DEFAULT 0,
            closes int(11) unsigned DEFAULT 0,
            dismisses int(11) unsigned DEFAULT 0,
            conversions int(11) unsigned DEFAULT 0,
            revenue decimal(10,2) DEFAULT 0.00,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY notification_date (notification_id, date),
            KEY notification_id (notification_id),
            KEY date (date)
        ) $charset_collate;";

        dbDelta($sql);

        // Table for user-specific statistics
        $table_name = $wpdb->prefix . 'notifal_onpage_user_stats';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            notification_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            total_impressions int(11) unsigned DEFAULT 0,
            total_clicks int(11) unsigned DEFAULT 0,
            total_closes int(11) unsigned DEFAULT 0,
            total_dismisses int(11) unsigned DEFAULT 0,
            total_conversions int(11) unsigned DEFAULT 0,
            first_seen datetime NOT NULL,
            last_seen datetime NOT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY notification_user (notification_id, user_id),
            KEY notification_id (notification_id),
            KEY user_id (user_id),
            KEY last_seen (last_seen)
        ) $charset_collate;";

        dbDelta($sql);

        // Table for user preferences
        $table_name = $wpdb->prefix . 'notifal_onpage_user_preferences';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            preferences longtext NOT NULL,
            consent_given tinyint(1) DEFAULT 0,
            consent_timestamp datetime,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY consent_given (consent_given)
        ) $charset_collate;";

        dbDelta($sql);

        // Table for frequency capping
        $table_name = $wpdb->prefix . 'notifal_onpage_frequency_caps';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            notification_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            session_id varchar(100) DEFAULT '',
            cap_type varchar(20) NOT NULL,
            cap_value int(11) unsigned NOT NULL,
            current_count int(11) unsigned DEFAULT 0,
            reset_date datetime NOT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY notification_user_session_type (notification_id, user_id, session_id, cap_type),
            KEY notification_id (notification_id),
            KEY user_id (user_id),
            KEY session_id (session_id),
            KEY reset_date (reset_date)
        ) $charset_collate;";

        dbDelta($sql);

        // Table for event queue (performance optimization)
        $table_name = $wpdb->prefix . 'notifal_onpage_event_queue';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            notification_id bigint(20) unsigned NOT NULL,
            event_type varchar(50) NOT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            session_id varchar(100),
            timestamp datetime NOT NULL,
            user_agent text,
            referrer varchar(500),
            page_url varchar(500),
            ip_address varchar(45),
            device_type varchar(20) DEFAULT 'desktop',
            campaign_id bigint(20) unsigned DEFAULT 0,
            country_code varchar(2),
            city varchar(100),
            timezone varchar(50),
            screen_resolution varchar(20),
            viewport_size varchar(20),
            processed tinyint(1) DEFAULT 0,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY notification_id (notification_id),
            KEY event_type (event_type),
            KEY user_id (user_id),
            KEY session_id (session_id),
            KEY processed (processed),
            KEY timestamp (timestamp),
            KEY created_at (created_at),
            KEY campaign_id (campaign_id)
        ) $charset_collate;";

        dbDelta($sql);

        // Table for product click attribution (v2.0.2)
        $table_name = $wpdb->prefix . 'notifal_onpage_product_clicks';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            notification_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            session_id varchar(100),
            campaign_id bigint(20) unsigned DEFAULT 0,
            click_timestamp datetime NOT NULL,
            attribution_window_hours int(11) unsigned DEFAULT 24,
            page_url varchar(500),
            referrer varchar(500),
            ip_address varchar(45),
            user_agent text,
            status varchar(20) DEFAULT 'pending',
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY notification_id (notification_id),
            KEY product_id (product_id),
            KEY user_id (user_id),
            KEY session_id (session_id),
            KEY click_timestamp (click_timestamp),
            KEY status (status),
            KEY attribution_lookup (product_id, click_timestamp, status),
            KEY campaign_id (campaign_id)
        ) $charset_collate;";

        dbDelta($sql);

        // Table for conversion attribution tracking (v2.0.2)
        $table_name = $wpdb->prefix . 'notifal_onpage_conversions';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            notification_id bigint(20) unsigned NOT NULL,
            product_click_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            product_revenue decimal(10,2) NOT NULL,
            total_order_value decimal(10,2) NOT NULL,
            currency varchar(3) DEFAULT 'USD',
            click_timestamp datetime NOT NULL,
            conversion_timestamp datetime NOT NULL,
            attribution_type varchar(20) DEFAULT 'woocommerce',
            user_id bigint(20) unsigned DEFAULT 0,
            campaign_id bigint(20) unsigned DEFAULT 0,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_product_order (product_click_id, order_id, product_id),
            KEY notification_id (notification_id),
            KEY product_click_id (product_click_id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY conversion_timestamp (conversion_timestamp),
            KEY attribution_type (attribution_type),
            KEY campaign_id (campaign_id),
            KEY campaign_conversion_date (campaign_id, conversion_timestamp)
        ) $charset_collate;";

        dbDelta($sql);

    }

    /**
     * Check if an index exists on a table.
     *
     * @param string $tableName Table name
     * @param string $indexName Index name
     * @return bool True if index exists, false otherwise
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function indexExists(string $tableName, string $indexName): bool
    {
        global $wpdb;

        // Query to check if index exists
        $result = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW INDEX FROM `{$tableName}` WHERE Key_name = %s",
                $indexName
            )
        );

        return !empty($result);
    }

    /**
     * Create an index if it doesn't already exist.
     *
     * @param string $tableName Table name
     * @param string $indexName Index name
     * @param string $columns Columns for the index
     * @return bool True on success, false on failure
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function createIndexIfNotExists(string $tableName, string $indexName, string $columns): bool
    {
        global $wpdb;

        // Check if index already exists
        if (self::indexExists($tableName, $indexName)) {
            return true; // Index already exists, skip creation
        }

        // Create the index
        $sql = "CREATE INDEX `{$indexName}` ON `{$tableName}` ({$columns})";
        $result = $wpdb->query($sql);

        return $result !== false;
    }

    /**
     * Create indexes for better performance.
     *
     * Compatible with MySQL 5.x and MariaDB 10.x which don't support
     * 'CREATE INDEX IF NOT EXISTS' syntax.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function createIndexes(): void
    {
        global $wpdb;

        // Define all indexes to create
        $indexes = [
            // Tracking table indexes
            [
                'table' => $wpdb->prefix . 'notifal_onpage_tracking',
                'name' => 'idx_tracking_notification_event_date',
                'columns' => 'notification_id, event_type, timestamp'
            ],
            [
                'table' => $wpdb->prefix . 'notifal_onpage_tracking',
                'name' => 'idx_tracking_campaign_event_date',
                'columns' => 'campaign_id, event_type, timestamp'
            ],
            [
                'table' => $wpdb->prefix . 'notifal_onpage_tracking',
                'name' => 'idx_tracking_user_date',
                'columns' => 'user_id, timestamp'
            ],
            [
                'table' => $wpdb->prefix . 'notifal_onpage_tracking',
                'name' => 'idx_tracking_session_date',
                'columns' => 'session_id, timestamp'
            ],
            // Daily stats table indexes
            [
                'table' => $wpdb->prefix . 'notifal_onpage_daily_stats',
                'name' => 'idx_daily_stats_notification_date',
                'columns' => 'notification_id, date'
            ],
            // User stats table indexes
            [
                'table' => $wpdb->prefix . 'notifal_onpage_user_stats',
                'name' => 'idx_user_stats_notification_user',
                'columns' => 'notification_id, user_id'
            ],
            // Frequency caps table indexes
            [
                'table' => $wpdb->prefix . 'notifal_onpage_frequency_caps',
                'name' => 'idx_frequency_caps_notification_user',
                'columns' => 'notification_id, user_id, cap_type'
            ],
            // Event queue table indexes
            [
                'table' => $wpdb->prefix . 'notifal_onpage_event_queue',
                'name' => 'idx_event_queue_processed_created',
                'columns' => 'processed, created_at'
            ],
            [
                'table' => $wpdb->prefix . 'notifal_onpage_event_queue',
                'name' => 'idx_event_queue_notification_event',
                'columns' => 'notification_id, event_type'
            ],
            [
                'table' => $wpdb->prefix . 'notifal_onpage_event_queue',
                'name' => 'idx_event_queue_campaign',
                'columns' => 'campaign_id'
            ],
            // Product clicks table indexes (v2.0.2)
            [
                'table' => $wpdb->prefix . 'notifal_onpage_product_clicks',
                'name' => 'idx_product_clicks_attribution',
                'columns' => 'notification_id, product_id, click_timestamp'
            ],
            [
                'table' => $wpdb->prefix . 'notifal_onpage_product_clicks',
                'name' => 'idx_product_clicks_session',
                'columns' => 'session_id, click_timestamp'
            ],
            [
                'table' => $wpdb->prefix . 'notifal_onpage_product_clicks',
                'name' => 'idx_product_clicks_campaign',
                'columns' => 'campaign_id, click_timestamp'
            ],
            // Conversions table indexes (v2.0.2)
            [
                'table' => $wpdb->prefix . 'notifal_onpage_conversions',
                'name' => 'idx_conversions_revenue_calc',
                'columns' => 'notification_id, conversion_timestamp, product_revenue'
            ],
            [
                'table' => $wpdb->prefix . 'notifal_onpage_conversions',
                'name' => 'idx_conversions_campaign_date',
                'columns' => 'campaign_id, conversion_timestamp'
            ],
        ];

        // Create each index if it doesn't exist
        foreach ($indexes as $index) {
            self::createIndexIfNotExists($index['table'], $index['name'], $index['columns']);
        }
    }

    /**
     * Drop legacy tables from versions before 2.0.0.
     *
     * Removes old database tables that are no longer used in the current architecture.
     * This ensures a clean migration when upgrading from pre-2.0.0 versions.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function dropLegacyTables(): void
    {
        global $wpdb;

        // Define legacy table names that should be dropped from pre-2.0.0 versions
        $legacyTables = [
            $wpdb->prefix . 'notifal_notif',
            $wpdb->prefix . 'notifal_notifmeta',
            $wpdb->prefix . 'notifal_user_actions',
            $wpdb->prefix . 'notifal_user_actions_today',
            $wpdb->prefix . 'notifal_analytics',
        ];

        // Drop each legacy table if it exists
        foreach ($legacyTables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }

    /**
     * Hook: Called before main database migrations run.
     *
     * Creates and updates OnPage notification database schema.
     * Drops legacy tables from versions before 2.0.0 when upgrading.
     *
     * @param string $fromVersion Current database version
     * @param string $toVersion Target database version
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function onBeforeMigrations(string $fromVersion = '', string $toVersion = ''): void
    {
        // Ensure WordPress database upgrade functions are available
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Drop legacy tables from versions before 2.0.0
        if (version_compare($fromVersion, '2.0.0', '<')) {
            self::dropLegacyTables();
        }

        // Create tables and indexes when migrations run
        self::createTables();
        self::createIndexes();
    }

    /**
     * Hook: Called when cleaning up old data.
     *
     * @param int $daysOld Number of days old to consider for cleanup
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function onCleanupOldData(int $daysOld): void
    {
        self::cleanupOldData($daysOld);
    }

    /**
     * Hook: Called when getting table names.
     *
     * @param array $tables Array of table names
     * @return array Modified array of table names
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function onGetTableNames(array $tables): array
    {
        global $wpdb;
        
        $tables['onpage_tracking'] = $wpdb->prefix . 'notifal_onpage_tracking';
        $tables['onpage_daily_stats'] = $wpdb->prefix . 'notifal_onpage_daily_stats';
        $tables['onpage_user_stats'] = $wpdb->prefix . 'notifal_onpage_user_stats';
        $tables['onpage_user_preferences'] = $wpdb->prefix . 'notifal_onpage_user_preferences';
        $tables['onpage_frequency_caps'] = $wpdb->prefix . 'notifal_onpage_frequency_caps';
        $tables['onpage_event_queue'] = $wpdb->prefix . 'notifal_onpage_event_queue';
        $tables['onpage_product_clicks'] = $wpdb->prefix . 'notifal_onpage_product_clicks';
        $tables['onpage_conversions'] = $wpdb->prefix . 'notifal_onpage_conversions';

        
        return $tables;
    }

    /**
     * Clean up old data for this module.
     *
     * Removes tracking data and frequency caps older than specified days.
     *
     * @param int $daysOld Number of days old to consider for cleanup
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function cleanupOldData(int $daysOld): void
    {
        global $wpdb;

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));

        // Clean up old tracking data
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}notifal_onpage_tracking WHERE timestamp < %s",
            $cutoffDate
        ));

        // Clean up old frequency cap data
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}notifal_onpage_frequency_caps WHERE reset_date < %s",
            $cutoffDate
        ));

        /**
         * Fires after cleaning up OnPage notification database data.
         *
         * @since 2.0.0
         * @param int $daysOld Number of days old that were cleaned up
         */
        do_action(ActionHooks::ONPAGE_DATABASE_CLEANUP_COMPLETED, $daysOld);
    }

    /**
     * Get module table names with prefix.
     *
     * @return array Array of table names
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function getTableNames(): array
    {
        global $wpdb;
        
        return [
            'tracking' => $wpdb->prefix . 'notifal_onpage_tracking',
            'daily_stats' => $wpdb->prefix . 'notifal_onpage_daily_stats',
            'user_stats' => $wpdb->prefix . 'notifal_onpage_user_stats',
            'user_preferences' => $wpdb->prefix . 'notifal_onpage_user_preferences',
            'frequency_caps' => $wpdb->prefix . 'notifal_onpage_frequency_caps',
            'event_queue' => $wpdb->prefix . 'notifal_onpage_event_queue',
            'product_clicks' => $wpdb->prefix . 'notifal_onpage_product_clicks',
            'conversions' => $wpdb->prefix . 'notifal_onpage_conversions',
        ];
    }

    /**
     * Check if all required module tables exist.
     *
     * @return bool True if all tables exist, false otherwise
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function checkTablesExist(): bool
    {
        global $wpdb;
        
        $tables = self::getTableNames();
        
        foreach ($tables as $table) {
            $result = $wpdb->get_var("SHOW TABLES LIKE '$table'");
            if (!$result) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Clean up module tables (for module deactivation).
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function cleanupTables(): void
    {
        global $wpdb;
        
        $tables = self::getTableNames();
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        /**
         * Fires after cleaning up OnPage notification tables.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::ONPAGE_DATABASE_TABLES_CLEANED_UP);
    }
} 
