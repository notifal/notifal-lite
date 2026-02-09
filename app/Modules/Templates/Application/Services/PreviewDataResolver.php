<?php

namespace Notifal\Modules\Templates\Application\Services;

use Notifal\Domain\Orders\OrderFetcherInterface;
use Notifal\Domain\Products\ProductFetcherInterface;
use Notifal\Infrastructure\WordPress\Services\UserFetcher;
use Notifal\Domain\Products\DTO\ProductDTO;
use Notifal\Domain\Tags\TagManager;
use Notifal\Modules\Templates\Domain\DTO\PreviewDataDTO;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\ContentSourceService;
use Notifal\Infrastructure\WordPress\Admin\Settings\Services\PostTypeDiscoveryService;

defined('ABSPATH') || exit;

/**
 * Class PreviewDataResolver
 *
 * Resolves data for template previews (Elementor, Block Editor).
 * Provides fallback values for tags to ensure a complete preview experience.
 *
 * @package Notifal\Modules\Templates\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class PreviewDataResolver
{
    /**
     * @var ProductFetcherInterface
     */
    private ProductFetcherInterface $productFetcher;


    /**
     * @var OrderFetcherInterface
     */
    private OrderFetcherInterface $orderFetcher;

    /**
     * @var UserFetcher
     */
    private UserFetcher $userFetcher;

    /**
     * @var TagManager
     */
    private TagManager $tagManager;

    /**
     * @var ContentSourceService
     */
    private ContentSourceService $contentSourceService;

    /**
     * @var PostTypeDiscoveryService
     */
    private PostTypeDiscoveryService $postTypeDiscoveryService;

    /**
     * PreviewDataResolver constructor.
     *
     * @param ProductFetcherInterface $productFetcher
     * @param OrderFetcherInterface $orderFetcher
     * @param UserFetcher $userFetcher
     * @param PostTypeDiscoveryService $postTypeDiscoveryService
     * @since 2.0.0
     */
    public function __construct(
        ProductFetcherInterface $productFetcher,
        OrderFetcherInterface $orderFetcher,
        UserFetcher $userFetcher,
        PostTypeDiscoveryService $postTypeDiscoveryService
    )
    {
        $this->productFetcher = $productFetcher;
        $this->orderFetcher = $orderFetcher;
        $this->userFetcher = $userFetcher;
        $this->postTypeDiscoveryService = $postTypeDiscoveryService;
        $this->tagManager = notifal_app(TagManager::class);
        $this->contentSourceService = notifal_app(ContentSourceService::class);
    }

    /**
     * Resolve preview data using a random product and fallback values.
     *
     * @param string|null $templateContent Optional template content (no longer used for dynamic tags).
     * @param array $contentSourceSettings Optional content source settings for filtering
     * @return PreviewDataDTO|null
     * @since 2.0.0
     */
    public function resolve(?string $templateContent = null, array $contentSourceSettings = []): ?PreviewDataDTO
    {
        // Determine primary content type from template content
        $primaryContentType = $this->determinePrimaryContentType($templateContent);
        
        // Use current user for preview, fallback to random if no current user
        $currentUser = $this->userFetcher->getCurrent();
        $user = $currentUser ?: $this->userFetcher->getRandom();
        
        // Build context based on primary content type priority
        $context = ['user' => $user];
        
        // Get sample data for content types
        $post = $this->contentSourceService->getRandomPost($contentSourceSettings);
        $page = $this->contentSourceService->getRandomPage($contentSourceSettings);
        $comment = $this->contentSourceService->getRandomComment($contentSourceSettings);
        $order = $this->contentSourceService->getRandomOrder($contentSourceSettings);
        $product = $this->getSmartProduct($templateContent, $contentSourceSettings);
        
        // Get sample data for custom post types that might be used in tags
        $customPostTypeContext = $this->getCustomPostTypeContext($templateContent, $contentSourceSettings);

        // Build context with priority based on primary content type
        switch ($primaryContentType) {
            case 'post':
                if ($post) $context['post'] = $post;
                if ($page) $context['page'] = $page;
                $context = array_merge($context, $customPostTypeContext);
                if ($comment) $context['comment'] = $comment;
                if ($order) $context['order'] = $order;
                if ($product) $context['product'] = $product;
                break;
                
            case 'page':
                if ($page) $context['page'] = $page;
                if ($post) $context['post'] = $post;
                $context = array_merge($context, $customPostTypeContext);
                if ($comment) $context['comment'] = $comment;
                if ($order) $context['order'] = $order;
                if ($product) $context['product'] = $product;
                break;
                
            case 'comment':
                if ($comment) $context['comment'] = $comment;
                if ($post) $context['post'] = $post;
                if ($page) $context['page'] = $page;
                $context = array_merge($context, $customPostTypeContext);
                if ($order) $context['order'] = $order;
                if ($product) $context['product'] = $product;
                break;
                
            case 'custom_post_type':
                $context = array_merge($context, $customPostTypeContext);
                if ($post) $context['post'] = $post;
                if ($page) $context['page'] = $page;
                if ($comment) $context['comment'] = $comment;
                if ($order) $context['order'] = $order;
                if ($product) $context['product'] = $product;
                break;
                
            case 'order':
                if ($order) $context['order'] = $order;
                if ($product) $context['product'] = $product;
                if ($post) $context['post'] = $post;
                if ($page) $context['page'] = $page;
                $context = array_merge($context, $customPostTypeContext);
                if ($comment) $context['comment'] = $comment;
                break;
                
            case 'product':
            default:
                if ($product) $context['product'] = $product;
                if ($order) $context['order'] = $order;
                if ($post) $context['post'] = $post;
                if ($page) $context['page'] = $page;
                $context = array_merge($context, $customPostTypeContext);
                if ($comment) $context['comment'] = $comment;
                break;
        }
        
        // Ensure we have at least a product for fallback compatibility
        if (!$product) {
            $product = $this->productFetcher->getRandom();
            if (!$product) {
                return null;
            }
            $context['product'] = $product;
        }

        $resolvedTags = [];
        $allTags = $this->tagManager->allFiltered();

        // Only resolve static tags for preview data
        foreach ($allTags as $tag) {
            if (method_exists($tag, 'isDynamic') && $tag->isDynamic()) {
                // Skip dynamic tags; these will be handled by TagManager::render()
                continue;
            }
            $value = $tag->resolve($context);
            if ($value === null || $value === '') {
                $value = $this->getPreviewFallback($tag->getKey());
            }
            $resolvedTags[$tag->getKey()] = $value;
        }

        return new PreviewDataDTO($product, $resolvedTags);
    }

    /**
     * Get smart product selection based on template content context.
     *
     * @param string|null $templateContent The template content to analyze for context.
     * @param array $contentSourceSettings Optional content source settings for filtering
     * @return ProductDTO|null Selected product based on content context.
     * @since 2.0.0
     */
    private function getSmartProduct(?string $templateContent, array $contentSourceSettings = []): ?ProductDTO
    {
        // Use content source service to get filtered product
        $product = $this->contentSourceService->getRandomProduct($contentSourceSettings);
        
        if ($product) {
            return $product;
        }

        // Fallback to original logic if no product found with filters
        if (! $templateContent) {
            return $this->productFetcher->getRandom();
        }

        // Check if content contains order-related tags
        $hasOrderTags = preg_match('/\{(order_id|order_meta_|order_)/i', $templateContent);
        
        // Check if content contains sale-related tags
        $hasSaleTags = preg_match('/\{(product_meta_sale_price|product_discount_|product_meta_regular_price)/i', $templateContent);

        if ($hasOrderTags) {
            // For order-based notifications, get product from order items
            return $this->getProductFromOrder();
        } elseif ($hasSaleTags) {
            // For sale notifications, get a product on sale
            return $this->getProductOnSale();
        } else {
            // Default: random product
            return $this->productFetcher->getRandom();
        }
    }

    /**
     * Get a product from a random order's items.
     *
     * @return ProductDTO|null Product from order items, or random product if no order found.
     * @since 2.0.0
     */
    private function getProductFromOrder(): ?ProductDTO
    {
        $order = $this->orderFetcher->getRandom();
        if (! $order || empty($order->getItems())) {
            return $this->productFetcher->getRandom();
        }

        // Get random item from the order
        $items = $order->getItems();
        $randomItem = $items[array_rand($items)];
        
        // Fetch the actual product
        return $this->productFetcher->findById($randomItem->getProductId());
    }

    /**
     * Get a random product that is on sale.
     *
     * @return ProductDTO|null Product on sale, or random product if none found.
     * @since 2.0.0
     */
    private function getProductOnSale(): ?ProductDTO
    {
        // Query for products on sale
        $query = new \WP_Query([
            'post_type'      => 'product',
            'posts_per_page' => 5,
            'orderby'        => 'rand',
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => '_sale_price',
                    'value'   => '',
                    'compare' => '!='
                ],
                [
                    'key'     => '_sale_price',
                    'value'   => 0,
                    'compare' => '>'
                ]
            ]
        ]);

        if (! empty($query->posts)) {
            return $this->productFetcher->findById($query->posts[0]);
        }

        // Fallback to any random product if no sale products found
        return $this->productFetcher->getRandom();
    }

    /**
     * Get a fallback value for a tag key in preview mode.
     *
     * @param string $tagKey
     * @return string
     * @since 2.0.0
     */
    private function getPreviewFallback(string $tagKey): string
    {
        // Fallbacks for dynamic order meta keys
        if (preg_match('/^order_meta_(.+)$/', $tagKey, $matches)) {
            $metaKey = $matches[1];
            $orderMetaFallbacks = [
                'billing_first_name' => 'John',
                'billing_last_name'  => 'Doe',
                'billing_city'       => 'Paris',
                'billing_country'    => 'France',
                'billing_email'      => 'john.doe@example.com',
                'billing_phone'      => '+33 1 23 45 67 89',
                'shipping_first_name' => 'Jane',
                'shipping_last_name'  => 'Smith',
                'shipping_city'       => 'Lyon',
                'shipping_country'    => 'France'
            ];
            return $orderMetaFallbacks[$metaKey] ?? 'Sample Value';
        }

        // Fallbacks for dynamic product meta keys
        if (preg_match('/^product_meta_(.+)$/', $tagKey, $matches)) {
            $metaKey = $matches[1];
            $productMetaFallbacks = [
                'sale_price'    => '$79.99',
                'regular_price' => '$99.99',
                'sku'           => 'SKU12345'
            ];
            return $productMetaFallbacks[$metaKey] ?? 'Sample Value';
        }

        // Fallbacks for dynamic user meta keys
        if (preg_match('/^user_meta_(.+)$/', $tagKey, $matches)) {
            $metaKey = $matches[1];
            $userMetaFallbacks = [
                'first_name' => 'John',
                'last_name'  => 'Doe',
                'city'       => 'Paris'
            ];
            return $userMetaFallbacks[$metaKey] ?? 'Sample Value';
        }

        // Static fallbacks for non-dynamic tags
        $fallbacks = [
            // 🟢 User tags
            'user_first_name'        => 'John',
            'user_last_name'         => 'Doe',

            // 🟢 Order tags
            'order_created_at'       => '2024-12-31 23:59',
            'order_counter'          => '42',
            'order_city'             => 'Paris',
            'order_state'            => 'Île-de-France',
            'order_country'          => 'France',
            'order_company'          => 'Acme Corp',

            // 🟢 Product tags
            'product_name'           => 'Sample Product',
            'product_discount_amount'=> '$0.00',
            'product_discount_percent' => '0%',

            // 🟢 Post tags  
            'post_title'             => 'Sample Blog Post',
            'post_content'           => 'This is a sample blog post content for preview...',
            'post_excerpt'           => 'This is a sample excerpt from a blog post.',
            'post_author'            => 'John Smith',
            'post_published_date_Y-m-d' => '2024-12-31',
            'post_modified_date_Y-m-d' => '2024-12-31',
            'post_created_date_Y-m-d'  => '2024-12-31',
            'post_published_date_diff' => '2 hours ago',
            'post_modified_date_diff'  => '1 hour ago',
            'post_created_date_diff'   => '2 hours ago',
            'post_url'               => home_url('/sample-blog-post'),
            'post_categories'        => 'Technology, WordPress',
            'post_tags'              => 'web development, tutorials',

            // 🟢 Page tags
            'page_title'             => 'Sample Page',
            'page_content'           => 'This is sample page content for preview...',
            'page_excerpt'           => 'This is a sample page excerpt.',
            'page_author'            => 'Jane Doe',
            'page_published_date'              => '2024-12-31',
            'page_published_date_diff'         => '1 day ago',
            'page_url'               => home_url('/sample-page'),

            // 🟢 Comment tags
            'comment_author'         => 'Mike Johnson',
            'comment_content'        => 'This is a sample comment for preview purposes.',
            'comment_date'           => '2024-12-31 10:30',
            'comment_date_diff'      => '30 minutes ago',
            'comment_post_title'     => 'Sample Post Title',
            'comment_post_url'       => home_url('/sample-post'),
            'comment_author_email'   => 'mike@example.com',
            'comment_author_url'     => 'https://example.com',
        ];

        // Check if this is a custom post type tag
        $customPostTypeFallback = $this->postTypeDiscoveryService->getCustomPostTypeFallback($tagKey);
        if ($customPostTypeFallback !== null) {
            return $customPostTypeFallback;
        }

        return $fallbacks[$tagKey] ?? 'N/A';
    }


    /**
     * Get custom post type context data for preview.
     *
     * @param string|null $templateContent Template content to analyze for custom post type usage
     * @param array $contentSourceSettings Content source settings for filtering
     * @return array Custom post type context data
     * @since 2.0.0
     */
    private function getCustomPostTypeContext(?string $templateContent, array $contentSourceSettings = []): array
    {
        $context = [];
        
        // Get all registered public custom post types (excluding built-in and WooCommerce types)
        $customPostTypes = $this->postTypeDiscoveryService->getFilteredCustomPostTypeNames();
        
        // If template content is provided, detect which custom post types are actually used
        if ($templateContent) {
            $usedPostTypes = [];
            foreach ($customPostTypes as $postType) {
                // Look for tags like {custom_post_type_title}, {custom_post_type_content}, etc.
                $pattern = '/\{' . preg_quote($postType, '/') . '_[a-zA-Z_]+\}/i';
                if (preg_match($pattern, $templateContent)) {
                    $usedPostTypes[] = $postType;
                }
            }
            $customPostTypes = $usedPostTypes;
        }
        
        // Get sample data for each detected/used custom post type
        foreach ($customPostTypes as $postType) {
            $samplePost = $this->contentSourceService->getRandomCustomPostType($postType, $contentSourceSettings);
            if ($samplePost) {
                $context[$postType] = $samplePost;
            }
        }
        
        return $context;
    }


    /**
     * Determine the primary content type from template content.
     *
     * @param string|null $templateContent Template content to analyze
     * @return string Primary content type (post, page, comment, order, product, custom_post_type)
     * @since 2.0.0
     */
    private function determinePrimaryContentType(?string $templateContent): string
    {
        if (!$templateContent) {
            return 'product'; // Default fallback
        }
        
        // Count tags for each content type to determine primary focus
        $tagCounts = [
            'post' => 0,
            'page' => 0,
            'comment' => 0,
            'order' => 0,
            'product' => 0,
            'custom_post_type' => 0,
        ];
        
        // Count post tags
        $tagCounts['post'] = preg_match_all('/\{post_[a-zA-Z_]+\}/i', $templateContent);
        
        // Count page tags
        $tagCounts['page'] = preg_match_all('/\{page_[a-zA-Z_]+\}/i', $templateContent);
        
        // Count comment tags
        $tagCounts['comment'] = preg_match_all('/\{comment_[a-zA-Z_]+\}/i', $templateContent);
        
        // Count order tags
        $tagCounts['order'] = preg_match_all('/\{order_[a-zA-Z_]+\}/i', $templateContent);
        
        // Count product tags
        $tagCounts['product'] = preg_match_all('/\{product_[a-zA-Z_]+\}/i', $templateContent);
        
        // Count custom post type tags
        $customPostTypes = $this->postTypeDiscoveryService->getFilteredCustomPostTypeNames();
        
        foreach ($customPostTypes as $postType) {
            $pattern = '/\{' . preg_quote($postType, '/') . '_[a-zA-Z_]+\}/i';
            $count = preg_match_all($pattern, $templateContent);
            $tagCounts['custom_post_type'] += $count;
        }
        
        // Remove zero counts and find the highest
        $tagCounts = array_filter($tagCounts);
        
        if (empty($tagCounts)) {
            return 'product'; // Default fallback
        }
        
        // Return the content type with the highest tag count
        arsort($tagCounts);
        return array_key_first($tagCounts);
    }
}
