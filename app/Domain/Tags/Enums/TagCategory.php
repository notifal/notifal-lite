<?php

namespace Notifal\Domain\Tags\Enums;

use Notifal\Domain\Settings\Services\SettingsService;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class TagCategory
 *
 * Defines all available tag categories in Notifal.
 * Allows developers to extend categories via filters.
 *
 * @package Notifal\Domain\Tags\Enums
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
final class TagCategory
{
    /**
     * Tags related to product data.
     */
    public const PRODUCTS = 'products';

    /**
     * Tags related to order data.
     */
    public const ORDERS = 'orders';

    /**
     * Tags related to user data.
     */
    public const USERS = 'users';

    /**
     * Tags related to WordPress posts.
     */
    public const POSTS = 'posts';

    /**
     * Tags related to WordPress pages.
     */
    public const PAGES = 'pages';

    /**
     * Tags related to WordPress comments.
     */
    public const COMMENTS = 'comments';


    /**
     * General tags not tied to a specific domain.
     */
    public const GENERAL = 'general';

    /**
     * Get all tag categories.
     *
     * This includes default categories and any additional ones added
     * via the notifal/tag/categories filter, plus dynamic custom post type categories.
     *
     * @return string[]
     * @since 2.0.0
     */
    public static function all(): array
    {
        $categories = [
            self::PRODUCTS,
            self::ORDERS,
            self::USERS,
            self::POSTS,
            self::PAGES,
            self::GENERAL,
        ];

        // Add COMMENTS category only if Notifal Pro is active and allows comment tags
        if (apply_filters('notifal_pro_comment_tags_allowed', false)) {
            $categories[] = self::COMMENTS;
        }

        // Add dynamic custom post type categories
        try {
            $settingsService = notifal_app(SettingsService::class);
            $generatedPostTypes = $settingsService->get('generated_posttype_list', []);
            $categories = array_merge($categories, $generatedPostTypes);
        } catch (\Exception $e) {
            // Log the error for debugging while maintaining functionality
            // This ensures the method doesn't break if called during early initialization
            Helper::logAdvanced(
                'Failed to load custom post type categories: ' . $e->getMessage(),
                'ERROR'
            );
        }

        /**
         * Filter: notifal/tag/categories
         *
         *
         * @param string[] $categories Array of tag categories.
         * @return string[]
         * @since 2.0.0
         */
        return apply_filters(FilterHooks::TAG_CATEGORIES, $categories);
    }
}
