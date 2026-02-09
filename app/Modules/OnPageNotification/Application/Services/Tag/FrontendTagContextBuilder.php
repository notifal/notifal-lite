<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Tag;

use Notifal\Modules\OnPageNotification\Application\Services\Settings\ContentSourceService;
use Notifal\Infrastructure\WordPress\WooCommerce\Services\OrderFetcher;
use Notifal\Infrastructure\WordPress\WooCommerce\Services\ProductFetcher;
use Notifal\Infrastructure\WordPress\Services\UserFetcher;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Domain\Tags\Services\TagDetector;

defined('ABSPATH') || exit;

/**
 * Builds proper context for tag resolution on frontend based on content source settings.
 *
 * Ensures that tags are resolved with data that matches the content source restrictions.
 * This service analyzes template content to determine the primary entity type and builds
 * appropriate context data for consistent tag rendering across widgets and notifications.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FrontendTagContextBuilder
{
    /**
     * @var ContentSourceService
     */
    private $contentSourceService;

    /**
     * @var OrderFetcher
     */
    private $orderFetcher;

    /**
     * @var ProductFetcher
     */
    private $productFetcher;

    /**
     * @var UserFetcher
     */
    private $userFetcher;

    /**
     * @var array Cache for WooCommerce product objects during validation
     */
    private $productCache = [];


    /**
     * Constructor
     *
     * @param ContentSourceService $contentSourceService
     * @param OrderFetcher $orderFetcher
     * @param ProductFetcher $productFetcher
     * @param UserFetcher $userFetcher
     * @since 2.0.0
     */
    public function __construct(
        ContentSourceService $contentSourceService,
        OrderFetcher $orderFetcher,
        ProductFetcher $productFetcher,
        UserFetcher $userFetcher
    ) {
        $this->contentSourceService = $contentSourceService;
        $this->orderFetcher = $orderFetcher;
        $this->productFetcher = $productFetcher;
        $this->userFetcher = $userFetcher;
    }

    /**
     * Build tag context for frontend rendering based on content source settings.
     *
     * @param array $contentSourceSettings Content source settings from notification
     * @param array $pageContext Current page context (user, product, order)
     * @return array Context array for tag resolution
     * @since 2.0.0
     */
    public function buildContext(array $contentSourceSettings, array $pageContext = []): array
    {
        $contentSourceType = $contentSourceSettings['content_source_type'] ?? 'dynamic';

        $context = [
            'is_frontend' => true,
            'content_source_type' => $contentSourceType
        ];

        if ($contentSourceType === 'dynamic') {
            $context = array_merge($context, $this->buildDynamicContext($contentSourceSettings, $pageContext));
        } else {
            // For static content, we might still need some context for widgets
            $context = array_merge($context, $this->buildStaticContext($contentSourceSettings, $pageContext));
        }

        /**
         * Filter the built context before returning.
         *
         * @param array $context Built context array
         * @param array $contentSourceSettings Content source settings
         * @param array $pageContext Current page context
         * @since 2.0.0
         */
        return apply_filters(FilterHooks::ONPAGE_TAG_CONTEXT, $context, $contentSourceSettings, $pageContext);
    }

    /**
     * Build dynamic context with entity relationships.
     *
     * @param array $contentSourceSettings Content source settings
     * @param array $pageContext Current page context
     * @return array Dynamic context
     * @since 2.0.0
     */
    private function buildDynamicContext(array $contentSourceSettings, array $pageContext): array
    {
        $context = [];

        $templateContent = $pageContext['template_content'] ?? '';
        $primaryEntityType = $this->determinePrimaryEntityType($templateContent, $contentSourceSettings);

        switch ($primaryEntityType) {
            case 'post':
                $context = $this->buildPostBasedContext($contentSourceSettings, $pageContext);
                break;
            case 'page':
                $context = $this->buildPageBasedContext($contentSourceSettings, $pageContext);
                break;
            case 'comment':
                $context = $this->buildCommentBasedContext($contentSourceSettings, $pageContext);
                break;
            case 'order':
                $context = $this->buildOrderBasedContext($contentSourceSettings, $pageContext);
                break;
            case 'product':
                $context = $this->buildProductBasedContext($contentSourceSettings, $pageContext);
                break;
            case 'user':
                $context = $this->buildUserBasedContext($contentSourceSettings, $pageContext);
                break;
            default:
                // Check if it's a custom post type
                if ($this->isCustomPostType($primaryEntityType)) {
                    $context = $this->buildCustomPostTypeBasedContext($primaryEntityType, $contentSourceSettings, $pageContext);
                } else {
                    // Mixed or unknown context - try to build comprehensive context
                    $context = $this->buildMixedContext($contentSourceSettings, $pageContext);
                }
                break;
        }

        return $context;
    }

    /**
     * Build order-based context where order is primary and product comes from order.
     *
     * @param array $contentSourceSettings Content source settings
     * @param array $pageContext Current page context
     * @return array Order-based context
     * @since 2.0.0
     */
    private function buildOrderBasedContext(array $contentSourceSettings, array $pageContext): array
    {
        // Check if we also have product restrictions that need to be satisfied
        $hasProductFilters = !empty($contentSourceSettings['product_filters']['conditions']);
        $hasLegacyProductFilters = !empty($contentSourceSettings['product_restriction_type']) && 
                                   $contentSourceSettings['product_restriction_type'] !== 'all';

        // If product restrictions exist, we need to find an order that ALSO satisfies those restrictions
        if ($hasProductFilters || $hasLegacyProductFilters) {
            $order = $this->findOrderWithValidProducts($contentSourceSettings);
        } else {
            // No product restrictions, just get any order matching order restrictions
            $order = $this->contentSourceService->getRandomOrder($contentSourceSettings);
        }

        if (!$order) {
            return $this->buildFallbackContext($contentSourceSettings, $pageContext);
        }

        $context = ['order' => $order];

        // Get product from this specific order (maintaining relationship)
        // AND ensure the product also matches product restrictions
        $orderItems = $order->getItems();
        if (!empty($orderItems)) {
            // Get products from order that match product restrictions
            $validProducts = $this->getValidProductsFromOrder($orderItems, $contentSourceSettings);

            if (!empty($validProducts)) {
                // Select one product deterministically based on order ID
                $orderIdSeed = $order->getId() % count($validProducts);
                $selectedItem = array_values($validProducts)[$orderIdSeed];

                // Store the selected order item for product name consistency with order
                $context['selected_order_item'] = $selectedItem;

                $product = $this->productFetcher->findById($selectedItem->getProductId());
                if ($product) {
                    $context['product'] = $product;
                }
            }
            // Note: Even if no valid products found, we still return the order
            // (product tags will just be empty, but order tags will work)
        }

        $context['user'] = $this->getUserForContext($contentSourceSettings, $pageContext, $order);

        // Add any other supplementary contexts based on detected tags (post, page, etc.)
        return $this->addSupplementaryContext($context, $contentSourceSettings, $pageContext, 'order_based');
    }

    /**
     * Find an order that matches order restrictions AND contains at least one product matching product restrictions.
     *
     * This method gets a pool of orders matching order filters, then iterates through them
     * to find the first order that also contains products matching product restrictions.
     * If the first pool doesn't contain valid orders, it tries additional pools.
     *
     * @param array $contentSourceSettings Content source settings
     * @return mixed Order DTO or null if no valid order found
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function findOrderWithValidProducts(array $contentSourceSettings)
    {
        // Get a pool of orders matching order restrictions
        $orderFilters = $contentSourceSettings['order_filters'] ?? [];
        if (empty($orderFilters)) {
            // Check legacy format
            $orderFilters = $this->buildLegacyOrderFilters($contentSourceSettings);
        }

        // Adaptive pool strategy: Start small, increase if needed
        // This balances performance (when matches are common) with consistency (when matches are rare)
        $poolSizes = [30, 40, 50]; // Progressive pool sizes
        
        foreach ($poolSizes as $poolSize) {
            $ordersPool = $this->orderFetcher->getRandomPool($poolSize, $orderFilters);

            if (empty($ordersPool)) {
                return null;
            }

            // Find the first order that contains at least one product matching product restrictions
            foreach ($ordersPool as $order) {
                $orderItems = $order->getItems();
                if (empty($orderItems)) {
                    continue;
                }

                $validProducts = $this->getValidProductsFromOrder($orderItems, $contentSourceSettings);
                
                if (!empty($validProducts)) {
                    // Found an order with valid products!
                    return $order;
                }
            }
            
            // No valid order in this pool, try next larger pool
        }

        // Tried multiple pools, no valid order found
        return null;
    }

    /**
     * Build legacy order filters from old format content source settings.
     *
     * @param array $contentSourceSettings Content source settings
     * @return array Order filters in new format
     * @since 2.0.0
     */
    private function buildLegacyOrderFilters(array $contentSourceSettings): array
    {
        $restrictionType = $contentSourceSettings['order_restriction_type'] ?? 'all';

        if ($restrictionType === 'all') {
            return [];
        }

        $conditions = [];

        switch ($restrictionType) {
            case 'status':
                if (!empty($contentSourceSettings['order_statuses'])) {
                    $conditions[] = [
                        'type' => 'status',
                        'statuses' => $contentSourceSettings['order_statuses']
                    ];
                }
                break;

            case 'products':
                if (!empty($contentSourceSettings['order_products'])) {
                    $conditions[] = [
                        'type' => 'products',
                        'products' => $contentSourceSettings['order_products']
                    ];
                }
                break;
        }

        if (empty($conditions)) {
            return [];
        }

        return [
            'multiple_filters' => true,
            'logic' => 'AND',
            'conditions' => $conditions
        ];
    }

    /**
     * Get valid products from order items that match product restrictions.
     *
     * This method filters order items to only include products that satisfy
     * the product restriction filters (e.g., on sale, specific categories, etc.)
     *
     * @param array $orderItems Array of order item DTOs
     * @param array $contentSourceSettings Content source settings
     * @return array Array of order items whose products match restrictions
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function getValidProductsFromOrder(array $orderItems, array $contentSourceSettings): array
    {
        $validItems = [];

        foreach ($orderItems as $item) {
            $productId = $item->getProductId();
            $product = $this->productFetcher->findById($productId);

            if ($product && $this->validateProductAgainstRestrictions($product, $contentSourceSettings)) {
                $validItems[] = $item;
            }
        }

        return $validItems;
    }

    /**
     * Check if content source settings are filtering by specific products.
     *
     * @param array $contentSourceSettings Content source settings
     * @return bool True if filtering by products
     * @since 2.0.0
     */
    private function isFilteringByProducts(array $contentSourceSettings): bool
    {
        // Check for multi-filter format
        if (isset($contentSourceSettings['order_filters']) &&
            ($contentSourceSettings['order_filters']['multiple_filters'] ?? false)) {
            $conditions = $contentSourceSettings['order_filters']['conditions'] ?? [];
            foreach ($conditions as $condition) {
                if (($condition['type'] ?? '') === 'products' && !empty($condition['data']['products'])) {
                    return true;
                }
            }
        }

        // Check for legacy format
        if (($contentSourceSettings['order_restriction_type'] ?? '') === 'products' &&
            !empty($contentSourceSettings['order_products'])) {
            return true;
        }

        return false;
    }

    /**
     * Get the filtered product IDs from content source settings.
     *
     * @param array $contentSourceSettings Content source settings
     * @return array Array of product IDs being filtered by
     * @since 2.0.0
     */
    private function getFilteredProductIds(array $contentSourceSettings): array
    {
        $productIds = [];

        // Check for multi-filter format
        if (isset($contentSourceSettings['order_filters']) &&
            ($contentSourceSettings['order_filters']['multiple_filters'] ?? false)) {
            $conditions = $contentSourceSettings['order_filters']['conditions'] ?? [];
            foreach ($conditions as $condition) {
                if (($condition['type'] ?? '') === 'products' && !empty($condition['data']['products'])) {
                    $productIds = array_merge($productIds, $condition['data']['products']);
                }
            }
        }

        // Check for legacy format
        if (($contentSourceSettings['order_restriction_type'] ?? '') === 'products' &&
            !empty($contentSourceSettings['order_products'])) {
            $productIds = array_merge($productIds, $contentSourceSettings['order_products']);
        }

        return array_unique($productIds);
    }

    /**
     * Build product-based context where product is primary.
     *
     * @param array $contentSourceSettings Content source settings
     * @param array $pageContext Current page context
     * @return array Product-based context
     * @since 2.0.0
     */
    private function buildProductBasedContext(array $contentSourceSettings, array $pageContext): array
    {
        $product = $this->getConsistentRandomProduct($contentSourceSettings, $pageContext);

        if (!$product) {
            return $this->buildFallbackContext($contentSourceSettings, $pageContext);
        }

        $context = ['product' => $product];

        // Try to find an order that contains this product AND matches order restrictions
        $order = $this->findOrderContainingProductWithValidation($product->getId(), $contentSourceSettings);
        if ($order) {
            $context['order'] = $order;
        } else {
            // If order tags are required and no valid order found, check if order restrictions exist
            $templateContent = $pageContext['template_content'] ?? '';
            if (\Notifal\Domain\Tags\Services\TagDetector::hasOrderTags($templateContent)) {
                $hasOrderFilters = !empty($contentSourceSettings['order_filters']['conditions']) ||
                                  (!empty($contentSourceSettings['order_restriction_type']) && 
                                   $contentSourceSettings['order_restriction_type'] !== 'all');
                
                if ($hasOrderFilters) {
                    // Order is required but no valid order found, return fallback
                    return $this->buildFallbackContext($contentSourceSettings, $pageContext);
                }
            }
        }

        $context['user'] = $this->getUserForContext($contentSourceSettings, $pageContext, $order ?? null);

        // Add any other supplementary contexts based on detected tags
        return $this->addSupplementaryContext($context, $contentSourceSettings, $pageContext, 'product_based');
    }

    /**
     * Find an order containing the specified product that also matches order restrictions.
     *
     * This method gets a pool of orders containing the specified product,
     * then finds the first one that also matches order restrictions.
     * If the first pool doesn't contain valid orders, it tries additional pools.
     *
     * @param int $productId Product ID to search for
     * @param array $contentSourceSettings Content source settings for order filtering
     * @return mixed Order DTO or null if no valid order found
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function findOrderContainingProductWithValidation(int $productId, array $contentSourceSettings)
    {
        // Check if we have order restrictions
        $hasOrderFilters = !empty($contentSourceSettings['order_filters']['conditions']);
        $hasLegacyFilters = !empty($contentSourceSettings['order_restriction_type']) && 
                           $contentSourceSettings['order_restriction_type'] !== 'all';

        // If no order restrictions, use the simple method
        if (!$hasOrderFilters && !$hasLegacyFilters) {
            return $this->findOrderContainingProduct($productId, $contentSourceSettings);
        }

        // Build filter to get orders containing this product
        $productFilter = [
            'multiple_filters' => true,
            'logic' => 'AND',
            'conditions' => [
                [
                    'type' => 'products',
                    'products' => [$productId]
                ]
            ]
        ];

        // Adaptive pool strategy: Start small, increase if needed
        // This balances performance (when matches are common) with consistency (when matches are rare)
        $poolSizes = [30, 40, 50]; // Progressive pool sizes

        foreach ($poolSizes as $poolSize) {
            // Get a pool of orders containing this product
            $ordersPool = $this->orderFetcher->getRandomPool($poolSize, $productFilter);

            if (empty($ordersPool)) {
                return null;
            }

            // Find the first order that also matches order restrictions
            foreach ($ordersPool as $order) {
                if ($this->validateOrderAgainstRestrictions($order, $contentSourceSettings)) {
                    return $order;
                }
            }
            
            // No valid order in this pool, try next larger pool
        }

        // Tried multiple pools, no valid order found
        return null;
    }

    /**
     * Build user-based context where user is primary.
     *
     * @param array $contentSourceSettings Content source settings
     * @param array $pageContext Current page context
     * @return array User-based context
     * @since 2.0.0
     */
    private function buildUserBasedContext(array $contentSourceSettings, array $pageContext): array
    {
        // Always use current user for user tags (not restricted by content source filters)
        $user = $this->userFetcher->getCurrent();

        if (!$user) {
            return $this->buildFallbackContext($contentSourceSettings, $pageContext);
        }

        $context = ['user' => $user];

        // Add all other supplementary contexts based on detected tags
        // This will add product, order, post, page, comment contexts as needed
        return $this->addSupplementaryContext($context, $contentSourceSettings, $pageContext, 'user_based');
    }

    /**
     * Build post-based context where post is primary.
     *
     * @param array $contentSourceSettings Content source settings
     * @param array $pageContext Current page context
     * @return array Post-based context
     * @since 2.0.0
     */
    private function buildPostBasedContext(array $contentSourceSettings, array $pageContext): array
    {
        $post = $this->contentSourceService->getRandomPost($contentSourceSettings);

        if (!$post) {
            return $this->buildFallbackContext($contentSourceSettings, $pageContext);
        }

        $context = ['post' => $post];

        return $this->addSupplementaryContext($context, $contentSourceSettings, $pageContext, 'post_based');
    }

    /**
     * Build page-based context where page is primary.
     *
     * @param array $contentSourceSettings Content source settings
     * @param array $pageContext Current page context
     * @return array Page-based context
     * @since 2.0.0
     */
    private function buildPageBasedContext(array $contentSourceSettings, array $pageContext): array
    {
        $page = $this->contentSourceService->getRandomPage($contentSourceSettings);

        if (!$page) {
            return $this->buildFallbackContext($contentSourceSettings, $pageContext);
        }

        $context = ['page' => $page];

        return $this->addSupplementaryContext($context, $contentSourceSettings, $pageContext, 'page_based');
    }

    /**
     * Add supplementary context data based on template requirements.
     *
     * @param array $context Current context array
     * @param array $contentSourceSettings Content source settings
     * @param array $pageContext Current page context
     * @param string $entityType The primary entity type (post, page, custom_post_type)
     * @param string|null $postType Custom post type name if applicable
     * @return array Updated context with supplementary data
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function addSupplementaryContext(array $context, array $contentSourceSettings, array $pageContext, string $entityType, ?string $postType = null): array
    {
        $templateContent = $pageContext['template_content'] ?? '';

        // Add user context if user tags are detected
        if (TagDetector::hasUserTags($templateContent) && !isset($context['user'])) {
            $context['user'] = $this->getUserForContext($contentSourceSettings, $pageContext);
        }

        // Add comment context if comment tags are detected (Pro only)
        if (TagDetector::hasCommentTags($templateContent)) {
            // Comment functionality is only available in Notifal Pro
            $filterName = 'notifal_pro_add_comment_context_to_' . $entityType;
            if ($postType) {
                $context = apply_filters($filterName, $context, $contentSourceSettings, $pageContext, $templateContent, $postType);
            } else {
                $context = apply_filters($filterName, $context, $contentSourceSettings, $pageContext, $templateContent);
            }
        }

        // Add product context if product tags are detected
        if (TagDetector::hasProductTags($templateContent) && !isset($context['product'])) {
            // If we already have an order, get product from that order AND validate against product restrictions
            if (isset($context['order'])) {
                $orderItems = $context['order']->getItems();
                if (!empty($orderItems)) {
                    $validProducts = $this->getValidProductsFromOrder($orderItems, $contentSourceSettings);
                    
                    if (!empty($validProducts)) {
                        // Select one product deterministically based on order ID
                        $orderIdSeed = $context['order']->getId() % count($validProducts);
                        $selectedItem = array_values($validProducts)[$orderIdSeed];
                        
                        $context['selected_order_item'] = $selectedItem;
                        
                        $product = $this->productFetcher->findById($selectedItem->getProductId());
                        if ($product) {
                            $context['product'] = $product;
                        }
                    }
                    // If no valid products in the order, try to get a random product
                    if (!isset($context['product'])) {
                        $product = $this->getConsistentRandomProduct($contentSourceSettings, $pageContext);
                        if ($product) {
                            $context['product'] = $product;
                        }
                    }
                }
            } else {
                // No order context, just get a random product matching product restrictions
                $product = $this->getConsistentRandomProduct($contentSourceSettings, $pageContext);
                if ($product) {
                    $context['product'] = $product;
                }
            }
        }

        // Add order context if order tags are detected
        if (TagDetector::hasOrderTags($templateContent) && !isset($context['order'])) {
            // If we already have a product, find an order that contains it AND matches order restrictions
            if (isset($context['product'])) {
                $order = $this->findOrderContainingProductWithValidation($context['product']->getId(), $contentSourceSettings);
            } else {
                $order = $this->contentSourceService->getRandomOrder($contentSourceSettings);
            }

            if ($order) {
                $context['order'] = $order;
                
                // When order is supplementary, also add product from order for consistency
                // AND ensure the product matches product restrictions
                if (!isset($context['product'])) {
                    $orderItems = $order->getItems();
                    if (!empty($orderItems)) {
                        // Get products from order that match product restrictions
                        $validProducts = $this->getValidProductsFromOrder($orderItems, $contentSourceSettings);

                        if (!empty($validProducts)) {
                            // Select one product deterministically based on order ID
                            $orderIdSeed = $order->getId() % count($validProducts);
                            $selectedItem = array_values($validProducts)[$orderIdSeed];

                            $context['selected_order_item'] = $selectedItem;

                            $product = $this->productFetcher->findById($selectedItem->getProductId());
                            if ($product) {
                                $context['product'] = $product;
                            }
                        }
                        // If no valid products found, we still keep the order but without product
                    }
                }
            }
        }

        // Add post context if post tags are detected
        if (TagDetector::hasPostTags($templateContent) && !isset($context['post'])) {
            $post = $this->contentSourceService->getRandomPost($contentSourceSettings);
            if ($post) {
                $context['post'] = $post;
            }
        }

        // Add page context if page tags are detected
        if (TagDetector::hasPageTags($templateContent) && !isset($context['page'])) {
            $page = $this->contentSourceService->getRandomPage($contentSourceSettings);
            if ($page) {
                $context['page'] = $page;
            }
        }

        return $context;
    }

    /**
     * Build comment-based context where comment is primary.
     *
     * @param array $contentSourceSettings Content source settings
     * @param array $pageContext Current page context
     * @return array Comment-based context
     * @since 2.0.0
     */
    private function buildCommentBasedContext(array $contentSourceSettings, array $pageContext): array
    {
        // Comment-based context building is only available in Notifal Pro
        return apply_filters('notifal_pro_build_comment_based_context', $contentSourceSettings, $pageContext) ?: $this->buildFallbackContext($contentSourceSettings, $pageContext);
    }

    /**
     * Build custom post type based context where custom post type is primary.
     *
     * @param string $postType The custom post type name
     * @param array $contentSourceSettings Content source settings
     * @param array $pageContext Current page context
     * @return array Custom post type based context
     * @since 2.0.0
     */
    private function buildCustomPostTypeBasedContext(string $postType, array $contentSourceSettings, array $pageContext): array
    {
        $post = $this->contentSourceService->getRandomCustomPostType($postType, $contentSourceSettings);

        if (!$post) {
            return $this->buildFallbackContext($contentSourceSettings, $pageContext);
        }

        $context = [$postType => $post];

        return $this->addSupplementaryContext($context, $contentSourceSettings, $pageContext, 'custom_post_type', $postType);
    }

    /**
     * Build mixed context when multiple entity types are present.
     *
     * @param array $contentSourceSettings Content source settings
     * @param array $pageContext Current page context
     * @return array Mixed context
     * @since 2.0.0
     */
    private function buildMixedContext(array $contentSourceSettings, array $pageContext): array
    {
        $context = [];

        $templateContent = $pageContext['template_content'] ?? '';

        $context = $this->addWordPressEntities($context, $contentSourceSettings, $templateContent);
        $context = $this->addWooCommerceEntities($context, $contentSourceSettings, $pageContext, $templateContent);

        if (TagDetector::hasUserTags($templateContent)) {
            $user = $this->getUserForContext($contentSourceSettings, $pageContext);
            if ($user) {
                $context['user'] = $user;
            }
        }

        if (empty($context)) {
            return $this->buildFallbackContext($contentSourceSettings, $pageContext);
        }

        return $context;
    }

    /**
     * Add WordPress content entities to context based on template tags.
     *
     * @param array $context Current context array
     * @param array $contentSourceSettings Content source settings
     * @param string $templateContent Template content to analyze
     * @return array Updated context with WordPress entities
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function addWordPressEntities(array $context, array $contentSourceSettings, string $templateContent): array
    {
        if (TagDetector::hasPostTags($templateContent)) {
            $post = $this->contentSourceService->getRandomPost($contentSourceSettings);
            if ($post) {
                $context['post'] = $post;
            }
        }

        if (TagDetector::hasPageTags($templateContent)) {
            $page = $this->contentSourceService->getRandomPage($contentSourceSettings);
            if ($page) {
                $context['page'] = $page;
            }
        }

        if (TagDetector::hasCommentTags($templateContent)) {
            // Comment functionality is only available in Notifal Pro
            $context = apply_filters('notifal_pro_add_comment_context_to_mixed', $context, $contentSourceSettings, [], $templateContent);
        }

        // Check for custom post type tags - RESTRICTED TO NOTIFAL PRO
        if (function_exists('is_notifal_pro_active') && is_notifal_pro_active()) {
            $customPostTypes = $this->detectCustomPostTypeTags($templateContent);
            foreach ($customPostTypes as $postType) {
                $post = $this->contentSourceService->getRandomCustomPostType($postType, $contentSourceSettings);
                if ($post) {
                    $context[$postType] = $post;
                }
            }
        }

        return $context;
    }

    /**
     * Add WooCommerce entities to context based on template tags.
     *
     * @param array $context Current context array
     * @param array $contentSourceSettings Content source settings
     * @param array $pageContext Current page context
     * @param string $templateContent Template content to analyze
     * @return array Updated context with WooCommerce entities
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function addWooCommerceEntities(array $context, array $contentSourceSettings, array $pageContext, string $templateContent): array
    {
        if (TagDetector::hasProductTags($templateContent)) {
            $product = $this->getConsistentRandomProduct($contentSourceSettings, $pageContext);
            if ($product) {
                $context['product'] = $product;
            }
        }

        if (TagDetector::hasOrderTags($templateContent)) {
            $order = $this->contentSourceService->getRandomOrder($contentSourceSettings);
            if ($order) {
                $context['order'] = $order;
            }
        }

        return $context;
    }

    /**
     * Build static context for non-dynamic content.
     *
     * @param array $contentSourceSettings Content source settings
     * @param array $pageContext Current page context
     * @return array Static context
     * @since 2.0.0
     */
    private function buildStaticContext(array $contentSourceSettings, array $pageContext): array
    {
        // Even for static content, widgets might need some context
        // Use minimal context with current page data if available
        return [
            'user' => $this->userFetcher->getCurrent(),
            'product' => $pageContext['current_product'] ?? null,
            'order' => $pageContext['current_order'] ?? null,
        ];
    }

    /**
     * Build fallback context when content source filtering fails.
     *
     * @param array $contentSourceSettings Content source settings
     * @param array $pageContext Current page context
     * @return array Fallback context
     * @since 2.0.0
     */
    private function buildFallbackContext(array $contentSourceSettings, array $pageContext): array
    {
        $hasOrderFilters = !empty($contentSourceSettings['order_filters']) &&
                          !empty($contentSourceSettings['order_filters']['conditions']);
        $hasProductFilters = !empty($contentSourceSettings['product_filters']) &&
                            !empty($contentSourceSettings['product_filters']['conditions']);
        $hasUserFilters = !empty($contentSourceSettings['user_filters']) &&
                         !empty($contentSourceSettings['user_filters']['conditions']);

        $hasLegacyOrderFilters = ($contentSourceSettings['order_restriction_type'] ?? 'all') !== 'all';
        $hasLegacyProductFilters = ($contentSourceSettings['product_restriction_type'] ?? 'all') !== 'all';
        $hasLegacyUserFilters = ($contentSourceSettings['user_restriction_type'] ?? 'all') !== 'all';

        // If any filters are active and no data was found, return empty context
        if ($hasOrderFilters || $hasProductFilters || $hasUserFilters ||
            $hasLegacyOrderFilters || $hasLegacyProductFilters || $hasLegacyUserFilters) {

            return [
                'no_matching_data' => true,
                'applied_filters' => [
                    'order_filters' => $hasOrderFilters || $hasLegacyOrderFilters,
                    'product_filters' => $hasProductFilters || $hasLegacyProductFilters,
                    'user_filters' => $hasUserFilters || $hasLegacyUserFilters
                ]
            ];
        }

        // Fallback to any available data without restrictions
        return [
            'product' => $this->getConsistentRandomProduct($contentSourceSettings, $pageContext),
            'order' => $this->orderFetcher->getRandom(),
            'user' => $this->userFetcher->getCurrent() ?: $this->userFetcher->getRandom(),
            'is_fallback' => true
        ];
    }

    /**
     * Determine the primary entity type based on template content analysis.
     *
     * @param string $templateContent Template content to analyze
     * @param array $contentSourceSettings Content source settings
     * @return string Primary entity type (order|product|user|mixed)
     * @since 2.0.0
     */
    private function determinePrimaryEntityType(string $templateContent, array $contentSourceSettings): string
    {
        $tagCounts = TagDetector::getAllTagCounts($templateContent);
        $orderTagCount = $tagCounts['order'];
        $productTagCount = $tagCounts['product'];
        $userTagCount = $tagCounts['user'];
        $postTagCount = $tagCounts['post'];
        $pageTagCount = $tagCounts['page'];
        $commentTagCount = $tagCounts['comment'];


        $customPostTypeTags = $this->detectCustomPostTypeTags($templateContent);

        $hasOrderRestrictions = !empty($contentSourceSettings['order_restriction_type']) &&
                              $contentSourceSettings['order_restriction_type'] !== 'all';
        $hasProductRestrictions = !empty($contentSourceSettings['product_restriction_type']) &&
                                $contentSourceSettings['product_restriction_type'] !== 'all';
        $hasUserRestrictions = !empty($contentSourceSettings['user_restriction_type']) &&
                             $contentSourceSettings['user_restriction_type'] !== 'all';
        $hasPostRestrictions = !empty($contentSourceSettings['post_restriction_type']) &&
                             $contentSourceSettings['post_restriction_type'] !== 'all';
        $hasPageRestrictions = !empty($contentSourceSettings['page_restriction_type']) &&
                             $contentSourceSettings['page_restriction_type'] !== 'all';
        $hasCommentRestrictions = !empty($contentSourceSettings['comment_restriction_type']) &&
                                $contentSourceSettings['comment_restriction_type'] !== 'all';

        // Priority logic: post → page → comment → custom post type → order → product → user
        
        if ($postTagCount > 0) {
            return 'post';
        }

        if ($pageTagCount > 0) {
            return 'page';
        }

        if ($commentTagCount > 0 && function_exists('is_notifal_pro_active') && is_notifal_pro_active()) {
            return 'comment';
        }

        // Check for custom post type tags - return the first detected CPT
        if (!empty($customPostTypeTags)) {
            return $customPostTypeTags[0];
        }

        // WooCommerce content priority
        if ($orderTagCount > 0) {
            return 'order';
        }

        // If only product tags (no order tags), use product context
        if ($productTagCount > 0) {
            return 'product';
        }

        // Primary entity is the one with most tags AND restrictions
        $scores = [
            'post' => $postTagCount + ($hasPostRestrictions ? 10 : 0),
            'page' => $pageTagCount + ($hasPageRestrictions ? 10 : 0),
            'comment' => $commentTagCount + ((function_exists('is_notifal_pro_active') && is_notifal_pro_active() && $hasCommentRestrictions) ? 10 : 0),
            'order' => $orderTagCount + ($hasOrderRestrictions ? 10 : 0),
            'product' => $productTagCount + ($hasProductRestrictions ? 10 : 0),
            'user' => $userTagCount + ($hasUserRestrictions ? 10 : 0)
        ];

        // Add custom post type scores
        foreach ($customPostTypeTags as $postType) {
            $restrictionKey = $postType . '_restriction_type';
            $hasCustomRestrictions = !empty($contentSourceSettings[$restrictionKey]) && 
                                   $contentSourceSettings[$restrictionKey] !== 'all';
            $scores[$postType] = 1 + ($hasCustomRestrictions ? 10 : 0);
        }

        $maxScore = max($scores);
        if ($maxScore === 0) {
            return 'mixed';
        }

        // Return the entity type with highest score
        $primaryType = array_search($maxScore, $scores, true);
        return $primaryType;
    }

    /**
     * Get appropriate user for the context.
     * Always prioritizes current logged-in user for user tags.
     *
     * @param array $contentSourceSettings Content source settings
     * @param array $pageContext Current page context
     * @param mixed $order Order object if available
     * @return mixed User object or null
     * @since 2.0.0
     */
    private function getUserForContext(array $contentSourceSettings, array $pageContext, $order = null)
    {
        // Always prioritize current user for user tags
        $currentUser = $this->userFetcher->getCurrent();
        if ($currentUser) {
            return $currentUser;
        }

        // Fallback to filtered user only if no current user
        $user = $this->contentSourceService->getRandomUser($contentSourceSettings);
        if ($user) {
            return $user;
        }

        // Final fallback to random user
        return $this->userFetcher->getRandom();
    }

    /**
     * Find an order that contains the specified product.
     *
     * @param int $productId Product ID to search for
     * @param array $contentSourceSettings Content source settings for order filtering
     * @return mixed Order object or null
     * @since 2.0.0
     */
    private function findOrderContainingProduct(int $productId, array $contentSourceSettings)
    {
        // Build order filters that include the specific product
        $orderSettings = $contentSourceSettings;
        $orderSettings['order_restriction_type'] = 'products';
        $orderSettings['order_products'] = [$productId];

        return $this->contentSourceService->getRandomOrder($orderSettings);
    }

    /**
     * Get a consistent product using the SAME mechanism as order context.
     *
     * Instead of using random pools (which can vary), this method
     * uses the ContentSourceService ONCE to get a single product, then caches it
     * for the entire request, just like order context does with findById().
     *
     * @param array $contentSourceSettings Content source settings
     * @param array $pageContext Current page context
     * @return mixed Product object or null
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function getConsistentRandomProduct(array $contentSourceSettings, array $pageContext)
    {
        $templateId = $pageContext['template_id'] ?? 0;
        if (!$templateId && isset($pageContext['notification_id'])) {
            $templateId = $pageContext['notification_id'];
        }

        $requestId = $pageContext['request_id'] ?? ($_SERVER['REQUEST_TIME_FLOAT'] ?? $_SERVER['REQUEST_TIME'] ?? microtime(true));

        $cacheKey = 'notifal_product_context_' . md5(serialize([
            'template_id' => $templateId,
            'request_id' => floor($requestId),
            'content_source_settings' => $contentSourceSettings
        ]));

        static $requestCache = [];
        if (isset($requestCache[$cacheKey])) {
            return $requestCache[$cacheKey];
        }

        $product = $this->getDeterministicProductFromPool($contentSourceSettings, $cacheKey);

        $requestCache[$cacheKey] = $product;

        return $product;
    }



    /**
     * Get a deterministic product from pool using the SAME logic as order context.
     *
     * This replicates how order context uses deterministic selection based on order ID,
     * but for product context using a deterministic seed.
     *
     * @param array $contentSourceSettings Content source settings
     * @param string $cacheKey Cache key to use as seed
     * @return mixed Product object or null
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function getDeterministicProductFromPool(array $contentSourceSettings, string $cacheKey)
    {
        $productPool = $this->contentSourceService->getProductPool($contentSourceSettings);

        if (empty($productPool)) {
            return null;
        }

        $deterministicIndex = crc32($cacheKey) % count($productPool);
        return $productPool[$deterministicIndex];
    }


    /**
     * Detect custom post type tags in template content.
     *
     * @param string $templateContent Template content to analyze
     * @return array Array of detected custom post type names
     * @since 2.0.0
     */
    private function detectCustomPostTypeTags(string $templateContent): array
    {
        $customPostTypes = [];
        
        // Get all registered public post types
        $postTypes = get_post_types(['public' => true], 'names');
        
        // Remove WordPress built-in types and WooCommerce types that we handle separately
        $excludedTypes = ['post', 'page', 'attachment', 'product', 'product_variation', 'shop_order', 'shop_coupon'];
        $postTypes = array_diff($postTypes, $excludedTypes);
        
        foreach ($postTypes as $postType) {
            // Look for tags like {custom_post_type_title}, {custom_post_type_content}, etc.
            $pattern = '/\{' . preg_quote($postType, '/') . '_[a-zA-Z_]+\}/i';
            if (preg_match($pattern, $templateContent)) {
                $customPostTypes[] = $postType;
            }
        }
        
        return array_unique($customPostTypes);
    }

    /**
     * Check if a given entity type is a custom post type.
     *
     * @param string $entityType Entity type to check
     * @return bool True if it's a custom post type, false otherwise
     * @since 2.0.0
     */
    private function isCustomPostType(string $entityType): bool
    {
        // Check if post type exists and is public
        if (!post_type_exists($entityType)) {
            return false;
        }
        
        $postTypeObject = get_post_type_object($entityType);
        
        // Ensure it's public or publicly queryable
        if (!$postTypeObject || (!$postTypeObject->public && !$postTypeObject->publicly_queryable)) {
            return false;
        }
        
        // Exclude WordPress built-in types and WooCommerce types
        $excludedTypes = ['post', 'page', 'attachment', 'revision', 'nav_menu_item', 'product', 'product_variation', 'shop_order', 'shop_coupon'];
        
        return !in_array($entityType, $excludedTypes);
    }

    /**
     * Validate if a product matches the product restrictions from content source settings.
     *
     * This ensures that when products are used in combination with other entity types
     * (e.g., order + product), the product also satisfies its own restriction filters.
     *
     * @param mixed $product Product DTO to validate
     * @param array $contentSourceSettings Content source settings
     * @return bool True if product matches restrictions, false otherwise
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function validateProductAgainstRestrictions($product, array $contentSourceSettings): bool
    {
        if (!$product || !method_exists($product, 'getId')) {
            return false;
        }

        $productId = $product->getId();

        // Check if product restrictions are configured
        $hasProductFilters = !empty($contentSourceSettings['product_filters']['conditions']);
        $hasLegacyFilters = !empty($contentSourceSettings['product_restriction_type']) && 
                           $contentSourceSettings['product_restriction_type'] !== 'all';

        // If no product restrictions are configured, product is valid
        if (!$hasProductFilters && !$hasLegacyFilters) {
            return true;
        }

        // Get the actual WooCommerce product object for validation (with caching)
        $wcProduct = $this->getCachedWcProduct($productId);
        if (!$wcProduct) {
            return false;
        }

        // Validate against multi-filter format
        if ($hasProductFilters) {
            $conditions = $contentSourceSettings['product_filters']['conditions'] ?? [];
            $logic = strtoupper($contentSourceSettings['product_filters']['logic'] ?? 'AND');

            if ($logic === 'OR') {
                // For OR logic, product must match at least one condition
                foreach ($conditions as $condition) {
                    if ($this->validateProductAgainstCondition($wcProduct, $condition)) {
                        return true;
                    }
                }
                return false;
            } else {
                // For AND logic, product must match all conditions
                foreach ($conditions as $condition) {
                    if (!$this->validateProductAgainstCondition($wcProduct, $condition)) {
                        return false;
                    }
                }
                return true;
            }
        }

        // Validate against legacy format
        return $this->validateProductAgainstLegacyRestrictions($wcProduct, $contentSourceSettings);
    }

    /**
     * Get WooCommerce product with caching to reduce database calls.
     *
     * @param int $productId Product ID
     * @return \WC_Product|null WooCommerce product object or null
     * @since 2.0.0
     */
    private function getCachedWcProduct(int $productId)
    {
        if (!isset($this->productCache[$productId])) {
            $this->productCache[$productId] = wc_get_product($productId);
        }
        return $this->productCache[$productId];
    }

    /**
     * Validate if a product matches a specific condition.
     *
     * @param \WC_Product $wcProduct WooCommerce product object
     * @param array $condition Single condition to validate
     * @return bool True if product matches condition
     * @since 2.0.0
     */
    private function validateProductAgainstCondition($wcProduct, array $condition): bool
    {
        $type = $condition['type'] ?? '';
        $data = $condition['data'] ?? [];

        switch ($type) {
            case 'all':
                return true;

            case 'categories':
                $categoryIds = $data['categories'] ?? [];
                if (empty($categoryIds)) {
                    return true;
                }
                $productCategories = wp_get_post_terms($wcProduct->get_id(), 'product_cat', ['fields' => 'ids']);
                return !empty(array_intersect($categoryIds, $productCategories));

            case 'specific':
                $specificProducts = $data['products'] ?? [];
                if (empty($specificProducts)) {
                    return true;
                }
                // Check both the product ID and parent ID (for variations)
                $productId = $wcProduct->get_id();
                $parentId = $wcProduct->get_parent_id();
                return in_array($productId, $specificProducts) || 
                       ($parentId > 0 && in_array($parentId, $specificProducts));

            case 'sale':
                return $wcProduct->is_on_sale();

            case 'featured':
                return $wcProduct->is_featured();

            case 'date_range':
                return $this->validateProductDateRange($wcProduct, $data);

            case 'custom_meta':
                return $this->validateProductCustomMeta($wcProduct, $data);

            default:
                return true;
        }
    }

    /**
     * Validate product against date range condition.
     *
     * @param \WC_Product $wcProduct WooCommerce product object
     * @param array $data Date range condition data
     * @return bool True if product matches date range
     * @since 2.0.0
     */
    private function validateProductDateRange($wcProduct, array $data): bool
    {
        $dateType = $data['date_type'] ?? 'publish';
        $range = $data['range'] ?? '';

        if (empty($range)) {
            return true;
        }

        $productDate = null;
        if ($dateType === 'modified') {
            $productDate = $wcProduct->get_date_modified();
        } else {
            $productDate = $wcProduct->get_date_created();
        }

        if (!$productDate) {
            return false;
        }

        $now = new \DateTime();
        $productDateTime = $productDate->getTimestamp();

        switch ($range) {
            case 'last_24h':
                $startDate = clone $now;
                $startDate->modify('-24 hours');
                return $productDateTime >= $startDate->getTimestamp();

            case 'last_7d':
                $startDate = clone $now;
                $startDate->modify('-7 days');
                return $productDateTime >= $startDate->getTimestamp();

            case 'last_30d':
                $startDate = clone $now;
                $startDate->modify('-30 days');
                return $productDateTime >= $startDate->getTimestamp();

            case 'last_90d':
                $startDate = clone $now;
                $startDate->modify('-90 days');
                return $productDateTime >= $startDate->getTimestamp();

            case 'custom':
                $startDateStr = $data['start_date'] ?? '';
                $endDateStr = $data['end_date'] ?? '';
                
                if (!empty($startDateStr)) {
                    $startDate = new \DateTime($startDateStr . ' 00:00:00');
                    if ($productDateTime < $startDate->getTimestamp()) {
                        return false;
                    }
                }
                
                if (!empty($endDateStr)) {
                    $endDate = new \DateTime($endDateStr . ' 23:59:59');
                    if ($productDateTime > $endDate->getTimestamp()) {
                        return false;
                    }
                }
                
                return true;

            default:
                return true;
        }
    }

    /**
     * Validate product against custom meta condition.
     *
     * @param \WC_Product $wcProduct WooCommerce product object
     * @param array $data Custom meta condition data
     * @return bool True if product matches custom meta
     * @since 2.0.0
     */
    private function validateProductCustomMeta($wcProduct, array $data): bool
    {
        $customFilter = $data['custom_filter'] ?? '';
        if (empty($customFilter)) {
            return true;
        }

        // Parse and evaluate the custom filter using FilterHelper
        return \Notifal\Shared\Utils\FilterHelper::evaluateCustomFilterForObject($customFilter, $wcProduct->get_id(), 'product');
    }

    /**
     * Validate product against legacy restriction format.
     *
     * @param \WC_Product $wcProduct WooCommerce product object
     * @param array $contentSourceSettings Content source settings
     * @return bool True if product matches legacy restrictions
     * @since 2.0.0
     */
    private function validateProductAgainstLegacyRestrictions($wcProduct, array $contentSourceSettings): bool
    {
        $restrictionType = $contentSourceSettings['product_restriction_type'] ?? 'all';

        switch ($restrictionType) {
            case 'all':
                return true;

            case 'categories':
                $categoryIds = $contentSourceSettings['product_categories'] ?? [];
                if (empty($categoryIds)) {
                    return true;
                }
                $productCategories = wp_get_post_terms($wcProduct->get_id(), 'product_cat', ['fields' => 'ids']);
                return !empty(array_intersect($categoryIds, $productCategories));

            case 'specific':
                $specificProducts = $contentSourceSettings['specific_products'] ?? [];
                if (empty($specificProducts)) {
                    return true;
                }
                $productId = $wcProduct->get_id();
                $parentId = $wcProduct->get_parent_id();
                return in_array($productId, $specificProducts) || 
                       ($parentId > 0 && in_array($parentId, $specificProducts));

            case 'sale':
                return $wcProduct->is_on_sale();

            case 'featured':
                return $wcProduct->is_featured();

            default:
                return true;
        }
    }

    /**
     * Validate if an order matches the order restrictions from content source settings.
     *
     * This ensures that when orders are used in combination with other entity types
     * (e.g., product + order), the order also satisfies its own restriction filters.
     *
     * @param mixed $order Order DTO to validate
     * @param array $contentSourceSettings Content source settings
     * @return bool True if order matches restrictions, false otherwise
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function validateOrderAgainstRestrictions($order, array $contentSourceSettings): bool
    {
        if (!$order || !method_exists($order, 'getId')) {
            return false;
        }

        $orderId = $order->getId();

        // Check if order restrictions are configured
        $hasOrderFilters = !empty($contentSourceSettings['order_filters']['conditions']);
        $hasLegacyFilters = !empty($contentSourceSettings['order_restriction_type']) && 
                           $contentSourceSettings['order_restriction_type'] !== 'all';

        // If no order restrictions are configured, order is valid
        if (!$hasOrderFilters && !$hasLegacyFilters) {
            return true;
        }

        // Get the actual WooCommerce order object for validation
        $wcOrder = wc_get_order($orderId);
        if (!$wcOrder) {
            return false;
        }

        // Validate against multi-filter format
        if ($hasOrderFilters) {
            $conditions = $contentSourceSettings['order_filters']['conditions'] ?? [];
            $logic = strtoupper($contentSourceSettings['order_filters']['logic'] ?? 'AND');

            if ($logic === 'OR') {
                // For OR logic, order must match at least one condition
                foreach ($conditions as $condition) {
                    if ($this->validateOrderAgainstCondition($wcOrder, $condition)) {
                        return true;
                    }
                }
                return false;
            } else {
                // For AND logic, order must match all conditions
                foreach ($conditions as $condition) {
                    if (!$this->validateOrderAgainstCondition($wcOrder, $condition)) {
                        return false;
                    }
                }
                return true;
            }
        }

        // Validate against legacy format
        return $this->validateOrderAgainstLegacyRestrictions($wcOrder, $contentSourceSettings);
    }

    /**
     * Validate if an order matches a specific condition.
     *
     * @param \WC_Order $wcOrder WooCommerce order object
     * @param array $condition Single condition to validate
     * @return bool True if order matches condition
     * @since 2.0.0
     */
    private function validateOrderAgainstCondition($wcOrder, array $condition): bool
    {
        $type = $condition['type'] ?? '';
        $data = $condition['data'] ?? [];

        switch ($type) {
            case 'all':
                return true;

            case 'status':
                $statuses = $data['statuses'] ?? [];
                if (empty($statuses)) {
                    return true;
                }
                $orderStatus = $wcOrder->get_status();
                return in_array($orderStatus, $statuses);

            case 'date_range':
                return $this->validateOrderDateRange($wcOrder, $data);

            case 'products':
                $productIds = $data['products'] ?? [];
                if (empty($productIds)) {
                    return true;
                }
                return $this->orderContainsProducts($wcOrder, $productIds);

            case 'custom_filter':
                return $this->validateOrderCustomFilter($wcOrder, $data);

            default:
                return true;
        }
    }

    /**
     * Validate order against date range condition.
     *
     * @param \WC_Order $wcOrder WooCommerce order object
     * @param array $data Date range condition data
     * @return bool True if order matches date range
     * @since 2.0.0
     */
    private function validateOrderDateRange($wcOrder, array $data): bool
    {
        $range = $data['range'] ?? '';
        if (empty($range)) {
            return true;
        }

        $orderDate = $wcOrder->get_date_created();
        if (!$orderDate) {
            return false;
        }

        $now = new \DateTime();
        $orderDateTime = $orderDate->getTimestamp();

        switch ($range) {
            case 'last_24h':
                $startDate = clone $now;
                $startDate->modify('-24 hours');
                return $orderDateTime >= $startDate->getTimestamp();

            case 'last_7d':
                $startDate = clone $now;
                $startDate->modify('-7 days');
                return $orderDateTime >= $startDate->getTimestamp();

            case 'last_30d':
                $startDate = clone $now;
                $startDate->modify('-30 days');
                return $orderDateTime >= $startDate->getTimestamp();

            case 'last_90d':
                $startDate = clone $now;
                $startDate->modify('-90 days');
                return $orderDateTime >= $startDate->getTimestamp();

            case 'custom':
                $startDateStr = $data['start_date'] ?? '';
                $endDateStr = $data['end_date'] ?? '';
                
                if (!empty($startDateStr)) {
                    $startDate = new \DateTime($startDateStr . ' 00:00:00');
                    if ($orderDateTime < $startDate->getTimestamp()) {
                        return false;
                    }
                }
                
                if (!empty($endDateStr)) {
                    $endDate = new \DateTime($endDateStr . ' 23:59:59');
                    if ($orderDateTime > $endDate->getTimestamp()) {
                        return false;
                    }
                }
                
                return true;

            default:
                return true;
        }
    }

    /**
     * Check if an order contains any of the specified products.
     *
     * @param \WC_Order $wcOrder WooCommerce order object
     * @param array $productIds Array of product IDs to check
     * @return bool True if order contains at least one of the products
     * @since 2.0.0
     */
    private function orderContainsProducts($wcOrder, array $productIds): bool
    {
        if (empty($productIds)) {
            return true;
        }

        $orderItems = $wcOrder->get_items();
        foreach ($orderItems as $item) {
            /** @var \WC_Order_Item_Product $item */
            $itemProductId = $item->get_product_id();
            $itemVariationId = $item->get_variation_id();
            
            if (in_array($itemProductId, $productIds) || 
                ($itemVariationId > 0 && in_array($itemVariationId, $productIds))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate order against custom filter condition.
     *
     * @param \WC_Order $wcOrder WooCommerce order object
     * @param array $data Custom filter condition data
     * @return bool True if order matches custom filter
     * @since 2.0.0
     */
    private function validateOrderCustomFilter($wcOrder, array $data): bool
    {
        $customFilter = $data['custom_filter'] ?? '';
        if (empty($customFilter)) {
            return true;
        }

        // Parse and evaluate the custom filter using FilterHelper
        return \Notifal\Shared\Utils\FilterHelper::evaluateCustomFilterForObject($customFilter, $wcOrder->get_id(), 'shop_order');
    }

    /**
     * Validate order against legacy restriction format.
     *
     * @param \WC_Order $wcOrder WooCommerce order object
     * @param array $contentSourceSettings Content source settings
     * @return bool True if order matches legacy restrictions
     * @since 2.0.0
     */
    private function validateOrderAgainstLegacyRestrictions($wcOrder, array $contentSourceSettings): bool
    {
        $restrictionType = $contentSourceSettings['order_restriction_type'] ?? 'all';

        switch ($restrictionType) {
            case 'all':
                return true;

            case 'status':
                $statuses = $contentSourceSettings['order_statuses'] ?? [];
                if (empty($statuses)) {
                    return true;
                }
                $orderStatus = $wcOrder->get_status();
                return in_array($orderStatus, $statuses);

            case 'products':
                $productIds = $contentSourceSettings['order_products'] ?? [];
                if (empty($productIds)) {
                    return true;
                }
                return $this->orderContainsProducts($wcOrder, $productIds);

            default:
                return true;
        }
    }
}

