<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\Import;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Shared\Services\MediaImportService;
use Notifal\Shared\Utils\FileImportHelper;
use Notifal\Modules\Templates\Application\Services\TemplateBuilderDetector;
use Notifal\Modules\Templates\Infrastructure\WordPress\HtmlBuilder\Services\HtmlTemplateSanitizer;
use Notifal\Shared\Utils\Helper;
use ZipArchive;

defined('ABSPATH') || exit;

/**
 * Class Importer
 *
 * Handles importing notifal_template posts from .json or .zip files.
 * Provides validation, duplicate detection, and proper error handling
 * for template import operations.
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\Import
 * @author Hossein <hossein@notifal.com>
 */
class Importer
{
    /**
     * Import one or more templates from a file path.
     *
     * Detects file type (JSON or ZIP), validates data structure,
     * and imports templates with duplicate detection.
     *
     * @since 2.0.0
     * @param string $filePath Absolute path to the uploaded file.
     * @return array {
     *     @type int    $success      Number of successfully imported templates.
     *     @type int    $failed       Number of failed imports.
     *     @type string $first_title  Title of the first imported template (empty if ZIP or failed).
     *     @type bool   $is_zip       Whether the file was a ZIP archive.
     *     @type array  $errors       Array of error messages for failed imports.
     * }
     */
    public static function importFromFile(string $filePath): array
    {
        $firstTitle = '';

        // Define processor for JSON imports (single template)
        $jsonProcessor = function ($json) use (&$firstTitle) {
            $result = self::importFromData($json);

            // Extract title for response metadata on success
            if ($result['success'] && isset($json['title'])) {
                $firstTitle = Helper::sanitizeInput($json['title'], 'text');
            }

            return $result;
        };

        // Define processor for ZIP imports (multiple templates with bundled media)
        $zipProcessor = function ($jsonData, $bundledMedia = []) use (&$firstTitle) {
            $result = self::importFromData($jsonData, $bundledMedia);

            // Extract title from first successful import for response metadata
            if (empty($firstTitle) && $result['success'] && isset($jsonData['title'])) {
                $firstTitle = Helper::sanitizeInput($jsonData['title'], 'text');
            }

            return $result;
        };

        // Process file import using shared helper
        $result = FileImportHelper::processFileImport($filePath, $jsonProcessor, $zipProcessor);

        // Add template-specific metadata
        $result['first_title'] = $firstTitle;

        // Apply filter for extensibility
        return apply_filters(FilterHooks::TEMPLATE_IMPORT_RESULT, $result, $filePath);
    }

    /**
     * Import a template from structured data array.
     *
     * Performs validation, duplicate detection, and creates or updates
     * template posts based on content hash. Supports bundled media files
     * for marketplace imports where source URLs may not be accessible.
     *
     * @since 2.0.0
     * @param array $data Template data array with 'builder' and 'content' keys.
     * @param array $bundledMedia Map of bundled path => temp file path for bundled media.
     * @return array Array with 'success' boolean and 'error' string if failed.
     */
    public static function importFromData(array $data, array $bundledMedia = []): array
    {

        // Validate import data structure
        $validation = self::validateImportData($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error']
            ];
        }

