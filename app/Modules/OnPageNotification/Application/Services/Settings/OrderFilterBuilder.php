<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Settings;

defined('ABSPATH') || exit;

/**
 * Class OrderFilterBuilder
 *
 * Builds filters specifically for WooCommerce orders.
 * Handles both legacy single filters and new multiple filters with AND/OR logic.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class OrderFilterBuilder extends BaseFilterBuilder
{
    /**
     * Build order filters from content source settings.
     *
     * @param array $settings Content source settings
     * @return array Order filters
     * @since 2.0.0
     */
    public function buildFilters(array $settings): array
    {
        // Check for new multiple filters format first
        if (isset($settings['order_filters']) && ($settings['order_filters']['multiple_filters'] ?? false)) {
            return $this->buildMultipleFilters($settings['order_filters']);
        }

        // Fall back to legacy single filter format
        return $this->buildLegacyFilters($settings);
    }

    /**
     * Build order filter condition.
     *
     * @param string $type Filter type
     * @param array $data Filter data
     * @return array|null Filter condition
     * @since 2.0.0
     */
    protected function buildFilterCondition(string $type, array $data): ?array
    {
        switch ($type) {
            case 'status':
                if (!empty($data['statuses'])) {
                    return [
                        'type' => 'status',
                        'statuses' => $data['statuses']
                    ];
                }
                break;

            case 'date_range':
                return [
                    'type' => 'date_range',
                    'range' => $data['range'] ?? 'last_7d',
                    'start_date' => $data['start_date'] ?? '',
                    'end_date' => $data['end_date'] ?? ''
                ];

            case 'products':
                if (!empty($data['products'])) {
                    return [
                        'type' => 'products',
                        'products' => $data['products']
                    ];
                }
                break;

            case 'custom_meta':
                if ($this->isProFeatureAllowed() && !empty($data['meta_key']) && isset($data['value'])) {
                    return [
                        'type' => 'custom_meta',
                        'meta_key' => $data['meta_key'],
                        'operator' => $data['operator'] ?? '=',
                        'value' => $data['value']
                    ];
                }
                break;

            case 'custom_filter':
                if (!empty($data['custom_filter'])) {
                    return [
                        'type' => 'custom_filter',
                        'custom_filter' => $data['custom_filter']
                    ];
                }
                break;
        }

        return null;
    }

    /**
     * Build legacy order filters (backward compatibility).
     *
     * @param array $settings Content source settings
     * @return array Order filters
     * @since 2.0.0
     */
    private function buildLegacyFilters(array $settings): array
    {
        $filters = [];

        // Order restriction type
        $orderRestrictionType = $settings['order_restriction_type'] ?? 'status';

        if ($orderRestrictionType === 'all') {
            return $filters;
        }

        switch ($orderRestrictionType) {
            case 'date_range':
                $filters['date_range'] = $settings['order_date_range'] ?? 'last_7d';
                if ($filters['date_range'] === 'custom') {
                    $filters['start_date'] = $settings['order_date_start'] ?? '';
                    $filters['end_date'] = $settings['order_date_end'] ?? '';
                }
                break;

            case 'status':
                $filters['status'] = $settings['order_statuses'] ?? ['completed', 'processing'];
                break;

            case 'products':
                $filters['products'] = $settings['order_products'] ?? [];
                break;

            case 'custom':
                $filters['custom_filter'] = $settings['order_custom_filter'] ?? '';
                break;
        }

        return $filters;
    }

    /**
     * Convert a single order filter condition to legacy format.
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
            case 'status':
                return [
                    'status' => $data['statuses'] ?? []
                ];

            case 'date_range':
                $filters = [
                    'date_range' => $data['range'] ?? 'last_7d'
                ];
                if ($filters['date_range'] === 'custom') {
                    $filters['start_date'] = $data['start_date'] ?? '';
                    $filters['end_date'] = $data['end_date'] ?? '';
                }
                return $filters;

            case 'products':
                return [
                    'products' => $data['products'] ?? []
                ];

            case 'custom_filter':
                return [
                    'custom_filter' => $data['custom_filter'] ?? ''
                ];

            default:
                return [];
        }
    }
}
