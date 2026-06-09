<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Settings;

defined('ABSPATH') || exit;

/**
 * Class ProductFilterBuilder
 *
 * Builds filters specifically for WooCommerce products.
 * Handles both legacy single filters and new multiple filters with AND/OR logic.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ProductFilterBuilder extends BaseFilterBuilder
{
    /**
     * Build product filters from content source settings.
     *
     * @param array $settings Content source settings
     * @return array Product filters
     * @since 2.0.0
     */
    public function buildFilters(array $settings): array
    {
        // Check for new multiple filters format first
        if (isset($settings['product_filters']) && ($settings['product_filters']['multiple_filters'] ?? false)) {
            return $this->buildMultipleFilters($settings['product_filters']);
        }

        // Fall back to legacy single filter format
        return $this->buildLegacyFilters($settings);
    }

    /**
     * Build product filter condition.
     *
     * @param string $type Filter type
     * @param array $data Filter data
     * @return array|null Filter condition
     * @since 2.0.0
     */
    protected function buildFilterCondition(string $type, array $data): ?array
    {
        switch ($type) {
            case 'categories':
                if (!empty($data['categories'])) {
                    return [
                        'type' => 'categories',
                        'categories' => $data['categories']
                    ];
                }
                break;

            case 'specific':
                if (!empty($data['products'])) {
                    return [
                        'type' => 'specific',
                        'products' => $data['products']
                    ];
                }
                break;

            case 'sale':
                return [
                    'type' => 'sale',
                    'on_sale' => true
                ];

            case 'featured':
                return [
                    'type' => 'featured',
                    'featured' => true
                ];

            case 'custom_meta':
                // Support both new format (meta_key, operator, value) and legacy format (custom_filter)
                if (!empty($data['meta_key']) && isset($data['value'])) {
                    return [
                        'type' => 'custom_meta',
                        'meta_key' => $data['meta_key'],
                        'operator' => $data['operator'] ?? '=',
                        'value' => $data['value']
                    ];
                } elseif (!empty($data['custom_filter'])) {
                    // Legacy format: parse the custom_filter string
                    return [
                        'type' => 'custom_meta',
                        'custom_filter' => $data['custom_filter']
                    ];
                }
                break;

            case 'date_range':
                return [
                    'type' => 'date_range',
                    'date_type' => $data['date_type'] ?? 'publish',
                    'range' => $data['range'] ?? 'last_7d',
                    'start_date' => $data['start_date'] ?? '',
                    'end_date' => $data['end_date'] ?? ''
                ];

            case 'cart':
                // Require at least one cart source toggle.
                if (!self::hasEnabledCartToggle($data)) {
                    return null;
                }

                return [
                    'type' => 'cart',
                    'cart_products' => !empty($data['cart_products']),
                    'related_cart_products' => !empty($data['related_cart_products']),
                    'upsell_cart_products' => !empty($data['upsell_cart_products']),
                    'cross_sell_cart_products' => !empty($data['cross_sell_cart_products']),
                ];
        }

        return null;
    }

    /**
     * Determine whether a cart filter has at least one enabled source toggle.
     *
     * @param array<string, mixed> $data Cart filter condition data.
     * @return bool
     * @since 2.3.9
     */
    private static function hasEnabledCartToggle(array $data): bool
    {
        return !empty($data['cart_products'])
            || !empty($data['related_cart_products'])
            || !empty($data['upsell_cart_products'])
            || !empty($data['cross_sell_cart_products']);
    }

    /**
     * Build legacy product filters (backward compatibility).
     *
     * @param array $settings Content source settings
     * @return array Product filters
     * @since 2.0.0
     */
    private function buildLegacyFilters(array $settings): array
    {
        $filters = [];

        // Product restriction type
        $productRestrictionType = $settings['product_restriction_type'] ?? 'all';

        if ($productRestrictionType === 'all') {
            return $filters;
        }

        switch ($productRestrictionType) {
            case 'categories':
                $filters['categories'] = $settings['product_categories'] ?? [];
                break;

            case 'specific':
                $filters['products'] = $settings['specific_products'] ?? [];
                break;

            case 'sale':
                $filters['on_sale'] = true;
                break;

            case 'featured':
                $filters['featured'] = true;
                break;

            case 'custom':
                $filters['custom_filter'] = $settings['product_custom_filter'] ?? '';
                break;
        }

        return $filters;
    }

    /**
     * Convert a single product filter condition to legacy format.
     *
     * @param array $condition Single filter condition
     * @return array Legacy format filter
     * @since 2.0.0
     */
    protected function convertSingleFilterToLegacy(array $condition): array
    {
        $type = $condition['type'] ?? '';
        $data = $condition['data'] ?? [];

        switch ($type) {
            case 'categories':
                return [
                    'categories' => $data['categories'] ?? []
                ];

            case 'specific':
                return [
                    'products' => $data['products'] ?? []
                ];

            case 'sale':
                return [
                    'on_sale' => true
                ];

            case 'featured':
                return [
                    'featured' => true
                ];

            case 'custom_meta':
                return [
                    'custom_filter' => $data['custom_filter'] ?? ''
                ];

            case 'cart':
                return [
                    'multiple_filters' => true,
                    'logic' => 'AND',
                    'conditions' => [
                        [
                            'type' => 'cart',
                            'cart_products' => !empty($data['cart_products']),
                            'related_cart_products' => !empty($data['related_cart_products']),
                            'upsell_cart_products' => !empty($data['upsell_cart_products']),
                            'cross_sell_cart_products' => !empty($data['cross_sell_cart_products']),
                        ],
                    ],
                ];

            default:
                return [];
        }
    }
}
