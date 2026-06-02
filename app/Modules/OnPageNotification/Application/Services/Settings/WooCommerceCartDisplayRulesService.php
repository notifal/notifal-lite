<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Settings;

use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Configuration, validation, and formatting for WooCommerce cart display rules.
 *
 * @since 2.3.5
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Application\Services\Settings
 */
class WooCommerceCartDisplayRulesService
{
    /**
     * Rule type slug stored in display rules meta.
     *
     * @since 2.3.5
     */
    public const RULE_TYPE = 'woocommerce_cart';

    /**
     * Allowed cart condition values.
     *
     * @since 2.3.5
     * @var array<int, string>
     */
    private const CONDITIONS = [
        'cart_not_empty',
        'cart_empty',
        'product_in_cart',
        'product_not_in_cart',
        'category_in_cart',
        'cart_total',
        'cart_item_count',
        'coupon_applied',
    ];

    /**
     * Allowed numeric comparison operators.
     *
     * @since 2.3.5
     * @var array<int, string>
     */
    private const OPERATORS = ['gt', 'lt', 'eq'];

    /**
     * Whether WooCommerce cart rules are available in the current environment.
     *
     * @return bool True when WooCommerce is active.
     * @since 2.3.5
     */
    public static function isAvailable(): bool
    {
        return PluginDetector::isWooCommerceActive();
    }

    /**
     * Rule type metadata for the admin rule type dropdown.
     *
     * @return array<string, mixed> Rule type configuration.
     * @since 2.3.5
     */
    public static function getRuleTypeConfig(): array
    {
        return [
            'label' => __('WooCommerce Cart', 'notifal'),
            'icon'  => '🛒',
        ];
    }

    /**
     * Cart condition options for the admin select field.
     *
     * @return array<int, array<string, string>> Options for FieldRenderer::select.
     * @since 2.3.5
     */
    public static function getConditionOptions(): array
    {
        return [
            ['value' => 'cart_not_empty', 'label' => __('Cart is not empty', 'notifal')],
            ['value' => 'cart_empty', 'label' => __('Cart is empty', 'notifal')],
            ['value' => 'product_in_cart', 'label' => __('Specific product in cart', 'notifal')],
            ['value' => 'product_not_in_cart', 'label' => __('Specific product NOT in cart', 'notifal')],
            ['value' => 'category_in_cart', 'label' => __('Product from category in cart', 'notifal')],
            ['value' => 'cart_total', 'label' => __('Cart total comparison', 'notifal')],
            ['value' => 'cart_item_count', 'label' => __('Cart item count comparison', 'notifal')],
            ['value' => 'coupon_applied', 'label' => __('Coupon applied', 'notifal')],
        ];
    }

    /**
     * Comparison operator options for total and item count rules.
     *
     * @return array<int, array<string, string>> Options for FieldRenderer::select.
     * @since 2.3.5
     */
    public static function getOperatorOptions(): array
    {
        return [
            ['value' => 'gt', 'label' => __('Greater than (>)', 'notifal')],
            ['value' => 'lt', 'label' => __('Less than (<)', 'notifal')],
            ['value' => 'eq', 'label' => __('Equal to (=)', 'notifal')],
        ];
    }

    /**
     * Validate cart rule data submitted from the admin UI.
     *
     * @param array<string, mixed> $ruleData Raw rule data.
     * @return array<int, string> Validation error messages.
     * @since 2.3.5
     */
    public static function validateRule(array $ruleData): array
    {
        $errors = [];

        $condition = isset($ruleData['condition']) ? (string) $ruleData['condition'] : '';

        if (!in_array($condition, self::CONDITIONS, true)) {
            $errors[] = __('Invalid WooCommerce cart condition.', 'notifal');
            return $errors;
        }

        if (in_array($condition, ['product_in_cart', 'product_not_in_cart'], true)) {
            if (empty($ruleData['product_ids'])) {
                $errors[] = __('Please select at least one product for the cart rule.', 'notifal');
            }
        }

        if ($condition === 'category_in_cart' && empty($ruleData['category_ids'])) {
            $errors[] = __('Please select at least one product category for the cart rule.', 'notifal');
        }

        if (in_array($condition, ['cart_total', 'cart_item_count'], true)) {
            $operator = isset($ruleData['operator']) ? (string) $ruleData['operator'] : '';
            if (!in_array($operator, self::OPERATORS, true)) {
                $errors[] = __('Invalid cart comparison operator.', 'notifal');
            }

            if (!isset($ruleData['value']) || $ruleData['value'] === '' || !is_numeric($ruleData['value'])) {
                $errors[] = __('Please enter a valid number for the cart comparison.', 'notifal');
            }
        }

        return $errors;
    }

