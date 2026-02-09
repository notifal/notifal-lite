<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Settings;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Traits\SettingsServiceTrait;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class BehaviorSettingsService
 *
 * Handles behavior settings data processing, validation, and formatting
 * for OnPage Notifications. Manages user interaction behaviors, animation
 * settings, accessibility options, and advanced behavior controls.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Application\Services
 */
class BehaviorSettingsService
{
    use SettingsServiceTrait;
    /**
     * Default behavior settings
     *
     * @since 2.0.0
     * @var array
     */
    private const DEFAULT_SETTINGS = [
        // User Interaction
        'dismiss_on_click_outside' => false,
        'dismiss_on_escape_key' => false,
        'dismiss_on_scroll' => false,
        
        // Accessibility
        'enable_aria_labels' => true,
        'focus_trap' => false,
        'announce_to_screen_reader' => false,
        
        // Advanced Behavior
        'prevent_page_scroll' => false,
        'close_on_form_submit' => true,
        'close_on_form_submit_delay_seconds' => 5,
        'maintain_state_on_refresh' => true,
        
        // Mobile Behavior
        'mobile_optimized' => true,
        'swipe_to_dismiss' => true,
        'touch_friendly' => true,
        'prevent_zoom_on_touch' => true,
        
        // Tab Badge Behavior
        'enable_tab_badge' => false,
        'tab_badge_display_mode' => 'inactive_only',
        'tab_badge_style' => 'count',
        'tab_badge_color' => '#dc3545',
        'tab_badge_custom_text' => '',
        'tab_badge_title_animation' => true,
        'tab_badge_text_position' => 'prefix',
        'tab_badge_animation_style' => 'flash',
        'tab_badge_animation_speed' => 'normal',
        'tab_badge_auto_clear_on_focus' => true,
    ];

    /**
     * Get default behavior settings
     *
     * @since 2.0.0
     * @return array
     */
    public function getDefaultSettings(): array
    {
        return apply_filters(FilterHooks::ONPAGE_BEHAVIOR_DEFAULT_SETTINGS, self::DEFAULT_SETTINGS);
    }

    /**
     * Validate and sanitize behavior settings
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

        return apply_filters(FilterHooks::ONPAGE_BEHAVIOR_SANITIZED_SETTINGS, $sanitized, $settings);
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
            // Boolean settings
            case 'enable_aria_labels':
            case 'focus_trap':
            case 'announce_to_screen_reader':
            case 'prevent_page_scroll':
            case 'close_on_form_submit':
                return (bool) $value;

            case 'close_on_form_submit_delay_seconds':
                $int = (int) $value;
                return max(0, min(60, $int));

            case 'maintain_state_on_refresh':
            case 'mobile_optimized':
            case 'touch_friendly':
            case 'prevent_zoom_on_touch':
                return (bool) $value;

            case 'dismiss_on_click_outside':
            case 'dismiss_on_escape_key':
            case 'dismiss_on_scroll':
                if (!$this->isProFeatureAllowed()) {
                    return false;
                }
                return (bool) $value;

            case 'swipe_to_dismiss':
                if (!$this->isProFeatureAllowed()) {
                    return false; 
                }
                return (bool) $value;

            case 'enable_tab_badge':
            case 'tab_badge_title_animation':
            case 'tab_badge_auto_clear_on_focus':
                if (!$this->isProFeatureAllowed()) {
                    return false;
                }
                return (bool) $value;

            case 'tab_badge_display_mode':
                if (!$this->isProFeatureAllowed()) {
                    return 'inactive_only';
                }
                return $this->sanitizeSelect($value, ['inactive_only', 'always'], 'inactive_only');

            case 'tab_badge_style':
                if (!$this->isProFeatureAllowed()) {
                    return 'count';
                }
                return $this->sanitizeSelect($value, ['count', 'custom', 'both'], 'count');

            case 'tab_badge_color':
                if (!$this->isProFeatureAllowed()) {
                    return '#dc3545';
                }
                return $this->sanitizeHexColor($value, '#dc3545');

            case 'tab_badge_custom_text':
                if (!$this->isProFeatureAllowed()) {
                    return '';
                }
                return sanitize_text_field($value);

            case 'tab_badge_text_position':
                if (!$this->isProFeatureAllowed()) {
                    return 'prefix';
                }
                return $this->sanitizeSelect($value, ['prefix', 'suffix'], 'prefix');

            case 'tab_badge_animation_style':
                if (!$this->isProFeatureAllowed()) {
                    return 'flash';
                }
                return $this->sanitizeSelect($value, ['flash', 'marquee'], 'flash');

            case 'tab_badge_animation_speed':
                if (!$this->isProFeatureAllowed()) {
                    return 'normal';
                }
                return $this->sanitizeSelect($value, ['slow', 'normal', 'fast'], 'normal');

            default:
                return $default;
        }
    }


    /**
     * Get user interaction configuration
     *
     * @since 2.0.0
     * @param array $settings Behavior settings
     * @return array User interaction config
     */
    public function getUserInteractionConfig(array $settings): array
    {
        $config = [
            'dismiss_on_click_outside' => $this->isProFeatureAllowed() ? (bool) ($settings['dismiss_on_click_outside'] ?? false) : false,
            'dismiss_on_escape_key' => $this->isProFeatureAllowed() ? (bool) ($settings['dismiss_on_escape_key'] ?? true) : false,
            'dismiss_on_scroll' => $this->isProFeatureAllowed() ? (bool) ($settings['dismiss_on_scroll'] ?? false) : false,
        ];

        return apply_filters(FilterHooks::ONPAGE_BEHAVIOR_INTERACTION_CONFIG, $config, $settings);
    }



