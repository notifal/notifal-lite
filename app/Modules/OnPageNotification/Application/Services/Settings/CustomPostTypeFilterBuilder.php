<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Settings;

defined('ABSPATH') || exit;

/**
 * Class CustomPostTypeFilterBuilder
 *
 * Builds filters specifically for WordPress custom post types.
 * Handles both legacy single filters and new multiple filters with AND/OR logic.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class CustomPostTypeFilterBuilder extends BaseFilterBuilder
{
    /**
     * Build custom post type filters from content source settings.
     *
     * @param string $postType The custom post type name
     * @param array $settings Content source settings
     * @return array Custom post type filters
     * @since 2.0.0
     */
    public function buildFilters(string $postType, array $settings): array
    {
        // Check for new multiple filters format first
        $genericFilterKey = 'custom_posttype_filters';
        $specificFilterKey = $postType . '_filters';

        // Try both generic and post-type-specific filter keys
        if (isset($settings[$genericFilterKey]) && ($settings[$genericFilterKey]['multiple_filters'] ?? false)) {
            return $this->buildMultipleFilters($settings[$genericFilterKey]);
        }

        if (isset($settings[$specificFilterKey]) && ($settings[$specificFilterKey]['multiple_filters'] ?? false)) {
            return $this->buildMultipleFilters($settings[$specificFilterKey]);
        }

        // Fall back to legacy single filter format
        return $this->buildLegacyFilters($postType, $settings);
    }

    /**
     * Build custom post type filter condition.
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
                if (!empty($data['items'])) {
                    return [
                        'type' => 'specific',
                        'items' => $data['items']
                    ];
                }
                break;

            case 'status':
                if (!empty($data['statuses'])) {
                    return [
                        'type' => 'status',
                        'statuses' => $data['statuses']
                    ];
                }
                break;

            case 'author':
                if (!empty($data['authors'])) {
                    return [
                        'type' => 'author',
                        'authors' => $data['authors']
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

            case 'custom_meta':
                // Support structured format (legacy)
                if ($this->isProFeatureAllowed() && !empty($data['meta_key']) && isset($data['value'])) {
                    return [
                        'type' => 'custom_meta',
                        'meta_key' => $data['meta_key'],
                        'operator' => $data['operator'] ?? '=',
                        'value' => $data['value']
                    ];
                }
                // Support custom filter string format (new UI format)
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
     * Build legacy custom post type filters (backward compatibility).
     *
     * @param string $postType The custom post type name
     * @param array $settings Content source settings
     * @return array Custom post type filters
     * @since 2.0.0
     */
    private function buildLegacyFilters(string $postType, array $settings): array
    {
        $filters = [];

        // Use post-type-specific restriction type key for proper cache key matching
        $restrictionTypeKey = $postType . '_restriction_type';
        $cptRestrictionType = $settings[$restrictionTypeKey] ?? 'all';

        if ($cptRestrictionType === 'all') {
            return $filters;
        }

        // Use post-type-specific setting keys to match cache key building logic
        switch ($cptRestrictionType) {
            case 'taxonomies':
                $taxonomiesKey = $postType . '_taxonomies';
                $filters['taxonomies'] = $settings[$taxonomiesKey] ?? [];
                break;

            case 'specific':
                $specificKey = $postType . '_specific';
                $filters['items'] = $settings[$specificKey] ?? [];
                break;

            case 'status':
                $statusKey = $postType . '_status';
                $filters['status'] = $settings[$statusKey] ?? ['publish'];
                break;

            case 'author':
                $authorKey = $postType . '_author';
                $filters['authors'] = $settings[$authorKey] ?? [];
                break;

            case 'date_range':
                // Handle date range filters
                $dateRangeKey = $postType . '_date_range';
                $dateRange = $settings[$dateRangeKey] ?? 'all';
                if ($dateRange !== 'all') {
                    $filters['date_range'] = $dateRange;
                    if ($dateRange === 'custom') {
                        $startDateKey = $postType . '_date_start';
                        $endDateKey = $postType . '_date_end';
                        $filters['start_date'] = $settings[$startDateKey] ?? '';
                        $filters['end_date'] = $settings[$endDateKey] ?? '';
                    }
                }
                break;

            case 'custom':
                $customFilterKey = $postType . '_custom_filter';
                $filters['custom_filter'] = $settings[$customFilterKey] ?? '';
                break;
        }

        return $filters;
    }

    /**
     * Convert a single custom post type filter condition to legacy format.
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
                    'taxonomies' => $data['categories'] ?? []
                ];

            case 'specific':
                return [
                    'items' => $data['items'] ?? []
                ];

            case 'status':
                return [
                    'status' => $data['statuses'] ?? []
                ];

            case 'author':
                return [
                    'authors' => $data['authors'] ?? []
                ];

            default:
                return [];
        }
    }

}
