<?php

namespace Notifal\Shared\Utils;

use ZipArchive;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FileImportHelper
 *
 * Shared utility for handling file imports (JSON/ZIP) across different importers.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FileImportHelper
{
    /**
     * Process file import by detecting type and handling accordingly.
     *
     * Supports bundled media files in ZIP exports for marketplace portability.
     * Extracts media/ folder from ZIP and makes files available to importers.
     *
     * @since 2.0.0
     * @param string $filePath Absolute path to the file
     * @param callable $jsonProcessor Function to process JSON data, receives decoded data and returns success boolean
     * @param callable $zipProcessor Function to process ZIP entries, receives decoded JSON data and bundled media array, returns success boolean
     * @return array Import results with success count, failed count, etc.
     */
    public static function processFileImport(string $filePath, callable $jsonProcessor, callable $zipProcessor): array
    {
        $success = 0;
        $failed = 0;
        $errors = [];

        // Read file content with error handling
        $content = file_get_contents($filePath);
        if ($content === false) {
            return [
                'success' => 0,
                'failed' => 1,
                'errors' => [__('Could not read file content.', 'notifal')]
            ];
        }

        // When file has .zip extension (e.g. marketplace download), process as ZIP first
        // so bundled media is used; avoids JSON path and ensures bundle approach
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'zip' && class_exists('ZipArchive')) {
            return self::processAsZip($filePath, $zipProcessor);
        }

        // Try processing as JSON first (single file / legacy)
        $json = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            $result = $jsonProcessor($json);
            if ($result['success']) {
                $success++;
            } else {
                $failed++;
                $errors[] = $result['error'] ?? __('Import failed.', 'notifal');
            }

            return [
                'success' => $success,
                'failed' => $failed,
                'errors' => $errors,
            ];
        }

        // If not JSON, attempt ZIP processing with bundled media support
        // Check if ZipArchive class is available
        if (!class_exists('ZipArchive')) {
            $failed++;
            $errors[] = __('ZIP support is not available on this server. Please enable the PHP zip extension or contact your hosting provider.', 'notifal');
            
            return [
                'success' => $success,
                'failed' => $failed,
                'errors' => $errors,
            ];
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) === true) {
            // Extract bundled media files first
            $bundledMedia = self::extractBundledMedia($zip);

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);

                // Skip media folder entries (already extracted)
                if (strpos($filename, 'media/') === 0) {
                    continue;
                }

                $zipContent = $zip->getFromIndex($i);
                $jsonData = json_decode($zipContent, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                    // Pass bundled media to processor
                    $result = $zipProcessor($jsonData, $bundledMedia);
                    if ($result['success']) {
                        $success++;
                    } else {
                        $failed++;
                        $errors[] = $result['error'] ?? sprintf(__('Import failed for file %d.', 'notifal'), $i + 1);
                    }
                } else {
                    // Skip non-JSON files silently (might be metadata or other files)
                    continue;
                }
            }

            $zip->close();

            // Cleanup extracted media files
            self::cleanupBundledMedia($bundledMedia);
        } else {
            $failed++;
            $errors[] = __('Invalid file format. Please upload a valid JSON or ZIP file.', 'notifal');
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
            'is_zip' => true,
        ];
    }

    /**
     * Process file as ZIP (bundled media).
     *
     * Used when file path has .zip extension (e.g. marketplace download) so the
     * importer uses bundled images instead of downloading from URLs.
     *
     * @since 2.0.0
     * @param string   $filePath     Absolute path to the ZIP file
     * @param callable $zipProcessor Function (jsonData, bundledMedia) => result array
     * @return array Import results with success, failed, errors, is_zip
     */
    private static function processAsZip(string $filePath, callable $zipProcessor): array
    {
        $success = 0;
        $failed = 0;
        $errors = [];

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return [
                'success' => 0,
                'failed' => 1,
                'errors' => [__('Invalid file format. Please upload a valid JSON or ZIP file.', 'notifal')],
                'is_zip' => true,
            ];
        }

        $bundledMedia = self::extractBundledMedia($zip);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            if (strpos($filename, 'media/') === 0) {
                continue;
            }

            $zipContent = $zip->getFromIndex($i);
            $jsonData = json_decode($zipContent, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                $result = $zipProcessor($jsonData, $bundledMedia);
                if ($result['success']) {
                    $success++;
                } else {
                    $failed++;
                    $errors[] = $result['error'] ?? sprintf(__('Import failed for file %d.', 'notifal'), $i + 1);
                }
            }
        }

        $zip->close();
        self::cleanupBundledMedia($bundledMedia);

        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
            'is_zip' => true,
        ];
    }

    /**
     * Extract bundled media files from ZIP to temporary directory.
     *
     * Extracts all files from the media/ folder in the ZIP archive to a temporary
     * directory and returns a mapping of bundled path => temp file path.
     *
     * @since 2.0.0
     * @param ZipArchive $zip Opened ZIP archive.
     * @return array Map of bundled path (e.g., 'media/image.jpg') => temp file path.
     */
    private static function extractBundledMedia(ZipArchive $zip): array
    {
        $bundledMedia = [];
        $tempDir = sys_get_temp_dir() . '/notifal-import-' . time() . '-' . wp_rand(1000, 9999);

        // Create temp directory for media extraction
        if (!file_exists($tempDir) && !mkdir($tempDir, 0755, true)) {
            return [];
        }

        // Extract all media files
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            // Only extract files from media/ folder
            if (strpos($filename, 'media/') !== 0) {
                continue;
            }

            // Skip directories
            if (substr($filename, -1) === '/') {
                continue;
            }

            // Extract file to temp directory
            $fileContent = $zip->getFromIndex($i);
            if ($fileContent === false) {
                continue;
            }

            // Generate safe temp filename
            $basename = basename($filename);
            $tempFilePath = $tempDir . '/' . sanitize_file_name($basename);

            // Save to temp file
            if (file_put_contents($tempFilePath, $fileContent) !== false) {
                $bundledMedia[$filename] = $tempFilePath;
            }
        }

        return $bundledMedia;
    }

    /**
     * Cleanup temporary bundled media files.
     *
     * Removes temporary files and directory created during media extraction.
     *
     * @since 2.0.0
     * @param array $bundledMedia Map of bundled path => temp file path.
     * @return void
     */
    private static function cleanupBundledMedia(array $bundledMedia): void
    {
        if (empty($bundledMedia)) {
            return;
        }

        // Delete temp files
        foreach ($bundledMedia as $tempFilePath) {
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
        }

        // Delete temp directory
        $tempDir = dirname(reset($bundledMedia));
        if (file_exists($tempDir) && is_dir($tempDir)) {
            rmdir($tempDir);
        }
    }
}
