<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax;

defined('ABSPATH') || exit;

use Notifal\Domain\Users\UserFetcherInterface;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax\GetTemplateContentController;
use Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax\SaveNotificationController;
use Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax\ToggleStatusController;

/**
 * Class EditPageAjaxController
 *
 * Handles AJAX requests on the OnPage Notification edit screen.
 *
 * @since 2.0.0
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax
 */
class EditPageAjaxController
{
    /**
     * Register AJAX handlers.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register(): void
    {
        add_action('wp_ajax_notifal_search_posts', [self::class, 'searchPosts']);
        add_action('wp_ajax_notifal_search_terms', [self::class, 'searchTerms']);
        add_action('wp_ajax_notifal_search_users', [self::class, 'searchUsers']);
        add_action('wp_ajax_notifal_search_post_types', [self::class, 'searchPostTypes']);
        add_action('wp_ajax_notifal_search_post_types_for_categories', [self::class, 'searchPostTypesForCategories']);
        add_action('wp_ajax_notifal_search_templates', [self::class, 'searchTemplates']);
        add_action('wp_ajax_notifal_get_post_titles', [self::class, 'getPostTitles']);
        add_action('wp_ajax_notifal_add_label_term', [self::class, 'addLabelTerm']);
        add_action('wp_ajax_notifal_remove_label_term', [self::class, 'deleteLabelTerm']);
        GetTemplateContentController::register();
        
        // Register save notification handlers
        SaveNotificationController::register();
        
        // Register toggle status handlers
        ToggleStatusController::register();
        
        // Add handler for getting term titles by IDs
        add_action('wp_ajax_notifal_get_term_titles', [self::class, 'getTermTitles']);
        
        // Add handler for getting user titles by IDs
        add_action('wp_ajax_notifal_get_user_titles', [self::class, 'getUserTitles']);
    }

    /**
     * AJAX: Search posts by title and post type.
     *
     * @return void
     * @since 2.0.0
     */
    public static function searchPosts(): void
    {
        check_ajax_referer('notifal_search_nonce', 'nonce');

        $search = sanitize_text_field($_GET['s'] ?? '');
        $postType = sanitize_text_field($_GET['post_type'] ?? 'page');
        $contextPostTypes = sanitize_text_field($_GET['context_post_types'] ?? '');
        $results = [];

        if (!empty($search) && strlen($search) >= 2) {
            // Handle special post type cases
            $postTypes = self::getPostTypesForSearch($postType, $contextPostTypes);
            
            // Search only in post title, not content
            global $wpdb;

            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $post_types_placeholders = implode(',', array_fill(0, count($postTypes), '%s'));

            $sql = $wpdb->prepare(
                "SELECT ID, post_title, post_type, post_parent
                FROM {$wpdb->posts}
                WHERE post_type IN ({$post_types_placeholders})
                AND post_status = 'publish'
                AND post_title LIKE %s
                ORDER BY post_title ASC
                LIMIT 50", // Increase limit to accommodate grouped results
                array_merge($postTypes, [$search_term])
            );

            $posts = $wpdb->get_results($sql);

            // Separate variations from regular posts first
            $variations = [];
            $regularPosts = [];

            foreach ($posts as $post) {
                if ($post->post_type === 'product_variation') {
                    $variations[] = $post;
                } else {
                    $regularPosts[] = $post;
                }
            }

            // Group product variations under their parent products
            $groupedResults = [];
            $parentProducts = [];

            // Process variations first
            foreach ($variations as $variation) {
                $parentId = $variation->post_parent;

                // Get parent product details if not already processed
                if (!isset($parentProducts[$parentId])) {
                    $parentProduct = get_post($parentId);
                    if ($parentProduct && $parentProduct->post_type === 'product') {
                        $parentProducts[$parentId] = $parentProduct;
                        $groupedResults[] = [
                            'id' => $parentId,
                            'title' => $parentProduct->post_title,
                            'image' => has_post_thumbnail($parentId) ? get_the_post_thumbnail_url($parentId, 'thumbnail') : '',
                            'type' => 'product',
                            'variations' => []
                        ];
                    }
                }

                // Add variation to parent if parent exists
                if (isset($parentProducts[$parentId])) {
                    $variationTitle = self::getVariationTitle($variation);
                    // Find the correct parent result and add variation
                    foreach ($groupedResults as &$result) {
                        if ($result['id'] === $parentId && isset($result['variations'])) {
                            $result['variations'][] = [
                                'id' => $variation->ID,
                                'title' => $variationTitle,
                                'type' => 'product_variation'
                            ];
                            break;
                        }
                    }
                }
            }

            // Get list of parent IDs that have variations
            $parentIdsWithVariations = array_keys($parentProducts);

            // Process regular posts, but skip parent products that already have variations
            foreach ($regularPosts as $post) {
                // Skip if this is a parent product that already has variations displayed
                if (in_array($post->ID, $parentIdsWithVariations)) {
                    continue;
                }

                $image = '';
                if (has_post_thumbnail($post->ID)) {
                    $image = get_the_post_thumbnail_url($post->ID, 'thumbnail');
                }

                $groupedResults[] = [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'image' => $image,
                    'type' => $post->post_type,
                ];
            }

            // Limit to 20 results, prioritizing products with variations
            $results = array_slice($groupedResults, 0, 20);
        }

        // Apply filter for extensibility
        $results = apply_filters(FilterHooks::ONPAGE_ADMIN_SEARCH_RESULTS, $results, $postType, $search);

        wp_send_json($results);
    }

