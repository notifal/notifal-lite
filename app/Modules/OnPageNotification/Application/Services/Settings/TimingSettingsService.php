<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Settings;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Traits\SettingsServiceTrait;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class TimingSettingsService
 *
 * Handles timing settings data processing, validation, and formatting
 * for OnPage Notifications. Manages display timing, duration settings,
 * frequency controls, and advanced timing options.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Application\Services
 */
class TimingSettingsService
{
    use SettingsServiceTrait;
    /**
     * Default timing settings
     *
     * @since 2.0.0
     * @var array
     */
    private const DEFAULT_SETTINGS = [
        // Display Timing
        'show_timing' => 'immediate',
        'delay_seconds' => 3,
        'scroll_percentage' => 50,
        'idle_seconds' => 30,
        
        // Custom Trigger Settings
        'custom_trigger_type' => 'javascript_event',
        'custom_js_event' => 'click',
        'custom_element_selector' => '',
        'visibility_element_selector' => '',
        'visibility_threshold' => 50,
        'custom_js_condition' => '',
        'multiple_triggers_config' => '',
        'custom_trigger_delay' => 0,
        
        // Display Duration
        'display_duration' => 'until_dismissed',
        'auto_hide_seconds' => 15,
        'persistent_duration' => 0,
        
        // Frequency Control
        'show_frequency' => 'once_per_session',
        'max_shows_per_session' => 1,
        'respect_user_dismissal' => true,
        'allow_retrigger_after_hide' => false,
        'retrigger_delay_seconds' => 20,
        'max_retrigger_per_page' => 2,
        'require_user_consent' => false,
        'consent_template_type' => 'default',
        'consent_template_id' => null,
        'pause_on_tab_inactive' => true,
        'resume_on_tab_active' => true,
        'prevent_multiple_instances' => true,

        // Priority Management
        'enable_priority' => false,
        'priority_level' => 5,

        // Session Control
        'clear_session_on_logout' => true,
        'respect_user_preferences' => false,
    ];

    /**
     * Get default timing settings
     *
     * @since 2.0.0
     * @return array
     */
    public function getDefaultSettings(): array
    {
        return apply_filters(FilterHooks::ONPAGE_TIMING_DEFAULT_SETTINGS, self::DEFAULT_SETTINGS);
    }

    /**
     * Validate and sanitize timing settings
     *
     * @since 2.0.0
     * @param array $settings Raw settings data
     * @return array Sanitized settings
     */
    public function sanitizeSettings(array $settings): array
    {
        $sanitized = [];
        $defaults = $this->getDefaultSettings();

        foreach ($defaults as $key => $default_value) {
            $value = $settings[$key] ?? $default_value;
            $sanitized[$key] = $this->sanitizeSetting($key, $value, $default_value);
        }

        return apply_filters(FilterHooks::ONPAGE_TIMING_SANITIZED_SETTINGS, $sanitized, $settings);
    }

