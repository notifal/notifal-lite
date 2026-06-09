<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Rules;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;

defined('ABSPATH') || exit;

/**
 * Builds a WooCommerce cart snapshot for display rule evaluation.
 *
 * @since 2.3.5
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Application\Services\Rules
 */
class WooCommerceCartContextBuilder
{
    /**
     * Register hooks that inject cart context into frontend and API payloads.
     *
     * @return void
     * @since 2.3.5
     */
    public static function register(): void
    {
        // Merge cart snapshot whenever frontend page context is built.
        add_filter(FilterHooks::ONPAGE_FRONTEND_CONTEXT, [self::class, 'mergeIntoContext'], 10, 1);
    }

    /**
     * Append cart snapshot keys to an existing context array.
     *
     * @param array<string, mixed> $context Existing page context.
     * @return array<string, mixed> Context with `cart` key when WooCommerce is active.
     * @since 2.3.5
     */
    public static function mergeIntoContext(array $context): array
    {
        // Skip when WooCommerce is unavailable.
        if (!PluginDetector::isWooCommerceActive()) {
            return $context;
        }

        // Skip cart snapshot work when no active notification evaluates cart display rules.
        if (!CartDisplayRulesUsageChecker::anyActiveNotificationUsesCartRules()) {
            return $context;
        }

        // Attach normalized cart snapshot for rule matchers.
        $context['cart'] = self::build();

        /**
         * Filter the WooCommerce cart snapshot used for display rule evaluation.
         *
         * @since 2.3.5
         * @param array<string, mixed> $cart    Cart snapshot.
         * @param array<string, mixed> $context Full page context.
         */
        $context['cart'] = apply_filters(FilterHooks::ONPAGE_WOOCOMMERCE_CART_CONTEXT, $context['cart'], $context);

        return $context;
    }

    /**
     * Build a normalized cart snapshot from the current WooCommerce session cart.
     *
     * @return array<string, mixed> Cart snapshot safe for rule evaluation.
     * @since 2.3.5
     */
    public static function build(): array
    {
        // Return empty snapshot when WooCommerce is not active.
        if (!PluginDetector::isWooCommerceActive() || !function_exists('WC')) {
            return self::emptySnapshot();
        }

        // Load cart and sync from session (required after Ajax add-to-cart on REST).
        if (!self::ensureCartReady()) {
            return self::emptySnapshot();
        }

        $cart = WC()->cart;

        // Collect product and variation IDs present in the cart.
        $productIds = [];
        // Per-line parent/variation pairs for accurate client-side product rules.
        $cartLines = [];
        // Collect product category term IDs referenced by cart line items.
        $categoryIds = [];

        foreach ($cart->get_cart() as $cartItem) {
            // Parent product ID for simple and variable products.
            $productId = isset($cartItem['product_id']) ? absint($cartItem['product_id']) : 0;
            // Variation ID when the line item is a variation.
            $variationId = isset($cartItem['variation_id']) ? absint($cartItem['variation_id']) : 0;

            // Store line pair so the frontend can match parent vs variation without expanding siblings.
            $cartLines[] = [
                'product_id'   => $productId,
                'variation_id' => $variationId,
            ];

            if ($productId > 0) {
                $productIds[] = $productId;
            }

            if ($variationId > 0) {
                $productIds[] = $variationId;
            }

            // Resolve categories for the parent product (variations inherit product_cat).
            if ($productId > 0) {
                $terms = wp_get_post_terms($productId, 'product_cat', ['fields' => 'ids']);
                if (is_array($terms)) {
                    foreach ($terms as $termId) {
                        $categoryIds[] = absint($termId);
                    }
                }
            }
        }

        // Cart contents total reflects discounts on line items (excludes shipping/tax).
        $total = (float) $cart->get_cart_contents_total();

        return [
            'is_empty'     => $cart->is_empty(),
            'item_count'   => (int) $cart->get_cart_contents_count(),
            'total'        => $total,
            'product_ids'  => array_values(array_unique(array_filter($productIds))),
            'cart_lines'   => $cartLines,
            'category_ids' => array_values(array_unique(array_filter($categoryIds))),
            'coupons'      => array_map('strtolower', array_map('strval', $cart->get_applied_coupons())),
        ];
    }

    /**
     * Ensure WooCommerce session and cart reflect the latest visitor session.
     *
     * WooCommerce often skips full cart init on REST. After Ajax add-to-cart the in-memory
     * cart can be empty until line items are reloaded from the session.
     *
     * @return bool True when a cart instance is available.
     * @since 2.3.5
     */
    private static function ensureCartReady(): bool
    {
        if (!did_action('before_woocommerce_init')) {
            return false;
        }

        if (null === WC()->cart) {
            if (function_exists('wc_load_cart')) {
                wc_load_cart();
            }
        } else {
            // Cart object exists but may be stale on REST — reload from session.
            self::reloadCartFromSession();
        }

        return null !== WC()->cart;
    }

    /**
     * Reload cart contents from the customer session (REST/AJAX after cart changes).
     *
     * @return void
     * @since 2.3.5
     */
    private static function reloadCartFromSession(): void
    {
        $isRestOrAjax = (defined('REST_REQUEST') && REST_REQUEST) || wp_doing_ajax();

        if (!$isRestOrAjax || null === WC()->cart) {
            return;
        }

        if (null === WC()->session && method_exists(WC(), 'initialize_session')) {
            WC()->initialize_session();
        }

        if (method_exists(WC()->cart, 'get_cart_from_session')) {
            WC()->cart->get_cart_from_session();
        }

        if (method_exists(WC()->cart, 'calculate_totals')) {
            WC()->cart->calculate_totals();
        }
    }

    /**
     * Default snapshot representing an empty cart.
     *
     * @return array<string, mixed> Empty cart structure.
     * @since 2.3.5
     */
    public static function emptySnapshot(): array
    {
        return [
            'is_empty'     => true,
            'item_count'   => 0,
            'total'        => 0.0,
            'product_ids'  => [],
            'cart_lines'   => [],
            'category_ids' => [],
            'coupons'      => [],
        ];
    }
}
