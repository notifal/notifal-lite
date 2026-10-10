<?php

namespace Notifal\Modules\Forms\Infrastructure\WordPress\Database;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Forms module database migrations for submission tables.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class MigrationService
{
    /**
     * Hook into central migration manager.
     *
     * @return void
     * @since 3.0.0
     */
    public static function init(): void
    {
        add_action(ActionHooks::DATABASE_MIGRATIONS_BEFORE_RUN, [self::class, 'onBeforeMigrations']);
        add_filter(FilterHooks::DATABASE_TABLE_NAMES, [self::class, 'onGetTableNames']);

        // dbDelta is idempotent; ensure tables exist even when DB version already matches.
        add_action('admin_init', [self::class, 'createTables'], 5);
    }

    /**
     * Create Forms tables before migrations run.
     *
     * @param string $fromVersion From version.
     * @param string $toVersion   To version.
     * @return void
     * @since 3.0.0
     */
    public static function onBeforeMigrations(string $fromVersion = '', string $toVersion = ''): void
    {
        unset($fromVersion, $toVersion);
        self::createTables();
    }

    /**
     * Register table names for cleanup tooling.
     *
     * @param array<int, string> $tables Table list.
     * @return array<int, string>
     * @since 3.0.0
     */
    public static function onGetTableNames(array $tables): array
    {
        global $wpdb;

        $tables[] = $wpdb->prefix . 'notifal_form_submissions';
        $tables[] = $wpdb->prefix . 'notifal_form_submission_fields';

        return $tables;
    }

    /**
     * Create submission tables via dbDelta.
     *
     * @return void
     * @since 3.0.0
     */
    public static function createTables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $submissions = $wpdb->prefix . 'notifal_form_submissions';
        $fields = $wpdb->prefix . 'notifal_form_submission_fields';

        $sql1 = "CREATE TABLE {$submissions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_id bigint(20) unsigned NOT NULL DEFAULT 0,
            notification_id bigint(20) unsigned NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'new',
            admin_note longtext,
            ip_hash varchar(64) NOT NULL DEFAULT '',
            user_agent text,
            page_url varchar(500) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY form_id (form_id),
            KEY notification_id (notification_id),
            KEY status (status),
            KEY created_at (created_at),
            KEY form_created (form_id, created_at)
        ) {$charset};";

        $sql2 = "CREATE TABLE {$fields} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            submission_id bigint(20) unsigned NOT NULL,
            field_name varchar(100) NOT NULL DEFAULT '',
            field_value longtext,
            PRIMARY KEY  (id),
            KEY submission_id (submission_id),
            KEY field_name (field_name)
        ) {$charset};";

        dbDelta($sql1);
        dbDelta($sql2);
    }
}
