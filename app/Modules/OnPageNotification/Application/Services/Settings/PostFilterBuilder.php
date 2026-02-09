<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Settings;

defined('ABSPATH') || exit;

/**
 * Class PostFilterBuilder
 *
 * Builds filters specifically for WordPress posts.
 * Handles both legacy single filters and new multiple filters with AND/OR logic.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class PostFilterBuilder extends BaseFilterBuilder
{
    /**
     * Build post filters from content source settings.
     *
     * @param array $settings Content source settings
     * @return array Post filters
     * @since 2.0.0
     */
    public function buildFilters(array $settings): array
    {
        // Check for new multiple filters format first
        if (isset($settings['post_filters']) && ($settings['post_filters']['multiple_filters'] ?? false)) {
            return $this->buildMultipleFilters($settings['post_filters']);
        }

        // Fall back to legacy single filter format
        return $this->buildLegacyFilters($settings);
    }

    /**
     * Build post filter condition.
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
                if (!empty($data['posts'])) {
                    return [
                        'type' => 'specific',
                        'items' => $data['posts']
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
        }

        return null;
    }

    /**
     * Build legacy post filters (backward compatibility).
     *
     * @param array $settings Content source settings
     * @return array Post filters
     * @since 2.0.0
     */
    private function buildLegacyFilters(array $settings): array
    {
        $filters = [];

        // Post restriction type
        $postRestrictionType = $settings['post_restriction_type'] ?? 'all';

        if ($postRestrictionType === 'all') {
            return $filters;
        }

        switch ($postRestrictionType) {
            case 'categories':
                $filters['categories'] = $settings['post_categories'] ?? [];
                break;

            case 'specific':
                $filters['posts'] = $settings['specific_posts'] ?? [];
                break;

            case 'status':
                $filters['status'] = $settings['post_statuses'] ?? ['publish'];
                break;

            case 'author':
                $filters['authors'] = $settings['post_authors'] ?? [];
                break;

            case 'custom':
                $filters['custom_filter'] = $settings['post_custom_filter'] ?? '';
                break;
        }

        return $filters;
    }

    /**
     * Convert a single post filter condition to legacy format.
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
                    'posts' => $data['posts'] ?? []
                ];

            case 'status':
                return [
                    'status' => $data['statuses'] ?? []
                ];

            case 'author':
                return [
                    'authors' => $data['authors'] ?? []
                ];

            case 'date_range':
                return [
                    'date_range' => $data['range'] ?? 'last_7d',
                    'date_type' => $data['date_type'] ?? 'publish',
                    'start_date' => $data['start_date'] ?? '',
                    'end_date' => $data['end_date'] ?? ''
                ];

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

            default:
                return [];
        }
    }
}
