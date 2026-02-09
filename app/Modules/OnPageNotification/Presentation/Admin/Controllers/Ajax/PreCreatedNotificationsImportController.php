<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Services\API\PreCreatedNotificationsApiService;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Import\Importer;
use Notifal\Shared\Controllers\Ajax\BaseImportController;

defined('ABSPATH') || exit;

/**
 * Pre-created Notifications Import Controller
 *
 * Handles AJAX import requests for pre-created notifications from the marketplace.
 * Downloads notification files from API and imports them using the notification importer.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class PreCreatedNotificationsImportController extends BaseImportController
{
    /**
     * Get the AJAX action name for registration.
     *
     * @since 2.0.0
     * @return string The AJAX action name
     */
    protected static function getAjaxAction(): string
    {
        return 'notifal_import_precreated_notification';
    }

    /**
     * Get the nonce action name for verification.
     *
     * @since 2.0.0
     * @return string The nonce action name
     */
    protected static function getNonceAction(): string
    {
        return 'notifal_admin_ajax_nonce';
    }

    /**
     * Get whether this import controller requires file upload validation.
     *
     * @since 2.0.0
     * @return bool True if file validation is required, false otherwise
     */
    protected static function requiresFileValidation(): bool
    {
        return false;
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
        return __('Pre-created Notifications', 'notifal');
    }

    /**
     * Handles import request with validation and error handling.
     * Overrides parent method to use 'nonce' instead of '_wpnonce' for nonce verification.
     *
     * @since 2.0.0
     * @return void
     */
    public static function handleImport(): void
    {
        try {
            // Verify AJAX request with nonce and user capabilities (using 'nonce' key instead of '_wpnonce')
            notifal_verify_ajax_request(static::getNonceAction(), 'edit_posts', 'nonce');

            // Perform the import
            $result = static::performImport(null);

            // Apply filters for extensibility
            $result = apply_filters(static::getImportResultFilter(), $result, null);

            // Check for import errors
            $errors = $result['errors'] ?? [];
            if (!empty($errors)) {
                $errorMessage = implode(' ', $errors);
                notifal_json_error($errorMessage);
                return;
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
            notifal_json_error(__('Import failed. Please try again.', 'notifal'));
        }
    }

    /**
     * Perform the actual import by downloading from API and importing.
     *
     * @since 2.0.0
     * @param string|null $filePath The path to the downloaded file (not used, we download from API)
     * @return array The import result
     */
    protected static function performImport(?string $filePath): array
    {
        $tempFilePath = null;

        try {
            // Sanitize and validate notification ID
            $notificationId = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;

            if ($notificationId <= 0) {
                throw new \Exception(__('Invalid notification ID.', 'notifal'));
            }

            // Get file type (prefer elementor, fallback to block-editor)
            $fileType = sanitize_text_field($_POST['file_type'] ?? 'elementor');
            $validTypes = ['elementor', 'block-editor'];
            if (!in_array($fileType, $validTypes, true)) {
                $fileType = 'elementor';
            }

            // Get download URL from API
            $apiService = notifal_app(PreCreatedNotificationsApiService::class);
            $downloadResponse = $apiService->getDownloadUrl($notificationId, $fileType);

            if (isset($downloadResponse['success']) && !$downloadResponse['success']) {
                throw new \Exception($downloadResponse['error'] ?? __('Failed to get download URL.', 'notifal'));
            }

            // Extract download URL from response
            $downloadUrl = $downloadResponse['data']['download_url'] ?? '';

            if (empty($downloadUrl)) {
                throw new \Exception(__('Download URL not found in API response.', 'notifal'));
            }

            // Download the file
            $downloadResult = $apiService->downloadFile($downloadUrl);

            if (!$downloadResult['success']) {
                throw new \Exception($downloadResult['error'] ?? __('Failed to download file.', 'notifal'));
            }

            $tempFilePath = $downloadResult['file_path'];

            // Import the file using the notification importer
            return Importer::importFromFile($tempFilePath);

        } catch (\Exception $e) {
            // Clean up temporary file on error
            if ($tempFilePath && file_exists($tempFilePath)) {
                @unlink($tempFilePath);
            }

            throw $e;
        } finally {
            // Clean up temporary file
            if ($tempFilePath && file_exists($tempFilePath)) {
                @unlink($tempFilePath);
            }
        }
    }
}