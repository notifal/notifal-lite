<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Services\NotificationExportService;
use Notifal\Shared\Services\NotifalIconService;
use Notifal\Shared\Utils\Helper;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Class ExportController
 *
 * Registers export-related admin hooks and handles single and bulk export operations
 * for notifal_onpage_notif post type.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ExportController
{
    /**
     * Register all export-related admin hooks.
     *
     * Hooks into bulk actions, row actions, and admin_post for handling notification exports.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register(): void
    {
        add_action(ActionHooks::ADMIN_LIST_HANDLE_BULK_ACTION, [self::class, 'handleExportBulkAction'], 10, 3);
        add_filter(FilterHooks::ADMIN_LIST_ROW_ACTIONS, [self::class, 'addExportRowAction'], 10, 3);
        add_action('admin_post_notifal_export_single_notification', [self::class, 'handleSingleExport']);
    }

    /**
     * Handle bulk export action for notifal_onpage_notif post type.
     *
     * Delegates export logic to NotificationExportService if action is `export`.
     *
     * @param string $action   Current bulk action name.
     * @param int[]  $ids      Array of post IDs to export.
     * @param string $postType Post type of current bulk action.
     * @since 2.0.0
     * @return void
     */
    public static function handleExportBulkAction(string $action, array $ids, string $postType): void
    {
        if ($action === 'export' && $postType === 'notifal_onpage_notif') {
            NotificationExportService::handle($postType, $ids);
        }
    }

    /**
     * Add export icon to row actions for notifal_onpage_notif posts.
     *
     * @param array   $actions  Existing row actions.
     * @param WP_Post $post     Current post object.
     * @param string  $postType Current post type.
     * @since 2.0.0
     * @return array Modified row actions.
     */
    public static function addExportRowAction(array $actions, WP_Post $post, string $postType): array
    {
        if ($postType !== 'notifal_onpage_notif') {
            return $actions;
        }

        // Check if user has export capability
        if (!current_user_can('edit_posts')) {
            return $actions;
        }

        $exportUrl = add_query_arg([
            'action'           => 'notifal_export_single_notification',
            'notification_id'  => $post->ID,
            '_wpnonce'         => wp_create_nonce('notifal_export_single_notification_' . $post->ID),
        ], admin_url('admin-post.php'));

        $actions['export'] = sprintf(
            '<a href="%s" class="notifal-button secondary" title="%s" aria-label="%s">
                %s
            </a>',
            esc_url($exportUrl),
            esc_attr__('Export', 'notifal'),
            esc_attr(sprintf(__('Export %s', 'notifal'), $post->post_title)),
            NotifalIconService::render('download', 20)
        );

        return $actions;
    }

    /**
     * Handle exporting a single notifal_onpage_notif post via admin_post.
     *
     * Validates access and delegates to NotificationExportService for consistent export handling.
     *
     * @since 2.0.0
     * @return void
     */
    public static function handleSingleExport(): void
    {
        // Validate and sanitize notification ID first
        $notificationId = Helper::sanitizeInput($_GET['notification_id'] ?? '', 'int');

        if (!$notificationId || !Helper::isPositiveInt($notificationId)) {
            wp_die(__('Notification ID is required and must be a valid positive integer.', 'notifal'));
        }

        // Verify nonce and user capabilities using core helpers with sanitized notification ID
        notifal_verify_get_request('notifal_export_single_notification_' . $notificationId, 'edit_posts', '_wpnonce');

        $post = Helper::getPostSafe($notificationId, 'notifal_onpage_notif');

        if (!$post) {
            wp_die(__('Invalid notification.', 'notifal'));
        }

        // Use NotificationExportService::handle for consistent export behavior
        NotificationExportService::handle('notifal_onpage_notif', [$notificationId]);
    }
} 
