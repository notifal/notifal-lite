<?php

namespace Notifal\Domain\Settings\Models;

defined('ABSPATH') || exit;

use Notifal\Domain\Settings\Constants\SettingsKeys;

/**
 * Settings data model
 * 
 * Represents settings data structure and provides validation.
 * Ensures data integrity and type safety for settings operations.
 * 
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SettingsModel
{
    /**
     * Tag category settings
     * 
     * @var array
     */
    private $tagSettings;

    /**
     * OnPage notification settings
     * 
     * @var array
     */
    private $onPageSettings;

    /**
     * Template system settings
     * 
     * @var array
     */
    private $templateSettings;

    /**
     * Initialize settings model
     * 
     * Creates model instance with default or provided settings data.
     * Validates and normalizes settings structure.
     * 
     * @param array $data Optional settings data to initialize with
     * @since 2.0.0
     */
    public function __construct(array $data = [])
    {
        $this->initializeTagSettings($data);
        $this->initializeOnPageSettings($data);
        $this->initializeTemplateSettings($data);
    }

    /**
     * Initialize tag settings with validation
     *
     * Sets up tag category enable/disable settings with proper defaults.
     * Validates each setting value to ensure boolean type.
     *
     * @param array $data Raw settings data
     * @return void
     * @since 2.0.0
     */
    private function initializeTagSettings(array $data): void
    {
        $defaults = SettingsKeys::getDefaultTagSettings();
        $this->tagSettings = [];

        foreach ($defaults as $key => $defaultValue) {
            $this->tagSettings[$key] = $this->validateBooleanSetting(
                $data[$key] ?? $defaultValue
            );
        }
    }

    /**
     * Initialize OnPage notification settings
     * 
     * Placeholder for future OnPage notification settings.
     * Will be expanded when OnPage settings are implemented.
     * 
     * @param array $data Raw settings data
     * @return void
     * @since 2.0.0
     */
    private function initializeOnPageSettings(array $data): void
    {
        $this->onPageSettings = [
            // Future OnPage settings will be added here
            // 'animation_enabled' => true,
            // 'sound_enabled' => true,
        ];
    }

    /**
     * Initialize template system settings
     * 
     * Placeholder for future template system settings.
     * Will be expanded when template settings are implemented.
     * 
     * @param array $data Raw settings data
     * @return void
     * @since 2.0.0
     */
    private function initializeTemplateSettings(array $data): void
    {
        $this->templateSettings = [
            // Future template settings will be added here
            // 'cache_enabled' => true,
            // 'compression_enabled' => false,
        ];
    }

    /**
     * Get tag settings array
     * 
     * Returns all tag-related settings for use by tag system.
     * Includes validation and plugin dependency checks.
     * 
     * @return array Tag settings data
     * @since 2.0.0
     */
    public function getTagSettings(): array
    {
        return $this->tagSettings;
    }

    /**
     * Get OnPage settings array
     * 
     * Returns all OnPage notification settings.
     * Currently placeholder for future implementation.
     * 
     * @return array OnPage settings data
     * @since 2.0.0
     */
    public function getOnPageSettings(): array
    {
        return $this->onPageSettings;
    }

    /**
     * Get template settings array
     * 
     * Returns all template system settings.
     * Currently placeholder for future implementation.
     * 
     * @return array Template settings data
     * @since 2.0.0
     */
    public function getTemplateSettings(): array
    {
        return $this->templateSettings;
    }

    /**
     * Get all settings as flat array
     * 
     * Returns complete settings data suitable for storage.
     * Flattens nested structure into key-value pairs.
     * 
     * @return array Flat settings array
     * @since 2.0.0
     */
    public function toArray(): array
    {
        return array_merge(
            $this->flattenSettings($this->tagSettings, ''),
            $this->flattenSettings($this->onPageSettings, SettingsKeys::ONPAGE_PREFIX),
            $this->flattenSettings($this->templateSettings, SettingsKeys::TEMPLATES_PREFIX)
        );
    }

    /**
     * Validate boolean setting value
     * 
     * Ensures setting value is proper boolean type.
     * Converts string representations to boolean.
     * 
     * @param mixed $value Setting value to validate
     * @return bool Validated boolean value
     * @since 2.0.0
     */
    private function validateBooleanSetting($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        return false; // Default to false for invalid values
    }

    /**
     * Flatten settings array with prefix
     * 
     * Converts nested settings structure to flat key-value pairs.
     * Applies optional prefix for namespacing.
     * 
     * @param array $settings Settings array to flatten
     * @param string $prefix Optional prefix for keys
     * @return array Flattened settings array
     * @since 2.0.0
     */
    private function flattenSettings(array $settings, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($settings as $key => $value) {
            $fullKey = $prefix . $key;
            $flattened[$fullKey] = $value;
        }

        return $flattened;
    }

    /**
     * Validate settings data structure
     * 
     * Checks if provided data contains valid settings keys and values.
     * Returns validation errors if any found.
     * 
     * @param array $data Settings data to validate
     * @return array Validation errors (empty if valid)
     * @since 2.0.0
     */
    public static function validate(array $data): array
    {
        $errors = [];

        // Validate tag settings
        foreach (SettingsKeys::getTagKeys() as $key) {
            if (isset($data[$key]) && !is_bool($data[$key])) {
                $errors[] = sprintf('Setting "%s" must be boolean value', $key);
            }
        }

        return $errors;
    }

    /**
     * Create model from flat settings array
     * 
     * Factory method to create SettingsModel from stored data.
     * Handles data transformation and validation.
     * 
     * @param array $flatData Flat settings array from storage
     * @return self New SettingsModel instance
     * @since 2.0.0
     */
    public static function fromArray(array $flatData): self
    {
        // Transform flat data to nested structure if needed
        // For now, we keep it simple as our settings are mostly flat
        return new self($flatData);
    }
}
