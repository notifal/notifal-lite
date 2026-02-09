<?php

namespace Notifal\Infrastructure\WordPress\Admin\Settings\Services;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Post Type Discovery Service
 *
 * Handles discovery and analysis of WordPress post types for tag generation.
 * Provides comprehensive metadata about post types for smart tag creation.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class PostTypeDiscoveryService
{
    /**
     * Discover all available post types with metadata
     *
     * Analyzes WordPress installation to find all post types and gather
     * comprehensive metadata for smart tag generation.
     *
     * @return array Array of post type objects with metadata
     * @since 2.0.0
     */
    public function discoverPostTypes(): array
    {
        $discoveredTypes = [];

        // Get all registered post types
        $allPostTypes = get_post_types(['public' => true], 'objects');

        // Add some non-public but important post types
        $additionalTypes = get_post_types(['name' => ['attachment', 'revision', 'nav_menu_item']], 'objects');
        $allPostTypes = array_merge($allPostTypes, $additionalTypes);

        // Define default post types that are already handled by the main tag categories
        $defaultExcludedPostTypes = [
            // Core Tags
            'post',
            'page',
            // WooCommerce Tags
            'product',
            'shop_order',
            // Other internal/unwanted types
            'attachment',
            'revision',
            'nav_menu_item',
            'customize_changeset',
            'oembed_cache',
            'notifal_template'
        ];

        /**
         * Filters the list of excluded post types in post type discovery.
         *
         * Allows developers to modify which post types are excluded from
         * the post type discovery service used for tag generation.
         *
         * @param string[] $excludedTypes Array of post type names to exclude
         * @since 2.0.0
         */
        $excludedPostTypes = apply_filters(FilterHooks::POST_TYPE_DISCOVERY_EXCLUDED_TYPES, $defaultExcludedPostTypes);

        foreach ($allPostTypes as $postType) {
            // Skip post types that are already handled or are not relevant
            if (in_array($postType->name, $excludedPostTypes, true)) {
                continue;
            }

            $typeData = [
                'name' => $postType->name,
                'label' => $postType->label,
                'labels' => (array) $postType->labels,
                'public' => $postType->public,
                'has_archive' => $postType->has_archive,
                'supports' => get_all_post_type_supports($postType->name),
                'source' => $this->determinePostTypeSource($postType->name),
                'source_label' => $this->getPostTypeSourceLabel($postType->name),
                'count' => $this->getPostTypeCount($postType->name),
                'meta_fields' => $this->getPostTypeMetaFieldCount($postType->name),
                'sample_meta' => $this->getSampleMetaFields($postType->name),
                'taxonomies' => $this->getPostTypeTaxonomies($postType->name)
            ];

            $discoveredTypes[] = $typeData;
        }

        // Sort by source (core first, then plugin, then custom) and then by label
        usort($discoveredTypes, function($a, $b) {
            $sourceOrder = ['core' => 1, 'plugin' => 2, 'custom' => 3];
            $aOrder = $sourceOrder[$a['source']] ?? 4;
            $bOrder = $sourceOrder[$b['source']] ?? 4;

            if ($aOrder !== $bOrder) {
                return $aOrder - $bOrder;
            }

            return strcmp($a['label'], $b['label']);
        });

        return $discoveredTypes;
    }

    /**
     * Determine the source of a post type
     *
     * Identifies whether a post type comes from WordPress core, a plugin, or is custom.
     *
     * @param string $postType Post type name
     * @return string Source type: 'core', 'plugin', or 'custom'
     * @since 2.0.0
     */
    private function determinePostTypeSource(string $postType): string
    {
        // WordPress core post types
        $coreTypes = ['post', 'page'];
        if (in_array($postType, $coreTypes)) {
            return 'core';
        }

        // WooCommerce post types
        $wooTypes = ['product', 'shop_order', 'shop_coupon', 'product_variation'];
        if (in_array($postType, $wooTypes)) {
            return 'plugin';
        }

        // Check if registered by a plugin (has plugin-like characteristics)
        $postTypeObj = get_post_type_object($postType);
        if ($postTypeObj && isset($postTypeObj->_builtin) && !$postTypeObj->_builtin) {
            return 'plugin';
        }

        return 'custom';
    }

    /**
     * Get human-readable label for post type source
     *
     * @param string $postType Post type name
     * @return string Source label
     * @since 2.0.0
     */
    private function getPostTypeSourceLabel(string $postType): string
    {
        $source = $this->determinePostTypeSource($postType);

        switch ($source) {
            case 'core':
                return __('WordPress Core', 'notifal');
            case 'plugin':
                // Try to identify specific plugins
                if (in_array($postType, ['product', 'shop_order', 'shop_coupon'])) {
                    return __('WooCommerce', 'notifal');
                }
                return __('Plugin', 'notifal');
            default:
                return __('Custom', 'notifal');
        }
    }

    /**
     * Get count of posts for a post type
     *
     * @param string $postType Post type name
     * @return int Number of posts
     * @since 2.0.0
     */
    private function getPostTypeCount(string $postType): int
    {
        $counts = wp_count_posts($postType);

        if (!$counts) {
            return 0;
        }

        // Sum all published posts
        $total = 0;
        foreach ($counts as $status => $count) {
            if ($status !== 'auto-draft' && $status !== 'trash') {
                $total += (int) $count;
            }
        }

        return $total;
    }

    /**
     * Get count of unique meta fields for a post type
     *
     * @param string $postType Post type name
     * @return int Number of unique meta keys
     * @since 2.0.0
     */
    private function getPostTypeMetaFieldCount(string $postType): int
    {
        global $wpdb;

        // Get unique meta keys for this post type
        $query = $wpdb->prepare("
            SELECT COUNT(DISTINCT pm.meta_key)
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = %s
            AND pm.meta_key NOT LIKE '\_%'
            AND pm.meta_key NOT LIKE '%_edit_%'
        ", $postType);

        $count = $wpdb->get_var($query);

        return (int) $count;
    }

    /**
     * Get sample meta fields for a post type
     *
     * Retrieves real meta field examples with sample values for preview.
     *
     * @param string $postType Post type name
     * @return array Sample meta fields with values
     * @since 2.0.0
     */
    private function getSampleMetaFields(string $postType): array
    {
        global $wpdb;

        // Get common meta keys with sample values
        $query = $wpdb->prepare("
            SELECT pm.meta_key, pm.meta_value
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = %s
            AND pm.meta_key NOT LIKE '\_%'
            AND pm.meta_key NOT LIKE '%_edit_%'
            AND pm.meta_value != ''
            AND pm.meta_value IS NOT NULL
            GROUP BY pm.meta_key
            ORDER BY COUNT(*) DESC
            LIMIT 10
        ", $postType);

        $results = $wpdb->get_results($query);
        $sampleMeta = [];

        foreach ($results as $meta) {
            // Truncate long values for preview
            $value = $meta->meta_value;
            if (strlen($value) > 50) {
                $value = substr($value, 0, 47) . '...';
            }

            $sampleMeta[$meta->meta_key] = $value;
        }

        return $sampleMeta;
    }

    /**
     * Get taxonomies associated with a post type
     *
     * @param string $postType Post type name
     * @return array Taxonomy information
     * @since 2.0.0
     */
    private function getPostTypeTaxonomies(string $postType): array
    {
        $taxonomies = get_object_taxonomies($postType, 'objects');
        $taxonomyData = [];

        foreach ($taxonomies as $taxonomy) {
            // Get sample terms for this taxonomy
            $terms = get_terms([
                'taxonomy' => $taxonomy->name,
                'number' => 3,
                'hide_empty' => false
            ]);

            $sampleTerms = '';
            if (!is_wp_error($terms) && !empty($terms)) {
                $termNames = wp_list_pluck($terms, 'name');
                $sampleTerms = implode(', ', $termNames);
            }

            $taxonomyData[] = [
                'name' => $taxonomy->name,
                'label' => $taxonomy->label,
                'public' => $taxonomy->public,
                'hierarchical' => $taxonomy->hierarchical,
                'sample_terms' => $sampleTerms
            ];
        }

        return $taxonomyData;
    }

    /**
     * Get filtered custom post type names for template operations.
     *
     * Returns an array of post type names excluding WordPress built-in and
     * other unwanted types, optimized for template preview and processing.
     *
     * @return array Array of custom post type names
     * @since 2.0.0
     */
    public function getFilteredCustomPostTypeNames(): array
    {
        $discoveredTypes = $this->discoverPostTypes();
        return array_column($discoveredTypes, 'name');
    }

    /**
     * Get fallback value for custom post type tags in template previews.
     *
     * @param string $tagKey The tag key to get fallback for
     * @return string|null Fallback value or null if not a custom post type tag
     * @since 2.0.0
     */
    public function getCustomPostTypeFallback(string $tagKey): ?string
    {
        $customPostTypes = $this->getFilteredCustomPostTypeNames();

        // Check if tag matches any custom post type pattern
        foreach ($customPostTypes as $postType) {
            $pattern = '/^' . preg_quote($postType, '/') . '_(.+)$/';
            if (preg_match($pattern, $tagKey, $matches)) {
                $field = $matches[1];

                // Get post type object to get labels
                $postTypeObject = get_post_type_object($postType);
                $postTypeName = $postTypeObject ? $postTypeObject->labels->singular_name : ucfirst($postType);

                // Generate fallback based on field type
                switch ($field) {
                    case 'title':
                        return "Sample {$postTypeName}";
                    case 'content':
                        return "This is sample {$postTypeName} content for preview purposes.";
                    case 'excerpt':
                        return "This is a sample {$postTypeName} excerpt.";
                    case 'author':
                        return 'Sample Author';
                    case 'date':
                        return '2024-12-31';
                    case 'date_diff':
                        return '1 hour ago';
                    case 'url':
                        return home_url("/sample-{$postType}");
                    case 'id':
                        return '123';
                    case 'slug':
                        return "sample-{$postType}";
                    default:
                        // Check if it's a meta field
                        if (strpos($field, 'meta_') === 0) {
                            $metaKey = substr($field, 5); // Remove 'meta_' prefix
                            return "Sample {$metaKey} value";
                        }
                        return "Sample {$field}";
                }
            }
        }

        return null;
    }
}