        try {
            // Check for existing template by content hash
            $existingTemplate = self::findExistingTemplate($data);
            if ($existingTemplate) {
                // Template already exists, return success
                return [
                    'success' => true,
                    'template_id' => $existingTemplate->ID
                ];
            }


            // Create new template with bundled media support
            $result = self::createNewTemplate($data, $bundledMedia);


            return $result;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => sprintf(__('Template import failed: %s', 'notifal'), $e->getMessage())
            ];
        }
    }

    /**
     * Validate import data structure and required fields.
     *
     * @since 2.0.0
     * @param array $data Import data to validate.
     * @return array Array with 'valid' boolean and 'error' string if invalid.
     */
    private static function validateImportData(array $data): array
    {
        // Check required top-level fields
        if (!isset($data['builder']) || !isset($data['content'])) {
            return [
                'valid' => false,
                'error' => __('Invalid template data structure. Missing required "builder" or "content" fields.', 'notifal')
            ];
        }

        // Normalize builder slug (handles notifal_html_builder, html-builder, block-editor aliases).
        $builder = TemplateBuilderDetector::normalizeBuilderSlug(
            Helper::sanitizeInput($data['builder'], 'key')
        );

        // Validate builder type against canonical slugs.
        $supportedBuilders = [
            TemplateBuilderDetector::BUILDER_ELEMENTOR,
            TemplateBuilderDetector::BUILDER_BLOCK_EDITOR,
            TemplateBuilderDetector::BUILDER_HTML,
        ];

        if (!in_array($builder, $supportedBuilders, true)) {
            return [
                'valid' => false,
                'error' => sprintf(
                    __('Unsupported builder type: %s. Supported builders: %s', 'notifal'),
                    Helper::sanitizeInput($data['builder'], 'key'),
                    implode(', ', $supportedBuilders)
                ),
            ];
        }

        // Validate content based on builder.
        if ($builder === TemplateBuilderDetector::BUILDER_ELEMENTOR) {
            $content = $data['content'];
            if (is_string($content)) {
                $content = json_decode($content, true);
            }

            if (!is_array($content) || empty($content)) {
                return [
                    'valid' => false,
                    'error' => __('Invalid Elementor template content. Content must be a valid JSON array.', 'notifal'),
                ];
            }
        } elseif ($builder === TemplateBuilderDetector::BUILDER_BLOCK_EDITOR) {
            $content = $data['content'];
            if (!is_string($content) && !is_array($content)) {
                return [
                    'valid' => false,
                    'error' => __('Invalid content format for block editor. Content must be a string or array.', 'notifal'),
                ];
            }
        } elseif ($builder === TemplateBuilderDetector::BUILDER_HTML) {
            $content = $data['content'];
            if (!is_string($content) || trim($content) === '') {
                return [
                    'valid' => false,
                    'error' => __('Invalid HTML Builder template content. Content must be a non-empty HTML string.', 'notifal'),
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Find existing template by content hash to prevent duplicates.
     *
     * @since 2.0.0
     * @param array $templateData Template data array.
     * @return \WP_Post|null Existing template post or null.
     */
    private static function findExistingTemplate(array $templateData): ?\WP_Post
    {
        $contentHash = md5(serialize($templateData['content']));

        $args = [
            'post_type' => 'notifal_template',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_notifal_content_hash',
                    'value' => $contentHash,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1,
        ];

        $posts = get_posts($args);
        return !empty($posts) ? $posts[0] : null;
    }

    /**
     * Create new template from validated import data.
     *
     * Handles both URL-based media imports (from live servers) and bundled media
     * imports (from marketplace ZIP packages).
     *
     * @since 2.0.0
     * @param array $templateData Validated template data.
     * @param array $bundledMedia Map of bundled path => temp file path for marketplace imports.
     * @return array Array with 'success' boolean, 'template_id' int or 'error' string.
     */
    private static function createNewTemplate(array $templateData, array $bundledMedia = []): array
    {
        $title = Helper::sanitizeInput(
            $templateData['title'] ?? __('Imported Template', 'notifal'),
            'text'
        );
        $builder = TemplateBuilderDetector::normalizeBuilderSlug(
            Helper::sanitizeInput($templateData['builder'], 'key')
        );

        // Apply filter for custom title logic
        $title = apply_filters(FilterHooks::TEMPLATE_DEFAULT_TITLE, $title, $templateData);

        // Set default status for imported templates with filter for customization
        $status = 'publish';
        $status = apply_filters(FilterHooks::TEMPLATE_IMPORT_POST_STATUS, $status, $templateData);

        // Ensure valid status
        if (!in_array($status, ['publish', 'draft', 'private'], true)) {
            $status = 'publish';
        }

        // Create template post
        $postId = wp_insert_post([
            'post_title' => $title,
            'post_type' => 'notifal_template',
            'post_status' => $status,
        ]);

        if (is_wp_error($postId) || !$postId) {
            return [
                'success' => false,
                'error' => __('Failed to create template post.', 'notifal')
            ];
        }

        // Apply filter for final title
        $finalTitle = apply_filters(FilterHooks::TEMPLATE_FINAL_TITLE, $title, $postId);

        // Update title if modified by filter
        if ($finalTitle !== $title) {
            wp_update_post([
                'ID' => $postId,
                'post_title' => $finalTitle
            ]);
        }

        // Import media files and update URLs in template data
        // Pass bundled media for marketplace imports (bundled files take priority over URL downloads)
        $templateData = MediaImportService::importMediaAndUpdateData($templateData, $builder, $bundledMedia);

        // Save content based on builder type
        $contentResult = self::saveTemplateContent($postId, $builder, $templateData['content']);
        if (!$contentResult['success']) {
            // Clean up post on content save failure
            wp_delete_post($postId, true);
            return $contentResult;
        }

        // Content hash is now saved below with error checking

        // Save builder metadata using the normalized slug.
        update_post_meta($postId, '_notifal_builder', $builder);

        // Save content hash for future duplicate detection
        $contentHash = md5(serialize($templateData['content']));
        update_post_meta($postId, '_notifal_content_hash', $contentHash);

        return [
            'success' => true,
            'template_id' => $postId
        ];
    }

    /**
     * Save template content based on builder type.
     *
     * @since 2.0.0
     * @param int $postId Template post ID.
     * @param string $builder Builder type (elementor, block_editor, gutenberg).
     * @param mixed $content Template content.
     * @return array Array with 'success' boolean and 'error' string if failed.
     */
    private static function saveTemplateContent(int $postId, string $builder, $content): array
    {
        try {
            $builder = TemplateBuilderDetector::normalizeBuilderSlug($builder);

            if ($builder === TemplateBuilderDetector::BUILDER_ELEMENTOR) {
                return self::saveElementorContent($postId, $content);
            }

            if ($builder === TemplateBuilderDetector::BUILDER_BLOCK_EDITOR) {
                return self::saveBlockEditorContent($postId, $content);
            }

            if ($builder === TemplateBuilderDetector::BUILDER_HTML) {
                return self::saveHtmlBuilderContent($postId, $content);
            }

            return [
                'success' => false,
                'error' => sprintf(__('Unsupported builder type: %s', 'notifal'), $builder),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => sprintf(__('Failed to save template content: %s', 'notifal'), $e->getMessage())
            ];
        }
    }

    /**
     * Save Elementor template content.
     *
     * @since 2.0.0
     * @param int $postId Template post ID.
     * @param mixed $content Elementor content data.
     * @return array Array with 'success' boolean and 'error' string if failed.
     */
    private static function saveElementorContent(int $postId, $content): array
    {
        // Handle case where content might be a JSON string (from export)
        $elementorData = $content;
        if (is_string($elementorData)) {
            $elementorData = json_decode($elementorData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'error' => __('Invalid Elementor content format.', 'notifal')
                ];
            }
        }

        if (!is_array($elementorData)) {
            return [
                'success' => false,
                'error' => __('Invalid Elementor content format.', 'notifal')
            ];
        }

        // Save Elementor metadata with error checking
        $meta_updates = [
            '_elementor_edit_mode' => 'builder',
            '_elementor_template_type' => 'page',
            '_elementor_version' => defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '3.0.0',
            '_wp_page_template' => 'default'
        ];

        foreach ($meta_updates as $key => $value) {
            $result = update_post_meta($postId, $key, $value);
            if ($result === false) {
                return [
                    'success' => false,
                    'error' => sprintf(__('Failed to save Elementor metadata: %s', 'notifal'), $key)
                ];
            }
        }

        $jsonData = wp_slash(wp_json_encode($elementorData));
        if ($jsonData === false) {
            return [
                'success' => false,
                'error' => __('Failed to encode Elementor data.', 'notifal')
            ];
        }

        $result = update_post_meta($postId, '_elementor_data', $jsonData);
        if ($result === false) {
            return [
                'success' => false,
                'error' => __('Failed to save Elementor data.', 'notifal')
            ];
        }

        return ['success' => true];
    }

    /**
     * Save Block Editor template content.
     *
     * Applies wp_slash() to preserve JSON escape sequences in block attributes
     * which are stripped by wp_update_post()'s internal wp_unslash() call.
     *
     * Common escape sequences that need preservation:
     * - \u003c (for <), \u003e (for >) - Used in SVG icons
     * - \u0022 (for ") - Used in quoted strings
     * - \n (newline) - Used in custom CSS with line breaks
     * - \t (tab) - Used in formatted code
     *
     * Without wp_slash(), these become corrupted:
     * - SVG: "\u003csvg..." becomes "u003csvg..." (invalid)
     * - CSS: "selector {\nheight: 100px;\n}" becomes "selector {nheight: 100px;n}" (broken)
     *
     * @since 2.0.0
     * @param int $postId Template post ID.
     * @param mixed $content Block editor content.
     * @return array Array with 'success' boolean and 'error' string if failed.
     */
    private static function saveBlockEditorContent(int $postId, $content): array
    {
        $postContent = is_string($content) ? $content : '';

        // Use wp_slash() to preserve escape sequences in block JSON attributes.
        // WordPress internally calls wp_unslash() in wp_update_post(), so we need
        // to pre-slash the content to preserve \uXXXX sequences in block comments.
        $slashedContent = wp_slash($postContent);

        $result = wp_update_post([
            'ID' => $postId,
            'post_content' => $slashedContent,
        ]);

        if (is_wp_error($result)) {
            Helper::logAdvanced(
                sprintf(
                    'Failed to save block editor content for template ID %d: %s',
                    $postId,
                    $result->get_error_message()
                ),
                'ERROR'
            );
            return [
                'success' => false,
                'error' => __('Failed to save block editor content.', 'notifal')
            ];
        }

        return ['success' => true];
    }

    /**
     * Save HTML Builder template content.
     *
     * @since 2.4.0
     * @param int   $postId  Template post ID.
     * @param mixed $content HTML string content.
     * @return array Array with 'success' boolean and 'error' string if failed.
     */
    private static function saveHtmlBuilderContent(int $postId, $content): array
    {
        $postContent = is_string($content) ? $content : '';
        $sanitized = HtmlTemplateSanitizer::sanitize($postContent);

        $result = wp_update_post([
            'ID'           => $postId,
            'post_content' => $sanitized['content'],
        ]);

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error'   => __('Failed to save HTML Builder content.', 'notifal'),
            ];
        }

        update_post_meta($postId, '_notifal_builder', TemplateBuilderDetector::BUILDER_HTML);

        return ['success' => true];
    }
}

