<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Settings;

use Notifal\Domain\Orders\OrderFetcherInterface;
use Notifal\Domain\Products\DTO\ProductDTO;
use Notifal\Domain\Products\ProductFetcherInterface;
use Notifal\Domain\Users\UserFetcherInterface;
use Notifal\Infrastructure\WordPress\Services\PostFetcher;
use Notifal\Infrastructure\WordPress\Services\PageFetcher;
use Notifal\Infrastructure\WordPress\Services\CustomPostTypeFetcher;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Traits\SettingsServiceTrait;
use Notifal\Modules\OnPageNotification\Application\Support\ContentSourceRequestContext;
use Notifal\Modules\OnPageNotification\Application\Support\PageContextHelper;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class ContentSourceService
 *
 * Handles content source settings and applies filters to data fetching.
 * Uses pool-based caching for performance and consistency.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ContentSourceService
{
    use SettingsServiceTrait;

    /**
     * Default interval (seconds) between live sale re-validations of cached product pools.
     *
     * @var int
     * @since 2.0.0
     */
    private const SALE_REVALIDATION_INTERVAL_DEFAULT = 5 * MINUTE_IN_SECONDS;

    /**
     * @var OrderFetcherInterface
     */
    private OrderFetcherInterface $orderFetcher;

    /**
     * @var ProductFetcherInterface
     */
    private ProductFetcherInterface $productFetcher;

    /**
     * @var UserFetcherInterface
     */
    private UserFetcherInterface $userFetcher;

    /**
     * @var PostFetcher
     */
    private PostFetcher $postFetcher;

    /**
     * @var PageFetcher
     */
    private PageFetcher $pageFetcher;

    /**
     * @var CustomPostTypeFetcher
     */
    private CustomPostTypeFetcher $customPostTypeFetcher;

    /**
     * @var ContentSourceFilterBuilder
     */
    private ContentSourceFilterBuilder $filterBuilder;

    /**
     * ContentSourceService constructor.
     *
     * @param OrderFetcherInterface $orderFetcher
     * @param ProductFetcherInterface $productFetcher
     * @param UserFetcherInterface $userFetcher
     * @param PostFetcher $postFetcher
     * @param PageFetcher $pageFetcher
     * @param CustomPostTypeFetcher $customPostTypeFetcher
     * @param ContentSourceFilterBuilder $filterBuilder
     * @since 2.0.0
     */
    public function __construct(
        OrderFetcherInterface $orderFetcher,
        ProductFetcherInterface $productFetcher,
        UserFetcherInterface $userFetcher,
        PostFetcher $postFetcher,
        PageFetcher $pageFetcher,
        CustomPostTypeFetcher $customPostTypeFetcher,
        ContentSourceFilterBuilder $filterBuilder
    ) {
        $this->orderFetcher = $orderFetcher;
        $this->productFetcher = $productFetcher;
        $this->userFetcher = $userFetcher;
        $this->postFetcher = $postFetcher;
        $this->pageFetcher = $pageFetcher;
        $this->customPostTypeFetcher = $customPostTypeFetcher;
        $this->filterBuilder = $filterBuilder;
    }

    /**
     * Get a random item from cached pool or fetch new pool.
     *
     * Common caching logic used by all getRandom* methods to reduce duplication.
     *
     * @param string $cacheKey Cache key for the pool
     * @param string $cacheGroup Cache group name
     * @param callable $fetcher Function to fetch items when cache is empty
     * @return mixed|null Random item or null if no items found
     * @since 2.0.0
     */
    private function getRandomFromPool(string $cacheKey, string $cacheGroup, callable $fetcher)
    {
        // Check if we have a cached pool
        $cachedPool = wp_cache_get($cacheKey, $cacheGroup);

        if ($cachedPool !== false) {
            // Use cached pool
            if (!empty($cachedPool)) {
                return $cachedPool[array_rand($cachedPool)];
            }
            return null; // Empty pool
        }

        // Fetch new pool
        $pool = $fetcher();

        if (empty($pool)) {
            // Cache empty pool
            wp_cache_set($cacheKey, [], $cacheGroup, HOUR_IN_SECONDS);
            return null;
        }

        // Cache the pool for future use
        wp_cache_set($cacheKey, $pool, $cacheGroup, HOUR_IN_SECONDS);

        // Return a random item from the pool
        return $pool[array_rand($pool)];
    }

    /**
     * Get a random order with applied filters from content source settings.
     * Uses pool-based caching for performance and consistency.
     *
     * @param array $contentSourceSettings Content source settings
     * @return mixed|null Order data or null if no order found
     * @since 2.0.0
     * @since 2.3.5 Updated to persist shown order sources per visitor session.
     */
    public function getRandomOrder(array $contentSourceSettings = [])
    {
        $pool = $this->getOrderPool($contentSourceSettings);

        if (empty($pool)) {
            return null;
        }

        $order = $pool[ array_rand( $pool ) ];
        $this->rememberShownSource('order', $order, $contentSourceSettings);

        return $order;
    }

    /**
     * Get the total number of orders matching content source order restrictions.
     *
     * Respects all configured order filters (date range, status, products,
     * custom meta, custom filter, and multiple AND/OR conditions).
     *
     * @param array $contentSourceSettings Content source settings.
     * @return int Total matching order count.
     * @since 2.3.7
     */
    public function getOrderCount(array $contentSourceSettings = []): int
    {
        return $this->resolveCachedEntityCount(
            'order',
            $contentSourceSettings,
            function () use ($contentSourceSettings) {
                $filters = $this->filterBuilder->buildOrderFilters($contentSourceSettings);

                return $this->orderFetcher->count($filters);
            },
            FilterHooks::ONPAGE_ORDER_COUNT,
            $this->filterBuilder->buildOrderFilters($contentSourceSettings)
        );
    }

    /**
     * Get the total number of products matching content source product restrictions.
     *
     * @param array $contentSourceSettings Content source settings.
     * @return int Total matching product count.
     * @since 2.3.7
     */
    public function getProductCount(array $contentSourceSettings = []): int
    {
        return $this->resolveCachedEntityCount(
            'product',
            $contentSourceSettings,
            function () use ($contentSourceSettings) {
                $filters = $this->filterBuilder->buildProductFilters($contentSourceSettings);

                return $this->productFetcher->count($filters);
            },
            FilterHooks::ONPAGE_PRODUCT_COUNT,
            $this->filterBuilder->buildProductFilters($contentSourceSettings)
        );
    }

    /**
     * Get the total number of posts matching content source post restrictions.
     *
     * @param array $contentSourceSettings Content source settings.
     * @return int Total matching post count.
     * @since 2.3.7
     */
    public function getPostCount(array $contentSourceSettings = []): int
    {
        return $this->resolveCachedEntityCount(
            'post',
            $contentSourceSettings,
            function () use ($contentSourceSettings) {
                $filters = $this->filterBuilder->buildPostFilters($contentSourceSettings);

                return $this->postFetcher->count($filters);
            },
            FilterHooks::ONPAGE_POST_COUNT,
            $this->filterBuilder->buildPostFilters($contentSourceSettings)
        );
    }

    /**
     * Get the total number of pages matching content source page restrictions.
     *
     * @param array $contentSourceSettings Content source settings.
     * @return int Total matching page count.
     * @since 2.3.7
     */
    public function getPageCount(array $contentSourceSettings = []): int
    {
        return $this->resolveCachedEntityCount(
            'page',
            $contentSourceSettings,
            function () use ($contentSourceSettings) {
                $filters = $this->filterBuilder->buildPageFilters($contentSourceSettings);

                return $this->pageFetcher->count($filters);
            },
            FilterHooks::ONPAGE_PAGE_COUNT,
            $this->filterBuilder->buildPageFilters($contentSourceSettings)
        );
    }

    /**
     * Get the total number of comments matching content source comment restrictions.
     *
     * Comment counting is delegated to Notifal Pro through integration hooks.
     *
     * @param array $contentSourceSettings Content source settings.
     * @return int Total matching comment count.
     * @since 2.3.7
     */
    public function getCommentCount(array $contentSourceSettings = []): int
    {
        // Build comment filters once so Pro receives smart targeting constraints too.
        $builtFilters = $this->filterBuilder->buildCommentFilters($contentSourceSettings);

        return $this->resolveCachedEntityCount(
            'comment',
            $contentSourceSettings,
            function () use ($contentSourceSettings, $builtFilters) {
                $count = apply_filters('notifal_pro_get_comment_count', null, $contentSourceSettings, $builtFilters);

                return $count === null ? 0 : (int) $count;
            },
            FilterHooks::ONPAGE_COMMENT_COUNT,
            $builtFilters
        );
    }

    /**
     * Get the total number of custom post type items matching content source restrictions.
     *
     * @param string $postType Custom post type slug.
     * @param array  $contentSourceSettings Content source settings.
     * @return int Total matching item count.
     * @since 2.3.7
     */
    public function getCustomPostTypeCount(string $postType, array $contentSourceSettings = []): int
    {
        $postType = sanitize_key($postType);
        if ($postType === '') {
            return 0;
        }

        return $this->resolveCachedEntityCount(
            'custom_posttype_' . $postType,
            $contentSourceSettings,
            function () use ($postType, $contentSourceSettings) {
                $filters = $this->filterBuilder->buildCustomPostTypeFilters($postType, $contentSourceSettings);

                return $this->customPostTypeFetcher->count($postType, $filters);
            },
            FilterHooks::ONPAGE_CUSTOM_POSTTYPE_COUNT,
            $this->filterBuilder->buildCustomPostTypeFilters($postType, $contentSourceSettings),
            $postType
        );
    }

    /**
     * Resolve, filter, and cache a content-source entity count.
     *
     * @param string   $scopeKey Cache scope key (order, product, post, etc.).
     * @param array    $contentSourceSettings Content source settings.
     * @param callable $countResolver Callable that returns the raw count.
     * @param string   $filterHook Filter hook for modifying the count.
     * @param array    $builtFilters Built filters for the filter hook context.
     * @param string|null $postType Optional custom post type slug.
     * @return int Cached entity count.
     * @since 2.3.7
     */
    private function resolveCachedEntityCount(
        string $scopeKey,
        array $contentSourceSettings,
        callable $countResolver,
        string $filterHook,
        array $builtFilters,
        ?string $postType = null
    ): int {
        // Build a stable cache key from scope and settings payload.
        $cacheKey = 'notifal_' . $scopeKey . '_count_' . md5(wp_json_encode($contentSourceSettings));
        $cacheGroup = 'notifal_content_counts';

        // Return cached count when available.
        $cachedCount = wp_cache_get($cacheKey, $cacheGroup);
        if ($cachedCount !== false) {
            return (int) $cachedCount;
        }

        // Resolve the raw count from the entity fetcher.
        $count = (int) $countResolver();

        // Allow developers to adjust the computed count.
        if ($postType !== null) {
            $count = (int) apply_filters($filterHook, $count, $contentSourceSettings, $builtFilters, $postType);
        } else {
            $count = (int) apply_filters($filterHook, $count, $contentSourceSettings, $builtFilters);
        }

        // Cache the count to avoid repeated heavy queries.
        $ttl = (int) apply_filters(
            FilterHooks::ONPAGE_CONTENT_SOURCE_COUNT_CACHE_TIMEOUT,
            HOUR_IN_SECONDS,
            $contentSourceSettings,
            $scopeKey
        );
        wp_cache_set($cacheKey, $count, $cacheGroup, max(60, $ttl));

        return $count;
    }

    /**
     * Get order pool for deterministic selection and retrigger variants.
     *
     * @param array $contentSourceSettings Content source settings.
     * @return array<int, mixed> Order DTO pool (may be empty).
     * @since 2.3.5
     */
    public function getOrderPool(array $contentSourceSettings = []): array
    {
        $cacheKey = $this->buildOrderPoolCacheKey($contentSourceSettings);
        $poolSize = (int) apply_filters(FilterHooks::ONPAGE_ORDER_POOL_SIZE, 18, $contentSourceSettings);

        return $this->loadOrBuildPool(
            $cacheKey,
            'notifal_order_pools',
            function () use ($contentSourceSettings, $poolSize) {
                $filters = $this->filterBuilder->buildOrderFilters($contentSourceSettings);

                return $this->orderFetcher->getRandomPool($poolSize, $filters);
            },
            $contentSourceSettings,
            'order'
        );
    }

    /**
     * Get a random product with applied filters from content source settings.
     * Uses pool-based caching for performance and consistency.
     *
     * @param array $contentSourceSettings Content source settings
     * @return mixed|null Product data or null if no product found
     * @since 2.0.0
     * @since 2.3.5 Updated to persist shown product sources per visitor session.
     */
    public function getRandomProduct(array $contentSourceSettings = [])
    {
        $pool = $this->getOrRefreshProductPool($contentSourceSettings, 20);

        if (empty($pool)) {
            return null;
        }

        $product = $pool[array_rand($pool)];
        $this->rememberShownSource('product', $product, $contentSourceSettings);

        return $product;
    }

    /**
     * Get product pool cache key for given content source settings.
     * Used by FrontendTagContextBuilder to ensure cache consistency.
     *
     * @param array $contentSourceSettings Content source settings
     * @return string Cache key for product pool
     * @since 2.0.0
     */
    public function getProductPoolCacheKey(array $contentSourceSettings): string
    {
        return $this->buildProductPoolCacheKey($contentSourceSettings);
    }

    /**
     * Get product pool for deterministic selection.
     * Used by FrontendTagContextBuilder for consistent product selection across tag resolutions.
     *
     * @param array $contentSourceSettings Content source settings
     * @return array Product pool array or empty array if no products found
     * @since 2.0.0
     */
    public function getProductPool(array $contentSourceSettings): array
    {
        $poolSize = (int) apply_filters(FilterHooks::ONPAGE_PRODUCT_POOL_SIZE, 18, $contentSourceSettings);

        return $this->getOrRefreshProductPool($contentSourceSettings, $poolSize);
    }

    /**
     * Load the product pool from object cache or build it.
     *
     * For "sale-only" pools the cached entries are periodically re-checked against
     * {@see \WC_Product::is_on_sale()} (default every 5 minutes) so products whose
     * sale ended are dropped without re-running the full DB query.
     *
     * @param array $contentSourceSettings Notification content source settings.
     * @param int   $poolSizeForFetch Number of products to request from {@see ProductFetcherInterface::getRandomPool()}.
     * @return ProductDTO[] Cached or freshly built pool (may be empty).
     * @since 2.0.0
     */
    private function getOrRefreshProductPool(array $contentSourceSettings, int $poolSizeForFetch): array
    {
        $cacheKey = $this->buildProductPoolCacheKey($contentSourceSettings);
        $cacheKey = $this->applyPoolCacheKeyFilter($cacheKey, 'product', $contentSourceSettings);
        $ttl = $this->getProductPoolCacheTtl($contentSourceSettings);
        $bypassEntityCache = $this->shouldBypassEntityPoolCache()
            || CartProductPoolResolver::settingsContainCartFilter($contentSourceSettings);

        if (!$bypassEntityCache) {
            $cached = wp_cache_get($cacheKey, 'notifal_product_pools');

            if (is_array($cached)) {
                $pool = $cached['pool'] ?? $cached;
                $validatedAt = $cached['validated_at'] ?? 0;
                $filters = $this->filterBuilder->buildProductFilters($contentSourceSettings);

                return $this->refreshSaleProductPoolIfDue(
                    $pool,
                    $validatedAt,
                    $filters,
                    $contentSourceSettings,
                    $cacheKey,
                    $ttl,
                    $poolSizeForFetch
                );
            }
        }

        // Build product filters inside the fetcher so smart targeting phase injection is accurate.
        $pool = $this->resolvePoolOrFetch(
            $cacheKey,
            'notifal_product_pools',
            function () use ($poolSizeForFetch, $contentSourceSettings) {
                $filters = $this->filterBuilder->buildProductFilters($contentSourceSettings);

                return $this->productFetcher->getRandomPool($poolSizeForFetch, $filters);
            },
            $contentSourceSettings,
            'product'
        );

        if (!$bypassEntityCache) {
            $this->storeProductPool($cacheKey, $pool, $ttl);
        }

        return $pool;
    }

    /**
     * Store a product pool in object cache with a validation timestamp.
     *
     * @param string       $cacheKey Object cache key.
     * @param ProductDTO[] $pool Product DTOs.
     * @param int          $ttl Cache TTL in seconds.
     * @return void
     * @since 2.0.0
     */
    private function storeProductPool(string $cacheKey, array $pool, int $ttl): void
    {
        wp_cache_set(
            $cacheKey,
            ['pool' => $pool, 'validated_at' => time()],
            'notifal_product_pools',
            $ttl
        );
    }

    /**
     * Re-validate a "sale-only" product pool if enough time has passed since the last check.
     *
     * Throttled to once per {@see SALE_REVALIDATION_INTERVAL_DEFAULT} (filterable) so that
     * concurrent visitors do not each run the full {@see \WC_Product::is_on_sale()} pass.
     *
     * @param ProductDTO[] $pool Cached pool entries.
     * @param int          $validatedAt Unix timestamp of last validation (0 = never).
     * @param array        $filters Product filters for this pool.
     * @param array        $contentSourceSettings Settings used for pool size and TTL.
     * @param string       $cacheKey Object cache key.
     * @param int          $ttl Expiration in seconds.
     * @param int          $poolSizeForFetch Pool size when rebuilding.
     * @return ProductDTO[]
     * @since 2.0.0
     */
    private function refreshSaleProductPoolIfDue(
        array $pool,
        int $validatedAt,
        array $filters,
        array $contentSourceSettings,
        string $cacheKey,
        int $ttl,
        int $poolSizeForFetch
    ): array {
        if (!$this->productFetcher->requiresLiveSaleValidation($filters)) {
            return $pool;
        }

        $interval = $this->getSaleRevalidationInterval($contentSourceSettings);

        if ((time() - $validatedAt) < $interval) {
            return $pool;
        }

        $filtered = $this->productFetcher->filterProductPoolToLiveSaleOnly($pool);

        if ($this->productPoolIdsSignature($filtered) === $this->productPoolIdsSignature($pool)) {
            $this->storeProductPool($cacheKey, $pool, $ttl);

            return $pool;
        }

        if (!empty($filtered)) {
            $this->storeProductPool($cacheKey, $filtered, $ttl);

            return $filtered;
        }

        wp_cache_delete($cacheKey, 'notifal_product_pools');

        $rebuilt = $this->productFetcher->getRandomPool($poolSizeForFetch, $filters);
        $this->storeProductPool($cacheKey, $rebuilt, $ttl);

        return $rebuilt;
    }

    /**
     * Stable signature of pool product IDs for comparing filtered vs cached pools.
     *
     * @param ProductDTO[]|array $pool Product pool.
     * @return string Sorted, comma-separated IDs or empty string.
     * @since 2.0.0
     */
    private function productPoolIdsSignature(array $pool): string
    {
        $ids = [];
        foreach ($pool as $dto) {
            if ($dto instanceof ProductDTO) {
                $ids[] = $dto->getId();
            }
        }
        sort($ids);

        return implode(',', $ids);
    }

    /**
     * How often (seconds) sale-only pools should be re-validated against WooCommerce.
     *
     * @param array $contentSourceSettings Content source settings.
     * @return int Seconds (minimum 60).
     * @since 2.0.0
     */
    private function getSaleRevalidationInterval(array $contentSourceSettings): int
    {
        $interval = (int) apply_filters(
            FilterHooks::ONPAGE_PRODUCT_POOL_SALE_REVALIDATION_INTERVAL,
            self::SALE_REVALIDATION_INTERVAL_DEFAULT,
            $contentSourceSettings
        );

        return max(60, $interval);
    }

    /**
     * TTL for product pool object-cache entries (filterable).
     *
     * @param array $contentSourceSettings Content source settings.
     * @return int Seconds (minimum 1).
     * @since 2.0.0
     */
    private function getProductPoolCacheTtl(array $contentSourceSettings): int
    {
        $ttl = (int) apply_filters(
            FilterHooks::ONPAGE_PRODUCT_POOL_TIMEOUT,
            HOUR_IN_SECONDS,
            $contentSourceSettings
        );

        return max(1, $ttl);
    }

    /**
     * Get a random user with applied filters from content source settings.
     * Uses pool-based caching for performance and consistency.
     *
     * @param array $contentSourceSettings Content source settings
     * @return mixed|null User data or null if no user found
     * @since 2.0.0
     */
    public function getRandomUser(array $contentSourceSettings = [])
    {
        $cacheKey = $this->buildUserPoolCacheKey($contentSourceSettings);

        return $this->getRandomFromPool($cacheKey, 'notifal_user_pools', function() use ($contentSourceSettings) {
            $filters = $this->filterBuilder->buildUserFilters($contentSourceSettings);
            return $this->userFetcher->getRandomPool(20, $filters);
        });
    }

    /**
     * Get a random post with applied filters from content source settings.
     * Uses pool-based caching for performance and consistency.
     *
     * @param array $contentSourceSettings Content source settings
     * @return \WP_Post|null Post data or null if no post found
     * @since 2.0.0
     * @since 2.3.5 Updated to persist shown post sources per visitor session.
     */
    public function getRandomPost(array $contentSourceSettings = [])
    {
        $pool = $this->getPostPool($contentSourceSettings);

        if (empty($pool)) {
            return null;
        }

        $post = $pool[ array_rand( $pool ) ];
        $this->rememberShownSource('post', $post, $contentSourceSettings);

        return $post;
    }

    /**
     * Get post pool for deterministic selection and retrigger variants.
     *
     * @param array $contentSourceSettings Content source settings.
     * @return array<int, \WP_Post> Post pool (may be empty).
     * @since 2.3.5
     */
    public function getPostPool(array $contentSourceSettings = []): array
    {
        $cacheKey = $this->buildPostPoolCacheKey($contentSourceSettings);

        return $this->loadOrBuildPool(
            $cacheKey,
            'notifal_post_pools',
            function () use ($contentSourceSettings) {
                $filters = $this->filterBuilder->buildPostFilters($contentSourceSettings);

                return $this->postFetcher->getRandomPool(20, $filters);
            },
            $contentSourceSettings,
            'post'
        );
    }

    /**
     * Get a random page with applied filters from content source settings.
     * Uses pool-based caching for performance and consistency.
     *
     * @param array $contentSourceSettings Content source settings
     * @return \WP_Post|null Page data or null if no page found
     * @since 2.0.0
     * @since 2.3.5 Updated to persist shown page sources per visitor session.
     */
    public function getRandomPage(array $contentSourceSettings = [])
    {
        $pool = $this->getPagePool($contentSourceSettings);

        if (empty($pool)) {
            return null;
        }

        $page = $pool[ array_rand( $pool ) ];
        $this->rememberShownSource('page', $page, $contentSourceSettings);

        return $page;
    }

    /**
     * Get page pool for deterministic selection and retrigger variants.
     *
     * @param array $contentSourceSettings Content source settings.
     * @return array<int, \WP_Post> Page pool (may be empty).
     * @since 2.3.5
     */
    public function getPagePool(array $contentSourceSettings = []): array
    {
        $cacheKey = $this->buildPagePoolCacheKey($contentSourceSettings);

        return $this->loadOrBuildPool(
            $cacheKey,
            'notifal_page_pools',
            function () use ($contentSourceSettings) {
                $filters = $this->filterBuilder->buildPageFilters($contentSourceSettings);

                return $this->pageFetcher->getRandomPool(20, $filters);
            },
            $contentSourceSettings,
            'page'
        );
    }

    /**
     * Get comment pool for deterministic selection and smart targeting phases.
     *
     * @param array $contentSourceSettings Content source settings.
     * @return array<int, \WP_Comment> Comment pool (may be empty).
     * @since 2.3.7
     */
    public function getCommentPool(array $contentSourceSettings = []): array
    {
        // Comment pools are only available when Pro features are allowed.
        if (!$this->isProFeatureAllowed()) {
            return [];
        }

        // Build a settings-scoped cache key before page-context extension.
        $cacheKey = $this->buildCommentPoolCacheKey($contentSourceSettings);

        // Reuse the shared pool loader so smart targeting phases and cache keys apply.
        return $this->loadOrBuildPool(
            $cacheKey,
            'notifal_comment_pools',
            function () use ($contentSourceSettings) {
                // Build filters with smart targeting constraints when applicable.
                $filters = $this->filterBuilder->buildCommentFilters($contentSourceSettings);

                // Delegate the actual query to Notifal Pro.
                $pool = apply_filters('notifal_pro_fetch_comment_pool', [], $filters, $contentSourceSettings);

                return is_array($pool) ? $pool : [];
            },
            $contentSourceSettings,
            'comment'
        );
    }

    /**
     * Get a random comment with applied filters from content source settings.
     * Uses pool-based caching for performance and consistency.
     *
     * @param array $contentSourceSettings Content source settings
     * @return \WP_Comment|null Comment data or null if no comment found or pro not active
     * @since 2.0.0
     * @since 2.3.7 Updated to use smart-targeting-aware comment pools.
     */
    public function getRandomComment(array $contentSourceSettings = [])
    {
        // Resolve the contextual comment pool first.
        $pool = $this->getCommentPool($contentSourceSettings);

        // Return null when no comments match the active scope.
        if (empty($pool)) {
            return null;
        }

        $pageContext = ContentSourceRequestContext::getPageContext();
        $pinnedEntityId = (int) ($pageContext['notifal_pool_entity_id'] ?? 0);

        // Honor a pinned pool entity for first paint / retrigger variant alignment.
        if ($pinnedEntityId > 0) {
            foreach ($pool as $comment) {
                if ($comment instanceof \WP_Comment && (int) $comment->comment_ID === $pinnedEntityId) {
                    $this->rememberShownSource('comment', $comment, $contentSourceSettings);
                    ContentSourceRequestContext::setLastSelectedPoolEntityId($pinnedEntityId);

                    return $comment;
                }
            }
        }

        $pool = $this->excludeSeenSourcesFromPool('comment', $pool, $contentSourceSettings);
        if (empty($pool)) {
            return null;
        }

        // Pick a random comment from the remaining pool.
        $comment = $pool[ array_rand( $pool ) ];
        $this->rememberShownSource('comment', $comment, $contentSourceSettings);

        $entityId = $this->extractEntityId($comment);
        if ($entityId > 0) {
            ContentSourceRequestContext::setLastSelectedPoolEntityId($entityId);
        }

        return $comment;
    }

    /**
     * Get a random custom post type item with applied filters from content source settings.
     * Uses pool-based caching for performance and consistency.
     *
     * @param string $postType The custom post type name
     * @param array $contentSourceSettings Content source settings
     * @return \WP_Post|null Custom post type data or null if no post found
     * @since 2.0.0
     * @since 2.3.5 Updated to persist shown custom post type sources per visitor session.
     */
    public function getRandomCustomPostType(string $postType, array $contentSourceSettings = [])
    {
        $pool = $this->getCustomPostTypePool($postType, $contentSourceSettings);

        if (empty($pool)) {
            return null;
        }

        $item = $pool[ array_rand( $pool ) ];
        $this->rememberShownSource('custom_posttype:' . $postType, $item, $contentSourceSettings);

        return $item;
    }

    /**
     * Get custom post type pool for deterministic selection and retrigger variants.
     *
     * @param string $postType Custom post type slug.
     * @param array  $contentSourceSettings Content source settings.
     * @return array<int, \WP_Post> Pool (may be empty).
     * @since 2.3.5
     */
    public function getCustomPostTypePool(string $postType, array $contentSourceSettings = []): array
    {
        $cacheKey = $this->buildCustomPostTypePoolCacheKey($postType, $contentSourceSettings);

        return $this->loadOrBuildPool(
            $cacheKey,
            'notifal_custom_posttype_pools',
            function () use ($postType, $contentSourceSettings) {
                $filters = $this->filterBuilder->buildCustomPostTypeFilters($postType, $contentSourceSettings);

                return $this->customPostTypeFetcher->getRandomPool($postType, 20, $filters);
            },
            $contentSourceSettings,
            'custom_posttype:' . sanitize_key($postType)
        );
    }

    /**
     * Load a cached content pool or build and store it.
     *
     * @param string   $cacheKey Object cache key.
     * @param string   $cacheGroup Object cache group.
     * @param callable $fetcher Builds the pool when cache is empty.
     * @return array<int, mixed> Pool items (may be empty).
     * @since 2.3.5
     */
    private function loadOrBuildPool(
        string $cacheKey,
        string $cacheGroup,
        callable $fetcher,
        array $contentSourceSettings = [],
        string $entityType = 'generic'
    ): array {
        // Extend cache key when contextual targeting is active (Pro hook).
        $cacheKey = $this->applyPoolCacheKeyFilter($cacheKey, $entityType, $contentSourceSettings);

        // Retrigger variant builds must resolve the forced contextual phase, not reuse first-paint cache.
        $bypassEntityCache = $this->shouldBypassEntityPoolCache();

        if (!$bypassEntityCache) {
            $cachedPool = wp_cache_get($cacheKey, $cacheGroup);

            if (is_array($cachedPool)) {
                return $cachedPool;
            }
        }

        $pool = $this->resolvePoolOrFetch($cacheKey, $cacheGroup, $fetcher, $contentSourceSettings, $entityType);

        if (!is_array($pool)) {
            $pool = [];
        }

        if (!$bypassEntityCache) {
            wp_cache_set($cacheKey, $pool, $cacheGroup, HOUR_IN_SECONDS);
        }

        return $pool;
    }

    /**
     * Resolve a pool via extensibility hook or fall back to the default fetcher.
     *
     * @param string   $cacheKey              Object cache key.
     * @param string   $cacheGroup            Object cache group.
     * @param callable $fetcher               Default pool fetcher callback.
     * @param array    $contentSourceSettings Content source settings.
     * @param string   $entityType            Entity scope key.
     * @return array<int, mixed> Resolved pool items.
     * @since 2.3.7
     */
    private function resolvePoolOrFetch(
        string $cacheKey,
        string $cacheGroup,
        callable $fetcher,
        array $contentSourceSettings,
        string $entityType
    ): array {
        // Allow Pro smart targeting to resolve multi-phase contextual pools.
        $resolved = apply_filters(
            FilterHooks::ONPAGE_CONTENT_SOURCE_RESOLVE_POOL,
            null,
            [
                'entity_type' => $entityType,
                'settings' => $contentSourceSettings,
                'cache_key' => $cacheKey,
                'cache_group' => $cacheGroup,
                'fetcher' => $fetcher,
                'page_context' => ContentSourceRequestContext::getPageContext(),
            ]
        );

        if (is_array($resolved)) {
            return $resolved;
        }

        // Default core loader path.
        $pool = $fetcher();

        return is_array($pool) ? $pool : [];
    }

    /**
     * Apply pool cache key extension filter.
     *
     * @param string $cacheKey              Base cache key.
     * @param string $entityType            Entity scope key.
     * @param array  $contentSourceSettings Content source settings.
     * @return string Extended cache key.
     * @since 2.3.7
     */
    private function applyPoolCacheKeyFilter(string $cacheKey, string $entityType, array $contentSourceSettings): string
    {
        // Pass page context so Pro can scope cache entries per visitor page.
        $pageContext = ContentSourceRequestContext::getPageContext();

        /**
         * Extend content source pool cache keys (e.g. smart targeting page context).
         *
         * @param string $cacheKey    Base cache key.
         * @param string $entityType  Entity scope key.
         * @param array  $settings    Content source settings.
         * @param array  $pageContext Current visitor page context.
         * @since 2.3.7
         */
        return (string) apply_filters(
            FilterHooks::ONPAGE_CONTENT_SOURCE_POOL_CACHE_KEY,
            $cacheKey,
            $entityType,
            $contentSourceSettings,
            $pageContext
        );
    }

    /**
     * Determine whether entity-level pool cache should be skipped for smart targeting retrigger builds.
     *
     * @return bool
     * @since 2.3.7
     */
    private function shouldBypassEntityPoolCache(): bool
    {
        $pageContext = ContentSourceRequestContext::getPageContext();

        if (!is_array($pageContext)) {
            return false;
        }

        return sanitize_key((string) ($pageContext['smart_targeting_forced_phase'] ?? '')) !== '';
    }


    /**
     * Check if pro features are allowed (user has active pro license).
     * Uses secure hooks that can only be provided by the legitimate pro plugin.
     *
     * @return bool True if pro features are allowed
     * @since 2.0.0
     */
    protected function isProFeatureAllowed(): bool
    {
        return $this->checkProFeatureAllowed('notifal_pro_content_source_features');
    }

    /**
     * Get default filter configuration for lite version.
     *
     * @param string $category Filter category
     * @return array Default filter configuration
     * @since 2.0.0
     */
    private function getDefaultFilters(string $category): array
    {
        return [
            'multiple_filters' => false,
            'logic' => 'AND',
            'conditionsUsed' => false,
            'conditions' => []
        ];
    }

    /**
     * Parse content source settings from form data.
     * Supports both legacy and new multiple filters format.
     *
     * @param array $formData Form data
     * @return array Parsed content source settings
     * @since 2.0.0
     * @since 2.3.5 Updated to parse `allow_duplicate_source`.
     */
    public function parseSettings(array $formData): array
    {
        $settings = [];

        // Content source type
        $settings['content_source_type'] = sanitize_text_field($formData['content_source_type'] ?? 'dynamic');
        $settings['allow_duplicate_source'] = !empty($formData['allow_duplicate_source']);

        // Parse new multiple filters format
        $settings['product_filters'] = $this->isProFeatureAllowed() ? $this->filterBuilder->parseMultipleFilters($formData, 'product') : $this->getDefaultFilters('product');
        $settings['order_filters'] = $this->isProFeatureAllowed() ? $this->filterBuilder->parseMultipleFilters($formData, 'order') : $this->getDefaultFilters('order');
        $settings['user_filters'] = $this->isProFeatureAllowed() ? $this->filterBuilder->parseMultipleFilters($formData, 'user') : $this->getDefaultFilters('user');
        $settings['post_filters'] = $this->isProFeatureAllowed() ? $this->filterBuilder->parseMultipleFilters($formData, 'post') : $this->getDefaultFilters('post');
        $settings['page_filters'] = $this->isProFeatureAllowed() ? $this->filterBuilder->parseMultipleFilters($formData, 'page') : $this->getDefaultFilters('page');
        $settings['comment_filters'] = $this->isProFeatureAllowed() ? $this->filterBuilder->parseMultipleFilters($formData, 'comment') : $this->getDefaultFilters('comment');
        $settings['custom_posttype_filters'] = $this->isProFeatureAllowed() ? $this->filterBuilder->parseMultipleFilters($formData, 'custom_posttype') : $this->getDefaultFilters('custom_posttype');

        // Legacy fields
        $settings['product_restriction_type'] = sanitize_text_field($formData['product_restriction_type'] ?? 'all');
        $settings['product_categories'] = array_map('intval', $formData['product_categories'] ?? []);
        $settings['specific_products'] = array_map('intval', $formData['specific_products'] ?? []);
        $settings['product_custom_filter'] = sanitize_text_field($formData['product_custom_filter'] ?? '');

        $settings['order_restriction_type'] = sanitize_text_field($formData['order_restriction_type'] ?? 'status');
        $settings['order_date_range'] = sanitize_text_field($formData['order_date_range'] ?? 'last_7d');
        $settings['order_date_start'] = sanitize_text_field($formData['order_date_start'] ?? '');
        $settings['order_date_end'] = sanitize_text_field($formData['order_date_end'] ?? '');
        $settings['order_statuses'] = array_map('sanitize_text_field', $formData['order_statuses'] ?? ['completed', 'processing']);
        $settings['order_products'] = array_map('intval', $formData['order_products'] ?? []);
        $settings['order_custom_filter'] = sanitize_text_field($formData['order_custom_filter'] ?? '');

        $settings['user_restriction_type'] = sanitize_text_field($formData['user_restriction_type'] ?? 'all');
        $settings['user_roles'] = array_map('sanitize_text_field', $formData['user_roles'] ?? []);
        $settings['specific_users'] = array_map('intval', $formData['specific_users'] ?? []);
        $settings['user_custom_filter'] = sanitize_text_field($formData['user_custom_filter'] ?? '');

        // Custom Post Type specific fields
        $settings['specific_custom_posttypes'] = array_map('intval', $formData['specific_custom_posttypes'] ?? []);
        $settings['custom_posttype_categories'] = array_map('intval', $formData['custom_posttype_categories'] ?? []);

        /**
         * Filter parsed content source settings.
         *
         * @param array $settings The parsed settings
         * @param array $formData The original form data
         * @since 2.0.0
         */
        return apply_filters(FilterHooks::ONPAGE_CONTENT_SOURCE_SETTINGS, $settings, $formData);
    }



    /**
     * Sanitize content source settings.
     *
     * @since 2.0.0
     * @since 2.3.5 Updated to sanitize `allow_duplicate_source`.
     * @param array $settings Raw settings data
     * @return array Sanitized settings
     */
    public function sanitizeSettings(array $settings): array
    {
        $sanitized = [];

        // Content source type
        $sanitized['content_source_type'] = sanitize_text_field($settings['content_source_type'] ?? 'dynamic');
        $sanitized['allow_duplicate_source'] = !empty($settings['allow_duplicate_source']);

        // Sanitize multiple filters
        $sanitized['product_filters'] = $this->filterBuilder->sanitizeMultipleFilters($settings['product_filters'] ?? []);
        $sanitized['order_filters'] = $this->filterBuilder->sanitizeMultipleFilters($settings['order_filters'] ?? []);
        $sanitized['user_filters'] = $this->filterBuilder->sanitizeMultipleFilters($settings['user_filters'] ?? []);
        $sanitized['post_filters'] = $this->filterBuilder->sanitizeMultipleFilters($settings['post_filters'] ?? []);
        $sanitized['page_filters'] = $this->filterBuilder->sanitizeMultipleFilters($settings['page_filters'] ?? []);
        $sanitized['comment_filters'] = $this->filterBuilder->sanitizeMultipleFilters($settings['comment_filters'] ?? []);
        $sanitized['custom_posttype_filters'] = $this->filterBuilder->sanitizeMultipleFilters($settings['custom_posttype_filters'] ?? []);

        // Legacy fields
        $sanitized['product_restriction_type'] = sanitize_text_field($settings['product_restriction_type'] ?? 'all');
        $sanitized['product_categories'] = array_map('intval', $settings['product_categories'] ?? []);
        $sanitized['specific_products'] = array_map('intval', $settings['specific_products'] ?? []);
        $sanitized['product_custom_filter'] = sanitize_text_field($settings['product_custom_filter'] ?? '');

        $sanitized['order_restriction_type'] = sanitize_text_field($settings['order_restriction_type'] ?? 'status');
        $sanitized['order_date_range'] = sanitize_text_field($settings['order_date_range'] ?? 'last_7d');
        $sanitized['order_date_start'] = sanitize_text_field($settings['order_date_start'] ?? '');
        $sanitized['order_date_end'] = sanitize_text_field($settings['order_date_end'] ?? '');
        $sanitized['order_statuses'] = array_map('sanitize_text_field', $settings['order_statuses'] ?? ['completed', 'processing']);
        $sanitized['order_products'] = array_map('intval', $settings['order_products'] ?? []);
        $sanitized['order_custom_filter'] = sanitize_text_field($settings['order_custom_filter'] ?? '');

        $sanitized['user_restriction_type'] = sanitize_text_field($settings['user_restriction_type'] ?? 'all');
        $sanitized['user_roles'] = array_map('sanitize_text_field', $settings['user_roles'] ?? []);
        $sanitized['specific_users'] = array_map('intval', $settings['specific_users'] ?? []);
        $sanitized['user_custom_filter'] = sanitize_text_field($settings['user_custom_filter'] ?? '');

        // Custom Post Type specific fields
        $sanitized['specific_custom_posttypes'] = array_map('intval', $settings['specific_custom_posttypes'] ?? []);
        $sanitized['custom_posttype_categories'] = array_map('intval', $settings['custom_posttype_categories'] ?? []);

        /**
         * Filter sanitized content source settings.
         *
         * @param array $sanitized The sanitized settings
         * @param array $settings The original settings
         * @since 2.0.0
         */
        return apply_filters(FilterHooks::ONPAGE_CONTENT_SOURCE_SANITIZED_SETTINGS, $sanitized, $settings);
    }


    /**
     * Build cache key for order pool based on content source settings.
     * Different filters create different pools to ensure proper targeting.
     *
     * @param array $contentSourceSettings Content source settings
     * @return string Cache key for order pool
     * @since 2.3.5
     */
    private function buildOrderPoolCacheKey(array $contentSourceSettings): string
    {
        return $this->buildContentPoolCacheKey('order', $contentSourceSettings);
    }

    /**
     * Build cache key for product pool based on content source settings.
     * Different filters create different pools to ensure proper targeting.
     *
     * @param array $contentSourceSettings Content source settings
     * @return string Cache key for product pool
     * @since 2.3.5
     */
    private function buildProductPoolCacheKey(array $contentSourceSettings): string
    {
        return $this->buildContentPoolCacheKey('product', $contentSourceSettings);
    }

    /**
     * Build cache key for user pool based on content source settings.
     * Different filters create different pools to ensure proper targeting.
     *
     * @param array $contentSourceSettings Content source settings
     * @return string Cache key for user pool
     * @since 2.3.5
     */
    private function buildUserPoolCacheKey(array $contentSourceSettings): string
    {
        return $this->buildContentPoolCacheKey('user', $contentSourceSettings);
    }

    /**
     * Build cache key for post pool based on content source settings.
     * Different filters create different pools to ensure proper targeting.
     *
     * @param array $contentSourceSettings Content source settings
     * @return string Cache key for post pool
     * @since 2.3.5
     */
    private function buildPostPoolCacheKey(array $contentSourceSettings): string
    {
        return $this->buildContentPoolCacheKey('post', $contentSourceSettings);
    }

    /**
     * Build cache key for page pool based on content source settings.
     * Different filters create different pools to ensure proper targeting.
     *
     * @param array $contentSourceSettings Content source settings
     * @return string Cache key for page pool
     * @since 2.3.5
     */
    private function buildPagePoolCacheKey(array $contentSourceSettings): string
    {
        return $this->buildContentPoolCacheKey('page', $contentSourceSettings);
    }

    /**
     * Build cache key for comment pool based on content source settings.
     *
     * @param array $contentSourceSettings Content source settings.
     * @return string Cache key for comment pool.
     * @since 2.3.7
     */
    private function buildCommentPoolCacheKey(array $contentSourceSettings): string
    {
        return $this->buildContentPoolCacheKey('comment', $contentSourceSettings);
    }

    /**
     * Build cache key for custom post type pool based on content source settings.
     * Different filters create different pools to ensure proper targeting.
     *
     * @param string $postType The custom post type name
     * @param array $contentSourceSettings Content source settings
     * @return string Cache key for custom post type pool
     * @since 2.3.5
     */
    private function buildCustomPostTypePoolCacheKey(string $postType, array $contentSourceSettings): string
    {
        return $this->buildContentPoolCacheKey('custom_posttype', $contentSourceSettings, $postType);
    }

    /**
     * Generic method to build cache keys for different content types.
     * Handles both multiple filters and legacy single filters.
     *
     * @param string $contentType The content type (order, product, user, post, page, comment, custom_posttype)
     * @param array $contentSourceSettings Content source settings
     * @param string|null $postType Optional post type for custom post types
     * @return string Cache key for the content pool
     * @since 2.0.0
     */
    private function buildContentPoolCacheKey(string $contentType, array $contentSourceSettings, ?string $postType = null): string
    {
        $keyParts = [
            'notifal_' . $contentType . '_pool',
            $contentSourceSettings['content_source_type'] ?? 'dynamic',
        ];

        // Add post type for custom post types
        if ($postType && $contentType === 'custom_posttype') {
            $keyParts[] = $postType;
        }

        $filterKey = $contentType . '_filters';
        if ($contentType === 'custom_posttype') {
            // For custom post types, try both generic and specific keys
            $genericFilterKey = 'custom_posttype_filters';
            $specificFilterKey = $postType . '_filters';
            $filterKey = isset($contentSourceSettings[$genericFilterKey]) ? $genericFilterKey : $specificFilterKey;
        }

        // Check for multiple filters first
        if (isset($contentSourceSettings[$filterKey]) && $contentSourceSettings[$filterKey]['multiple_filters']) {
            $filters = $contentSourceSettings[$filterKey];
            $keyParts[] = 'multi_' . strtolower($filters['logic']);

            if (!empty($filters['conditions'])) {
                foreach ($filters['conditions'] as $condition) {
                    if ($condition['enabled']) {
                        $keyParts[] = $condition['type'] . '_' . md5(serialize($condition['data']));
                    }
                }
            }
        } else {
            // Legacy single filter support - build key based on content type
            $keyParts[] = $this->buildLegacyFilterKey($contentType, $contentSourceSettings, $postType);
        }

        return implode('_', $keyParts);
    }

    /**
     * Build legacy filter key for single filter configurations.
     *
     * @param string $contentType The content type
     * @param array $contentSourceSettings Content source settings
     * @param string|null $postType Optional post type for custom post types
     * @return string Legacy filter key
     * @since 2.0.0
     */
    private function buildLegacyFilterKey(string $contentType, array $contentSourceSettings, ?string $postType = null): string
    {
        $restrictionTypeKey = $contentType . '_restriction_type';
        if ($postType && $contentType === 'custom_posttype') {
            $restrictionTypeKey = $postType . '_restriction_type';
        }

        $restrictionType = $contentSourceSettings[$restrictionTypeKey] ?? 'all';

        if ($restrictionType === 'all') {
            return 'all';
        }

        switch ($contentType) {
            case 'order':
                return $this->buildOrderLegacyKey($restrictionType, $contentSourceSettings);

            case 'product':
                return $this->buildProductLegacyKey($restrictionType, $contentSourceSettings);

            case 'user':
                return $this->buildUserLegacyKey($restrictionType, $contentSourceSettings);

            case 'post':
                return $this->buildPostLegacyKey($restrictionType, $contentSourceSettings);

            case 'page':
                return $this->buildPageLegacyKey($restrictionType, $contentSourceSettings);

            case 'comment':
                return $this->buildCommentLegacyKey($restrictionType, $contentSourceSettings);

            case 'custom_posttype':
                return $this->buildCustomPostTypeLegacyKey($restrictionType, $contentSourceSettings, $postType);

            default:
                return 'all';
        }
    }

    /**
     * Build legacy key for order filters.
     *
     * @param string $restrictionType The restriction type
     * @param array $settings Content source settings
     * @return string Legacy filter key
     * @since 2.0.0
     */
    private function buildOrderLegacyKey(string $restrictionType, array $settings): string
    {
        switch ($restrictionType) {
            case 'date_range':
                $dateRange = $settings['order_date_range'] ?? 'last_7d';
                $key = 'date_' . $dateRange;
                if ($dateRange === 'custom') {
                    $startDate = $settings['order_date_start'] ?? '';
                    $endDate = $settings['order_date_end'] ?? '';
                    $key .= '_custom_' . md5($startDate . $endDate);
                }
                return $key;

            case 'status':
                $statuses = $settings['order_statuses'] ?? ['completed', 'processing'];
                return 'status_' . md5(implode(',', $statuses));

            case 'products':
                $products = $settings['order_products'] ?? [];
                return 'prod_' . md5(implode(',', $products));

            case 'custom':
                $customFilter = $settings['order_custom_filter'] ?? '';
                return 'custom_' . md5($customFilter);

            default:
                return $restrictionType;
        }
    }

    /**
     * Build legacy key for product filters.
     *
     * @param string $restrictionType The restriction type
     * @param array $settings Content source settings
     * @return string Legacy filter key
     * @since 2.0.0
     */
    private function buildProductLegacyKey(string $restrictionType, array $settings): string
    {
        switch ($restrictionType) {
            case 'categories':
                $categories = $settings['product_categories'] ?? [];
                return 'cat_' . md5(implode(',', $categories));

            case 'specific':
                $products = $settings['specific_products'] ?? [];
                return 'prod_' . md5(implode(',', $products));

            case 'sale':
            case 'featured':
                return $restrictionType;

            case 'custom':
                $customFilter = $settings['product_custom_filter'] ?? '';
                return 'custom_' . md5($customFilter);

            default:
                return 'all';
        }
    }

    /**
     * Build legacy key for user filters.
     *
     * @param string $restrictionType The restriction type
     * @param array $settings Content source settings
     * @return string Legacy filter key
     * @since 2.0.0
     */
    private function buildUserLegacyKey(string $restrictionType, array $settings): string
    {
        switch ($restrictionType) {
            case 'roles':
                $roles = $settings['user_roles'] ?? ['customer'];
                return 'roles_' . md5(implode(',', $roles));

            case 'specific':
                $users = $settings['specific_users'] ?? [];
                return 'users_' . md5(implode(',', $users));

            case 'custom':
                $customFilter = $settings['user_custom_filter'] ?? '';
                return 'custom_' . md5($customFilter);

            default:
                return 'all';
        }
    }

    /**
     * Build legacy key for post filters.
     *
     * @param string $restrictionType The restriction type
     * @param array $settings Content source settings
     * @return string Legacy filter key
     * @since 2.0.0
     */
    private function buildPostLegacyKey(string $restrictionType, array $settings): string
    {
        switch ($restrictionType) {
            case 'categories':
                $categories = $settings['post_categories'] ?? [];
                return 'categories_' . md5(implode(',', $categories));

            case 'tags':
                $tags = $settings['post_tags'] ?? [];
                return 'tags_' . md5(implode(',', $tags));

            case 'author':
                $author = $settings['post_author'] ?? '';
                return 'author_' . md5($author);

            case 'date_range':
                $dateRange = $settings['post_date_range'] ?? 'last_7d';
                $key = 'date_' . $dateRange;
                if ($dateRange === 'custom') {
                    $startDate = $settings['post_date_start'] ?? '';
                    $endDate = $settings['post_date_end'] ?? '';
                    $key .= '_custom_' . md5($startDate . $endDate);
                }
                return $key;

            case 'custom':
                $customFilter = $settings['post_custom_filter'] ?? '';
                return 'custom_' . md5($customFilter);

            default:
                return 'all';
        }
    }

    /**
     * Build legacy key for page filters.
     *
     * @param string $restrictionType The restriction type
     * @param array $settings Content source settings
     * @return string Legacy filter key
     * @since 2.0.0
     */
    private function buildPageLegacyKey(string $restrictionType, array $settings): string
    {
        switch ($restrictionType) {
            case 'templates':
                $templates = $settings['page_templates'] ?? [];
                return 'templates_' . md5(implode(',', $templates));

            case 'author':
                $author = $settings['page_author'] ?? '';
                return 'author_' . md5($author);

            case 'parent':
                $parent = $settings['page_parent'] ?? '';
                return 'parent_' . md5($parent);

            case 'date_range':
                $dateRange = $settings['page_date_range'] ?? 'last_7d';
                $key = 'date_' . $dateRange;
                if ($dateRange === 'custom') {
                    $startDate = $settings['page_date_start'] ?? '';
                    $endDate = $settings['page_date_end'] ?? '';
                    $key .= '_custom_' . md5($startDate . $endDate);
                }
                return $key;

            case 'custom':
                $customFilter = $settings['page_custom_filter'] ?? '';
                return 'custom_' . md5($customFilter);

            default:
                return 'all';
        }
    }

    /**
     * Build legacy key for comment filters.
     *
     * @param string $restrictionType The restriction type.
     * @param array  $settings          Content source settings.
     * @return string Legacy filter key.
     * @since 2.3.7
     */
    private function buildCommentLegacyKey(string $restrictionType, array $settings): string
    {
        switch ($restrictionType) {
            case 'status':
                $statuses = $settings['comment_statuses'] ?? ['approved'];
                return 'status_' . md5(implode(',', (array) $statuses));

            case 'post_type':
                $postTypes = $settings['comment_post_types'] ?? ['post'];
                return 'post_type_' . md5(implode(',', (array) $postTypes));

            case 'author':
                $authors = $settings['comment_authors'] ?? [];
                return 'author_' . md5(implode(',', (array) $authors));

            case 'date_range':
                $dateRange = $settings['comment_date_range'] ?? 'last_7d';
                $key = 'date_' . $dateRange;
                if ($dateRange === 'custom') {
                    $startDate = $settings['comment_date_start'] ?? '';
                    $endDate = $settings['comment_date_end'] ?? '';
                    $key .= '_custom_' . md5($startDate . $endDate);
                }
                return $key;

            case 'custom':
                $customFilter = $settings['comment_custom_filter'] ?? '';
                return 'custom_' . md5($customFilter);

            default:
                return $restrictionType;
        }
    }

    /**
     * Build legacy key for custom post type filters.
     *
     * @param string $restrictionType The restriction type
     * @param array $settings Content source settings
     * @param string $postType The post type name
     * @return string Legacy filter key
     * @since 2.0.0
     */
    private function buildCustomPostTypeLegacyKey(string $restrictionType, array $settings, string $postType): string
    {
        switch ($restrictionType) {
            case 'taxonomies':
                $taxonomiesKey = $postType . '_taxonomies';
                $taxonomies = $settings[$taxonomiesKey] ?? [];
                return 'taxonomies_' . md5(serialize($taxonomies));

            case 'author':
                $authorKey = $postType . '_author';
                $author = $settings[$authorKey] ?? '';
                return 'author_' . md5($author);

            case 'date_range':
                $dateRangeKey = $postType . '_date_range';
                $dateRange = $settings[$dateRangeKey] ?? 'last_7d';
                $key = 'date_' . $dateRange;
                if ($dateRange === 'custom') {
                    $startDateKey = $postType . '_date_start';
                    $endDateKey = $postType . '_date_end';
                    $startDate = $settings[$startDateKey] ?? '';
                    $endDate = $settings[$endDateKey] ?? '';
                    $key .= '_custom_' . md5($startDate . $endDate);
                }
                return $key;

            case 'custom':
                $customFilterKey = $postType . '_custom_filter';
                $customFilter = $settings[$customFilterKey] ?? '';
                return 'custom_' . md5($customFilter);

            default:
                return 'all';
        }
    }

    /**
     * Exclude source items already shown in the current visitor session.
     *
     * When all items have been shown, the seen list is reset so rotation can restart.
     *
     * @param string $sourceType Source type key (e.g. product, order, custom_posttype:book).
     * @param array  $pool Source pool.
     * @param array  $contentSourceSettings Notification content source settings.
     * @return array Filtered pool.
     * @since 2.3.5
     */
    public function excludeSeenSourcesFromPool(string $sourceType, array $pool, array $contentSourceSettings): array
    {
        if (empty($pool) || $this->isDuplicateSourceAllowed($contentSourceSettings)) {
            return $pool;
        }

        // Browser session is authoritative until a retrigger request activates server rotation.
        $seenIds = $this->getClientSeenEntityIds($contentSourceSettings);
        if ($this->shouldTrackSeenSourceInServerSession()) {
            $seenIds = array_values(array_unique(array_merge(
                $seenIds,
                $this->getSeenSourceIds($sourceType, $contentSourceSettings)
            )));
        }

        if (empty($seenIds)) {
            return $pool;
        }

        $filteredPool = array_values(array_filter($pool, function ($entity) use ($seenIds) {
            $entityId = $this->extractEntityId($entity);

            if ($entityId <= 0) {
                return true;
            }

            return !in_array($entityId, $seenIds, true);
        }));

        if (!empty($filteredPool)) {
            return $filteredPool;
        }

        // With smart targeting and fallback off, an exhausted contextual pool must not restart rotation.
        if ($this->shouldHoldSeenSourceRotation($contentSourceSettings)) {
            return [];
        }

        $this->setSeenSourceIds($sourceType, $contentSourceSettings, []);

        return $pool;
    }

    /**
     * Whether seen-source rotation should stop instead of resetting when the pool is exhausted.
     *
     * @param array $contentSourceSettings Notification content source settings.
     * @return bool
     * @since 2.3.7
     */
    private function shouldHoldSeenSourceRotation(array $contentSourceSettings): bool
    {
        if ($this->isDuplicateSourceAllowed($contentSourceSettings)) {
            return false;
        }

        // Cart product pools are session-bound; do not restart rotation with the same cart items.
        if (CartProductPoolResolver::settingsContainCartFilter($contentSourceSettings)) {
            return true;
        }

        if (empty($contentSourceSettings['smart_targeting_enabled'])) {
            return false;
        }

        // Hold rotation only on pages where smart targeting actually narrows the pool.
        $pageContext = ContentSourceRequestContext::getPageContext();
        if (!is_array($pageContext) || !PageContextHelper::isSmartTargetingApplicableContext($pageContext)) {
            return false;
        }

        return empty($contentSourceSettings['smart_targeting_fallback']);
    }

    /**
     * Determine whether server-side seen-source transients should be updated for this request.
     *
     * Preload and first eligibility prepare only render HTML; the browser tracks first display.
     * Server rotation is persisted on retrigger / force-fresh API requests only.
     *
     * @return bool
     * @since 2.3.7
     */
    private function shouldTrackSeenSourceInServerSession(): bool
    {
        // Read the active content-source resolution context.
        $pageContext = ContentSourceRequestContext::getPageContext();
        if (!is_array($pageContext) || empty($pageContext)) {
            return false;
        }

        // Retrigger and forced-fresh API calls rotate the contextual pool server-side.
        if (!empty($pageContext['force_fresh_content'])) {
            return true;
        }

        // Rotation seed from the frontend marks an intentional retrigger fetch.
        if (!empty($pageContext['retrigger_rotation'])) {
            return true;
        }

        return false;
    }

    /**
     * Remember a selected source item in the current visitor session.
     *
     * @param string $sourceType Source type key.
     * @param mixed  $entity Selected entity.
     * @param array  $contentSourceSettings Notification content source settings.
     * @return void
     * @since 2.3.5
     */
    public function rememberShownSource(string $sourceType, $entity, array $contentSourceSettings): void
    {
        if ($this->isDuplicateSourceAllowed($contentSourceSettings)) {
            return;
        }

        // Initial page prepare + eligibility must not consume the contextual pool before the browser displays it.
        if (!$this->shouldTrackSeenSourceInServerSession()) {
            return;
        }

        $entityId = $this->extractEntityId($entity);
        if ($entityId <= 0) {
            return;
        }

        $seenIds = $this->getSeenSourceIds($sourceType, $contentSourceSettings);
        if (in_array($entityId, $seenIds, true)) {
            return;
        }

        $seenIds[] = $entityId;
        if (count($seenIds) > 500) {
            $seenIds = array_slice($seenIds, -500);
        }

        $this->setSeenSourceIds($sourceType, $contentSourceSettings, $seenIds);
    }

    /**
     * Read client-side shown entity IDs sent with the eligibility API request.
     *
     * Retrigger displays are tracked in the browser; merge them so new pages skip seen sources.
     *
     * @param array $contentSourceSettings Notification content source settings.
     * @return array<int, int>
     * @since 2.3.7
     */
    private function getClientSeenEntityIds(array $contentSourceSettings): array
    {
        if ($this->isDuplicateSourceAllowed($contentSourceSettings)) {
            return [];
        }

        $pageContext = ContentSourceRequestContext::getPageContext();
        if (!is_array($pageContext)) {
            return [];
        }

        $notificationId = (int) ($pageContext['notification_id'] ?? 0);
        if ($notificationId <= 0) {
            return [];
        }

        $clientSeenMap = $pageContext['client_seen_sources'] ?? [];
        if (!is_array($clientSeenMap)) {
            return [];
        }

        $entry = $clientSeenMap[$notificationId] ?? $clientSeenMap[(string) $notificationId] ?? null;
        if (!is_array($entry)) {
            return [];
        }

        $entityIds = $entry['entityIds'] ?? [];
        if (!is_array($entityIds)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $entityIds))));
    }

    /**
     * Check whether duplicate source display is allowed.
     *
     * Default is false for backward compatibility.
     *
     * @param array $contentSourceSettings Notification content source settings.
     * @return bool
     * @since 2.3.5
     */
    private function isDuplicateSourceAllowed(array $contentSourceSettings): bool
    {
        return !empty($contentSourceSettings['allow_duplicate_source']);
    }

    /**
     * Get seen source IDs for a visitor session and source scope.
     *
     * @param string $sourceType Source type key.
     * @param array  $contentSourceSettings Notification content source settings.
     * @return array<int, int>
     * @since 2.3.5
     */
    private function getSeenSourceIds(string $sourceType, array $contentSourceSettings): array
    {
        $key = $this->buildSeenSourcesCacheKey($sourceType, $contentSourceSettings);
        $seenIds = get_transient($key);

        if (!is_array($seenIds)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $seenIds)));
    }

    /**
     * Store seen source IDs for a visitor session and source scope.
     *
     * @param string $sourceType Source type key.
     * @param array  $contentSourceSettings Notification content source settings.
     * @param array  $seenIds Seen source IDs.
     * @return void
     * @since 2.3.5
     */
    private function setSeenSourceIds(string $sourceType, array $contentSourceSettings, array $seenIds): void
    {
        $key = $this->buildSeenSourcesCacheKey($sourceType, $contentSourceSettings);
        set_transient($key, array_values(array_unique(array_map('intval', $seenIds))), DAY_IN_SECONDS);
    }

    /**
     * Build transient key for seen sources scoped by visitor session and settings.
     *
     * @param string $sourceType Source type key.
     * @param array  $contentSourceSettings Notification content source settings.
     * @return string
     * @since 2.3.5
     */
    private function buildSeenSourcesCacheKey(string $sourceType, array $contentSourceSettings): string
    {
        $sessionId = Helper::getSessionId();

        // Do not include the toggle in scope hash to keep history stable when toggled.
        $scopeSettings = $contentSourceSettings;
        unset($scopeSettings['allow_duplicate_source']);

        $scopeHash = md5(wp_json_encode($scopeSettings));
        $sourceSlug = sanitize_key(str_replace(':', '_', $sourceType));

        return 'notifal_seen_src_' . md5($sessionId . '|' . $sourceSlug . '|' . $scopeHash);
    }

    /**
     * Extract entity ID from supported content source item types.
     *
     * @param mixed $entity Content source entity.
     * @return int
     * @since 2.3.5
     */
    private function extractEntityId($entity): int
    {
        if ($entity instanceof \WP_Post) {
            return (int) $entity->ID;
        }

        if ($entity instanceof \WP_Comment) {
            return (int) $entity->comment_ID;
        }

        if (is_object($entity) && method_exists($entity, 'getId')) {
            return (int) $entity->getId();
        }

        if (is_array($entity) && isset($entity['id'])) {
            return (int) $entity['id'];
        }

        return 0;
    }

    /**
     * Whether a notification is configured to show on every page view.
     *
     * @param int $notificationId Notification post ID.
     * @return bool
     * @since 2.3.7
     */
    private function isAlwaysShowFrequency(int $notificationId): bool
    {
        $timingSettings = get_post_meta($notificationId, '_notifal_timing_settings', true);

        if (!is_array($timingSettings)) {
            return false;
        }

        return ($timingSettings['show_frequency'] ?? 'once_per_session') === 'always';
    }
}