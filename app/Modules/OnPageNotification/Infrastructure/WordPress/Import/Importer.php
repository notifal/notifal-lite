<?php

namespace Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Import;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\AppearanceSettingsService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\BehaviorSettingsService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\TimingSettingsService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\ContentSourceService;
use Notifal\Modules\OnPageNotification\Application\Services\OnPageImportSettingsNormalizer;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\DisplayRulesDataNormalizer;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\DisplayRulesService;
use Notifal\Modules\OnPageNotification\Application\Services\Core\NotificationSaveService;
use Notifal\Modules\Templates\Infrastructure\WordPress\Import\Importer as TemplateImporter;
use Notifal\Shared\Utils\FileImportHelper;
use ZipArchive;
use Exception;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class Importer
 *
 * Handles importing OnPage Notifications from JSON or ZIP files.
 * Includes template dependency handling and settings validation.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class Importer
{
    /**
     * Import a single notification from a decoded JSON array (pasted JSON flow).
     *
     * @since 2.4.1
     * @param array $json Decoded Notifal export JSON.
     * @return array Import result with success, failed, errors, and first_title keys.
     */
    public static function importFromJsonArray(array $json): array
    {
        // Sanitize title for response metadata only; importer sanitizes all stored fields.
        $firstTitle = Helper::sanitizeInput($json['notification']['title'] ?? __('Imported Notification', 'notifal'), 'text');

        // Run the shared single-notification import pipeline.
        $importResult = self::importFromData($json);

        // Map importer result to the standard import response shape.
        if (!empty($importResult['success'])) {
            return [
                'success' => 1,
                'failed' => 0,
                'errors' => [],
                'first_title' => $firstTitle,
                'is_zip' => false,
            ];
        }

        return [
            'success' => 0,
            'failed' => 1,
            'errors' => [$importResult['error'] ?? __('Import failed.', 'notifal')],
            'first_title' => '',
            'is_zip' => false,
        ];
    }

    /**
     * Import one or more notifications from a file path.
     * Detects file type (JSON or ZIP), and imports accordingly.
     *
     * @since 2.0.0
     * @param string $filePath Absolute path to the uploaded file.
     * @return array {
     *     @type int    $success      Number of successfully imported notifications.
     *     @type int    $failed       Number of failed imports.
     *     @type string $first_title  Title of the first imported notification (empty if ZIP or failed).
     *     @type bool   $is_zip       Whether the file was a ZIP archive.
     *     @type array  $errors       Array of error messages for failed imports.
     * }
     */
    public static function importFromFile(string $filePath): array
    {
        $firstTitle = '';

        // Define processor for JSON imports (single notification)
        $jsonProcessor = function ($json) use (&$firstTitle) {
            // Extract and sanitize title for response metadata
            $title = Helper::sanitizeInput($json['notification']['title'] ?? __('Imported Notification', 'notifal'), 'text');
            $firstTitle = $title;

            return self::importFromData($json);
        };

        // Define processor for ZIP imports (multiple notifications with bundled media)
        $zipProcessor = function ($jsonData, $bundledMedia = []) use (&$firstTitle) {
            $result = self::importFromData($jsonData, $bundledMedia);

            // Extract title from first successful import for response metadata
            if (empty($firstTitle) && $result['success'] && isset($jsonData['notification']['title'])) {
                $firstTitle = Helper::sanitizeInput($jsonData['notification']['title'], 'text');
            }

            return $result;
        };

        // Use shared file import helper to handle JSON/ZIP processing
        $result = FileImportHelper::processFileImport($filePath, $jsonProcessor, $zipProcessor);

        // Add notification-specific metadata
        $result['first_title'] = $firstTitle;

        return $result;
    }

    /**
     * Import a notification from structured data array.
     *
     * Supports bundled media for marketplace imports where notification templates
     * include images that need to be uploaded from ZIP packages.
     *
     * @since 2.0.0
     * @param array $data Notification data array.
     * @param array $bundledMedia Map of bundled path => temp file path for bundled media.
     * @return array Array with 'success' boolean and 'error' string if failed.
     */
    public static function importFromData(array $data, array $bundledMedia = []): array
    {
        // Validate import data structure
        if (!self::validateImportData($data)) {
            return [
                'success' => false,
                'error' => __('Invalid import data structure. Please check the file format.', 'notifal')
            ];
        }

        // Validate version compatibility for import data
        if (!self::isVersionCompatible($data['version'] ?? '')) {
            return [
                'success' => false,
                'error' => __('Incompatible version. This file was exported from a different version of Notifal.', 'notifal')
            ];
        }

        $notificationData = $data['notification'];

        try {
            // Import template first if exists (pass bundled media for marketplace support)
            $templateId = 0;
            if (isset($notificationData['template'])) {
                $templateResult = self::handleTemplateImport($notificationData['template'], $bundledMedia);
                if (!$templateResult['success']) {
                    return [
                        'success' => false,
                        'error' => sprintf(__('Template import failed: %s', 'notifal'), $templateResult['error'])
                    ];
                }
                $templateId = $templateResult['template_id'] ?? 0;
            }

            // Create notification post
            $postResult = self::createNotificationPost($notificationData);
            if (!$postResult['success']) {
                return [
                    'success' => false,
                    'error' => sprintf(__('Failed to create notification: %s', 'notifal'), $postResult['error'])
                ];
            }
            $postId = $postResult['post_id'];

            // Import settings
            $settingsResult = self::importNotificationSettings($postId, $notificationData['settings'], $templateId);
            if (!$settingsResult['success']) {
                // Rollback post creation
                wp_delete_post($postId, true);
                return [
                    'success' => false,
                    'error' => sprintf(__('Failed to import settings: %s', 'notifal'), $settingsResult['error'])
                ];
            }

            // Import labels
            if (!empty($notificationData['labels'])) {
                self::importNotificationLabels($postId, $notificationData['labels']);
            }

            // Update template reference if template was imported
            if ($templateId) {
                update_post_meta($postId, '_notifal_template_id', $templateId);
            }

            return ['success' => true];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => sprintf(__('Import failed: %s', 'notifal'), $e->getMessage())
            ];
        }
    }

    /**
     * Validate import data structure.
     *
     * @since 2.0.0
     * @param array $data Import data.
     * @return bool True if valid, false otherwise.
     */
    private static function validateImportData(array $data): bool
    {
        // Check required top-level fields
        if (!isset($data['version']) || !isset($data['type']) || !isset($data['notification'])) {
            return false;
        }

        // Check type compatibility - support both old and new format
        $validTypes = ['notifal_onpage_notif', 'notifal_onpage_notification'];
        if (!in_array($data['type'], $validTypes, true)) {
            return false;
        }

        $notification = $data['notification'];

        // Check required notification fields
        if (!isset($notification['title']) || !isset($notification['settings'])) {
            return false;
        }

        // Check required settings - make them optional for backward compatibility
        $requiredSettings = ['appearance', 'behavior', 'timing', 'content_source', 'display_rules'];
        foreach ($requiredSettings as $setting) {
            if (!isset($notification['settings'][$setting])) {
                // Set default empty arrays for missing settings
                $notification['settings'][$setting] = [];
            }
        }

        return true;
    }

    /**
     * Check if version is compatible.
     *
     * @since 2.0.0
     * @param string $version Version string.
     * @return bool True if compatible, false otherwise.
     */
    private static function isVersionCompatible(string $version): bool
    {
        // For now, accept any 2.x version
        return version_compare($version, '2.0.0', '>=');
    }

    /**
     * Handle template import using the dedicated Templates Importer.
     *
     * Passes bundled media to support marketplace imports where template images
     * are packaged in ZIP files for portability.
     *
     * @since 2.0.0
     * @param array $templateData Template data with builder and content.
     * @param array $bundledMedia Map of bundled path => temp file path for bundled media.
     * @return array Array with 'success' boolean and 'template_id' int or 'error' string.
     */
    private static function handleTemplateImport(array $templateData, array $bundledMedia = []): array
    {
        return TemplateImporter::importFromData($templateData, $bundledMedia);
    }


    /**
     * Create notification post.
     *
     * @since 2.0.0
     * @param array $notificationData Notification data.
     * @return array Array with 'success' boolean and 'post_id' int or 'error' string.
     */
    private static function createNotificationPost(array $notificationData): array
    {
        $title = Helper::sanitizeInput($notificationData['title'], 'text');

        // Set default status for imported notifications with filter for customization
        $status = 'draft';
        $status = apply_filters(FilterHooks::ONPAGE_NOTIFICATION_IMPORT_POST_STATUS, $status, $notificationData);

        // Ensure valid status
        if (!in_array($status, ['publish', 'draft', 'private'], true)) {
            $status = 'draft';
        }

        $postId = wp_insert_post([
            'post_title' => $title,
            'post_type' => 'notifal_onpage_notif',
            'post_status' => $status,
        ]);

        if (is_wp_error($postId) || !$postId) {
            return ['success' => false, 'error' => __('Failed to create notification post.', 'notifal')];
        }

        return ['success' => true, 'post_id' => $postId];
    }

    /**
     * Import notification settings with validation.
     *
     * @since 2.0.0
     * @param int $postId Notification post ID.
     * @param array $settings Settings data.
     * @param int|null $importedTemplateId The ID of the newly imported template (if any).
     * @return array Array with 'success' boolean and 'error' string if failed.
     */
    private static function importNotificationSettings(int $postId, array $settings, ?int $importedTemplateId = null): array
    {
        try {
            // Get settings services
            $appearanceService = notifal_app(AppearanceSettingsService::class);
            $behaviorService = notifal_app(BehaviorSettingsService::class);
            $timingService = notifal_app(TimingSettingsService::class);
            $contentSourceService = notifal_app(ContentSourceService::class);
            $displayRulesService = notifal_app(DisplayRulesService::class);

            // Normalize AI-shaped or inconsistent settings before service sanitization.
            $settings = OnPageImportSettingsNormalizer::normalize($settings);

            // Get content source settings and update template reference to the (existing or new) imported template
            $contentSourceSettings = $settings['content_source'] ?? [];
            if ($importedTemplateId) {
                $contentSourceSettings['_notifal_template_id'] = $importedTemplateId;
            }

            // Validate and sanitize each setting type
            $validatedSettings = [
                'appearance_settings' => $appearanceService->sanitizeSettings($settings['appearance'] ?? []),
                'behavior_settings' => $behaviorService->sanitizeSettings($settings['behavior'] ?? []),
                'timing_settings' => $timingService->sanitizeSettings($settings['timing'] ?? []),
                'content_source_settings' => $contentSourceService->sanitizeSettings($contentSourceSettings),
                'display_rules_data' => $displayRulesService->sanitizeSettings($settings['display_rules'] ?? []),
                'rule_combination_logic' => Helper::sanitizeInput($settings['rule_combination_logic'] ?? 'OR', 'key'),
                'display_rules_visibility_mode' => DisplayRulesDataNormalizer::sanitizeVisibilityMode(
                    Helper::sanitizeInput($settings['display_rules_visibility_mode'] ?? 'show_if', 'key')
                ),
            ];

            // Save settings to post meta with correct _notifal_ prefixes
            $postMetaMappings = [
                'appearance_settings' => '_notifal_appearance_settings',
                'behavior_settings' => '_notifal_behavior_settings',
                'timing_settings' => '_notifal_timing_settings',
                'content_source_settings' => '_notifal_content_source_settings',
                'display_rules_data' => '_notifal_display_rules_data',
                'rule_combination_logic' => '_notifal_rule_combination_logic',
                'display_rules_visibility_mode' => '_notifal_display_rules_visibility_mode',
            ];

            foreach ($validatedSettings as $key => $value) {
                if (isset($postMetaMappings[$key])) {
                    update_post_meta($postId, $postMetaMappings[$key], $value);
                }
            }

            // Save additional postmeta that should be set during import
            update_post_meta($postId, '_notifal_notif_enabled', 0); // Disable imported notifications by default for safety
            update_post_meta(
                $postId,
                '_notifal_content_source_type',
                $validatedSettings['content_source_settings']['content_source_type'] ?? 'dynamic'
            );

            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Import notification labels.
     *
     * @since 2.0.0
     * @param int $postId Notification post ID.
     * @param array $labels Array of label slugs.
     * @return void
     */
    private static function importNotificationLabels(int $postId, array $labels): void
    {
        // Ensure labels exist before assigning
        $existingLabels = [];
        foreach ($labels as $labelSlug) {
            $term = get_term_by('slug', Helper::sanitizeInput($labelSlug, 'key'), 'notifal_label');
            if ($term) {
                $existingLabels[] = $term->term_id;
            }
        }

        if (!empty($existingLabels)) {
            wp_set_object_terms($postId, $existingLabels, 'notifal_label');
        }
    }
} 
