<?php

namespace Notifal\Shared\Services;

use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class MediaImportService
 *
 * Handles importing media files (images) from external URLs to local WordPress media library.
 * Provides functionality to download images and replace URLs in template data for both
 * Elementor and block editor templates.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class MediaImportService
{
    /**
     * Import media files and update template data with local URLs.
     *
     * Supports both URL-based imports (downloads from source URLs) and bundled media
     * imports (uploads from temporary files in marketplace packages).
     *
     * @since 2.0.0
     * @param array $templateData Template data array containing content.
     * @param string $builder Builder type ('elementor' or 'block_editor').
     * @param array $bundledMedia Map of bundled path => temp file path (for marketplace imports).
     * @return array Updated template data with local media URLs.
     */
    public static function importMediaAndUpdateData(array $templateData, string $builder, array $bundledMedia = []): array
    {
        if ($builder === 'elementor') {
            return self::processElementorData($templateData, $bundledMedia);
        } elseif (in_array($builder, ['block_editor', 'gutenberg', 'block-editor'], true)) {
            return self::processBlockEditorData($templateData, $bundledMedia);
        }

        return $templateData;
    }

    /**
     * Process Elementor template data to import images.
     *
     * Handles both bundled media (marketplace) and URL-based media (live servers).
     *
     * @since 2.0.0
     * @param array $templateData Template data array.
     * @param array $bundledMedia Map of bundled path => temp file path.
     * @return array Updated template data with local image URLs.
     */
    private static function processElementorData(array $templateData, array $bundledMedia = []): array
    {
        if (!isset($templateData['content']) || !is_array($templateData['content'])) {
            return $templateData;
        }

        $templateData['content'] = self::processElementorElements($templateData['content'], $bundledMedia);
        return $templateData;
    }

    /**
     * Recursively process Elementor elements to find and import images.
     *
     * @since 2.0.0
     * @param array $elements Array of Elementor elements.
     * @param array $bundledMedia Map of bundled path => temp file path.
     * @return array Updated elements with local image URLs.
     */
    private static function processElementorElements(array $elements, array $bundledMedia = []): array
    {
        foreach ($elements as &$element) {
            // Process element settings
            if (isset($element['settings']) && is_array($element['settings'])) {
                $element['settings'] = self::processElementorSettings($element['settings'], $bundledMedia);
            }

            // Recursively process nested elements
            if (isset($element['elements']) && is_array($element['elements'])) {
                $element['elements'] = self::processElementorElements($element['elements'], $bundledMedia);
            }
        }

        return $elements;
    }

    /**
     * Process Elementor element settings to find and import images.
     *
     * @since 2.0.0
     * @param array $settings Element settings array.
     * @param array $bundledMedia Map of bundled path => temp file path.
     * @return array Updated settings with local image URLs.
     */
    private static function processElementorSettings(array $settings, array $bundledMedia = []): array
    {
        $imageFields = ['image', 'background_image', 'url', 'src'];

        foreach ($imageFields as $field) {
            if (isset($settings[$field])) {
                $settings[$field] = self::processImageField($settings[$field], $bundledMedia);
            }
        }

        // Handle background images in background settings
        if (isset($settings['background_background']) &&
            $settings['background_background'] === 'classic' &&
            isset($settings['background_image'])) {
            $settings['background_image'] = self::processImageField($settings['background_image'], $bundledMedia);
        }

        return $settings;
    }

    /**
     * Process block editor template data to import images and icons.
     *
     * Handles both bundled media (marketplace) and URL-based media (live servers).
     * Bundled media takes priority - if [BUNDLED]media/file.jpg marker exists,
     * uploads from temp file instead of downloading from URL.
     *
     * @since 2.0.0
     * @param array $templateData Template data array (content, dependencies optional).
     * @param array $bundledMedia Map of bundled path => temp file path.
     * @return array Updated template data with local media URLs and attachment IDs.
     */
    private static function processBlockEditorData(array $templateData, array $bundledMedia = []): array
    {
        if (!isset($templateData['content']) || !is_string($templateData['content'])) {
            return $templateData;
        }

        $content = $templateData['content'];
        $dependencies = isset($templateData['dependencies']) && is_array($templateData['dependencies'])
            ? $templateData['dependencies']
            : [];

        // Process bundled media first (marketplace imports)
        $bundledToNewUrl = [];
        $bundledToNewId = [];
        if (!empty($bundledMedia)) {
            foreach ($bundledMedia as $bundledPath => $tempFilePath) {
                $result = self::uploadBundledMediaToLibrary($tempFilePath, basename($bundledPath));
                if ($result['success']) {
                    $bundledToNewUrl['[BUNDLED]' . $bundledPath] = $result['url'];
                    $bundledToNewId[$bundledPath] = $result['attachment_id'];
                }
            }
        }

        // Collect all media URLs from content (img src, block JSON iconUrl/url) and from dependencies
        $urlsToImport = self::collectBlockEditorMediaUrls($content, $dependencies);

        // Download each external URL and build old_url => new_url and old_url => new_attachment_id
        // When we have bundled media, try matching by filename first (content may have URLs, no [BUNDLED])
        $urlToNewUrl = [];
        $urlToNewId = [];
        foreach ($urlsToImport as $oldUrl) {
            if (!self::isExternalImageUrl($oldUrl)) {
                continue;
            }
            $newUrl = null;
            if (!empty($bundledMedia)) {
                $tempPath = self::getBundledTempPathByUrlBasename($oldUrl, $bundledMedia);
                if ($tempPath !== null) {
                    $result = self::uploadBundledMediaToLibrary($tempPath, basename(parse_url($oldUrl, PHP_URL_PATH)));
                    if ($result['success']) {
                        $newUrl = $result['url'];
                        $urlToNewId[$oldUrl] = $result['attachment_id'];
                    }
                }
            }
            if ($newUrl === null) {
                $newUrl = self::downloadImageToMedia($oldUrl);
                if ($newUrl && $newUrl !== $oldUrl) {
                    $attachment = self::getAttachmentByUrl($oldUrl);
                    if ($attachment) {
                        $urlToNewId[$oldUrl] = $attachment->ID;
                    }
                }
            }
            if ($newUrl && $newUrl !== $oldUrl) {
                $urlToNewUrl[$oldUrl] = $newUrl;
            }
        }

        // Build old attachment ID => new attachment ID from dependencies (exported attachment_id_to_url)
        $idToNewId = [];
        if (!empty($dependencies['attachment_id_to_url']) && is_array($dependencies['attachment_id_to_url'])) {
            foreach ($dependencies['attachment_id_to_url'] as $oldId => $oldUrl) {
                if (isset($urlToNewId[$oldUrl])) {
                    $idToNewId[(int) $oldId] = $urlToNewId[$oldUrl];
                }
            }
        }

        // Merge bundled and URL-based replacements (bundled takes priority)
        $urlToNewUrl = array_merge($urlToNewUrl, $bundledToNewUrl);

        $templateData['content'] = self::processBlockEditorContent(
            $content,
            $urlToNewUrl,
            $idToNewId
        );

        return $templateData;
    }

    /**
     * Collect all media URLs that appear in Block Editor content or dependencies.
     *
     * @since 2.0.0
     * @param string $content Block editor post content.
     * @param array $dependencies Optional dependencies from export (images, attachment_id_to_url).
     * @return string[] Unique URLs to import.
     */
    private static function collectBlockEditorMediaUrls(string $content, array $dependencies): array
    {
        $urls = [];

        // Skip collecting from dependencies if media is bundled (bundled_media exists)
        // Bundled media is handled separately via $bundledMedia parameter
        if (empty($dependencies['bundled_media'])) {
            // From dependencies (export included these) - only for non-bundled imports
            if (!empty($dependencies['images']) && is_array($dependencies['images'])) {
                $urls = array_merge($urls, $dependencies['images']);
            }

            // From attachment_id_to_url: resolve IDs to URLs so we download them
            if (!empty($dependencies['attachment_id_to_url']) && is_array($dependencies['attachment_id_to_url'])) {
                $urls = array_merge($urls, array_values($dependencies['attachment_id_to_url']));
            }
        }

        // From content: img src (skip [BUNDLED] markers)
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $imgMatches)) {
            $urls = array_merge($urls, $imgMatches[1]);
        }

        // From content: block JSON attributes (iconUrl, url, src) (skip [BUNDLED] markers)
        if (preg_match_all('/"(?:iconUrl|url|src)"\s*:\s*"([^"]+)"/', $content, $jsonMatches)) {
            $urls = array_merge($urls, $jsonMatches[1]);
        }

        // From content: background-image url() in inline styles (skip [BUNDLED] markers)
        if (preg_match_all('/background-image:\s*url\(["\']?([^"\')\s]+)["\']?\)/i', $content, $bgMatches)) {
            $urls = array_merge($urls, $bgMatches[1]);
        }

        // Filter out [BUNDLED] markers - these are handled separately
        $urls = array_filter($urls, function($url) {
            return strpos($url, '[BUNDLED]') !== 0;
        });

        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * Process block editor content: replace old media URLs and attachment IDs with imported ones.
     *
     * @since 2.0.0
     * @param string $content Block editor content (HTML + block comments).
     * @param array<string, string> $urlToNewUrl Map of old URL => new local URL.
     * @param array<int, int> $idToNewId Map of old attachment ID => new attachment ID.
     * @return string Updated content.
     */
    private static function processBlockEditorContent(
        string $content,
        array $urlToNewUrl = [],
        array $idToNewId = []
    ): string {
        // Replace all old URLs with new URLs (in img tags and in block JSON)
        foreach ($urlToNewUrl as $oldUrl => $newUrl) {
            if ($oldUrl !== $newUrl) {
                $content = str_replace($oldUrl, $newUrl, $content);
            }
        }

        // Replace attachment IDs in block JSON (iconId, core/image id) with new IDs
        foreach ($idToNewId as $oldId => $newId) {
            if ($oldId === $newId) {
                continue;
            }
            // Only replace numeric occurrences that are attribute values (iconId / id)
            $content = preg_replace(
                '/"iconId"\s*:\s*' . preg_quote((string) $oldId, '/') . '(?=\s*[,}])/',
                '"iconId": ' . (int) $newId,
                $content
            );
            $content = preg_replace(
                '/"id"\s*:\s*' . preg_quote((string) $oldId, '/') . '(?=\s*[,}])/',
                '"id": ' . (int) $newId,
                $content
            );
        }

        return $content;
    }

    /**
     * Process an image field (could be string URL or array with URL).
     *
     * Handles bundled media markers ([BUNDLED]media/file.jpg) for marketplace imports.
     *
     * @since 2.0.0
     * @param mixed $field Image field value.
     * @param array $bundledMedia Map of bundled path => temp file path.
     * @return mixed Updated field with local image URL.
     */
    private static function processImageField($field, array $bundledMedia = [])
    {
        // Handle string fields
        if (is_string($field)) {
            // Check for bundled media marker
            if (strpos($field, '[BUNDLED]') === 0 && !empty($bundledMedia)) {
                $bundledPath = str_replace('[BUNDLED]', '', $field);
                if (isset($bundledMedia[$bundledPath])) {
                    $result = self::uploadBundledMediaToLibrary($bundledMedia[$bundledPath], basename($bundledPath));
                    if ($result['success']) {
                        return $result['url'];
                    }
                }
            }

            // Fallback: content has URL but we have bundled media (ZIP had no [BUNDLED] replacement)
            // Match by filename so marketplace/legacy exports still work
            if (!empty($bundledMedia) && self::isExternalImageUrl($field)) {
                $tempPath = self::getBundledTempPathByUrlBasename($field, $bundledMedia);
                if ($tempPath !== null) {
                    $result = self::uploadBundledMediaToLibrary($tempPath, basename(parse_url($field, PHP_URL_PATH)));
                    if ($result['success']) {
                        return $result['url'];
                    }
                }
            }

            // Handle external URLs (download when no bundled match)
            if (self::isExternalImageUrl($field)) {
                return self::downloadImageToMedia($field);
            }
        }

        // Handle array fields (Elementor image objects)
        if (is_array($field) && isset($field['url'])) {
            // Check for bundled media marker in URL
            if (is_string($field['url']) && strpos($field['url'], '[BUNDLED]') === 0 && !empty($bundledMedia)) {
                $bundledPath = str_replace('[BUNDLED]', '', $field['url']);
                if (isset($bundledMedia[$bundledPath])) {
                    $result = self::uploadBundledMediaToLibrary($bundledMedia[$bundledPath], basename($bundledPath));
                    if ($result['success']) {
                        $field['url'] = $result['url'];
                        $field['id'] = $result['attachment_id'];
                        return $field;
                    }
                }
            }

            // Fallback: URL in content but we have bundled media (match by filename)
            if (!empty($bundledMedia) && is_string($field['url']) && self::isExternalImageUrl($field['url'])) {
                $tempPath = self::getBundledTempPathByUrlBasename($field['url'], $bundledMedia);
                if ($tempPath !== null) {
                    $result = self::uploadBundledMediaToLibrary($tempPath, basename(parse_url($field['url'], PHP_URL_PATH)));
                    if ($result['success']) {
                        $field['url'] = $result['url'];
                        $field['id'] = $result['attachment_id'];
                        return $field;
                    }
                }
            }

            // Handle external URLs (download when no bundled match)
            if (self::isExternalImageUrl($field['url'])) {
                $field['url'] = self::downloadImageToMedia($field['url']);
                // Update id if we have attachment data
                $attachment = self::getAttachmentByUrl($field['url']);
                if ($attachment) {
                    $field['id'] = $attachment->ID;
                }
            }
        }

        return $field;
    }

    /**
     * Find bundled temp file path by matching URL basename to bundled media.
     *
     * When content has original URLs (no [BUNDLED] markers) but we have bundled
     * media in the ZIP, match by filename so we use the bundled file instead of
     * downloading (e.g. marketplace/legacy exports).
     *
     * @since 2.0.0
     * @param string $imageUrl Image URL from content (e.g. http://example.com/uploads/blackfriday.webp).
     * @param array<string, string> $bundledMedia Map of bundled path => temp file path.
     * @return string|null Temp file path if a bundled file with same basename exists, null otherwise.
     */
    private static function getBundledTempPathByUrlBasename(string $imageUrl, array $bundledMedia): ?string
    {
        if (empty($bundledMedia)) {
            return null;
        }

        $urlPath = parse_url($imageUrl, PHP_URL_PATH);
        if ($urlPath === false || $urlPath === null) {
            return null;
        }

        $basename = basename($urlPath);
        if ($basename === '') {
            return null;
        }

        foreach ($bundledMedia as $bundledPath => $tempPath) {
            if (!is_string($tempPath)) {
                continue;
            }
            if (basename($bundledPath) === $basename && file_exists($tempPath) && is_readable($tempPath)) {
                return $tempPath;
            }
        }

        return null;
    }

    /**
     * Check if URL is an external image URL that should be imported.
     *
     * @since 2.0.0
     * @param string $url Image URL to check.
     * @return bool True if URL is external and should be imported.
     */
    private static function isExternalImageUrl(string $url): bool
    {
        // Skip if not a valid URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Skip if it's already a local URL
        $siteUrl = get_site_url();
        if (strpos($url, $siteUrl) === 0) {
            return false;
        }

        // Check if it's an image extension
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($extension, $imageExtensions, true);
    }

    /**
     * Download image from URL and add to WordPress media library.
     *
     * @since 2.0.0
     * @param string $imageUrl External image URL.
     * @return string Local image URL or original URL if download failed.
     */
    private static function downloadImageToMedia(string $imageUrl): string
    {
        // Check if we already imported this image
        $existingAttachment = self::getAttachmentByUrl($imageUrl);
        if ($existingAttachment) {
            return wp_get_attachment_url($existingAttachment->ID);
        }

        // Download the image
        $response = wp_remote_get($imageUrl, [
            'timeout' => 30,
            'redirection' => 5,
            'user-agent' => 'Notifal Template Import/' . NOTIFAL_VERSION,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            Helper::logAdvanced('Failed to download image from URL: ' . $imageUrl . ' - Response code: ' . wp_remote_retrieve_response_code($response), 'ERROR');
            return $imageUrl; // Return original URL if download failed
        }

        $imageData = wp_remote_retrieve_body($response);
        if (empty($imageData)) {
            return $imageUrl;
        }

        // Get image filename
        $filename = self::getImageFilename($imageUrl);
        if (!$filename) {
            return $imageUrl;
        }

        // Upload to media library
        $upload = wp_upload_bits($filename, null, $imageData);
        if ($upload['error']) {
            Helper::logAdvanced('Failed to upload image to media library: ' . $filename . ' - Error: ' . $upload['error'], 'ERROR');
            return $imageUrl;
        }

        // Create attachment
        $attachment = [
            'post_mime_type' => wp_check_filetype($filename)['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        $attachmentId = wp_insert_attachment($attachment, $upload['file']);

        if (is_wp_error($attachmentId)) {
            Helper::logAdvanced('Failed to create attachment for URL: ' . $imageUrl . ' - Error: ' . $attachmentId->get_error_message(), 'ERROR');
            return $imageUrl;
        }

        // Generate metadata
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachmentData = wp_generate_attachment_metadata($attachmentId, $upload['file']);
        wp_update_attachment_metadata($attachmentId, $attachmentData);

        // Store original URL for future reference
        update_post_meta($attachmentId, '_notifal_imported_from_url', $imageUrl);

        return wp_get_attachment_url($attachmentId);
    }

    /**
     * Get image filename from URL.
     *
     * @since 2.0.0
     * @param string $url Image URL.
     * @return string|null Filename or null if invalid.
     */
    private static function getImageFilename(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return null;
        }

        $filename = basename($path);
        if (!$filename || strpos($filename, '.') === false) {
            // Generate filename if not present
            $extension = self::getImageExtensionFromContent($url);
            $filename = 'imported-image-' . time() . '.' . $extension;
        }

        return sanitize_file_name($filename);
    }

    /**
     * Get image extension by checking content type (fallback method).
     *
     * @since 2.0.0
     * @param string $url Image URL.
     * @return string Default extension if unable to determine.
     */
    private static function getImageExtensionFromContent(string $url): string
    {
        $response = wp_remote_head($url, ['timeout' => 10]);
        if (!is_wp_error($response)) {
            $contentType = wp_remote_retrieve_header($response, 'content-type');
            $extensions = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'image/svg+xml' => 'svg',
            ];

            if (isset($extensions[$contentType])) {
                return $extensions[$contentType];
            }
        }

        return 'jpg'; // Default fallback
    }

    /**
     * Get attachment by original import URL.
     *
     * @since 2.0.0
     * @param string $url Original import URL.
     * @return \WP_Post|null Attachment post or null if not found.
     */
    private static function getAttachmentByUrl(string $url): ?\WP_Post
    {
        $args = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'meta_query' => [
                [
                    'key' => '_notifal_imported_from_url',
                    'value' => $url,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1,
        ];

        $posts = get_posts($args);
        return !empty($posts) ? $posts[0] : null;
    }

    /**
     * Upload bundled media file from temporary location to WordPress media library.
     *
     * Used for marketplace imports where images are packaged in the ZIP file.
     * This ensures templates are fully portable regardless of source site accessibility.
     *
     * @since 2.0.0
     * @param string $tempFilePath Path to temporary file extracted from ZIP.
     * @param string $originalFilename Original filename from bundle.
     * @return array Array with 'success' boolean, 'url' and 'attachment_id' on success.
     */
    private static function uploadBundledMediaToLibrary(string $tempFilePath, string $originalFilename): array
    {
        // Validate temp file exists
        if (!file_exists($tempFilePath)) {
            Helper::logAdvanced(
                sprintf('Bundled media file not found: %s', $tempFilePath),
                'ERROR'
            );
            return ['success' => false];
        }

        // Read file content
        $fileContent = file_get_contents($tempFilePath);
        if ($fileContent === false) {
            Helper::logAdvanced(
                sprintf('Failed to read bundled media file: %s', $tempFilePath),
                'ERROR'
            );
            return ['success' => false];
        }

        // Sanitize filename
        $filename = sanitize_file_name($originalFilename);

        // Upload to WordPress media library
        $upload = wp_upload_bits($filename, null, $fileContent);
        if ($upload['error']) {
            Helper::logAdvanced(
                sprintf('Failed to upload bundled media to library: %s - Error: %s', $filename, $upload['error']),
                'ERROR'
            );
            return ['success' => false];
        }

        // Create attachment post
        $attachment = [
            'post_mime_type' => wp_check_filetype($filename)['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        $attachmentId = wp_insert_attachment($attachment, $upload['file']);

        if (is_wp_error($attachmentId) || !$attachmentId) {
            Helper::logAdvanced(
                sprintf('Failed to create attachment for bundled media: %s', $filename),
                'ERROR'
            );
            return ['success' => false];
        }

        // Generate attachment metadata (thumbnails, etc.)
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachmentData = wp_generate_attachment_metadata($attachmentId, $upload['file']);
        wp_update_attachment_metadata($attachmentId, $attachmentData);

        // Mark as bundled import for tracking
        update_post_meta($attachmentId, '_notifal_bundled_import', true);

        return [
            'success' => true,
            'url' => wp_get_attachment_url($attachmentId),
            'attachment_id' => $attachmentId,
        ];
    }
}