    /**
     * Get accessibility configuration
     *
     * @since 2.0.0
     * @param array $settings Behavior settings
     * @return array Accessibility config
     */
    public function getAccessibilityConfig(array $settings): array
    {
        $config = [
            'enable_aria_labels' => (bool) ($settings['enable_aria_labels'] ?? true),
            'focus_trap' => (bool) ($settings['focus_trap'] ?? true),
            'announce_to_screen_reader' => (bool) ($settings['announce_to_screen_reader'] ?? true),
        ];

        return apply_filters(FilterHooks::ONPAGE_BEHAVIOR_ACCESSIBILITY_CONFIG, $config, $settings);
    }

    /**
     * Get advanced behavior configuration
     *
     * @since 2.0.0
     * @param array $settings Behavior settings
     * @return array Advanced behavior config
     */
    public function getAdvancedBehaviorConfig(array $settings): array
    {
        $config = [
            'prevent_page_scroll' => (bool) ($settings['prevent_page_scroll'] ?? false),
            'close_on_form_submit' => (bool) ($settings['close_on_form_submit'] ?? true),
            'close_on_form_submit_delay_seconds' => (int) ($settings['close_on_form_submit_delay_seconds'] ?? 5),
            'maintain_state_on_refresh' => (bool) ($settings['maintain_state_on_refresh'] ?? true),
        ];

        return apply_filters(FilterHooks::ONPAGE_BEHAVIOR_ADVANCED_CONFIG, $config, $settings);
    }

    /**
     * Get mobile behavior configuration
     *
     * @since 2.0.0
     * @param array $settings Behavior settings
     * @return array Mobile behavior config
     */
    public function getMobileBehaviorConfig(array $settings): array
    {
        $config = [
            'mobile_optimized' => (bool) ($settings['mobile_optimized'] ?? true),
            'swipe_to_dismiss' => $this->isProFeatureAllowed() ? (bool) ($settings['swipe_to_dismiss'] ?? true) : false,
            'touch_friendly' => (bool) ($settings['touch_friendly'] ?? true),
            'prevent_zoom_on_touch' => (bool) ($settings['prevent_zoom_on_touch'] ?? true),
        ];

        return apply_filters(FilterHooks::ONPAGE_BEHAVIOR_MOBILE_CONFIG, $config, $settings);
    }

