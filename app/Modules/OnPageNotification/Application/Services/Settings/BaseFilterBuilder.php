<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Settings;

use Notifal\Modules\OnPageNotification\Application\Traits\SettingsServiceTrait;

defined('ABSPATH') || exit;

/**
 * Abstract base class for filter builders.
 * Provides common functionality for building filters across different content types.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
abstract class BaseFilterBuilder
{
    use SettingsServiceTrait;

    /**
     * Build multiple filters for a content type.
     *
     * @param array $filterConfig Multiple filter configuration
     * @return array Multiple filters
     * @since 2.0.0
     */
    protected function buildMultipleFilters(array $filterConfig): array
    {
        $logic = strtoupper($filterConfig['logic'] ?? 'AND');
        $logic = in_array($logic, ['AND', 'OR'], true) ? $logic : 'AND';

        $filters = [
            'multiple_filters' => true,
            'logic' => $logic,
            'conditions' => []
        ];

        if (empty($filterConfig['conditions'])) {
            return $filters;
        }

        $proAllowed = $this->isProFeatureAllowed();
        $processedConditions = [];

        foreach ($filterConfig['conditions'] as $condition) {
            if (!$condition['enabled']) {
                continue;
            }

            $type = $condition['type'];

            // Check if this filter type requires Pro
            if (!$proAllowed && $this->isProOnlyFilterType($type)) {
                // Skip Pro-only filter types when Pro is not active
                continue;
            }

            $data = $condition['data'];

            $filterCondition = $this->buildFilterCondition($type, $data);
            if ($filterCondition) {
                $processedConditions[] = $filterCondition;
            }
        }

        // Handle Pro restrictions
        if (!$proAllowed) {
            if (count($processedConditions) > 1) {
                // Multiple conditions require Pro - only allow the first one and convert to legacy
                $singleCondition = $filterConfig['conditions'][0]; // Use first original condition for legacy conversion
                $legacyFilters = $this->convertSingleFilterToLegacy($singleCondition);
                return $legacyFilters;
            } elseif (count($processedConditions) === 1) {
                // Single condition - convert to legacy format for non-Pro users
                $singleCondition = $filterConfig['conditions'][0];
                $legacyFilters = $this->convertSingleFilterToLegacy($singleCondition);
                return $legacyFilters;
            } else {
                // No valid conditions
                return [
                    'multiple_filters' => false,
                    'logic' => 'AND',
                    'conditions' => []
                ];
            }
        }

        $filters['conditions'] = $processedConditions;
        $filters['multiple_filters'] = !empty($processedConditions);

        return $filters;
    }

    /**
     * Build a filter condition based on type and data.
     * Must be implemented by concrete filter builders.
     *
     * @param string $type Filter type
     * @param array $data Filter data
     * @return array|null Filter condition or null if invalid
     * @since 2.0.0
     */
    abstract protected function buildFilterCondition(string $type, array $data): ?array;

    /**
     * Convert a single filter condition to legacy format.
     * Must be implemented by concrete filter builders.
     *
     * @param array $condition Single filter condition
     * @return array Legacy format filter
     * @since 2.0.0
     */
    abstract protected function convertSingleFilterToLegacy(array $condition): array;

    /**
     * Check if pro features are allowed (user has active pro license).
     * Uses secure hooks that can only be provided by the legitimate pro plugin.
     *
     * @return bool True if pro features are allowed
     * @since 2.0.0
     */
    protected function isProFeatureAllowed(): bool
    {
        return $this->checkProFeatureAllowed('notifal_pro_content_source_features');
    }

    /**
     * Check if a filter type requires Pro license.
     *
     * @param string $filterType The filter type to check
     * @return bool True if the filter type requires Pro
     * @since 2.0.0
     */
    protected function isProOnlyFilterType(string $filterType): bool
    {
        // Pro-only filter types that require active license
        $proOnlyTypes = [];

        return in_array($filterType, $proOnlyTypes, true);
    }
}
