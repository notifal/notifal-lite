<?php
/**
 * Activation Popup Service
 *
 * Main service for the activation popup infrastructure component.
 *
 * @package Notifal\Infrastructure\WordPress\ActivationPopup
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\ActivationPopup;

use Notifal\Infrastructure\WordPress\ActivationPopup\Infrastructure\ActivationController;
use Notifal\Infrastructure\WordPress\ActivationPopup\Presentation\Admin\ActivationAssetsRegistrar;

defined('ABSPATH') || exit;

/**
 * Class ActivationPopupService
 *
 * Main service provider for activation popup functionality.
 */
class ActivationPopupService
{
    /**
     * Register activation popup services
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        // Register services in dependency order
        ActivationController::register();
        ActivationAssetsRegistrar::register();
    }

    /**
     * Boot activation popup services
     *
     * @return void
     * @since 2.0.0
     */
    public static function boot(): void
    {
        // Boot services if needed
    }
}
