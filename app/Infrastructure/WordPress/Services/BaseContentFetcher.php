<?php

namespace Notifal\Infrastructure\WordPress\Services;

use WP_Post;
use WP_Query;
use Notifal\Shared\Utils\FilterHelper;

defined('ABSPATH') || exit;

/**
 * Base class for WordPress content fetchers.
 *
 * Provides common functionality for fetching WordPress content (posts, pages, custom post types)
 * with filtering, caching, and query building capabilities.
 *
 * @package Notifal\Infrastructure\WordPress\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
abstract class BaseContentFetcher
{
    /**
     * Retrieve a single random content item from WordPress.
     *
     * @param string $postType The content type name
     * @param array $filters Optional filters to apply
     * @return WP_Post|null Null if no content found.
     * @since 2.0.0
     */
    protected function getRandomContent(string $postType, array $filters = []): ?WP_Post
    {
        // Validate post type exists and is public
        if (!$this->isValidPostType($postType)) {
            return null;
        }

        // Build query arguments for content type
        $args = [
            'post_type'      => $postType,
            'posts_per_page' => 1,
            'orderby'        => 'rand',
            'post_status'    => 'publish',
        ];

        // Apply custom filters if provided
        $args = $this->applyFilters($args, $filters);

        $query = new WP_Query($args);

        if (empty($query->posts)) {
            return null;
        }

        return $query->posts[0];
    }

    /**
     * Retrieve multiple random content items from WordPress for pool-based caching.
     *
     * @param string $postType The content type name
     * @param int $count Number of items to fetch (default: 20)
     * @param array $filters Optional filters to apply
     * @return WP_Post[] Array of WP_Post objects
     * @since 2.0.0
     */
    protected function getRandomPoolContent(string $postType, int $count = 20, array $filters = []): array
    {
        // Validate post type exists and is public
        if (!$this->isValidPostType($postType)) {
            return [];
        }

        // Ensure we don't fetch too many items (performance limit)
        $count = max(1, min($count, 50));

        // Build query arguments for content type
        $args = [
            'post_type'      => $postType,
            'posts_per_page' => $count,
            'orderby'        => 'rand',
            'post_status'    => 'publish',
        ];

        // Apply custom filters if provided
        $args = $this->applyFilters($args, $filters);

        $query = new WP_Query($args);

        if (empty($query->posts)) {
            return [];
        }

        return $query->posts;
    }

    /**
     * Find content item by its ID.
     *
     * @param int $id Content ID.
     * @param string $postType The content type name
     * @return WP_Post|null Null if content not found.
     * @since 2.0.0
     */
    protected function findContentById(int $id, string $postType): ?WP_Post
    {
        $post = get_post($id);

        // Verify it's the correct post type and published
        if (!$post || $post->post_type !== $postType || $post->post_status !== 'publish') {
            return null;
        }

        // Validate post type exists and is public
        if (!$this->isValidPostType($postType)) {
            return null;
        }

        return $post;
    }

    /**
     * Check if a post type is valid and accessible.
     *
     * @param string $postType The post type to validate
     * @return bool True if valid, false otherwise
     * @since 2.0.0
     */
    abstract protected function isValidPostType(string $postType): bool;

    /**
     * Apply custom filters to content query arguments.
     * Supports both legacy single filters and new multiple filters with AND/OR logic.
     *
     * @param array $args Base query arguments
     * @param array $filters Filter configuration
     * @return array Modified query arguments
     * @since 2.0.0
     */
    protected function applyFilters(array $args, array $filters): array
    {
        if (empty($filters)) {
            return $args;
        }

        // Check for new multi-filter format
        if (isset($filters['multiple_filters']) && $filters['multiple_filters'] === true) {
            return $this->applyMultipleFilters($args, $filters);
        }

        // Legacy single filter support
        return $this->applyLegacyFilters($args, $filters);
    }

    /**
     * Apply legacy single filters (to be implemented by subclasses).
     *
     * @param array $args Base query arguments
     * @param array $filters Filter configuration
     * @return array Modified query arguments
     * @since 2.0.0
     */
    abstract protected function applyLegacyFilters(array $args, array $filters): array;

    /**
     * Apply common legacy filters that are shared across multiple content fetchers.
     *
     * @param array $args Base query arguments
     * @param array $filters Filter configuration
     * @return array Modified query arguments
     * @since 2.0.0
     */
    protected function applyCommonLegacyFilters(array $args, array $filters): array
    {
        // Date range filter
        if (isset($filters['date_range']) && $filters['date_range'] !== 'all') {
            $args = $this->applyDateRangeFilter($args, $filters);
        }

        // Custom meta filter
        if (isset($filters['custom_filter']) && !empty($filters['custom_filter'])) {
            $args = $this->applyCustomFilter($args, $filters['custom_filter']);
        }

        return $args;
    }

    /**
     * Apply multiple filters with AND/OR logic to content query.
     *
     * @param array $args Base query arguments
     * @param array $filters Multiple filters configuration
     * @return array Modified query arguments
     * @since 2.0.0
     */
    protected function applyMultipleFilters(array $args, array $filters): array
    {
        $conditions = $filters['conditions'] ?? [];
        $logic = strtoupper($filters['logic'] ?? 'AND');

        if (empty($conditions)) {
            return $args;
        }

        // For OR logic, run separate queries and merge results
        if ($logic === 'OR') {
            $final_post_ids = [];
            $base_args = [
                'post_type' => $args['post_type'],
                'posts_per_page' => -1,
                'fields' => 'ids',
                'post_status' => 'publish'
            ];

            foreach ($conditions as $condition) {
                // Apply each condition individually
                $single_condition_filters = [
                    'conditions' => [$condition],
                    'logic' => 'AND'
                ];
                $condition_args = $this->applyAndLogicFilters($base_args, $single_condition_filters);

                // Execute query for this condition
                $query = new WP_Query($condition_args);
                if (!empty($query->posts)) {
                    $final_post_ids = array_merge($final_post_ids, $query->posts);
                }
            }

            // Remove duplicates and apply to main query
            $final_post_ids = array_unique($final_post_ids);

            // Clear any existing query constraints and use post__in
            unset($args['meta_query'], $args['tax_query'], $args['date_query']);
            $args['post__in'] = !empty($final_post_ids) ? $final_post_ids : [0];
            return $args;
        }

        // For AND logic, use the existing method
        return $this->applyAndLogicFilters($args, $filters);
    }

    /**
     * Apply multiple filters with AND logic.
     *
     * @param array $args Base query arguments
     * @param array $filters Filters configuration
     * @return array Modified query arguments
     * @since 2.0.0
     */
    protected function applyAndLogicFilters(array $args, array $filters): array
    {
        $conditions = $filters['conditions'] ?? [];

        if (empty($conditions)) {
            return $args;
        }

        // Initialize query arrays
        if (!isset($args['meta_query'])) {
            $args['meta_query'] = [];
        }
        if (!isset($args['tax_query'])) {
            $args['tax_query'] = [];
        }

        // Process conditions and group by query type
        $metaConditions = [];
        $taxConditions = [];

        foreach ($conditions as $condition) {
            $conditionType = $condition['type'] ?? '';

            switch ($conditionType) {
                case 'categories':
                case 'taxonomies':
                    $taxonomyQuery = $this->buildTaxonomyCondition($condition);
                    if ($taxonomyQuery) {
                        $taxConditions[] = $taxonomyQuery;
                    }
                    break;

                case 'specific':
                    // Handle specific items filter
                    if (!empty($condition['items'])) {
                        $args['post__in'] = $condition['items'];
                    }
                    break;

                case 'status':
                    // Handle status filter
                    if (!empty($condition['statuses'])) {
                        $args['post_status'] = $condition['statuses'];
                    }
                    break;

                case 'author':
                    if (!empty($condition['authors'])) {
                        $args['author__in'] = $condition['authors'];
                    }
                    break;

                case 'date_range':
                    $dateQuery = $this->buildDateCondition($condition);
                    if ($dateQuery) {
                        $args['date_query'] = $args['date_query'] ?? [];
                        $args['date_query'][] = $dateQuery;
                    }
                    break;

                case 'custom_meta':
                    if (!empty($condition['meta_key']) && isset($condition['value'])) {
                        $metaConditions[] = [
                            'key' => $condition['meta_key'],
                            'value' => $condition['value'],
                            'compare' => $condition['operator'] ?? '='
                        ];
                    }
                    break;

                case 'template':
                    // Handle page template filter (only for pages)
                    if (!empty($condition['templates'])) {
                        $metaConditions[] = [
                            'key' => '_wp_page_template',
                            'value' => $condition['templates'],
                            'compare' => 'IN'
                        ];
                    }
                    break;

                case 'custom_filter':
                    if (!empty($condition['custom_filter'])) {
                        $args = $this->applyCustomFilter($args, $condition['custom_filter']);
                    }
                    break;
            }
        }

        // Apply meta conditions with AND logic
        if (!empty($metaConditions)) {
            if (count($metaConditions) === 1) {
                $args['meta_query'][] = $metaConditions[0];
            } else {
                $metaConditions['relation'] = 'AND';
                $args['meta_query'][] = $metaConditions;
            }
        }

        // Apply taxonomy conditions with AND logic
        if (!empty($taxConditions)) {
            if (count($taxConditions) === 1) {
                $args['tax_query'][] = $taxConditions[0];
            } else {
                $taxConditions['relation'] = 'AND';
                $args['tax_query'][] = $taxConditions;
            }
        }

        return $args;
    }

    /**
     * Build taxonomy condition for tax query.
     *
     * @param array $condition Taxonomy condition
     * @return array|null Tax query condition or null
     * @since 2.0.0
     */
    abstract protected function buildTaxonomyCondition(array $condition): ?array;

    /**
     * Apply date range filter to content query.
     *
     * @param array $args Query arguments
     * @param array $filters Filter configuration
     * @return array Modified query arguments
     * @since 2.0.0
     */
    protected function applyDateRangeFilter(array $args, array $filters): array
    {
        $dateRange = $filters['date_range'];
        $dateType = $filters['date_type'] ?? 'publish';
        $now = new \DateTime();

        // Determine the column based on date type
        $column = ($dateType === 'modified') ? 'post_modified' : 'post_date';

        switch ($dateRange) {
            case 'last_24h':
            case '24':
                $startDate = clone $now;
                $startDate->modify('-24 hours');
                break;
            case 'last_7d':
            case '7':
                $startDate = clone $now;
                $startDate->modify('-7 days');
                break;
            case 'last_30d':
            case '30':
                $startDate = clone $now;
                $startDate->modify('-30 days');
                break;
            case 'last_90d':
            case '90':
                $startDate = clone $now;
                $startDate->modify('-90 days');
                break;
            case 'custom':
                if (isset($filters['start_date']) && isset($filters['end_date'])) {
                    $args['date_query'] = [
                        [
                            'after'     => $filters['start_date'],
                            'before'    => $filters['end_date'],
                            'inclusive' => true,
                            'column'    => $column
                        ]
                    ];
                }
                return $args;
            default:
                return $args;
        }

        $args['date_query'] = [
            [
                'after' => $startDate->format('Y-m-d H:i:s'),
                'inclusive' => true,
                'column' => $column
            ]
        ];

        return $args;
    }

    /**
     * Apply custom meta filter to content query.
     *
     * @param array $args Query arguments
     * @param string $customFilter Custom filter string (format: meta_key:value)
     * @return array Modified query arguments
     * @since 2.0.0
     */
    protected function applyCustomFilter(array $args, string $customFilter): array
    {
        if (empty($customFilter)) {
            return $args;
        }

        // Parse the custom filter
        $metaQueries = $this->parseCustomFilter($customFilter);

        if (empty($metaQueries)) {
            return $args;
        }

        // Initialize meta_query if not set
        if (!isset($args['meta_query'])) {
            $args['meta_query'] = [];
        }

        // If there's only one condition, add it directly
        if (count($metaQueries) === 1) {
            $args['meta_query'][] = $metaQueries[0];
        } else {
            // Multiple conditions with relation
            $args['meta_query'][] = $metaQueries;
        }

        return $args;
    }

    /**
     * Parse custom filter string into meta query array.
     *
     * @param string $customFilter Custom filter string
     * @return array Meta query array
     * @since 2.0.0
     */
    protected function parseCustomFilter(string $customFilter): array
    {
        return FilterHelper::parseCustomFilter($customFilter);
    }





    /**
     * Build date condition for date query.
     *
     * @param array $condition Date condition configuration
     * @return array|null Date query condition or null
     * @since 2.0.0
     */
    protected function buildDateCondition(array $condition): ?array
    {
        $dateType = $condition['date_type'] ?? 'publish'; // 'publish' or 'modified'
        $range = $condition['range'] ?? '';

        if (empty($range)) {
            return null;
        }

        $dateQuery = [];
        $now = new \DateTime();

        // Determine which date column to use
        $column = ($dateType === 'modified') ? 'post_modified' : 'post_date';
        $dateQuery['column'] = $column;

        switch ($range) {
            case 'last_24h':
            case '24':
                $startDate = clone $now;
                $startDate->modify('-24 hours');
                $dateQuery['after'] = $startDate->format('Y-m-d H:i:s');
                break;

            case 'last_7d':
            case '7':
                $startDate = clone $now;
                $startDate->modify('-7 days');
                $dateQuery['after'] = $startDate->format('Y-m-d H:i:s');
                break;

            case 'last_30d':
            case '30':
                $startDate = clone $now;
                $startDate->modify('-30 days');
                $dateQuery['after'] = $startDate->format('Y-m-d H:i:s');
                break;

            case 'last_90d':
            case '90':
                $startDate = clone $now;
                $startDate->modify('-90 days');
                $dateQuery['after'] = $startDate->format('Y-m-d H:i:s');
                break;

            case 'custom':
                $startDate = $condition['start_date'] ?? '';
                $endDate = $condition['end_date'] ?? '';

                if (!empty($startDate)) {
                    $dateQuery['after'] = $startDate . ' 00:00:00';
                }
                if (!empty($endDate)) {
                    $dateQuery['before'] = $endDate . ' 23:59:59';
                }

                // If neither start nor end date provided, return null
                if (empty($startDate) && empty($endDate)) {
                    return null;
                }
                break;

            default:
                return null;
        }

        // Ensure we have at least one date constraint
        if (empty($dateQuery['after']) && empty($dateQuery['before'])) {
            return null;
        }

        return $dateQuery;
    }
}
