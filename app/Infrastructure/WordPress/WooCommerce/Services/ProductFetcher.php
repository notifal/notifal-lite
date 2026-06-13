<?php

namespace Notifal\Infrastructure\WordPress\WooCommerce\Services;

use Notifal\Domain\Products\ProductFetcherInterface;
use Notifal\Domain\Products\DTO\ProductDTO;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\CartProductPoolResolver;
use Notifal\Shared\Utils\FilterHelper;
use WP_Query;
use WC_Product;

defined('ABSPATH') || exit;

/**
 * Class ProductFetcher
 *
 * Fetches product data from WooCommerce.
 * Acts as a data adapter between Notifal and WooCommerce.
 *
 * @package Notifal\Infrastructure\WordPress\WooCommerce\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ProductFetcher implements ProductFetcherInterface
{
    /**
     * Retrieve a single random product from WooCommerce.
     *
     * @param array $filters Optional filters to apply
     * @return ProductDTO|null Null if no product found.
     * @since 2.0.0
     */
    public function getRandom(array $filters = []): ?ProductDTO
    {
        if (!PluginDetector::isWooCommerceActive()) {
            return null;
        }

        $pool = $this->getRandomPool(1, $filters);

        return $pool[0] ?? null;
    }

    /**
     * Retrieve multiple random products from WooCommerce for pool-based caching.
     *
     * @param int $count Number of products to fetch (default: 20)
     * @param array $filters Optional filters to apply
     * @return ProductDTO[] Array of ProductDTO objects
     * @since 2.0.0
     */
    public function getRandomPool(int $count = 20, array $filters = []): array
    {
        // Check if WooCommerce is active before proceeding
        if (!PluginDetector::isWooCommerceActive()) {
            return [];
        }
        // Ensure we don't fetch too many products (performance limit)
        $count = max(1, min($count, 50));

        // Resolve cart-aware filters directly from the live cart snapshot.
        if ($this->filtersContainCartCondition($filters)) {
            $productIds = $this->resolveProductIdsFromFilters($filters);

            if (empty($productIds)) {
                return [];
            }

            return $this->buildPoolFromIds($productIds, $count);
        }

        $mustMatchLiveSale = $this->mustValidateSaleAgainstWooCommerce($filters);
        // Widen the SQL candidate set when we will drop rows that fail WC_Product::is_on_sale() (scheduled sale windows, etc.).
        $postsPerPage = $mustMatchLiveSale
            ? min(max($count * 8, 32), 200)
            : $count;

        $args = [
            'post_type'      => 'product',
            'posts_per_page' => $postsPerPage,
            'orderby'        => 'rand',
            'fields'         => 'ids',
        ];

        // Apply custom filters if provided
        $args = $this->applyFilters($args, $filters);

        // If post_type was changed to array due to variations, update the default
        if (isset($args['post_type']) && is_array($args['post_type'])) {
            // Keep as array
        } elseif (!isset($args['post_type'])) {
            $args['post_type'] = 'product';
        }

        $query = new WP_Query($args);

        if (empty($query->posts)) {
            return [];
        }

        $postIds = $query->posts;
        if ($mustMatchLiveSale) {
            $postIds = $this->filterPostIdsByLiveOnSale($postIds, $count);
        }

        if (empty($postIds)) {
            return [];
        }

        $products = [];
        foreach ($postIds as $productId) {
            $productDto = $this->buildProductDTO((int) $productId);
            if ($productDto) {
                $products[] = $productDto;
            }
        }

        return $products;
    }

    /**
     * Count products matching the provided filters.
     *
     * @param array $filters Optional filters to apply.
     * @return int Total matching product count.
     * @since 2.3.7
     */
    public function count(array $filters = []): int
    {
        // Return zero when WooCommerce is unavailable.
        if (!PluginDetector::isWooCommerceActive()) {
            return 0;
        }

        // Count cart-derived pools without running a broad catalog query.
        if ($this->filtersContainCartCondition($filters)) {
            return count($this->resolveProductIdsFromFilters($filters));
        }

        // Query all matching product IDs.
        $args = [
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post_status'    => 'publish',
        ];

        // Apply the same filters used by pool fetching.
        $args = $this->applyFilters($args, $filters);

        $query   = new \WP_Query($args);
        $postIds = is_array($query->posts) ? $query->posts : [];

        // Re-validate sale-only filters against live WooCommerce state.
        if ($this->mustValidateSaleAgainstWooCommerce($filters)) {
            $postIds = $this->filterPostIdsByLiveOnSale($postIds, PHP_INT_MAX);
        }

        return count($postIds);
    }

    /**
     * Find a product by its ID.
     *
     * @param int $id Product ID or variation ID.
     * @return ProductDTO|null Null if product not found.
     * @since 2.0.0
     */
    public function findById(int $id): ?ProductDTO
    {
        // Check if WooCommerce is active before proceeding
        if (!PluginDetector::isWooCommerceActive()) {
            return null;
        }
        $post = get_post($id);
        if (!$post) {
            return null;
        }

        $postType = get_post_type($id);
        
        // Accept both products and product variations
        if (!in_array($postType, ['product', 'product_variation'])) {
            return null;
        }

        return $this->buildProductDTO($id);
    }

    /**
     * Check whether a product or variable parent is currently on sale in WooCommerce.
     *
     * @param int $productId Product or variation ID.
     * @return bool
     * @since 2.3.10
     */
    private function isProductLiveOnSale(int $productId): bool
    {
        // Load the requested WooCommerce product without remapping variable parents.
        $wcProduct = wc_get_product($productId);

        // Delegate to WooCommerce so variable parents include on-sale child variations.
        return $wcProduct instanceof WC_Product && $wcProduct->is_on_sale();
    }

    /**
     * Pick a child variation to represent variable product prices in tags.
     *
     * @param WC_Product $wcProduct Variable parent product.
     * @return WC_Product|null
     * @since 2.3.10
     */
    private function resolveDisplayVariation(WC_Product $wcProduct): ?WC_Product
    {
        // Bail when the product is not a variable parent.
        if (!$wcProduct->is_type('variable')) {
            return null;
        }

        // Read child variation IDs from the parent product.
        $children = $wcProduct->get_children();
        if (empty($children)) {
            return null;
        }

        // Prefer an on-sale variation so sale tags reflect an active discount.
        foreach ($children as $childId) {
            $child = wc_get_product((int) $childId);
            if ($child instanceof WC_Product && $child->is_on_sale()) {
                return $child;
            }
        }

        // Fall back to the first variation when none are currently on sale.
        $firstChild = wc_get_product((int) $children[0]);

        return $firstChild instanceof WC_Product ? $firstChild : null;
    }

    /**
     * {@inheritDoc}
     */
    public function requiresLiveSaleValidation(array $filters): bool
    {
        return $this->mustValidateSaleAgainstWooCommerce($filters);
    }

    /**
     * {@inheritDoc}
     */
    public function filterProductPoolToLiveSaleOnly(array $productDtos): array
    {
        if (!PluginDetector::isWooCommerceActive()) {
            return [];
        }

        $out = [];
        foreach ($productDtos as $dto) {
            if (!$dto instanceof ProductDTO) {
                continue;
            }
            if ($this->isProductLiveOnSale((int) $dto->getId())) {
                $out[] = $dto;
            }
        }

        return $out;
    }

    /**
     * Whether every candidate from the SQL query must currently be on sale per WooCommerce.
     *
     * When false (e.g. OR groups where a branch is non-sale), post-query filtering would drop valid rows.
     *
     * @param array $filters Filter configuration passed to {@see applyFilters()}.
     * @return bool
     * @since 2.0.0
     */
    private function mustValidateSaleAgainstWooCommerce(array $filters): bool
    {
        if (!empty($filters['on_sale'])) {
            return true;
        }

        $conditions = $filters['conditions'] ?? [];
        if (empty($conditions) || !is_array($conditions)) {
            return false;
        }

        if (strtoupper($filters['logic'] ?? 'AND') === 'OR') {
            return false;
        }

        foreach ($conditions as $condition) {
            if (($condition['type'] ?? '') === 'sale') {
                return true;
            }
        }

        return false;
    }

    /**
     * Keep post IDs whose resolved product passes {@see WC_Product::is_on_sale()} at request time.
     *
     * @param array $postIds Post IDs from {@see WP_Query}.
     * @param int   $maxResults Maximum IDs to return.
     * @return int[]
     * @since 2.0.0
     */
    private function filterPostIdsByLiveOnSale(array $postIds, int $maxResults): array
    {
        $out = [];
        foreach ($postIds as $productId) {
            if (count($out) >= $maxResults) {
                break;
            }
            if ($this->isProductLiveOnSale((int) $productId)) {
                $out[] = (int) $productId;
            }
        }

        return $out;
    }

    /**
     * Build a ProductDTO from WooCommerce product ID or variation ID.
     *
     * @param int $id Product ID or variation ID.
     * @return ProductDTO|null
     * @since 2.0.0
     */
    private function buildProductDTO(int $id): ?ProductDTO
    {
        // Load the requested WooCommerce product object.
        $wcProduct = wc_get_product($id);

        if (!$wcProduct instanceof WC_Product) {
            return null;
        }

        // Defaults assume a simple product with no parent/variation split.
        $parentProductId    = $wcProduct->get_id();
        $variationContextId = null;
        $displayProduct     = $wcProduct;

        // Variable parents keep parent identity while prices come from a child variation.
        if ($wcProduct->is_type('variable')) {
            $displayVariation = $this->resolveDisplayVariation($wcProduct);
            if ($displayVariation instanceof WC_Product) {
                $variationContextId = $displayVariation->get_id();
            }
        } elseif ($wcProduct->is_type('variation')) {
            // Direct variation lookups keep variation prices and parent aggregate meta.
            $parentProductId    = (int) $wcProduct->get_parent_id();
            $variationContextId = $wcProduct->get_id();
        }

        // Defaults use the parent/simple product label and URL.
        $displayName = $displayProduct->get_name();
        $permalink   = $displayProduct->get_permalink();

        // Resolved variation drives the public name and deep-link URL shown in tags/buttons.
        if ($variationContextId > 0) {
            $variationProduct = wc_get_product($variationContextId);
            if ($variationProduct instanceof WC_Product) {
                $displayName = $variationProduct->get_name();
                $permalink   = $variationProduct->get_permalink();
            }
        }

        // Build the DTO from the parent ID while exposing the resolved variation details.
        $dto = new ProductDTO(
            $displayProduct->get_id(),
            $displayName,
            $permalink
        );

        // Attach parent/variation context for smart meta resolution in tags.
        $dto->setProductContext($parentProductId, $variationContextId);

        /**
         * Filter: Allow modification of ProductDTO after build
         */
        return apply_filters(FilterHooks::WOOCOMMERCE_PRODUCT_DTO, $dto, $wcProduct);
    }

    /**
     * Apply custom filters to product query arguments.
     * Supports both legacy single filters and new multiple filters with AND/OR logic.
     *
     * @param array $args Base query arguments
     * @param array $filters Filter configuration
     * @return array Modified query arguments
     * @since 2.0.0
     */
    private function applyFilters(array $args, array $filters): array
    {
        if (empty($filters)) {
            return $args;
        }

        // Check for new multi-filter format
        if (isset($filters['multiple_filters']) && $filters['multiple_filters'] === true) {
            return $this->applyMultipleFilters($args, $filters);
        }

        // Check for single condition format (when Pro is not active but we have non-legacy filters)
        if (isset($filters['conditions']) && is_array($filters['conditions']) && !empty($filters['conditions'])) {
            // Convert single condition to multiple format and apply
            $singleConditionFilters = [
                'multiple_filters' => true,
                'logic' => 'AND',
                'conditions' => $filters['conditions']
            ];
            return $this->applyMultipleFilters($args, $singleConditionFilters);
        }

        // Legacy single filter support
        // Categories filter
        if (isset($filters['categories']) && !empty($filters['categories'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $filters['categories'],
                'operator' => 'IN'
            ];
        }

        // Specific products filter
        if (isset($filters['products']) && !empty($filters['products'])) {
            $args['post__in'] = $filters['products'];

            // If we have specific products, check if any are variations
            // If so, we need to query for both products and variations
            $hasVariations = false;
            foreach ($filters['products'] as $productId) {
                $post = get_post($productId);
                if ($post && $post->post_type === 'product_variation') {
                    $hasVariations = true;
                    break;
                }
            }

            if ($hasVariations) {
                $args['post_type'] = ['product', 'product_variation'];
            }
        }

        // Sale products filter (includes variable parents when any variation is on sale).
        if (isset($filters['on_sale']) && $filters['on_sale']) {
            $args = $this->applySalePostInConstraint($args);
        }

        // Featured products filter
        if (isset($filters['featured']) && $filters['featured']) {
            $args['tax_query'][] = [
                'taxonomy' => 'product_visibility',
                'field'    => 'name',
                'terms'    => 'featured',
                'operator' => 'IN'
            ];
        }

        // Custom meta filter
        if (isset($filters['custom_filter']) && !empty($filters['custom_filter'])) {
            $args = $this->applyCustomFilter($args, $filters['custom_filter']);
        }

        return $args;
    }

    /**
     * Apply multiple filters with AND/OR logic to product query.
     *
     * @param array $args Base query arguments
     * @param array $filters Multiple filters configuration
     * @return array Modified query arguments
     * @since 2.0.0
     */
    private function applyMultipleFilters(array $args, array $filters): array
    {
        $conditions = $filters['conditions'] ?? [];
        $logic = strtoupper($filters['logic'] ?? 'AND');

        if (empty($conditions)) {
            return $args;
        }

        // For OR logic, run separate queries and merge results
        // This is necessary because WordPress treats different query types (meta_query, tax_query) as AND by default
        if ($logic === 'OR') {
            $final_product_ids = [];
            $base_args = [
                'post_type' => 'product',
                'posts_per_page' => -1,
                'fields' => 'ids'
            ];

            foreach ($conditions as $condition) {
                // Apply each condition individually
                $single_condition_filters = [
                    'conditions' => [$condition],
                    'logic' => 'AND'
                ];
                $condition_args = $this->applyAndLogicFilters($base_args, $single_condition_filters);
                
                // Execute query for this condition
                $query = new WP_Query($condition_args);
                if (!empty($query->posts)) {
                    $final_product_ids = array_merge($final_product_ids, $query->posts);
                }
            }
            
            // Remove duplicates and apply to main query
            $final_product_ids = array_unique($final_product_ids);

            // Clear any existing query constraints and use post__in
            unset($args['meta_query'], $args['tax_query']);
            $args['post__in'] = !empty($final_product_ids) ? $final_product_ids : [0];
            return $args;
        }

        // For AND logic, use the existing method
        return $this->applyAndLogicFilters($args, $filters);
    }

    /**
     * Apply multiple filters with AND logic.
     *
     * @param array $args Base query arguments
     * @param array $filters Filters configuration
     * @return array Modified query arguments
     * @since 2.0.0
     */
    private function applyAndLogicFilters(array $args, array $filters): array
    {
        $conditions = $filters['conditions'] ?? [];

        if (empty($conditions)) {
            return $args;
        }

        // Initialize query arrays
        if (!isset($args['meta_query'])) {
            $args['meta_query'] = [];
        }
        if (!isset($args['tax_query'])) {
            $args['tax_query'] = [];
        }

        // Process conditions and group by query type
        $metaConditions = [];
        $taxConditions = [];
        $postInConditions = [];
        $applySaleConstraint = false;

        foreach ($conditions as $condition) {
            $conditionType = $condition['type'] ?? '';

            switch ($conditionType) {
                case 'categories':
                    $categoryQuery = $this->buildCategoriesCondition($condition);
                    if ($categoryQuery) {
                        $taxConditions[] = $categoryQuery;
                    }
                    break;

                case 'specific':
                    $specificProducts = $condition['products'] ?? [];
                    if (!empty($specificProducts)) {
                        $postInConditions = array_merge($postInConditions, $specificProducts);
                    }
                    break;

                case 'sale':
                    // Sale constraints are applied through WooCommerce on-sale IDs, not parent meta.
                    $applySaleConstraint = true;
                    break;

                case 'featured':
                    $featuredQuery = $this->buildFeaturedCondition();
                    if ($featuredQuery) {
                        $taxConditions[] = $featuredQuery;
                    }
                    break;

                case 'custom_meta':
                    $customQuery = $this->buildCustomCondition($condition, $filters);
                    if ($customQuery) {
                        $metaConditions[] = $customQuery;
                    }
                    break;

                case 'date_range':
                    $dateQuery = $this->buildDateCondition($condition);
                    if ($dateQuery) {
                        // Date queries are handled through date_query parameter
                        $args['date_query'] = $args['date_query'] ?? [];
                        $args['date_query'][] = $dateQuery;
                    }
                    break;
            }
        }

        // Apply meta conditions with AND logic
        if (!empty($metaConditions)) {
            if (count($metaConditions) === 1) {
                // If the single condition is a group (from custom_meta OR), add it directly
                if (isset($metaConditions[0]['relation'])) {
                    $args['meta_query'][] = $metaConditions[0];
                } else {
                    $args['meta_query'][] = $metaConditions[0];
                }
            } else {
                $args['meta_query'][] = array_merge(['relation' => 'AND'], $metaConditions);
            }
        }

        // Apply taxonomy conditions with AND logic  
        if (!empty($taxConditions)) {
            if (count($taxConditions) === 1) {
                $args['tax_query'][] = $taxConditions[0];
            } else {
                $args['tax_query'][] = array_merge(['relation' => 'AND'], $taxConditions);
            }
        }

        // Apply post_in conditions (specific products)
        if (!empty($postInConditions)) {
            if (isset($args['post__in'])) {
                // Merge with existing post__in filter
                $args['post__in'] = array_unique(array_merge($args['post__in'], $postInConditions));
            } else {
                $args['post__in'] = array_unique($postInConditions);
            }

            // If we have specific products, check if any are variations
            // If so, we need to query for both products and variations
            $hasVariations = false;
            foreach ($postInConditions as $productId) {
                $post = get_post($productId);
                if ($post && $post->post_type === 'product_variation') {
                    $hasVariations = true;
                    break;
                }
            }

            if ($hasVariations) {
                $args['post_type'] = ['product', 'product_variation'];
            }
        }

        // Restrict the query to WooCommerce on-sale IDs (parents + variations).
        if ($applySaleConstraint) {
            $args = $this->applySalePostInConstraint($args);
        }

        return $args;
    }

    /**
     * Build categories condition for tax query.
     *
     * @param array $condition Categories condition
     * @return array|null Tax query condition or null
     * @since 2.0.0
     */
    private function buildCategoriesCondition(array $condition): ?array
    {
        $categories = $condition['categories'] ?? [];
        if (empty($categories)) {
            return null;
        }

        return [
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => $categories,
            'operator' => 'IN'
        ];
    }

    /**
     * Restrict query args to WooCommerce on-sale product IDs.
     *
     * Uses {@see wc_get_product_ids_on_sale()} so variable parents are included when
     * any child variation is discounted.
     *
     * @param array $args WP_Query arguments.
     * @return array
     * @since 2.3.10
     */
    private function applySalePostInConstraint(array $args): array
    {
        // Bail when WooCommerce sale helpers are unavailable.
        if (!function_exists('wc_get_product_ids_on_sale')) {
            $args['post__in'] = [0];
            return $args;
        }

        // Read WooCommerce on-sale IDs (variation IDs and parent IDs).
        $onSaleIds = array_values(array_unique(array_map('intval', wc_get_product_ids_on_sale())));
        if (empty($onSaleIds)) {
            $args['post__in'] = [0];
            return $args;
        }

        // Intersect with any existing post__in constraint (e.g. smart targeting current product).
        if (isset($args['post__in']) && is_array($args['post__in'])) {
            $intersected      = array_values(array_intersect($args['post__in'], $onSaleIds));
            $args['post__in'] = !empty($intersected) ? $intersected : [0];
            return $args;
        }

        // Apply the on-sale ID list directly when no prior post__in exists.
        $args['post__in'] = $onSaleIds;

        return $args;
    }

    /**
     * Build featured condition for tax query.
     *
     * @return array Featured condition for tax query
     * @since 2.0.0
     */
    private function buildFeaturedCondition(): array
    {
        return [
            'taxonomy' => 'product_visibility',
            'field'    => 'name',
            'terms'    => 'featured',
            'operator' => 'IN'
        ];
    }

    /**
     * Build custom condition by parsing custom filter string.
     *
     * @param array $condition Custom condition
     * @return array|null Meta query condition or null
     * @since 2.0.0
     */
    private function buildCustomCondition(array $condition, array $filters): ?array
    {
        $customFilter = $condition['custom_filter'] ?? '';
        if (empty($customFilter)) {
            return null;
        }

        // Parse the custom filter using FilterHelper
        $metaQueries = FilterHelper::parseCustomFilter($customFilter);

        if (empty($metaQueries)) {
            return null;
        }

        // Return the parsed condition(s)
        if (count($metaQueries) === 1) {
            return $metaQueries[0];
        } else {
            // If the main logic is OR, and we have a custom meta filter with OR,
            // we need to wrap it in an AND group to avoid conflicts.
            if (($filters['logic'] ?? 'AND') === 'AND' && isset($metaQueries['relation']) && $metaQueries['relation'] === 'OR') {
                return $metaQueries;
            }
            return $metaQueries;
        }
    }

    /**
     * Apply custom meta filter to product query.
     * Supports operations: =, !=, >, <, >=, <=, LIKE, NOT LIKE
     * Supports logical operators: &&, ||, AND, OR
     *
     * @param array $args Query arguments
     * @param string $customFilter Custom filter string
     * @return array Modified query arguments
     * @since 2.0.0
     */
    private function applyCustomFilter(array $args, string $customFilter): array
    {
        if (empty($customFilter)) {
            return $args;
        }

        // Parse the custom filter using FilterHelper
        $metaQueries = FilterHelper::parseCustomFilter($customFilter);

        if (empty($metaQueries)) {
            return $args;
        }

        // If there's only one condition, add it directly
        if (count($metaQueries) === 1) {
            $args['meta_query'][] = $metaQueries[0];
        } else {
            // Multiple conditions with relation
            $args['meta_query'][] = $metaQueries;
        }

        return $args;
    }

    /**
     * Build date condition for date query.
     *
     * Supports filtering products by publish date or modified date.
     *
     * @param array $condition Date condition configuration
     * @return array|null Date query condition or null
     * @since 2.0.0
     */
    private function buildDateCondition(array $condition): ?array
    {
        $dateType = $condition['date_type'] ?? 'publish'; // 'publish' or 'modified'
        $range = $condition['range'] ?? '';
        
        if (empty($range)) {
            return null;
        }

        $dateQuery = [];
        $now = new \DateTime();

        // Determine which date column to use
        $column = ($dateType === 'modified') ? 'post_modified' : 'post_date';
        $dateQuery['column'] = $column;

        switch ($range) {
            case 'last_24h':
                $startDate = clone $now;
                $startDate->modify('-24 hours');
                $dateQuery['after'] = $startDate->format('Y-m-d H:i:s');
                break;

            case 'last_7d':
                $startDate = clone $now;
                $startDate->modify('-7 days');
                $dateQuery['after'] = $startDate->format('Y-m-d H:i:s');
                break;

            case 'last_30d':
                $startDate = clone $now;
                $startDate->modify('-30 days');
                $dateQuery['after'] = $startDate->format('Y-m-d H:i:s');
                break;

            case 'last_90d':
                $startDate = clone $now;
                $startDate->modify('-90 days');
                $dateQuery['after'] = $startDate->format('Y-m-d H:i:s');
                break;

            case 'custom':
                $startDate = $condition['start_date'] ?? '';
                $endDate = $condition['end_date'] ?? '';
                
                if (!empty($startDate)) {
                    $dateQuery['after'] = $startDate . ' 00:00:00';
                }
                if (!empty($endDate)) {
                    $dateQuery['before'] = $endDate . ' 23:59:59';
                }
                
                // If neither start nor end date provided, return null
                if (empty($startDate) && empty($endDate)) {
                    return null;
                }
                break;

            default:
                return null;
        }

        // Ensure we have at least one date constraint
        if (empty($dateQuery['after']) && empty($dateQuery['before'])) {
            return null;
        }

        return $dateQuery;
    }

    /**
     * Determine whether filter conditions include a cart product source.
     *
     * @param array<string, mixed> $filters Product filter configuration.
     * @return bool
     * @since 2.3.9
     */
    private function filtersContainCartCondition(array $filters): bool
    {
        $conditions = $filters['conditions'] ?? [];

        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                continue;
            }

            if (($condition['type'] ?? '') === 'cart') {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve product IDs for multi-filter configurations that include cart conditions.
     *
     * @param array<string, mixed> $filters Product filter configuration.
     * @return int[]
     * @since 2.3.9
     */
    private function resolveProductIdsFromFilters(array $filters): array
    {
        $conditions = $filters['conditions'] ?? [];
        $logic = strtoupper((string) ($filters['logic'] ?? 'AND'));

        if (empty($conditions)) {
            return [];
        }

        $resultSets = [];

        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                continue;
            }

            $resultSets[] = $this->resolveProductIdsForCondition($condition);
        }

        if (empty($resultSets)) {
            return [];
        }

        if ($logic === 'OR') {
            $merged = [];

            foreach ($resultSets as $resultSet) {
                $merged = array_merge($merged, $resultSet);
            }

            return array_values(array_unique(array_map('intval', $merged)));
        }

        $intersection = $resultSets[0];

        for ($index = 1, $count = count($resultSets); $index < $count; $index++) {
            if (empty($resultSets[$index])) {
                return [];
            }

            $intersection = array_values(array_intersect($intersection, $resultSets[$index]));
        }

        return array_values(array_map('intval', $intersection));
    }

    /**
     * Resolve product IDs for one filter condition.
     *
     * @param array<string, mixed> $condition Single filter condition.
     * @return int[]
     * @since 2.3.9
     */
    private function resolveProductIdsForCondition(array $condition): array
    {
        $type = (string) ($condition['type'] ?? '');

        if ($type === 'cart') {
            return CartProductPoolResolver::resolve($condition);
        }

        $singleFilters = [
            'multiple_filters' => true,
            'logic' => 'AND',
            'conditions' => [$condition],
        ];

        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post_status' => 'publish',
        ];

        $args = $this->applyAndLogicFilters($args, $singleFilters);

        if (isset($args['post__in']) && $args['post__in'] === [0]) {
            return [];
        }

        $query = new WP_Query($args);
        $postIds = is_array($query->posts) ? array_map('intval', $query->posts) : [];

        if ($this->mustValidateSaleAgainstWooCommerce($singleFilters)) {
            $postIds = $this->filterPostIdsByLiveOnSale($postIds, PHP_INT_MAX);
        }

        return $postIds;
    }

    /**
     * Build a product DTO pool from explicit product IDs.
     *
     * @param int[] $productIds Resolved product IDs.
     * @param int   $count      Maximum pool size.
     * @return ProductDTO[]
     * @since 2.3.9
     */
    private function buildPoolFromIds(array $productIds, int $count): array
    {
        if (empty($productIds)) {
            return [];
        }

        shuffle($productIds);
        $productIds = array_slice($productIds, 0, min($count, 50));

        $products = [];

        foreach ($productIds as $productId) {
            $productDto = $this->buildProductDTO((int) $productId);

            if ($productDto) {
                $products[] = $productDto;
            }
        }

        return $products;
    }
}
