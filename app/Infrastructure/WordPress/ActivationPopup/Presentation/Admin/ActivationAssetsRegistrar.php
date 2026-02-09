<?php
/**
 * Activation Assets Registrar
 *
 * Registers CSS and JavaScript assets for the activation popup.
 *
 * @package Notifal\Infrastructure\WordPress\ActivationPopup\Presentation\Admin
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\ActivationPopup\Presentation\Admin;

use Notifal\Core\Support\Helpers\UrlHelper;
use Notifal\Infrastructure\WordPress\ActivationPopup\Domain\ActivationPopup;
use Notifal\Shared\Config\Paths;
use Notifal\Shared\Helpers\AdminScreenDetector;

defined('ABSPATH') || exit;

/**
 * Class ActivationAssetsRegistrar
 */
class ActivationAssetsRegistrar
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
     * @param string $hook Current admin page hook
     * @return void
     * @since 2.0.0
     */
    public function enqueueAssets(string $hook): void
    {
        $activation_popup = new ActivationPopup();

        // Only enqueue if popup should be shown
        if (!$activation_popup->shouldShowActivationPopup()) {
            return;
        }

        // Check if we should enqueue assets based on current context
        $should_enqueue = false;

        // Scenario 1: On Notifal pages (original logic)
        if (strpos($hook, 'toplevel_page_notifal') !== false ||
            strpos($hook, 'notifal_page_') !== false) {
            $should_enqueue = true;
        }
        // Scenario 2: Manual activation parameter on non-Notifal pages
        elseif (isset($_GET['notifal_activation']) && $_GET['notifal_activation'] === 'true' &&
                !AdminScreenDetector::isNotifalPage()) {
            $should_enqueue = true;
        }

        if (!$should_enqueue) {
            return;
        }

        // Enqueue CSS
        notifal_enqueue_style(
            'notifal-activation-popup',
            Paths::cssAdminBuildUrl() . 'ActivationPopupStyle.css',
            []
        );

        // Enqueue JavaScript
        notifal_enqueue_script(
            'notifal-activation-popup',
            Paths::jsAdminBuildUrl() . 'ActivationPopupScript.js',
            ['jquery'],
            [
                'ajaxUrl' => UrlHelper::baseAjax(),
                'nonce' => wp_create_nonce('notifal_activation_popup'),
            ]
        );
    }
}
