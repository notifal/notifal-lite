<?php

namespace Notifal\Modules\Templates\Application\Services;

use Notifal\Domain\Products\DTO\ProductDTO;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;

defined('ABSPATH') || exit;

/**
 * Class CountdownDateResolver
 *
 * Resolves countdown due dates dynamically from product sale end dates.
 * Provides context-aware date resolution for Elementor Pro Countdown widgets.
 *
 * @package Notifal\Modules\Templates\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class CountdownDateResolver
{
    /**
     * Get the product sale end date for countdown widget.
     *
     * Retrieves the WooCommerce product sale end date (_sale_price_dates_to meta)
     * and formats it for use in countdown widgets.
     *
     * @param ProductDTO $product Product DTO object
     * @return string|null ISO 8601 formatted date string or null if no sale date
     * @since 2.0.0
     */
    public static function getProductSaleEndDate(ProductDTO $product)
    {
        // Check if WooCommerce is active
        if (!PluginDetector::isWooCommerceActive()) {
            return null;
        }

        $productId = $product->getId();
        
        // Get WooCommerce product object
        $wcProduct = wc_get_product($productId);
        
        if (!$wcProduct) {
            return null;
        }
        
        // Get sale end date timestamp
        $saleEndDate = $wcProduct->get_date_on_sale_to();
        
        if (!$saleEndDate) {
            return null;
        }
        
        // Convert to timestamp for countdown widget
        // Elementor expects a Unix timestamp
        return $saleEndDate->getTimestamp();
    }

    /**
     * Get countdown due date from context with fallback.
     *
     * Attempts to get product sale end date from context, falls back to default date.
     *
     * @param array|null $context Widget context from WidgetContextProvider
     * @param string|int|null $defaultDate Default date (timestamp or date string) to use if no product context
     * @return string|int|null Timestamp for countdown or null if no date available
     * @since 2.0.0
     */
    public static function resolveCountdownDate($context, $defaultDate = null)
    {
        try {
            // Check if we have product context
            if (!$context || !isset($context['product'])) {
                return $defaultDate;
            }

            $product = $context['product'];
            
            // Validate product is ProductDTO
            if (!($product instanceof ProductDTO)) {
                return $defaultDate;
            }

            // Try to get product sale end date
            $productSaleDate = self::getProductSaleEndDate($product);
            
            // Return product sale date if available, otherwise fall back to default
            return $productSaleDate !== null ? $productSaleDate : $defaultDate;
            
        } catch (\Exception $e) {
            // Silently return default date for production stability
            return $defaultDate;
        }
    }

    /**
     * Check if product has a sale end date.
     *
     * @param ProductDTO $product Product DTO object
     * @return bool True if product has sale end date, false otherwise
     * @since 2.0.0
     */
    public static function hasProductSaleEndDate(ProductDTO $product)
    {
        return self::getProductSaleEndDate($product) !== null;
    }

    /**
     * Get formatted sale end date for display purposes.
     *
     * @param ProductDTO $product Product DTO object
     * @param string $format Date format (default: WordPress date format)
     * @return string Formatted date string or empty string if no date
     * @since 2.0.0
     */
    public static function getFormattedSaleEndDate(ProductDTO $product, $format = '')
    {
        $timestamp = self::getProductSaleEndDate($product);
        
        if ($timestamp === null) {
            return '';
        }

        // Use WordPress date format if not specified
        if (empty($format)) {
            $format = get_option('date_format') . ' ' . get_option('time_format');
        }

        return date_i18n($format, $timestamp);
    }

    /**
     * Get debug information about product sale dates.
     *
     * Provides comprehensive information for debugging countdown date issues.
     *
     * @param ProductDTO $product Product DTO object
     * @return array Debug information
     * @since 2.0.0
     */
    public static function getDebugInfo(ProductDTO $product)
    {
        $info = [
            'product_id' => $product->getId(),
            'product_name' => $product->getName(),
            'has_sale_date' => false,
            'sale_end_timestamp' => null,
            'sale_end_formatted' => '',
            'is_on_sale' => false,
            'woocommerce_active' => PluginDetector::isWooCommerceActive(),
        ];

        if (!PluginDetector::isWooCommerceActive()) {
            $info['error'] = 'WooCommerce not active';
            return $info;
        }

        $wcProduct = wc_get_product($product->getId());
        
        if (!$wcProduct) {
            $info['error'] = 'Product not found';
            return $info;
        }

        $info['is_on_sale'] = $wcProduct->is_on_sale();
        
        $saleEndDate = $wcProduct->get_date_on_sale_to();
        
        if ($saleEndDate) {
            $info['has_sale_date'] = true;
            $info['sale_end_timestamp'] = $saleEndDate->getTimestamp();
            $info['sale_end_formatted'] = self::getFormattedSaleEndDate($product);
        }

        return $info;
    }
}