    /**
     * Get tab badge configuration
     *
     * @since 2.0.0
     * @param array $settings Behavior settings
     * @return array Tab badge config
     */
    public function getTabBadgeConfig(array $settings): array
    {
        if (!$this->isProFeatureAllowed()) {
            $config = [
                'enable_tab_badge' => false,
                'tab_badge_display_mode' => 'inactive_only',
                'tab_badge_style' => 'count',
                'tab_badge_color' => '#dc3545',
                'tab_badge_custom_text' => '',
                'tab_badge_title_animation' => false,
                'tab_badge_text_position' => 'prefix',
                'tab_badge_animation_style' => 'flash',
                'tab_badge_animation_speed' => 'normal',
                'tab_badge_auto_clear_on_focus' => false,
            ];
        } else {
            $config = [
                'enable_tab_badge' => (bool) ($settings['enable_tab_badge'] ?? false),
                'tab_badge_display_mode' => $settings['tab_badge_display_mode'] ?? 'inactive_only',
                'tab_badge_style' => $settings['tab_badge_style'] ?? 'count',
                'tab_badge_color' => $settings['tab_badge_color'] ?? '#dc3545',
                'tab_badge_custom_text' => $settings['tab_badge_custom_text'] ?? '',
                'tab_badge_title_animation' => (bool) ($settings['tab_badge_title_animation'] ?? true),
                'tab_badge_text_position' => $settings['tab_badge_text_position'] ?? 'prefix',
                'tab_badge_animation_style' => $settings['tab_badge_animation_style'] ?? 'flash',
                'tab_badge_animation_speed' => $settings['tab_badge_animation_speed'] ?? 'normal',
                'tab_badge_auto_clear_on_focus' => (bool) ($settings['tab_badge_auto_clear_on_focus'] ?? true),
            ];
        }

        return apply_filters(FilterHooks::ONPAGE_BEHAVIOR_TAB_BADGE_CONFIG, $config, $settings);
    }

    /**
     * Check if behavior settings allow specific action
     *
     * @since 2.0.0
     * @param array $settings Behavior settings
     * @param string $action Action to check (e.g., 'dismiss', 'scroll', 'escape')
     * @param array $context Current context
     * @return bool Whether action is allowed
     */
    public function isActionAllowed(array $settings, string $action, array $context = []): bool
    {
        $interaction_config = $this->getUserInteractionConfig($settings);
        
        switch ($action) {
            case 'dismiss_on_click_outside':
                return $interaction_config['dismiss_on_click_outside'];
                
            case 'dismiss_on_escape_key':
                return $interaction_config['dismiss_on_escape_key'];
                
            case 'dismiss_on_scroll':
                return $interaction_config['dismiss_on_scroll'];
                
            default:
                return true;
        }
    }

    /**
     * Check if pro features are allowed (user has active pro license).
     *
     * @return bool True if pro features are allowed
     * @since 2.0.0
     */
    protected function isProFeatureAllowed(): bool
    {
        return $this->checkProFeatureAllowed('notifal_pro_behavior_features');
    }

    /**
     * Generate frontend behavior configuration
     *
     * @since 2.0.0
     * @param array $settings Behavior settings
     * @return array Frontend config
     */
    public function generateFrontendConfig(array $settings): array
    {
        $interaction_config = $this->getUserInteractionConfig($settings);
        $accessibility_config = $this->getAccessibilityConfig($settings);
        $advanced_config = $this->getAdvancedBehaviorConfig($settings);
        $mobile_config = $this->getMobileBehaviorConfig($settings);
        $tab_badge_config = $this->getTabBadgeConfig($settings);

        $frontend_config = [
            'interaction' => $interaction_config,
            'accessibility' => $accessibility_config,
            'advanced' => $advanced_config,
            'mobile' => $mobile_config,
            'tab_badge' => $tab_badge_config,
        ];

        return apply_filters(FilterHooks::ONPAGE_BEHAVIOR_JS_CONFIG, $frontend_config, $settings);
    }
} 
