<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\Menu;

use Notifal\Shared\AdminUI\Toast\ToastManager;
use Notifal\Shared\AdminUI\Lists\BaseListView;

defined('ABSPATH') || exit;

/**
 * Class MenuController
 *
 * Registers the OnPage Notification submenu page under the main Notifal menu in wp-admin.
 *
 * @since 2.0.0
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Menu
 */
class MenuController
{
    /**
     * Register admin_menu hook for submenu registration.
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'addMenu'], 20);
        add_filter('submenu_file', [self::class, 'hideEditPageFromMenu']);
    }

    /**
     * Add submenu pages for OnPage Notifications under the Notifal menu.
     *
     * @return void
     * @since 2.0.0
     */
    public static function addMenu(): void
    {
        // Add the main list page
        $hook = add_submenu_page(
            'notifal',
            __('OnPage Notifications', 'notifal'),
            __('OnPage Notifications', 'notifal'),
            'manage_options',
            'notifal-onpage-notifications',
            [self::class, 'renderList'],
        );

        // Handle bulk actions on this specific page load
        add_action("load-{$hook}", [self::class, 'handleBulkActions']);

        // Add the analytics dashboard page
        add_submenu_page(
            'notifal',
            __('OnPage Analytics', 'notifal'),
            __('OnPage Analytics', 'notifal'),
            'manage_options',
            'notifal-onpage-analytics',
            [self::class, 'renderAnalytics'],
        );

        // Add the edit page (hidden from menu but accessible via URL)
        add_submenu_page(
            'notifal',
            __('OnPage Notification', 'notifal'),
            __('OnPage Notification', 'notifal'),
            'manage_options',
            'notifal-onpage-notification',
            [self::class, 'renderEdit'],
        );
    }

    /**
     * Render the admin view for OnPage Notifications list.
     *
     * @return void
     * @since 2.0.0
     */
    public static function renderList(): void
    {
        notifal_view('OnPageNotification.Admin.notifications-list');
    }

    /**
     * Render the admin view for OnPage Notification edit page.
     *
     * @return void
     * @since 2.0.0
     */
    public static function renderEdit(): void
    {
        notifal_view('OnPageNotification.Admin.Edit.index');
    }

    /**
     * Render the admin view for OnPage Analytics dashboard.
     *
     * @return void
     * @since 2.0.0
     */
    public static function renderAnalytics(): void
    {
        notifal_view('OnPageNotification.Admin.Analytics.dashboard');
    }

    /**
     * Handle bulk actions for OnPage Notifications before rendering.
     *
     * This method processes POST requests for bulk actions and redirects with appropriate messages.
     * Must be called before any output is sent to avoid "headers already sent" errors.
     *
     * @return void
     * @since 2.0.0
     */
    public static function handleBulkActions(): void
    {
        // Use the generic BaseListView method for onpage notifications
        BaseListView::handleBulkActionsForPostType('notifal_onpage_notif');
    }

    /**
     * Handle bulk restore action.
     *
     * @param array $ids Post IDs to restore
     * @return void
     * @since 2.0.0
     */
    protected static function handleBulkRestore(array $ids): void
    {
        foreach ($ids as $id) {
            if (get_post_status($id) === 'trash') {
                wp_untrash_post($id);
            }
        }

        $redirectUrl = add_query_arg([
            'page' => 'notifal-onpage-notifications',
            'status' => 'all'
        ], admin_url('admin.php'));

        ToastManager::success(__('Items restored successfully.', 'notifal'), $redirectUrl);
    }

    /**
     * Handle bulk delete action.
     *
     * @param array $ids Post IDs to delete
     * @param string $currentStatus Current status filter
     * @return void
     * @since 2.0.0
     */
    protected static function handleBulkDelete(array $ids, string $currentStatus): void
    {
        $deleted = 0;
        $postType = 'notifal_onpage_notif';

        foreach ($ids as $id) {
            if (get_post_type($id) === $postType) {
                if ($currentStatus === 'trash') {
                    wp_delete_post($id, true);
                } else {
                    wp_trash_post($id);
                }
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $message = $currentStatus === 'trash'
                ? __('Items permanently deleted.', 'notifal')
                : __('Items moved to trash.', 'notifal');

            $redirectUrl = add_query_arg([
                'page' => 'notifal-onpage-notifications',
                'status' => 'all'
            ], admin_url('admin.php'));

            ToastManager::success($message, $redirectUrl);
        }
    }

    /**
     * Handle bulk duplicate action.
     *
     * @param array $ids Post IDs to duplicate
     * @return void
     * @since 2.0.0
     */
    protected static function handleBulkDuplicate(array $ids): void
    {
        $duplicatedCount = 0;
        $postType = 'notifal_onpage_notif';

        foreach ($ids as $id) {
            if (get_post_type($id) === $postType && self::duplicatePost($id)) {
                $duplicatedCount++;
            }
        }

        if ($duplicatedCount > 0) {
            $message = sprintf(
                _n(
                    '%d item duplicated successfully.',
                    '%d items duplicated successfully.',
                    $duplicatedCount,
                    'notifal'
                ),
                $duplicatedCount
            );

            $redirectUrl = add_query_arg([
                'page' => 'notifal-onpage-notifications',
                'status' => 'all'
            ], admin_url('admin.php'));

            ToastManager::success($message, $redirectUrl);
        } else {
            $redirectUrl = add_query_arg([
                'page' => 'notifal-onpage-notifications',
                'status' => 'all'
            ], admin_url('admin.php'));

            ToastManager::error(__('No items were duplicated. Please try again.', 'notifal'), $redirectUrl);
        }
    }

    /**
     * Duplicate a post with all its metadata.
     *
     * @param int $postId The post ID to duplicate
     * @return int|false The new post ID on success, false on failure
     * @since 2.0.0
     */
    protected static function duplicatePost(int $postId)
    {
        $post = get_post($postId);
        if (!$post) {
            return false;
        }

        $newPostData = [
            'post_title'   => $post->post_title . ' (' . __('Copy', 'notifal') . ')',
            'post_content' => $post->post_content,
            'post_status'  => 'draft',
            'post_type'    => $post->post_type,
            'post_author'  => $post->post_author,
            'post_excerpt' => $post->post_excerpt,
        ];

        $newPostId = wp_insert_post($newPostData);
        if (!$newPostId) {
            return false;
        }

        // Copy post meta
        $metaData = get_post_meta($postId);
        foreach ($metaData as $key => $values) {
            foreach ($values as $value) {
                add_post_meta($newPostId, $key, maybe_unserialize($value));
            }
        }

        return $newPostId;
    }

    /**
     * Hide the edit page from the submenu display.
     *
     * @param string|null $submenu_file The current submenu file
     * @return string|null
     * @since 2.0.0
     */
    public static function hideEditPageFromMenu($submenu_file)
    {
        global $submenu;

        if (isset($submenu['notifal'])) {
            foreach ($submenu['notifal'] as $key => $item) {
                if ($item[2] === 'notifal-onpage-notification') {
                    unset($submenu['notifal'][$key]);
                    break;
                }
            }
        }

        return $submenu_file;
    }

}
