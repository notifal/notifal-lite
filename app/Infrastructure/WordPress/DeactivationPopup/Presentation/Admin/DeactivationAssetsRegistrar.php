<?php
/**
 * Deactivation Assets Registrar
 *
 * Registers CSS and JS assets for the deactivation popup.
 *
 * @package Notifal\Infrastructure\WordPress\DeactivationPopup\Presentation\Admin
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\DeactivationPopup\Presentation\Admin;

use Notifal\Shared\Config\Paths;
use Notifal\Core\Support\Helpers\UrlHelper;
use Notifal\Infrastructure\WordPress\Admin\Localization\LangLoader;

defined('ABSPATH') || exit;

/**
 * Class DeactivationAssetsRegistrar
 */
class DeactivationAssetsRegistrar
{
    /**
     * Register hooks
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        // Create instance for handling callbacks
        $instance = new self();
        add_action('admin_enqueue_scripts', [$instance, 'enqueueAssets']);
    }

    /**
     * Enqueue deactivation popup assets
     *
     * @param string $hook Current admin page hook
     * @return void
     * @since 2.0.0
     */
    public function enqueueAssets(string $hook): void
    {
        // Only load on plugins page
        $current_screen = get_current_screen();
        if (!$current_screen || $current_screen->id !== 'plugins') {
            return;
        }

        // Enqueue CSS from built assets
        notifal_enqueue_style(
            'notifal-deactivation-popup',
            Paths::cssAdminBuildUrl() . 'DeactivationPopupStyle.css',
            []
        );

        // Load JS translations
        $translations = LangLoader::load(__NAMESPACE__);

        // Enqueue JavaScript from built assets with localization
        notifal_enqueue_script(
            'notifal-deactivation-popup',
            Paths::jsAdminBuildUrl() . 'DeactivationPopupScript.js',
            [],
            [
                'ajaxUrl' => UrlHelper::baseAjax(),
                'nonce' => wp_create_nonce('notifal_deactivation_feedback'),
                'skipNonce' => wp_create_nonce('notifal_deactivation_skip'),
                'i18n' => $translations,
            ]
        );
    }


}
