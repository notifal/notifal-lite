<?php

namespace Notifal\Modules\Templates\Infrastructure\Shared\Traits;

defined('ABSPATH') || exit;

use WP_Post;

/**
 * Trait TemplateContentTrait
 *
 * Provides methods to check if templates have meaningful content.
 *
 * @package Notifal\Modules\Templates\Infrastructure\Shared\Traits
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
trait TemplateContentTrait
{
    /**
     * Check if a template has meaningful content.
     *
     * @since 2.0.0
     * @param WP_Post $post    The template post object
     * @param string  $builder The builder type ('elementor' or 'block-editor')
     * @return bool True if template has content, false otherwise
     */
    public static function hasTemplateContent(WP_Post $post, string $builder): bool
    {
        if ($builder === 'elementor') {
            return self::hasElementorContent($post);
        } else {
            return self::hasBlockEditorContent($post);
        }
    }

    /**
     * Check if an Elementor template has content.
     *
     * Only returns true when the post is actually built with Elementor (_elementor_data).
     * Block-editor templates use post_content with Gutenberg blocks, so we must not
     * treat them as Elementor based on post_content alone (they would appear in both sections).
     *
     * @since 2.0.0
     * @param WP_Post $post The template post object
     * @return bool True if template has Elementor content, false otherwise
     */
    private static function hasElementorContent(WP_Post $post): bool
    {
        // Check if Elementor data exists and is not empty
        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);

        if (!empty($elementor_data)) {
            $data = json_decode($elementor_data, true);
            if (is_array($data) && !empty($data)) {
                // Check if there's at least one element with meaningful content
                return self::elementorDataHasContent($data);
            }
        }

        // Fallback only when post is clearly not a block-editor template (no Gutenberg blocks).
        // Block-editor templates store content in post_content with <!-- wp: markers;
        // if we used post_content alone, they would wrongly be considered "Elementor" and
        // appear in the Elementor section instead of staying in Block Editor section.
        $post_content = trim($post->post_content ?? '');
        if (empty($post_content)) {
            return false;
        }
        if (strpos($post_content, '<!-- wp:') !== false) {
            // Has Gutenberg blocks: this is a block-editor template, not Elementor
            return false;
        }

        return true;
    }

    /**
     * Check if Elementor data contains meaningful content.
     *
     * @since 2.0.0
     * @param array $data Elementor data array
     * @return bool True if data has content, false otherwise
     */
    private static function elementorDataHasContent(array $data): bool
    {
        foreach ($data as $element) {
            // Check if element has a widget type (indicates it's a real widget)
            if (isset($element['widgetType']) && !empty($element['widgetType'])) {
                // Skip spacer and divider widgets as they don't add meaningful content
                if (!in_array($element['widgetType'], ['spacer', 'divider'], true)) {
                    return true;
                }
            }

            // Check settings for text content
            if (isset($element['settings']) && is_array($element['settings'])) {
                foreach ($element['settings'] as $setting_key => $setting_value) {
                    if (is_string($setting_value) && !empty(trim($setting_value))) {
                        // Check for common content fields
                        if (in_array($setting_key, ['text', 'content', 'title', 'editor'], true)) {
                            return true;
                        }
                    }
                }
            }

            // Recursively check nested elements (inner sections, columns, etc.)
            if (isset($element['elements']) && is_array($element['elements'])) {
                if (self::elementorDataHasContent($element['elements'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a block editor template has content.
     *
     * @since 2.0.0
     * @param WP_Post $post The template post object
     * @return bool True if template has block editor content, false otherwise
     */
    private static function hasBlockEditorContent(WP_Post $post): bool
    {
        $content = trim($post->post_content);

        // Check if post content is not empty
        if (empty($content)) {
            return false;
        }

        // Check if content contains actual blocks (not just empty HTML)
        // Look for Gutenberg block comments or meaningful HTML content
        if (strpos($content, '<!-- wp:') !== false) {
            // Has Gutenberg blocks
            return true;
        }

        // Check if content has meaningful HTML tags (not just whitespace or basic tags)
        $content_without_tags = wp_strip_all_tags($content);
        $content_without_tags = trim($content_without_tags);

        // If there's actual text content, consider it meaningful
        if (!empty($content_without_tags)) {
            return true;
        }

        // Check for other HTML elements that indicate content
        // (paragraphs, headings, lists, etc.)
        if (preg_match('/<(p|h[1-6]|ul|ol|blockquote|div|span)[^>]*>.*?<\/\1>/is', $content)) {
            return true;
        }

        return false;
    }
}
