<?php

namespace Notifal\Modules\Templates\Application\Services;

use Notifal\Infrastructure\WordPress\Elementor\Helpers\ElementorHelper;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Shared\Services\BaseExportService;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Class TemplateExportService
 *
 * Handles exporting templates as JSON or ZIP archive.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class TemplateExportService extends BaseExportService
{
    /**
     * Handle export for one or more template posts.
     *
     * @param string $postType Post type name (must be 'notifal_template').
     * @param int[] $ids List of template post IDs.
     * @return void
     * @since 2.0.0
     */
    public static function handle(string $postType, array $ids): void
    {
        parent::handle($postType, $ids);
    }

    /**
     * Get the post type for this export service.
     *
     * @return string Post type.
     * @since 2.0.0
     */
    protected static function getPostType(): string
    {
        return 'notifal_template';
    }

    /**
     * Get the export type identifier for file naming.
     *
     * @return string Export type.
     * @since 2.0.0
     */
    protected static function getExportType(): string
    {
        return 'template';
    }

    /**
     * Generate filename for single export.
     *
     * @param WP_Post $post The post.
     * @return string Filename.
     * @since 2.0.0
     */
    protected static function generateFilename(WP_Post $post): string
    {
        return sanitize_title($post->post_title) . '.json';
    }

    /**
     * Generate filename for ZIP entry.
     *
     * @param WP_Post $post The post.
     * @return string Filename.
     * @since 2.0.0
     */
    protected static function generateZipFilename(WP_Post $post): string
    {
        return sanitize_file_name($post->post_title) . '-' . $post->ID . '.json';
    }

    /**
     * Generate ZIP archive name.
     *
     * @return string Archive name.
     * @since 2.0.0
     */
    protected static function generateZipArchiveName(): string
    {
        return 'notifal-templates-export-' . date('Y-m-d') . '.zip';
    }

    /**
     * Prepare export data array for a template post.
     *
     * Detects Elementor or Block Editor and extracts appropriate content and dependencies.
     * Ensures data integrity by validating post object and handling serialization safely.
     *
     * @param WP_Post $post The template post.
     * @return array Structured export data ready for JSON serialization.
     * @since 2.0.0
     */
    public static function prepareExportData(WP_Post $post): array
    {
        // Validate post object
        if (!$post instanceof WP_Post || $post->post_type !== 'notifal_template') {
            return [];
        }

        $isElementor = ElementorHelper::hasBuilder($post);

        // Safely extract content based on builder type
        $content = $isElementor
            ? self::getElementorContent($post->ID)
            : $post->post_content;

        $data = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'builder' => $isElementor ? 'elementor' : 'block-editor',
            'content' => $content,
        ];

        // Add dependencies for template portability
        $data['dependencies'] = self::extractDependencies($data);

        return apply_filters(FilterHooks::EXPORT_TEMPLATE_DATA, $data, $post);
    }

    /**
     * Safely retrieve and decode Elementor content data.
     *
     * Handles JSON decoding of Elementor data stored as JSON strings.
     *
     * @param int $postId Template post ID.
     * @return array|null Decoded Elementor data or null on failure.
     * @since 2.0.0
     */
    private static function getElementorContent(int $postId): ?array
    {
        $elementorData = get_post_meta($postId, '_elementor_data', true);

        if (empty($elementorData)) {
            return null;
        }

        // Elementor data is stored as JSON string, decode it
        if (is_string($elementorData)) {
            $decoded = json_decode($elementorData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }
            return $decoded;
        }

        // Fallback for cases where data might already be unserialized
        $unserialized = maybe_unserialize($elementorData);

        // Validate that we have proper array data
        return is_array($unserialized) ? $unserialized : null;
    }

    /**
     * Extract dependencies from template data.
     *
     * Processes content to identify images and fonts required for template portability.
     * For Block Editor, also extracts media from block attributes (iconUrl, iconId, core/image id).
     * Performance optimized by early validation and type checking.
     *
     * @param array $templateData Template export data.
     * @return array Dependencies array with images, fonts, and attachment_id_to_url for block editor.
     * @since 2.0.0
     */
    public static function extractDependencies(array $templateData): array
    {
        $dependencies = [
            'images' => [],
            'fonts' => [],
            'attachment_id_to_url' => [],
        ];

        // Validate required template data keys
        if (!isset($templateData['builder'], $templateData['content'])) {
            return $dependencies;
        }

        // Extract image URLs from Elementor data
        if ($templateData['builder'] === 'elementor' && is_array($templateData['content'])) {
            $dependencies['images'] = self::extractImagesFromElementorData($templateData['content']);
        }

        // Extract image URLs and attachment IDs from Block Editor content (blocks + raw HTML)
        if ($templateData['builder'] === 'block-editor' && is_string($templateData['content'])) {
            $blockEditorDeps = self::extractBlockEditorDependencies($templateData['content']);
            $dependencies['images'] = array_unique(array_merge(
                $blockEditorDeps['images'],
                self::extractImagesFromBlockContent($templateData['content'])
            ));
            $dependencies['attachment_id_to_url'] = $blockEditorDeps['attachment_id_to_url'];
        }

        return $dependencies;
    }

    /**
     * Extract media URLs and attachment ID-to-URL map from Block Editor content.
     *
     * Parses blocks via parse_blocks and extracts iconUrl, iconId (notifal/close-icon, notifal/action-button),
     * and attachment id (core/image) so they can be included in export and re-mapped on import.
     *
     * @param string $content Block Editor post content (serialized blocks).
     * @return array{images: string[], attachment_id_to_url: array<int, string>}
     * @since 2.0.0
     */
    public static function extractBlockEditorDependencies(string $content): array
    {
        $images = [];
        $attachmentIdToUrl = [];

        if (empty($content) || !function_exists('parse_blocks')) {
            return ['images' => [], 'attachment_id_to_url' => []];
        }

        $blocks = parse_blocks($content);
        self::collectBlockMediaRecursive($blocks, $images, $attachmentIdToUrl);

        return [
            'images' => array_values(array_unique($images)),
            'attachment_id_to_url' => $attachmentIdToUrl,
        ];
    }

    /**
     * Recursively collect media URLs and attachment IDs from block tree.
     *
     * @param array[] $blocks Array of block arrays from parse_blocks.
     * @param string[] $images Output array of image/icon URLs.
     * @param array<int, string> $attachmentIdToUrl Output map of attachment ID => URL.
     * @return void
     * @since 2.0.0
     */
    private static function collectBlockMediaRecursive(array $blocks, array &$images, array &$attachmentIdToUrl): void
    {
        foreach ($blocks as $block) {
            if (empty($block['blockName'])) {
                continue;
            }

            $attrs = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : [];

            // Notifal icon blocks: iconUrl (URL) and iconId (attachment ID)
            if (isset($attrs['iconUrl']) && is_string($attrs['iconUrl']) && $attrs['iconUrl'] !== '') {
                $url = esc_url_raw($attrs['iconUrl']);
                if ($url) {
                    $images[] = $url;
                }
            }
            if (!empty($attrs['iconId']) && is_numeric($attrs['iconId'])) {
                $id = (int) $attrs['iconId'];
                $url = wp_get_attachment_url($id);
                if ($url) {
                    $images[] = $url;
                    $attachmentIdToUrl[$id] = $url;
                }
            }

            // Core image block: id (attachment ID)
            if ($block['blockName'] === 'core/image' && !empty($attrs['id']) && is_numeric($attrs['id'])) {
                $id = (int) $attrs['id'];
                $url = wp_get_attachment_url($id);
                if ($url) {
                    $images[] = $url;
                    $attachmentIdToUrl[$id] = $url;
                }
            }

            // Notifal advanced background image: notifalBackgroundImageUrl / notifalBackgroundImageId
            if (isset($attrs['notifalBackgroundImageUrl']) && is_string($attrs['notifalBackgroundImageUrl']) && $attrs['notifalBackgroundImageUrl'] !== '') {
                $backgroundUrl = esc_url_raw($attrs['notifalBackgroundImageUrl']);
                if ($backgroundUrl) {
                    $images[] = $backgroundUrl;
                }
            }

            if (!empty($attrs['notifalBackgroundImageId']) && is_numeric($attrs['notifalBackgroundImageId'])) {
                $backgroundId = (int) $attrs['notifalBackgroundImageId'];
                $backgroundAttachmentUrl = wp_get_attachment_url($backgroundId);
                if ($backgroundAttachmentUrl) {
                    $images[] = $backgroundAttachmentUrl;
                    $attachmentIdToUrl[$backgroundId] = $backgroundAttachmentUrl;
                }
            }

            // Recursively process inner blocks
            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                self::collectBlockMediaRecursive($block['innerBlocks'], $images, $attachmentIdToUrl);
            }
        }
    }

    /**
     * Extract image URLs from Elementor data structure.
     *
     * Recursively traverses Elementor widget tree to find image elements.
     * Handles multiple image storage patterns in Elementor:
     * - Image widget: settings->image->url
     * - Background images: settings->background_image->url
     * - Section backgrounds: settings->background_image->url
     * - Custom widgets with image fields
     *
     * @param array $elementorData Elementor data array.
     * @return array Array of unique image URLs.
     * @since 2.0.0
     */
    private static function extractImagesFromElementorData(array $elementorData): array
    {
        $images = [];

        foreach ($elementorData as $element) {
            // Skip invalid elements
            if (!is_array($element)) {
                continue;
            }

            // Extract images from settings
            if (isset($element['settings']) && is_array($element['settings'])) {
                $settings = $element['settings'];

                // Image widget: settings->image->url
                if (isset($settings['image']['url']) && is_string($settings['image']['url']) && !empty($settings['image']['url'])) {
                    $images[] = $settings['image']['url'];
                }

                // Background image: settings->background_image->url
                if (isset($settings['background_image']['url']) && is_string($settings['background_image']['url']) && !empty($settings['background_image']['url'])) {
                    $images[] = $settings['background_image']['url'];
                }

                // Some widgets use 'url' directly (legacy)
                if (isset($settings['url']['url']) && is_string($settings['url']['url']) && !empty($settings['url']['url'])) {
                    $images[] = $settings['url']['url'];
                }

                // Gallery widget: settings->gallery (array of images)
                if (isset($settings['gallery']) && is_array($settings['gallery'])) {
                    foreach ($settings['gallery'] as $galleryItem) {
                        if (isset($galleryItem['url']) && is_string($galleryItem['url']) && !empty($galleryItem['url'])) {
                            $images[] = $galleryItem['url'];
                        }
                    }
                }

                // Carousel/Slider widgets: settings->slides
                if (isset($settings['slides']) && is_array($settings['slides'])) {
                    foreach ($settings['slides'] as $slide) {
                        if (isset($slide['image']['url']) && is_string($slide['image']['url']) && !empty($slide['image']['url'])) {
                            $images[] = $slide['image']['url'];
                        }
                    }
                }

                // Check for any other fields that might contain image URLs
                $images = array_merge($images, self::extractImagesFromSettings($settings));
            }

            // Recursively process nested elements
            if (isset($element['elements']) && is_array($element['elements'])) {
                $nestedImages = self::extractImagesFromElementorData($element['elements']);
                $images = array_merge($images, $nestedImages);
            }
        }

        return array_values(array_unique(array_filter($images)));
    }

    /**
     * Extract image URLs from Elementor settings recursively.
     *
     * Searches through all settings for any fields that look like image URLs.
     * This catches custom widgets or uncommon image placements.
     *
     * @param array $settings Elementor settings array.
     * @return array Array of image URLs found.
     * @since 2.0.0
     */
    private static function extractImagesFromSettings(array $settings): array
    {
        $images = [];

        foreach ($settings as $key => $value) {
            // Skip already processed keys
            if (in_array($key, ['image', 'background_image', 'url', 'gallery', 'slides'], true)) {
                continue;
            }

            // Check if value is an array with 'url' key (common Elementor pattern)
            if (is_array($value) && isset($value['url']) && is_string($value['url']) && !empty($value['url'])) {
                // Verify it looks like an image URL
                if (self::isImageUrl($value['url'])) {
                    $images[] = $value['url'];
                }
            }

            // Recursively check nested arrays
            if (is_array($value)) {
                $nestedImages = self::extractImagesFromSettings($value);
                $images = array_merge($images, $nestedImages);
            }
        }

        return $images;
    }

    /**
     * Check if a URL looks like an image URL.
     *
     * @param string $url URL to check.
     * @return bool True if URL looks like an image.
     * @since 2.0.0
     */
    private static function isImageUrl(string $url): bool
    {
        // Must be a valid URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Check file extension
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'];
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($extension, $imageExtensions, true);
    }

    /**
     * Extract image URLs from Block Editor content.
     *
     * Uses optimized regex patterns to find images in HTML content and inline styles.
     * Performance optimized by combining patterns and reducing array operations.
     *
     * @param string $content Block Editor content.
     * @return array Array of unique image URLs.
     * @since 2.0.0
     */
    private static function extractImagesFromBlockContent(string $content): array
    {
        $images = [];

        // Extract image URLs from img tags - optimized pattern
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $imgMatches)) {
            $images = array_merge($images, $imgMatches[1]);
        }

        // Extract image URLs from background-image CSS - optimized pattern
        if (preg_match_all('/background-image:\s*url\(["\']?([^"\')\s]+)["\']?\)/i', $content, $bgMatches)) {
            $images = array_merge($images, $bgMatches[1]);
        }

        return array_unique($images);
    }
}
