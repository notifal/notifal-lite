<?php

namespace Notifal\Domain\Orders\Services;

use Automattic\WooCommerce\Utilities\OrderUtil;

defined('ABSPATH') || exit;

/**
 * Class OrderMetaResolver
 *
 * Resolves order meta data with compatibility for both legacy storage
 * (WordPress posts storage) and HPOS (High-performance order storage).
 *
 * This service provides a unified interface for retrieving order meta data
 * regardless of the underlying WooCommerce storage method.
 *
 * @package Notifal\Domain\Orders\Services
 * @author Hossein <hossein@notifal.com>
 * @since 2.0.0
 */
class OrderMetaResolver
{
    /**
     * Mapping of common meta keys to their corresponding WooCommerce getter methods.
     *
     * @var array
     */
    private const META_KEY_TO_GETTER_MAP = [
        // Billing fields
        'billing_first_name'  => 'get_billing_first_name',
        '_billing_first_name' => 'get_billing_first_name',
        'billing_last_name'   => 'get_billing_last_name',
        '_billing_last_name'  => 'get_billing_last_name',
        'billing_company'     => 'get_billing_company',
        '_billing_company'    => 'get_billing_company',
        'billing_email'       => 'get_billing_email',
        '_billing_email'      => 'get_billing_email',
        'billing_phone'       => 'get_billing_phone',
        '_billing_phone'      => 'get_billing_phone',
        'billing_country'     => 'get_billing_country',
        '_billing_country'    => 'get_billing_country',
        'billing_address_1'   => 'get_billing_address_1',
        '_billing_address_1'  => 'get_billing_address_1',
        'billing_address_2'   => 'get_billing_address_2',
        '_billing_address_2'  => 'get_billing_address_2',
        'billing_city'        => 'get_billing_city',
        '_billing_city'       => 'get_billing_city',
        'billing_state'       => 'get_billing_state',
        '_billing_state'      => 'get_billing_state',
        'billing_postcode'    => 'get_billing_postcode',
        '_billing_postcode'   => 'get_billing_postcode',

        // Shipping fields
        'shipping_first_name'  => 'get_shipping_first_name',
        '_shipping_first_name' => 'get_shipping_first_name',
        'shipping_last_name'   => 'get_shipping_last_name',
        '_shipping_last_name'  => 'get_shipping_last_name',
        'shipping_company'     => 'get_shipping_company',
        '_shipping_company'    => 'get_shipping_company',
        'shipping_country'     => 'get_shipping_country',
        '_shipping_country'    => 'get_shipping_country',
        'shipping_address_1'   => 'get_shipping_address_1',
        '_shipping_address_1'  => 'get_shipping_address_1',
        'shipping_address_2'   => 'get_shipping_address_2',
        '_shipping_address_2'  => 'get_shipping_address_2',
        'shipping_city'        => 'get_shipping_city',
        '_shipping_city'       => 'get_shipping_city',
        'shipping_state'       => 'get_shipping_state',
        '_shipping_state'      => 'get_shipping_state',
        'shipping_postcode'    => 'get_shipping_postcode',
        '_shipping_postcode'   => 'get_shipping_postcode',

        // Order fields
        'order_total'          => 'get_total',
        '_order_total'         => 'get_total',
        'order_subtotal'       => 'get_subtotal',
        '_order_subtotal'      => 'get_subtotal',
        'order_tax'            => 'get_total_tax',
        '_order_tax'           => 'get_total_tax',
        'order_shipping'       => 'get_shipping_total',
        '_order_shipping'      => 'get_shipping_total',
        'order_discount'       => 'get_total_discount',
        '_order_discount'      => 'get_total_discount',
        'order_currency'       => 'get_currency',
        '_order_currency'      => 'get_currency',
        'payment_method'       => 'get_payment_method',
        '_payment_method'      => 'get_payment_method',
        'payment_method_title' => 'get_payment_method_title',
        '_payment_method_title'=> 'get_payment_method_title',
        'transaction_id'       => 'get_transaction_id',
        '_transaction_id'      => 'get_transaction_id',
        'customer_ip_address'  => 'get_customer_ip_address',
        '_customer_ip_address' => 'get_customer_ip_address',
        'customer_user_agent'  => 'get_customer_user_agent',
        '_customer_user_agent' => 'get_customer_user_agent',
        'customer_id'          => 'get_customer_id',
        '_customer_id'         => 'get_customer_id',
        'order_key'            => 'get_order_key',
        '_order_key'           => 'get_order_key',
        'order_status'         => 'get_status',
        '_order_status'        => 'get_status',

        // Date fields
        'date_created'         => 'get_date_created',
        '_date_created'        => 'get_date_created',
        'date_modified'        => 'get_date_modified',
        '_date_modified'       => 'get_date_modified',
        'date_completed'       => 'get_date_completed',
        '_date_completed'      => 'get_date_completed',
        'date_paid'            => 'get_date_paid',
        '_date_paid'           => 'get_date_paid',
    ];

    /**
     * Check if High-Performance Order Storage (HPOS) is enabled.
     *
     * @return bool True if HPOS is enabled, false otherwise.
     * @since 2.0.0
     */
    private function isHposEnabled(): bool
    {
        // Check if OrderUtil class exists (WooCommerce 7.1+)
        if (!class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            return false;
        }

        // Check if HPOS is enabled
        return OrderUtil::custom_orders_table_usage_is_enabled();
    }

    /**
     * Resolve meta value for an order.
     *
     * This method handles both HPOS and legacy storage automatically by:
     * 1. Using WooCommerce native getter methods when available (works for both storage types)
     * 2. Falling back to direct meta access for custom fields
     *
     * @param int    $orderId The order ID.
     * @param string $metaKey The meta key to retrieve.
     * @return mixed|null Meta value or null if not found.
     * @since 2.0.0
     */
    public function resolve(int $orderId, string $metaKey)
    {
        // Get WooCommerce order object
        $order = wc_get_order($orderId);

        if (!$order) {
            return null;
        }

        // Check if we have a getter method for this meta key
        if (isset(self::META_KEY_TO_GETTER_MAP[$metaKey])) {
            $getterMethod = self::META_KEY_TO_GETTER_MAP[$metaKey];

            // Call the getter method if it exists
            if (method_exists($order, $getterMethod)) {
                $value = $order->{$getterMethod}();

                // Handle WC_DateTime objects (returned by date getters)
                if ($value instanceof \WC_DateTime) {
                    return $value->getTimestamp();
                }

                return $value;
            }
        }

        // For custom meta fields or fields without specific getters,
        // use WooCommerce's get_meta() which handles both storage types
        $value = $order->get_meta($metaKey, true);

        // If no value found and key doesn't have underscore prefix, try with prefix
        if (empty($value) && strpos($metaKey, '_') !== 0) {
            $value = $order->get_meta('_' . $metaKey, true);
        }

        return $value;
    }

    /**
     * Check if a meta key is a standard WooCommerce field.
     *
     * @param string $metaKey The meta key to check.
     * @return bool True if it's a standard WooCommerce field, false otherwise.
     * @since 2.0.0
     */
    public function isStandardField(string $metaKey): bool
    {
        return isset(self::META_KEY_TO_GETTER_MAP[$metaKey]);
    }

    /**
     * Get all supported meta keys.
     *
     * @return array List of all supported meta keys.
     * @since 2.0.0
     */
    public function getSupportedMetaKeys(): array
    {
        return array_keys(self::META_KEY_TO_GETTER_MAP);
    }
}
