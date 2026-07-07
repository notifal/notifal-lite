<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Settings;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Traits\SettingsServiceTrait;
use Notifal\Shared\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class AppearanceSettingsService
 *
 * Handles appearance settings data processing, validation, and formatting
 * for OnPage Notifications. Manages position settings, device visibility,
 * distance controls, and custom positioning.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Application\Services
 */
class AppearanceSettingsService
{
    use SettingsServiceTrait;

    /**
     * Available display types for notifications.
     *
     * @since 2.0.0
     * @var array
     */
    public const DISPLAY_TYPES = [
        'toast',
        'popup',
        'topbar',
        'floating',
        'inline',
        'corner'
    ];
    /**
     * Default appearance settings
     *
     * @since 2.0.0
     * @var array
     */
    private const DEFAULT_SETTINGS = [
        // Device Visibility
        'show_on_desktop' => true,
        'show_on_tablet' => true,
        'show_on_mobile' => true,

        // Display Type
        'notification_display_type' => 'toast',

        // Desktop Position (for toast/floating)
        'desktop_position' => 'top-right',
        'desktop_top_distance' => 50,
        'desktop_bottom_distance' => 50,
        'desktop_left_distance' => 50,
        'desktop_right_distance' => 50,

        // Desktop Bar Position (for topbar)
        'desktop_bar_position' => 'top',
        'desktop_bar_distance' => 0,

        // Mobile Position (for toast/floating)
        'mobile_position' => 'top',
        'mobile_top_distance' => 50,
        'mobile_bottom_distance' => 50,

        // Mobile Bar Position (for topbar)
        'mobile_bar_position' => 'top',
        'mobile_bar_distance' => 0,

        // Top-bar placement: fixed at viewport top vs above header (first element)
        'topbar_placement' => 'fixed_top',
        // When above header: whether bar is sticky on scroll
        'topbar_sticky_on_scroll' => true,

        // Animation
        'show_animation_type' => 'fade',
        'hide_animation_type' => 'fade',
        'animation_duration' => 300,

        // Audio Settings
        'enable_audio' => false,
        'audio_type' => 'default',
        'audio_file' => '',
        'custom_audio_file' => '',
        'audio_volume' => 50,
        'audio_play_on_show' => true,
        'audio_play_on_hide' => false,

        // Advanced Styling
        'z_index' => 9999,
        'custom_css' => '',

        // Popup Backdrop Settings
        'backdrop_bg_color' => 'rgba(0, 0, 0, 0.5)',
        'backdrop_blur' => 2,
    ];

    /**
     * Get default appearance settings
     *
     * @since 2.0.0
     * @return array
     */
    public function getDefaultSettings(): array
    {
        return apply_filters(FilterHooks::ONPAGE_APPEARANCE_DEFAULT_SETTINGS, self::DEFAULT_SETTINGS);
    }

