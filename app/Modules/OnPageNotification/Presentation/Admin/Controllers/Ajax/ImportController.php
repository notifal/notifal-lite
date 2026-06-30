<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Import\Importer;
use Notifal\Shared\Controllers\Ajax\BaseImportController;
use Notifal\Shared\Utils\FileUploadValidator;
use Notifal\Shared\Utils\JsonImportValidator;

defined('ABSPATH') || exit;

/**
 * Class ImportController
 *
 * Handles AJAX import logic for notifal_onpage_notification files (JSON/ZIP)
 * and pasted JSON payloads.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ImportController extends BaseImportController
{
    /**
     * POST field name for import source mode (file or json).
     *
     * @since 2.4.1
     */
    const IMPORT_SOURCE_FIELD = 'notifal_import_source';

    /**
     * Import source value for file uploads.
     *
     * @since 2.4.1
     */
    const SOURCE_FILE = 'file';

    /**
     * Import source value for pasted JSON.
     *
     * @since 2.4.1
     */
    const SOURCE_JSON = 'json';

    /**
     * Get the AJAX action name for registration.
     *
     * @since 2.0.0
     * @return string The AJAX action name
     */
    protected static function getAjaxAction(): string
    {
        return 'notifal_import_onpage_notification_ajax';
    }

    /**
     * Get the nonce action name for verification.
     *
     * @since 2.0.0
     * @return string The nonce action name
     */
    protected static function getNonceAction(): string
    {
        return 'notifal_import_onpage_notification_ajax_nonce';
    }

    /**
     * Get whether this import controller requires file upload validation.
     *
     * @since 2.0.0
     * @return bool True if file validation is required, false otherwise
     */
    protected static function requiresFileValidation(): bool
    {
        return true;
    }

    /**
     * Get the filter hook name for import results.
     *
     * @since 2.0.0
     * @return string The filter hook name
     */
    protected static function getImportResultFilter(): string
    {
        return FilterHooks::ONPAGE_NOTIFICATION_IMPORT_RESULT;
    }

    /**
     * Get the title text for ZIP imports.
     *
     * @since 2.0.0
     * @return string The title text
     */
    protected static function getZipImportTitle(): string
    {
        return __('Notifications', 'notifal');
    }

    /**
     * Perform the actual import using the notification importer.
     *
     * @since 2.0.0
     * @param string|null $filePath The path to the uploaded file
     * @return array The import result
     */
    protected static function performImport(?string $filePath): array
    {
        return Importer::importFromFile($filePath);
    }

    /**
     * Handles import request with validation and error handling.
     *
     * Supports file upload (JSON/ZIP) and pasted JSON payloads.
     *
     * @since 2.4.1
     * @return void
     */
    public static function handleImport(): void
    {
        try {
            // Verify AJAX request with nonce and user capabilities.
            notifal_verify_ajax_request(static::getNonceAction(), 'edit_posts', '_wpnonce');

            // Read sanitized import source mode from POST.
            $importSource = isset($_POST[self::IMPORT_SOURCE_FIELD])
                ? sanitize_key(wp_unslash($_POST[self::IMPORT_SOURCE_FIELD]))
                : self::SOURCE_FILE;

            // Branch between pasted JSON and file upload flows.
            if ($importSource === self::SOURCE_JSON) {
                static::assertTrustedJsonConfirmation();
                $decoded = JsonImportValidator::validateAndDecodeFromPost('notifal_import_json');
                $result = Importer::importFromJsonArray($decoded);
                $filterContext = null;
            } else {
                $filePath = FileUploadValidator::validateImportFile('notifal_import_file');
                $result = static::performImport($filePath);
                $filterContext = $filePath;
            }

            // Apply filters for extensibility.
            $result = apply_filters(static::getImportResultFilter(), $result, $filterContext);

            // Check for import errors.
            $errors = $result['errors'] ?? [];
            if (!empty($errors)) {
                $errorMessage = implode(' ', $errors);
                notifal_json_error($errorMessage);
            }

            // Prepare success response.
            $title = $result['is_zip'] ?? false
                ? static::getZipImportTitle()
                : ($result['first_title'] ?? '');

            notifal_json_success([
                'success' => $result['success'] ?? 0,
                'failed' => $result['failed'] ?? 0,
                'title' => $title,
            ]);
        } catch (\Exception $e) {
            // Handle unexpected errors without exposing internal details.
            error_log('Notifal: Import failed - ' . $e->getMessage());
            notifal_json_error($e->getMessage());
        }
    }

    /**
     * Require explicit user confirmation before processing pasted JSON.
     *
     * @since 2.4.1
     * @return void
     * @throws \Exception When confirmation checkbox is not checked.
     */
    private static function assertTrustedJsonConfirmation(): void
    {
        // Read confirmation flag from POST.
        $confirmed = isset($_POST['notifal_import_json_confirmed'])
            ? sanitize_text_field(wp_unslash($_POST['notifal_import_json_confirmed']))
            : '';

        // Reject import when user has not confirmed trusted source.
        if ($confirmed !== '1') {
            throw new \Exception(
                __('Please confirm that the pasted JSON is from a trusted source before importing.', 'notifal')
            );
        }
    }
}
