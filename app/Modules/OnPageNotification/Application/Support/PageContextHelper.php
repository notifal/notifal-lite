<?php

namespace Notifal\Modules\OnPageNotification\Application\Support;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Shared helpers for resolving singular vs archive visitor page context.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Support
 * @since 2.3.7
 * @author Hossein <hossein@notifal.com>
 */
class PageContextHelper
{
    /**
     * Default post_type values that represent taxonomy/archive views in frontend context.
     *
     * @var string[]
     */
    private const DEFAULT_ARCHIVE_CONTEXT_POST_TYPES = [
        'category',
        'product_category',
        'tag',
        'product_tag',
        'archive',
    ];

    /**
     * Resolve archive pseudo post types used in frontend/API context payloads.
     *
     * @return string[]
     * @since 2.3.7
     */
    public static function getArchiveContextPostTypes(): array
    {
        // Allow extensions to register additional archive context post types.
        $postTypes = apply_filters(
            FilterHooks::ONPAGE_ARCHIVE_CONTEXT_POST_TYPES,
            self::DEFAULT_ARCHIVE_CONTEXT_POST_TYPES
        );

        if (!is_array($postTypes)) {
            return self::DEFAULT_ARCHIVE_CONTEXT_POST_TYPES;
        }

        return array_values(array_unique(array_filter(array_map('sanitize_key', $postTypes))));
    }

    /**
     * Determine whether the visitor context represents a taxonomy/archive view.
     *
     * @param array<string, mixed> $context Visitor page context.
     * @return bool
     * @since 2.3.7
     */
    public static function isArchiveContext(array $context): bool
    {
        // Read the post_type slug supplied by PHP or the frontend client.
        $postType = sanitize_key((string) ($context['post_type'] ?? ''));

        if ($postType === '') {
            return false;
        }

        // Archive views use taxonomy pseudo-types rather than registered post types.
        return in_array($postType, self::getArchiveContextPostTypes(), true);
    }

    /**
     * Determine whether the visitor context represents a singular registered post type.
     *
     * @param array<string, mixed> $context Visitor page context.
     * @return bool
     * @since 2.3.7
     */
    public static function isSingularContext(array $context): bool
    {
        // Require a concrete object ID and post type slug.
        $pageId = absint($context['page_id'] ?? 0);
        $postType = sanitize_key((string) ($context['post_type'] ?? ''));

        if ($pageId <= 0 || $postType === '') {
            return false;
        }

        // Taxonomy/archive pseudo-types are never singular object views.
        if (self::isArchiveContext($context)) {
            return false;
        }

        // Registered post types must match a real published object (term IDs can collide with post IDs).
        if (!post_type_exists($postType)) {
            return false;
        }

        $post = get_post($pageId);
        if (!$post instanceof \WP_Post || $post->post_status !== 'publish') {
            return false;
        }

        return sanitize_key($post->post_type) === $postType;
    }

    /**
     * Taxonomy archive pseudo post types that qualify for smart targeting.
     *
     * @var string[]
     */
    private const SMART_TARGETING_TAXONOMY_ARCHIVE_POST_TYPES = [
        'category',
        'tag',
        'product_category',
        'product_tag',
    ];

    /**
     * Singular post types excluded from smart targeting by default.
     *
     * @var string[]
     */
    private const DEFAULT_SMART_TARGETING_EXCLUDED_SINGULAR_POST_TYPES = [
        'page',
    ];

    /**
     * Resolve singular post types that should bypass smart targeting.
     *
     * @return string[]
     * @since 2.3.7
     */
    public static function getSmartTargetingExcludedSingularPostTypes(): array
    {
        // Allow extensions to adjust excluded singular post types.
        $postTypes = apply_filters(
            FilterHooks::ONPAGE_SMART_TARGETING_EXCLUDED_SINGULAR_POST_TYPES,
            self::DEFAULT_SMART_TARGETING_EXCLUDED_SINGULAR_POST_TYPES
        );

        if (!is_array($postTypes)) {
            return self::DEFAULT_SMART_TARGETING_EXCLUDED_SINGULAR_POST_TYPES;
        }

        return array_values(array_unique(array_filter(array_map('sanitize_key', $postTypes))));
    }

