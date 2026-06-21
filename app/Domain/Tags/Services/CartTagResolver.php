<?php

namespace Notifal\Domain\Tags\Services;

use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\OnPageNotification\Application\Services\Rules\WooCommerceCartContextBuilder;

defined('ABSPATH') || exit;

/**
 * Resolves WooCommerce cart values for template tags.
 *
 * @package Notifal\Domain\Tags\Services
 * @since 2.3.5
 * @author Hossein <hossein@notifal.com>
 */
class CartTagResolver
{
    /**
     * Preview fallback values keyed by cart tag identifier.
     *
     * @var array<string, string>
     */
    private const PREVIEW_FALLBACKS = [
        'cart_total'              => '149.99',
        'cart_subtotal'           => '159.99',
        'cart_discount'           => '10.00',
        'cart_item_count'         => '3',
        'cart_unique_products'    => '2',
        'cart_coupons'            => 'SAVE10',
        'cart_first_product_name' => 'Sample Product',
    ];

    /**
     * Read the cart snapshot from context or build it from the current session.
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @return array<string, mixed> Normalized cart snapshot.
     * @since 2.3.5
     */
    public static function getSnapshot(array $context): array
    {
        // Prefer an existing cart snapshot passed from the frontend or API.
        if (isset($context['cart']) && is_array($context['cart'])) {
            $snapshot = $context['cart'];

            // Enrich empty admin-preview carts with sample values.
            if (self::shouldUsePreviewCartSnapshot($context, $snapshot)) {
                return self::buildPreviewSnapshot($context);
            }

            return self::appendFirstProductNameToSnapshot($snapshot);
        }

        // Build from WooCommerce when the plugin is available.
        if (PluginDetector::isWooCommerceActive()) {
            $snapshot = WooCommerceCartContextBuilder::build();

            if (self::shouldUsePreviewCartSnapshot($context, $snapshot)) {
                return self::buildPreviewSnapshot($context);
            }

            return self::appendFirstProductNameToSnapshot($snapshot);
        }

        // Return a safe empty structure when WooCommerce is unavailable.
        return WooCommerceCartContextBuilder::emptySnapshot();
    }

    /**
     * Resolve a cart snapshot for builder/admin previews.
     *
     * Uses the live session cart when it has items; otherwise returns sample
     * values so tags like {cart_first_product_name} render in the HTML Builder.
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @return array<string, mixed> Cart snapshot for preview rendering.
     * @since 2.4.0
     */
    public static function resolvePreviewCartSnapshot(array $context): array
    {
        // Start from any snapshot already attached to the context.
        if (isset($context['cart']) && is_array($context['cart'])) {
            $snapshot = $context['cart'];
        } elseif (PluginDetector::isWooCommerceActive()) {
            $snapshot = WooCommerceCartContextBuilder::build();
        } else {
            $snapshot = WooCommerceCartContextBuilder::emptySnapshot();
        }

        // Outside preview mode, return the live snapshot unchanged.
        if (!self::isPreviewMode($context)) {
            return self::appendFirstProductNameToSnapshot($snapshot);
        }

        // Keep real cart data when the editor session already has line items.
        if (!self::shouldUsePreviewCartSnapshot($context, $snapshot)) {
            return self::appendFirstProductNameToSnapshot($snapshot);
        }

        return self::buildPreviewSnapshot($context);
    }

    /**
     * Resolve formatted cart total (line items total, excludes shipping/tax).
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @return string Formatted price or empty string.
     * @since 2.3.5
     */
    public static function resolveTotal(array $context): string
    {
        // Read numeric total from the snapshot.
        $snapshot = self::getSnapshot($context);
        $total    = isset($snapshot['total']) ? (float) $snapshot['total'] : 0.0;

        // Use preview sample when cart is empty in preview mode.
        if ($total <= 0 && self::isPreviewMode($context)) {
            return function_exists('wc_price') ? wc_price((float) self::PREVIEW_FALLBACKS['cart_total']) : self::PREVIEW_FALLBACKS['cart_total'];
        }

        // Return formatted WooCommerce price when total is positive.
        if ($total > 0 && function_exists('wc_price')) {
            return wc_price($total);
        }

        return '';
    }

    /**
     * Resolve formatted cart subtotal before discounts.
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @return string Formatted price or empty string.
     * @since 2.3.5
     */
    public static function resolveSubtotal(array $context): string
    {
        // Try live WooCommerce cart for accurate subtotal.
        $subtotal = self::getLiveCartFloat('get_subtotal');

        // Fall back to snapshot total when live cart is unavailable.
        if ($subtotal <= 0) {
            $snapshot = self::getSnapshot($context);
            $subtotal = isset($snapshot['total']) ? (float) $snapshot['total'] : 0.0;
        }

        // Preview sample when empty in preview mode.
        if ($subtotal <= 0 && self::isPreviewMode($context)) {
            return function_exists('wc_price') ? wc_price((float) self::PREVIEW_FALLBACKS['cart_subtotal']) : self::PREVIEW_FALLBACKS['cart_subtotal'];
        }

        if ($subtotal > 0 && function_exists('wc_price')) {
            return wc_price($subtotal);
        }

        return '';
    }

