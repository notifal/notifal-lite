<?php

namespace Notifal\Modules\Templates\Application\Services;

use Notifal\Domain\Products\DTO\ProductDTO;

defined('ABSPATH') || exit;

/**
 * Class ProductImageResolver
 *
 * Resolves the correct image for WooCommerce products and variations.
 * Handles fallback logic from variation images to parent product images.
 *
 * @package Notifal\Modules\Templates\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ProductImageResolver
{
    /**
     * Get the correct image ID for a product, handling variation fallbacks.
     *
     * For variations:
     * 1. Try to get variation-specific image
     * 2. If no variation image, fall back to parent product image
     * 
     * For simple/variable products:
     * 1. Return the product's featured image
     *
     * @param ProductDTO $product Product DTO object
     * @return int Image attachment ID (0 if no image found)
     * @since 2.0.0
     */
    public static function getProductImageId(ProductDTO $product): int
    {
        $productId = $product->getId();
        
        // Get WooCommerce product object
        $wcProduct = wc_get_product($productId);
        
        if (!$wcProduct) {
            return 0;
        }
        
        // Get the image ID using WooCommerce's built-in logic
        $imageId = $wcProduct->get_image_id();
        
        if ($imageId) {
            return $imageId;
        }
        
        // If no image found and this is a variation, try parent product
        if ($wcProduct->is_type('variation')) {
            $parentProduct = wc_get_product($wcProduct->get_parent_id());
            if ($parentProduct) {
                return $parentProduct->get_image_id();
            }
        }
        
        return 0;
    }
    
    /**
     * Get image HTML for a product with proper fallback handling.
     *
     * @param ProductDTO $product Product DTO object
     * @param string $size Image size (default: 'large')
     * @param array $attributes Additional HTML attributes
     * @return string Image HTML or WooCommerce placeholder
     * @since 2.0.0
     */
    public static function getProductImageHtml(ProductDTO $product, string $size = 'large', array $attributes = []): string
    {
        $imageId = self::getProductImageId($product);
        
        // Set default attributes
        $defaultAttributes = [
            'alt' => esc_attr($product->getName()),
            'loading' => 'lazy'
        ];
        
        $attributes = array_merge($defaultAttributes, $attributes);
        
        if (!$imageId) {
            // Use WooCommerce placeholder image as fallback
            return self::getWooCommercePlaceholderHtml($size, $attributes);
        }
        
        return wp_get_attachment_image($imageId, $size, false, $attributes);
    }
    
    /**
     * Get detailed image information for debugging.
     *
     * Provides comprehensive information about image resolution for variations.
     *
     * @param ProductDTO $product Product DTO object
     * @return array Debug information
     * @since 2.0.0
     */
    public static function getImageDebugInfo(ProductDTO $product): array
    {
        $productId = $product->getId();
        $wcProduct = wc_get_product($productId);
        
        $info = [
            'product_id' => $productId,
            'product_name' => $product->getName(),
            'product_type' => $wcProduct ? $wcProduct->get_type() : 'unknown',
            'variation_image_id' => 0,
            'parent_image_id' => 0,
            'resolved_image_id' => 0,
            'image_source' => 'none'
        ];
        
        if (!$wcProduct) {
            return $info;
        }
        
        // Get variation's own image
        $variationImageId = get_post_thumbnail_id($productId);
        $info['variation_image_id'] = $variationImageId;
        
        if ($wcProduct->is_type('variation')) {
            // Get parent product image
            $parentId = $wcProduct->get_parent_id();
            $parentImageId = get_post_thumbnail_id($parentId);
            $info['parent_image_id'] = $parentImageId;
            
            // Determine which image WooCommerce will use
            $resolvedImageId = $wcProduct->get_image_id();
            $info['resolved_image_id'] = $resolvedImageId;
            
            if ($resolvedImageId === $variationImageId && $variationImageId > 0) {
                $info['image_source'] = 'variation';
            } elseif ($resolvedImageId === $parentImageId && $parentImageId > 0) {
                $info['image_source'] = 'parent_fallback';
            } else {
                $info['image_source'] = 'unknown';
            }
        } else {
            // Simple or variable product
            $info['resolved_image_id'] = $wcProduct->get_image_id();
            $info['image_source'] = 'product';
        }
        
        return $info;
    }
    
    /**
     * Check if product has variation-specific image.
     *
     * @param ProductDTO $product Product DTO object
     * @return bool True if variation has its own image
     * @since 2.0.0
     */
    public static function hasVariationImage(ProductDTO $product): bool
    {
        $productId = $product->getId();
        $wcProduct = wc_get_product($productId);
        
        if (!$wcProduct || !$wcProduct->is_type('variation')) {
            return false;
        }
        
        $variationImageId = get_post_thumbnail_id($productId);
        return $variationImageId > 0;
    }
    
    /**
     * Get image URL for a product with proper fallback handling.
     *
     * @param ProductDTO $product Product DTO object
     * @param string $size Image size (default: 'large')
     * @return string Image URL or empty string if no image
     * @since 2.0.0
     */
    public static function getProductImageUrl(ProductDTO $product, string $size = 'large'): string
    {
        $imageId = self::getProductImageId($product);
        
        if (!$imageId) {
            return '';
        }
        
        $imageUrl = wp_get_attachment_image_url($imageId, $size);
        return $imageUrl ?: '';
    }
    
    /**
     * Get WooCommerce placeholder image HTML.
     *
     * @param string $size Image size (default: 'large')
     * @param array $attributes Additional HTML attributes
     * @return string Placeholder image HTML
     * @since 2.0.0
     */
    public static function getWooCommercePlaceholderHtml(string $size = 'large', array $attributes = []): string
    {
        // Get WooCommerce placeholder image URL
        $placeholderSrc = function_exists('wc_placeholder_img_src') 
            ? wc_placeholder_img_src($size) 
            : '';
            
        if (empty($placeholderSrc)) {
            // Fallback to WordPress default placeholder
            $placeholderSrc = includes_url('images/media/default.png');
        }
        
        // Set default attributes for placeholder
        $defaultAttributes = [
            'alt' => esc_attr__('Product placeholder', 'notifal'),
            'class' => 'notifal-product-image-placeholder',
            'loading' => 'lazy'
        ];
        
        $attributes = array_merge($defaultAttributes, $attributes);
        
        // Build attribute string
        $attributeString = '';
        foreach ($attributes as $key => $value) {
            $attributeString .= sprintf(' %s="%s"', esc_attr($key), esc_attr($value));
        }
        
        return sprintf('<img src="%s"%s />', esc_url($placeholderSrc), $attributeString);
    }
    
    /**
     * Get WooCommerce placeholder image when no product is available at all.
     *
     * @param string $size Image size (default: 'large')
     * @param array $attributes Additional HTML attributes
     * @return string Placeholder image HTML for template builders
     * @since 2.0.0
     */
    public static function getTemplatePlaceholderHtml(string $size = 'large', array $attributes = []): string
    {
        // Set placeholder attributes for template builders
        $defaultAttributes = [
            'alt' => esc_attr__('Product image placeholder', 'notifal'),
            'class' => 'notifal-product-image-placeholder notifal-template-placeholder',
            'loading' => 'lazy'
        ];
        
        $attributes = array_merge($defaultAttributes, $attributes);
        
        return self::getWooCommercePlaceholderHtml($size, $attributes);
    }
}
