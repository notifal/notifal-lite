<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\ListTable;

defined('ABSPATH') || exit;

use Notifal\Core\Support\Helpers\UrlHelper;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\UrlService;
use Notifal\Modules\OnPageNotification\Helpers\NotificationHelper;
use Notifal\Shared\Services\NotifalIconService;
use WP_Post;

/**
 * Class ColumnsController
 *
 * Handles custom columns for the admin list table of notifal_onpage_notif post type.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ColumnsController
{
    /**
     * Register all related filters for custom columns.
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        add_filter(FilterHooks::ADMIN_LIST_CUSTOM_COLUMN, [self::class, 'renderStatusColumn'], 10, 4);
        add_filter(FilterHooks::ADMIN_LIST_CUSTOM_COLUMN, [self::class, 'renderEnabledColumn'], 10, 4);
        add_filter(FilterHooks::ADMIN_LIST_CUSTOM_COLUMN, [self::class, 'renderLabelsColumn'], 10, 4);
        add_filter(FilterHooks::ADMIN_LIST_ROW_ACTIONS, [self::class, 'modifyRowActions'], 10, 3);
        add_filter('get_edit_post_link', [self::class, 'modifyEditPostLink'], 10, 2);
    }

    /**
     * Render the "status" column for notifal_onpage_notif post type.
     *
     * @param string $content Default column content.
     * @param string $columnKey Current column key.
     * @param WP_Post $post The post object.
     * @param string $postType The post type.
     * @return string
     * @since 2.0.0
     */
    public static function renderStatusColumn(string $content, string $columnKey, WP_Post $post, string $postType): string
    {
        if ($postType !== 'notifal_onpage_notif' || $columnKey !== 'status') {
            return $content;
        }

        $status = get_post_status($post);
        $statusObj = get_post_status_object($status);
        
        if (!$statusObj) {
            return $content;
        }

        $statusClass = 'notifal-status-' . $status;
        $statusLabel = $statusObj->label;

        return sprintf(
            '<span class="notifal-status-badge %s">%s</span>',
            esc_attr($statusClass),
            esc_html($statusLabel)
        );
    }

    /**
     * Render the "enabled" column for notifal_onpage_notif post type.
     *
     * @param string $content Default column content.
     * @param string $columnKey Current column key.
     * @param WP_Post $post The post object.
     * @param string $postType The post type.
     * @return string
     * @since 2.0.0
     */
    public static function renderEnabledColumn(string $content, string $columnKey, WP_Post $post, string $postType): string
    {
        if ($postType !== 'notifal_onpage_notif' || $columnKey !== 'enabled') {
            return $content;
        }

        // CRITICAL: Enabled status is determined by meta key, NOT post_status
        // This is essential for proper licensing enforcement
        $isEnabled = NotificationHelper::isNotificationEnabled($post);
        $statusClass = NotificationHelper::getNotificationStatusClass($post);
        $statusLabel = NotificationHelper::getNotificationStatusLabel($post);
        $icon = $isEnabled ? 'check' : 'x-circle';

        return sprintf(
            '<button type="button" class="notifal-status-badge notifal-status-toggle %s" data-notification-id="%d" data-current-enabled="%s" title="%s" aria-label="%s">
                %s %s
            </button>',
            esc_attr($statusClass),
            esc_attr($post->ID),
            esc_attr($isEnabled ? '1' : '0'),
            esc_attr(sprintf(__('Click to %s this notification', 'notifal'), $isEnabled ? __('disable', 'notifal') : __('enable', 'notifal'))),
            esc_attr(sprintf(__('Toggle notification status. Currently %s.', 'notifal'), $statusLabel)),
            NotifalIconService::render($icon, 16),
            esc_html($statusLabel)
        );
    }

    /**
     * Render the "labels" column for notifal_onpage_notif post type.
     *
     * @param string $content Default column content.
     * @param string $columnKey Current column key.
     * @param WP_Post $post The post object.
     * @param string $postType The post type.
     * @return string
     * @since 2.0.0
     */
    public static function renderLabelsColumn(string $content, string $columnKey, WP_Post $post, string $postType): string
    {
        if ($postType !== 'notifal_onpage_notif' || $columnKey !== 'labels') {
            return $content;
        }

        // Get labels as taxonomy terms
        $labels = wp_get_object_terms($post->ID, 'notifal_label', ['fields' => 'names']);
        
        if (empty($labels) || is_wp_error($labels)) {
            return '<span class="notifal-no-labels">' . __('No labels', 'notifal') . '</span>';
        }

        if (!is_array($labels)) {
            $labels = [$labels];
        }

        $labelHtml = [];
        foreach ($labels as $labelName) {
            $labelHtml[] = sprintf(
                '<span class="notifal-label-badge">%s</span>',
                esc_html($labelName)
            );
        }

        return implode(' ', $labelHtml);
    }

    /**
     * Modify row actions for OnPage notifications to use custom edit URLs.
     *
     * @param array $actions Current actions array
     * @param WP_Post $post The post object
     * @param string $postType The post type
     * @return array Modified actions array
     * @since 2.0.0
     */
    public static function modifyRowActions(array $actions, WP_Post $post, string $postType): array
    {
        if ($postType !== 'notifal_onpage_notif') {
            return $actions;
        }

        // Remove view action
        unset($actions['view']);

        // Replace edit action with custom URL
        if (isset($actions['edit'])) {
            $urlService = notifal_app(UrlService::class);
            $editUrl = $urlService->getEditNotificationUrl($post->ID);
            $actions['edit'] = sprintf(
                '<a href="%s" class="notifal-button secondary" title="%s" aria-label="%s">
                    %s
                </a>',
                esc_url($editUrl),
                esc_attr__('Edit', 'notifal'),
                esc_attr(sprintf(__('Edit %s', 'notifal'), $post->post_title)),
                NotifalIconService::render('pencil-square', 20)
            );
        }

        // Add Preview row action for users who can edit
        if (current_user_can('edit_posts')) {
            $urlService = notifal_app(UrlService::class);
            $previewUrl = $urlService->getPreviewUrl($post->ID);
            $actions['preview'] = sprintf(
                '<a href="%s" class="notifal-button secondary" title="%s" target="_blank" rel="noopener noreferrer" aria-label="%s">%s</a>',
                esc_url($previewUrl),
                esc_attr__('Preview', 'notifal'),
                esc_attr(sprintf(__('Preview %s', 'notifal'), $post->post_title)),
                NotifalIconService::render('eye', 20)
            );
        }

        return $actions;
    }

    /**
     * Modify the edit post link for OnPage notifications to use custom URL.
     *
     * @param string $link The edit post link
     * @param int $postId The post ID
     * @return string Modified edit link
     * @since 2.0.0
     */
    public static function modifyEditPostLink(string $link, int $postId): string
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== 'notifal_onpage_notif') {
            return $link;
        }

        $urlService = notifal_app(UrlService::class);
        return $urlService->getEditNotificationUrl($postId);
    }
} 
