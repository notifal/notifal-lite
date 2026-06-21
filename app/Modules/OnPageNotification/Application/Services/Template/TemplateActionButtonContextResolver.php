<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Template;

use Notifal\Modules\OnPageNotification\Application\Services\Utility\WidgetContextProvider;

defined('ABSPATH') || exit;

/**
 * Resolves notification context metadata for class-based action button placeholders.
 *
 * Mirrors Elementor widget and Block Editor action button context resolution so
 * custom HTML elements receive the same data attributes for revenue and analytics.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services\Template
 * @since 2.3.12
 * @author Hossein <hossein@notifal.com>
 */
class TemplateActionButtonContextResolver
{
    /**
     * Resolve action button context metadata from the active widget context.
     *
     * @param array|null $context Optional frontend context; falls back to WidgetContextProvider.
     * @return array{
     *     url?: string,
     *     context_type?: string,
     *     is_product_context?: bool,
     *     product_id?: int,
     *     variation_id?: int,
     *     product_url?: string,
     *     product?: mixed
     * }
     * @since 2.3.12
     */
    public static function resolve(?array $context = null): array
    {
        // Start with an empty metadata array
        $meta = [];

        // Use provided context or read from the active widget context provider
        if ($context === null && class_exists(WidgetContextProvider::class) && WidgetContextProvider::isActive()) {
            $context = WidgetContextProvider::getContext();
        }

        // Bail when no usable context is available
        if (empty($context) || !is_array($context)) {
            return $meta;
        }

        // Track the product entity when resolved for attribute building
        $contextProduct = null;

        // Product context has the highest priority for commerce notifications
        if (!empty($context['product'])) {
            $contextProduct = $context['product'];
            $meta['url'] = method_exists($contextProduct, 'getLink') ? $contextProduct->getLink() : '';
            $meta['context_type'] = 'product';
            $meta['is_product_context'] = true;
        } elseif (!empty($context['order'])) {
            // Order context falls back to the first order line product when possible
            $order = $context['order'];
            $orderItems = method_exists($order, 'getItems') ? $order->getItems() : [];

            if (!empty($orderItems)) {
                $firstItem = reset($orderItems);
                $product = method_exists($firstItem, 'getProduct') ? $firstItem->getProduct() : null;

                if ($product) {
                    $contextProduct = $product;
                    $meta['url'] = method_exists($product, 'getPermalink') ? $product->getPermalink() : '';
                    $meta['context_type'] = 'product';
                    $meta['is_product_context'] = true;
                }
            }

            // When no product URL exists, use the order view URL instead
            if (empty($meta['url']) && method_exists($order, 'getViewOrderUrl')) {
                $meta['url'] = $order->getViewOrderUrl();
                $meta['context_type'] = 'order';
            }
        } elseif (!empty($context['post'])) {
            // Standard post permalink
            $meta['url'] = get_permalink($context['post']->ID);
            $meta['context_type'] = 'post';
        } elseif (!empty($context['page'])) {
            // Standard page permalink
            $meta['url'] = get_permalink($context['page']->ID);
            $meta['context_type'] = 'page';
        } elseif (!empty($context['comment'])) {
            // Comment context links to the parent post permalink
            $meta['url'] = get_permalink($context['comment']->comment_post_ID);
            $meta['context_type'] = 'comment';
        } else {
            // Scan remaining context keys for custom post type entities
            foreach ($context as $value) {
                if (is_object($value) && isset($value->ID, $value->post_type)) {
                    $meta['url'] = get_permalink($value->ID);
                    $meta['context_type'] = sanitize_key((string) $value->post_type);
                    break;
                }
            }
        }

        // Attach WooCommerce product identifiers when a product entity was resolved
        if ($contextProduct && method_exists($contextProduct, 'getId')) {
            $meta['product'] = $contextProduct;
            $meta['product_id'] = (int) $contextProduct->getId();

            if (method_exists($contextProduct, 'getVariationContextId')) {
                $variationId = (int) ($contextProduct->getVariationContextId() ?? 0);
                if ($variationId > 0) {
                    $meta['variation_id'] = $variationId;
                }
            }

            if (method_exists($contextProduct, 'getLink')) {
                $meta['product_url'] = $contextProduct->getLink();
            }
        }

        return $meta;
    }
}
