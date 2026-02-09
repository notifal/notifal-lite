<?php

namespace Notifal\Modules\Templates\Presentation\Admin\Assets;
use Notifal\Infrastructure\WordPress\Admin\Localization\LangLoader;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Security\NonceManager;
use Notifal\Shared\Config\Paths;
use Notifal\Core\Support\Helpers\UrlHelper;

defined('ABSPATH') || exit;

/**
 * Class EditorAssets
 * Handles enqueueing admin styles and scripts for Templates module.
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Presentation\Admin\Assets
 */
class EditorAssets
{
    /**
     * Register WordPress hook.
     *
     * @since 2.0.0
     */
    public static function register(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    /**
     * Enqueue styles and scripts for notifal_templates admin screen.
     *
     * @since 2.0.0
     */
    public static function enqueue(): void
    {
        $screen = get_current_screen();

        // Only enqueue on the Templates admin page
        if (! $screen || $screen->base !== 'notifal_page_notifal_templates') {
            return;
        }

        /**
         * Fires before enqueuing Notifal Templates admin assets.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::TEMPLATES_ADMIN_ASSETS_BEFORE);

        $cssUrl    = Paths::cssAdminBuildUrl();
        $jsUrl     = Paths::jsAdminBuildUrl();
        $version   = NOTIFAL_VERSION;

        // Enqueue WordPress media scripts for media upload functionality
        wp_enqueue_media();

        // Load translations
        $translations = LangLoader::load(__NAMESPACE__);
        $translations['templates_page_url'] = admin_url('admin.php?page=notifal_templates');

        // Enqueue CSS
        notifal_enqueue_style(
            'notifal-templates-admin-style',
            $cssUrl . 'TemplatesAdminStyle.css',
            []
        );

        // Enqueue JS
        notifal_enqueue_script(
            'notifal-templates-admin-script',
            $jsUrl . 'TemplatesAdminScript.js',
            ['notifal-shared-admin-js'],
            $translations,
            'NotifalTemplateStrings'
        );

        wp_localize_script('notifal-templates-admin-script', 'NotifalTemplatesAjax', self::getAjaxConfig());

        /**
         * Fires after enqueuing Notifal Templates admin assets.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::TEMPLATES_ADMIN_ASSETS_AFTER);
    }

    /**
     * Get AJAX configuration for JavaScript.
     *
     * @return array AJAX configuration data
     * @since 2.0.0
     */
    private static function getAjaxConfig(): array
    {
        return [
            'nonce' => [
                'import' => NonceManager::create('notifal_import_ajax_nonce'),
            ],
            'ajax_url' => UrlHelper::baseAjax(),
        ];
    }
}
