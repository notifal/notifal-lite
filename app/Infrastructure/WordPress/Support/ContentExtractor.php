<?php

namespace Notifal\Infrastructure\WordPress\Support;

defined('ABSPATH') || exit;

/**
 * Utility class for extracting raw text content from structured WordPress content formats.
 * Provides centralized content extraction logic.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ContentExtractor
{
    /**
     * Extract raw text content containing tags from Elementor template for context detection.
     *
     * @param \WP_Post $template Template post
     * @return string Raw content containing tags for context detection
     * @since 2.0.0
     */
    public static function extractFromElementorTemplate(\WP_Post $template): string
    {
        try {
            // Try to get Elementor data first
            $elementorData = get_post_meta($template->ID, '_elementor_data', true);

            if (empty($elementorData)) {
                return $template->post_content;
            }

            $data = json_decode($elementorData, true);

            if (!is_array($data)) {
                return $template->post_content;
            }

            // Extract all text content that might contain tags
            $extractedContent = self::extractTextFromElementorData($data);

            return $extractedContent ?: $template->post_content;

        } catch (\Exception $e) {
            // Fallback to post content if Elementor data extraction fails
            return $template->post_content;
        }
    }

    /**
     * Extract raw text content containing tags from Block Editor template for context detection.
     *
     * @param \WP_Post $template Template post
     * @return string Raw content containing tags for context detection
     * @since 2.0.0
     */
    public static function extractFromBlockTemplate(\WP_Post $template): string
    {
        try {
            $content = $template->post_content;

            if (empty($content)) {
                return '';
            }

            // Parse blocks to extract text content with tags
            $blocks = parse_blocks($content);

            if (empty($blocks)) {
                return $content;
            }

            // Extract all text content that might contain tags from blocks
            $extractedContent = self::extractTextFromBlocks($blocks);

            return $extractedContent ?: $content;

        } catch (\Exception $e) {
            // Fallback to post content if block parsing fails
            return $template->post_content;
        }
    }

    /**
     * Extract text content from Elementor data array.
     *
     * @param array $data Elementor data array
     * @return string Concatenated text content
     * @since 2.0.0
     */
    public static function extractTextFromElementorData(array $data): string
    {
        $textContent = '';

        foreach ($data as $element) {
            // Extract text from element settings
            if (isset($element['settings']) && is_array($element['settings'])) {
                foreach ($element['settings'] as $key => $value) {
                    if (is_string($value) && !empty($value)) {
                        // Common text fields that might contain tags
                        $textFields = ['title', 'editor', 'text', 'content', 'description', 'label', 'placeholder'];
                        if (in_array($key, $textFields) || strpos($key, 'text') !== false || strpos($key, 'content') !== false) {
                            $textContent .= ' ' . $value;
                        }
                    }
                }
            }

            // Recursively check nested elements
            if (isset($element['elements']) && is_array($element['elements'])) {
                $textContent .= ' ' . self::extractTextFromElementorData($element['elements']);
            }
        }

        return $textContent;
    }

    /**
     * Recursively extract text content from WordPress blocks array.
     *
     * @param array $blocks WordPress blocks array
     * @return string Concatenated text content
     * @since 2.0.0
     */
    private static function extractTextFromBlocks(array $blocks): string
    {
        $textContent = '';

        foreach ($blocks as $block) {
            // Extract content from block innerHTML (this contains the actual text)
            if (isset($block['innerHTML']) && !empty($block['innerHTML'])) {
                // Remove HTML tags but keep the text (tags are in the text)
                $blockText = wp_strip_all_tags($block['innerHTML']);
                $textContent .= ' ' . $blockText;
            }

            // Extract text from block attributes (might contain tags in settings)
            if (isset($block['attrs']) && is_array($block['attrs'])) {
                foreach ($block['attrs'] as $key => $value) {
                    if (is_string($value) && !empty($value)) {
                        // Common text fields that might contain tags
                        $textFields = ['text', 'content', 'title', 'description', 'label', 'placeholder', 'caption'];
                        if (in_array($key, $textFields) || strpos($key, 'text') !== false || strpos($key, 'content') !== false) {
                            $textContent .= ' ' . $value;
                        }
                    }
                }
            }

            // Recursively check nested blocks (inner blocks)
            if (isset($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                $textContent .= ' ' . self::extractTextFromBlocks($block['innerBlocks']);
            }
        }

        return $textContent;
    }
}