    /**
     * Resolve formatted cart discount total.
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @return string Formatted price or empty string.
     * @since 2.3.5
     */
    public static function resolveDiscount(array $context): string
    {
        // Read discount from the live cart when possible.
        $discount = self::getLiveCartFloat('get_discount_total');

        // Preview sample when no discount and preview mode is active.
        if ($discount <= 0 && self::isPreviewMode($context)) {
            return function_exists('wc_price') ? wc_price((float) self::PREVIEW_FALLBACKS['cart_discount']) : self::PREVIEW_FALLBACKS['cart_discount'];
        }

        if ($discount > 0 && function_exists('wc_price')) {
            return wc_price($discount);
        }

        return '';
    }

    /**
     * Resolve total quantity of items in the cart.
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @return string Item count as string.
     * @since 2.3.5
     */
    public static function resolveItemCount(array $context): string
    {
        // Read item count from snapshot.
        $snapshot   = self::getSnapshot($context);
        $itemCount = isset($snapshot['item_count']) ? (int) $snapshot['item_count'] : 0;

        // Preview sample when cart is empty in preview mode.
        if ($itemCount <= 0 && self::isPreviewMode($context)) {
            return self::PREVIEW_FALLBACKS['cart_item_count'];
        }

        return $itemCount > 0 ? (string) $itemCount : '';
    }

    /**
     * Resolve count of unique product IDs in the cart.
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @return string Unique product count as string.
     * @since 2.3.5
     */
    public static function resolveUniqueProductCount(array $context): string
    {
        // Count unique product IDs from snapshot.
        $snapshot   = self::getSnapshot($context);
        $productIds = isset($snapshot['product_ids']) && is_array($snapshot['product_ids'])
            ? $snapshot['product_ids']
            : [];
        $count      = count($productIds);

        // Preview sample when empty in preview mode.
        if ($count <= 0 && self::isPreviewMode($context)) {
            return self::PREVIEW_FALLBACKS['cart_unique_products'];
        }

        return $count > 0 ? (string) $count : '';
    }

    /**
     * Resolve applied coupon codes as a comma-separated list.
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @return string Coupon codes or empty string.
     * @since 2.3.5
     */
    public static function resolveCoupons(array $context): string
    {
        // Read coupon list from snapshot.
        $snapshot = self::getSnapshot($context);
        $coupons  = isset($snapshot['coupons']) && is_array($snapshot['coupons'])
            ? array_filter(array_map('strval', $snapshot['coupons']))
            : [];

        // Preview sample when no coupons in preview mode.
        if (empty($coupons) && self::isPreviewMode($context)) {
            return self::PREVIEW_FALLBACKS['cart_coupons'];
        }

        return !empty($coupons) ? implode(', ', $coupons) : '';
    }

