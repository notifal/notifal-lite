<?php

namespace Notifal\Modules\Templates\Presentation\Frontend\Controllers;

use Notifal\Infrastructure\WordPress\Support\PluginDetector;

defined('ABSPATH') || exit;

/**
 * Class AjaxAddToCartController
 *
 * Handles AJAX add-to-cart requests for the Action Button when link type is "Ajax Add to Cart".
 * Only registered when WooCommerce is active. Uses the notification context product (same as
 * replace tags / featured image) so the product added is the one currently shown in the notification.
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Presentation\Frontend\Controllers
 * @author Hossein <hossein@notifal.com>
 */
class AjaxAddToCartController
{
    /**
     * AJAX action name for add to cart.
     *
     * @var string
     * @since 2.0.0
     */
    public const AJAX_ACTION = 'notifal_ajax_add_to_cart';

    /**
     * Nonce action for verification.
     *
     * @var string
     * @since 2.0.0
     */
    public const NONCE_ACTION = 'notifal_ajax_add_to_cart';

    /**
     * Register AJAX handlers. Call only when WooCommerce is active.
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        if (!PluginDetector::isWooCommerceActive()) {
            return;
        }

        add_action('wp_ajax_' . self::AJAX_ACTION, [self::class, 'handle']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [self::class, 'handle']);
    }

    /**
     * Handle AJAX add to cart request.
     *
     * Expects product_id and quantity. Verifies nonce. Adds product to WooCommerce cart
     * and returns JSON. Triggers WooCommerce fragments refresh for cart widget updates.
     *
     * @return void
     * @since 2.0.0
     */
    public static function handle(): void
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error([
                'message' => __('Security check failed. Please refresh and try again.', 'notifal'),
            ]);
        }

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $quantity  = isset($_POST['quantity']) ? max(1, absint($_POST['quantity'])) : 1;

        if (!$product_id) {
            wp_send_json_error([
                'message' => __('Invalid product.', 'notifal'),
            ]);
        }

        if (!function_exists('WC') || !WC()->cart) {
            wp_send_json_error([
                'message' => __('Cart is not available.', 'notifal'),
            ]);
        }

        $product = wc_get_product($product_id);

        if (!$product || !$product->is_purchasable()) {
            wp_send_json_error([
                'message' => __('This product cannot be added to the cart.', 'notifal'),
            ]);
        }

        $variation_id = isset($_POST['variation_id']) ? absint(wp_unslash($_POST['variation_id'])) : 0;

        if ($product->is_type('variable')) {
            if ($variation_id <= 0) {
                wp_send_json_error([
                    'message' => __('This product has variations. Open the product page, choose your options, then add to cart again.', 'notifal'),
                    'code'    => 'variation_required',
                ]);
            }

            $variation_product = wc_get_product($variation_id);

            if (
                !$variation_product
                || !$variation_product->is_type('variation')
                || (int) $variation_product->get_parent_id() !== $product_id
            ) {
                wp_send_json_error([
                    'message' => __('Invalid variation for this product.', 'notifal'),
                    'code'    => 'invalid_variation',
                ]);
            }

            if (!$variation_product->is_purchasable() || !$variation_product->is_in_stock()) {
                wp_send_json_error([
                    'message' => __('This variation cannot be added to the cart.', 'notifal'),
                ]);
            }

            $variation_attributes = function_exists('wc_get_product_variation_attributes')
                ? wc_get_product_variation_attributes($variation_id)
                : $variation_product->get_attributes();

            $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation_attributes);
        } else {
            if (!$product->is_in_stock()) {
                wp_send_json_error([
                    'message' => __('This product cannot be added to the cart.', 'notifal'),
                ]);
            }
            $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity);
        }

        if ($cart_item_key === false) {
            wp_send_json_error([
                'message' => __('Could not add the product to the cart.', 'notifal'),
            ]);
        }

        do_action('woocommerce_ajax_added_to_cart', $product_id);

        $cart_url    = wc_get_cart_url();
        $checkout_url = wc_get_checkout_url();
        $cart_hash   = WC()->cart->get_cart_hash();

        ob_start();
        woocommerce_mini_cart();
        $mini_cart = ob_get_clean();
        $fragments = apply_filters('woocommerce_add_to_cart_fragments', [
            'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
        ]);

        wp_send_json_success([
            'message'      => __('Product added to cart.', 'notifal'),
            'cart_url'     => $cart_url,
            'checkout_url' => $checkout_url,
            'cart_hash'    => $cart_hash,
            'fragments'    => $fragments,
        ]);
    }
}
