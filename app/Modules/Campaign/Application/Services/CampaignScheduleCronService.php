<?php

namespace Notifal\Modules\Campaign\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers WordPress cron for campaign end-date maintenance.
 *
 * @package Notifal\Modules\Campaign\Application\Services
 * @since 2.2.0
 * @author Hossein <hossein@notifal.com>
 */
final class CampaignScheduleCronService {

    /**
     * WordPress cron hook name for end-date expiry processing.
     *
     * @since 2.2.0
     * @var string
     */
    private const CRON_HOOK = 'notifal_campaign_end_date_expiry';

    /**
     * Registers cron scheduling, hooks, and lifecycle cleanup.
     *
     * @since 2.2.0
     * @return void
     */
    public static function register(): void {
        add_action( 'init', [ self::class, 'maybeSchedule' ] );
        add_action( self::CRON_HOOK, [ self::class, 'run' ] );
        add_action( ActionHooks::PLUGIN_ACTIVATED, [ self::class, 'maybeSchedule' ] );
        add_action( ActionHooks::PLUGIN_DEACTIVATED, [ self::class, 'unschedule' ] );
    }

    /**
     * Ensures the hourly cron event exists when missing.
     *
     * @since 2.2.0
     * @return void
     */
    public static function maybeSchedule(): void {
        if ( wp_next_scheduled( self::CRON_HOOK ) ) {
            return;
        }

        wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::CRON_HOOK );
    }

    /**
     * Removes the scheduled event on plugin deactivation.
     *
     * @since 2.2.0
     * @return void
     */
    public static function unschedule(): void {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }

    /**
     * Executes campaign expiry maintenance.
     *
     * @since 2.2.0
     * @return void
     */
    public static function run(): void {
        $service = notifal_app( CampaignSettingsService::class );
        $count   = $service->markExpiredCampaigns();

        do_action( ActionHooks::CAMPAIGN_END_DATE_EXPIRY_CRON_COMPLETED, $count );
    }
}
