<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Settings;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\OnPageNotification\Application\Services\Rules\WooCommerceCartContextBuilder;
use WC_Product;

defined('ABSPATH') || exit;

/**
 * Resolves WooCommerce product IDs for cart-based content source filters.
 *
 * Supports cart line items plus related, upsell, and cross-sell products.
 * Toggle sources inside one cart condition are combined with OR logic.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services\Settings
 * @since 2.3.9
 * @author Hossein <hossein@notifal.com>
 */
class CartProductPoolResolver
{
    /**
     * Maximum number of product IDs returned per cart condition.
     *
     * @since 2.3.9
     */
    private const MAX_POOL_IDS = 50;

    /**
     * Cart toggle field names stored in filter condition data.
     *
     * @since 2.3.9
     */
    private const TOGGLE_FIELDS = [
        'cart_products',
        'related_cart_products',
        'upsell_cart_products',
        'cross_sell_cart_products',
    ];

    /**
     * Determine whether content source settings include a cart product filter.
     *
     * @param array<string, mixed> $contentSourceSettings Notification content source settings.
     * @return bool True when at least one enabled cart condition exists.
     * @since 2.3.9
     */
    public static function settingsContainCartFilter(array $contentSourceSettings): bool
    {
        // Cart filters require WooCommerce.
        if (!PluginDetector::isWooCommerceActive()) {
            return false;
        }

        // Read multi-filter product conditions first.
        $productFilters = $contentSourceSettings['product_filters'] ?? [];
        $conditions = is_array($productFilters['conditions'] ?? null) ? $productFilters['conditions'] : [];

        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                continue;
            }

            // Skip disabled conditions.
            if (empty($condition['enabled'])) {
                continue;
            }

            // Match cart filter type.
            if (($condition['type'] ?? '') === 'cart') {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve product IDs for one cart filter condition.
     *
     * @param array<string, mixed>      $condition    Built cart filter condition.
     * @param array<string, mixed>|null $cartSnapshot Optional cart snapshot override.
     * @return int[] Unique publishable product IDs.
     * @since 2.3.9
     */
    public static function resolve(array $condition, ?array $cartSnapshot = null): array
    {
        // Return empty set when WooCommerce is unavailable.
        if (!PluginDetector::isWooCommerceActive()) {
            return [];
        }

        // Read enabled toggles from the condition payload.
        $includeCartProducts = self::isToggleEnabled($condition, 'cart_products');
        $includeRelated = self::isToggleEnabled($condition, 'related_cart_products');
        $includeUpsell = self::isToggleEnabled($condition, 'upsell_cart_products');
        $includeCrossSell = self::isToggleEnabled($condition, 'cross_sell_cart_products');

        // Require at least one source toggle.
        if (!$includeCartProducts && !$includeRelated && !$includeUpsell && !$includeCrossSell) {
            return [];
        }

        // Build or reuse the cart snapshot.
        $cart = is_array($cartSnapshot) ? $cartSnapshot : WooCommerceCartContextBuilder::build();

        // Empty cart cannot contribute IDs.
        if (!empty($cart['is_empty'])) {
            return [];
        }

        $productIds = [];

        // Add cart line product or variation IDs when requested.
        if ($includeCartProducts) {
            $productIds = array_merge($productIds, self::resolveCartLineProductIds($cart));
        }

        // Related/upsell/cross-sell are resolved from parent cart products.
        if ($includeRelated || $includeUpsell || $includeCrossSell) {
            $productIds = array_merge(
                $productIds,
                self::resolveLinkedProductIds($cart, $includeRelated, $includeUpsell, $includeCrossSell)
            );
        }

        // Normalize, deduplicate, and cap pool size for performance.
        $productIds = self::normalizeProductIds($productIds);

        /**
         * Filter cart-derived product IDs before pool building.
         *
         * @since 2.3.9
         * @param int[]                $productIds Resolved product IDs.
         * @param array<string, mixed> $condition  Cart filter condition.
         * @param array<string, mixed> $cart      Cart snapshot.
         */
        $productIds = apply_filters(
            FilterHooks::ONPAGE_CART_PRODUCT_POOL_IDS,
            $productIds,
            $condition,
            $cart
        );

        return self::normalizeProductIds(is_array($productIds) ? $productIds : []);
    }

    /**
     * Extract cart line product IDs (variation ID preferred over parent ID).
     *
     * @param array<string, mixed> $cart Cart snapshot.
     * @return int[]
     * @since 2.3.9
     */
    private static function resolveCartLineProductIds(array $cart): array
    {
        $productIds = [];
        $lines = is_array($cart['cart_lines'] ?? null) ? $cart['cart_lines'] : [];

        // Prefer per-line pairs so variations stay accurate.
        if (!empty($lines)) {
            foreach ($lines as $line) {
                if (!is_array($line)) {
                    continue;
                }

                $variationId = absint($line['variation_id'] ?? 0);
                $productId = absint($line['product_id'] ?? 0);
                $resolvedId = $variationId > 0 ? $variationId : $productId;

                if ($resolvedId > 0) {
                    $productIds[] = $resolvedId;
                }
            }

            return $productIds;
        }

        // Fallback to flattened product_ids when cart_lines are unavailable.
        foreach ((array) ($cart['product_ids'] ?? []) as $productId) {
            $resolvedId = absint($productId);
            if ($resolvedId > 0) {
                $productIds[] = $resolvedId;
            }
        }

        return $productIds;
    }

    /**
     * Resolve related, upsell, or cross-sell IDs from cart parent products.
     *
     * @param array<string, mixed> $cart Cart snapshot.
     * @param bool                 $includeRelated Include related products.
     * @param bool                 $includeUpsell Include upsell products.
     * @param bool                 $includeCrossSell Include cross-sell products.
     * @return int[]
     * @since 2.3.9
     */
    private static function resolveLinkedProductIds(
        array $cart,
        bool $includeRelated,
        bool $includeUpsell,
        bool $includeCrossSell
    ): array {
        $productIds = [];
        $parentIds = self::resolveParentProductIds($cart);
        $loadedProducts = [];

        foreach ($parentIds as $parentId) {
            // Load each parent product once per request.
            if (!isset($loadedProducts[$parentId])) {
                $wcProduct = wc_get_product($parentId);
                $loadedProducts[$parentId] = $wcProduct instanceof WC_Product ? $wcProduct : null;
            }

            $wcProduct = $loadedProducts[$parentId];
            if (!$wcProduct instanceof WC_Product) {
                continue;
            }

            // Append related product IDs via WooCommerce core helper (not a WC_Product method).
            if ($includeRelated) {
                $productIds = array_merge($productIds, self::resolveRelatedProductIds($parentId));
            }

            // Append upsell product IDs.
            if ($includeUpsell) {
                $productIds = array_merge($productIds, array_map('absint', (array) $wcProduct->get_upsell_ids()));
            }

            // Append cross-sell product IDs.
            if ($includeCrossSell) {
                $productIds = array_merge($productIds, array_map('absint', (array) $wcProduct->get_cross_sell_ids()));
            }
        }

        return $productIds;
    }

    /**
     * Resolve related product IDs for one parent product.
     *
     * Uses {@see wc_get_related_products()} because related IDs are not exposed on WC_Product.
     *
     * @param int $parentId Parent product ID from the cart.
     * @return int[]
     * @since 2.3.9
     */
    private static function resolveRelatedProductIds(int $parentId): array
    {
        if ($parentId <= 0 || !function_exists('wc_get_related_products')) {
            return [];
        }

        $relatedIds = wc_get_related_products($parentId, self::MAX_POOL_IDS);

        return array_map('absint', is_array($relatedIds) ? $relatedIds : []);
    }

    /**
     * Collect unique parent product IDs from cart lines.
     *
     * @param array<string, mixed> $cart Cart snapshot.
     * @return int[]
     * @since 2.3.9
     */
    private static function resolveParentProductIds(array $cart): array
    {
        $parentIds = [];
        $lines = is_array($cart['cart_lines'] ?? null) ? $cart['cart_lines'] : [];

        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }

            $productId = absint($line['product_id'] ?? 0);
            if ($productId > 0) {
                $parentIds[] = $productId;
            }
        }

