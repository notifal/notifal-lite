<?php

namespace Notifal\Infrastructure\WordPress\WooCommerce\Services;

use Notifal\Domain\Products\ProductFetcherInterface;
use Notifal\Domain\Products\DTO\ProductDTO;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
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
     * Resolve the WC_Product instance used for DTOs (variable parents map to a child variation).
     *
     * @param int $id Product or variation ID.
     * @return WC_Product|null
     * @since 2.0.0
     */
    private function resolveWcProductForDto(int $id): ?WC_Product
    {
        $wcProduct = wc_get_product($id);

        if (!$wcProduct instanceof WC_Product) {
            return null;
        }

        // For variable products (parent), select first variation for preview
        // For variation products, use the variation directly
        if ($wcProduct->is_type('variable')) {
            $children = $wcProduct->get_children();
            if (!empty($children)) {
                $resolved = wc_get_product($children[0]);
                $wcProduct = $resolved instanceof WC_Product ? $resolved : $wcProduct;
            }
        }

        return $wcProduct instanceof WC_Product ? $wcProduct : null;
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
            $wcProduct = $this->resolveWcProductForDto($dto->getId());
            if ($wcProduct && $wcProduct->is_on_sale()) {
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
            $wcProduct = $this->resolveWcProductForDto((int) $productId);
            if ($wcProduct && $wcProduct->is_on_sale()) {
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
        $wcProduct = $this->resolveWcProductForDto($id);

        if (!$wcProduct instanceof WC_Product) {
            return null;
        }

        $dto = new ProductDTO(
            $wcProduct->get_id(),
            $wcProduct->get_name(),
            $wcProduct->get_permalink()
        );

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

        // Sale products filter
        if (isset($filters['on_sale']) && $filters['on_sale']) {
            $args['meta_query'][] = [
                'relation' => 'OR',
                [
                    'key'     => '_sale_price',
                    'value'   => '',
                    'compare' => '!='
                ],
                [
                    'key'     => '_sale_price',
                    'value'   => '0',
                    'compare' => '>'
                ]
            ];
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
                    $saleQuery = $this->buildSaleCondition();
                    if ($saleQuery) {
                        $metaConditions[] = $saleQuery;
                    }
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
     * Build sale condition for meta query.
     *
     * @return array Sale condition for meta query
     * @since 2.0.0
     */
    private function buildSaleCondition(): array
    {
        return [
            'relation' => 'OR',
            [
                'key'     => '_sale_price',
                'value'   => '',
                'compare' => '!='
            ],
            [
                'key'     => '_sale_price',
                'value'   => '0',
                'compare' => '>'
            ]
        ];
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
}