    /**
     * AJAX: Search users by username, email, or display name.
     *
     * @return void
     * @since 2.0.0
     */
    public static function searchUsers(): void
    {
        check_ajax_referer('notifal_search_nonce', 'nonce');

        $search = sanitize_text_field($_GET['s'] ?? '');
        $results = [];

        if (!empty($search) && strlen($search) >= 2) {
            $users = get_users([
                'search' => '*' . $search . '*',
                'search_columns' => ['user_login', 'user_email', 'display_name'],
                'number' => 20,
                'orderby' => 'display_name',
                'order' => 'ASC'
            ]);

            foreach ($users as $user) {
                $results[] = [
                    'id' => $user->ID,
                    'title' => $user->display_name . ' (' . $user->user_login . ')',
                    'image' => get_avatar_url($user->ID, ['size' => 32]),
                    'type' => 'user'
                ];
            }
        }

        wp_send_json($results);
    }

    /**
     * AJAX: Search post types by name.
     *
     * @return void
     * @since 2.0.0
     */
    public static function searchPostTypes(): void
    {
        check_ajax_referer('notifal_search_nonce', 'nonce');

        $search = sanitize_text_field($_GET['s'] ?? '');
        $results = [];

        if (!empty($search) && strlen($search) >= 2) {
            // Get all registered post types
            $postTypes = get_post_types(['public' => true], 'objects');
            
            foreach ($postTypes as $postType => $postTypeObj) {
                // Check if the post type name or label contains the search term
                $name = $postType;
                $label = $postTypeObj->labels->singular_name ?? $postTypeObj->labels->name ?? $postType;
                
                if (stripos($name, $search) !== false || stripos($label, $search) !== false) {
                    $results[] = [
                        'id' => $postType,
                        'title' => $label . ' (' . $name . ')',
                        'type' => 'post_type',
                        'count' => wp_count_posts($postType)->publish ?? 0,
                    ];
                }
            }
        }

        // Apply filter for extensibility
        $results = apply_filters(FilterHooks::ONPAGE_ADMIN_SEARCH_RESULTS, $results, 'post_type', $search);

        wp_send_json($results);
    }

