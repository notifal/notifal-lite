<?php

namespace Notifal\Modules\Templates\Application\Services;

use Notifal\Domain\Products\DTO\ProductDTO;
use Notifal\Modules\Templates\Application\Services\ProductImageResolver;
use Notifal\Infrastructure\WordPress\Admin\Settings\Services\PostTypeDiscoveryService;
use Notifal\Core\Foundation\Container;
use Notifal\Domain\Orders\OrderFetcherInterface;
use Notifal\Domain\Products\ProductFetcherInterface;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;

defined('ABSPATH') || exit;

/**
 * Class FeaturedImageResolver
 *
 * Resolves featured images based on notification context.
 * Handles different entity types: products, orders, posts, pages, comments, custom post types.
 *
 * @package Notifal\Modules\Templates\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FeaturedImageResolver
{

    /**
     * Get featured image HTML based on context.
     *
     * @param array|null $context Widget context from WidgetContextProvider
     * @param string $size Image size (default: 'large')
     * @param array $attributes Additional HTML attributes
     * @param string $source Preferred source ('auto', 'post', 'page', 'product', 'order', 'comment')
     * @return string Image HTML
     * @since 2.0.0
     */
    public static function getFeaturedImageHtml(?array $context, string $size = 'large', array $attributes = [], string $source = 'auto'): string
    {
        try {
            // When user has explicitly selected a source, respect it (do not override with comment)
            if ($source !== 'auto') {
                // When source is 'product' and we have order context, use order image for consistency with order notifications
                if ($source === 'product' && isset($context['order']) && $context['order']) {
                    return self::getOrderContextImageHtml($context['order'], $size, $attributes, $context);
                }
                
                if (isset($context[$source]) && $context[$source]) {
                     switch ($source) {
                         case 'comment':
                             return self::getCommentContextImageHtml($context['comment'], $size, $attributes);
                         case 'post':
                             // Check if post actually has a featured image
                             if (self::hasPostFeaturedImage($context['post'])) {
                                 return self::getPostImageHtml($context['post'], $size, $attributes);
                             }
                             return self::getPlaceholderImageHtml($size, $attributes);
                         case 'page':
                             // Check if page actually has a featured image
                             if (self::hasPostFeaturedImage($context['page'])) {
                                 return self::getPageImageHtml($context['page'], $size, $attributes);
                             }
                             return self::getPlaceholderImageHtml($size, $attributes);
                         case 'product':
                             return self::getProductImageHtml($context['product'], $size, $attributes);
                         case 'order':
                             return self::getOrderContextImageHtml($context['order'], $size, $attributes, $context);
                     }
                } else {
                   // Comment-only templates may not include post/page/product keys — use the
                   // featured image of the post the active comment belongs to instead.
                   if (isset($context['comment']) && $context['comment']) {
                       return self::getCommentContextImageHtml($context['comment'], $size, $attributes);
                   }

                   // If selected source not found, try any available custom post types before fallback
                   $customPostTypeImage = self::getCustomPostTypeImageHtml($context, $size, $attributes);
                   if (!empty($customPostTypeImage)) {
                       return $customPostTypeImage;
                   }

                   // If selected source is not available in context, return placeholder
                   return self::getPlaceholderImageHtml($size, $attributes);
                }
            }

             // Check for any custom post types before built-in types
             $customPostTypeImage = self::getCustomPostTypeImageHtml($context, $size, $attributes);
             if (!empty($customPostTypeImage)) {
                 return $customPostTypeImage;
             }

             // Default priority order: comment → post → page → order → product
             // Comment comes first to ensure comment notifications show the featured image of the commented post
             // Order comes before product to ensure order notifications show the correct product image
             if (isset($context['comment']) && $context['comment']) {
                 return self::getCommentContextImageHtml($context['comment'], $size, $attributes);
             }

             if (isset($context['post']) && $context['post']) {
                 return self::getPostImageHtml($context['post'], $size, $attributes);
             }

             if (isset($context['page']) && $context['page']) {
                 return self::getPageImageHtml($context['page'], $size, $attributes);
             }

             // Priority: Order context before product to ensure consistency
             // When order exists, the product in context is from that specific order
             if (isset($context['order']) && $context['order']) {
                 return self::getOrderContextImageHtml($context['order'], $size, $attributes, $context);
             }

             if (isset($context['product']) && $context['product']) {
                 return self::getProductImageHtml($context['product'], $size, $attributes);
             }

            // Fallback to placeholder
            return self::getPlaceholderImageHtml($size, $attributes);
        } catch (\Exception $e) {
            // Silently return placeholder for production stability
            return self::getPlaceholderImageHtml($size, $attributes);
        }
    }

    /**
     * Get image for comment context.
     * Comments → Featured image of the commented post/page/custom post type.
     *
     * @param mixed $comment Comment object or comment data
     * @param string $size Image size (default: 'large')
     * @param array $attributes Additional HTML attributes
     * @return string Image HTML
     * @since 2.0.0
     */
    private static function getCommentContextImageHtml($comment, string $size, array $attributes): string
    {
        try {
            // Resolve the parent post ID from the comment entity in context
            $postId = self::getCommentPostId($comment);
            if ($postId <= 0) {
                return self::getPlaceholderImageHtml($size, $attributes);
            }

            // Load the post/page/product the comment was left on
            $post = get_post($postId);

            if (!$post) {
                return self::getPlaceholderImageHtml($size, $attributes);
            }

            // Check if this is a product (WooCommerce)
            if ($post->post_type === 'product' && PluginDetector::isWooCommerceActive()) {
                try {
                    // Convert to ProductDTO and use existing product image logic
                    $productFetcher = notifal_app(ProductFetcherInterface::class);
                    $product = $productFetcher->findById($postId);

                    if ($product) {
                        return ProductImageResolver::getProductImageHtml($product, $size, $attributes);
                    }
                } catch (\Exception $e) {
                    // Fallback to post type image if product fetching fails
                    return self::getPostTypeImageHtml($post, $size, $attributes);
                }
            }

            // For regular posts, pages, or custom post types
            return self::getPostTypeImageHtml($post, $size, $attributes);
        } catch (\Exception $e) {
            return self::getPlaceholderImageHtml($size, $attributes);
        }
    }

    /**
     * Get image for post context.
     *
     * @param mixed $post Post object or post data
     * @param string $size Image size (default: 'large')
     * @param array $attributes Additional HTML attributes
     * @return string Image HTML
     * @since 2.0.0
     */
    private static function getPostImageHtml($post, string $size, array $attributes): string
    {
        return self::getPostTypeImageHtml($post, $size, $attributes);
    }

    /**
     * Get image for page context.
     *
     * @param mixed $page Page object or page data
     * @param string $size Image size (default: 'large')
     * @param array $attributes Additional HTML attributes
     * @return string Image HTML
     * @since 2.0.0
     */
    private static function getPageImageHtml($page, string $size, array $attributes): string
    {
        return self::getPostTypeImageHtml($page, $size, $attributes);
    }

    /**
     * Get image for any WordPress post type (posts, pages, custom post types).
     *
     * @param mixed $post Post object or post data
     * @param string $size Image size (default: 'large')
     * @param array $attributes Additional HTML attributes
     * @return string Image HTML
     * @since 2.0.0
     */
    private static function getPostTypeImageHtml($post, string $size, array $attributes): string
    {
        try {
            // Check if post object is valid
            if (!$post || !isset($post->ID)) {
                return self::getPlaceholderImageHtml($size, $attributes);
            }

            $imageId = get_post_thumbnail_id($post->ID);

            if (!$imageId) {
                return self::getPlaceholderImageHtml($size, $attributes);
            }

            $defaultAttributes = [
                'alt' => isset($post->post_title) ? esc_attr($post->post_title) : '',
                'loading' => 'lazy'
            ];

            $attributes = array_merge($defaultAttributes, $attributes);

            return wp_get_attachment_image($imageId, $size, false, $attributes);
        } catch (\Exception $e) {
            return self::getPlaceholderImageHtml($size, $attributes);
        }
    }

    /**
     * Get image for custom post type context.
     *
     * @param array|null $context Widget context from WidgetContextProvider
     * @param string $size Image size (default: 'large')
     * @param array $attributes Additional HTML attributes
     * @return string Image HTML
     * @since 2.0.0
     */
    private static function getCustomPostTypeImageHtml(?array $context, string $size, array $attributes): string
    {
        if (!$context) return '';

        // Check for any custom post type in context (excluding built-in and WooCommerce types)
        $postTypeDiscoveryService = Container::getInstance()->get(PostTypeDiscoveryService::class);
        $customPostTypes = $postTypeDiscoveryService->getFilteredCustomPostTypeNames();


        foreach ($customPostTypes as $postType) {
            if (isset($context[$postType])) {
                $post = $context[$postType];
                
                
                return self::getPostTypeImageHtml($context[$postType], $size, $attributes);
            }
        }

        return '';
    }

    /**
     * Get image for order context.
     * Orders → Product image from the order (deterministic selection matching product_name tag).
     *
     * @param mixed $order Order object or order data
     * @param string $size Image size (default: 'large')
     * @param array $attributes Additional HTML attributes
     * @param array|null $context Full context array (to access selected_order_item)
     * @return string Image HTML
     * @since 2.0.0
     */
    private static function getOrderContextImageHtml($order, string $size, array $attributes, ?array $context = null): string
    {
        try {
            // Check if order exists and has items
            if (!$order || !method_exists($order, 'getItems') || empty($order->getItems())) {
                return self::getPlaceholderImageHtml($size, $attributes);
            }

            $items = $order->getItems();
            if (empty($items)) {
                return self::getPlaceholderImageHtml($size, $attributes);
            }

            // CRITICAL FIX: Use the same deterministic selection as product_name tag
            // Priority 1: Use selected_order_item from context (matches {product_name} tag exactly)
            $selectedItem = null;
            if ($context && isset($context['selected_order_item'])) {
                $selectedItem = $context['selected_order_item'];
            }
            // Priority 2: If we have context['product'], use that product directly
            elseif ($context && isset($context['product'])) {
                // The product is already fetched in the context from the selected order item
                return ProductImageResolver::getProductImageHtml($context['product'], $size, $attributes);
            }
            // Priority 3: Use deterministic selection based on order ID (same as tag logic)
            elseif (isset($context['filtered_by_products']) && $context['filtered_by_products']) {
                // For products filter, use random selection for variety
                $selectedItem = $items[array_rand($items)];
            } else {
                // Use deterministic selection based on order ID to ensure consistency
                $orderIdSeed = $order->getId() % count($items);
                $selectedItem = array_values($items)[$orderIdSeed];
            }

            // Check if item has product ID method
            if (!$selectedItem || !method_exists($selectedItem, 'getProductId')) {
                return self::getPlaceholderImageHtml($size, $attributes);
            }

            // Fetch the actual product (only if WooCommerce is active)
            if (!PluginDetector::isWooCommerceActive()) {
                return self::getPlaceholderImageHtml($size, $attributes);
            }

            $productFetcher = notifal_app(ProductFetcherInterface::class);
            $product = $productFetcher->findById($selectedItem->getProductId());

            if ($product) {
                return ProductImageResolver::getProductImageHtml($product, $size, $attributes);
            }
        } catch (\Exception $e) {
            // Return placeholder for stability
        }

        return self::getPlaceholderImageHtml($size, $attributes);
    }

    /**
     * Get image for product context.
     *
     * @param ProductDTO $product Product DTO object
     * @param string $size Image size (default: 'large')
     * @param array $attributes Additional HTML attributes
     * @return string Image HTML
     * @since 2.0.0
     */
    private static function getProductImageHtml(ProductDTO $product, string $size, array $attributes): string
    {
        return ProductImageResolver::getProductImageHtml($product, $size, $attributes);
    }

    /**
     * Get placeholder image HTML.
     *
     * @param string $size Image size (default: 'large')
     * @param array $attributes Additional HTML attributes
     * @return string Placeholder image HTML
     * @since 2.0.0
     */
    private static function getPlaceholderImageHtml(string $size, array $attributes): string
    {
        return ProductImageResolver::getTemplatePlaceholderHtml($size, $attributes);
    }

    /**
     * Get available sources from context.
     *
     * @param array|null $context Widget context from WidgetContextProvider
     * @return array Available source keys
     * @since 2.0.0
     */
    public static function getAvailableSources(?array $context): array
    {
        if (!$context) {
            return ['auto'];
        }

        $availableSources = ['auto']; // Always include auto

         $possibleSources = ['comment', 'post', 'page', 'product', 'order'];

        foreach ($possibleSources as $source) {
            if (isset($context[$source]) && $context[$source]) {
                $availableSources[] = $source;
            }
        }

        // Check for custom post types
        $postTypeDiscoveryService = Container::getInstance()->get(PostTypeDiscoveryService::class);
        $customPostTypes = $postTypeDiscoveryService->getFilteredCustomPostTypeNames();

        foreach ($customPostTypes as $postType) {
            if (isset($context[$postType]) && $context[$postType]) {
                $availableSources[] = $postType;
            }
        }

        return $availableSources;
    }

    /**
     * Extract the parent post ID from a comment entity in widget context.
     *
     * Supports WP_Comment objects and array-shaped comment data for flexibility.
     *
     * @param mixed $comment Comment object or comment data array.
     * @return int Parent post ID or 0 when unavailable.
     * @since 2.0.0
     */
    private static function getCommentPostId($comment): int
    {
        // Bail when comment payload is empty
        if (!$comment) {
            return 0;
        }

        // Standard WP_Comment object shape
        if (is_object($comment) && isset($comment->comment_post_ID)) {
            return (int) $comment->comment_post_ID;
        }

        // Array-shaped comment data (REST / serialized payloads)
        if (is_array($comment) && isset($comment['comment_post_ID'])) {
            return (int) $comment['comment_post_ID'];
        }

        return 0;
    }

    /**
     * Check if a post/page has a featured image.
     *
     * @param mixed $post Post object to check
     * @return bool True if post has featured image, false otherwise
     * @since 2.0.0
     */
    private static function hasPostFeaturedImage($post): bool
    {
        if (!$post || !isset($post->ID)) {
            return false;
        }

        $imageId = get_post_thumbnail_id($post->ID);
        return !empty($imageId);
    }
}

