<?php
namespace Notifal\Modules\SetupWizard\Presentation\Admin\Assets;
use Notifal\Modules\Ai\Config\Paths as AiPaths;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardConfigService;
use Notifal\Modules\SetupWizard\Config\Paths;
use Notifal\Modules\SetupWizard\Presentation\Admin\Menu\MenuController;
use Notifal\Shared\Config\Paths as SharedPaths;
defined('ABSPATH') || exit;
/**
 * Registers Setup Wizard admin assets on the wizard screen only.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardAssets
{
    /**
     * Register asset hooks.
     *
     * @since 3.0.0
     * @return void
     */
    public static function register(): void
    {
        // Enqueue wizard assets only on the dedicated admin screen.
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }
    /**
     * Enqueue Setup Wizard styles and scripts.
     *
     * @since 3.0.0
     * @param string $hook Current admin hook suffix.
     * @return void
     */
    public static function enqueue(string $hook): void
    {
        // Load assets only on the fullscreen wizard screen.
        if ('notifal_page_' . MenuController::PAGE_SLUG !== $hook) {
            return;
        }
        // Load localized UI strings from the module language file.
        $langPath = Paths::langJsPath() . 'setup-wizard.php';
        $strings = file_exists($langPath) ? include $langPath : [];
        // Build the browser-safe boot configuration object.
        $config = notifal_app(SetupWizardConfigService::class)->build(is_array($strings) ? $strings : []);
        // Enqueue shared loader styles when not already loaded.
        notifal_enqueue_style(
            'notifal-loader-admin-css',
            SharedPaths::cssAdminBuildUrl() . 'LoaderAdminStyle.css',
            []
        );
        // Enqueue hosted AI connection styles for the Connect step.
        notifal_enqueue_style(
            'notifal-ai-connection-css',
            AiPaths::cssAdminBuildUrl() . 'AiConnectionStyle.css',
            ['notifal-shared-admin-css', 'notifal-loader-admin-css']
        );
        // Reuse hosted AI wizard shell styles for Noti chrome consistency.
        notifal_enqueue_style(
            'notifal-ai-wizard-css',
            AiPaths::cssAdminBuildUrl() . 'AiWizardStyle.css',
            ['notifal-ai-connection-css']
        );
        // Reuse OnPage AI hosted shell form and palette styles from the shared Vite build.
        notifal_enqueue_style(
            'notifal-onpage-ai-prompt-css',
            SharedPaths::cssAdminBuildUrl() . 'OnPageAiPromptStyle.css',
            ['notifal-ai-wizard-css']
        );
        // Enqueue the Setup Wizard stylesheet from the Vite build output.
        notifal_enqueue_style(
            'notifal-setup-wizard-css',
            Paths::cssAdminBuildUrl() . 'SetupWizardStyle.css',
            ['notifal-onpage-ai-prompt-css']
        );
        // Enqueue shared hosted AI client infrastructure for connect and generation.
        notifal_enqueue_script(
            'notifal-ai-connection-js',
            AiPaths::jsAdminBuildUrl() . 'AiConnectionScript.js',
            ['notifal-shared-admin-js'],
            $config['ai'] ?? [],
            'NotifalAi'
        );
        // Enqueue the Setup Wizard JavaScript bundle from the Vite build output.
        notifal_enqueue_script(
            'notifal-setup-wizard-js',
            Paths::jsAdminBuildUrl() . 'SetupWizardScript.js',
            ['notifal-shared-admin-js', 'notifal-ai-connection-js'],
            $config,
            'NotifalSetupWizard'
        );
    }
}
