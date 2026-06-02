<?php

namespace Notifal\Modules\OnPageNotification\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Templates\Application\Services\TemplateExportService;
use Notifal\Modules\OnPageNotification\Application\Services\Core\NotificationSaveService;
use Notifal\Shared\Services\BaseExportService;
use Notifal\Shared\Utils\Helper;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Class NotificationExportService
 *
 * Handles exporting OnPage Notifications as JSON or ZIP archive.
 * Includes all notification settings, display rules, and associated template data.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class NotificationExportService extends BaseExportService
{
    /**
     * Handle export for one or more notification posts.
     *
     * Delegates to parent class for common export logic.
     *
     * @param string $postType Post type name (must be 'notifal_onpage_notif').
     * @param int[] $ids List of notification post IDs.
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
        return 'notifal_onpage_notif';
    }

    /**
     * Get the export type identifier for file naming.
     *
     * @return string Export type.
     * @since 2.0.0
     */
    protected static function getExportType(): string
    {
        return 'notification';
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
        return 'notifal-onpage-notification-' . sanitize_title($post->post_title) . '.json';
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
        return 'notifal-onpage-notification-' . sanitize_file_name($post->post_title) . '-' . $post->ID . '.json';
    }

    /**
     * Generate ZIP archive name.
     *
     * @return string Archive name.
     * @since 2.0.0
     */
    protected static function generateZipArchiveName(): string
    {
        return 'notifal-onpage-notifications-export-' . date('Y-m-d') . '.zip';
    }

    /**
     * Prepare export data array for a notification post.
     *
     * Extracts notification settings, display rules, and associated template data with dependencies.
     * Pulls template media dependencies to root level for marketplace bundling.
     *
     * @param WP_Post $post The notification post.
     * @return array Structured export data ready for JSON serialization.
     * @since 2.0.0
     */
    public static function prepareExportData(WP_Post $post): array
    {
        // Get notification data using the save service
        $saveService = notifal_app(NotificationSaveService::class);
        $notificationData = $saveService->getNotificationData($post->ID);

        if (!$notificationData) {
            return [];
        }

        // Prepare basic notification data
        $data = [
            'version' => NOTIFAL_VERSION,
            'type' => 'notifal_onpage_notif',
            'notification' => [
                'id' => $post->ID,
                'title' => $post->post_title,
                'status' => $post->post_status,
                'labels' => self::getNotificationLabels($post->ID),
                'settings' => [
                    'appearance' => $notificationData['appearance_settings'] ?? [],
                    'behavior' => $notificationData['behavior_settings'] ?? [],
                    'timing' => $notificationData['timing_settings'] ?? [],
                    'content_source' => $notificationData['content_source_settings'] ?? [],
                    'display_rules' => $notificationData['display_rules_data'] ?? [],
                    'rule_combination_logic' => $notificationData['rule_combination_logic'] ?? 'OR',
                    'display_rules_visibility_mode' => $notificationData['display_rules_visibility_mode'] ?? 'show_if',
                ],
            ],
        ];

        // Initialize dependencies array for marketplace media bundling
        $data['dependencies'] = [
            'images' => [],
        ];

        // Add template data if exists and extract its dependencies
        if (!empty($notificationData['template_id'])) {
            $templateData = self::prepareTemplateData($notificationData['template_id']);
            if ($templateData) {
                $data['notification']['template'] = $templateData;
                
                // Pull template dependencies to root level for BaseExportService::bundleMediaInZip()
                if (!empty($templateData['dependencies']['images'])) {
                    $data['dependencies']['images'] = $templateData['dependencies']['images'];
                }
            }
        }

        return apply_filters(FilterHooks::EXPORT_ONPAGE_NOTIFICATION_DATA, $data, $post);
    }

    /**
     * Get notification labels as array of slugs.
     *
     * @param int $postId Notification post ID.
     * @return array Array of label slugs.
     * @since 2.0.0
     */
    private static function getNotificationLabels(int $postId): array
    {
        $terms = wp_get_object_terms($postId, 'notifal_label', ['fields' => 'slugs']);
        return is_array($terms) ? $terms : [];
    }

    /**
     * Prepare template data for export.
     *
     * @param int $templateId Template post ID.
     * @return array|null Template export data or null if not found.
     * @since 2.0.0
     */
    private static function prepareTemplateData(int $templateId): ?array
    {
        $templatePost = Helper::getPostSafe($templateId, 'notifal_template');
        if (!$templatePost) {
            return null;
        }

        // Use existing TemplateExportService to get template data
        $templateData = TemplateExportService::prepareExportData($templatePost);

        return [
            'id' => $templateId,
            'builder' => $templateData['builder'],
            'content' => $templateData['content'],
            'dependencies' => $templateData['dependencies'] ?? [],
        ];
    }

}
