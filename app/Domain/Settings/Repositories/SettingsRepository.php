<?php

namespace Notifal\Domain\Settings\Repositories;

defined('ABSPATH') || exit;

use Notifal\Domain\Settings\Constants\SettingsKeys;

/**
 * Settings data repository
 * 
 * Handles persistent storage of settings using WordPress options API.
 * Provides abstraction layer for settings data access.
 * 
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SettingsRepository
{
    /**
     * WordPress option name for storing settings
     * 
     * @var string
     */
    private const OPTION_NAME = 'notifal_settings';

    /**
     * Get setting value from WordPress options
     *
     * @param string $key Setting key to retrieve
     * @param mixed $default Default value if not found
     * @return mixed Setting value or default
     * @since 2.0.0
     */
    public function get(string $key, $default = null)
    {
        $settings = get_option(self::OPTION_NAME, []);
        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * Store setting value in WordPress options
     *
     * @param string $key Setting key to store
     * @param mixed $value Setting value to store
     * @return bool True if successfully saved
     * @since 2.0.0
     */
    public function set(string $key, $value): bool
    {
        $settings = get_option(self::OPTION_NAME, []);
        $settings[$key] = $value;
        return update_option(self::OPTION_NAME, $settings);
    }

    /**
     * Get all settings from WordPress options
     *
     * @return array Complete settings array
     * @since 2.0.0
     */
    public function getAll(): array
    {
        return get_option(self::OPTION_NAME, []);
    }

    /**
     * Save multiple settings at once
     * 
     * Efficiently updates multiple settings in single database operation.
     * Merges with existing settings to preserve unmodified values.
     * 
     * @param array $settings Associative array of key-value pairs
     * @return bool True if successfully saved
     * @since 2.0.0
     */
    public function setMultiple(array $settings): bool
    {
        $currentSettings = get_option(self::OPTION_NAME, []);
        
        // Merge with new settings
        $updatedSettings = array_merge($currentSettings, $settings);
        
        // Save merged settings
        return update_option(self::OPTION_NAME, $updatedSettings);
    }

    /**
     * Delete specific setting
     * 
     * Removes setting key from WordPress options while preserving others.
     * Used for cleaning up deprecated or unused settings.
     * 
     * @param string $key Setting key to delete
     * @return bool True if successfully deleted
     * @since 2.0.0
     */
    public function delete(string $key): bool
    {
        $settings = get_option(self::OPTION_NAME, []);
        if (isset($settings[$key])) {
            unset($settings[$key]);
            return update_option(self::OPTION_NAME, $settings);
        }
        return true;
    }

    /**
     * Reset all settings to empty state
     *
     * @return bool True if successfully reset
     * @since 2.0.0
     */
    public function reset(): bool
    {
        return delete_option(self::OPTION_NAME);
    }

    /**
     * Check if setting exists
     *
     * @param string $key Setting key to check
     * @return bool True if setting exists
     * @since 2.0.0
     */
    public function exists(string $key): bool
    {
        $settings = get_option(self::OPTION_NAME, []);
        return array_key_exists($key, $settings);
    }

    /**
     * Get default settings structure
     * 
     * Returns array of default setting values for initialization.
     * Used during first installation or settings reset.
     * 
     * @return array Default settings array
     * @since 2.0.0
     */
    public function getDefaults(): array
    {
        return [
            SettingsKeys::USER_TAGS_ENABLED => true,
            SettingsKeys::POST_TAGS_ENABLED => true,
            SettingsKeys::PAGE_TAGS_ENABLED => true,
            // SettingsKeys::COMMENT_TAGS_ENABLED => true, // Moved to Notifal Pro
            SettingsKeys::PRODUCT_TAGS_ENABLED => true,
            SettingsKeys::ORDER_TAGS_ENABLED => true,
        ];
    }

    /**
     * Initialize settings with defaults if not exists
     * 
     * Sets up default settings on first plugin activation.
     * Only creates missing settings, preserves existing values.
     * 
     * @return bool True if initialization successful
     * @since 2.0.0
     */
    public function initializeDefaults(): bool
    {
        $currentSettings = $this->getAll();
        $defaults = $this->getDefaults();
        
        // Only add missing default settings
        $missingSettings = array_diff_key($defaults, $currentSettings);

        if (!empty($missingSettings)) {
            return $this->setMultiple($missingSettings);
        }

        return true;
    }
}
