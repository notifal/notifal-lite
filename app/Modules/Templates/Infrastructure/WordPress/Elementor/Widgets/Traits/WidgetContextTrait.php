<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Widgets\Traits;

use Notifal\Modules\OnPageNotification\Application\Services\Utility\WidgetContextProvider;

defined('ABSPATH') || exit;

/**
 * Trait WidgetContextTrait
 *
 * Provides standardized methods for accessing widget context data from WidgetContextProvider.
 * Eliminates code duplication across Elementor widgets that need context-aware rendering.
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Widgets\Traits
 * @author Hossein <hossein@notifal.com>
 */
trait WidgetContextTrait
{
    /**
     * Get the current widget context if available.
     *
     * @since 2.0.0
     * @return array|null Context data or null if not available
     */
    protected function getWidgetContext(): ?array
    {
        if (!class_exists(WidgetContextProvider::class)) {
            return null;
        }

        if (!WidgetContextProvider::isActive()) {
            return null;
        }

        return WidgetContextProvider::getContext();
    }

    /**
     * Check if widget context is currently active.
     *
     * @since 2.0.0
     * @return bool True if context is active, false otherwise
     */
    protected function isWidgetContextActive(): bool
    {
        return class_exists(WidgetContextProvider::class) && WidgetContextProvider::isActive();
    }

    /**
     * Resolve context URL from available context entities.
     *
     * Priority order: product → order → post → page → comment → custom post types.
     *
     * @since 2.0.0
     * @param array|null $context Context data from getWidgetContext()
     * @return array Returns array with 'data', 'url', and optional 'context_type' keys, or empty array if no context found
     * @since 2.3.9 Added `context_type` for revenue attribution on action buttons.
     */
    protected function resolveContextData(?array $context = null): array
    {
        $context = $context ?? $this->getWidgetContext();

        if (!$context) {
            return [];
        }

        // Product context - highest priority
        if (isset($context['product']) && $context['product']) {
            $product = $context['product'];
            if (method_exists($product, 'getLink')) {
                return [
                    'data' => $product,
                    'url' => $product->getLink(),
                    'context_type' => 'product',
                ];
            }
        }

        // Order context - get product from order or use order view URL
        if (isset($context['order']) && $context['order']) {
            $order = $context['order'];
            $orderItems = method_exists($order, 'getItems') ? $order->getItems() : [];

            if (!empty($orderItems)) {
                // Get first product from order
                $firstItem = reset($orderItems);
                $product = method_exists($firstItem, 'getProduct') ? $firstItem->getProduct() : null;

                if ($product && method_exists($product, 'getPermalink')) {
                    return [
                        'data' => $product,
                        'url' => $product->getPermalink(),
                        'context_type' => 'product',
                    ];
                }
            }

            // Fallback to order view URL
            if (method_exists($order, 'getViewOrderUrl')) {
                return [
                    'data' => $order,
                    'url' => $order->getViewOrderUrl(),
                    'context_type' => 'order',
                ];
            }
        }

        // Post context
        if (isset($context['post']) && $context['post']) {
            return [
                'data' => $context['post'],
                'url' => get_permalink($context['post']->ID),
                'context_type' => 'post',
            ];
        }

        // Page context
        if (isset($context['page']) && $context['page']) {
            return [
                'data' => $context['page'],
                'url' => get_permalink($context['page']->ID),
                'context_type' => 'page',
            ];
        }

        // Comment context - use the post/page the comment belongs to
        if (isset($context['comment']) && $context['comment']) {
            $commentPostId = $context['comment']->comment_post_ID;
            return [
                'data' => $context['comment'],
                'url' => get_permalink($commentPostId),
                'context_type' => 'comment',
            ];
        }

        // Check for custom post types
        foreach ($context as $key => $value) {
            if (is_object($value) && isset($value->ID) && isset($value->post_type)) {
                return [
                    'data' => $value,
                    'url' => get_permalink($value->ID),
                    'context_type' => sanitize_key((string) $value->post_type),
                ];
            }
        }

        return [];
    }
}