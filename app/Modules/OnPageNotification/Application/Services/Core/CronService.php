<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Core;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Shared\Utils\Helper;
use Notifal\Modules\OnPageNotification\Application\Services\Analytics\EventProcessor;

defined('ABSPATH') || exit;

/**
 * Class CronService
 *
 * Handles cron scheduling for background event processing
 * and queue maintenance tasks.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class CronService
{
    /**
     * Cron hook name for event processing.
     */
    private const CRON_HOOK_PROCESS = 'notifal_onpage_process_events';

    /**
     * Cron hook name for queue cleanup.
     */
    private const CRON_HOOK_CLEANUP = 'notifal_onpage_cleanup_queue';

    /**
     * @var EventProcessor
     */
    private $eventProcessor;

    /**
     * Initialize cron service.
     *
     * @since 2.0.0
     */
    public static function init(): void
    {
        $instance = new self();
        $instance->registerHooks();
        $instance->scheduleEvents();
    }

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->eventProcessor = notifal_app(EventProcessor::class);
    }

    /**
     * Get validated batch size from options.
     *
     * @return int Validated batch size
     * @since 2.0.0
     */
    private function getValidatedBatchSize(): int
    {
        $batchSize = get_option('notifal_onpage_processing_batch_size', 50);

        // Ensure reasonable batch size
        return max(10, min(200, (int) $batchSize));
    }

    /**
     * Register WordPress hooks.
     *
     * @since 2.0.0
     */
    private function registerHooks(): void
    {
        // Register cron hooks
        add_action(self::CRON_HOOK_PROCESS, [$this, 'processEvents']);
        add_action(self::CRON_HOOK_CLEANUP, [$this, 'cleanupQueue']);
        
        // Add custom cron interval
        add_filter('cron_schedules', [$this, 'addCustomCronInterval']);
    }

    /**
     * Schedule cron events.
     *
     * @since 2.0.0
     */
    private function scheduleEvents(): void
    {
        // Schedule event processing every 5 hours
        if (!wp_next_scheduled(self::CRON_HOOK_PROCESS)) {
            wp_schedule_event(time(), 'notifal_every_5_hours', self::CRON_HOOK_PROCESS);
        }

        // Schedule queue cleanup daily
        if (!wp_next_scheduled(self::CRON_HOOK_CLEANUP)) {
            wp_schedule_event(time(), 'daily', self::CRON_HOOK_CLEANUP);
        }

    }

    /**
     * Process events via cron.
     *
     * @since 2.0.0
     */
    public function processEvents(): void
    {
        try {
            $batchSize = $this->getValidatedBatchSize();

            // Process events
            $result = $this->eventProcessor->processQueuedEvents($batchSize);

            /**
             * Fires after cron event processing.
             *
             * @since 2.0.0
             * @param array $result Processing result
             */
            do_action(ActionHooks::ONPAGE_CRON_PROCESSING_COMPLETED, $result);

        } catch (\Exception $e) {
            // Log critical cron processing errors
            Helper::log('Notifal OnPage Cron Processing Error: ' . $e->getMessage());
        }
    }

    /**
     * Clean up queue via cron.
     *
     * @since 2.0.0
     */
    public function cleanupQueue(): void
    {
        try {
            // Get cleanup age from options
            $cleanupAge = get_option('notifal_onpage_cleanup_age_days', 7);
            
            // Ensure reasonable cleanup age
            $cleanupAge = max(1, min(90, (int) $cleanupAge));
            
            // Clean up old events
            $eventQueue = notifal_app(EventQueue::class);
            $deletedCount = $eventQueue->cleanupOldEvents($cleanupAge);

            /**
             * Fires after cron queue cleanup.
             *
             * @since 2.0.0
             * @param int $deletedCount Number of events deleted
             */
            do_action(ActionHooks::ONPAGE_CRON_CLEANUP_COMPLETED, $deletedCount);

        } catch (\Exception $e) {
            // Log critical cron cleanup errors
            Helper::log('Notifal OnPage Cron Cleanup Error: ' . $e->getMessage());
        }
    }

    /**
     * Handle module activation.
     *
     * @since 2.0.0
     */
    public function onModuleActivated(): void
    {
        // Schedule events
        $this->scheduleEvents();
    }

    /**
     * Handle module deactivation.
     *
     * @since 2.0.0
     */
    public function onModuleDeactivated(): void
    {
        // Clear scheduled events
        wp_clear_scheduled_hook(self::CRON_HOOK_PROCESS);
        wp_clear_scheduled_hook(self::CRON_HOOK_CLEANUP);
    }

    /**
     * Add custom cron interval.
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     * @since 2.0.0
     */
    public function addCustomCronInterval(array $schedules): array
    {
        $schedules['notifal_every_5_hours'] = [
            'interval' => 18000, // 5 hours
            'display' => __('Every 5 Hours (Notifal)', 'notifal'),
        ];
        
        return $schedules;
    }

    /**
     * Get cron status information.
     *
     * @return array Cron status
     * @since 2.0.0
     */
    public function getCronStatus(): array
    {
        $processNext = wp_next_scheduled(self::CRON_HOOK_PROCESS);
        $cleanupNext = wp_next_scheduled(self::CRON_HOOK_CLEANUP);
        
        $wpCronDisabled = $this->isWpCronDisabled();

        return [
            'processing' => [
                'scheduled' => $processNext !== false,
                'next_run' => $processNext ? date('Y-m-d H:i:s', $processNext) : null,
                'hook' => self::CRON_HOOK_PROCESS,
            ],
            'cleanup' => [
                'scheduled' => $cleanupNext !== false,
                'next_run' => $cleanupNext ? date('Y-m-d H:i:s', $cleanupNext) : null,
                'hook' => self::CRON_HOOK_CLEANUP,
            ],
            'wp_cron_disabled' => $wpCronDisabled,
        ];
    }

    /**
     * Force run cron processing now.
     *
     * @return array Processing result
     * @since 2.0.0
     */
    public function forceRunProcessing(): array
    {
        try {
            $batchSize = $this->getValidatedBatchSize();

            // Process events directly
            $result = $this->eventProcessor->processQueuedEvents($batchSize);

            return [
                'success' => true,
                'message' => $result['message'] ?? __('Cron processing executed successfully', 'notifal'),
                'processed_count' => $result['processed_count'] ?? 0,
                'error_count' => $result['error_count'] ?? 0,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('Error executing cron processing', 'notifal'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Force run cleanup now.
     *
     * @return array Cleanup result
     * @since 2.0.0
     */
    public function forceRunCleanup(): array
    {
        try {
            $this->cleanupQueue();
            
            return [
                'success' => true,
                'message' => __('Cron cleanup executed successfully', 'notifal'),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('Error executing cron cleanup', 'notifal'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if WordPress cron is disabled.
     *
     * @return bool True if WP cron is disabled
     * @since 2.0.0
     */
    private function isWpCronDisabled(): bool
    {
        return defined('DISABLE_WP_CRON') && constant('DISABLE_WP_CRON');
    }

    /**
     * Reschedule cron events (useful for settings changes).
     *
     * @return array Result
     * @since 2.0.0
     */
    public function rescheduleCronEvents(): array
    {
        try {
            // Clear existing schedules
            wp_clear_scheduled_hook(self::CRON_HOOK_PROCESS);
            wp_clear_scheduled_hook(self::CRON_HOOK_CLEANUP);
            
            // Reschedule
            $this->scheduleEvents();
            
            return [
                'success' => true,
                'message' => __('Cron events rescheduled successfully', 'notifal'),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('Error rescheduling cron events', 'notifal'),
                'error' => $e->getMessage(),
            ];
        }
    }
}