    /**
     * AJAX: Search post types specifically for categories context.
     *
     * @return void
     * @since 2.0.0
     */
    public static function searchPostTypesForCategories(): void
    {
        check_ajax_referer('notifal_search_nonce', 'nonce');

        $search = sanitize_text_field($_GET['s'] ?? '');
        $results = [];

        if (!empty($search) && strlen($search) >= 2) {
            // Get all registered post types that have taxonomies (for categories)
            $postTypes = get_post_types(['public' => true], 'objects');
            
            foreach ($postTypes as $postType => $postTypeObj) {
                // Check if this post type has any taxonomies
                $taxonomies = get_object_taxonomies($postType, 'names');
                if (empty($taxonomies)) {
                    continue;
                }
                
                // Check if the post type name or label contains the search term
                $name = $postType;
                $label = $postTypeObj->labels->singular_name ?? $postTypeObj->labels->name ?? $postType;
                
                if (stripos($name, $search) !== false || stripos($label, $search) !== false) {
                    $results[] = [
                        'id' => $postType,
                        'title' => $label . ' (' . $name . ')',
                        'type' => 'post_type',
                        'count' => wp_count_posts($postType)->publish ?? 0,
                    ];
                }
            }
        }

        // Apply filter for extensibility
        $results = apply_filters(FilterHooks::ONPAGE_ADMIN_SEARCH_RESULTS, $results, 'post_type', $search);

        wp_send_json($results);
    }

    /**
     * AJAX: Get post titles by IDs.
     *
     * @return void
     * @since 2.0.0
     */
    public static function getPostTitles(): void
    {
        check_ajax_referer('notifal_search_nonce', 'nonce');

        // Handle both GET and POST requests
        $postIds = sanitize_text_field($_POST['post_ids'] ?? $_GET['post_ids'] ?? '');
        $results = [];

        if (!empty($postIds)) {
            $ids = array_map('intval', explode(',', $postIds));
            $ids = array_filter($ids); // Remove any invalid IDs

            if (!empty($ids)) {
                global $wpdb;
                
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                $sql = $wpdb->prepare(
                    "SELECT ID, post_title, post_type 
                    FROM {$wpdb->posts} 
                    WHERE ID IN ({$placeholders}) 
                    AND post_status = 'publish'",
                    $ids
                );
                
                $posts = $wpdb->get_results($sql);
                
                foreach ($posts as $post) {
                    $results[] = [
                        'id' => (int) $post->ID, // Ensure it's explicitly an integer
                        'title' => $post->post_title,
                        'type' => $post->post_type,
                        'url' => get_permalink($post->ID),
                    ];
                }
            }
        }

        // Return in the expected format with success flag
        wp_send_json_success($results);
    }

    /**
     * AJAX: Get user titles by IDs.
     *
     * @return void
     * @since 2.0.0
     */
    public static function getUserTitles(): void
    {
        check_ajax_referer('notifal_search_nonce', 'nonce');

        // Handle both GET and POST requests
        $userIds = sanitize_text_field($_POST['user_ids'] ?? $_GET['user_ids'] ?? '');
        $results = [];
        


        if (!empty($userIds)) {
            $ids = array_map('intval', explode(',', $userIds));
            $ids = array_filter($ids); // Remove any invalid IDs

            if (!empty($ids)) {
                $userFetcher = notifal_app(UserFetcherInterface::class);

                foreach ($ids as $userId) {
                    $user = $userFetcher->findById($userId);
                    
                    if ($user) {
                        // Build display name with additional info
                        $displayName = $user->getDisplayName();
                        $additionalInfo = [];
                        
                        if (!empty($user->getUsername()) && $user->getUsername() !== $displayName) {
                            $additionalInfo[] = '@' . $user->getUsername();
                        }
                        
                        if (!empty($user->getEmail())) {
                            $additionalInfo[] = $user->getEmail();
                        }
                        
                        if (!empty($additionalInfo)) {
                            $displayName .= ' (' . implode(', ', $additionalInfo) . ')';
                        }

                        $results[] = [
                            'id' => (int) $user->getId(),
                            'title' => $displayName,
                            'email' => $user->getEmail(),
                            'username' => $user->getUsername(),
                            'firstName' => $user->getFirstName(),
                            'lastName' => $user->getLastName(),
                        ];
                    }
                }
            }
        }

        // Return in the expected format with success flag
        wp_send_json_success($results);
    }

