<?php

namespace Notifal\Infrastructure\WordPress\Elementor\Helpers;

use Notifal\Core\Support\Helpers\UrlHelper;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class ElementorHelper
 * Provides utility methods to inspect and work with Elementor posts.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ElementorHelper
{
    /**
     * Check if a post is built using Elementor.
     *
     * @param int|\WP_Post|null $post Post ID or object
     * @return bool
     * @since 2.0.0
     */
    public static function hasBuilder($post): bool
    {
        $post = Helper::getPostSafe(is_object($post) ? $post->ID : (int) $post);
        if (!$post) {
            return false;
        }

        return get_post_meta($post->ID, '_elementor_edit_mode', true) === 'builder';
    }

    /**
     * Get Elementor edit URL for a given post ID.
     *
     * @param int $postId
     * @return string
     * @since 2.0.0
     */
    public static function getEditUrl(int $postId): string
    {
        return UrlHelper::admin("post.php?post={$postId}&action=elementor");
    }
}
