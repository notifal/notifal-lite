<?php
/**
 * Sticky Menu Service Provider
 *
 * Registers all sticky menu related services and hooks.
 *
 * @package Notifal\Infrastructure\WordPress\StickyMenu
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\StickyMenu;

use Notifal\Infrastructure\WordPress\StickyMenu\Infrastructure\StickyMenuController;

defined('ABSPATH') || exit;

/**
 * Class StickyMenuServiceProvider
 */
class StickyMenuServiceProvider
{
    /**
     * Register sticky menu services and hooks.
     *
     * Sticky menu assets are included in the shared admin bundles,
     * so no separate asset registration is needed.
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        // Register controller hooks
        StickyMenuController::register();

        // Note: Assets are included in shared bundles, no separate registration needed
    }
}
