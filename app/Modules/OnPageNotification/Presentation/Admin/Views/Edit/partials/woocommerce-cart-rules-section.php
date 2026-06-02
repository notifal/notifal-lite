<?php

use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\WooCommerceCartDisplayRulesService;
use Notifal\Shared\AdminUI\Fields\FieldRenderer;

defined('ABSPATH') || exit;

/**
 * WooCommerce Cart display rule admin section.
 *
 * Rendered only when WooCommerce is active. Provides a single condition selector
 * with contextual fields so admins are not overwhelmed by irrelevant options.
 *
 * @since 2.3.5
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Views\Edit\partials
 */

// Section is omitted entirely when WooCommerce is not installed.
if (!PluginDetector::isWooCommerceActive()) {
    return;
}
?>

<!-- WooCommerce Cart — @since 2.3.5 -->
<div class="notifal-display-condition-section notifal-display-woocommerce_cart notifal-hidden">
    <?php
    FieldRenderer::select(
        'target_cart_condition',
        WooCommerceCartDisplayRulesService::getConditionOptions(),
        'cart_not_empty',
        __('Cart Condition', 'notifal'),
        __('Choose when this notification should appear based on the visitor\'s WooCommerce cart. Cart conditions are evaluated in real time, notifications may show or hide when items are added or removed.', 'notifal')
    );
    ?>

    <!-- Product picker — shown for product_in_cart / product_not_in_cart -->
    <div class="notifal-cart-rule-field notifal-cart-rule-products notifal-hidden">
        <?php
        FieldRenderer::ajaxSearch(
            'target_cart_products',
            [],
            __('Select Products', 'notifal'),
            __('Search and select products to match against the cart.', 'notifal'),
            'product'
        );
        ?>
    </div>

    <!-- Category picker — shown for category_in_cart -->
    <div class="notifal-cart-rule-field notifal-cart-rule-categories notifal-hidden">
        <?php
        FieldRenderer::ajaxSearch(
            'target_cart_categories',
            [],
            __('Select Product Categories', 'notifal'),
            __('Search and select product categories. The rule matches when any cart item belongs to one of these categories.', 'notifal'),
            'product_cat'
        );
        ?>
    </div>

    <!-- Numeric comparison — shown for cart_total / cart_item_count -->
    <div class="notifal-cart-rule-field notifal-cart-rule-comparison notifal-hidden">
        <?php
        FieldRenderer::select(
            'target_cart_operator',
            WooCommerceCartDisplayRulesService::getOperatorOptions(),
            'gt',
            __('Comparison', 'notifal'),
            __('Compare the cart value or item count using greater than, less than, or equal to.', 'notifal')
        );

        FieldRenderer::numberInput(
            'target_cart_value',
            '',
            __('Value', 'notifal'),
            __('Enter a number only. For Cart total, it is compared to the cart subtotal (products in cart, before shipping and tax). For Cart item count, it is the number of items.', 'notifal'),
            [
                'input' => [
                    'min'  => '0',
                    'step' => 'any',
                ],
            ]
        );
        ?>
    </div>

    <!-- Optional coupon code — shown for coupon_applied -->
    <div class="notifal-cart-rule-field notifal-cart-rule-coupon notifal-hidden">
        <?php
        FieldRenderer::textInput(
            'target_cart_coupon_code',
            '',
            __('Coupon Code (optional)', 'notifal'),
            __('Leave empty to match any applied coupon, or enter a specific coupon code.', 'notifal')
        );
        ?>
    </div>
</div>
