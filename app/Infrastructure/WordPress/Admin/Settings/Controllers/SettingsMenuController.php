<?php

namespace Notifal\Infrastructure\WordPress\Admin\Settings\Controllers;

use Notifal\Domain\Settings\Services\SettingsService;
use Notifal\Domain\Settings\Services\SettingsSanitizationService;
use Notifal\Infrastructure\WordPress\Security\NonceManager;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Settings Menu Controller
 *
 * Handles WordPress admin menu registration and page rendering for settings.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SettingsMenuController
{
    /**
     * Settings service instance
     *
     * @var SettingsService
     */
    private $settingsService;

    /**
     * Settings sanitization service instance
     *
     * @var SettingsSanitizationService
     */
    private $sanitizationService;

    /**
     * Nonce manager for security
     *
     * @var NonceManager
     */
    private $nonceManager;

    /**
     * Settings page slug
     *
     * @var string
     */
    private const PAGE_SLUG = 'notifal-settings';

    /**
     * Initialize settings menu controller
     *
     * @param SettingsService $settingsService Settings business logic service
     * @param SettingsSanitizationService $sanitizationService Settings sanitization service
     * @param NonceManager $nonceManager Security nonce manager
     * @since 2.0.0
     */
    public function __construct(
        SettingsService $settingsService,
        SettingsSanitizationService $sanitizationService,
        NonceManager $nonceManager
    ) {
        $this->settingsService = $settingsService;
        $this->sanitizationService = $sanitizationService;
        $this->nonceManager = $nonceManager;
    }

    /**
     * Register menu hooks
     *
     * @since 2.0.0
     * @return void
     */
    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerAdminMenu']);
        add_action('admin_post_notifal_save_settings', [$this, 'handleSettingsSave']);
    }

    /**
     * Register admin menu page
     *
     * Adds "Settings" submenu under main Notifal menu.
     * Ensures proper capabilities and page structure.
     *
     * @return void
     * @since 2.0.0
     */
    public function registerAdminMenu(): void
    {
        add_submenu_page(
            'notifal', // Parent menu slug (main Notifal menu)
            __('Notifal Settings', 'notifal'), // Page title
            __('Settings', 'notifal'), // Menu title
            'manage_options', // Capability required
            self::PAGE_SLUG, // Menu slug
            [$this, 'renderSettingsPage'] // Callback function
        );
    }

    /**
     * Render settings page
     *
     * Displays main settings page with tab navigation.
     * Passes data to view for rendering without business logic.
     *
     * @return void
     * @since 2.0.0
     */
    public function renderSettingsPage(): void
    {
        // Verify user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'notifal'));
        }

        $controller = new self(
            notifal_app(SettingsService::class),
            notifal_app(SettingsSanitizationService::class),
            notifal_app(NonceManager::class)
        );

        $controller->renderPage();
    }

    /**
     * Render the settings page
     *
     * @return void
     * @since 2.0.0
     */
    private function renderPage(): void
    {
        // Get current tab from URL parameter
        $currentTab = $this->getCurrentTab();

        // Prepare data for view
        $viewData = [
            'current_tab' => $currentTab,
            'page_slug' => self::PAGE_SLUG,
            'nonce_action' => 'notifal_settings_save',
            'nonce_name' => 'notifal_settings_nonce',
            'nonce_value' => $this->nonceManager->create('notifal_settings_save'),
            'tag_settings' => $this->settingsService->getTagSettings(),
            'available_tabs' => $this->getAvailableTabs(),
            'woocommerce_active' => PluginDetector::isWooCommerceActive(),
            'notifal_pro_active' => apply_filters('notifal_pro_tags_generator_allowed', false),
        ];

        // Render view without any business logic
        $this->renderView('settings-page', $viewData);
    }

    /**
     * Handle settings form submission
     *
     * Processes POST request from settings form with security validation.
     * Updates settings and redirects with status message.
     *
     * @return void
     * @since 2.0.0
     */
    public function handleSettingsSave(): void
    {
        // Verify user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'notifal'));
        }

        $controller = new self(
            notifal_app(SettingsService::class),
            notifal_app(SettingsSanitizationService::class),
            notifal_app(NonceManager::class)
        );

        $controller->processSettingsSave();
    }

    /**
     * Process the settings save
     *
     * @return void
     * @since 2.0.0
     */
    private function processSettingsSave(): void
    {
        // Verify nonce for security
        if (!$this->nonceManager->verify('notifal_settings_nonce', 'notifal_settings_save')) {
            wp_die(__('Security check failed. Please try again.', 'notifal'));
        }

        // Sanitize and validate form data
        $settings = $this->sanitizationService->sanitizeSettingsInput($_POST);

        // Save settings through service
        $success = $this->sanitizationService->saveSettings($settings);

        // Prepare redirect URL with status
        $redirectUrl = $this->buildRedirectUrl($success);

        // Redirect back to settings page
        wp_redirect($redirectUrl);
        exit;
    }

    /**
     * Get current active tab from URL
     *
     * Determines which settings tab is currently being viewed.
     * Validates tab parameter and provides fallback.
     *
     * @return string Current tab slug
     * @since 2.0.0
     */
    private function getCurrentTab(): string
    {
        $tab = Helper::sanitizeInput($_GET['tab'] ?? 'tags', 'key');
        $availableTabs = array_keys($this->getAvailableTabs());

        return in_array($tab, $availableTabs, true) ? $tab : 'tags';
    }

    /**
     * Get available settings tabs
     *
     * Returns array of available tabs with labels and descriptions.
     * Allows for easy tab management and future expansion.
     *
     * @return array Available tabs array
     * @since 2.0.0
     */
    private function getAvailableTabs(): array
    {
        return [
            'tags' => [
                'label' => __('Tags', 'notifal'),
                'description' => __('Manage tag categories and visibility settings', 'notifal'),
            ],
        ];
    }


    /**
     * Build redirect URL with status message
     *
     * Creates redirect URL with success or error status.
     * Preserves current tab during redirect.
     *
     * @param bool $success Whether save operation succeeded
     * @return string Redirect URL
     * @since 2.0.0
     */
    private function buildRedirectUrl(bool $success): string
    {
        $baseUrl = admin_url('admin.php?page=' . self::PAGE_SLUG);
        $currentTab = $this->getCurrentTab();
        $status = $success ? 'saved' : 'error';

        return add_query_arg([
            'tab' => $currentTab,
            'status' => $status,
        ], $baseUrl);
    }

    /**
     * Render view file with data
     *
     * Includes PHP view file with provided data variables.
     * Implements view rendering without business logic.
     *
     * @param string $view View file name (without .php)
     * @param array $data Data to pass to view
     * @return void
     * @since 2.0.0
     */
    private function renderView(string $view, array $data = []): void
    {
        // Extract data variables for view
        extract($data, EXTR_OVERWRITE);

        // Include view file
        $viewPath = __DIR__ . '/../Views/' . $view . '.php';
        if (file_exists($viewPath)) {
            include $viewPath;
        }
    }
}