    /**
     * Validate and sanitize appearance settings
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

        return apply_filters(FilterHooks::ONPAGE_APPEARANCE_SANITIZED_SETTINGS, $sanitized, $settings);
    }

    /**
     * Sanitize individual setting value
     *
     * @since 2.0.0
     * @since 2.3.7 Position distance fields accept negative values via sanitizeSignedInteger().
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @param mixed $default Default value
     * @return mixed Sanitized value
     */
    private function sanitizeSetting(string $key, $value, $default)
    {
        switch ($key) {
            // Boolean settings
            case 'show_on_desktop':
            case 'show_on_tablet':
            case 'show_on_mobile':
                return (bool) $value;

            // Integer settings with ranges
            case 'desktop_top_distance':
            case 'desktop_bottom_distance':
            case 'desktop_left_distance':
            case 'desktop_right_distance':
            case 'desktop_bar_distance':
            case 'mobile_bar_distance':
            case 'mobile_top_distance':
            case 'mobile_bottom_distance':
                return $this->sanitizeSignedInteger($value);
            
            case 'animation_duration':
                return $this->sanitizeDistance($value, 0, 2000);
            
            case 'z_index':
                return $this->sanitizeDistance($value, 1, 99999999);

            // Select options
            case 'notification_display_type':
                return $this->sanitizeSelect($value, self::DISPLAY_TYPES, 'toast');
            
            case 'desktop_position':
                return $this->sanitizeSelect($value, [
                    'top-right', 'top-center', 'top-left', 'bottom-right',
                    'bottom-center', 'bottom-left', 'center'
                ], 'top-right');

            case 'desktop_bar_position':
                return $this->sanitizeSelect($value, [
                    'top', 'bottom', 'left', 'right'
                ], 'top');

            case 'mobile_position':
                return $this->sanitizeSelect($value, [
                    'top', 'bottom', 'center'
                ], 'top');

            case 'mobile_bar_position':
                return $this->sanitizeSelect($value, [
                    'top', 'bottom', 'left', 'right'
                ], 'top');
            
            case 'show_animation_type':
                return $this->sanitizeSelect($value, [
                    'fade', 'slide-in-left', 'slide-in-right', 'slide-in-top', 'slide-in-bottom',
                    'bounce', 'zoom', 'flip', 'none'
                ], 'fade');

            case 'hide_animation_type':
                return $this->sanitizeSelect($value, [
                    'fade', 'slide-out-left', 'slide-out-right', 'slide-out-top', 'slide-out-bottom',
                    'bounce', 'zoom', 'flip', 'none'
                ], 'fade');
            
            // Audio settings
            case 'enable_audio':
            case 'audio_play_on_show':
            case 'audio_play_on_hide':
                return (bool) $value;

            case 'audio_type':
                return $this->sanitizeSelect($value, [
                    'default', 'custom', 'none'
                ], 'default');

            case 'audio_file':
                if (strpos($value, 'audio') === 0 && strpos($value, '.mp3') !== false) {
                    return Helper::sanitizeInput($value, 'text');
                } else {
                    return Helper::sanitizeInput($value, 'url');
                }

            case 'custom_audio_file':
                return Helper::sanitizeInput($value, 'url');
            
            case 'audio_volume':
                return $this->sanitizePercentage($value);
            
            case 'custom_css':
                return $this->sanitizeCustomCSS($value);

            case 'backdrop_bg_color':
                return $this->sanitizeRgbaColor($value, 'rgba(0, 0, 0, 0.5)');

            case 'backdrop_blur':
                return $this->sanitizeDistance($value, 0, 20);

            case 'topbar_placement':
                return $this->sanitizeSelect($value, ['fixed_top', 'above_header'], 'fixed_top');

            case 'topbar_sticky_on_scroll':
                return (bool) $value;

            default:
                return $value;
        }
    }

    /**
     * Validate and sanitize custom CSS with proper selector validation.
     *
     * @since 2.0.0
     * @param string $css Raw CSS input
     * @return string Sanitized CSS or empty string if invalid
     */
    private function sanitizeCustomCSS(string $css): string
    {
        if (!$this->isProFeatureAllowed()) {
            return '';
        }

        if (empty(trim($css))) {
            return '';
        }

        // Basic sanitization first
        $css = Helper::sanitizeInput($css, 'textarea');

        // Remove comments and normalize whitespace
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = trim($css);

        if (empty($css)) {
            return '';
        }

        // Keep only selectors scoped to this notification; preserve @media wrappers when valid.
        return trim($this->filterValidCSSBlocks($css));
    }

    /**
     * Recursively filter CSS blocks, allowing @media wrappers with scoped inner rules.
     *
     * @since 2.4.2
     * @param string $css CSS string to filter.
     * @return string Filtered CSS containing only allowed selectors.
     */
    private function filterValidCSSBlocks(string $css): string
    {
        $output = '';
        $length = strlen($css);
        $position = 0;

        while ($position < $length) {
            // Skip whitespace between blocks.
            while ($position < $length && ctype_space($css[$position])) {
                $position++;
            }

            if ($position >= $length) {
                break;
            }

            $braceStart = strpos($css, '{', $position);
            if ($braceStart === false) {
                break;
            }

            $selector = trim(substr($css, $position, $braceStart - $position));
            $block = $this->extractCSSBlock($css, $braceStart);
            $position = $block['end'];

            if ($selector === '') {
                continue;
            }

            // @media is allowed as a wrapper; inner rules must still use the notification prefix.
            if (stripos($selector, '@media') === 0) {
                $innerCss = $this->filterValidCSSBlocks($block['content']);
                if ($innerCss !== '') {
                    $output .= $selector . ' { ' . $innerCss . ' }' . "\n";
                }
                continue;
            }

            if ($this->validateCSSSelector($selector)) {
                $output .= $selector . ' { ' . trim($block['content']) . ' }' . "\n";
            }
        }

        return $output;
    }

