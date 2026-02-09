<?php
/**
 * Deactivation Popup Service
 *
 * Main service for the deactivation popup infrastructure component.
 * Always loaded as part of core WordPress infrastructure.
 *
 * @package Notifal\Infrastructure\WordPress\DeactivationPopup
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\DeactivationPopup;

use Notifal\Infrastructure\WordPress\DeactivationPopup\Application\DeactivationApiService;
use Notifal\Infrastructure\WordPress\DeactivationPopup\Infrastructure\DeactivationController;
use Notifal\Infrastructure\WordPress\DeactivationPopup\Presentation\Admin\DeactivationAssetsRegistrar;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class DeactivationPopupService
 *
 * Main service provider for deactivation popup functionality.
 * This is always active infrastructure, not a user-configurable module.
 */
class DeactivationPopupService
{
    /**
     * Register deactivation popup services
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        // Register services in dependency order
        DeactivationController::register();
        DeactivationAssetsRegistrar::register();

        // Register AJAX handlers for skip action
        add_action('wp_ajax_notifal_skip_deactivation_feedback', [self::class, 'handleSkipDeactivation']);
    }

    /**
     * Boot deactivation popup services
     *
     * @return void
     * @since 2.0.0
     */
    public static function boot(): void
    {
        // Note: Skip deactivations now use scheduled events to send data after deactivation is complete
        // AJAX handler is now registered in register() method
    }

    /**
     * Handle skip deactivation feedback AJAX request
     *
     * @return void
     * @since 2.0.0
     */
    public static function handleSkipDeactivation(): void
    {
        try {
            // Verify AJAX request with nonce and capabilities
            notifal_verify_ajax_request('notifal_deactivation_skip', 'deactivate_plugins');

            // Get container and services
            $container = \Notifal\Core\Foundation\Container::getInstance();
            $deactivationPopup = $container->get(\Notifal\Infrastructure\WordPress\DeactivationPopup\Domain\DeactivationPopup::class);

            // Get basic site information for tracking
            $site_info = $deactivationPopup->getSiteInformation();
            $deactivation_data = new \Notifal\Infrastructure\WordPress\DeactivationPopup\Domain\DeactivationData(
                array_merge($site_info, [
                    'deactivation_reason' => 'skip_no_feedback',
                ])
            );

            // Store deactivation data for immediate sending during deactivation
            set_transient('notifal_skip_deactivation_data', $deactivation_data->toArray(), 5 * MINUTE_IN_SECONDS);

            // Update deactivation tracking
            $deactivationPopup->incrementDeactivationCount();
            $deactivationPopup->updateLastDeactivationTime();
            $deactivationPopup->setFirstDeactivationTimeIfNotSet();

            notifal_json_success(null, __('Deactivating plugin...', 'notifal'));

        } catch (\Exception $e) {
            Helper::logAdvanced('Error processing skip deactivation action: ' . $e->getMessage(), 'ERROR');
            notifal_json_error(__('An error occurred while deactivating the plugin.', 'notifal'));
        }
    }

    /**
     * Process delayed API call for deactivation feedback
     *
     * @param array $args Arguments containing transient key
     * @return void
     * @since 2.0.0
     */
    public static function processDelayedApiCall(array $args): void
    {
        try {
            $transient_key = $args[0] ?? '';
            if (empty($transient_key)) {
                return;
            }

            // Get deactivation data from transient
            $deactivation_data_array = get_transient($transient_key);
            if (empty($deactivation_data_array)) {
                return;
            }

            // Delete transient after use
            delete_transient($transient_key);

            $container = \Notifal\Core\Foundation\Container::getInstance();
            $apiService = $container->get(DeactivationApiService::class);

            $deactivation_data = new \Notifal\Infrastructure\WordPress\DeactivationPopup\Domain\DeactivationData($deactivation_data_array);
            $apiService->sendDeactivationFeedback($deactivation_data);

        } catch (\Exception $e) {
            Helper::logAdvanced('Delayed API call failed: ' . $e->getMessage(), 'ERROR');
        }
    }
}
