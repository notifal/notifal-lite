<?php
namespace Notifal\Infrastructure\WordPress\WooCommerce\Services;

use Notifal\Domain\Orders\DTO\OrderDTO;
use Notifal\Domain\Orders\DTO\OrderItemDTO;
use Notifal\Domain\Orders\OrderFetcherInterface;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Shared\Utils\FilterHelper;
use Automattic\WooCommerce\Utilities\OrderUtil;

use WC_Order;

defined('ABSPATH') || exit;

/**
 * Class OrderFetcher
 *
 * WooCommerce order data retrieval implementation.
 * Handles fetching orders with various filters and caching strategies.
 *
 * @package Notifal\Infrastructure\WordPress\WooCommerce\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class OrderFetcher implements OrderFetcherInterface
{
    /**
     * WooCommerce parent order type (not a post-type-only value).
     *
     *
     *    
     */
    private const ORDER_TYPE_SHOP_ORDER = 'shop_order';

    /**
     * Retrieve a random order for preview.
     *
     * @param array $filters Optional filters to apply
     * @return OrderDTO|null
     * @since 2.0.0
     */
    public function getRandom(array $filters = []): ?OrderDTO
    {
        // Check if WooCommerce is active before proceeding
        if (!PluginDetector::isWooCommerceActive()) {
            return null;
        }
        $args = [
            'limit'   => 1,
            'orderby' => 'rand',
            'type'    => self::ORDER_TYPE_SHOP_ORDER,
        ];

        // Apply custom filters if provided
        $args = $this->applyFilters($args, $filters);
        $args = $this->ensureShopOrderType($args);

        $context = $this->extractContextFromFilters($filters);

        $orders = wc_get_orders($this->buildWcGetOrdersArgs($args));

        if (empty($orders)) {
            return null;
        }

        $order = $this->pickFirstProcessableOrder($orders);

        if ($order === null) {
            return null;
        }

        return $this->buildOrderDTO($order, $context);
    }

    /**
     * Retrieve multiple random orders for pool-based caching.
     *
     * @param int $count Number of orders to fetch (default: 20)
     * @param array $filters Optional filters to apply
     * @return OrderDTO[] Array of OrderDTO objects
     * @since 2.0.0
     */
    public function getRandomPool(int $count = 20, array $filters = []): array
    {
        // Check if WooCommerce is active before proceeding
        if (!PluginDetector::isWooCommerceActive()) {
            return [];
        }
        // Ensure we don't fetch too many orders (performance limit)
        $count = max(1, min($count, 50));
        
        $args = [
            'limit'   => $count,
            'orderby' => 'rand',
            'type'    => self::ORDER_TYPE_SHOP_ORDER,
        ];

        // Apply custom filters if provided
        $args = $this->applyFilters($args, $filters);
        $args = $this->ensureShopOrderType($args);

        $context = $this->extractContextFromFilters($filters);

        // meta_query: use wc_get_orders on HPOS; legacy CPT falls back to WP_Query inside helper
        if (!empty($args['meta_query'])) {
            $orders = $this->getOrdersWithMetaQuery($args);
        } else {
            $orders = wc_get_orders($this->buildWcGetOrdersArgs($args));
        }
        
        if (empty($orders)) {
            return [];
        }

        $orderDTOs = [];
        foreach ($orders as $order) {
            if (! $this->isProcessableOrder($order)) {
                continue;
            }

            $orderDTOs[] = $this->buildOrderDTO($order, $context);
        }

        return $orderDTOs;
    }

    /**
     * Count orders matching the provided filters.
     *
     * Uses the same filter pipeline as pool fetching but returns the full
     * matching total instead of a limited random sample.
     *
     * @param array $filters Optional filters to apply.
     * @return int Total matching order count.
     * @since 2.3.7
     */
    public function count(array $filters = []): int
    {
        // Return zero when WooCommerce is not available.
        if (!PluginDetector::isWooCommerceActive()) {
            return 0;
        }

        // Build a query that requests all matching order IDs.
        $args = [
            'limit'   => -1,
            'orderby' => 'ID',
            'order'   => 'DESC',
            'type'    => self::ORDER_TYPE_SHOP_ORDER,
            'return'  => 'ids',
        ];

        // Apply the same content-source filters used for random pools.
        $args = $this->applyFilters($args, $filters);

        // Count IDs using HPOS-safe and legacy-compatible fetching.
        return count($this->fetchMatchingOrderIds($args));
    }

    /**
     * Find an order by its ID.
     *
     * @param int $id
     * @return OrderDTO|null
     * @since 2.0.0
     */
    public function findById(int $id): ?OrderDTO
    {
        // Check if WooCommerce is active before proceeding
        if (!PluginDetector::isWooCommerceActive()) {
            return null;
        }
        $order = wc_get_order($id);

        if (! $order instanceof WC_Order) {
            return null;
        }

        return $this->buildOrderDTO($order);
    }

    /**
     * Extracts contextual data from filters.
     *
     * @param array $filters The filters array.
     * @return array The extracted context.
     * @since 2.0.0
     */
    private function extractContextFromFilters(array $filters): array
    {
        $context = [];

        // Legacy single-filter format (content source + smart targeting inject).
        if (!empty($filters['products'])) {
            $context['filtered_product_ids'] = array_values(array_map('intval', (array) $filters['products']));
            return $context;
        }

        // Multi-filter format: read the first products condition.
        $conditions = $filters['conditions'] ?? [];

        foreach ($conditions as $condition) {
            if (($condition['type'] ?? '') !== 'products') {
                continue;
            }

            $productIds = $condition['products'] ?? ($condition['data']['products'] ?? []);
            if (!empty($productIds)) {
                $context['filtered_product_ids'] = array_values(array_map('intval', (array) $productIds));
                break;
            }
        }

        return $context;
    }

    /**
     * Apply custom filters to order query arguments.
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

        // Date range filter
        if (isset($filters['date_range']) && $filters['date_range'] !== 'all') {
            $args = $this->applyDateRangeFilter($args, $filters);
        }

        // Status filter
        if (isset($filters['status']) && !empty($filters['status'])) {
            $args['status'] = $filters['status'];
        }

        // Products filter
        if (isset($filters['products']) && !empty($filters['products'])) {
            $args = $this->applyProductsFilter($args, $filters['products']);
        }

        // Custom meta filter
        if (isset($filters['custom_filter']) && !empty($filters['custom_filter'])) {
            $args = $this->applyCustomFilter($args, $filters['custom_filter']);
        }

        return $args;
    }

    /**
     * Apply multiple filters with AND/OR logic to order query.
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

        if ($logic === 'OR') {
            $final_order_ids = [];
            $base_args = [
                'limit'  => -1,
                'return' => 'ids',
                'type'   => self::ORDER_TYPE_SHOP_ORDER,
            ];

            foreach ($conditions as $condition) {
                $single_condition_filters = [
                    'conditions' => [$condition],
                    'logic' => 'AND'
                ];
                $condition_args = $this->applyAndLogicFilters($base_args, $single_condition_filters);
                
                $order_ids = [];
                // Fetch IDs through storage-agnostic helper (HPOS + legacy post type).
                $order_ids = $this->fetchMatchingOrderIds($condition_args);
                
                if (!empty($order_ids)) {
                    $final_order_ids = array_merge($final_order_ids, $order_ids);
                }
            }
            
            $final_order_ids = array_unique($final_order_ids);

            unset($args['status'], $args['date_query'], $args['meta_query']);
            $args['post__in'] = !empty($final_order_ids) ? $final_order_ids : [0];
            return $args;
        }

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

        if (!isset($args['meta_query'])) {
            $args['meta_query'] = [];
        }

        $metaConditions = [];

        foreach ($conditions as $condition) {
            $conditionType = $condition['type'] ?? '';

            switch ($conditionType) {
                case 'status':
                    if (!empty($condition['statuses'])) {
                        $args['status'] = $condition['statuses'];
                    }
                    break;
                case 'date_range':
                    $date_range_data = [
                        'date_range' => $condition['range'] ?? 'last_7d',
                    ];
                    if ($date_range_data['date_range'] === 'custom') {
                        $date_range_data['start_date'] = $condition['start_date'] ?? '';
                        $date_range_data['end_date'] = $condition['end_date'] ?? '';
                    }
                    $args = $this->applyDateRangeFilter($args, $date_range_data);
                    break;
                case 'products':
                    if (!empty($condition['products'])) {
                        $args = $this->applyProductsFilter($args, $condition['products']);
                    }
                    break;

                case 'custom_meta':
                    if (!empty($condition['meta_key']) && isset($condition['value'])) {
                        $metaConditions[] = [
                            'key' => $condition['meta_key'],
                            'value' => $condition['value'],
                            'compare' => $condition['operator'] ?? '='
                        ];
                    }
                    break;
                case 'custom_filter':
                    if (!empty($condition['custom_filter'])) {
                        $args = $this->applyCustomFilter($args, $condition['custom_filter']);
                    }
                    break;
            }
        }

        if (!empty($metaConditions)) {
            if (count($metaConditions) === 1) {
                $args['meta_query'][] = $metaConditions[0];
            } else {
                $metaConditions['relation'] = 'AND';
                $args['meta_query'][] = $metaConditions;
            }
        }

        return $args;
    }



    /**
     * Apply date range filter to order query.
     *
     * @param array $args Query arguments
     * @param array $filters Filter configuration
     * @return array Modified query arguments
     * @since 2.0.0
     */
    private function applyDateRangeFilter(array $args, array $filters): array
    {
        $dateRange = $filters['date_range'];
        $now = new \DateTime();

        switch ($dateRange) {
            case 'last_24h':
                $startDate = clone $now;
                $startDate->modify('-24 hours');
                break;
            case 'last_7d':
                $startDate = clone $now;
                $startDate->modify('-7 days');
                break;
            case 'last_30d':
                $startDate = clone $now;
                $startDate->modify('-30 days');
                break;
            case 'last_90d':
                $startDate = clone $now;
                $startDate->modify('-90 days');
                break;
            case 'custom':
                if (isset($filters['start_date']) && isset($filters['end_date'])) {
                    $startDate = new \DateTime($filters['start_date']);
                    $endDate = new \DateTime($filters['end_date']);
                    $args['date_created'] = $startDate->format('Y-m-d') . '...' . $endDate->format('Y-m-d');
                    return $args;
                }
                return $args;
            default:
                return $args;
        }

        $args['date_created'] = '>=' . $startDate->format('Y-m-d');
        return $args;
    }

    /**
     * Apply products filter to order query.
     *
     * @param array $args Query arguments
     * @param array $productIds Array of product IDs
     * @return array Modified query arguments
     * @since 2.0.0
     */
    private function applyProductsFilter(array $args, array $productIds): array
    {
        global $wpdb;

        if (empty($productIds)) {
            return $args;
        }

        $productIds = array_map('intval', $productIds);
        $placeholders = implode(',', array_fill(0, count($productIds), '%d'));

        $order_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT oi.order_id
            FROM {$wpdb->prefix}woocommerce_order_items oi
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            WHERE oi.order_item_type = 'line_item'
            AND oim.meta_key IN ('_product_id', '_variation_id')
            AND oim.meta_value IN ($placeholders)",
            $productIds
        ));

        if (empty($order_ids)) {
            $args['post__in'] = [0];
            return $args;
        }

        if (isset($args['post__in'])) {
            $args['post__in'] = array_intersect($args['post__in'], $order_ids);
            if (empty($args['post__in'])) {
                $args['post__in'] = [0];
            }
        } else {
            $args['post__in'] = $order_ids;
        }

        return $args;
    }

    /**
     * Apply custom meta filter to order query.
     *
     * @param array $args Query arguments
     * @param string $customFilter Custom filter string (format: meta_key:value)
     * @return array Modified query arguments
     * @since 2.0.0
     */
    private function applyCustomFilter(array $args, string $customFilter): array
    {
        if (empty($customFilter)) {
            return $args;
        }
        
        // Parse the custom filter
        $metaQueries = FilterHelper::parseCustomFilter($customFilter);
        
        if (empty($metaQueries)) {
            return $args;
        }
        
        // Initialize meta_query if not set
        if (!isset($args['meta_query'])) {
            $args['meta_query'] = [];
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
     * Whether WooCommerce stores orders in custom tables (HPOS).
     *
     * @return bool
     * @since 2.0.0
     */
    private function isHposEnabled(): bool
    {
        if (! class_exists(OrderUtil::class)) {
            return false;
        }

        return OrderUtil::custom_orders_table_usage_is_enabled();
    }

    /**
     * Force parent-order type on wc_get_orders args (HPOS + legacy).
     *
     * @param array $args Query arguments.
     * @return array
     * @since 2.0.0
     */
    private function ensureShopOrderType(array $args): array
    {
        $args['type'] = self::ORDER_TYPE_SHOP_ORDER;

        return $args;
    }

    /**
     * Map internal fetcher args to wc_get_orders() (storage-agnostic).
     *
     * @param array $args Internal query arguments.
     * @return array Arguments for wc_get_orders().
     * @since 2.0.0
     */
    private function buildWcGetOrdersArgs(array $args): array
    {
        $wcArgs = [
            'limit'   => $args['limit'] ?? 20,
            'orderby' => $args['orderby'] ?? 'rand',
            'order'   => $args['order'] ?? 'DESC',
            'type'    => $args['type'] ?? self::ORDER_TYPE_SHOP_ORDER,
            'return'  => 'objects',
        ];

        if (! empty($args['meta_query'])) {
            $wcArgs['meta_query'] = $args['meta_query'];
        }

        if (! empty($args['status'])) {
            $wcArgs['status'] = $args['status'];
        }

        if (! empty($args['date_created'])) {
            $wcArgs['date_created'] = $args['date_created'];
        }

        // Restrict to explicit order IDs when product or OR-filter pipelines set post__in.
        if (! empty($args['post__in'])) {
            // HPOS remaps post__in to the orders table id column; legacy CPT uses WP_Query post__in.
            $wcArgs['post__in'] = array_values(array_map('intval', (array) $args['post__in']));
        }

        return $wcArgs;
    }

    /**
     * Fetch matching parent shop order IDs for HPOS and legacy post-type storage.
     *
     * @param array $args Internal query arguments after filters are applied.
     * @return int[] Matching order IDs.
     * @since 2.3.7
     */
    private function fetchMatchingOrderIds(array $args): array
    {
        // Always scope queries to parent shop orders (exclude refunds).
        $args = $this->ensureShopOrderType($args);

        // Explicit empty intersection means no matches.
        if (isset($args['post__in']) && $args['post__in'] === [0]) {
            return [];
        }

        // Meta queries use dedicated HPOS/legacy handlers.
        if (!empty($args['meta_query'])) {
            $orders = $this->getOrdersWithMetaQuery($args);
            $ids    = [];

            foreach ($orders as $order) {
                if ($this->isProcessableOrder($order)) {
                    $ids[] = (int) $order->get_id();
                }
            }

            return $ids;
        }

        // Default path uses wc_get_orders() with mapped include/date/status args.
        $orderIds = wc_get_orders($this->buildWcGetOrdersCountArgs($args));

        if (!is_array($orderIds)) {
            return [];
        }

        return array_map('intval', $orderIds);
    }

    /**
     * Map internal fetcher args to wc_get_orders() for ID-only counting.
     *
     * @param array $args Internal query arguments.
     * @return array Arguments for wc_get_orders().
     * @since 2.3.7
     */
    private function buildWcGetOrdersCountArgs(array $args): array
    {
        // Reuse the standard argument builder for consistency.
        $wcArgs = $this->buildWcGetOrdersArgs($args);

        // Counting only needs IDs, not full order objects.
        $wcArgs['return'] = 'ids';
        $wcArgs['limit']  = -1;

        return $wcArgs;
    }

    /**
     * Get orders when meta_query is required.
     *
     * HPOS: wc_get_orders() with meta_query (custom tables).
     * Legacy CPT: WP_Query on shop_order posts (meta_query not supported on wc_get_orders pre-HPOS).
     *
     * @param array $args Query arguments with meta_query
     * @return array Array of WC_Order objects
     * @since 2.0.0
     */
    private function getOrdersWithMetaQuery(array $args): array
    {
        if ($this->isHposEnabled()) {
            $orders = wc_get_orders($this->buildWcGetOrdersArgs($args));

            return $this->filterProcessableOrders(is_array($orders) ? $orders : []);
        }

        // Legacy post-type storage: WP_Query against shop_order posts.
        $queryArgs = [
            'post_type' => self::ORDER_TYPE_SHOP_ORDER,
            'post_status' => ['wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending'],
            'posts_per_page' => $args['limit'] ?? 20,
            'orderby' => $args['orderby'] ?? 'rand',
            'order' => $args['order'] ?? 'DESC',
            'meta_query' => $args['meta_query'] ?? []
        ];
        
        // Add post__in filter if specified (important for products filter intersection)
        if (!empty($args['post__in'])) {
            $queryArgs['post__in'] = $args['post__in'];
        }
        
        // Add status filter if specified
        if (!empty($args['status'])) {
            $statuses = is_array($args['status']) ? $args['status'] : [$args['status']];
            $queryArgs['post_status'] = array_map(function($status) {
                return 'wc-' . $status;
            }, $statuses);
        }
        
        // Add date filter if specified
        if (!empty($args['date_created'])) {
            $queryArgs['date_query'] = $this->parseDateQuery($args['date_created']);
        }
        
        $query = new \WP_Query($queryArgs);
        $orders = [];
        
        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $order = wc_get_order($post->ID);
                if ($this->isProcessableOrder($order)) {
                    $orders[] = $order;
                }
            }
        }
        
        wp_reset_postdata();

        return $this->filterProcessableOrders($orders);
    }

    /**
     * Keep only parent shop orders from a query result (excludes refunds).
     *
     * @param array $orders Order objects from WooCommerce.
     * @return WC_Order[]
     * @since 2.0.0
     */
    private function filterProcessableOrders(array $orders): array
    {
        $processable = [];

        foreach ($orders as $order) {
            if ($this->isProcessableOrder($order)) {
                $processable[] = $order;
            }
        }

        return $processable;
    }

    /**
     * Parse date query from WooCommerce date_created format.
     *
     * @param string $dateCreated Date created string
     * @return array Date query array
     * @since 2.0.0
     */
    private function parseDateQuery(string $dateCreated): array
    {
        if (strpos($dateCreated, '...') !== false) {
            // Range format: "2023-01-01...2023-12-31"
            [$start, $end] = explode('...', $dateCreated);
            return [
                [
                    'after' => $start,
                    'before' => $end,
                    'inclusive' => true
                ]
            ];
        } elseif (strpos($dateCreated, '>=') === 0) {
            // Greater than format: ">=2023-01-01"
            $date = substr($dateCreated, 2);
            return [
                [
                    'after' => $date,
                    'inclusive' => true
                ]
            ];
        }
        
        return [];
    }






    /**
     * Whether the order object can be converted into preview/notification DTO data.
     *
     * Uses WC_Order instance check plus get_type() so HPOS and legacy both exclude refunds.
     *
     * @param mixed $order WooCommerce order instance from wc_get_order / wc_get_orders.
     * @return bool
     * @since 2.0.0
     */
    private function isProcessableOrder($order): bool
    {
        if (! $order instanceof WC_Order) {
            return false;
        }

        if (! method_exists($order, 'get_type')) {
            return true;
        }

        return $order->get_type() === self::ORDER_TYPE_SHOP_ORDER;
    }

    /**
     * Return the first real shop order from a wc_get_orders result (skips refunds).
     *
     * @param array $orders Order objects from WooCommerce.
     * @return WC_Order|null
     * @since 2.0.0
     */
    private function pickFirstProcessableOrder(array $orders): ?WC_Order
    {
        foreach ($orders as $order) {
            if ($this->isProcessableOrder($order)) {
                return $order;
            }
        }

        return null;
    }

    /**
     * Build an OrderDTO from WC_Order object.
     *
     * @param WC_Order $order
     * @param array $context Contextual data, e.g., filtered product IDs.
     * @return OrderDTO
     * @since 2.0.0
     */
    private function buildOrderDTO(WC_Order $order, array $context = []): OrderDTO
    {
        $items = [];
        $order_items = $order->get_items();
        $filtered_product_ids = $context['filtered_product_ids'] ?? [];

        if (!empty($filtered_product_ids)) {
            $filtered_items = array_filter($order_items, function ($item) use ($filtered_product_ids) {
                $product_id = $item->get_product_id();
                $variation_id = $item->get_variation_id();
                return in_array($product_id, $filtered_product_ids) || ($variation_id && in_array($variation_id, $filtered_product_ids));
            });

            if (!empty($filtered_items)) {
                $order_items = $filtered_items;
            }
        }

        foreach ($order_items as $item) {
            // Get the correct product ID (variation ID if it's a variation, product ID if simple)
            $productId = $this->getCorrectProductId($item);

            $items[] = new OrderItemDTO(
                $productId,
                $this->getFullProductName($item),
                $item->get_quantity(),
                floatval($item->get_total() / max(1, $item->get_quantity()))
            );
        }

        return new OrderDTO(
            $order->get_id(),
            $order->get_date_created(),
            $items
        );
    }

    /**
     * Get the full product name including variation attributes.
     *
     * @param \WC_Order_Item_Product $item Order item
     * @return string Full product name with variation attributes
     * @since 2.0.0
     */
    private function getFullProductName(\WC_Order_Item_Product $item): string
    {
        // Get the base product name from the order item
        $baseName = $item->get_name();
        
        // Get the actual product object
        $product = $item->get_product();
        
        if (!$product) {
            return $baseName;
        }
        
        // Check if this is a product variation
        if ($product->is_type('variation')) {
            // Get the parent product name
            $parentProduct = wc_get_product($product->get_parent_id());
            $parentName = $parentProduct ? $parentProduct->get_name() : $baseName;
            
            // Get formatted variation attributes (e.g., "36, Black")
            $variationAttributes = wc_get_formatted_variation($product, true);
            
            if (!empty($variationAttributes)) {
                // Remove "Color: " and "Size: " prefixes and keep only values
                $variationAttributes = strip_tags($variationAttributes);
                
                // Build full name: "Pierce Gym Short - 36 - Black"
                return $parentName . ' - ' . str_replace(', ', ' - ', $variationAttributes);
            }
            
            // Fallback: if no variation attributes, return parent name
            return $parentName;
        }
        
        // For simple products, return the base name
        return $baseName;
    }

    /**
     * Get the correct product ID for an order item.
     * 
     * For variations, this should return the variation ID, not the parent product ID.
     * WooCommerce order items store both product_id (parent) and variation_id.
     *
     * @param \WC_Order_Item_Product $item Order item
     * @return int Correct product ID (variation ID for variations, product ID for simple products)
     * @since 2.0.0
     */
    private function getCorrectProductId(\WC_Order_Item_Product $item): int
    {
        // Check if this is a variation (has variation_id)
        $variationId = $item->get_variation_id();
        
        if ($variationId > 0) {
            // This is a variation - return the variation ID
            return $variationId;
        }
        
        // This is a simple product - return the product ID
        return $item->get_product_id();
    }
}
