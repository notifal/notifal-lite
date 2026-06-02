<?php

namespace Notifal\Domain\Tags;

use Notifal\Domain\Tags\Enums\TagCategory;
use Notifal\Domain\Tags\Services\CartTagResolver;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;

defined('ABSPATH') || exit;

/**
 * Registers WooCommerce cart template tags when WooCommerce is active.
 *
 * @package Notifal\Domain\Tags
 * @since 2.3.5
 * @author Hossein <hossein@notifal.com>
 */
class RegisterWooCommerceCartTags
{
    /**
     * Register cart tags with the tag manager.
     *
     * @param TagManager $manager Tag manager instance.
     * @return void
     * @since 2.3.5
     */
    public static function register(TagManager $manager): void
    {
        // Skip registration when WooCommerce is not installed.
        if (!PluginDetector::isWooCommerceActive()) {
            return;
        }

        // -----------------------
        // Cart Tags
        // -----------------------

        $manager->registerTag(new Tag(
            'cart_total',
            __('Cart Total', 'notifal'),
            function ($context) {
                return CartTagResolver::resolveTotal($context);
            },
            TagCategory::CART,
            __('Displays the formatted cart contents total (excludes shipping and tax).', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'cart_subtotal',
            __('Cart Subtotal', 'notifal'),
            function ($context) {
                return CartTagResolver::resolveSubtotal($context);
            },
            TagCategory::CART,
            __('Displays the formatted cart subtotal before discounts.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'cart_discount',
            __('Cart Discount', 'notifal'),
            function ($context) {
                return CartTagResolver::resolveDiscount($context);
            },
            TagCategory::CART,
            __('Displays the formatted total discount applied to the cart.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'cart_item_count',
            __('Cart Item Count', 'notifal'),
            function ($context) {
                return CartTagResolver::resolveItemCount($context);
            },
            TagCategory::CART,
            __('Displays the total quantity of items in the cart.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'cart_unique_products',
            __('Cart Unique Products', 'notifal'),
            function ($context) {
                return CartTagResolver::resolveUniqueProductCount($context);
            },
            TagCategory::CART,
            __('Displays the number of unique products in the cart.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'cart_coupons',
            __('Cart Coupons', 'notifal'),
            function ($context) {
                return CartTagResolver::resolveCoupons($context);
            },
            TagCategory::CART,
            __('Displays applied coupon codes as a comma-separated list.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'cart_url',
            __('Cart URL', 'notifal'),
            function ($context) {
                return CartTagResolver::resolveCartUrl($context);
            },
            TagCategory::CART,
            __('Displays the WooCommerce cart page URL.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'cart_checkout_url',
            __('Checkout URL', 'notifal'),
            function ($context) {
                return CartTagResolver::resolveCheckoutUrl($context);
            },
            TagCategory::CART,
            __('Displays the WooCommerce checkout page URL.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'cart_first_product_name',
            __('First Cart Product Name', 'notifal'),
            function ($context) {
                return CartTagResolver::resolveFirstProductName($context);
            },
            TagCategory::CART,
            __('Displays the name of the first product in the cart.', 'notifal')
        ));
    }
}