    /**
     * Sanitize cart rule data for storage.
     *
     * @param array<string, mixed> $ruleData Raw rule data.
     * @return array<string, mixed> Sanitized rule data.
     * @since 2.3.5
     */
    public static function sanitizeRule(array $ruleData): array
    {
        $condition = Helper::sanitizeInput($ruleData['condition'] ?? 'cart_not_empty', 'text');
        if (!in_array($condition, self::CONDITIONS, true)) {
            $condition = 'cart_not_empty';
        }

        $sanitized = [
            'condition' => $condition,
        ];

        $sanitized['product_ids'] = self::sanitizeIdList($ruleData['product_ids'] ?? []);
        $sanitized['category_ids'] = self::sanitizeIdList($ruleData['category_ids'] ?? []);

        $operator = Helper::sanitizeInput($ruleData['operator'] ?? 'gt', 'text');
        $sanitized['operator'] = in_array($operator, self::OPERATORS, true) ? $operator : 'gt';

        $value = isset($ruleData['value']) ? (string) $ruleData['value'] : '0';
        $sanitized['value'] = is_numeric($value) ? (string) (0 + $value) : '0';

        $sanitized['coupon_code'] = sanitize_text_field($ruleData['coupon_code'] ?? '');

        return $sanitized;
    }

    /**
     * Generate a human-readable summary for the admin rule list.
     *
     * @param array<string, mixed> $ruleData Sanitized rule data.
     * @return string Summary text.
     * @since 2.3.5
     */
    public static function generateSummary(array $ruleData): string
    {
        $condition = $ruleData['condition'] ?? 'cart_not_empty';
        $labels = [];

        foreach (self::getConditionOptions() as $option) {
            $labels[$option['value']] = $option['label'];
        }

        $summary = $labels[$condition] ?? __('WooCommerce Cart', 'notifal');

        if (in_array($condition, ['product_in_cart', 'product_not_in_cart'], true)) {
            $count = count($ruleData['product_ids'] ?? []);
            /* translators: %d: number of products */
            $summary .= ' (' . sprintf(_n('%d product', '%d products', $count, 'notifal'), $count) . ')';
        }

        if ($condition === 'category_in_cart') {
            $count = count($ruleData['category_ids'] ?? []);
            /* translators: %d: number of categories */
            $summary .= ' (' . sprintf(_n('%d category', '%d categories', $count, 'notifal'), $count) . ')';
        }

        if (in_array($condition, ['cart_total', 'cart_item_count'], true)) {
            $operatorLabels = ['gt' => '>', 'lt' => '<', 'eq' => '='];
            $op = $operatorLabels[$ruleData['operator'] ?? 'gt'] ?? '>';
            $summary .= ' ' . $op . ' ' . ($ruleData['value'] ?? '0');
        }

        if ($condition === 'coupon_applied' && !empty($ruleData['coupon_code'])) {
            $summary .= ': ' . $ruleData['coupon_code'];
        }

        return $summary;
    }

    /**
     * Sanitize a list of numeric IDs.
     *
     * @param array<int, mixed> $ids Raw ID list.
     * @return array<int, int> Sanitized IDs.
     * @since 2.3.5
     */
    private static function sanitizeIdList(array $ids): array
    {
        return array_values(array_unique(array_map('absint', array_filter($ids, 'is_numeric'))));
    }
}
