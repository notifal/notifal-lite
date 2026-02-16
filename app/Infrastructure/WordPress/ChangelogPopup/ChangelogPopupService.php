<?php
/**
 * Changelog Popup Service
 *
 * Registers the changelog popup (sticky menu button) infrastructure.
 * Separate from What's New popup.
 *
 * @package Notifal\Infrastructure\WordPress\ChangelogPopup
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\ChangelogPopup;

use Notifal\Infrastructure\WordPress\ChangelogPopup\Infrastructure\ChangelogController;
use Notifal\Infrastructure\WordPress\ChangelogPopup\Presentation\Admin\ChangelogAssetsRegistrar;

defined('ABSPATH') || exit;

/**
 * Class ChangelogPopupService
 */
class ChangelogPopupService
{
    /**
     * Register changelog popup services
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        ChangelogController::register();
        ChangelogAssetsRegistrar::register();
    }

    /**
     * Boot changelog popup services if needed
     *
     * @return void
     * @since 2.0.0
     */
    public static function boot(): void
    {
        // Reserved for future boot logic
    }
}
