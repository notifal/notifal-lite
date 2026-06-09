<?php

namespace Notifal\Domain\Tags\Services;

use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\ContentSourceService;

defined('ABSPATH') || exit;

/**
 * Resolves content-source counter tags for notifications and templates.
 *
 * Each counter respects the matching notification content source restrictions.
 *
 * @package Notifal\Domain\Tags\Services
 * @since 2.3.7
 * @author Hossein <hossein@notifal.com>
 */
class ContentSourceCountResolver
{
    /**
     * Preview fallback values keyed by entity type.
     *
     * @var array<string, string>
     */
    private const PREVIEW_FALLBACKS = [
        'order'           => '42',
        'product'         => '156',
        'post'            => '24',
        'page'            => '12',
        'comment'         => '87',
        'custom_posttype' => '18',
    ];

    /**
     * Resolve filtered order count for {order_counter}.
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @return string Order count as string.
     * @since 2.3.7
     */
    public static function resolveOrder(array $context): string
    {
        // Order counting requires WooCommerce.
        if (!PluginDetector::isWooCommerceActive()) {
            return self::resolvePreviewOrEmpty($context, 'order');
        }

        return self::resolve('order', $context, 'getOrderCount');
    }

    /**
     * Resolve filtered product count for {product_counter}.
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @return string Product count as string.
     * @since 2.3.7
     */
    public static function resolveProduct(array $context): string
    {
        // Product counting requires WooCommerce.
        if (!PluginDetector::isWooCommerceActive()) {
            return self::resolvePreviewOrEmpty($context, 'product');
        }

        return self::resolve('product', $context, 'getProductCount');
    }

    /**
     * Resolve filtered post count for {post_counter}.
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @return string Post count as string.
     * @since 2.3.7
     */
    public static function resolvePost(array $context): string
    {
        return self::resolve('post', $context, 'getPostCount');
    }

    /**
     * Resolve filtered page count for {page_counter}.
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @return string Page count as string.
     * @since 2.3.7
     */
    public static function resolvePage(array $context): string
    {
        return self::resolve('page', $context, 'getPageCount');
    }

    /**
     * Resolve filtered comment count for {comment_counter}.
     *
     * Comment counting is provided by Notifal Pro via integration hooks.
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @return string Comment count as string.
     * @since 2.3.7
     */
    public static function resolveComment(array $context): string
    {
        return self::resolve('comment', $context, 'getCommentCount');
    }

    /**
     * Resolve filtered custom post type count for {custom_posttype_counter_{post_type}}.
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @param string              $postType Custom post type slug.
     * @return string Custom post type count as string.
     * @since 2.3.7
     */
    public static function resolveCustomPostType(array $context, string $postType): string
    {
        // Sanitize the post type slug from the dynamic tag key.
        $postType = sanitize_key($postType);
        if ($postType === '') {
            return self::resolvePreviewOrEmpty($context, 'custom_posttype');
        }

        // Allow precomputed counts scoped per post type.
        $contextKey = 'custom_posttype_count_' . $postType;
        if (isset($context[$contextKey])) {
            $precomputed = (int) $context[$contextKey];

            return $precomputed > 0
                ? (string) $precomputed
                : self::resolvePreviewOrEmpty($context, 'custom_posttype');
        }

        $settings = self::getContentSourceSettings($context);
        $count    = self::getServiceCount('getCustomPostTypeCount', $settings, $postType);

        if ($count <= 0) {
            return self::resolvePreviewOrEmpty($context, 'custom_posttype');
        }

        return (string) $count;
    }

    /**
     * Shared resolver for standard entity counter tags.
     *
     * @param string               $entityType Entity type key.
     * @param array<string, mixed> $context Tag resolution context.
     * @param string               $serviceMethod ContentSourceService method name.
     * @return string Entity count as string.
     * @since 2.3.7
     */
    private static function resolve(string $entityType, array $context, string $serviceMethod): string
    {
        // Use precomputed count from context when provided.
        $contextKey = $entityType . '_count';
        if (isset($context[$contextKey])) {
            $precomputed = (int) $context[$contextKey];

            return $precomputed > 0
                ? (string) $precomputed
                : self::resolvePreviewOrEmpty($context, $entityType);
        }

        // Resolve count from content source settings.
        $settings = self::getContentSourceSettings($context);
        $count    = self::getServiceCount($serviceMethod, $settings);

        if ($count <= 0) {
            return self::resolvePreviewOrEmpty($context, $entityType);
        }

        return (string) $count;
    }

    /**
     * Read content source settings from tag context.
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @return array<string, mixed> Content source settings array.
     * @since 2.3.7
     */
    private static function getContentSourceSettings(array $context): array
    {
        return isset($context['content_source_settings']) && is_array($context['content_source_settings'])
            ? $context['content_source_settings']
            : [];
    }

    /**
     * Call a ContentSourceService count method safely.
     *
     * @param string               $method Service method name.
     * @param array<string, mixed> $settings Content source settings.
     * @param string|null          $postType Optional custom post type slug.
     * @return int Matching entity count.
     * @since 2.3.7
     */
    private static function getServiceCount(string $method, array $settings, ?string $postType = null): int
    {
        if (!function_exists('notifal_app')) {
            return 0;
        }

        try {
            /** @var ContentSourceService $contentSourceService */
            $contentSourceService = notifal_app(ContentSourceService::class);

            if ($postType !== null) {
                return (int) $contentSourceService->{$method}($postType, $settings);
            }

            return (int) $contentSourceService->{$method}($settings);
        } catch (\Throwable $exception) {
            return 0;
        }
    }

    /**
     * Return preview fallback or empty string depending on context mode.
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @param string               $entityType Entity type key.
     * @return string Preview fallback or empty string.
     * @since 2.3.7
     */
    private static function resolvePreviewOrEmpty(array $context, string $entityType): string
    {
        if (self::isPreviewMode($context)) {
            return self::PREVIEW_FALLBACKS[$entityType] ?? '0';
        }

        return '';
    }

    /**
     * Check whether the current request is in preview mode.
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @return bool True when preview mode is active.
     * @since 2.3.7
     */
    private static function isPreviewMode(array $context): bool
    {
        return isset($context['is_preview']) && $context['is_preview'] === true;
    }
}
