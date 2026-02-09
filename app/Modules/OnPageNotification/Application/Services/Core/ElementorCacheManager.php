<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Core;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Handles Elementor-specific cache clearing operations.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ElementorCacheManager
{
    /**
     * Clear Elementor caches when template changes.
     *
     * @param int   $postId        The notification post ID
     * @param array $sanitizedData The sanitized notification data
     * @since 2.0.0
     */
    public function clearElementorCaches(int $postId, array $sanitizedData): void
    {
        if (!PluginDetector::isElementorActive()) {
            return;
        }

        try {
            $template_changed = $this->hasTemplateChanged($postId, $sanitizedData);
            $content_source_changed = $this->hasContentSourceChanged($postId, $sanitizedData);

            // Always clear Elementor caches when notification is saved to prevent stale content
            // This ensures template changes are reflected immediately
            $this->clearElementorCssFiles();

            if (isset($sanitizedData['template_id']) && $sanitizedData['template_id'] > 0) {
                $this->clearElementorTemplateCache($sanitizedData['template_id']);
            }

            $this->clearElementorDynamicCache();
            $this->forceElementorRegeneration();

        } catch (\Throwable $e) {
            Helper::log('ElementorCacheManager: Cache clearing failed for post ' . $postId . ' - ' . $e->getMessage());
        }

        do_action(ActionHooks::ONPAGE_ELEMENTOR_CACHE_CLEARED, $postId, $sanitizedData);
    }

    /**
     * Check if the template has changed for this notification.
     *
     * @param int   $postId        The notification post ID
     * @param array $sanitizedData The sanitized notification data
     * @return bool True if template changed, false otherwise
     * @since 2.0.0
     */
    private function hasTemplateChanged(int $postId, array $sanitizedData): bool
    {
        $previous_template_id = get_post_meta($postId, '_notifal_template_id', true);
        $new_template_id = $sanitizedData['template_id'] ?? 0;

        return empty($previous_template_id) || $previous_template_id != $new_template_id;
    }

    /**
     * Check if content source settings have changed for this notification.
     *
     * @param int   $postId        The notification post ID
     * @param array $sanitizedData The sanitized notification data
     * @return bool True if content source changed, false otherwise
     * @since 2.0.0
     */
    private function hasContentSourceChanged(int $postId, array $sanitizedData): bool
    {
        $previous_settings = get_post_meta($postId, '_notifal_content_source_settings', true);
        $new_settings = $sanitizedData['content_source_settings'] ?? [];

        $previous_json = is_array($previous_settings) ? json_encode($previous_settings) : $previous_settings;
        $new_json = json_encode($new_settings);

        return empty($previous_settings) || $previous_json !== $new_json;
    }

    /**
     * Clear Elementor CSS files to force regeneration.
     *
     * @since 2.0.0
     */
    private function clearElementorCssFiles(): void
    {
        try {
            if (method_exists('\Elementor\Plugin', 'instance')) {
                $elementor = \Elementor\Plugin::instance();

                if (isset($elementor->files_manager)) {
                    $elementor->files_manager->clear_cache();
                }
            }
        } catch (\Throwable $e) {
            Helper::log('ElementorCacheManager: clearElementorCssFiles failed - ' . $e->getMessage());
        }
    }

    /**
     * Clear specific Elementor template cache.
     *
     * @param int $templateId The template ID to clear cache for
     * @since 2.0.0
     */
    private function clearElementorTemplateCache(int $templateId): void
    {
        delete_post_meta($templateId, '_elementor_css');
        delete_transient('elementor_' . $templateId);

        try {
            if (class_exists('\Elementor\Core\Files\CSS\Post')) {
                $css_file = new \Elementor\Core\Files\CSS\Post($templateId);
                $css_file->delete();
            }
        } catch (\Throwable $e) {
            Helper::log('ElementorCacheManager: clearElementorTemplateCache failed for ' . $templateId . ' - ' . $e->getMessage());
        }
    }

    /**
     * Clear Elementor's dynamic content cache.
     *
     * @since 2.0.0
     */
    private function clearElementorDynamicCache(): void
    {
        // Common Elementor transient patterns that should be cleared
        $elementor_transient_patterns = [
            'elementor_css_',
            'elementor_dynamic_',
            'elementor_global_',
            'elementor_library_',
            'elementor_template_'
        ];

        // Clear common Elementor transients directly for better performance
        foreach ($elementor_transient_patterns as $pattern) {
            // Use WordPress transient functions which handle cache groups efficiently
            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group($pattern);
            }
        }

        // Clear specific known Elementor transients
        $known_transients = [
            'elementor_remote_info_api_data_' . md5('elementor'),
            'elementor_upgrade_notice_dismissed',
            'elementor_tracking_last_send'
        ];

        foreach ($known_transients as $transient) {
            delete_transient($transient);
        }
    }

    /**
     * Force Elementor files and data regeneration.
     * Optimized to avoid duplicate cache clearing operations.
     *
     * @since 2.0.0
     */
    private function forceElementorRegeneration(): void
    {
        if (!PluginDetector::isElementorActive()) {
            return;
        }

        try {
            $elementor = \Elementor\Plugin::instance();

            // Clear Elementor options cache first
            $options_to_clear = [
                'elementor_css_print_method',
                'elementor_cache_files_query_string',
                'elementor_element_cache_ttl'
            ];

            foreach ($options_to_clear as $option) {
                delete_option($option);
            }

            // Clear files cache once (avoiding duplicate calls)
            if (isset($elementor->files_manager)) {
                $elementor->files_manager->clear_cache();
            }

            do_action('elementor/core/files/clear_cache');

        } catch (\Throwable $e) {
            Helper::log('ElementorCacheManager: Failed to regenerate Elementor files - ' . $e->getMessage());
        }
    }
}
