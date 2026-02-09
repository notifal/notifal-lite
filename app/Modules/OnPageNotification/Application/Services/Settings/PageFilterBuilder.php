<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Settings;

defined('ABSPATH') || exit;

/**
 * Class PageFilterBuilder
 *
 * Builds filters specifically for WordPress pages.
 * Handles both legacy single filters and new multiple filters with AND/OR logic.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class PageFilterBuilder extends BaseFilterBuilder
{
    /**
     * Build page filters from content source settings.
     *
     * @param array $settings Content source settings
     * @return array Page filters
     * @since 2.0.0
     */
    public function buildFilters(array $settings): array
    {
        // Check for new multiple filters format first
        if (isset($settings['page_filters']) && ($settings['page_filters']['multiple_filters'] ?? false)) {
            return $this->buildMultipleFilters($settings['page_filters']);
        }

        // Fall back to legacy single filter format
        return $this->buildLegacyFilters($settings);
    }

    /**
     * Build page filter condition.
     *
     * @param string $type Filter type
     * @param array $data Filter data
     * @return array|null Filter condition
     * @since 2.0.0
     */
    protected function buildFilterCondition(string $type, array $data): ?array
    {
        switch ($type) {
            case 'specific':
                if (!empty($data['pages'])) {
                    return [
                        'type' => 'specific',
                        'items' => $data['pages']
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

            case 'template':
                if (!empty($data['templates'])) {
                    return [
                        'type' => 'template',
                        'templates' => $data['templates']
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
     * Build legacy page filters (backward compatibility).
     *
     * @param array $settings Content source settings
     * @return array Page filters
     * @since 2.0.0
     */
    private function buildLegacyFilters(array $settings): array
    {
        $filters = [];

        // Page restriction type
        $pageRestrictionType = $settings['page_restriction_type'] ?? 'all';

        if ($pageRestrictionType === 'all') {
            return $filters;
        }

        switch ($pageRestrictionType) {
            case 'specific':
                $filters['pages'] = $settings['specific_pages'] ?? [];
                break;

            case 'status':
                $filters['status'] = $settings['page_statuses'] ?? ['publish'];
                break;

            case 'author':
                $filters['authors'] = $settings['page_authors'] ?? [];
                break;

            case 'template':
                $filters['templates'] = $settings['page_templates'] ?? [];
                break;

            case 'custom':
                $filters['custom_filter'] = $settings['page_custom_filter'] ?? '';
                break;
        }

        return $filters;
    }

    /**
     * Convert a single page filter condition to legacy format.
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
            case 'specific':
                return [
                    'pages' => $data['pages'] ?? []
                ];

            case 'status':
                return [
                    'status' => $data['statuses'] ?? []
                ];

            case 'author':
                return [
                    'authors' => $data['authors'] ?? []
                ];

            case 'template':
                return [
                    'templates' => $data['templates'] ?? []
                ];

            case 'date_range':
                // Convert date_range to legacy format for pages
                $legacyFilters = [];
                $legacyFilters['date_range'] = $data['range'] ?? 'last_7d';
                if (!empty($data['date_type'])) {
                    $legacyFilters['date_type'] = $data['date_type'];
                }
                if (!empty($data['start_date'])) {
                    $legacyFilters['start_date'] = $data['start_date'];
                }
                if (!empty($data['end_date'])) {
                    $legacyFilters['end_date'] = $data['end_date'];
                }
                return $legacyFilters;

            case 'custom_meta':
                if (!empty($data['custom_filter'])) {
                    return [
                        'custom_filter' => $data['custom_filter']
                    ];
                } elseif (!empty($data['meta_key']) && isset($data['value'])) {
                    // Convert meta_key/value/operator to custom_filter string format
                    $operator = $data['operator'] ?? '=';
                    return [
                        'custom_filter' => $data['meta_key'] . $operator . $data['value']
                    ];
                }
                return [];

            case 'custom_filter':
                return [
                    'custom_filter' => $data['custom_filter'] ?? ''
                ];

            default:
                return [];
        }
    }

}
