<?php

namespace Notifal\Domain\Settings\Services;

use Notifal\Core\Modules\ModuleRegistry;
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

        // Determine which settings tab is being saved to avoid wiping other tabs.
        $tab = isset($input['settings_tab'])
            ? sanitize_key((string) wp_unslash($input['settings_tab']))
            : '';

        // Legacy requests without a tab still target Tags.
        if ($tab === '' || $tab === 'tags') {
            // Sanitize core and plugin-dependent tag category settings.
            foreach (SettingsKeys::getTagKeys() as $key) {
                $sanitized[$key] = isset($input[$key]) && (string) $input[$key] === '1';
            }

            // Sanitize custom post type tag settings dynamically.
            $this->sanitizeCustomPostTypeTagSettings($input, $sanitized);
        }

        // Forms tab: captcha, rate limit, honeypot, default email (module toggle lives on Modules page).
        if ($tab === 'forms') {
            $sanitized = array_merge($sanitized, $this->sanitizeFormsSettingsInput($input));
        }

        return $sanitized;
    }

    /**
     * Sanitize module toggle input from the Modules admin page.
     *
     * @param array<string, mixed> $input Raw POST data.
     * @return array<string, bool>
     * @since 3.0.0
     */
    public function sanitizeModulesSettingsInput(array $input): array
    {
        $sanitized = [];

        // Only accept keys registered for available modules.
        foreach (ModuleRegistry::getToggleableSettingKeys() as $settingsKey) {
            $sanitized[$settingsKey] = isset($input[$settingsKey])
                && (string) $input[$settingsKey] === '1';
        }

        return $sanitized;
    }

    /**
     * Sanitize Forms tab settings input.
     *
     * Empty captcha secret does not wipe an existing secret unless clear flag is set.
     *
     * @param array<string, mixed> $input Raw input.
     * @return array<string, mixed>
     * @since 3.0.0
     */
    private function sanitizeFormsSettingsInput(array $input): array
    {
        $sanitized = [];

        // Honeypot is a checkbox (unchecked means off).
        $sanitized[SettingsKeys::FORMS_HONEYPOT_ENABLED] = isset($input[SettingsKeys::FORMS_HONEYPOT_ENABLED])
            && (string) $input[SettingsKeys::FORMS_HONEYPOT_ENABLED] === '1';

        // Rate limit numeric bounds.
        $max = isset($input[SettingsKeys::FORMS_RATE_LIMIT_MAX])
            ? absint($input[SettingsKeys::FORMS_RATE_LIMIT_MAX])
            : 5;
        $sanitized[SettingsKeys::FORMS_RATE_LIMIT_MAX] = max(1, min(100, $max));

        $window = isset($input[SettingsKeys::FORMS_RATE_LIMIT_WINDOW])
            ? absint($input[SettingsKeys::FORMS_RATE_LIMIT_WINDOW])
            : 300;
        $sanitized[SettingsKeys::FORMS_RATE_LIMIT_WINDOW] = max(30, min(86400, $window));

        // Captcha provider allowlist.
        $provider = isset($input[SettingsKeys::FORMS_CAPTCHA_PROVIDER])
            ? sanitize_key((string) wp_unslash($input[SettingsKeys::FORMS_CAPTCHA_PROVIDER]))
            : 'none';
        $allowedProviders = [
            'none',
            'google_recaptcha_v2',
            'google_recaptcha_v3',
            'cloudflare_turnstile',
        ];
        $sanitized[SettingsKeys::FORMS_CAPTCHA_PROVIDER] = in_array($provider, $allowedProviders, true)
            ? $provider
            : 'none';

        // Public site key.
        $siteKey = isset($input[SettingsKeys::FORMS_CAPTCHA_SITE_KEY])
            ? sanitize_text_field(wp_unslash((string) $input[SettingsKeys::FORMS_CAPTCHA_SITE_KEY]))
            : '';
        $sanitized[SettingsKeys::FORMS_CAPTCHA_SITE_KEY] = $siteKey;

        // Secret: write-only; empty keeps existing unless explicit clear.
        $clearSecret = isset($input['forms_captcha_secret_clear'])
            && (string) $input['forms_captcha_secret_clear'] === '1';
        $secretRaw = isset($input[SettingsKeys::FORMS_CAPTCHA_SECRET_KEY])
            ? sanitize_text_field(wp_unslash((string) $input[SettingsKeys::FORMS_CAPTCHA_SECRET_KEY]))
            : '';

        if ($clearSecret) {
            $sanitized[SettingsKeys::FORMS_CAPTCHA_SECRET_KEY] = '';
        } elseif ($secretRaw !== '') {
            $sanitized[SettingsKeys::FORMS_CAPTCHA_SECRET_KEY] = $secretRaw;
        }

        // Default admin email for submissions.
        $email = isset($input[SettingsKeys::FORMS_DEFAULT_ADMIN_EMAIL])
            ? sanitize_email(wp_unslash((string) $input[SettingsKeys::FORMS_DEFAULT_ADMIN_EMAIL]))
            : '';
        $sanitized[SettingsKeys::FORMS_DEFAULT_ADMIN_EMAIL] = is_email($email) ? $email : '';

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