    /**
     * AJAX: Search terms by name and taxonomy.
     *
     * @return void
     * @since 2.0.0
     */
    public static function searchTerms(): void
    {
        check_ajax_referer('notifal_search_nonce', 'nonce');

        $search = sanitize_text_field($_GET['s'] ?? '');
        $postType = sanitize_text_field($_GET['post_type'] ?? '');
        $contextPostTypes = sanitize_text_field($_GET['context_post_types'] ?? '');
        $results = [];

        if (!empty($search) && strlen($search) >= 2) {
            // Handle direct taxonomy search - prioritize direct taxonomy names over context
            if (taxonomy_exists($postType) || in_array($postType, ['category', 'product_cat', 'post_tag', 'product_tag'])) {
                $taxonomies = [$postType];
            } else {
                // Get post types to filter by
                $postTypes = [];
                if (!empty($contextPostTypes)) {
                    $postTypes = array_map('sanitize_text_field', explode(',', $contextPostTypes));
                    $postTypes = array_filter($postTypes);
                } else {
                    // Default to common post types if no context provided
                    $postTypes = ['post', 'product'];
                }

                // Get taxonomies based on post types
                $taxonomies = [];
                foreach ($postTypes as $pt) {
                    switch ($pt) {
                        case 'post':
                        case 'page':
                            $taxonomies[] = 'category';
                            break;
                        case 'product':
                            $taxonomies[] = 'product_cat';
                            break;
                        default:
                            // For custom post types, try to get their taxonomies
                            $customTaxonomies = get_object_taxonomies($pt, 'names');
                            if (!empty($customTaxonomies)) {
                                $taxonomies = array_merge($taxonomies, $customTaxonomies);
                            }
                            break;
                    }
                }

                // Remove duplicates and internal taxonomies
                $taxonomies = array_unique($taxonomies);
                
                // Filter out internal/system taxonomies
                $taxonomies = array_filter($taxonomies, function($taxonomy) {
                    $excludedTaxonomies = ['post_format', 'nav_menu', 'link_category', 'post_translations'];
                    return !in_array($taxonomy, $excludedTaxonomies);
                });
            }

            foreach ($taxonomies as $taxonomy) {
                // Use WordPress's built-in name__like for better results
                $search_args = [
                    'taxonomy' => $taxonomy,
                    'hide_empty' => false,
                    'number' => 15,
                    'orderby' => 'name',
                    'order' => 'ASC',
                    'name__like' => $search
                ];
                
                $terms = get_terms($search_args);
                
                if (is_wp_error($terms)) {
                    wp_send_json_error(
                        'Error fetching categories: ' . $terms->get_error_message()
                    );
                    return;
                }
                
                if (!is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $results[] = [
                            'id' => $term->term_id,
                            'title' => $term->name,
                            'image' => '', // Terms don't have images by default
                            'taxonomy' => $taxonomy,
                        ];
                        
                        // Limit results to 10 for better performance
                        if (count($results) >= 10) {
                            break;
                        }
                    }
                }
            }
        }

        // Apply filter for extensibility
        $results = apply_filters(FilterHooks::ONPAGE_ADMIN_SEARCH_RESULTS, $results, $postType, $search);

