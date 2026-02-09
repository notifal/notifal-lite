<?php

namespace Notifal\Modules\Templates\Presentation\Admin\Controllers;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Templates\Application\Services\TemplateExportService;
use Notifal\Shared\Services\NotifalIconService;
use Notifal\Shared\Utils\Helper;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Class ExportController
 *
 * Registers export-related admin hooks and handles single and bulk export operations
 * for notifal_template post type.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ExportController
{
    /**
     * Register all export-related admin hooks.
     *
     * Hooks into bulk actions, row actions, and admin_post for handling template exports.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register(): void
    {
        add_action(ActionHooks::ADMIN_LIST_HANDLE_BULK_ACTION, [self::class, 'handleExportBulkAction'], 10, 3);
        add_filter(FilterHooks::ADMIN_LIST_ROW_ACTIONS, [self::class, 'addExportRowAction'], 10, 3);
        add_action('admin_post_notifal_export_single', [self::class, 'handleSingleExport']);
    }

    /**
     * Handle bulk export action for notifal_template post type.
     *
     * Delegates export logic to Exporter service if action is `export`.
     *
     * @param string $action   Current bulk action name.
     * @param int[]  $ids      Array of post IDs to export.
     * @param string $postType Post type of current bulk action.
     * @since 2.0.0
     * @return void
     */
    public static function handleExportBulkAction(string $action, array $ids, string $postType): void
    {
        if ($action === 'export' && $postType === 'notifal_template') {
            TemplateExportService::handle($postType, $ids);
        }
    }

    /**
     * Add export icon to row actions for notifal_template posts.
     *
     * @param array   $actions  Existing row actions.
     * @param WP_Post $post     Current post object.
     * @param string  $postType Current post type.
     * @since 2.0.0
     * @return array Modified row actions.
     */
    public static function addExportRowAction(array $actions, WP_Post $post, string $postType): array
    {
        if ($postType !== 'notifal_template') {
            return $actions;
        }

        // Check if user has export capability
        if (!current_user_can('edit_posts')) {
            return $actions;
        }

        $exportUrl = add_query_arg([
            'action'      => 'notifal_export_single',
            'template_id' => $post->ID,
            '_wpnonce'    => wp_create_nonce('notifal_export_single_' . $post->ID),
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
     * Handle exporting a single notifal_template post via admin_post.
     *
     * Validates access, loads post, prepares data, and triggers file download response.
     *
     * @since 2.0.0
     * @return void
     */
    public static function handleSingleExport(): void
    {
        // Validate and sanitize template ID first
        $templateId = Helper::sanitizeInput($_GET['template_id'] ?? '', 'int');

        if (!$templateId || !Helper::isPositiveInt($templateId)) {
            wp_die(__('Template ID is required and must be a valid positive integer.', 'notifal'));
        }

        // Verify nonce and user capabilities using core helpers with sanitized template ID
        notifal_verify_get_request('notifal_export_single_' . $templateId, 'edit_posts', '_wpnonce');

        $post = Helper::getPostSafe($templateId, 'notifal_template');

        if (!$post) {
            wp_die(__('Invalid template.', 'notifal'));
        }

        // Use TemplateExportService::handle for consistent export behavior
        TemplateExportService::handle('notifal_template', [$templateId]);
    }
} 
