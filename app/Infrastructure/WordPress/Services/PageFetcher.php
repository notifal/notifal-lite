<?php

namespace Notifal\Infrastructure\WordPress\Services;

use WP_Post;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class PageFetcher
 *
 * Fetches page data from WordPress.
 * Acts as a data adapter between Notifal and WordPress pages.
 *
 * @package Notifal\Infrastructure\WordPress\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class PageFetcher extends BaseContentFetcher
{
    /**
     * Retrieve a single random page from WordPress.
     *
     * @param array $filters Optional filters to apply
     * @return WP_Post|null Null if no page found.
     * @since 2.0.0
     */
    public function getRandom(array $filters = []): ?WP_Post
    {
        return $this->getRandomContent('page', $filters);
    }

    /**
     * Retrieve multiple random pages from WordPress for pool-based caching.
     *
     * @param int $count Number of pages to fetch (default: 20)
     * @param array $filters Optional filters to apply
     * @return WP_Post[] Array of WP_Post objects
     * @since 2.0.0
     */
    public function getRandomPool(int $count = 20, array $filters = []): array
    {
        return $this->getRandomPoolContent('page', $count, $filters);
    }

    /**
     * Count pages matching the provided filters.
     *
     * @param array $filters Optional filters to apply.
     * @return int Total matching page count.
     * @since 2.3.7
     */
    public function count(array $filters = []): int
    {
        return $this->countContent('page', $filters);
    }

    /**
     * Find a page by its ID.
     *
     * @param int $id Page ID.
     * @return WP_Post|null Null if page not found.
     * @since 2.0.0
     */
    public function findById(int $id): ?WP_Post
    {
        return $this->findContentById($id, 'page');
    }

    /**
     * Check if pages are valid and accessible.
     *
     * @param string $postType The post type to validate (should be 'page')
     * @return bool True if valid, false otherwise
     * @since 2.0.0
     */
    protected function isValidPostType(string $postType): bool
    {
        return $postType === 'page';
    }

    /**
     * Apply legacy filters specific to pages.
     *
     * @param array $args Base query arguments
     * @param array $filters Filter configuration
     * @return array Modified query arguments
     * @since 2.0.0
     */
    protected function applyLegacyFilters(array $args, array $filters): array
    {
        // Page template filter
        if (isset($filters['templates']) && !empty($filters['templates'])) {
            $args['meta_query'][] = [
                'key'     => '_wp_page_template',
                'value'   => $filters['templates'],
                'compare' => 'IN'
            ];
        }

        // Author filter (multiple authors - new multi-filter system)
        if (isset($filters['authors']) && !empty($filters['authors'])) {
            $args['author__in'] = $filters['authors'];
        } elseif (isset($filters['author']) && !empty($filters['author'])) {
            // Legacy single author support
            $args['author'] = $filters['author'];
        }

        // Parent page filter
        if (isset($filters['parent']) && is_numeric($filters['parent'])) {
            $args['post_parent'] = $filters['parent'];
        }

        // Status filter
        if (isset($filters['status']) && !empty($filters['status'])) {
            $args['post_status'] = $filters['status'];
        }

        // Specific pages filter
        if (isset($filters['pages']) && !empty($filters['pages'])) {
            $args['post__in'] = array_map('intval', $filters['pages']);
        }

        // Apply common filters (date_range, custom_filter)
        return $this->applyCommonLegacyFilters($args, $filters);
    }

    /**
     * Build taxonomy condition for pages.
     * Pages don't typically use categories/tags like posts, so this returns null.
     *
     * @param array $condition Taxonomy condition
     * @return array|null Tax query condition or null
     * @since 2.0.0
     */
    protected function buildTaxonomyCondition(array $condition): ?array
    {
        // Pages don't have categories/tags like posts do
        return null;
    }
}