    /**
     * Extract the inner content of a CSS block starting at an opening brace.
     *
     * @since 2.4.2
     * @param string $css Full CSS string.
     * @param int $openBrace Index of the opening "{" character.
     * @return array{content: string, end: int} Block content and index after the closing "}".
     */
    private function extractCSSBlock(string $css, int $openBrace): array
    {
        $depth = 0;
        $length = strlen($css);
        $contentStart = $openBrace + 1;

        for ($index = $openBrace; $index < $length; $index++) {
            if ($css[$index] === '{') {
                $depth++;
            } elseif ($css[$index] === '}') {
                $depth--;
                if ($depth === 0) {
                    return [
                        'content' => substr($css, $contentStart, $index - $contentStart),
                        'end' => $index + 1,
                    ];
                }
            }
        }

        return ['content' => '', 'end' => $length];
    }

    /**
     * Validate CSS selector format for security.
     *
     * @since 2.0.0
     * @param string $selector CSS selector string
     * @return bool True if valid, false otherwise
     */
    private function validateCSSSelector(string $selector): bool
    {
        if (empty($selector)) {
            return true;
        }

        // Split by comma to handle multiple selectors
        $selectors = array_map('trim', explode(',', $selector));
        
        foreach ($selectors as $singleSelector) {
            // Check if selector starts with required prefix (either ID or class)
            if (!preg_match('/^[.#]notifal-onpage-notification-\d+/', $singleSelector)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get CSS validation error message.
     *
     * @since 2.0.0
     * @return string Error message
     */
    public function getCSSValidationMessage(): string
    {
        return sprintf(
            __("Custom CSS selectors must start with either \"#notifal-onpage-notification-{ID}\" (ID selector) or \".notifal-onpage-notification-{ID}\" (class selector). @media queries are supported when the rules inside use these prefixes. Any other selectors will be ignored for security reasons.", 'notifal')
        );
    }

    /**
     * Get position settings for a specific device
     *
     * @since 2.0.0
     * @param array $settings Appearance settings
     * @param string $device Device type (desktop|mobile)
     * @return array Position settings
     */
    public function getPositionSettings(array $settings, string $device): array
    {
        $display_type = $settings['notification_display_type'] ?? 'toast';
        $position_settings = [];

        switch ($display_type) {
            case 'popup':
                // Popup is always centered, no position settings needed
                $position_settings = [
                    'position' => 'center',
                    'top_distance' => 0,
                    'bottom_distance' => 0,
                    'left_distance' => 0,
                    'right_distance' => 0,
                ];
                break;

            case 'topbar':
                // Floating bar uses different position settings
                if ($device === 'desktop') {
                    $position_settings = [
                        'position' => $settings['desktop_bar_position'] ?? 'top',
                        'distance' => $settings['desktop_bar_distance'] ?? 0,
                    ];
                } elseif ($device === 'mobile') {
                    $position_settings = [
                        'position' => $settings['mobile_bar_position'] ?? 'top',
                        'distance' => $settings['mobile_bar_distance'] ?? 0,
                    ];
                }
                break;

            case 'toast':
            case 'floating':
            default:
                // Toast/floating use traditional position settings
                if ($device === 'desktop') {
                    $position_settings = [
                        'position' => $settings['desktop_position'] ?? 'top-right',
                        'top_distance' => $settings['desktop_top_distance'] ?? 50,
                        'bottom_distance' => $settings['desktop_bottom_distance'] ?? 50,
                        'left_distance' => $settings['desktop_left_distance'] ?? 50,
                        'right_distance' => $settings['desktop_right_distance'] ?? 50,
                    ];
                } elseif ($device === 'mobile') {
                    $position_settings = [
                        'position' => $settings['mobile_position'] ?? 'top',
                        'top_distance' => $settings['mobile_top_distance'] ?? 50,
                        'bottom_distance' => $settings['mobile_bottom_distance'] ?? 50,
                    ];
                }
                break;
        }

        return apply_filters(FilterHooks::ONPAGE_APPEARANCE_POSITION_SETTINGS, $position_settings, $settings, $device);
    }

    /**
     * Generate CSS for position settings
     *
     * @since 2.0.0
     * @param array $settings Appearance settings
     * @param string $device Device type
     * @return string Generated CSS
     */
    public function generatePositionCSS(array $settings, string $device): string
    {
        $position_settings = $this->getPositionSettings($settings, $device);
        $css = '';

        if ($device === 'desktop') {
            $css = $this->generateDesktopPositionCSS($position_settings, $settings);
        } elseif ($device === 'mobile') {
            $css = $this->generateMobilePositionCSS($position_settings, $settings);
        }

        return apply_filters(FilterHooks::ONPAGE_APPEARANCE_POSITION_CSS, $css, $position_settings, $device);
    }

    /**
     * Generate desktop position CSS
     *
     * @since 2.0.0
     * @param array $position_settings Position settings
     * @param array $full_settings Full appearance settings for context
     * @return string CSS rules
     */
    private function generateDesktopPositionCSS(array $position_settings, array $full_settings = []): string
    {
        $position = $position_settings['position'];
        $display_type = $full_settings['notification_display_type'] ?? 'toast';
        $css = '';

        // Handle popup display type (always centered)
        if ($display_type === 'popup') {
            $css = 'top: 50%; left: 50%; transform: translate(-50%, -50%);';
            return $css;
        }

        // Handle topbar display type
        if ($display_type === 'topbar') {
            $distance = $position_settings['distance'] ?? 0;
            switch ($position) {
                case 'top':
                    $css = sprintf('top: %dpx; left: 0; right: 0;', $distance);
                    break;
                case 'bottom':
                    $css = sprintf('bottom: %dpx; left: 0; right: 0;', $distance);
                    break;
                case 'left':
                    $css = sprintf('left: %dpx; top: 0; bottom: 0; width: auto; height: 100vh;', $distance);
                    break;
                case 'right':
                    $css = sprintf('right: %dpx; top: 0; bottom: 0; width: auto; height: 100vh;', $distance);
                    break;
            }
            return $css;
        }

        // Handle toast/floating display types (traditional positioning)
        switch ($position) {
            case 'top-right':
                $css = sprintf(
                    'top: %dpx; right: %dpx;',
                    $position_settings['top_distance'],
                    $position_settings['right_distance']
                );
                break;

            case 'top-center':
                $css = sprintf(
                    'top: %dpx; left: 50%%; transform: translateX(-50%%);',
                    $position_settings['top_distance']
                );
                break;

            case 'top-left':
                $css = sprintf(
                    'top: %dpx; left: %dpx;',
                    $position_settings['top_distance'],
                    $position_settings['left_distance']
                );
                break;

            case 'bottom-right':
                $css = sprintf(
                    'bottom: %dpx; right: %dpx;',
                    $position_settings['bottom_distance'],
                    $position_settings['right_distance']
                );
                break;

            case 'bottom-center':
                $css = sprintf(
                    'bottom: %dpx; left: 50%%; transform: translateX(-50%%);',
                    $position_settings['bottom_distance']
                );
                break;

            case 'bottom-left':
                $css = sprintf(
                    'bottom: %dpx; left: %dpx;',
                    $position_settings['bottom_distance'],
                    $position_settings['left_distance']
                );
                break;

            case 'center':
                $css = 'top: 50%; left: 50%; transform: translate(-50%, -50%);';
                break;
        }

        return $css;
    }

    /**
     * Generate mobile position CSS
     *
     * @since 2.0.0
     * @param array $position_settings Position settings
     * @param array $full_settings Full appearance settings for context
     * @return string CSS rules
     */
    private function generateMobilePositionCSS(array $position_settings, array $full_settings = []): string
    {
        $position = $position_settings['position'];
        $display_type = $full_settings['notification_display_type'] ?? 'toast';
        $css = '';

        // Handle popup display type (always centered)
        if ($display_type === 'popup') {
            $css = 'top: 50%; left: 50%; transform: translate(-50%, -50%);';
            return $css;
        }

        // Handle topbar display type
        if ($display_type === 'topbar') {
            $distance = $position_settings['distance'] ?? 0;
            switch ($position) {
                case 'top':
                    $css = sprintf('top: %dpx; left: 0; right: 0;', $distance);
                    break;
                case 'bottom':
                    $css = sprintf('bottom: %dpx; left: 0; right: 0;', $distance);
                    break;
                case 'left':
                    $css = sprintf('left: %dpx; top: 0; bottom: 0; width: auto; height: 100vh;', $distance);
                    break;
                case 'right':
                    $css = sprintf('right: %dpx; top: 0; bottom: 0; width: auto; height: 100vh;', $distance);
                    break;
            }
            return $css;
        }

        // Handle toast/floating display types (traditional positioning)
        switch ($position) {
            case 'top':
                $css = sprintf('top: %dpx; left: 0; right: 0;', $position_settings['top_distance']);
                break;

            case 'bottom':
                $css = sprintf('bottom: %dpx; left: 0; right: 0;', $position_settings['bottom_distance']);
                break;

            case 'center':
                $css = 'top: 50%; left: 50%; transform: translate(-50%, -50%);';
                break;
        }

        return $css;
    }

    /**
     * Check if notification should be shown on a specific device
     *
     * @since 2.0.0
     * @param array $settings Appearance settings
     * @param string $device Device type
     * @return bool Whether to show notification
     */
    public function shouldShowOnDevice(array $settings, string $device): bool
    {
        $key = 'show_on_' . $device;
        return (bool) ($settings[$key] ?? true);
    }

    /**
     * Get animation CSS classes
     *
     * @since 2.0.0
     * @param array $settings Appearance settings
     * @param string $event Event type (show|hide)
     * @return string Animation class
     */
    public function getAnimationClass(array $settings, string $event = 'show'): string
    {
        $animation_type = $event === 'hide'
            ? ($settings['hide_animation_type'] ?? 'fade')
            : ($settings['show_animation_type'] ?? 'fade');
        return 'notifal-' . $animation_type;
    }

    /**
     * Whether the animation duration setting applies to the current show/hide configuration.
     *
     * Duration is hidden and ignored only when both show and hide are set to "No Animation".
     *
     * @since 2.0.0
     * @param array $settings Appearance settings.
     * @return bool True when animation duration should be shown and applied.
     */
    public static function isAnimationDurationApplicable(array $settings): bool
    {
        $showType = isset($settings['show_animation_type']) ? (string) $settings['show_animation_type'] : 'fade';
        $hideType = isset($settings['hide_animation_type']) ? (string) $settings['hide_animation_type'] : 'fade';

        return $showType !== 'none' || $hideType !== 'none';
    }

    /**
     * Get animation duration
     *
     * @since 2.0.0
     * @param array $settings Appearance settings
     * @return int Duration in milliseconds
     */
    public function getAnimationDuration(array $settings): int
    {
        if (!self::isAnimationDurationApplicable($settings)) {
            return (int) (self::DEFAULT_SETTINGS['animation_duration'] ?? 300);
        }

        return (int) ($settings['animation_duration'] ?? 300);
    }

    /**
     * Get audio configuration
     *
     * @since 2.0.0
     * @param array $settings Appearance settings
     * @return array Audio configuration
     */
    public function getAudioConfig(array $settings): array
    {
        return [
            'enabled' => (bool) ($settings['enable_audio'] ?? false),
            'type' => $settings['audio_type'] ?? 'default',
            'file' => $settings['audio_file'] ?? '',
            'volume' => (int) ($settings['audio_volume'] ?? 50),
            'play_on_show' => (bool) ($settings['audio_play_on_show'] ?? true),
            'play_on_hide' => (bool) ($settings['audio_play_on_hide'] ?? false),
        ];
    }

    /**
     * Check if pro features are allowed (user has active pro license).
     *
     * @return bool True if pro features are allowed
     * @since 2.0.0
     */
    protected function isProFeatureAllowed(): bool
    {
        return $this->checkProFeatureAllowed('notifal_pro_appearance_features');
    }

    /**
     * Get available default audio files
     *
     * @since 2.0.0
     * @return array List of default audio files
     */
    public function getDefaultAudioFiles(): array
    {
        $audio_files = [
            'audio1.mp3' => __('Notification Sound 1', 'notifal'),
            'audio2.mp3' => __('Notification Sound 2', 'notifal'),
            'audio3.mp3' => __('Notification Sound 3', 'notifal'),
            'audio4.mp3' => __('Notification Sound 4', 'notifal'),
            'audio5.mp3' => __('Notification Sound 5', 'notifal'),
        ];

        return apply_filters(FilterHooks::ONPAGE_APPEARANCE_DEFAULT_AUDIO_FILES, $audio_files);
    }

    /**
     * Get audio file URL
     *
     * @since 2.0.0
     * @param array $settings Appearance settings
     * @return string Audio file URL
     */
    public function getAudioFileUrl(array $settings): string
    {
        $audio_config = $this->getAudioConfig($settings);
        
        if (!$audio_config['enabled'] || $audio_config['type'] === 'none') {
            return '';
        }

        if ($audio_config['type'] === 'custom') {
            // Prefer explicit custom audio file setting
            $customUrl = isset($settings['custom_audio_file']) ? (string) $settings['custom_audio_file'] : '';
            if (!empty($customUrl)) {
                return $customUrl;
            }
            // Fallback: some older saves may have stored custom URL in 'audio_file'
            if (!empty($audio_config['file'])) {
                return $audio_config['file'];
            }
        }

        if ($audio_config['type'] === 'default') {
            // Get the audio file from settings or use fallback
            $audio_file = $audio_config['file'] ?: 'audio2.mp3';

            // Extract filename from URL if necessary
            $audio_file = self::extractFilename($audio_file);
            
            // Get the first default audio file as fallback if the file doesn't exist in our list
            $default_files = $this->getDefaultAudioFiles();
            if (!array_key_exists($audio_file, $default_files)) {
                $audio_file = array_keys($default_files)[0] ?? 'audio2.mp3';
            }
            
            return apply_filters(
                FilterHooks::ONPAGE_APPEARANCE_AUDIO_FILE_URL,
                plugin_dir_url(NOTIFAL_FILE) . 'app/Modules/OnPageNotification/Resources/Assets/audios/' . $audio_file,
                $audio_file
            );
        }

        return '';
    }

    /**
     * Check if audio should be played
     *
     * @since 2.0.0
     * @param array $settings Appearance settings
     * @param string $event Event type (show|hide)
     * @return bool Whether to play audio
     */
    public function shouldPlayAudio(array $settings, string $event): bool
    {
        $audio_config = $this->getAudioConfig($settings);
        
        if (!$audio_config['enabled']) {
            return false;
        }

        if ($event === 'show' && $audio_config['play_on_show']) {
            return true;
        }

        if ($event === 'hide' && $audio_config['play_on_hide']) {
            return true;
        }

        return false;
    }

    /**
     * Generate frontend configuration for appearance settings
     *
     * @since 2.0.0
     * @param array $settings Appearance settings
     * @return array Frontend configuration
     */
    public function generateFrontendConfig(array $settings): array
    {
        // Get audio config
        $audioConfig = $this->getAudioConfig($settings);
        
        // Flatten audio config to root level with original key names for frontend compatibility
        $config = [
            'device_visibility' => [
                'show_on_desktop' => (bool) ($settings['show_on_desktop'] ?? true),
                'show_on_tablet' => (bool) ($settings['show_on_tablet'] ?? true),
                'show_on_mobile' => (bool) ($settings['show_on_mobile'] ?? true),
            ],
            'display_type' => $settings['notification_display_type'] ?? 'toast',
            'position' => [
                'desktop' => $this->getPositionSettings($settings, 'desktop'),
                'mobile' => $this->getPositionSettings($settings, 'mobile'),
            ],
            'animation' => [
                'show_type' => $settings['show_animation_type'] ?? 'fade',
                'hide_type' => $settings['hide_animation_type'] ?? 'fade',
                'duration' => $this->getAnimationDuration($settings),
            ],
            'z_index' => (int) ($settings['z_index'] ?? 9999),
            
            'enable_audio' => $audioConfig['enabled'],
            'audio_type' => $audioConfig['type'],
            'audio_file' => $audioConfig['file'],
            'custom_audio_file' => $settings['custom_audio_file'] ?? '',
            'audio_volume' => $audioConfig['volume'],
            'audio_play_on_show' => $audioConfig['play_on_show'],
            'audio_play_on_hide' => $audioConfig['play_on_hide'],
            'audio_url' => $this->getAudioFileUrl($settings),
        ];

        // Add backdrop settings for popup/modal display types
        $config['backdrop_bg_color'] = $settings['backdrop_bg_color'] ?? 'rgba(0, 0, 0, 0.5)';
        $config['backdrop_blur'] = (int) ($settings['backdrop_blur'] ?? 2);

        // Add custom CSS only if pro features are allowed and CSS is not empty
        if ($this->isProFeatureAllowed() && !empty($settings['custom_css'])) {
            $config['custom_css'] = $settings['custom_css'];
        }

        // Top-bar placement and sticky (for floating bar display type)
        $config['topbar_placement'] = $settings['topbar_placement'] ?? 'fixed_top';
        $config['topbar_sticky_on_scroll'] = (bool) ($settings['topbar_sticky_on_scroll'] ?? true);
        $config['topbar_header_selector'] = apply_filters(
            FilterHooks::ONPAGE_TOPBAR_HEADER_SELECTOR,
            'header, .site-header, #masthead, #header, [role="banner"]'
        );

        return apply_filters(FilterHooks::ONPAGE_APPEARANCE_FRONTEND_CONFIG, $config, $settings);
    }

    /**
     * Get display type options for select fields
     *
     * Returns an array of display type options formatted for FieldRenderer::select()
     *
     * @return array Array of options with 'value' and 'label' keys
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function getDisplayTypeOptions(): array {
        return [
            ['value' => 'toast', 'label' => __('Floating Side Box', 'notifal')],
            ['value' => 'popup', 'label' => __('Popup (Modal)', 'notifal')],
            ['value' => 'topbar', 'label' => __('Floating Bar (Top Bar)', 'notifal')],
        ];
    }

    /**
     * Get concise description for display type
     *
     * Returns a short, focused description perfect for inline tooltips
     * that update dynamically when the user changes the display type.
     *
     * @param string $display_type The display type to get description for
     * @return string Concise description for the display type
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function getDisplayTypeDescription(string $display_type): string {
        $descriptions = [
            'toast' => __(
                'Small notification that slides in from the side. Perfect for quick confirmations and alerts.',
                'notifal'
            ),
            'popup' => __(
                'Modal dialog in the center with backdrop. Ideal for important messages and forms.',
                'notifal'
            ),
            'topbar' => __(
                'Full-width banner sliding from top. Great for site-wide announcements and promotions.',
                'notifal'
            ),
            'floating' => __(
                'Fixed circular element that stays in position. Perfect for chat widgets and help buttons.',
                'notifal'
            ),
            'inline' => __(
                'Content within page flow like banners. Integrates naturally with your content.',
                'notifal'
            ),
            'corner' => __(
                'Small badge in corner for notifications count. Minimal visual impact but noticeable.',
                'notifal'
            )
        ];

        return $descriptions[$display_type] ?? __('No description available for this display type.', 'notifal');
    }

    /**
     * Get desktop position options for select fields
     *
     * Returns an array of desktop position options formatted for FieldRenderer::select()
     *
     * @return array Array of options with 'value' and 'label' keys
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function getDesktopPositionOptions(): array {
        return [
            ['value' => 'top-right', 'label' => __('Top Right', 'notifal')],
            ['value' => 'top-center', 'label' => __('Top Center', 'notifal')],
            ['value' => 'top-left', 'label' => __('Top Left', 'notifal')],
            ['value' => 'bottom-right', 'label' => __('Bottom Right', 'notifal')],
            ['value' => 'bottom-center', 'label' => __('Bottom Center', 'notifal')],
            ['value' => 'bottom-left', 'label' => __('Bottom Left', 'notifal')],
            ['value' => 'center', 'label' => __('Center Screen', 'notifal')],
        ];
    }

    /**
     * Get mobile position options for select fields
     *
     * Returns an array of mobile position options formatted for FieldRenderer::select()
     *
     * @return array Array of options with 'value' and 'label' keys
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function getMobilePositionOptions(): array {
        return [
            ['value' => 'top', 'label' => __('Top', 'notifal')],
            ['value' => 'bottom', 'label' => __('Bottom', 'notifal')],
            ['value' => 'center', 'label' => __('Center', 'notifal')],
        ];
    }

    /**
     * Get show animation type options for select fields
     *
     * Returns an array of show animation type options formatted for FieldRenderer::select()
     * Only includes slide-in options for show animations
     *
     * @return array Array of options with 'value' and 'label' keys
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function getShowAnimationTypeOptions(): array {
        return [
            ['value' => 'fade', 'label' => __('Fade In', 'notifal')],
            ['value' => 'slide-in-left', 'label' => __('Slide In From Left', 'notifal')],
            ['value' => 'slide-in-right', 'label' => __('Slide In From Right', 'notifal')],
            ['value' => 'slide-in-top', 'label' => __('Slide In From Top', 'notifal')],
            ['value' => 'slide-in-bottom', 'label' => __('Slide In From Bottom', 'notifal')],
            ['value' => 'bounce', 'label' => __('Bounce', 'notifal')],
            ['value' => 'zoom', 'label' => __('Zoom In', 'notifal')],
            ['value' => 'flip', 'label' => __('Flip', 'notifal')],
            ['value' => 'none', 'label' => __('No Animation', 'notifal')],
        ];
    }

    /**
     * Get hide animation type options for select fields
     *
     * Returns an array of hide animation type options formatted for FieldRenderer::select()
     * Only includes slide-out options for hide animations
     *
     * @return array Array of options with 'value' and 'label' keys
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function getHideAnimationTypeOptions(): array {
        return [
            ['value' => 'fade', 'label' => __('Fade Out', 'notifal')],
            ['value' => 'slide-out-left', 'label' => __('Slide Out To Left', 'notifal')],
            ['value' => 'slide-out-right', 'label' => __('Slide Out To Right', 'notifal')],
            ['value' => 'slide-out-top', 'label' => __('Slide Out To Top', 'notifal')],
            ['value' => 'slide-out-bottom', 'label' => __('Slide Out To Bottom', 'notifal')],
            ['value' => 'bounce', 'label' => __('Bounce', 'notifal')],
            ['value' => 'zoom', 'label' => __('Zoom Out', 'notifal')],
            ['value' => 'flip', 'label' => __('Flip', 'notifal')],
            ['value' => 'none', 'label' => __('No Animation', 'notifal')],
        ];
    }

    /**
     * Extract filename from URL or return filename as-is
     *
     * @since 2.0.0
     * @param string $file_path File path or URL
     * @return string Filename only
     * @author Hossein <hossein@notifal.com>
     */
    public static function extractFilename(string $file_path): string {
        if (!$file_path) {
            return '';
        }

        // Extract filename if it's a full URL
        if (strpos($file_path, 'http') !== false) {
            return basename($file_path);
        }

        return $file_path;
    }
} 