        wp_send_json($results);
    }

    /**
     * AJAX: Get term titles by IDs.
     *
     * @return void
     * @since 2.0.0
     */
    public static function getTermTitles(): void
    {
        check_ajax_referer('notifal_search_nonce', 'nonce');

        // Handle both GET and POST requests
        $termIds = sanitize_text_field($_POST['term_ids'] ?? $_GET['term_ids'] ?? '');
        $results = [];

        if (!empty($termIds)) {
            $ids = array_map('intval', explode(',', $termIds));
            $ids = array_filter($ids); // Remove any invalid IDs

            if (!empty($ids)) {
                global $wpdb;
                
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                $sql = $wpdb->prepare(
                    "SELECT term_id, name 
                    FROM {$wpdb->terms} 
                    WHERE term_id IN ({$placeholders})",
                    $ids
                );
                
                $terms = $wpdb->get_results($sql);
                
                foreach ($terms as $term) {
                    $results[] = [
                        'id' => (int) $term->term_id, // Ensure it's explicitly an integer
                        'title' => $term->name,
                    ];
                }
            }
        }

        // Return in the expected format with success flag
        wp_send_json_success($results);
    }

    /**
     * Get post types for search based on the requested type and context.
     *
     * @param string $requestedType
     * @param string $contextPostTypes Comma-separated list of post types from context
     * @return array
     * @since 2.0.0
     */
    private static function getPostTypesForSearch(string $requestedType, string $contextPostTypes = ''): array
    {
        // If context post types are provided, use them to filter the search
        if (!empty($contextPostTypes)) {
            $contextPostTypes = explode(',', $contextPostTypes);
            $contextPostTypes = array_map('sanitize_text_field', $contextPostTypes);
            $contextPostTypes = array_filter($contextPostTypes);
            
            // Validate that the context post types are valid
            $validPostTypes = get_post_types(['public' => true], 'names');
            $contextPostTypes = array_intersect($contextPostTypes, $validPostTypes);
            
            if (!empty($contextPostTypes)) {
                return $contextPostTypes;
            }
        }

        // If no context post types or they were invalid, fall back to requested type
        switch ($requestedType) {
            case 'any':
                // When searching within a context, 'any' means search within the context
                // If no context is provided, fall back to common post types
                return ['post', 'page', 'product'];
            case 'page':
                return ['page'];
            case 'post':
                return ['post'];
            case 'product':
                return ['product', 'product_variation'];
            case 'post_type':
                // For post type search, we'll handle this differently in the frontend
                // This is just a fallback
                return ['post', 'page', 'product'];
            default:
                return [$requestedType];
        }
    }

    /**
     * Get taxonomies for search based on the requested type.
     *
     * @param string $requestedType
     * @return array
     * @since 2.0.0
     */
    private static function getTaxonomiesForSearch(string $requestedType): array
    {
        switch ($requestedType) {
            case 'any_category':
                return ['category', 'product_cat'];
            case 'term_category':
                return ['category'];
            case 'term_product_cat':
            case 'product_cat':
                return ['product_cat'];
            case 'category':
                return ['category'];
            default:
                return [$requestedType];
        }
    }

    /**
     * AJAX: Add a new label term.
     *
     * @return void
     * @since 2.0.0
     */
    public static function addLabelTerm(): void
    {
        check_ajax_referer('notifal_add_label', 'nonce');

        $label = sanitize_text_field($_POST['name'] ?? '');

        if (empty($label)) {
            wp_send_json_error(__('Label is required.', 'notifal'));
        }

        $term = wp_insert_term($label, 'notifal_label');

        if (is_wp_error($term)) {
            wp_send_json_error($term->get_error_message());
        }

        wp_send_json_success([
            'term' => get_term($term['term_id'], 'notifal_label')
        ]);
    }

    /**
     * AJAX: Delete a label term.
     *
     * @return void
     * @since 2.0.0
     */
    public static function deleteLabelTerm(): void
    {
        check_ajax_referer('notifal_remove_label', 'nonce');

        $termId = (int) ($_POST['term_id'] ?? 0);

        // Fallback to slug if term_id is not provided
        if (!$termId && !empty($_POST['slug'])) {
            $slug = sanitize_text_field($_POST['slug']);
            $term = get_term_by('slug', $slug, 'notifal_label');
            if ($term) {
                $termId = (int) $term->term_id;
            }
        }

        if (!$termId) {
            wp_send_json_error(__('Invalid term ID or slug.', 'notifal'));
        }

        $result = wp_delete_term($termId, 'notifal_label');

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(['deleted' => true]);
    }

    /**
     * Get a readable title for a product variation.
     *
     * @param object $variation The variation post object
     * @return string The variation title
     * @since 2.0.0
     */
    private static function getVariationTitle($variation) {
        // Get variation attributes
        $attributes = wc_get_product_variation_attributes($variation->ID);
        $variationTitle = $variation->post_title;

        // If no specific title, build one from attributes
        if (empty($variationTitle) || $variationTitle === 'Product #' . $variation->ID) {
            $attributeStrings = [];
            foreach ($attributes as $attribute => $value) {
                if (!empty($value)) {
                    // Remove 'pa_' prefix and format attribute name
                    $attributeName = str_replace('pa_', '', $attribute);
                    $attributeName = str_replace('-', ' ', $attributeName);
                    $attributeName = ucwords($attributeName);
                    $attributeStrings[] = $attributeName . ': ' . $value;
                }
            }

            if (!empty($attributeStrings)) {
                $variationTitle = implode(', ', $attributeStrings);
            } else {
                $variationTitle = 'Variation #' . $variation->ID;
            }
        }

        return $variationTitle;
    }
}
