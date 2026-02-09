<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Settings;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Traits\SettingsServiceTrait;
use Notifal\Shared\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GeneralSettingsService
 *
 * Handles general settings data processing, validation, and formatting
 * for OnPage Notifications. Manages basic notification properties including
 * enable/disable status, title, labels, and content source type.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Application\Services\Settings
 */
class GeneralSettingsService
{
    use SettingsServiceTrait;

    /**
     * Available content source types for notifications.
     *
     * @since 2.0.0
     * @var array
     */
    public const CONTENT_SOURCE_TYPES = [
        'dynamic',
        'static',
    ];

    /**
     * Default general settings
     *
     * @since 2.0.0
     * @var array
     */
    private const DEFAULT_SETTINGS = [
        'notif_enabled' => true,
        'notif_title' => '',
        'notifal_labels' => ['announcement'],
        'content_source_type' => 'dynamic',
    ];

    /**
     * Get default general settings
     *
     * @since 2.0.0
     * @return array
     */
    public function getDefaultSettings(): array
    {
        return apply_filters(FilterHooks::ONPAGE_GENERAL_DEFAULT_SETTINGS, self::DEFAULT_SETTINGS);
    }

    /**
     * Validate and sanitize general settings
     *
     * @since 2.0.0
     * @param array $settings Raw settings array
     * @return array Sanitized settings
     */
    public function sanitizeSettings(array $settings): array
    {
        $sanitized = [];

        // Notification enabled status
        $sanitized['notif_enabled'] = (bool) ($settings['notif_enabled'] ?? self::DEFAULT_SETTINGS['notif_enabled']);

        // Notification title
        $sanitized['notif_title'] = Helper::sanitizeInput(
            $settings['notif_title'] ?? self::DEFAULT_SETTINGS['notif_title'],
            'text'
        );

        // Content source type
        $sanitized['content_source_type'] = $this->sanitizeSelect(
            $settings['content_source_type'] ?? self::DEFAULT_SETTINGS['content_source_type'],
            self::CONTENT_SOURCE_TYPES,
            self::DEFAULT_SETTINGS['content_source_type']
        );

        // Labels (taxonomy)
        $sanitized['notifal_labels'] = $this->sanitizeLabels(
            $settings['notifal_labels'] ?? self::DEFAULT_SETTINGS['notifal_labels']
        );

        return apply_filters(FilterHooks::ONPAGE_GENERAL_SANITIZED_SETTINGS, $sanitized, $settings);
    }

    /**
     * Sanitize labels array
     *
     * @since 2.0.0
     * @param array $labels Raw labels
     * @return array Sanitized labels
     */
    private function sanitizeLabels(array $labels): array
    {
        $sanitized_labels = [];

        foreach ($labels as $label) {
            if (is_string($label) && !empty(trim($label))) {
                $sanitized_labels[] = Helper::sanitizeInput($label, 'text');
            }
        }

        // Ensure at least one default label if none provided
        if (empty($sanitized_labels)) {
            $sanitized_labels = self::DEFAULT_SETTINGS['notifal_labels'];
        }

        // Remove duplicates and limit to reasonable number
        $sanitized_labels = array_unique($sanitized_labels);
        $sanitized_labels = array_slice($sanitized_labels, 0, 10); // Limit to 10 labels max

        return $sanitized_labels;
    }

    /**
     * Validate general settings
     *
     * @since 2.0.0
     * @param array $settings Settings to validate
     * @return array Validation result with 'valid' boolean and optional 'message'
     */
    public function validateSettings(array $settings): array
    {
        // Title validation
        if (isset($settings['notif_title']) && strlen($settings['notif_title']) > 200) {
            return [
                'valid' => false,
                'message' => __('Notification title cannot exceed 200 characters.', 'notifal')
            ];
        }

        // Content source type validation
        if (isset($settings['content_source_type']) && !in_array($settings['content_source_type'], self::CONTENT_SOURCE_TYPES, true)) {
            return [
                'valid' => false,
                'message' => __('Invalid content source type selected.', 'notifal')
            ];
        }

        return ['valid' => true];
    }

    /**
     * Check if pro features are allowed
     *
     * @since 2.0.0
     * @return bool
     */
    protected function isProFeatureAllowed(): bool
    {
        return $this->checkProFeatureAllowed(FilterHooks::ONPAGE_GENERAL_PRO_FEATURES_ALLOWED);
    }
}