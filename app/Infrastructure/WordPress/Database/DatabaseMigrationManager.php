<?php

namespace Notifal\Infrastructure\WordPress\Database;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Shared\Utils\Helper;
use Throwable;

defined('ABSPATH') || exit;

/**
 * Class DatabaseMigrationManager
 *
 * Manages database migrations for the Notifal plugin.
 * Handles table creation, updates, and version management.
 *
 * @package Notifal\Infrastructure\WordPress\Database
 * @since 2.0.0
 * @updated 3.0.0 Persist DB version only after hooks succeed; isolate Throwable so install/update cannot white-screen the site.
 * @author Hossein <hossein@notifal.com>
 */
class DatabaseMigrationManager
{
    /**
     * Current database version.
     *
     * @since 2.0.0
     */
    private const CURRENT_VERSION = NOTIFAL_VERSION;

    /**
     * Option name for storing database version.
     *
     * @since 2.0.0
     */
    private const VERSION_OPTION = 'notifal_db_version';

    /**
     * Request-level lock so init + admin_init cannot double-run migrations.
     *
     * @since 3.0.0
     * @var bool
     */
    private static $isRunning = false;

    /**
     * Initialize the migration manager.
     *
     * @since 2.0.0
     */
    public static function init(): void
    {
        add_action('init', [self::class, 'checkAndRunMigrations']);
        add_action('admin_init', [self::class, 'checkAndRunMigrations']);
    }

    /**
     * Check if migrations need to be run and execute them.
     *
     * @since 2.0.0
     * @updated 3.0.0 Skip while a migration pass is already in progress for this request.
     */
    public static function checkAndRunMigrations(): void
    {
        // Avoid overlapping runs when both init and admin_init fire in one request.
        if (self::$isRunning) {
            return;
        }

        // Default to '0.0.0' if option doesn't exist (fresh install).
        // This ensures migrations run on first installation.
        $currentVersion = get_option(self::VERSION_OPTION, '0.0.0');

        if (version_compare($currentVersion, self::CURRENT_VERSION, '<')) {
            self::runMigrations($currentVersion);
        }
    }

    /**
     * Run all necessary migrations.
     *
     * Marks the database version only after before/after hooks complete successfully.
     * Catches Throwable so a single migration listener cannot take the whole site down.
     *
     * @param string $fromVersion Current database version
     * @since 2.0.0
     * @updated 3.0.0 Soft-fail migration hooks and delay version bump until hooks finish.
     */
    private static function runMigrations(string $fromVersion): void
    {
        // Mark this request as busy before any hook work starts.
        self::$isRunning = true;

        try {
            // Ensure we have the required WordPress database functions.
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

            /**
             * Fires before running database migrations.
             *
             * @since 2.0.0
             * @param string $fromVersion Current database version
             * @param string $toVersion Target database version
             */
            do_action(ActionHooks::DATABASE_MIGRATIONS_BEFORE_RUN, $fromVersion, self::CURRENT_VERSION);

            // Run migrations based on version.
            if (version_compare($fromVersion, '2.0.0', '<')) {
                self::migrateToVersion200();
            }

            /**
             * Fires after running database migrations.
             *
             * @since 2.0.0
             * @param string $fromVersion Previous database version
             * @param string $toVersion New database version
             */
            do_action(ActionHooks::DATABASE_MIGRATIONS_AFTER_RUN, $fromVersion, self::CURRENT_VERSION);

            // Persist version only when every migration step and hook finished cleanly.
            // Failed passes leave the old version so the next request can retry safely.
            update_option(self::VERSION_OPTION, self::CURRENT_VERSION);
        } catch (Throwable $throwable) {
            // Keep the site online; log details for support and allow a later retry.
            Helper::log(
                sprintf(
                    'Database migration failed from %1$s to %2$s: %3$s in %4$s:%5$d',
                    $fromVersion,
                    self::CURRENT_VERSION,
                    $throwable->getMessage(),
                    $throwable->getFile(),
                    $throwable->getLine()
                )
            );
        } finally {
            // Always clear the request lock so later admin_init/manual runs can proceed.
            self::$isRunning = false;
        }
    }

    /**
     * Migrate to version 2.0.0.
     *
     * Delegates migration tasks to individual modules via hooks.
     *
     * @since 2.0.0
     */
    private static function migrateToVersion200(): void
    {
        // Module migrations are handled by individual modules listening to DATABASE_MIGRATIONS_BEFORE_RUN hook
        // No additional logic needed at this level
    }

    /**
     * Get current database version.
     *
     * @return string Current database version
     * @since 2.0.0
     */
    public static function getCurrentVersion(): string
    {
        // Default to '0.0.0' if option doesn't exist (fresh install)
        return get_option(self::VERSION_OPTION, '0.0.0');
    }

    /**
     * Check if database is up to date.
     *
     * @return bool True if up to date, false otherwise
     * @since 2.0.0
     */
    public static function isUpToDate(): bool
    {
        return version_compare(self::getCurrentVersion(), self::CURRENT_VERSION, '>=');
    }

    /**
     * Force run migrations (for testing or manual updates).
     *
     * @since 2.0.0
     */
    public static function forceRunMigrations(): void
    {
        // Use getCurrentVersion() for consistency
        $currentVersion = self::getCurrentVersion();
        self::runMigrations($currentVersion);
    }

    /**
     * Get table names with prefix.
     *
     * @return array Array of table names
     * @since 2.0.0
     */
    public static function getTableNames(): array
    {
        global $wpdb;
        
        // Core plugin tables (if any) would go here
        $tables = [];
        
        /**
         * Allow modules to register their table names.
         *
         * @since 2.0.0
         * @param array $tables Array of table names
         */
        return apply_filters(FilterHooks::DATABASE_TABLE_NAMES, $tables);
    }

    /**
     * Check if all required tables exist.
     *
     * @return bool True if all tables exist, false otherwise
     * @since 2.0.0
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
     * Clean up old data (for maintenance).
     *
     * @param int $daysOld Number of days old to consider for cleanup
     * @since 2.0.0
     */
    public static function cleanupOldData(int $daysOld = 90): void
    {
        /**
         * Allow modules to clean up their own old data.
         *
         * @since 2.0.0
         * @param int $daysOld Number of days old to consider for cleanup
         */
        do_action(ActionHooks::DATABASE_CLEANUP_OLD_DATA, $daysOld);
        
        /**
         * Fires after cleaning up old data.
         *
         * @since 2.0.0
         * @param int $daysOld Number of days old that were cleaned up
         */
        do_action(ActionHooks::DATABASE_CLEANUP_COMPLETED, $daysOld);
    }
} 
