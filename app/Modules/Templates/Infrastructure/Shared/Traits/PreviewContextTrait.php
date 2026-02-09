<?php

namespace Notifal\Modules\Templates\Infrastructure\Shared\Traits;

use Notifal\Infrastructure\WordPress\Support\PluginDetector;

defined('ABSPATH') || exit;

/**
 * Trait PreviewContextTrait
 *
 * Shared functionality for detecting preview contexts across different template builders.
 * Provides standardized methods to determine if content should be processed for preview.
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Infrastructure\Shared\Traits
 * @author Hossein <hossein@notifal.com>
 */
trait PreviewContextTrait
{
    /**
     * Check if we're in an actual preview context (not frontend notification rendering)
     *
     * @return bool True if in preview context, false otherwise
     * @since 2.0.0
     */
    protected function isActualPreviewContext(): bool
    {
        // Check if we're in Elementor editor mode
        if (isset($_GET['action']) && $_GET['action'] === 'elementor') {
            return true;
        }

        // Check if we're in Elementor preview mode
        if (isset($_GET['elementor-preview'])) {
            return true;
        }

        // Check if we're in template preview mode
        if (isset($_GET['notifal_template_preview'])) {
            return true;
        }

        // Check if Elementor is in preview mode
        if (PluginDetector::isElementorActive() && \Elementor\Plugin::$instance->preview->is_preview_mode()) {
            return true;
        }

        // Check if we're in admin context (for editor)
        if (is_admin()) {
            return true;
        }

        // Default: not in preview context (frontend notifications should use dynamic data)
        return false;
    }

    /**
     * Check if we're in an active notification rendering context
     *
     * @return bool True if in active notification context, false otherwise
     * @since 2.0.0
     */
    protected function isInActiveNotificationContext(): bool
    {
        // Check if WidgetContextProvider indicates active notification rendering
        if (class_exists('\Notifal\Modules\OnPageNotification\Application\Services\Utility\WidgetContextProvider')) {
            return \Notifal\Modules\OnPageNotification\Application\Services\Utility\WidgetContextProvider::isActive();
        }

        return false;
    }

    /**
     * Check if we're in template preview mode via query parameter
     *
     * @return bool True if in template preview mode, false otherwise
     * @since 2.0.0
     */
    protected static function isTemplatePreviewMode(): bool
    {
        return isset($_GET['notifal_template_preview']);
    }
}