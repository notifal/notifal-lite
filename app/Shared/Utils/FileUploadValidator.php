<?php

namespace Notifal\Shared\Utils;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FileUploadValidator
 *
 * Shared utility for validating file uploads with security checks.
 * Handles common file upload validation patterns across the plugin.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FileUploadValidator
{
    /**
     * Validate file upload for import operations.
     *
     * @since 2.0.0
     * @param string $fileKey The $_FILES array key to validate
     * @return string The validated file path
     * @throws \Exception If validation fails
     */
    public static function validateImportFile(string $fileKey = 'notifal_import_file'): string
    {
        // Check if file was uploaded
        if (empty($_FILES[$fileKey])) {
            throw new \Exception(__('No file uploaded.', 'notifal'));
        }

        $file = $_FILES[$fileKey];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => __('The uploaded file exceeds the upload_max_filesize directive in php.ini.', 'notifal'),
                UPLOAD_ERR_FORM_SIZE => __('The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.', 'notifal'),
                UPLOAD_ERR_PARTIAL => __('The uploaded file was only partially uploaded.', 'notifal'),
                UPLOAD_ERR_NO_FILE => __('No file was uploaded.', 'notifal'),
                UPLOAD_ERR_NO_TMP_DIR => __('Missing a temporary folder.', 'notifal'),
                UPLOAD_ERR_CANT_WRITE => __('Failed to write file to disk.', 'notifal'),
                UPLOAD_ERR_EXTENSION => __('A PHP extension stopped the file upload.', 'notifal'),
            ];

            $errorMessage = $uploadErrors[$file['error']] ?? __('Unknown upload error.', 'notifal');
            throw new \Exception($errorMessage);
        }

        $filePath = $file['tmp_name'];

        // Check if file exists and is readable
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \Exception(__('Uploaded file is not accessible.', 'notifal'));
        }

        return $filePath;
    }

    /**
     * Validate file upload and return file info for custom processing.
     *
     * @since 2.0.0
     * @param string $fileKey The $_FILES array key to validate
     * @return array {
     *     @type string $path     The temporary file path
     *     @type string $name     The original file name
     *     @type string $type     The file mime type
     *     @type int    $size     The file size in bytes
     * }
     * @throws \Exception If validation fails
     */
    public static function validateAndGetFileInfo(string $fileKey = 'notifal_import_file'): array
    {
        $filePath = self::validateImportFile($fileKey);
        $file = $_FILES[$fileKey];

        return [
            'path' => $filePath,
            'name' => $file['name'],
            'type' => $file['type'],
            'size' => $file['size'],
        ];
    }
}
