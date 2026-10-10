<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Inline;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Detects inline OnPage notification shortcode usage in page content.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services\Inline
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class InlineNotificationPageDetector
{
    /**
     * Inline shortcode tag registered by Notifal Pro.
     *
     * @since 3.0.0
     */
    public const SHORTCODE_TAG = 'notifal-onpage-notification';

    /**
     * Register WordPress hooks for inline shortcode page detection.
     *
     * @return void
     * @since 3.0.0
     */
    public function register(): void
    {
        // Signal asset loading when the inline shortcode is present in post content.
        add_filter(FilterHooks::ONPAGE_PAGE_CONTAINS_NOTIFICATIONS, [$this, 'filterPageContainsNotifications'], 10, 1);
    }

    /**
     * Mark the current page as containing notifications when the inline shortcode exists.
     *
     * @param bool $containsNotifications Existing detection result.
     * @return bool
     * @since 3.0.0
     */
    public function filterPageContainsNotifications(bool $containsNotifications): bool
    {
        // Preserve a positive result from earlier filters.
        if ($containsNotifications) {
            return true;
        }

        // Scan the main queried post content for the inline shortcode tag.
        return self::currentPageHasInlineShortcode();
    }

    /**
     * Check whether the current singular post content includes the inline shortcode.
     *
     * @return bool
     * @since 3.0.0
     */
    public static function currentPageHasInlineShortcode(): bool
    {
        // Bail when there is no singular post context to inspect.
        if (!is_singular()) {
            return false;
        }

        // Load the current post object safely.
        $post = get_post();
        if (!$post instanceof \WP_Post) {
            return false;
        }

        // Delegate to the reusable content scanner.
        return self::postContentHasInlineShortcode($post->post_content);
    }

    /**
     * Detect inline shortcode usage inside raw post content.
     *
     * @param string $content Raw post content.
     * @return bool
     * @since 3.0.0
     */
    public static function postContentHasInlineShortcode(string $content): bool
    {
        // Empty content cannot contain a shortcode.
        if ($content === '') {
            return false;
        }

        // Prefer WordPress has_shortcode when the tag is already registered.
        if (shortcode_exists(self::SHORTCODE_TAG) && has_shortcode($content, self::SHORTCODE_TAG)) {
            return true;
        }

        // Fallback marker scan for early bootstrap before Pro registers the tag.
        return (bool) preg_match('/\[' . preg_quote(self::SHORTCODE_TAG, '/') . '\b/i', $content);
    }

    /**
     * Extract notification IDs referenced by inline shortcodes in content.
     *
     * @param string $content Raw post content.
     * @return int[] Unique notification IDs in content order.
     * @since 3.0.0
     */
    public static function extractShortcodeNotificationIds(string $content): array
    {
        // Bail early when no shortcode marker exists.
        if (!self::postContentHasInlineShortcode($content)) {
            return [];
        }

        // Collect every id attribute from inline shortcode instances.
        $matches = [];
        preg_match_all(
            '/\[' . preg_quote(self::SHORTCODE_TAG, '/') . '[^\]]*id=[\"\'](\d+)[\"\'][^\]]*\]/i',
            $content,
            $matches
        );

        // Normalize IDs to positive integers and preserve uniqueness.
        $ids = [];
        foreach ($matches[1] ?? [] as $rawId) {
            $id = absint($rawId);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}
