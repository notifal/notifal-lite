<?php

namespace Notifal\Modules\Templates\Controllers;

use Notifal\Domain\Products\ProductFetcherInterface;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\Templates\Application\Services\FeaturedImageResolver;
use Notifal\Modules\Templates\Application\Services\ImagePreviewService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\ContentSourceService;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class FeaturedImageApiController
 *
 * Handles API logic for fetching context-aware image data for Gutenberg editor preview.
 * Provides featured images that adapt to notification context.
 *
 * @package Notifal\Modules\Templates\Controllers
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FeaturedImageApiController
{
    /**
     * Get featured image data for editor preview.
     *
     * Returns context-aware image information including URL and metadata.
     * Supports multiple content sources: auto, post, page, product.
     *
     * @param \WP_REST_Request $request REST API request object
     * @return array|\WP_Error Image data or error response.
     * @since 2.0.0
     */
    public function getFeaturedImagePreview(\WP_REST_Request $request)
    {
        $requestedSource = Helper::sanitizeInput($request->get_param('source') ?? 'auto', 'text');

        try {
            $context = $this->buildContext();
            $imageHtml = FeaturedImageResolver::getFeaturedImageHtml($context, 'large', [], $requestedSource);

            if (empty($imageHtml)) {
                return ImagePreviewService::createErrorResponse(__('No featured image available.', 'notifal'));
            }

            $imageData = $this->extractImageDataFromHtml($imageHtml);
            if (!$imageData) {
                return ImagePreviewService::createErrorResponse(__('Unable to parse image data.', 'notifal'));
            }

            $thumbnailId = $this->findThumbnailId($context);
            $images = $thumbnailId ? ImagePreviewService::getImageData($thumbnailId) : [];
            $contentInfo = $this->determineContentInfo($context);

            $responseData = [
                'id'               => $contentInfo['id'],
                'name'             => $contentInfo['name'],
                'content_type'     => $contentInfo['type'],
                'woocommerce_active' => PluginDetector::isWooCommerceActive(),
                'thumbnail_id'     => $thumbnailId,
                'images'           => $images,
                'alt_text'         => $imageData['alt'],
                'html'             => $imageHtml
            ];

            return ImagePreviewService::createSuccessResponse($responseData);

        } catch (\Exception $e) {
            return ImagePreviewService::createErrorResponse(
                __('Unable to fetch featured image data.', 'notifal'),
                ['error_details' => $e->getMessage()]
            );
        }
    }

    /**
     * Build context data for image resolution.
     *
     * Creates a comprehensive context with available content types
     * for the FeaturedImageResolver to work with.
     *
     * @return array Context data with post, page, product, and order information
     * @since 2.0.0
     */
    private function buildContext(): array
    {
        $context = [
            'post' => $this->getSamplePost(),
            'page' => $this->getSamplePage(),
        ];

        if (PluginDetector::isWooCommerceActive()) {
            $context['product'] = $this->getSampleProduct();
            $context['order'] = $this->getSampleOrder();
        }

        return $context;
    }

    /**
     * Extract image data from HTML.
     *
     * Parses the generated HTML to extract image source and alt text.
     *
     * @param string $html Image HTML
     * @return array|null Image data or null if parsing fails
     * @since 2.0.0
     */
    private function extractImageDataFromHtml(string $html): ?array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $imgTag = $dom->getElementsByTagName('img')->item(0);

        if (!$imgTag) {
            return null;
        }

        return [
            'src' => $imgTag->getAttribute('src'),
            'alt' => $imgTag->getAttribute('alt') ?: __('Featured Image', 'notifal')
        ];
    }

    /**
     * Find thumbnail ID from context.
     *
     * Searches through available context to find a valid thumbnail ID.
     *
     * @param array $context Context data
     * @return int|null Thumbnail ID or null if not found
     * @since 2.0.0
     */
    private function findThumbnailId(array $context): ?int
    {
        $sources = ['product', 'post', 'page', 'order'];

        foreach ($sources as $source) {
            if (isset($context[$source]) && $context[$source]) {
                $id = $this->getThumbnailIdForEntity($context[$source], $source);
                if ($id) {
                    return $id;
                }
            }
        }

        return null;
    }

    /**
     * Determine content information from context.
     *
     * Analyzes the context to determine content type and name for UX.
     *
     * @param array $context Context data
     * @return array Content information
     * @since 2.0.0
     */
    private function determineContentInfo(array $context): array
    {
        $sources = ['product', 'post', 'page', 'order'];

        foreach ($sources as $source) {
            if (isset($context[$source]) && $context[$source]) {
                return $this->getContentInfoForEntity($context[$source], $source);
            }
        }

        return [
            'id'   => null,
            'name' => __('Featured Image', 'notifal'),
            'type' => 'post'
        ];
    }

    /**
     * Get thumbnail ID for a specific entity.
     *
     * @param mixed $entity Entity object
     * @param string $type Entity type
     * @return int|null Thumbnail ID
     * @since 2.0.0
     */
    private function getThumbnailIdForEntity($entity, string $type): ?int
    {
        if ($type === 'product' && method_exists($entity, 'getId')) {
            return get_post_thumbnail_id($entity->getId());
        }

        if ($type === 'order' && method_exists($entity, 'getItems')) {
            // For orders, get thumbnail from first product in the order
            $items = $entity->getItems();
            if (!empty($items)) {
                $firstItem = reset($items);
                if (method_exists($firstItem, 'getProductId')) {
                    return get_post_thumbnail_id($firstItem->getProductId());
                }
            }
            return null;
        }

        if (isset($entity->ID)) {
            return get_post_thumbnail_id($entity->ID);
        }

        return null;
    }

    /**
     * Get content information for a specific entity.
     *
     * @param mixed $entity Entity object
     * @param string $type Entity type
     * @return array Content information
     * @since 2.0.0
     */
    private function getContentInfoForEntity($entity, string $type): array
    {
        switch ($type) {
            case 'product':
                return [
                    'id'   => method_exists($entity, 'getId') ? $entity->getId() : null,
                    'name' => method_exists($entity, 'getName') ? $entity->getName() : __('Product', 'notifal'),
                    'type' => 'product'
                ];

            case 'order':
                return [
                    'id'   => method_exists($entity, 'getId') ? $entity->getId() : null,
                    'name' => method_exists($entity, 'getId') ? sprintf(__('Order #%s', 'notifal'), $entity->getId()) : __('Order', 'notifal'),
                    'type' => 'order'
                ];

            case 'post':
            case 'page':
                return [
                    'id'   => $entity->ID ?? null,
                    'name' => $entity->post_title ?? __('Content', 'notifal'),
                    'type' => $type
                ];

            default:
                return [
                    'id'   => null,
                    'name' => __('Featured Image', 'notifal'),
                    'type' => 'post'
                ];
        }
    }

    /**
     * Get sample product for preview context.
     *
     * @return mixed Product object or null if none found
     * @since 2.0.0
     */
    private function getSampleProduct()
    {
        try {
            $previewDataResolver = notifal_app(\Notifal\Modules\Templates\Application\Services\PreviewDataResolver::class);
            $previewData = $previewDataResolver->resolve();

            if ($previewData && $previewData->getProduct()) {
                return $previewData->getProduct();
            }

            $productFetcher = notifal_app(ProductFetcherInterface::class);
            return $productFetcher->getRandom();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get sample post for preview context.
     *
     * @return \WP_Post|null Sample post or null if none found.
     * @since 2.0.0
     */
    private function getSamplePost(): ?\WP_Post
    {
        // Try to get a post using the same filtering logic as content source
        // This ensures consistency between featured image preview and tag replacement

        // First, check if we have notification context (content source settings)
        $notificationId = isset($_GET['notification_id']) ? absint($_GET['notification_id']) : 0;
        if ($notificationId > 0) {
            $contentSourceSettings = get_post_meta($notificationId, '_notifal_content_source_settings', true);
            if ($contentSourceSettings && isset($contentSourceSettings['post_filters'])) {
                // Use ContentSourceService to get a filtered post
                $contentSourceService = notifal_app(ContentSourceService::class);
                $filteredPost = $contentSourceService->getRandomPost($contentSourceSettings);

                if ($filteredPost) {
                    return $filteredPost;
                }
            }
        }

        // Fallback to random post if no filtering available
        $posts = get_posts([
            'post_type'      => 'post',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'orderby'        => 'rand'
        ]);

        return !empty($posts) ? $posts[0] : null;
    }

    /**
     * Get sample page for preview context.
     *
     * @return \WP_Post|null Sample page or null if none found.
     * @since 2.0.0
     */
    private function getSamplePage(): ?\WP_Post
    {
        $pages = get_posts([
            'post_type'      => 'page',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'orderby'        => 'rand'
        ]);

        return !empty($pages) ? $pages[0] : null;
    }

    /**
     * Get sample order for preview context.
     *
     * @return mixed Sample order or null if none found.
     * @since 2.0.0
     */
    private function getSampleOrder()
    {
        try {
            $orderFetcher = notifal_app(\Notifal\Domain\Orders\OrderFetcherInterface::class);
            return $orderFetcher->getRandom();
        } catch (\Exception $e) {
            return null;
        }
    }

} 