        // Fallback when only flattened IDs exist.
        if (empty($parentIds)) {
            foreach ((array) ($cart['product_ids'] ?? []) as $productId) {
                $resolvedId = absint($productId);
                if ($resolvedId > 0) {
                    $parentIds[] = $resolvedId;
                }
            }
        }

        return array_values(array_unique($parentIds));
    }

    /**
     * Determine whether a cart toggle is enabled in condition data.
     *
     * @param array<string, mixed> $condition Filter condition.
     * @param string               $field     Toggle field name.
     * @return bool
     * @since 2.3.9
     */
    private static function isToggleEnabled(array $condition, string $field): bool
    {
        if (!in_array($field, self::TOGGLE_FIELDS, true)) {
            return false;
        }

        // Support built conditions (top-level) and raw saved data (nested under data).
        if (array_key_exists($field, $condition)) {
            return !empty($condition[$field]);
        }

        $data = is_array($condition['data'] ?? null) ? $condition['data'] : [];

        return !empty($data[$field]);
    }

    /**
     * Normalize, deduplicate, validate, and cap product IDs.
     *
     * @param array<int, int|string> $productIds Raw product IDs.
     * @return int[]
     * @since 2.3.9
     */
    private static function normalizeProductIds(array $productIds): array
    {
        $normalized = [];

        foreach ($productIds as $productId) {
            $resolvedId = absint($productId);
            if ($resolvedId <= 0) {
                continue;
            }

            // Skip unpublished or missing products to keep the pool valid.
            $postStatus = get_post_status($resolvedId);
            if ($postStatus !== 'publish') {
                continue;
            }

            $normalized[] = $resolvedId;
        }

        $normalized = array_values(array_unique($normalized));

        if (count($normalized) > self::MAX_POOL_IDS) {
            $normalized = array_slice($normalized, 0, self::MAX_POOL_IDS);
        }

        return $normalized;
    }
}
