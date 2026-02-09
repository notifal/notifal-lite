<?php
/**
 * What's New Assets Registrar
 *
 * Registers CSS and JavaScript assets for the what's new popup.
 *
 * @package Notifal\Infrastructure\WordPress\WhatsNewPopup\Presentation\Admin
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\WhatsNewPopup\Presentation\Admin;

use Notifal\Core\Support\Helpers\UrlHelper;
use Notifal\Infrastructure\WordPress\Security\NonceManager;
use Notifal\Infrastructure\WordPress\WhatsNewPopup\Domain\WhatsNewPopup;
use Notifal\Shared\Config\Paths;
use Notifal\Shared\Helpers\AdminScreenDetector;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class WhatsNewAssetsRegistrar
 */
class WhatsNewAssetsRegistrar
{
    /**
     * Register assets hooks
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        $instance = new self();
        add_action('admin_enqueue_scripts', [$instance, 'enqueueAssets']);
    }

    /**
     * Enqueue CSS and JavaScript assets
     *
     * Always enqueues assets on Notifal pages (for manual triggering).
     * For important updates, also enqueues on plugins.php page.
     *
     * @param string $hook Current admin page hook
     * @return void
     * @since 2.0.0
     */
    public function enqueueAssets(string $hook): void
    {
        // Determine if we should enqueue assets
        $should_enqueue = false;

        // Always enqueue on Notifal pages (for manual triggering via sticky menu)
        if (AdminScreenDetector::isNotifalPage()) {
            $should_enqueue = true;
        } else {
            // For important updates, also enqueue on plugins.php page
            $whatsnew_popup = new WhatsNewPopup();
            if ($whatsnew_popup->isImportantUpdate()) {
                // Check if we're on plugins.php page
                if ($hook === 'plugins.php') {
                    $should_enqueue = true;
                }
            }
        }

        // Exit if we shouldn't enqueue on this page
        if (!$should_enqueue) {
            return;
        }

        // Enqueue CSS
        notifal_enqueue_style(
            'notifal-whatsnew-popup',
            Paths::cssAdminBuildUrl() . 'WhatsNewPopupStyle.css',
            []
        );

        // Enqueue JavaScript
        notifal_enqueue_script(
            'notifal-whatsnew-popup',
            Paths::jsAdminBuildUrl() . 'WhatsNewPopupScript.js',
            [],
            [
                'ajaxUrl' => UrlHelper::baseAjax(),
                'nonce' => NonceManager::create('notifal_whatsnew_popup'),
            ],
            'notifalWhatsNewPopup'
        );
    }
}
