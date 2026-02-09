<?php

namespace Notifal\Infrastructure\WordPress\Services;

use WP_Post;


defined('ABSPATH') || exit;

/**
 * Class CustomPostTypeFetcher
 *
 * Fetches custom post type data from WordPress.
 * Acts as a data adapter between Notifal and WordPress custom post types.
 *
 * @package Notifal\Infrastructure\WordPress\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class CustomPostTypeFetcher extends BaseContentFetcher
{
    /**
     * Retrieve a single random custom post type item from WordPress.
     *
     * @param string $postType The custom post type name
     * @param array $filters Optional filters to apply
     * @return WP_Post|null Null if no post found.
     * @since 2.0.0
     */
    public function getRandom(string $postType, array $filters = []): ?WP_Post
    {
        return $this->getRandomContent($postType, $filters);
    }

    /**
     * Retrieve multiple random custom post type items from WordPress for pool-based caching.
     *
     * @param string $postType The custom post type name
     * @param int $count Number of posts to fetch (default: 20)
     * @param array $filters Optional filters to apply
     * @return WP_Post[] Array of WP_Post objects
     * @since 2.0.0
     */
    public function getRandomPool(string $postType, int $count = 20, array $filters = []): array
    {
        return $this->getRandomPoolContent($postType, $count, $filters);
    }

    /**
     * Find a custom post type item by its ID.
     *
     * @param int $id Post ID.
     * @param string $postType The custom post type name
     * @return WP_Post|null Null if post not found.
     * @since 2.0.0
     */
    public function findById(int $id, string $postType): ?WP_Post
    {
        return $this->findContentById($id, $postType);
    }

    /**
     * Check if a post type is valid and accessible.
     *
     * @param string $postType The post type to validate
     * @return bool True if valid, false otherwise
     * @since 2.0.0
     */
    protected function isValidPostType(string $postType): bool
    {
        // Check if post type exists
        if (!post_type_exists($postType)) {
            return false;
        }

        // Get post type object
        $postTypeObject = get_post_type_object($postType);

        // Ensure it's public or publicly queryable
        if (!$postTypeObject || (!$postTypeObject->public && !$postTypeObject->publicly_queryable)) {
            return false;
        }

        // Exclude WordPress built-in post types that we handle separately
        $excludedPostTypes = ['post', 'page', 'attachment', 'revision', 'nav_menu_item'];
        if (in_array($postType, $excludedPostTypes)) {
            return false;
        }

        return true;
    }

    /**
     * Apply legacy filters specific to custom post types.
     *
     * @param array $args Base query arguments
     * @param array $filters Filter configuration
     * @return array Modified query arguments
     * @since 2.0.0
     */
    protected function applyLegacyFilters(array $args, array $filters): array
    {
        // Taxonomies filter (generic for any custom taxonomy)
        if (isset($filters['taxonomies']) && !empty($filters['taxonomies'])) {
            foreach ($filters['taxonomies'] as $taxonomy => $terms) {
                if (taxonomy_exists($taxonomy) && !empty($terms)) {
                    $args['tax_query'][] = [
                        'taxonomy' => $taxonomy,
                        'field'    => 'term_id',
                        'terms'    => $terms,
                        'operator' => 'IN'
                    ];
                }
            }
        }

        // Specific items filter (post IDs)
        if (isset($filters['items']) && !empty($filters['items'])) {
            $args['post__in'] = $filters['items'];
        }

        // Author filter
        if (isset($filters['authors']) && !empty($filters['authors'])) {
            $args['author__in'] = $filters['authors'];
        } elseif (isset($filters['author']) && !empty($filters['author'])) {
            // Legacy single author support
            $args['author'] = $filters['author'];
        }

        // Status filter
        if (isset($filters['status']) && !empty($filters['status'])) {
            $args['post_status'] = $filters['status'];
        }

        // Apply common filters (date_range, custom_filter)
        return $this->applyCommonLegacyFilters($args, $filters);
    }

    /**
     * Build taxonomy condition for custom post types.
     *
     * @param array $condition Taxonomy condition
     * @return array|null Tax query condition or null
     * @since 2.0.0
     */
    protected function buildTaxonomyCondition(array $condition): ?array
    {
        $conditionType = $condition['type'] ?? '';

        if ($conditionType === 'categories') {
            return $this->buildCategoriesCondition($condition);
        } elseif ($conditionType === 'taxonomies') {
            return $this->buildTaxonomiesCondition($condition);
        }

        return null;
    }

    /**
     * Build categories condition for tax query.
     *
     * @param array $condition Categories condition
     * @return array|null Tax query condition or null
     * @since 2.0.0
     */
    private function buildCategoriesCondition(array $condition): ?array
    {
        $categories = $condition['categories'] ?? [];
        if (empty($categories)) {
            return null;
        }

        // Use specified taxonomy or auto-detect from the term IDs
        $taxonomy = $condition['taxonomy'] ?? null;
        
        // If no taxonomy specified, try to auto-detect from term IDs
        if (empty($taxonomy) && !empty($categories)) {
            $taxonomy = $this->detectTaxonomyFromTerms($categories);
        }
        
        // Final fallback to 'category' (for regular posts)
        if (empty($taxonomy)) {
            $taxonomy = 'category';
        }

        // Validate taxonomy exists
        if (!taxonomy_exists($taxonomy)) {
            return null;
        }

        return [
            'taxonomy' => $taxonomy,
            'field'    => 'term_id',
            'terms'    => array_map('intval', $categories),
            'operator' => 'IN'
        ];
    }
    
    /**
     * Detect taxonomy from term IDs.
     * Looks up each term to find which taxonomy it belongs to.
     *
     * @param array $termIds Array of term IDs
     * @return string|null Detected taxonomy name or null
     * @since 2.0.0
     */
    private function detectTaxonomyFromTerms(array $termIds): ?string
    {
        if (empty($termIds)) {
            return null;
        }
        
        // Try to get the taxonomy from the first term
        $firstTermId = reset($termIds);
        $term = get_term($firstTermId);
        
        if ($term && !is_wp_error($term) && !empty($term->taxonomy)) {
            return $term->taxonomy;
        }
        
        // If first term lookup failed, try other terms
        foreach ($termIds as $termId) {
            $term = get_term($termId);
            if ($term && !is_wp_error($term) && !empty($term->taxonomy)) {
                return $term->taxonomy;
            }
        }
        
        return null;
    }

    /**
     * Build taxonomies condition for tax query.
     *
     * @param array $condition Taxonomies condition
     * @return array|null Tax query condition or null
     * @since 2.0.0
     */
    private function buildTaxonomiesCondition(array $condition): ?array
    {
        $taxonomies = $condition['taxonomies'] ?? [];
        if (empty($taxonomies)) {
            return null;
        }

        // Build tax query for multiple taxonomies
        $taxQuery = ['relation' => 'AND'];

        foreach ($taxonomies as $taxonomy => $terms) {
            if (taxonomy_exists($taxonomy) && !empty($terms)) {
                $taxQuery[] = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $terms,
                    'operator' => 'IN'
                ];
            }
        }

        return count($taxQuery) > 1 ? $taxQuery : null;
    }
}
