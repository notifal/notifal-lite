<?php

namespace Notifal\Infrastructure\WordPress\Admin\Settings\Controllers\Ajax;

use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Domain\Settings\Services\SettingsService;
use Notifal\Domain\Settings\Services\SettingsSanitizationService;
use Notifal\Infrastructure\WordPress\Admin\Settings\Services\PostTypeDiscoveryService;
use Notifal\Infrastructure\WordPress\Security\NonceManager;

defined('ABSPATH') || exit;

/**
 * Settings AJAX Controller
 *
 * Handles AJAX requests for settings operations including save, post type discovery,
 * and generated tags management.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SettingsAjaxController
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
     * Post type discovery service instance
     *
     * @var PostTypeDiscoveryService
     */
    private $postTypeDiscoveryService;

    /**
     * Nonce manager for security
     *
     * @var NonceManager
     */
    private $nonceManager;

    /**
     * Initialize settings AJAX controller
     *
     * @param SettingsService $settingsService Settings business logic service
     * @param SettingsSanitizationService $sanitizationService Settings sanitization service
     * @param PostTypeDiscoveryService $postTypeDiscoveryService Post type discovery service
     * @param NonceManager $nonceManager Security nonce manager
     * @since 2.0.0
     */
    public function __construct(
        SettingsService $settingsService,
        SettingsSanitizationService $sanitizationService,
        PostTypeDiscoveryService $postTypeDiscoveryService,
        NonceManager $nonceManager
    ) {
        $this->settingsService = $settingsService;
        $this->sanitizationService = $sanitizationService;
        $this->postTypeDiscoveryService = $postTypeDiscoveryService;
        $this->nonceManager = $nonceManager;
    }

    /**
     * Register AJAX handlers
     *
     * @since 2.0.0
     * @return void
     */
    public function register(): void
    {
        add_action('wp_ajax_notifal_save_settings', [$this, 'handleSaveSettings']);
        add_action('wp_ajax_notifal_reset_settings', [$this, 'handleResetSettings']);
        add_action('wp_ajax_notifal_get_post_types', [$this, 'handleGetPostTypes']);

        // Only register pro-only handlers if pro is not active
        // Pro plugin will register its own handlers when active
        if (!apply_filters('notifal_pro_tags_generator_allowed', false)) {
            add_action('wp_ajax_notifal_save_generated_tags', [self::class, 'handleSaveGeneratedTags']);
            add_action('wp_ajax_notifal_remove_generated_tags', [self::class, 'handleRemoveGeneratedTags']);
            add_action('wp_ajax_notifal_get_generated_tags_status', [self::class, 'handleGetGeneratedTagsStatus']);
            add_action('wp_ajax_notifal_get_generated_tags_for_posttype', [self::class, 'handleGetGeneratedTagsForPostType']);
        }
    }

    /**
     * Handle AJAX settings save request
     *
     * Processes AJAX request for settings save with JSON response.
     * Provides better user experience with instant feedback.
     *
     * @return void
     * @since 2.0.0
     */
    public function handleSaveSettings(): void
    {
        try {
            // Verify AJAX request with nonce and capabilities
            notifal_verify_ajax_request('notifal_settings_ajax', 'manage_options');

            // Sanitize and validate form data
            $settings = $this->sanitizationService->sanitizeSettingsInput($_POST);

            // Save settings through service
            $success = $this->sanitizationService->saveSettings($settings);

            if ($success) {
                notifal_json_success($settings, __('Settings saved successfully!', 'notifal'));
            } else {
                notifal_json_error(__('Error saving settings. Please try again.', 'notifal'));
            }
        } catch (\Exception $e) {
            notifal_json_error(__('An error occurred while saving settings.', 'notifal'));
        }
    }

    /**
     * Handle AJAX settings reset request
     *
     * Processes AJAX request for settings reset to defaults with JSON response.
     * Provides instant feedback for reset operations.
     *
     * @return void
     * @since 2.0.0
     */
    public function handleResetSettings(): void
    {
        try {
            // Verify AJAX request with nonce and capabilities
            notifal_verify_ajax_request('notifal_settings_ajax', 'manage_options');

            // Reset settings to defaults through service
            $success = $this->settingsService->resetToDefaults();

            if ($success) {
                notifal_json_success([], __('Settings reset to defaults successfully!', 'notifal'));
            } else {
                notifal_json_error(__('Error resetting settings. Please try again.', 'notifal'));
            }
        } catch (\Exception $e) {
            notifal_json_error(__('An error occurred while resetting settings.', 'notifal'));
        }
    }

    /**
     * Handle AJAX request for post types discovery
     *
     * Discovers and returns all available post types with metadata for tag generation.
     * Includes WordPress core, WooCommerce, and custom post types.
     *
     * @return void
     * @since 2.0.0
     */
    public function handleGetPostTypes(): void
    {
        try {
            // Verify AJAX request with nonce and capabilities
            notifal_verify_ajax_request('notifal_settings_ajax', 'manage_options');

            // Get all registered post types
            $postTypes = $this->postTypeDiscoveryService->discoverPostTypes();

            notifal_json_success([
                'post_types' => $postTypes,
                'message' => sprintf(__('Found %d post types', 'notifal'), count($postTypes))
            ]);
        } catch (\Exception $e) {
            notifal_json_error(__('Error discovering post types. Please try again.', 'notifal'));
        }
    }

    /**
     * Handle AJAX request to save generated tags
     *
     * Checks for pro license and delegates to pro plugin for tag generation.
     *
     * @return void
     * @since 2.0.0
     */
    public static function handleSaveGeneratedTags(): void
    {
        self::handleProOnlyFeature();
    }

    /**
     * Handle AJAX request to remove generated tags for a post type
     *
     * Removes all generated tags for a specific post type.
     *
     * @return void
     * @since 2.0.0
     */
    public static function handleRemoveGeneratedTags(): void
    {
        self::handleProOnlyFeature();
    }

    /**
     * Handle AJAX request to get generated tags status
     *
     * Returns information about which post types have generated tags.
     *
     * @return void
     * @since 2.0.0
     */
    public static function handleGetGeneratedTagsStatus(): void
    {
        self::handleProOnlyFeature();
    }

    /**
     * Handle AJAX request to get generated tags for a specific post type
     *
     * Returns the list of generated tags for viewing purposes.
     *
     * @return void
     * @since 2.0.0
     */
    public static function handleGetGeneratedTagsForPostType(): void
    {
        self::handleProOnlyFeature();
    }


    /**
     * Handle pro-only features
     *
     * Checks for Notifal Pro license and returns appropriate error response.
     * Used by all tag generation related AJAX endpoints.
     *
     * @return void
     * @since 2.0.0
     */
    private static function handleProOnlyFeature(): void
    {
        // Check if Notifal Pro is active and licensed first
        // Use secure hook that only the legitimate pro plugin can provide
        if (!apply_filters('notifal_pro_tags_generator_allowed', false)) {
            notifal_json_error(__('This feature requires Notifal Pro. Please activate your license to use tag generation features.', 'notifal'), [
                'pro_required' => true,
                'upgrade_url' => function_exists('notifal_pro_get_upgrade_url') ? notifal_pro_get_upgrade_url('tag_generation') : Urls::getUpgradeUrl('tag_generation')
            ]);
            return;
        }

        return;
    }
}
