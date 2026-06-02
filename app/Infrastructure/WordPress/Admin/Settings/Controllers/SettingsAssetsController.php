<?php

namespace Notifal\Infrastructure\WordPress\Admin\Settings\Controllers;

use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Domain\Settings\Services\SettingsService;
use Notifal\Infrastructure\WordPress\Admin\Localization\LangLoader;
use Notifal\Infrastructure\WordPress\Security\NonceManager;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\UrlService;
use Notifal\Shared\Config\Paths;

defined('ABSPATH') || exit;

/**
 * Settings Assets Controller
 *
 * Handles asset enqueuing and plugin page links for the settings module.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SettingsAssetsController
{
    /**
     * Settings service instance
     *
     * @var SettingsService
     */
    private $settingsService;

    /**
     * Nonce manager for security
     *
     * @var NonceManager
     */
    private $nonceManager;

    /**
     * OnPage notification URL service
     *
     * @var UrlService
     */
    private $urlService;

    /**
     * Settings page slug
     *
     * @var string
     */
    private const PAGE_SLUG = 'notifal-settings';

    /**
     * Initialize settings assets controller
     *
     * @param SettingsService $settingsService Settings business logic service
     * @param NonceManager $nonceManager Security nonce manager
     * @param UrlService $urlService OnPage notification URL service
     * @since 2.0.0
     */
    public function __construct(SettingsService $settingsService, NonceManager $nonceManager, UrlService $urlService)
    {
        $this->settingsService = $settingsService;
        $this->nonceManager = $nonceManager;
        $this->urlService = $urlService;
    }

    /**
     * Register assets and plugin links hooks
     *
     * @since 2.0.0
     * @return void
     */
    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_filter('plugin_action_links_notifal/notifal.php', [$this, 'addSettingsLink']);
        add_filter('plugin_row_meta', [$this, 'addPluginRowMeta'], 10, 2);
    }

    /**
     * Enqueue admin assets for settings page
     *
     * Uses Vite build system for asset management.
     *
     * @param string $hook Current admin page hook
     * @return void
     * @since 2.0.0
     */
    public function enqueueAdminAssets(string $hook): void
    {
        // Only load on settings page
        if (!$this->isSettingsPage($hook)) {
            return;
        }

        $this->enqueueAssets();
    }

    /**
     * Enqueue the actual assets
     *
     * @return void
     * @since 2.0.0
     */
    private function enqueueAssets(): void
    {
        // Enqueue settings CSS
        notifal_enqueue_style(
            'notifal-settings-admin',
            Paths::cssAdminBuildUrl() . 'SettingsAdminStyle.css',
            ['notifal-shared-admin-css'] // Depend on globally loaded shared styles
        );

        // Load localized strings for JavaScript
        $translations = LangLoader::load(__NAMESPACE__);

        // Enqueue settings JavaScript with translations
        notifal_enqueue_script(
            'notifal-settings-admin',
            Paths::jsAdminBuildUrl() . 'SettingsAdminScript.js',
            [],
            [
                'ajax_url' => \Notifal\Core\Support\Helpers\UrlHelper::baseAjax(),
                'nonce' => $this->nonceManager->create('notifal_settings_ajax'),
                'tag_settings' => $this->settingsService->getTagSettings(),
                'strings' => $translations
            ],
            'notifal_settings'
        );
    }


    /**
     * Add settings and other links to plugin actions
     *
     * Adds "Resources", "Settings", and "OnPageNotifications" links to plugin list page.
     *
     * @param array $links Existing plugin action links
     * @return array Modified links array
     * @since 2.0.0
     */
    public function addSettingsLink(array $links): array
    {
        // Add Resources link
        $resourcesUrl = Urls::KNOWLEDGE_BASE;
        $resourcesLink = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url($resourcesUrl),
            esc_html__('Resources', 'notifal')
        );

        // Add Settings link
        $settingsUrl = admin_url('admin.php?page=' . self::PAGE_SLUG);
        $settingsLink = sprintf(
            '<a href="%s">%s</a>',
            esc_url($settingsUrl),
            esc_html__('Settings', 'notifal')
        );

        // Add OnPageNotifications link
        $onpageUrl = $this->urlService->getListUrl();
        $onpageLink = sprintf(
            '<a href="%s">%s</a>',
            esc_url($onpageUrl),
            esc_html__('OnPageNotifications', 'notifal')
        );

        // Prepend links to existing links
        array_unshift($links, $onpageLink);
        array_unshift($links, $settingsLink);
        array_unshift($links, $resourcesLink);

        return $links;
    }

    /**
     * Add row meta links below plugin name
     *
     * Adds "Free Configuration" for all installs and "Upgrade for Free" when Pro is not installed.
     *
     * @param array $links Existing plugin row meta links
     * @param string $file Plugin file name
     * @return array Modified links array
     * @since 2.0.0
     * @since 2.3.5 Adds Free Configuration row-meta link (Urls::FREE_CONFIGURATION) with plugin_row_meta UTM tracking.
     */
    public function addPluginRowMeta(array $links, string $file): array
    {
        if ($file !== 'notifal/notifal.php') {
            return $links;
        }

        $free_configuration_url = Urls::withCustomUtm(Urls::FREE_CONFIGURATION, [
            'utm_medium' => 'plugin_row_meta',
            'utm_campaign' => 'notifal_free_configuration',
            'utm_content' => 'free_configuration_row_meta_link',
        ]);

        $links[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer" class="notifal-menu-free-configuration">%s</a>',
            esc_url($free_configuration_url),
            esc_html__('Free Configuration', 'notifal')
        );

        // Show upgrade CTA only when Pro plugin files are not present on disk (not merely deactivated).
        if (!PluginDetector::isNotifalProInstalled()) {
            // License manager link for free upgrade flow (same destination as admin menu upgrade item).
            $upgrade_for_free_url = Urls::withCustomUtm(Urls::LICENSE_MANAGER, [
                'utm_medium' => 'plugin_row_meta',
                'utm_campaign' => 'notifal_pro_upgrade',
                'utm_content' => 'upgrade_for_free_row_meta_link',
            ]);

            $links[] = sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer" class="notifal-menu-upgrade-btn">%s</a>',
                esc_url($upgrade_for_free_url),
                esc_html__('Upgrade for Free', 'notifal')
            );
        }

        return $links;
    }

    /**
     * Check if current page is settings page
     *
     * Determines if assets should be loaded on current admin page.
     * Prevents unnecessary asset loading on other pages.
     *
     * @param string $hook Current admin page hook
     * @return bool True if on settings page
     * @since 2.0.0
     */
    private static function isSettingsPage(string $hook): bool
    {
        return strpos($hook, self::PAGE_SLUG) !== false;
    }

}