    /**
     * Resolve the cart page URL.
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @return string Escaped-ready cart URL.
     * @since 2.3.5
     */
    public static function resolveCartUrl(array $context): string
    {
        // Use WooCommerce cart URL when available.
        if (function_exists('wc_get_cart_url')) {
            $url = wc_get_cart_url();
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        // Preview fallback uses home URL.
        if (self::isPreviewMode($context)) {
            return home_url('/cart/');
        }

        return '';
    }

    /**
     * Resolve the checkout page URL.
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @return string Escaped-ready checkout URL.
     * @since 2.3.5
     */
    public static function resolveCheckoutUrl(array $context): string
    {
        // Use WooCommerce checkout URL when available.
        if (function_exists('wc_get_checkout_url')) {
            $url = wc_get_checkout_url();
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        // Preview fallback uses home URL.
        if (self::isPreviewMode($context)) {
            return home_url('/checkout/');
        }

        return '';
    }

    /**
     * Resolve the name of the first product line in the cart.
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @return string Product name or empty string.
     * @since 2.3.5
     */
    public static function resolveFirstProductName(array $context): string
    {
        // Walk live cart line items for the first product name.
        if (self::hasLiveCart()) {
            foreach (WC()->cart->get_cart() as $cartItem) {
                $product = $cartItem['data'] ?? null;
                if (is_object($product) && method_exists($product, 'get_name')) {
                    $name = $product->get_name();
                    if (is_string($name) && $name !== '') {
                        return $name;
                    }
                }
            }
        }

        // Read a precomputed name from the cart snapshot when available.
        $snapshot = self::getSnapshot($context);
        if (!empty($snapshot['first_product_name']) && is_string($snapshot['first_product_name'])) {
            return $snapshot['first_product_name'];
        }

        // Resolve the first product ID from the snapshot when possible.
        $productIds = isset($snapshot['product_ids']) && is_array($snapshot['product_ids'])
            ? array_filter(array_map('absint', $snapshot['product_ids']))
            : [];
        if (!empty($productIds) && function_exists('wc_get_product')) {
            $product = wc_get_product((int) $productIds[0]);
            if ($product && method_exists($product, 'get_name')) {
                $name = $product->get_name();
                if (is_string($name) && $name !== '') {
                    return $name;
                }
            }
        }

        // Use the preview product DTO name when the builder provides one.
        if (self::isPreviewMode($context)) {
            if (isset($context['product']) && is_object($context['product']) && method_exists($context['product'], 'getName')) {
                $name = $context['product']->getName();
                if (is_string($name) && $name !== '') {
                    return $name;
                }
            }

            return __('Sample Product', 'notifal');
        }

        return '';
    }

    /**
     * Check whether the current request is in preview mode.
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @return bool True when preview mode is active.
     * @since 2.3.5
     */
    public static function isPreviewMode(array $context): bool
    {
        return isset($context['is_preview']) && $context['is_preview'] === true;
    }

    /**
     * Read a numeric cart value from the live WooCommerce cart instance.
     *
     * @param string $method WooCommerce cart method name.
     * @return float Numeric cart value.
     * @since 2.3.5
     */
    private static function getLiveCartFloat(string $method): float
    {
        // Return zero when live cart is unavailable.
        if (!self::hasLiveCart() || !method_exists(WC()->cart, $method)) {
            return 0.0;
        }

        $value = WC()->cart->{$method}();

        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * Determine whether a usable WooCommerce cart instance exists.
     *
     * @return bool True when WC()->cart is available.
     * @since 2.3.5
     */
    private static function hasLiveCart(): bool
    {
        return PluginDetector::isWooCommerceActive()
            && function_exists('WC')
            && WC()->cart !== null
            && !WC()->cart->is_empty();
    }

    /**
     * Determine whether preview mode should replace an empty cart snapshot.
     *
     * @param array<string, mixed> $context  Tag resolution context.
     * @param array<string, mixed> $snapshot Current cart snapshot.
     * @return bool True when sample cart data should be used.
     * @since 2.4.0
     */
    private static function shouldUsePreviewCartSnapshot(array $context, array $snapshot): bool
    {
        if (!self::isPreviewMode($context)) {
            return false;
        }

        if (!empty($snapshot['is_empty'])) {
            return true;
        }

        return (int) ($snapshot['item_count'] ?? 0) <= 0;
    }

    /**
     * Build a sample cart snapshot for admin/builder previews.
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @return array<string, mixed> Sample cart snapshot.
     * @since 2.4.0
     */
    private static function buildPreviewSnapshot(array $context): array
    {
        // Prefer the preview product name so cart tags match product tags visually.
        $productName = self::PREVIEW_FALLBACKS['cart_first_product_name'];
        $productId   = 0;

        if (isset($context['product']) && is_object($context['product'])) {
            if (method_exists($context['product'], 'getName')) {
                $name = $context['product']->getName();
                if (is_string($name) && $name !== '') {
                    $productName = $name;
                }
            }

            if (method_exists($context['product'], 'getId')) {
                $productId = absint($context['product']->getId());
            }
        }

        return [
            'is_empty'           => false,
            'item_count'         => (int) self::PREVIEW_FALLBACKS['cart_item_count'],
            'total'              => (float) self::PREVIEW_FALLBACKS['cart_total'],
            'product_ids'        => $productId > 0 ? [$productId] : [],
            'cart_lines'         => $productId > 0
                ? [['product_id' => $productId, 'variation_id' => 0]]
                : [],
            'category_ids'       => [],
            'coupons'            => [strtolower(self::PREVIEW_FALLBACKS['cart_coupons'])],
            'first_product_name' => $productName,
        ];
    }

    /**
     * Attach the first cart line product name to a snapshot when missing.
     *
     * @param array<string, mixed> $snapshot Cart snapshot to enrich.
     * @return array<string, mixed> Snapshot with `first_product_name` when resolvable.
     * @since 2.4.0
     */
    private static function appendFirstProductNameToSnapshot(array $snapshot): array
    {
        if (!empty($snapshot['first_product_name'])) {
            return $snapshot;
        }

        $productIds = isset($snapshot['product_ids']) && is_array($snapshot['product_ids'])
            ? array_filter(array_map('absint', $snapshot['product_ids']))
            : [];

        if (empty($productIds) || !function_exists('wc_get_product')) {
            return $snapshot;
        }

        $product = wc_get_product((int) $productIds[0]);
        if ($product && method_exists($product, 'get_name')) {
            $name = $product->get_name();
            if (is_string($name) && $name !== '') {
                $snapshot['first_product_name'] = $name;
            }
        }

        return $snapshot;
    }
}
