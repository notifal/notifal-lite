<?php

namespace Notifal\Domain\Tags\Services;

defined('ABSPATH') || exit;

/**
 * Utility class for detecting various tag patterns in template content.
 * Provides centralized tag detection logic to eliminate duplication across services.
 *
 * @package Notifal\Domain\Tags
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class TagDetector
{
    /**
     * Regex pattern for user-related tags.
     */
    public const USER_TAG_PATTERN = '/\{(user_|user_meta_)/i';

    /**
     * Regex pattern for post-related tags.
     */
    public const POST_TAG_PATTERN = '/\{(post_|post_meta_)/i';

    /**
     * Regex pattern for page-related tags.
     */
    public const PAGE_TAG_PATTERN = '/\{(page_|page_meta_)/i';

    /**
     * Regex pattern for comment-related tags.
     */
    public const COMMENT_TAG_PATTERN = '/\{(comment_|comment_meta_)/i';

    /**
     * Regex pattern for order-related tags.
     */
    public const ORDER_TAG_PATTERN = '/\{(order_|order_meta_)/i';

    /**
     * Regex pattern for product-related tags including product_name.
     */
    public const PRODUCT_TAG_PATTERN = '/\{(product_|product_meta_|product_name)/i';

    /**
     * Regex pattern for WooCommerce cart-related tags.
     *
     * @since 2.3.5
     */
    public const CART_TAG_PATTERN = '/\{cart_/i';

    /**
     * Check if template content contains user-related tags.
     *
     * @param string $templateContent Template content to check.
     * @return bool True if user tags are found.
     * @since 2.0.0
     */
    public static function hasUserTags(string $templateContent): bool
    {
        return preg_match(self::USER_TAG_PATTERN, $templateContent) === 1;
    }

    /**
     * Check if template content contains post-related tags.
     *
     * @param string $templateContent Template content to check.
     * @return bool True if post tags are found.
     * @since 2.0.0
     */
    public static function hasPostTags(string $templateContent): bool
    {
        return preg_match(self::POST_TAG_PATTERN, $templateContent) === 1;
    }

    /**
     * Check if template content contains page-related tags.
     *
     * @param string $templateContent Template content to check.
     * @return bool True if page tags are found.
     * @since 2.0.0
     */
    public static function hasPageTags(string $templateContent): bool
    {
        return preg_match(self::PAGE_TAG_PATTERN, $templateContent) === 1;
    }

    /**
     * Check if template content contains comment-related tags.
     *
     * @param string $templateContent Template content to check.
     * @return bool True if comment tags are found.
     * @since 2.0.0
     */
    public static function hasCommentTags(string $templateContent): bool
    {
        return preg_match(self::COMMENT_TAG_PATTERN, $templateContent) === 1;
    }

    /**
     * Check if template content contains order-related tags.
     *
     * @param string $templateContent Template content to check.
     * @return bool True if order tags are found.
     * @since 2.0.0
     */
    public static function hasOrderTags(string $templateContent): bool
    {
        return preg_match(self::ORDER_TAG_PATTERN, $templateContent) === 1;
    }

    /**
     * Check if template content contains product-related tags.
     *
     * @param string $templateContent Template content to check.
     * @return bool True if product tags are found.
     * @since 2.0.0
     */
    public static function hasProductTags(string $templateContent): bool
    {
        return preg_match(self::PRODUCT_TAG_PATTERN, $templateContent) === 1;
    }

    /**
     * Check if template content contains WooCommerce cart-related tags.
     *
     * @param string $templateContent Template content to check.
     * @return bool True if cart tags are found.
     * @since 2.3.5
     */
    public static function hasCartTags(string $templateContent): bool
    {
        return preg_match(self::CART_TAG_PATTERN, $templateContent) === 1;
    }

    /**
     * Count occurrences of user-related tags in template content.
     *
     * @param string $templateContent Template content to analyze.
     * @return int Number of user tag occurrences.
     * @since 2.0.0
     */
    public static function countUserTags(string $templateContent): int
    {
        return preg_match_all(self::USER_TAG_PATTERN, $templateContent);
    }

    /**
     * Count occurrences of post-related tags in template content.
     *
     * @param string $templateContent Template content to analyze.
     * @return int Number of post tag occurrences.
     * @since 2.0.0
     */
    public static function countPostTags(string $templateContent): int
    {
        return preg_match_all(self::POST_TAG_PATTERN, $templateContent);
    }

    /**
     * Count occurrences of page-related tags in template content.
     *
     * @param string $templateContent Template content to analyze.
     * @return int Number of page tag occurrences.
     * @since 2.0.0
     */
    public static function countPageTags(string $templateContent): int
    {
        return preg_match_all(self::PAGE_TAG_PATTERN, $templateContent);
    }

    /**
     * Count occurrences of comment-related tags in template content.
     *
     * @param string $templateContent Template content to analyze.
     * @return int Number of comment tag occurrences.
     * @since 2.0.0
     */
    public static function countCommentTags(string $templateContent): int
    {
        return preg_match_all(self::COMMENT_TAG_PATTERN, $templateContent);
    }

    /**
     * Count occurrences of order-related tags in template content.
     *
     * @param string $templateContent Template content to analyze.
     * @return int Number of order tag occurrences.
     * @since 2.0.0
     */
    public static function countOrderTags(string $templateContent): int
    {
        return preg_match_all(self::ORDER_TAG_PATTERN, $templateContent);
    }

    /**
     * Count occurrences of product-related tags in template content.
     * Includes product_name as a special case.
     *
     * @param string $templateContent Template content to analyze.
     * @return int Number of product tag occurrences.
     * @since 2.0.0
     */
    public static function countProductTags(string $templateContent): int
    {
        $count = preg_match_all(self::PRODUCT_TAG_PATTERN, $templateContent);

        // Also count standalone {product_name} tags
        $productNameCount = preg_match_all('/\{product_name\}/i', $templateContent);

        return $count + $productNameCount;
    }

    /**
     * Count occurrences of cart-related tags in template content.
     *
     * @param string $templateContent Template content to analyze.
     * @return int Number of cart tag occurrences.
     * @since 2.3.5
     */
    public static function countCartTags(string $templateContent): int
    {
        return preg_match_all(self::CART_TAG_PATTERN, $templateContent);
    }

    /**
     * Check whether template content contains any known Notifal merge tags.
     *
     * Used by template static/dynamic classification in the admin picker.
     *
     * @param string $templateContent Raw template HTML or builder content.
     * @return bool True when at least one Notifal tag pattern is present.
     * @since 2.4.0
     */
    public static function hasAnyNotifalTags(string $templateContent): bool
    {
        if ($templateContent === '') {
            return false;
        }

        // Decode entities so tags saved as &#123;product_name&#125; still match.
        $content = html_entity_decode($templateContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (
            self::hasUserTags($content)
            || self::hasPostTags($content)
            || self::hasPageTags($content)
            || self::hasCommentTags($content)
            || self::hasOrderTags($content)
            || self::hasProductTags($content)
            || self::hasCartTags($content)
        ) {
            return true;
        }

        // Custom post type counter tags, e.g. {custom_posttype_counter_book}.
        return preg_match('/\{custom_posttype_counter_[a-zA-Z0-9_]+\}/i', $content) === 1;
    }

    /**
     * Get all tag counts for different entity types.
     *
     * @param string $templateContent Template content to analyze.
     * @return array Associative array with entity types as keys and counts as values.
     * @since 2.0.0
     */
    public static function getAllTagCounts(string $templateContent): array
    {
        return [
            'user' => self::countUserTags($templateContent),
            'post' => self::countPostTags($templateContent),
            'page' => self::countPageTags($templateContent),
            'comment' => self::countCommentTags($templateContent),
            'order' => self::countOrderTags($templateContent),
            'product' => self::countProductTags($templateContent),
            'cart' => self::countCartTags($templateContent),
        ];
    }
}