    /**
     * Sanitize individual setting value
     *
     * @since 2.0.0
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @param mixed $default Default value
     * @return mixed Sanitized value
     */
    private function sanitizeSetting(string $key, $value, $default)
    {
        switch ($key) {
            // Display Timing
            case 'show_timing':
                // Handle backwards compatibility for old 'exit' value
                if ($value === 'exit') {
                    $value = 'exit_intent';
                }

                if (!$this->isProFeatureAllowed()) {
                    $allowed_lite = ['immediate', 'delay'];
                    if (!in_array($value, $allowed_lite, true)) {
                        return 'immediate'; // Force to basic option
                    }
                }

                return $this->sanitizeSelect($value, [
                    'immediate', 'delay', 'scroll', 'exit_intent', 'idle', 'custom'
                ], 'immediate');
            
            case 'delay_seconds':
                return $this->sanitizeInteger($value, 0, 60);
            
            case 'scroll_percentage':
                return $this->sanitizeInteger($value, 1, 100);
            
            case 'idle_seconds':
                return $this->sanitizeInteger($value, 5, 300);
            
            // Custom Trigger Settings
            case 'custom_trigger_type':
                return $this->sanitizeSelect($value, [
                    'javascript_event', 'element_visible', 'custom_condition', 'multiple_triggers'
                ], 'javascript_event');
            
            case 'custom_js_event':
                return $this->sanitizeSelect($value, [
                    'click', 'hover', 'focus', 'blur', 'change', 'submit'
                ], 'click');
            
            case 'custom_element_selector':
            case 'visibility_element_selector':
                return sanitize_text_field($value);
            
            case 'visibility_threshold':
                return $this->sanitizeInteger($value, 1, 100);
            
            case 'custom_js_condition':
                return $this->sanitizeCustomJavaScript($value);
            
            case 'multiple_triggers_config':
                return $this->sanitizeJSON($value);
            
            case 'custom_trigger_delay':
                return $this->sanitizeFloat($value, 0.0, 60.0);
            
            // Display Duration
            case 'display_duration':
                return $this->sanitizeSelect($value, [
                    'until_dismissed', 'auto_hide', 'persistent'
                ], 'until_dismissed');
            
            case 'auto_hide_seconds':
                return $this->sanitizeInteger($value, 1, 60);
            
            case 'persistent_duration':
                return $this->sanitizeInteger($value, 0, 86400);
            
            // Frequency Control
            case 'show_frequency':
                return $this->sanitizeSelect($value, [
                    'always', 'once_per_session'
                ], 'once_per_session');
            
            case 'max_shows_per_session':
                return $this->sanitizeInteger($value, 1, 10);

            // Retriggering
            case 'allow_retrigger_after_hide':
                return (bool) $value;

            case 'retrigger_delay_seconds':
                return $this->sanitizeInteger($value, 1, 300);

            case 'max_retrigger_per_page':
                return $this->sanitizeInteger($value, 1, 10);
            
            // Priority settings
            case 'priority_level':
                return $this->sanitizeInteger($value, 1, 10);
            
            // Boolean settings
            case 'respect_user_dismissal':
            case 'respect_user_preferences':
                return (bool) $value;

            // SECURITY: Advanced timing features are pro-only
            case 'pause_on_tab_inactive':
            case 'resume_on_tab_active':
            case 'prevent_multiple_instances':
            case 'require_user_consent':
            case 'respect_user_preferences':
                if (!$this->isProFeatureAllowed()) {
                    return false; 
                }
                return (bool) $value;

            case 'enable_priority':
            case 'clear_session_on_logout':
                return (bool) $value;
            


            default:
                return $value;
        }
    }

    /**
     * Get display timing configuration
     *
     * @since 2.0.0
     * @param array $settings Timing settings
     * @return array Display timing config
     */
    public function getDisplayTimingConfig(array $settings): array
    {
        return [
            'type' => $settings['show_timing'] ?? 'immediate',
            'delay_seconds' => (int) ($settings['delay_seconds'] ?? 3),
            'scroll_percentage' => (int) ($settings['scroll_percentage'] ?? 50),
            'idle_seconds' => (int) ($settings['idle_seconds'] ?? 30),
            'custom_trigger' => [
                'type' => $settings['custom_trigger_type'] ?? 'javascript_event',
                'js_event' => $settings['custom_js_event'] ?? 'click',
                'element_selector' => $settings['custom_element_selector'] ?? '',
                'visibility_element' => $settings['visibility_element_selector'] ?? '',
                'visibility_threshold' => (int) ($settings['visibility_threshold'] ?? 50),
                'custom_condition' => $settings['custom_js_condition'] ?? '',
                'multiple_triggers' => $settings['multiple_triggers_config'] ?? '',
                'delay' => (float) ($settings['custom_trigger_delay'] ?? 0.0)
            ]
        ];
    }

    /**
     * Get display duration configuration
     *
     * @since 2.0.0
     * @param array $settings Timing settings
     * @return array Display duration config
     */
    public function getDisplayDurationConfig(array $settings): array
    {
        return [
            'type' => $settings['display_duration'] ?? 'until_dismissed',
            'auto_hide_seconds' => (int) ($settings['auto_hide_seconds'] ?? 15),
            'persistent_duration' => (int) ($settings['persistent_duration'] ?? 0)
        ];
    }

    /**
     * Get frequency control configuration
     *
     * @since 2.0.0
     * @param array $settings Timing settings
     * @return array Frequency control config
     */
    public function getFrequencyConfig(array $settings): array
    {
        return [
            'type' => $settings['show_frequency'] ?? 'once_per_session',
            'max_per_session' => (int) ($settings['max_shows_per_session'] ?? 1),
        ];
    }

