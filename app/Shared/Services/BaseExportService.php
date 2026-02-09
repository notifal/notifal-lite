<?php

namespace Notifal\Shared\Services;

use Notifal\Shared\Utils\Helper;
use WP_Post;
use ZipArchive;

defined('ABSPATH') || exit;

/**
 * Class BaseExportService
 *
 * Base service for handling common export operations (JSON and ZIP).
 * Specific export services should extend this class and implement prepareExportData().
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
abstract class BaseExportService
{
    /**
     * Handle export for one or more posts.
     *
     * DEFAULT BEHAVIOR (Marketplace Mode):
     * - ALL exports use ZIP format with bundled media for maximum portability
     * - Works even when source site goes offline or is inaccessible
     * - Perfect for template/notification marketplaces
     *
     * BACKWARD COMPATIBILITY:
     * - Import still accepts old JSON format (URLs without bundled media)
     * - Import accepts new ZIP format (with bundled media)
     *
     * To disable ZIP for single exports (legacy mode), use:
     * add_filter('notifal_disable_zip_for_single_export', '__return_true');
     *
     * @param string $postType Post type name.
     * @param int[] $ids List of post IDs.
     * @return void
     * @since 2.0.0
     */
    public static function handle(string $postType, array $ids): void
    {
        if ($postType !== static::getPostType()) {
            return;
        }

        // Allow disabling ZIP for single items (legacy mode for old behavior)
        $disableZipForSingle = apply_filters('notifal_disable_zip_for_single_export', false);
        $disableZipForTypeAndSingle = apply_filters("notifal_disable_zip_for_single_export_{$postType}", false);

        // DEFAULT: Always use ZIP with bundled media (marketplace mode)
        // Only export as JSON if explicitly disabled via filter AND single item
        if (count($ids) === 1 && ($disableZipForSingle || $disableZipForTypeAndSingle)) {
            static::exportSingle($ids[0]);
        } else {
            static::exportAsZip($ids);
        }
    }

    /**
     * Export a single post as JSON and trigger download.
     *
     * Suppresses PHP notices during export to prevent them from corrupting the JSON output.
     *
     * @param int $postId Post ID.
     * @return void
     * @since 2.0.0
     */
    protected static function exportSingle(int $postId): void
    {
        // Suppress PHP notices during the entire export process to prevent JSON corruption
        $originalErrorReporting = error_reporting();
        $originalDisplayErrors = ini_get('display_errors');
        $originalLogErrors = ini_get('log_errors');

        // Disable all error output and logging during export
        error_reporting(0);
        ini_set('display_errors', '0');
        ini_set('log_errors', '0');

        // Set custom error handler to completely silence notices
        $originalErrorHandler = set_error_handler(function($errno, $errstr, $errfile, $errline) {
            // Silently ignore notices and warnings during export
            if ($errno === E_NOTICE || $errno === E_WARNING || $errno === E_USER_NOTICE || $errno === E_USER_WARNING) {
                return true;
            }
            // For other errors, use default behavior
            return false;
        });

        try {
            $post = Helper::getPostSafe($postId, static::getPostType());
            if (!$post) {
                return;
            }

            $data = static::prepareExportData($post);

            // Clean any existing output buffers to prevent notices from being included
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Start fresh output buffer for clean JSON output
            ob_start();

            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . static::generateFilename($post) . '"');

            echo wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            // Flush and exit cleanly
            ob_end_flush();

        } finally {
            // Always restore original error handling regardless of what happens
            if ($originalErrorHandler !== null) {
                restore_error_handler();
            }
            error_reporting($originalErrorReporting);
            ini_set('display_errors', $originalDisplayErrors);
            ini_set('log_errors', $originalLogErrors);
        }

        exit;
    }

    /**
     * Export multiple posts as a ZIP file of JSONs with optional bundled media.
     *
     * Suppresses PHP notices during export to prevent them from corrupting the ZIP file.
     * For marketplace exports, images are downloaded and bundled in the ZIP for portability.
     *
     * @param int[] $ids Post IDs.
     * @return void
     * @since 2.0.0
     */
    protected static function exportAsZip(array $ids): void
    {
        // Check if ZipArchive class is available
        if (!class_exists('ZipArchive')) {
            wp_die(
                __('ZIP support is not available on this server. Please enable the PHP zip extension or contact your hosting provider.', 'notifal'),
                __('ZIP Extension Required', 'notifal'),
                ['back_link' => true]
            );
        }

        // Suppress PHP notices during the entire export process to prevent corruption
        $originalErrorReporting = error_reporting();
        $originalDisplayErrors = ini_get('display_errors');
        $originalLogErrors = ini_get('log_errors');

        // Disable all error output and logging during export
        error_reporting(0);
        ini_set('display_errors', '0');
        ini_set('log_errors', '0');

        // Set custom error handler to completely silence notices
        $originalErrorHandler = set_error_handler(function($errno, $errstr, $errfile, $errline) {
            // Silently ignore notices and warnings during export
            if ($errno === E_NOTICE || $errno === E_WARNING || $errno === E_USER_NOTICE || $errno === E_USER_WARNING) {
                return true;
            }
            // For other errors, use default behavior
            return false;
        });

        try {
            $zip = new ZipArchive();
            $tmpFile = tempnam(sys_get_temp_dir(), 'notifal_' . static::getExportType() . '_export_');
            $zipFilePath = $tmpFile . '.zip';

            rename($tmpFile, $zipFilePath);

            if ($zip->open($zipFilePath, ZipArchive::CREATE) !== true) {
                wp_die(__('Could not create ZIP archive.', 'notifal'));
            }

            // Track bundled media files to avoid duplicates
            $bundledMedia = [];

            foreach ($ids as $id) {
                $post = Helper::getPostSafe($id, static::getPostType());
                if (!$post) {
                    continue;
                }

                $data = static::prepareExportData($post);

                // Bundle media files if dependencies exist (for marketplace portability)
                if (!empty($data['dependencies']['images'])) {
                    $data = static::bundleMediaInZip($zip, $data, $bundledMedia);
                }

                $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $fileName = static::generateZipFilename($post);
                $zip->addFromString($fileName, $json);
            }

            $zip->close();

            // Clean any existing output buffers to prevent notices from being included
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Start fresh output buffer for clean response
            ob_start();

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . static::generateZipArchiveName() . '"');
            header('Content-Length: ' . filesize($zipFilePath));

            readfile($zipFilePath);
            unlink($zipFilePath);

            // Flush and exit cleanly
            ob_end_flush();

        } finally {
            // Always restore original error handling regardless of what happens
            if ($originalErrorHandler !== null) {
                restore_error_handler();
            }
            error_reporting($originalErrorReporting);
            ini_set('display_errors', $originalDisplayErrors);
            ini_set('log_errors', $originalLogErrors);
        }

        exit;
    }

    /**
     * Bundle media files in ZIP and update references in template data.
     *
     * Downloads images from URLs and adds them to the ZIP archive in a media/ folder.
     * Updates template data to use relative paths instead of absolute URLs for portability.
     *
     * @param ZipArchive $zip Zip archive instance.
     * @param array $data Template export data.
     * @param array $bundledMedia Reference array tracking already bundled files.
     * @return array Updated template data with bundled media references.
     * @since 2.0.0
     */
    protected static function bundleMediaInZip(ZipArchive $zip, array $data, array &$bundledMedia): array
    {
        if (empty($data['dependencies']['images'])) {
            return $data;
        }

        // Map old URL => bundled path
        $urlToPath = [];

        foreach ($data['dependencies']['images'] as $imageUrl) {
            // Skip if already bundled
            if (isset($bundledMedia[$imageUrl])) {
                $urlToPath[$imageUrl] = $bundledMedia[$imageUrl];
                continue;
            }

            // Download image
            $imageData = static::downloadImageForBundle($imageUrl);
            if (!$imageData) {
                continue;
            }

            // Generate unique filename
            $filename = static::generateBundledFilename($imageUrl, $bundledMedia);
            $zipPath = 'media/' . $filename;

            // Add to ZIP
            if ($zip->addFromString($zipPath, $imageData)) {
                $bundledMedia[$imageUrl] = $zipPath;
                $urlToPath[$imageUrl] = $zipPath;
            }
        }

        // Update template data to use bundled paths instead of URLs
        if (!empty($urlToPath)) {
            $data = static::replaceMediaUrls($data, $urlToPath);
        }

        return $data;
    }

    /**
     * Download image from URL for bundling in export.
     *
     * @param string $url Image URL.
     * @return string|false Image binary data or false on failure.
     * @since 2.0.0
     */
    protected static function downloadImageForBundle(string $url)
    {
        // Handle local files (from wp-content/uploads)
        if (strpos($url, site_url()) === 0) {
            $uploadDir = wp_upload_dir();
            $uploadUrl = $uploadDir['baseurl'];
            if (strpos($url, $uploadUrl) === 0) {
                $filePath = str_replace($uploadUrl, $uploadDir['basedir'], $url);
                if (file_exists($filePath)) {
                    return file_get_contents($filePath);
                }
            }
        }

        // Download from remote URL
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'redirection' => 5,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            Helper::logAdvanced(
                sprintf('Failed to download image for bundle: %s', $url),
                'WARNING'
            );
            return false;
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * Generate unique filename for bundled media file.
     *
     * @param string $url Original image URL.
     * @param array $bundledMedia Already bundled files to avoid duplicates.
     * @return string Unique filename.
     * @since 2.0.0
     */
    protected static function generateBundledFilename(string $url, array $bundledMedia): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $basename = basename($path);

        // Sanitize filename
        $basename = sanitize_file_name($basename);

        // Ensure unique filename
        $counter = 1;
        $originalBasename = $basename;
        while (in_array('media/' . $basename, $bundledMedia, true)) {
            $extension = pathinfo($originalBasename, PATHINFO_EXTENSION);
            $name = pathinfo($originalBasename, PATHINFO_FILENAME);
            $basename = $name . '-' . $counter . '.' . $extension;
            $counter++;
        }

        return $basename;
    }

    /**
     * Replace media URLs with bundled relative paths in template data.
     *
     * Updates both content and dependencies to reference bundled media files.
     *
     * @param array $data Template export data.
     * @param array $urlToPath Map of old URL => bundled path.
     * @return array Updated template data.
     * @since 2.0.0
     */
    protected static function replaceMediaUrls(array $data, array $urlToPath): array
    {
        // Update content (both Elementor array and Block Editor string)
        if (isset($data['content'])) {
            if (is_array($data['content'])) {
                // Elementor content
                $data['content'] = static::replaceUrlsInArray($data['content'], $urlToPath);
            } elseif (is_string($data['content'])) {
                // Block Editor content
                $data['content'] = static::replaceUrlsInString($data['content'], $urlToPath);
            }
        }

        // Update dependencies to reflect bundled status
        if (isset($data['dependencies']['images'])) {
            $data['dependencies']['bundled_media'] = $urlToPath;
        }

        return $data;
    }

    /**
     * Recursively replace URLs in array structure (for Elementor).
     *
     * @param array $array Array to process.
     * @param array $urlToPath URL replacement map.
     * @return array Updated array.
     * @since 2.0.0
     */
    protected static function replaceUrlsInArray(array $array, array $urlToPath): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = static::replaceUrlsInArray($value, $urlToPath);
            } elseif (is_string($value)) {
                $array[$key] = static::replaceUrlsInString($value, $urlToPath);
            }
        }
        return $array;
    }

    /**
     * Replace URLs in string content.
     *
     * @param string $string String to process.
     * @param array $urlToPath URL replacement map.
     * @return string Updated string.
     * @since 2.0.0
     */
    protected static function replaceUrlsInString(string $string, array $urlToPath): string
    {
        foreach ($urlToPath as $url => $path) {
            $string = str_replace($url, '[BUNDLED]' . $path, $string);
        }
        return $string;
    }

    /**
     * Get the post type for this export service.
     *
     * @return string Post type.
     * @since 2.0.0
     */
    abstract protected static function getPostType(): string;

    /**
     * Get the export type identifier for file naming.
     *
     * @return string Export type.
     * @since 2.0.0
     */
    abstract protected static function getExportType(): string;

    /**
     * Prepare export data array for a post.
     *
     * @param WP_Post $post The post.
     * @return array Structured export data.
     * @since 2.0.0
     */
    abstract public static function prepareExportData(WP_Post $post): array;

    /**
     * Generate filename for single export.
     *
     * @param WP_Post $post The post.
     * @return string Filename.
     * @since 2.0.0
     */
    abstract protected static function generateFilename(WP_Post $post): string;

    /**
     * Generate filename for ZIP entry.
     *
     * @param WP_Post $post The post.
     * @return string Filename.
     * @since 2.0.0
     */
    abstract protected static function generateZipFilename(WP_Post $post): string;

    /**
     * Generate ZIP archive name.
     *
     * @return string Archive name.
     * @since 2.0.0
     */
    abstract protected static function generateZipArchiveName(): string;
}