    /**
     * Determine whether a singular post type slug is excluded from smart targeting.
     *
     * @param string $postType Registered post type slug.
     * @return bool
     * @since 2.3.7
     */
    public static function isExcludedSmartTargetingSingularPostType(string $postType): bool
    {
        $postType = sanitize_key($postType);

        return $postType !== ''
            && in_array($postType, self::getSmartTargetingExcludedSingularPostTypes(), true);
    }

    /**
     * Map WooCommerce system routes to their underlying WordPress Page post objects.
     *
     * The shop page renders as a product archive in the main query, but display rules
     * that target the Page post type must evaluate against the assigned shop page ID.
     *
     * @param array<string, mixed> $context Visitor page context.
     * @return array<string, mixed>
     * @since 2.3.10
     */
    public static function normalizeWooCommerceSystemPages(array $context): array
    {
        if (!function_exists('wc_get_page_id')) {
            return $context;
        }

        $context = self::attachSmartTargetingViewFlags($context);

        $postType = sanitize_key((string) ($context['post_type'] ?? ''));

        // Product taxonomy archives are not WooCommerce system pages.
        if (!empty($context['archive_taxonomy'])
            || in_array($postType, ['product_category', 'product_tag'], true)
        ) {
            return $context;
        }

        $systemPages = [
            'is_shop_page'     => 'shop',
            'is_cart_page'     => 'cart',
            'is_checkout_page' => 'checkout',
            'is_account_page'  => 'myaccount',
        ];

        foreach ($systemPages as $flag => $wcPageKey) {
            if (empty($context[$flag])) {
                continue;
            }

            $wcPageId = absint(wc_get_page_id($wcPageKey));
            if ($wcPageId <= 0) {
                continue;
            }

            $context['page_id']   = $wcPageId;
            $context['post_type'] = 'page';

            return $context;
        }

        return $context;
    }

    /**
     * Build WooCommerce system page ID map for frontend display rule evaluation.
     *
     * @return array<string, int>
     * @since 2.3.10
     */
    public static function getWooCommerceSystemPageIds(): array
    {
        if (!function_exists('wc_get_page_id')) {
            return [];
        }

        return [
            'shop'      => absint(wc_get_page_id('shop')),
            'cart'      => absint(wc_get_page_id('cart')),
            'checkout'  => absint(wc_get_page_id('checkout')),
            'myaccount' => absint(wc_get_page_id('myaccount')),
        ];
    }