    /**
     * Get advanced timing configuration
     *
     * @since 2.0.0
     * @param array $settings Timing settings
     * @return array Advanced timing config
     */
    public function getAdvancedTimingConfig(array $settings): array
    {
        $config = [
            'enable_priority' => (bool) ($settings['enable_priority'] ?? false),
            'priority_level' => (int) ($settings['priority_level'] ?? 5),
            'clear_session_on_logout' => (bool) ($settings['clear_session_on_logout'] ?? true)
        ];

        // SECURITY: Only include pro features if pro is active
        if ($this->isProFeatureAllowed()) {
            $config['require_user_consent'] = (bool) ($settings['require_user_consent'] ?? false);
            $config['consent_template_type'] = sanitize_text_field($settings['consent_template_type'] ?? 'default');
            $config['consent_template_id'] = $settings['consent_template_id'] ? (int) $settings['consent_template_id'] : null;
            $config['pause_on_tab_inactive'] = (bool) ($settings['pause_on_tab_inactive'] ?? false);
            $config['resume_on_tab_active'] = (bool) ($settings['resume_on_tab_active'] ?? false);
            $config['prevent_multiple_instances'] = (bool) ($settings['prevent_multiple_instances'] ?? true);
            $config['respect_user_preferences'] = (bool) ($settings['respect_user_preferences'] ?? false);
        } else {
            // Force pro features to false in lite version
            $config['require_user_consent'] = false;
            $config['consent_template_type'] = 'default';
            $config['consent_template_id'] = null;
            $config['pause_on_tab_inactive'] = false;
            $config['resume_on_tab_active'] = false;
            $config['prevent_multiple_instances'] = false;
            $config['respect_user_preferences'] = false;
        }

        return $config;
    }

    /**
     * Check if timing settings are valid for display
     *
     * @since 2.0.0
     * @param array $settings Timing settings
     * @return bool Whether settings are valid
     */
    public function isValidForDisplay(array $settings): bool
    {
        $show_timing = $settings['show_timing'] ?? 'immediate';
        
        // Validate required fields based on timing type
        switch ($show_timing) {
            case 'delay':
                $delay = $settings['delay_seconds'] ?? 0;
                return $delay >= 0 && $delay <= 60;
            
            case 'scroll':
                $scroll = $settings['scroll_percentage'] ?? 0;
                return $scroll >= 1 && $scroll <= 100;
            
            case 'idle':
                $idle = $settings['idle_seconds'] ?? 0;
                return $idle >= 5 && $idle <= 300;
            
            case 'exit_intent':
                // Exit intent doesn't require additional validation
                return true;
            
            case 'custom':
                $trigger_type = $settings['custom_trigger_type'] ?? '';
                return $this->isValidCustomTrigger($settings, $trigger_type);
            
            default:
                return true;
        }
    }

    /**
     * Validate custom trigger settings
     *
     * @since 2.0.0
     * @param array $settings Timing settings
     * @param string $trigger_type Trigger type
     * @return bool Whether custom trigger is valid
     */
    private function isValidCustomTrigger(array $settings, string $trigger_type): bool
    {
        switch ($trigger_type) {
            case 'javascript_event':
                $element_selector = $settings['custom_element_selector'] ?? '';
                return !empty(trim($element_selector));
            
            case 'element_visible':
                $visibility_element = $settings['visibility_element_selector'] ?? '';
                return !empty(trim($visibility_element));
            
            case 'custom_condition':
                $condition = $settings['custom_js_condition'] ?? '';
                return !empty(trim($condition));
            
            case 'multiple_triggers':
                $config = $settings['multiple_triggers_config'] ?? '';
                return !empty(trim($config)) && $this->isValidJSON($config);
            
            default:
                return false;
        }
    }


    /**
     * Check if pro features are allowed (user has active pro license).
     *
     * @return bool True if pro features are allowed
     * @since 2.0.0
     */
    private function isProFeatureAllowed(): bool
    {
        return $this->checkProFeatureAllowed('notifal_pro_timing_features');
    }

    /**
     * Generate timing configuration for frontend use
     *
     * Flattens timing settings to match frontend JavaScript expectations.
     * The frontend expects direct access to properties like timing.show_frequency,
     * not nested objects.
     *
     * @since 2.0.0
     * @param array $settings Timing settings
     * @return array Frontend timing config (flattened structure)
     */
    public function generateFrontendConfig(array $settings): array
    {
        // Sanitize all settings first
        $sanitized = $this->sanitizeSettings($settings);
        
        // Return flattened structure for frontend compatibility
        return $sanitized;
    }
} 
