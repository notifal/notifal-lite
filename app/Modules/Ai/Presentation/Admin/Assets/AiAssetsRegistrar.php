<?php

namespace Notifal\Modules\Ai\Presentation\Admin\Assets;

use Notifal\Modules\Ai\Application\Services\AiPublicConfigService;
use Notifal\Modules\Ai\Config\Paths;
use Notifal\Shared\Config\Paths as SharedPaths;
use Notifal\Shared\Helpers\AdminScreenDetector;

defined('ABSPATH') || exit;

/**
 * Registers shared hosted AI admin assets on Notifal screens.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class AiAssetsRegistrar
{
    /**
     * Register WordPress hooks for AI admin assets.
     *
     * @since 3.0.0
     * @return void
     */
    public static function register(): void
    {
        // Enqueue AI infrastructure only on Notifal admin screens.
        add_action('admin_enqueue_scripts', [self::class, 'enqueue'], 20);
    }

    /**
     * Enqueue shared AI connection and generation helper assets.
     *
     * @since 3.0.0
     * @return void
     */
    public static function enqueue(): void
    {
        // Skip unrelated wp-admin screens for performance.
        if (!AdminScreenDetector::isNotifalPage()) {
            return;
        }

        // Load localized UI strings from the module language file.
        $langPath = Paths::langJsPath() . 'ai-connection.php';
        $strings = file_exists($langPath) ? include $langPath : [];

        // Build the browser-safe public configuration object.
        $config = notifal_app(AiPublicConfigService::class)->build(is_array($strings) ? $strings : []);

        // Enqueue the shared loader stylesheet when not already loaded.
        notifal_enqueue_style(
            'notifal-loader-admin-css',
            SharedPaths::cssAdminBuildUrl() . 'LoaderAdminStyle.css',
            []
        );

        // Enqueue the AI connection stylesheet from the Vite build output.
        notifal_enqueue_style(
            'notifal-ai-connection-css',
            Paths::cssAdminBuildUrl() . 'AiConnectionStyle.css',
            ['notifal-shared-admin-css', 'notifal-loader-admin-css']
        );

        // Enqueue shared hosted AI wizard styles used by modals and builder flows.
        notifal_enqueue_style(
            'notifal-ai-wizard-css',
            Paths::cssAdminBuildUrl() . 'AiWizardStyle.css',
            ['notifal-ai-connection-css']
        );

        // Enqueue the AI connection JavaScript bundle from the Vite build output.
        notifal_enqueue_script(
            'notifal-ai-connection-js',
            Paths::jsAdminBuildUrl() . 'AiConnectionScript.js',
            ['notifal-shared-admin-js'],
            $config,
            'NotifalAi'
        );
    }
}