    /**
     * Attach smart targeting view flags used to decide when contextual narrowing applies.
     *
     * @param array<string, mixed> $context Visitor page context.
     * @return array<string, mixed>
     * @since 2.3.7
     */
    public static function attachSmartTargetingViewFlags(array $context): array
    {
        // Prefer WordPress runtime conditionals only when the client did not already send flags.
        // REST API requests carry visitor flags from the browser and must not be overwritten by is_singular() here.
        if (!isset($context['is_front_page']) && function_exists('is_front_page')) {
            $context['is_front_page'] = is_front_page();
        }
        if (!isset($context['is_posts_home']) && function_exists('is_home')) {
            $context['is_posts_home'] = is_home() && !(function_exists('is_front_page') && is_front_page());
        }
        if (!isset($context['is_shop_page']) && function_exists('is_shop')) {
            $context['is_shop_page'] = is_shop();
        }
        if (!isset($context['is_cart_page']) && function_exists('is_cart')) {
            $context['is_cart_page'] = is_cart();
        }
        if (!isset($context['is_checkout_page']) && function_exists('is_checkout')) {
            $context['is_checkout_page'] = is_checkout();
        }
        if (!isset($context['is_account_page']) && function_exists('is_account_page')) {
            $context['is_account_page'] = is_account_page();
        }

        // Infer flags from the visitor URL when API requests lack runtime conditionals.
        $urlPath = self::normalizeContextUrlPath((string) ($context['url'] ?? ''));

        if ($urlPath !== '') {
            if (!isset($context['is_front_page'])) {
                $context['is_front_page'] = self::isHomeUrlPath($urlPath);
            }

            if (!isset($context['is_posts_home'])) {
                $context['is_posts_home'] = self::isPostsPageUrlPath($urlPath);
            }

            if (!isset($context['is_shop_page']) && function_exists('wc_get_page_id')) {
                $context['is_shop_page'] = self::isWooCommercePageUrlPath($urlPath, 'shop');
            }

            if (!isset($context['is_cart_page']) && function_exists('wc_get_page_id')) {
                $context['is_cart_page'] = self::isWooCommercePageUrlPath($urlPath, 'cart');
            }

            if (!isset($context['is_checkout_page']) && function_exists('wc_get_page_id')) {
                $context['is_checkout_page'] = self::isWooCommercePageUrlPath($urlPath, 'checkout');
            }

            if (!isset($context['is_account_page']) && function_exists('wc_get_page_id')) {
                $context['is_account_page'] = self::isWooCommercePageUrlPath($urlPath, 'myaccount');
            }
        }

        if (!isset($context['is_singular_query'])) {
            // Prefer resolved page_id/post_type context (REST/AJAX) over is_singular(), which is false off the main query.
            if (self::isSingularContext($context)) {
                $context['is_singular_query'] = !self::isExcludedSmartTargetingSingularPostType((string) ($context['post_type'] ?? ''))
                    && empty($context['is_front_page'])
                    && empty($context['is_posts_home'])
                    && empty($context['is_shop_page'])
                    && empty($context['is_cart_page'])
                    && empty($context['is_checkout_page'])
                    && empty($context['is_account_page']);
            } elseif (function_exists('is_singular')) {
                $context['is_singular_query'] = is_singular()
                    && !(function_exists('is_page') && is_page())
                    && empty($context['is_front_page'])
                    && empty($context['is_posts_home'])
                    && empty($context['is_shop_page'])
                    && empty($context['is_cart_page'])
                    && empty($context['is_checkout_page'])
                    && empty($context['is_account_page']);
            } else {
                $context['is_singular_query'] = false;
            }
        }

        return $context;
    }

    /**
     * Determine whether smart targeting should narrow content on the current page.
     *
     * Applies only on taxonomy archive pages and singular post/product/CPT queries.
     * The core WordPress `page` post type is excluded. All other non-target pages use content source filters only.
     *
     * @param array<string, mixed> $context Visitor page context.
     * @return bool
     * @since 2.3.7
     */
    public static function isSmartTargetingApplicableContext(array $context): bool
    {
        $context = self::attachSmartTargetingViewFlags($context);

        if (self::shouldIgnoreSmartTargetingForPage($context)) {
            return false;
        }

        if (self::isTaxonomyArchiveSmartTargetingContext($context)) {
            return true;
        }

        return self::isSingularSmartTargetingContext($context);
    }

