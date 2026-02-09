<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Settings;

defined('ABSPATH') || exit;

/**
 * Class UserFilterBuilder
 *
 * Builds filters specifically for WordPress users.
 * Handles both legacy single filters and new multiple filters with AND/OR logic.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class UserFilterBuilder extends BaseFilterBuilder
{
    /**
     * Build user filters from content source settings.
     *
     * @param array $settings Content source settings
     * @return array User filters
     * @since 2.0.0
     */
    public function buildFilters(array $settings): array
    {
        // Check for new multiple filters format first
        if (isset($settings['user_filters']) && ($settings['user_filters']['multiple_filters'] ?? false)) {
            return $this->buildMultipleFilters($settings['user_filters']);
        }

        // Fall back to legacy single filter format
        return $this->buildLegacyFilters($settings);
    }

    /**
     * Build user filter condition.
     *
     * @param string $type Filter type
     * @param array $data Filter data
     * @return array|null Filter condition
     * @since 2.0.0
     */
    protected function buildFilterCondition(string $type, array $data): ?array
    {
        switch ($type) {
            case 'roles':
                if (!empty($data['roles'])) {
                    return [
                        'type' => 'roles',
                        'roles' => $data['roles']
                    ];
                }
                break;

            case 'specific':
                if (!empty($data['users'])) {
                    return [
                        'type' => 'specific',
                        'users' => $data['users']
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

            case 'registration_date':
                if (isset($data['start_date']) || isset($data['end_date'])) {
                    return [
                        'type' => 'registration_date',
                        'start_date' => $data['start_date'] ?? '',
                        'end_date' => $data['end_date'] ?? ''
                    ];
                }
                break;
        }

        return null;
    }

    /**
     * Build legacy user filters (backward compatibility).
     *
     * @param array $settings Content source settings
     * @return array User filters
     * @since 2.0.0
     */
    private function buildLegacyFilters(array $settings): array
    {
        $filters = [];

        // User restriction type
        $userRestrictionType = $settings['user_restriction_type'] ?? 'all';

        if ($userRestrictionType === 'all') {
            return $filters;
        }

        switch ($userRestrictionType) {
            case 'roles':
                $filters['roles'] = $settings['user_roles'] ?? ['customer'];
                break;

            case 'specific':
                $filters['users'] = $settings['specific_users'] ?? [];
                break;

            case 'custom':
                $filters['custom_filter'] = $settings['user_custom_filter'] ?? '';
                break;
        }

        return $filters;
    }

    /**
     * Convert a single user filter condition to legacy format.
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
            case 'roles':
                return [
                    'roles' => $data['roles'] ?? []
                ];

            case 'specific':
                return [
                    'users' => $data['users'] ?? []
                ];

            case 'custom_meta':
                return [
                    'custom_filter' => $data['custom_filter'] ?? ''
                ];

            default:
                return [];
        }
    }

}
