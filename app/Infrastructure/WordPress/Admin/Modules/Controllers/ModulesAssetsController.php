<?php

namespace Notifal\Infrastructure\WordPress\Admin\Modules\Controllers;

use Notifal\Core\Support\Helpers\UrlHelper;
use Notifal\Infrastructure\WordPress\Security\NonceManager;
use Notifal\Shared\Config\Paths;

defined('ABSPATH') || exit;

/**
 * Enqueues assets for the Addons admin page.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ModulesAssetsController
{
    /**
     * Addons page slug fragment used in the admin hook suffix.
     *
     * @var string
     */
    private const PAGE_SLUG = 'notifal-addons';

    /**
     * Register asset hooks.
     *
     * @return void
     * @since 3.0.0
     */
    public static function register(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAdminAssets']);
    }

    /**
     * Enqueue Addons page CSS and JS on the matching screen only.
     *
     * @param string $hook Current admin page hook suffix.
     * @return void
     * @since 3.0.0
     */
    public static function enqueueAdminAssets(string $hook): void
    {
        // Load assets only on the Addons screen.
        if (strpos($hook, self::PAGE_SLUG) === false) {
            return;
        }

        // Built stylesheet from Vite output.
        notifal_enqueue_style(
            'notifal-addons-admin',
            Paths::cssAdminBuildUrl() . 'ModulesAdminStyle.css',
            ['notifal-shared-admin-css']
        );

        // Built script from Vite output, depends on shared toast utilities.
        notifal_enqueue_script(
            'notifal-addons-admin',
            Paths::jsAdminBuildUrl() . 'ModulesAdminScript.js',
            ['notifal-shared-admin-js'],
            [
                'ajaxUrl' => UrlHelper::baseAjax(),
                'nonce'   => NonceManager::create(ModulesAjaxController::NONCE_ACTION),
                'i18n'    => [
                    'enabledSuccess'  => __('Addon enabled successfully.', 'notifal'),
                    'disabledSuccess' => __('Addon disabled successfully.', 'notifal'),
                    'genericError'    => __('Unable to update addon. Please try again.', 'notifal'),
                ],
            ],
            'NotifalAddonsAdmin'
        );
    }
}
