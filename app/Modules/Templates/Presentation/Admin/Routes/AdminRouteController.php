<?php

namespace Notifal\Modules\Templates\Presentation\Admin\Routes;

defined('ABSPATH') || exit;

use Notifal\Infrastructure\WordPress\Elementor\Helpers\ElementorHelper;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Templates\Application\Services\TemplateBuilderDetector;
use Notifal\Modules\Templates\Application\Services\TemplateUrlService;
use Notifal\Shared\AdminUI\Traits\AdminOperationsTrait;
use Notifal\Shared\AdminUI\Toast\ToastManager;
use Notifal\Shared\Utils\Helper;

/**
 * Class AdminRouteController
 *
 * Handles admin-side routes/actions for templates dispatched via `admin_init`.
 * Provides CRUD operations including delete, duplicate, empty trash, and Elementor template creation
 * with proper security validation and user feedback.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class AdminRouteController
{
    use AdminOperationsTrait;

    /**
     * Hook prefix for action hooks.
     */
    protected const HOOK_PREFIX = 'Notifal\\Infrastructure\\WordPress\\Hooks\\ActionHooks::';

    /**
     * Post type constant for templates.
     */
    protected const POST_TYPE = 'notifal_template';

    /**
     * Registers the admin route dispatcher.
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        add_action('admin_init', [self::class, 'dispatch']);
    }

    /**
     * Dispatch the route based on `$_GET['action']`.
     *
     * @return void
     * @since 2.0.0
     * @since 2.4.0 Add support for HTML Builder templates.
     */
    public static function dispatch(): void
    {
        if (!is_admin()) {
            return;
        }

        $action = Helper::sanitizeInput($_GET['action'] ?? '', 'text');

        switch ($action) {
            case 'notifal_delete_notifal_template':
                self::handleDeleteTemplate();
                break;

            case 'notifal_duplicate_notifal_template':
                self::handleDuplicateTemplate();
                break;

            case 'notifal_empty_trash_notifal_template':
                self::handleEmptyTrash();
                break;

            case 'notifal_create_elementor_template':
                self::handleCreateElementorTemplate();
                break;

            case 'notifal_create_notifal_html_builder':
                self::handleCreateHtmlBuilderTemplate();
                break;
        }
    }

    /**
     * Handles deleting a single template.
     *
     * @return void
     * @since 2.0.0
     */
    protected static function handleDeleteTemplate(): void
    {
        $postId = absint($_GET['id'] ?? 0);

        self::handleDeletePost(
            $postId,
            "delete_notifal_template_$postId",
            self::POST_TYPE,
            function ($status) {
                return admin_url('admin.php?page=notifal_templates' . ($status ? '&status=' . urlencode($status) : ''));
            },
            [
                'deleted' => 'TEMPLATE_DELETED',
                'trashed' => 'TEMPLATE_TRASHED',
            ]
        );
    }

    /**
     * Handles duplicating a single template.
     *
     * @return void
     * @since 2.0.0
     */
    protected static function handleDuplicateTemplate(): void
    {
        $postId = absint($_GET['id'] ?? 0);

        self::handleDuplicatePost(
            $postId,
            "duplicate_notifal_template_$postId",
            self::POST_TYPE,
            function ($status) {
                return admin_url('admin.php?page=notifal_templates' . ($status ? '&status=' . urlencode($status) : ''));
            },
            'TEMPLATE_DUPLICATED'
        );
    }

    /**
     * Empties the trash for templates.
     *
     * @return void
     * @since 2.0.0
     */
    protected static function handleEmptyTrash(): void
    {
        self::handleEmptyTrash(
            self::POST_TYPE,
            'empty_trash_notifal_template',
            function ($status = '') {
                return admin_url('admin.php?page=notifal_templates' . ($status === 'trash' ? '&status=trash' : ''));
            },
            'TEMPLATE_TRASH_EMPTIED'
        );
    }

    /**
     * Creates a new Elementor-based template and redirects to editor.
     *
     * @return void
     * @since 2.0.0
     */
    protected static function handleCreateElementorTemplate(): void
    {
        $nonce = Helper::sanitizeInput($_GET['_wpnonce'] ?? '', 'key');

        if (!wp_verify_nonce($nonce, 'notifal_create_elementor_template')) {
            wp_die(__('Security check failed.', 'notifal'));
        }

        if (!current_user_can('edit_posts')) {
            wp_die(__('You are not allowed to create templates.', 'notifal'));
        }

        $postData = [
            'post_title' => apply_filters(FilterHooks::TEMPLATE_DEFAULT_TITLE, __('New Elementor Template', 'notifal')),
            'post_type'  => self::POST_TYPE,
            'post_status' => 'draft',
        ];

        $postId = wp_insert_post($postData);

        if (is_wp_error($postId) || !$postId) {
            wp_die(__('Failed to create a new template. Please try again.', 'notifal'));
        }

        wp_update_post([
            'ID'         => $postId,
            'post_title' => apply_filters(FilterHooks::TEMPLATE_FINAL_TITLE, sprintf(__('New Elementor Template #%d', 'notifal'), $postId), $postId),
        ]);

        do_action(ActionHooks::TEMPLATE_CREATED, $postId);

        wp_redirect(ElementorHelper::getEditUrl($postId));
        exit;
    }

    /**
     * Creates a new HTML Builder template and redirects to the builder screen.
     *
     * @return void
     * @since 2.4.0
     * @author Hossein <hossein@notifal.com>
     */
    protected static function handleCreateHtmlBuilderTemplate(): void
    {
        $nonce = Helper::sanitizeInput($_GET['_wpnonce'] ?? '', 'key');

        if (!wp_verify_nonce($nonce, 'notifal_create_notifal_html_builder')) {
            wp_die(__('Security check failed.', 'notifal'));
        }

        if (!current_user_can('edit_posts')) {
            wp_die(__('You are not allowed to create templates.', 'notifal'));
        }

        $postId = wp_insert_post([
            'post_title'  => apply_filters(FilterHooks::TEMPLATE_DEFAULT_TITLE, __('New HTML Template', 'notifal')),
            'post_type'   => self::POST_TYPE,
            'post_status' => 'draft',
        ]);

        if (is_wp_error($postId) || !$postId) {
            wp_die(__('Failed to create a new template. Please try again.', 'notifal'));
        }

        wp_update_post([
            'ID'         => $postId,
            'post_title' => apply_filters(
                FilterHooks::TEMPLATE_FINAL_TITLE,
                sprintf(__('New HTML Template #%d', 'notifal'), $postId),
                $postId
            ),
        ]);

        update_post_meta($postId, '_notifal_builder', TemplateBuilderDetector::BUILDER_HTML);

        do_action(ActionHooks::TEMPLATE_CREATED, $postId);

        /** @var TemplateUrlService $urlService */
        $urlService = notifal_app(TemplateUrlService::class);
        wp_redirect($urlService->getEditHtmlBuilderUrl($postId));
        exit;
    }
}
