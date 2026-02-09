<?php

namespace Notifal\Domain\Settings\Services;

use Notifal\Domain\Settings\Constants\SettingsKeys;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Settings sanitization and validation service
 *
 * Handles all settings input sanitization, validation, and processing.
 * Provides centralized logic for both AJAX and form-based settings operations.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SettingsSanitizationService
{
    /**
     * Settings service instance
     *
     * @var SettingsService
     */
    private $settingsService;

    /**
     * Initialize settings sanitization service
     *
     * @param SettingsService $settingsService Settings business logic service
     * @since 2.0.0
     */
    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Sanitize settings form input
     *
     * Cleans and validates all form input data.
     * Ensures data safety and prevents injection attacks.
     *
     * @param array $input Raw form input data
     * @return array Sanitized settings data
     * @since 2.0.0
     */
    public function sanitizeSettingsInput(array $input): array
    {
        $sanitized = [];

        // Sanitize core and plugin-dependent tag category settings
        foreach (SettingsKeys::getTagKeys() as $key) {
            $sanitized[$key] = isset($input[$key]) && $input[$key] === '1';
        }

        // Sanitize custom post type tag settings dynamically
        $this->sanitizeCustomPostTypeTagSettings($input, $sanitized);

        return $sanitized;
    }

    /**
     * Sanitize custom post type tag settings
     *
     * Processes settings for custom post types that have generated tags.
     * Only accepts settings for post types that actually have generated tags.
     *
     * @param array $input Raw input data
     * @param array &$sanitized Reference to sanitized array to update
     * @return void
     * @since 2.0.0
     */
    private function sanitizeCustomPostTypeTagSettings(array $input, array &$sanitized): void
    {
        $validPostTypes = $this->settingsService->getValidGeneratedPostTypes();

        // Process each generated post type
        foreach ($validPostTypes as $postType) {
            $settingKey = Helper::sanitizeInput($postType . '_tags_enabled', 'key');

            // Sanitize the setting value
            $sanitized[$settingKey] = isset($input[$settingKey]) && $input[$settingKey] === '1';
        }
    }

    /**
     * Save settings through service layer
     *
     * Updates settings using domain service with error handling.
     * Logs any errors that occur during save operation.
     *
     * @param array $settings Sanitized settings data
     * @return bool True if save successful
     * @since 2.0.0
     */
    public function saveSettings(array $settings): bool
    {
        try {
            foreach ($settings as $key => $value) {
                $this->settingsService->set($key, $value);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
