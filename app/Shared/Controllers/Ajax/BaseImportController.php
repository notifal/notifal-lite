<?php

namespace Notifal\Shared\Controllers\Ajax;

use Notifal\Shared\Utils\FileUploadValidator;

defined('ABSPATH') || exit;

/**
 * Abstract base class for AJAX import controllers.
 *
 * Provides common import functionality and validation patterns.
 * Subclasses must implement specific import logic and configuration.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
abstract class BaseImportController
{
    /**
     * Get the AJAX action name for registration.
     *
     * @since 2.0.0
     * @return string The AJAX action name
     */
    abstract protected static function getAjaxAction(): string;

    /**
     * Get the nonce action name for verification.
     *
     * @since 2.0.0
     * @return string The nonce action name
     */
    abstract protected static function getNonceAction(): string;

    /**
     * Get the filter hook name for import results.
     *
     * @since 2.0.0
     * @return string The filter hook name
     */
    abstract protected static function getImportResultFilter(): string;

    /**
     * Get the title text for ZIP imports.
     *
     * @since 2.0.0
     * @return string The title text
     */
    abstract protected static function getZipImportTitle(): string;

    /**
     * Perform the actual import using the appropriate importer.
     *
     * @since 2.0.0
     * @param string|null $filePath The path to the uploaded file, or null if no file validation required
     * @return array The import result
     */
    abstract protected static function performImport(?string $filePath): array;

    /**
     * Get whether this import controller requires file upload validation.
     *
     * @since 2.0.0
     * @return bool True if file validation is required, false otherwise
     */
    abstract protected static function requiresFileValidation(): bool;

    /**
     * Registers the AJAX handler for import.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register(): void
    {
        add_action('wp_ajax_' . static::getAjaxAction(), [static::class, 'handleImport']);
    }

    /**
     * Handles import request with validation and error handling.
     *
     * @since 2.0.0
     * @return void
     */
    public static function handleImport(): void
    {
        try {
            // Verify AJAX request with nonce and user capabilities
            notifal_verify_ajax_request(static::getNonceAction(), 'edit_posts', '_wpnonce');

            // Validate and get file path if required
            $filePath = null;
            if (static::requiresFileValidation()) {
                $filePath = FileUploadValidator::validateImportFile('notifal_import_file');
            }

            // Perform the import
            $result = static::performImport($filePath);

            // Apply filters for extensibility
            $result = apply_filters(static::getImportResultFilter(), $result, $filePath);

            // Check for import errors
            $errors = $result['errors'] ?? [];
            if (!empty($errors)) {
                $errorMessage = implode(' ', $errors);
                notifal_json_error($errorMessage);
            }

            // Prepare success response
            $title = $result['is_zip'] ?? false
                ? static::getZipImportTitle()
                : ($result['first_title'] ?? '');

            notifal_json_success([
                'success' => $result['success'] ?? 0,
                'failed' => $result['failed'] ?? 0,
                'title' => $title,
            ]);

        } catch (\Exception $e) {
            // Handle unexpected errors
            error_log('Notifal: Import failed - ' . $e->getMessage());
            notifal_json_error(__('Import failed. Please try again.', 'notifal'));
        }
    }
}
