<?php

namespace Notifal\Domain\Settings\Services;

defined('ABSPATH') || exit;

use Notifal\Domain\Settings\Repositories\SettingsRepository;
use Notifal\Domain\Settings\Constants\SettingsKeys;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Shared\Utils\Helper;

/**
 * Core settings management service
 * 
 * Handles all settings operations across Notifal modules and features.
 * Provides centralized settings management with WordPress integration.
 * 
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SettingsService
{
    /**
     * Settings repository instance
     * 
     * @var SettingsRepository
     */
    private $repository;

    /**
     * Initialize settings service
     * 
     * @param SettingsRepository $repository Settings data repository
     * @since 2.0.0
     */
    public function __construct(SettingsRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get a setting value with optional default
     * 
     * Retrieves setting value and applies WordPress filter for extensibility.
     * Returns default value if setting doesn't exist.
     * 
     * @param string $key Setting key to retrieve
     * @param mixed $default Default value if setting not found
     * @return mixed Setting value or default
     * @since 2.0.0
     */
    public function get(string $key, $default = null)
    {
        // Get value from repository
        $value = $this->repository->get($key, $default);
        
        // Apply WordPress filter for extensibility
        return apply_filters('notifal_setting_retrieved', $value, $key);
    }

    /**
     * Set a setting value
     * 
     * Stores setting value and triggers WordPress action for notifications.
     * Validates setting key format before storage.
     * 
     * @param string $key Setting key to store
     * @param mixed $value Setting value to store
     * @return bool True if setting saved successfully
     * @since 2.0.0
     */
    public function set(string $key, $value): bool
    {
        // Validate setting key format
        if (empty($key) || !is_string($key)) {
            return false;
        }

        // Store value in repository
        $result = $this->repository->set($key, $value);
        
        // Trigger WordPress action for notifications
        if ($result) {
            do_action(ActionHooks::SETTING_UPDATED, $key, $value);
        }
        
        return $result;
    }

    /**
     * Get all tag-related settings
     *
     * Returns all settings that control tag visibility and behavior.
     * Used by tag system to determine which tags to display.
     *
     * @return array Tag settings array
     * @since 2.0.0
     */
    public function getTagSettings(): array
    {
        $defaults = SettingsKeys::getDefaultTagSettings();
        $settings = [];

        foreach ($defaults as $key => $defaultValue) {
            $settings[$key] = $this->get($key, $defaultValue);
        }

        // Add custom post type tag settings dynamically
        $this->addCustomPostTypeTagSettings($settings);

        return $settings;
    }

    /**
     * Get valid generated post types for tag settings
     *
     * Retrieves and validates post types that have generated tags.
     * Used by both settings retrieval and sanitization processes.
     *
     * @return array Array of valid post type names
     * @since 2.0.0
     */
    public function getValidGeneratedPostTypes(): array
    {
        $generatedPostTypes = $this->get('generated_posttype_list', []);

        if (empty($generatedPostTypes) || !is_array($generatedPostTypes)) {
            return [];
        }

        $validPostTypes = [];
        foreach ($generatedPostTypes as $postType) {
            // Validate post type name format
            if (!empty($postType) && preg_match('/^[a-zA-Z0-9_-]+$/', $postType)) {
                $validPostTypes[] = $postType;
            }
        }

        return $validPostTypes;
    }

    /**
     * Add custom post type tag settings to the settings array
     *
     * Dynamically adds settings for each custom post type that has generated tags.
     *
     * @param array &$settings Reference to settings array to update
     * @return void
     * @since 2.0.0
     */
    private function addCustomPostTypeTagSettings(array &$settings): void
    {
        $validPostTypes = $this->getValidGeneratedPostTypes();

        // Add setting for each generated post type
        foreach ($validPostTypes as $postType) {
            $settingKey = Helper::sanitizeInput($postType . '_tags_enabled', 'key');

            // Default to enabled for newly generated tags to ensure visibility
            $settings[$settingKey] = $this->get($settingKey, true);
        }
    }

    /**
     * Check if specific tag category is enabled
     * 
     * Determines if a tag category should be available in tag selectors.
     * Includes plugin dependency checks (e.g., WooCommerce for product/order tags).
     * 
     * @param string $category Tag category to check
     * @return bool True if tag category is enabled and available
     * @since 2.0.0
     */
    public function isTagCategoryEnabled(string $category): bool
    {
        // Get base setting value
        $settingKey = $category . '_tags_enabled';
        $isEnabled = $this->get($settingKey, true);
        
        // Check plugin dependencies
        if (!$isEnabled) {
            return false;
        }
        
        // Check WooCommerce dependency for product/order tags
        if (in_array($category, ['product', 'order']) && !PluginDetector::isWooCommerceActive()) {
            return false;
        }
        
        return true;
    }

    /**
     * Get all settings as array
     * 
     * Returns all stored settings for backup or export purposes.
     * Useful for debugging and settings transfer.
     * 
     * @return array All settings data
     * @since 2.0.0
     */
    public function getAllSettings(): array
    {
        return $this->repository->getAll();
    }

    /**
     * Reset settings to defaults
     * 
     * Restores all settings to their default values.
     * Triggers action to notify other components of reset.
     * 
     * @return bool True if reset successful
     * @since 2.0.0
     */
    public function resetToDefaults(): bool
    {
        $result = $this->repository->reset();
        
        if ($result) {
            do_action(ActionHooks::SETTINGS_RESET);
        }
        
        return $result;
    }
    
    /**
     * Check if a setting exists
     * 
     * @param string $key Setting key to check
     * @return bool True if setting exists
     * @since 2.0.0
     */
    public function has(string $key): bool
    {
        return $this->repository->exists($key);
    }
}
