<?php

namespace Notifal\Shared\AdminUI\Traits;

defined('ABSPATH') || exit;

use Notifal\Shared\AdminUI\Toast\ToastManager;
use Notifal\Shared\Utils\Helper;

/**
 * Trait AdminOperationsTrait
 *
 * Provides common admin operations for managing custom post types.
 * Includes delete, duplicate, and empty trash functionality with proper
 * security checks, validation, and user feedback.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
trait AdminOperationsTrait
{
    /**
     * Handle deleting a single post (move to trash or permanent deletion).
     *
     * @param int $postId Post ID to delete
     * @param string $nonceAction Nonce action for verification
     * @param string $postType Expected post type
     * @param callable $getRedirectUrl Function to get redirect URL
     * @param array $hooks Array with 'deleted' and 'trashed' hook names
     * @return void
     * @since 2.0.0
     */
    protected static function handleDeletePost(
        int $postId,
        string $nonceAction,
        string $postType,
        callable $getRedirectUrl,
        array $hooks = []
    ): void {
        $nonce = Helper::sanitizeInput($_GET['_wpnonce'] ?? '', 'key');
        $status = Helper::sanitizeInput($_GET['status'] ?? '', 'text');
        $redirectUrl = $getRedirectUrl($status);

        // Validate post ID and type
        if (!$postId || get_post_type($postId) !== $postType) {
            ToastManager::error(__('Invalid post ID.', 'notifal'), $redirectUrl);
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($nonce, $nonceAction)) {
            ToastManager::error(__('Security check failed.', 'notifal'), $redirectUrl);
            return;
        }

        // Perform deletion based on status
        if ($status === 'trash') {
            $result = wp_delete_post($postId, true);
            $message = __('Post permanently deleted.', 'notifal');
            $hook = $hooks['deleted'] ?? null;
        } else {
            $result = wp_trash_post($postId);
            $message = __('Post moved to trash.', 'notifal');
            $hook = $hooks['trashed'] ?? null;
        }

        if (!$result) {
            ToastManager::error(__('Failed to delete post.', 'notifal'), $redirectUrl);
            return;
        }

        // Trigger appropriate hook
        if ($hook) {
            static::triggerHook($hook, [$postId, $status]);
        }

        ToastManager::success($message, $redirectUrl);
    }

    /**
     * Handle duplicating a single post with all metadata and terms.
     *
     * @param int $postId Post ID to duplicate
     * @param string $nonceAction Nonce action for verification
     * @param string $postType Expected post type
     * @param callable $getRedirectUrl Function to get redirect URL
     * @param string $hook Hook name for duplication action
     * @return void
     * @since 2.0.0
     */
    protected static function handleDuplicatePost(
        int $postId,
        string $nonceAction,
        string $postType,
        callable $getRedirectUrl,
        string $hook = ''
    ): void {
        $nonce = Helper::sanitizeInput($_GET['_wpnonce'] ?? '', 'key');
        $status = Helper::sanitizeInput($_GET['status'] ?? '', 'text');
        $redirectUrl = $getRedirectUrl($status);

        // Validate post ID and type
        if (!$postId || get_post_type($postId) !== $postType) {
            ToastManager::error(__('Invalid post ID.', 'notifal'), $redirectUrl);
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($nonce, $nonceAction)) {
            ToastManager::error(__('Security check failed.', 'notifal'), $redirectUrl);
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $postId)) {
            ToastManager::error(__('You do not have permission to duplicate this post.', 'notifal'), $redirectUrl);
            return;
        }

        // Get and validate original post
        $originalPost = Helper::getPostSafe($postId, $postType);
        if (!$originalPost) {
            ToastManager::error(__('Post not found.', 'notifal'), $redirectUrl);
            return;
        }

        // Prepare new post data.
        // Use wp_slash() on post_content (and post_excerpt) so that when wp_insert_post()
        // applies wp_unslash() before writing to DB, escape sequences are preserved
        // (e.g. \n in custom CSS, \u003c in SVG icons). Without this, duplicated block
        // editor templates get corrupted: \n becomes "n", \u003c becomes "u003c".
        $newPostData = [
            'post_title'     => $originalPost->post_title . ' (' . __('Copy', 'notifal') . ')',
            'post_content'   => wp_slash($originalPost->post_content),
            'post_excerpt'   => wp_slash($originalPost->post_excerpt),
            'post_status'    => 'draft',
            'post_type'      => $postType,
            'post_author'    => get_current_user_id(),
            'post_parent'    => $originalPost->post_parent,
            'menu_order'     => $originalPost->menu_order,
            'comment_status' => $originalPost->comment_status,
            'ping_status'    => $originalPost->ping_status,
        ];

        // Insert new post
        $newPostId = wp_insert_post($newPostData);
        if (is_wp_error($newPostId) || !$newPostId) {
            ToastManager::error(__('Failed to duplicate post. Please try again.', 'notifal'), $redirectUrl);
            return;
        }

        // Copy metadata efficiently
        self::copyPostMetadata($postId, $newPostId);

        // Copy taxonomy terms
        self::copyPostTerms($postId, $newPostId, $postType);

        // Trigger duplication hook
        if ($hook) {
            static::triggerHook($hook, [$postId, $newPostId]);
        }

        ToastManager::success(__('Post duplicated successfully.', 'notifal'), $redirectUrl);
    }

    /**
     * Handle emptying trash for a specific post type.
     *
     * @param string $postType Post type to empty trash for
     * @param string $nonceAction Nonce action for verification
     * @param callable $getRedirectUrl Function to get redirect URL
     * @param string $hook Hook name for trash emptied action
     * @return void
     * @since 2.0.0
     */
    protected static function handleEmptyTrash(
        string $postType,
        string $nonceAction,
        callable $getRedirectUrl,
        string $hook = ''
    ): void {
        $nonce = Helper::sanitizeInput($_GET['_wpnonce'] ?? '', 'key');

        // Verify nonce
        if (!wp_verify_nonce($nonce, $nonceAction)) {
            ToastManager::error(__('Security verification failed.', 'notifal'), $getRedirectUrl('trash'));
            return;
        }

        // Get trashed posts
        $trashedPosts = get_posts([
            'post_type'   => $postType,
            'post_status' => 'trash',
            'posts_per_page' => -1,
            'fields'      => 'ids',
        ]);

        if (empty($trashedPosts)) {
            ToastManager::success(__('Trash is already empty.', 'notifal'), $getRedirectUrl());
            return;
        }

        // Delete posts in batches for better performance
        $deletedCount = 0;
        foreach ($trashedPosts as $postId) {
            if (wp_delete_post($postId, true)) {
                $deletedCount++;
            }
        }

        // Trigger hook
        if ($hook) {
            static::triggerHook($hook, [$deletedCount]);
        }

        // Show success message with count
        $message = sprintf(
            _n(
                '%d post permanently deleted.',
                '%d posts permanently deleted.',
                $deletedCount,
                'notifal'
            ),
            $deletedCount
        );

        ToastManager::success($message, $getRedirectUrl());
    }

    /**
     * Copy all metadata from one post to another efficiently.
     *
     * @param int $sourcePostId Source post ID
     * @param int $targetPostId Target post ID
     * @return void
     * @since 2.0.0
     */
    private static function copyPostMetadata(int $sourcePostId, int $targetPostId): void
    {
        $allMeta = get_post_meta($sourcePostId);
        if (!$allMeta) {
            return;
        }

        foreach ($allMeta as $metaKey => $metaValues) {
            if (!empty($metaValues)) {
                foreach ($metaValues as $metaValue) {
                    add_post_meta($targetPostId, $metaKey, $metaValue);
                }
            }
        }
    }

    /**
     * Copy all taxonomy terms from one post to another.
     *
     * @param int $sourcePostId Source post ID
     * @param int $targetPostId Target post ID
     * @param string $postType Post type for taxonomy lookup
     * @return void
     * @since 2.0.0
     */
    private static function copyPostTerms(int $sourcePostId, int $targetPostId, string $postType): void
    {
        $taxonomies = get_object_taxonomies($postType);
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($sourcePostId, $taxonomy, ['fields' => 'slugs']);
            if (!is_wp_error($terms) && !empty($terms)) {
                wp_set_object_terms($targetPostId, $terms, $taxonomy);
            }
        }
    }

    /**
     * Trigger a hook with proper constant resolution.
     *
     * @param string $hookName Hook constant name (without class prefix)
     * @param array $args Arguments to pass to the hook
     * @return void
     * @since 2.0.0
     */
    private static function triggerHook(string $hookName, array $args = []): void
    {
        if (defined('static::HOOK_PREFIX')) {
            $hookConstant = constant('static::HOOK_PREFIX') . $hookName;
            if (defined($hookConstant)) {
                do_action(constant($hookConstant), ...$args);
            }
        }
    }
}