<?php

namespace Notifal\Infrastructure\WordPress\Services;

use WP_Post;
use WP_Query;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class PostFetcher
 *
 * Fetches post data from WordPress.
 * Acts as a data adapter between Notifal and WordPress posts.
 *
 * @package Notifal\Infrastructure\WordPress\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class PostFetcher extends BaseContentFetcher
{
    /**
     * Retrieve a single random post from WordPress.
     *
     * @param array $filters Optional filters to apply
     * @return WP_Post|null Null if no post found.
     * @since 2.0.0
     */
    public function getRandom(array $filters = []): ?WP_Post
    {
        return $this->getRandomContent('post', $filters);
    }

    /**
     * Retrieve multiple random posts from WordPress for pool-based caching.
     *
     * @param int $count Number of posts to fetch (default: 20)
     * @param array $filters Optional filters to apply
     * @return WP_Post[] Array of WP_Post objects
     * @since 2.0.0
     */
    public function getRandomPool(int $count = 20, array $filters = []): array
    {
        return $this->getRandomPoolContent('post', $count, $filters);
    }

    /**
     * Count posts matching the provided filters.
     *
     * @param array $filters Optional filters to apply.
     * @return int Total matching post count.
     * @since 2.3.7
     */
    public function count(array $filters = []): int
    {
        return $this->countContent('post', $filters);
    }

    /**
     * Find a post by its ID.
     *
     * @param int $id Post ID.
     * @return WP_Post|null Null if post not found.
     * @since 2.0.0
     */
    public function findById(int $id): ?WP_Post
    {
        return $this->findContentById($id, 'post');
    }

    /**
     * Check if posts are valid and accessible.
     *
     * @param string $postType The post type to validate (should be 'post')
     * @return bool True if valid, false otherwise
     * @since 2.0.0
     */
    protected function isValidPostType(string $postType): bool
    {
        return $postType === 'post';
    }

    /**
     * Apply legacy filters specific to posts.
     *
     * @param array $args Base query arguments
     * @param array $filters Filter configuration
     * @return array Modified query arguments
     * @since 2.0.0
     */
    protected function applyLegacyFilters(array $args, array $filters): array
    {
        // Categories filter
        if (isset($filters['categories']) && !empty($filters['categories'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => array_map('intval', $filters['categories']),
                'operator' => 'IN'
            ];
        }

        // Tags filter
        if (isset($filters['tags']) && !empty($filters['tags'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'post_tag',
                'field'    => 'term_id',
                'terms'    => array_map('intval', $filters['tags']),
                'operator' => 'IN'
            ];
        }

        // Author filter (single author - legacy)
        if (isset($filters['author']) && !empty($filters['author'])) {
            $args['author'] = $filters['author'];
        }

        // Authors filter (multiple authors - new multi-filter system)
        if (isset($filters['authors']) && !empty($filters['authors'])) {
            $args['author__in'] = $filters['authors'];
        }

        // Specific posts filter
        if (isset($filters['posts']) && !empty($filters['posts'])) {
            $args['post__in'] = array_map('intval', $filters['posts']);
        }

        // Apply common filters (date_range, custom_filter)
        return $this->applyCommonLegacyFilters($args, $filters);
    }

    /**
     * Build taxonomy condition for posts.
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
        } elseif ($conditionType === 'tags') {
            return $this->buildTagsCondition($condition);
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

        return [
            'taxonomy' => 'category',
            'field'    => 'term_id',
            'terms'    => array_map('intval', $categories),
            'operator' => 'IN'
        ];
    }

    /**
     * Build tags condition for tax query.
     *
     * @param array $condition Tags condition
     * @return array|null Tax query condition or null
     * @since 2.0.0
     */
    private function buildTagsCondition(array $condition): ?array
    {
        $tags = $condition['tags'] ?? [];
        if (empty($tags)) {
            return null;
        }

        return [
            'taxonomy' => 'post_tag',
            'field'    => 'term_id',
            'terms'    => array_map('intval', $tags),
            'operator' => 'IN'
        ];
    }
}
