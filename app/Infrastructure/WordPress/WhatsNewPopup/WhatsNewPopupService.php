<?php
/**
 * What's New Popup Service
 *
 * Main service for the what's new popup infrastructure component.
 * Always loaded as part of core WordPress infrastructure.
 *
 * @package Notifal\Infrastructure\WordPress\WhatsNewPopup
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\WhatsNewPopup;

use Notifal\Infrastructure\WordPress\WhatsNewPopup\Infrastructure\WhatsNewController;
use Notifal\Infrastructure\WordPress\WhatsNewPopup\Presentation\Admin\WhatsNewAssetsRegistrar;

defined('ABSPATH') || exit;

/**
 * Class WhatsNewPopupService
 *
 * Main service provider for what's new popup functionality.
 * This is always active infrastructure, not a user-configurable module.
 */
class WhatsNewPopupService
{
    /**
     * Register what's new popup services
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        // Register services in dependency order
        WhatsNewController::register();
        WhatsNewAssetsRegistrar::register();
    }

    /**
     * Boot what's new popup services
     *
     * @return void
     * @since 2.0.0
     */
    public static function boot(): void
    {
        // Boot services if needed
    }
}