    /**
     * Determine whether the current page should bypass smart targeting entirely.
     *
     * @param array<string, mixed> $context Visitor page context.
     * @return bool
     * @since 2.3.7
     */
    public static function shouldIgnoreSmartTargetingForPage(array $context): bool
    {
        foreach (['is_front_page', 'is_posts_home', 'is_shop_page', 'is_cart_page', 'is_checkout_page', 'is_account_page'] as $flag) {
            if (!empty($context[$flag])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the context represents a taxonomy archive smart targeting view.
     *
     * @param array<string, mixed> $context Visitor page context.
     * @return bool
     * @since 2.3.7
     */
    public static function isTaxonomyArchiveSmartTargetingContext(array $context): bool
    {
        $archiveTaxonomy = sanitize_key((string) ($context['archive_taxonomy'] ?? ''));
        if ($archiveTaxonomy !== '' && taxonomy_exists($archiveTaxonomy) && absint($context['page_id'] ?? 0) > 0) {
            return true;
        }

        $postType = sanitize_key((string) ($context['post_type'] ?? ''));

        return in_array($postType, self::SMART_TARGETING_TAXONOMY_ARCHIVE_POST_TYPES, true)
            && absint($context['page_id'] ?? 0) > 0;
    }

    /**
     * Determine whether the context represents a singular post/product/CPT query.
     *
     * @param array<string, mixed> $context Visitor page context.
     * @return bool
     * @since 2.3.7
     */
    public static function isSingularSmartTargetingContext(array $context): bool
    {
        if (empty($context['is_singular_query'])) {
            return false;
        }

        $postType = sanitize_key((string) ($context['post_type'] ?? ''));
        if (self::isExcludedSmartTargetingSingularPostType($postType)) {
            return false;
        }

        return self::isSingularContext($context);
    }

    /**
     * Normalize a URL path for comparisons against site routes.
     *
     * @param string $url Absolute or relative URL.
     * @return string
     * @since 2.3.7
     */
    public static function normalizeContextUrlPath(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $path = wp_parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $url;
        }

        $path = '/' . ltrim(untrailingslashit($path), '/');

        return $path === '/' ? '/' : untrailingslashit($path);
    }

    /**
     * Check whether a URL path matches the site home/front route.
     *
     * @param string $urlPath Normalized URL path.
     * @return bool
     * @since 2.3.7
     */
    private static function isHomeUrlPath(string $urlPath): bool
    {
        $homePath = self::normalizeContextUrlPath((string) (wp_parse_url(home_url('/'), PHP_URL_PATH) ?: '/'));

        return $urlPath === $homePath;
    }

    /**
     * Check whether a URL path matches the posts page route.
     *
     * @param string $urlPath Normalized URL path.
     * @return bool
     * @since 2.3.7
     */
    private static function isPostsPageUrlPath(string $urlPath): bool
    {
        $postsPageId = absint(get_option('page_for_posts'));
        if ($postsPageId <= 0) {
            return false;
        }

        $postsPath = self::normalizeContextUrlPath((string) (wp_parse_url(get_permalink($postsPageId), PHP_URL_PATH) ?: ''));

        return $postsPath !== '' && $urlPath === $postsPath;
    }

    /**
     * Check whether a URL path matches a WooCommerce system page route.
     *
     * @param string $urlPath Normalized URL path.
     * @param string $page    WooCommerce page key.
     * @return bool
     * @since 2.3.7
     */
    private static function isWooCommercePageUrlPath(string $urlPath, string $page): bool
    {
        if (!function_exists('wc_get_page_id')) {
            return false;
        }

        $pageId = absint(wc_get_page_id($page));
        if ($pageId <= 0) {
            return false;
        }

        $pagePath = self::normalizeContextUrlPath((string) (wp_parse_url(get_permalink($pageId), PHP_URL_PATH) ?: ''));

        return $pagePath !== '' && ($urlPath === $pagePath || strpos($urlPath, $pagePath . '/') === 0);
    }

    /**
     * Read smart targeting category depth from content source settings.
     *
     * @param array<string, mixed> $contentSourceSettings Content source settings.
     * @return int
     * @since 2.3.7
     */
    public static function getSmartTargetingCategoryLevel(array $contentSourceSettings): int
    {
        // Default to level 2 when the setting is missing.
        $level = (int) ($contentSourceSettings['smart_targeting_category_level'] ?? 2);

        // Clamp between 0 (current query only) and 10.
        return max(0, min(10, $level));
    }

    /**
     * Determine whether retrigger should widen smart targeting to the contextual pool.
     *
     * @param array<string, mixed> $context               Visitor page context.
     * @param array<string, mixed> $contentSourceSettings Optional notification content source settings.
     * @return bool
     * @since 2.3.7 Level 0 keeps retrigger on the current singular query only (no taxonomy widening).
     */
    public static function shouldUseContextPoolForRetrigger(array $context, array $contentSourceSettings = []): bool
    {
        // Only eligible singular queries can widen beyond the current queried object.
        $context = self::attachSmartTargetingViewFlags($context);
        if (!self::isSingularSmartTargetingContext($context)) {
            return false;
        }

        // Level 0 restricts smart targeting to the current query; skip contextual retrigger pools.
        if (!empty($contentSourceSettings['smart_targeting_enabled'])) {
            if (self::getSmartTargetingCategoryLevel($contentSourceSettings) <= 0) {
                return false;
            }
        }

        return true;
    }
}
