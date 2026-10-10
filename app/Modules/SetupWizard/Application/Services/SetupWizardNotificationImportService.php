<?php
namespace Notifal\Modules\SetupWizard\Application\Services;
use Notifal\Modules\OnPageNotification\Helpers\PreCreatedNotificationBuilderTypes;
use Notifal\Modules\OnPageNotification\Helpers\PreCreatedNotificationRequirementsHelper;
use Notifal\Modules\OnPageNotification\Application\Services\API\PreCreatedNotificationsApiService;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Import\Importer;
defined('ABSPATH') || exit;
/**
 * Imports marketplace notifications during the Setup Wizard.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardNotificationImportService
{
    /**
     * Marketplace API service dependency.
     *
     * @var PreCreatedNotificationsApiService
     */
    private PreCreatedNotificationsApiService $apiService;
    /**
     * Create the Setup Wizard notification import service.
     *
     * @since 3.0.0
     * @param PreCreatedNotificationsApiService $apiService Marketplace API service.
     */
    public function __construct(PreCreatedNotificationsApiService $apiService)
    {
        // Store dependency used to download marketplace packages.
        $this->apiService = $apiService;
    }
    /**
     * Import one marketplace notification as a disabled draft.
     *
     * @since 3.0.0
     * @param int    $notificationId Marketplace notification ID.
     * @param string $fileType Builder file type slug.
     * @return array<string, mixed> Import result payload.
     */
    public function import(int $notificationId, string $fileType): array
    {
        // Sanitize incoming identifiers.
        $notificationId = absint($notificationId);
        $fileType = sanitize_key($fileType);
        // Reject invalid notification IDs early.
        if ($notificationId <= 0) {
            return [
                'success' => false,
                'error'   => __('Invalid notification ID.', 'notifal'),
            ];
        }
        // Normalize unsupported file types to HTML Builder when possible.
        if (!PreCreatedNotificationBuilderTypes::isValidImportFileType($fileType)) {
            $fileType = PreCreatedNotificationBuilderTypes::HTML_BUILDER;
        }
        // Load notification metadata and version requirements.
        $singleResponse = $this->apiService->getSingleNotification($notificationId);
        if (!empty($singleResponse['success']) && false === $singleResponse['success']) {
            return [
                'success' => false,
                'error'   => sanitize_text_field((string) ($singleResponse['error'] ?? __('Failed to load notification requirements.', 'notifal'))),
            ];
        }
        // Validate local Notifal version against template requirements.
        $notificationData = is_array($singleResponse['data'] ?? null) ? $singleResponse['data'] : [];
        $versionRequirement = PreCreatedNotificationRequirementsHelper::evaluateNotifalVersionRequirement($notificationData);
        if (!$versionRequirement['meets_notifal_version']) {
            return [
                'success' => false,
                'error'   => sanitize_text_field((string) ($versionRequirement['message'] ?? __('This template requires a newer version of Notifal.', 'notifal'))),
            ];
        }
        // Resolve a signed download URL for the selected builder file.
        $downloadResponse = $this->apiService->getDownloadUrl($notificationId, $fileType);
        if (!empty($downloadResponse['success']) && false === $downloadResponse['success']) {
            return [
                'success' => false,
                'error'   => sanitize_text_field((string) ($downloadResponse['error'] ?? __('Failed to get download URL.', 'notifal'))),
            ];
        }
        // Extract the download URL from the API response envelope.
        $downloadUrl = esc_url_raw((string) ($downloadResponse['data']['download_url'] ?? ''));
        if ('' === $downloadUrl) {
            return [
                'success' => false,
                'error'   => __('Download URL not found in API response.', 'notifal'),
            ];
        }
        // Download the template package to a temporary file.
        $downloadResult = $this->apiService->downloadFile($downloadUrl);
        if (empty($downloadResult['success'])) {
            return [
                'success' => false,
                'error'   => sanitize_text_field((string) ($downloadResult['error'] ?? __('Failed to download file.', 'notifal'))),
            ];
        }
        // Import through the shared OnPage notification importer.
        $tempFilePath = (string) ($downloadResult['file_path'] ?? '');
        try {
            $result = Importer::importFromFile($tempFilePath);
        } finally {
            // Always remove the temporary download file.
            if ('' !== $tempFilePath && file_exists($tempFilePath)) {
                @unlink($tempFilePath);
            }
        }
        // Return importer errors to the wizard UI.
        $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
        if (!empty($errors) || empty($result['success'])) {
            return [
                'success' => false,
                'error'   => sanitize_text_field((string) ($errors[0] ?? __('Import failed.', 'notifal'))),
            ];
        }
        // Disable imported notifications so the success screen can review them safely.
        $notificationIdLocal = absint($result['notification_id'] ?? 0);
        if ($notificationIdLocal > 0) {
            wp_update_post(
                [
                    'ID'          => $notificationIdLocal,
                    'post_status' => 'draft',
                ]
            );
            update_post_meta($notificationIdLocal, '_notifal_enabled', '0');
        }
        // Return success metadata for the wizard success step.
        return [
            'success'         => true,
            'notification_id' => $notificationIdLocal,
            'edit_url'        => $notificationIdLocal > 0 ? (string) get_edit_post_link($notificationIdLocal, 'raw') : '',
            'title'           => sanitize_text_field((string) ($result['first_title'] ?? '')),
        ];
    }
}
