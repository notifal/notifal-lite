<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Rules;

defined('ABSPATH') || exit;

/**
 * Evaluates WooCommerce cart display rule conditions against a cart snapshot.
 *
 * @since 2.3.5
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Application\Services\Rules
 */
class WooCommerceCartRulesMatcher
{
    /**
     * Check whether cart rule data matches the provided cart snapshot.
     *
     * @param array<string, mixed> $ruleData    Sanitized cart rule configuration.
     * @param array<string, mixed> $cartContext Cart snapshot from WooCommerceCartContextBuilder.
     * @return bool True when the condition matches.
     * @since 2.3.5
     */
    public static function matches(array $ruleData, array $cartContext): bool
    {
        // Resolve condition type with a safe default.
        $condition = isset($ruleData['condition']) ? (string) $ruleData['condition'] : 'cart_not_empty';

        switch ($condition) {
            case 'cart_empty':
                // Match when the cart has no line items.
                return !empty($cartContext['is_empty']);

            case 'cart_not_empty':
                // Match when at least one item is in the cart.
                return empty($cartContext['is_empty']);

            case 'product_in_cart':
                // Match when any selected product (or variation) is in the cart.
                return self::cartContainsAnyProduct($ruleData['product_ids'] ?? [], $cartContext);

            case 'product_not_in_cart':
                // Match when none of the selected products are in the cart.
                return !self::cartContainsAnyProduct($ruleData['product_ids'] ?? [], $cartContext);

            case 'category_in_cart':
                // Match when a product from any selected category is in the cart.
                return self::cartContainsAnyCategory($ruleData['category_ids'] ?? [], $cartContext);

            case 'cart_total':
                // Compare cart contents total using gt/lt/eq operators.
                return self::compareNumeric(
                    (float) ($cartContext['total'] ?? 0),
                    (string) ($ruleData['operator'] ?? 'gt'),
                    (float) ($ruleData['value'] ?? 0)
                );

            case 'cart_item_count':
                // Compare number of items in the cart.
                return self::compareNumeric(
                    (float) ($cartContext['item_count'] ?? 0),
                    (string) ($ruleData['operator'] ?? 'gt'),
                    (float) ($ruleData['value'] ?? 0)
                );

            case 'coupon_applied':
                // Match when any coupon or a specific coupon code is applied.
                return self::cartHasCoupon($ruleData, $cartContext);

            default:
                // Unknown condition — fail closed so misconfigured rules do not show everywhere.
                return false;
        }
    }

    /**
     * Determine whether the cart contains any of the given product IDs.
     *
     * @param array<int, int>      $targetProductIds Product or variation IDs from the rule.
     * @param array<string, mixed> $cartContext      Cart snapshot.
     * @return bool True when at least one target product is in the cart.
     * @since 2.3.5
     */
    private static function cartContainsAnyProduct(array $targetProductIds, array $cartContext): bool
    {
        $targets = array_map('absint', array_filter($targetProductIds));
        if (empty($targets)) {
            return false;
        }

        // Only IDs present on cart lines (parent + variation per line) — never all sibling variations.
        $cartLineProductIds = array_values(array_unique(array_map('absint', $cartContext['product_ids'] ?? [])));
        $cartLineProductIds = array_filter($cartLineProductIds);

        foreach ($targets as $targetId) {
            if (self::cartLineContainsTargetProduct($targetId, $cartLineProductIds)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether any cart line represents the target product (exact, variation, or variable parent).
     *
     * "Product NOT in cart" uses the inverse: show when this returns false for all targets.
     * Sibling variations of the same variable product do not count as a match for a specific variation target.
     *
     * @param int   $targetId           Product or variation ID from the display rule.
     * @param int[] $cartLineProductIds Product and variation IDs on current cart lines.
     * @return bool True when the target is considered present in the cart.
     * @since 2.3.5
     */
    private static function cartLineContainsTargetProduct(int $targetId, array $cartLineProductIds): bool
    {
        if ($targetId <= 0 || empty($cartLineProductIds)) {
            return false;
        }

        if (in_array($targetId, $cartLineProductIds, true)) {
            return true;
        }

        if (!function_exists('wc_get_product')) {
            return false;
        }

        $targetProduct = wc_get_product($targetId);
        if (!$targetProduct) {
            return in_array($targetId, $cartLineProductIds, true);
        }

        foreach ($cartLineProductIds as $cartLineId) {
            if ($targetId === $cartLineId) {
                return true;
            }

            $cartLineProduct = wc_get_product($cartLineId);
            if (!$cartLineProduct) {
                continue;
            }

            $cartLineIdValue = (int) $cartLineProduct->get_id();

            // Variable parent selected in rule — any variation line of that parent counts as in cart.
            if ($targetProduct->is_type('variable') && (int) $cartLineProduct->get_parent_id() === $targetId) {
                return true;
            }

            // Variation selected in rule — only that variation (or its parent line id), not siblings.
            $targetParentId = (int) $targetProduct->get_parent_id();
            if ($targetParentId > 0 && $cartLineIdValue === $targetId) {
                return true;
            }

            // Simple product match by shared catalog ID.
            if ($cartLineIdValue === $targetId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the cart contains a product from any selected category.
     *
     * @param array<int, int>      $targetCategoryIds Category term IDs from the rule.
     * @param array<string, mixed> $cartContext       Cart snapshot.
     * @return bool True when a cart line item belongs to a selected category.
     * @since 2.3.5
     */
    private static function cartContainsAnyCategory(array $targetCategoryIds, array $cartContext): bool
    {
        // Require at least one category for a meaningful match.
        $targets = array_map('absint', array_filter($targetCategoryIds));
        if (empty($targets)) {
            return false;
        }

        $cartCategoryIds = array_map('absint', $cartContext['category_ids'] ?? []);

        return !empty(array_intersect($targets, $cartCategoryIds));
    }

    /**
     * Compare a numeric cart value against a rule threshold.
     *
     * @param float  $actual   Current cart value.
     * @param string $operator Comparison operator: gt, lt, or eq.
     * @param float  $expected Rule threshold.
     * @return bool True when the comparison passes.
     * @since 2.3.5
     */
    private static function compareNumeric(float $actual, string $operator, float $expected): bool
    {
        switch ($operator) {
            case 'lt':
                return $actual < $expected;
            case 'eq':
                // Small epsilon for floating cart totals.
                return abs($actual - $expected) < 0.0001;
            case 'gt':
            default:
                return $actual > $expected;
        }
    }

    /**
     * Check whether the cart has an applied coupon matching the rule.
     *
     * @param array<string, mixed> $ruleData    Rule configuration (optional coupon_code).
     * @param array<string, mixed> $cartContext Cart snapshot with lowercase coupon codes.
     * @return bool True when coupon condition is satisfied.
     * @since 2.3.5
     */
    private static function cartHasCoupon(array $ruleData, array $cartContext): bool
    {
        $appliedCoupons = $cartContext['coupons'] ?? [];

        // No coupons applied — condition fails.
        if (empty($appliedCoupons)) {
            return false;
        }

        // Empty coupon_code means "any applied coupon".
        $couponCode = isset($ruleData['coupon_code']) ? strtolower(trim((string) $ruleData['coupon_code'])) : '';

        if ($couponCode === '') {
            return true;
        }

        return in_array($couponCode, $appliedCoupons, true);
    }
}
