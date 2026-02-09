<?php

namespace Notifal\Domain\Tags\Controllers;

defined('ABSPATH') || exit;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Shared\Utils\Helper;

/**
 * Class DynamicKeysController
 *
 * Handles API logic for fetching dynamic keys (user, order, product).
 * Provides searchable key lists for dynamic tag resolution.
 *
 * @package Notifal\Domain\Tags\Controllers
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class DynamicKeysController
{
    /**
     * Get the most used meta keys for a given entity type.
     *
     * Retrieves the most frequently used meta keys from the database for different entity types.
     * Results can be filtered to allow customization and extension.
     *
     * @param string $type Entity type: user, order, product, custom_posttype.
     * @param string $postType Optional post type for custom_posttype
     * @return array Most used meta keys for the entity type.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function getMostUsedKeys(string $type, string $postType = ''): array
    {
        $keys = [];

        switch ($type) {
            case 'user':
                $keys = $this->getMostUsedUserKeys();
                break;
            case 'order':
                $keys = $this->getMostUsedOrderKeys();
                break;
            case 'product':
                $keys = $this->getMostUsedProductKeys();
                break;
            case 'custom_posttype':
                $keys = $this->getCustomPostTypeMostUsedKeys($postType);
                break;
            case 'post':
                $keys = $this->getMostUsedPostKeys();
                break;
            case 'page':
                $keys = $this->getMostUsedPageKeys();
                break;
            case 'comment':
                $keys = $this->getMostUsedCommentKeys();
                break;
        }

        /**
         * Filters the most used meta keys for dynamic tag resolution.
         *
         * @param string[] $keys     Array of meta key names.
         * @param string   $type     Entity type: user, order, product, custom_posttype.
         * @param string   $postType Post type name (for custom_posttype only).
         */
        return apply_filters(FilterHooks::DYNAMIC_KEYS_MOST_USED, $keys, $type, $postType);
    }

    /**
     * Get dynamic keys based on entity type and search.
     *
     * @param \WP_REST_Request $request
     * @return array|\WP_Error Array of keys on success, WP_Error on failure
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public function getKeys(\WP_REST_Request $request)
    {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return notifal_wp_error('invalid_nonce', __('Security check failed.', 'notifal'), ['status' => 403]);
        }
        $type = Helper::sanitizeInput($request->get_param('type'), 'key');
        $search = Helper::sanitizeInput($request->get_param('search'), 'text');
        $postType = Helper::sanitizeInput($request->get_param('post_type'), 'key'); // For custom post types
        $validTypes = ['user', 'order', 'product', 'custom_posttype', 'post', 'page', 'comment'];
        if (!in_array($type, $validTypes, true)) {
            return notifal_wp_error('invalid_type', __('Invalid entity type specified.', 'notifal'), ['status' => 400]);
        }
        // If no search, return most used keys for the type
        if (empty($search)) {
            return $this->getMostUsedKeys($type, $postType);
        }
        // If searching, return all matching keys from DB
        switch ($type) {
            case 'user':
                return $this->searchUserKeys($search);
            case 'order':
                return $this->searchOrderKeys($search);
            case 'product':
                return $this->searchProductKeys($search);
            case 'custom_posttype':
                return $this->searchCustomPostTypeKeys($search, $postType);
            case 'post':
                return $this->searchPostKeys($search);
            case 'page':
                return $this->searchPageKeys($search);
            case 'comment':
                return $this->searchCommentKeys($search);
        }
        return [];
    }

    /**
     * Search user meta keys in DB by search term.
     *
     * @param string $search
     * @return array
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function searchUserKeys(string $search): array
    {
        global $wpdb;
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT meta_key FROM {$wpdb->usermeta} WHERE meta_key LIKE %s LIMIT 30",
            '%' . $wpdb->esc_like($search) . '%'
        ));

        $keys = array_map(function($result) {
            return Helper::sanitizeInput($result, 'key');
        }, $results);

        /**
         * Filters the search results for user meta keys.
         *
         * @param string[] $keys   Array of meta key names from search.
         * @param string   $search The search term used.
         * @param string   $type   Entity type (user).
         */
        return apply_filters(FilterHooks::DYNAMIC_KEYS_SEARCH_RESULTS, $keys, $search, 'user', '');
    }

    /**
     * Search order meta keys in DB by search term, restricted to shop_order post type.
     *
     * @param string $search
     * @return array
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function searchOrderKeys(string $search): array
    {
        global $wpdb;
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT pm.meta_key
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = %s AND pm.meta_key LIKE %s
             LIMIT 30",
            'shop_order',
            '%' . $wpdb->esc_like($search) . '%'
        ));

        $keys = array_map(function($result) {
            return Helper::sanitizeInput($result, 'key');
        }, $results);

        /**
         * Filters the search results for order meta keys.
         *
         * @param string[] $keys   Array of meta key names from search.
         * @param string   $search The search term used.
         * @param string   $type   Entity type (order).
         */
        return apply_filters(FilterHooks::DYNAMIC_KEYS_SEARCH_RESULTS, $keys, $search, 'order', '');
    }

    /**
     * Search product meta keys in DB by search term, restricted to product post type.
     *
     * @param string $search
     * @return array
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function searchProductKeys(string $search): array
    {
        global $wpdb;
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT pm.meta_key
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = %s AND pm.meta_key LIKE %s
             LIMIT 30",
            'product',
            '%' . $wpdb->esc_like($search) . '%'
        ));

        $keys = array_map(function($result) {
            return Helper::sanitizeInput($result, 'key');
        }, $results);

        /**
         * Filters the search results for product meta keys.
         *
         * @param string[] $keys   Array of meta key names from search.
         * @param string   $search The search term used.
         * @param string   $type   Entity type (product).
         */
        return apply_filters(FilterHooks::DYNAMIC_KEYS_SEARCH_RESULTS, $keys, $search, 'product', '');
    }

    /**
     * Get most used user meta keys from the database.
     *
     * @return array Most used user meta keys
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function getMostUsedUserKeys(): array
    {
        // Define most commonly used WordPress user meta keys
        $commonWordPressUserKeys = [
            'first_name',
            'last_name',
            'nickname',
            'description',
            'wp_capabilities',
            'wp_user_level',
            'dismissed_wp_pointers',
            'show_welcome_panel',
            'wp_dashboard_quick_press_last_post_id',
            'wp_user-settings',
            'wp_user-settings-time',
        ];

        global $wpdb;

        // Get the most frequently used user meta keys (excluding common WordPress keys)
        $results = $wpdb->get_col(
            "SELECT um.meta_key
             FROM {$wpdb->usermeta} um
             WHERE um.meta_key NOT LIKE '\_%'
             GROUP BY um.meta_key
             ORDER BY COUNT(*) DESC
             LIMIT 15"
        );

        // Combine common WordPress user keys with most used custom keys
        $allKeys = array_merge($commonWordPressUserKeys, $results);

        // Remove duplicates and limit to 25 total
        $uniqueKeys = array_unique($allKeys);
        $uniqueKeys = array_slice($uniqueKeys, 0, 25);

        return array_map(function($result) {
            return Helper::sanitizeInput($result, 'key');
        }, $uniqueKeys);
    }

    /**
     * Get most used order meta keys from the database.
     *
     * @return array Most used order meta keys
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function getMostUsedOrderKeys(): array
    {
        // Define most commonly used WooCommerce order meta keys
        $commonWooCommerceOrderKeys = [
            '_order_total',
            '_order_tax',
            '_order_shipping',
            '_order_shipping_tax',
            '_payment_method',
            '_payment_method_title',
            '_transaction_id',
            '_customer_ip_address',
            '_customer_user_agent',
            '_created_via',
            '_cart_hash',
            '_billing_first_name',
            '_billing_last_name',
            '_billing_company',
            '_billing_email',
            '_billing_phone',
            '_billing_address_1',
            '_billing_address_2',
            '_billing_city',
            '_billing_state',
            '_billing_postcode',
            '_billing_country',
            '_shipping_first_name',
            '_shipping_last_name',
            '_shipping_company',
            '_shipping_address_1',
            '_shipping_address_2',
            '_shipping_city',
            '_shipping_state',
            '_shipping_postcode',
            '_shipping_country',
            '_order_currency',
            '_cart_discount',
            '_cart_discount_tax',
            '_order_discount',
            '_order_discount_tax',
            '_recorded_sales',
            '_download_permissions_granted'
        ];

        global $wpdb;

        // Get the most frequently used meta keys for shop_order post type (excluding common WooCommerce keys)
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT pm.meta_key
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = %s
             AND pm.meta_key NOT LIKE '\_%'
             GROUP BY pm.meta_key
             ORDER BY COUNT(*) DESC
             LIMIT 15",
            'shop_order'
        ));

        // Combine common WooCommerce order keys with most used custom keys
        $allKeys = array_merge($commonWooCommerceOrderKeys, $results);

        // Remove duplicates and limit to 30 total
        $uniqueKeys = array_unique($allKeys);
        $uniqueKeys = array_slice($uniqueKeys, 0, 30);

        return array_map(function($result) {
            return Helper::sanitizeInput($result, 'key');
        }, $uniqueKeys);
    }

    /**
     * Get most used product meta keys from the database.
     *
     * @return array Most used product meta keys
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function getMostUsedProductKeys(): array
    {
        // Define most commonly used WooCommerce product meta keys
        $commonWooCommerceKeys = [
            '_price',
            '_regular_price',
            '_sale_price',
            '_sku',
            '_stock',
            '_stock_status',
            '_manage_stock',
            '_weight',
            '_length',
            '_width',
            '_height',
            '_virtual',
            '_downloadable',
            '_product_attributes',
            '_product_version',
            '_visibility',
            '_featured',
            '_backorders',
            '_sold_individually',
            'total_sales'
        ];

        global $wpdb;

        // Get the most frequently used meta keys for product post type (excluding common WooCommerce keys)
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT pm.meta_key
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = %s
             AND pm.meta_key NOT LIKE '\_%'
             GROUP BY pm.meta_key
             ORDER BY COUNT(*) DESC
             LIMIT 15",
            'product'
        ));

        // Combine common WooCommerce keys with most used custom keys
        $allKeys = array_merge($commonWooCommerceKeys, $results);

        // Remove duplicates and limit to 25 total
        $uniqueKeys = array_unique($allKeys);
        $uniqueKeys = array_slice($uniqueKeys, 0, 25);

        return array_map(function($result) {
            return Helper::sanitizeInput($result, 'key');
        }, $uniqueKeys);
    }

    /**
     * Get most used post meta keys from the database.
     *
     * @return array Most used post meta keys
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function getMostUsedPostKeys(): array
    {
        // Define most commonly used WordPress post meta keys
        $commonWordPressPostKeys = [
            '_edit_lock',
            '_edit_last',
            '_wp_page_template',
            '_thumbnail_id',
        ];

        global $wpdb;

        // Get the most frequently used meta keys for post post type (excluding common WordPress keys)
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT pm.meta_key
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = %s
             AND pm.meta_key NOT LIKE '\_%'
             GROUP BY pm.meta_key
             ORDER BY COUNT(*) DESC
             LIMIT 15",
            'post'
        ));

        // Combine common WordPress post keys with most used custom keys
        $allKeys = array_merge($commonWordPressPostKeys, $results);

        // Remove duplicates and limit to 25 total
        $uniqueKeys = array_unique($allKeys);
        $uniqueKeys = array_slice($uniqueKeys, 0, 25);

        return array_map(function($result) {
            return Helper::sanitizeInput($result, 'key');
        }, $uniqueKeys);
    }

    /**
     * Get most used page meta keys from the database.
     *
     * @return array Most used page meta keys
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function getMostUsedPageKeys(): array
    {
        // Define most commonly used WordPress page meta keys
        $commonWordPressPageKeys = [
            '_edit_lock',
            '_edit_last',
            '_wp_page_template',
            '_thumbnail_id'
        ];

        global $wpdb;

        // Get the most frequently used meta keys for page post type (excluding common WordPress keys)
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT pm.meta_key
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = %s
             AND pm.meta_key NOT LIKE '\_%'
             GROUP BY pm.meta_key
             ORDER BY COUNT(*) DESC
             LIMIT 15",
            'page'
        ));

        // Combine common WordPress page keys with most used custom keys
        $allKeys = array_merge($commonWordPressPageKeys, $results);

        // Remove duplicates and limit to 25 total
        $uniqueKeys = array_unique($allKeys);
        $uniqueKeys = array_slice($uniqueKeys, 0, 25);

        return array_map(function($result) {
            return Helper::sanitizeInput($result, 'key');
        }, $uniqueKeys);
    }

    /**
     * Get most used comment meta keys from the database.
     *
     * @return array Most used comment meta keys
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function getMostUsedCommentKeys(): array
    {
        global $wpdb;

        // Get the most frequently used comment meta keys (excluding private keys)
        $results = $wpdb->get_col(
            "SELECT cm.meta_key
             FROM {$wpdb->commentmeta} cm
             WHERE cm.meta_key NOT LIKE '\_%'
             GROUP BY cm.meta_key
             ORDER BY COUNT(*) DESC
             LIMIT 20"
        );

        return array_map(function($result) {
            return Helper::sanitizeInput($result, 'key');
        }, $results);
    }

    /**
     * Get most used meta keys for a specific custom post type.
     *
     * @param string $postType Custom post type name
     * @return array Most used meta keys for the custom post type
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function getCustomPostTypeMostUsedKeys(string $postType): array
    {
        if (empty($postType)) {
            return [];
        }

        global $wpdb;
        
        // Get the most frequently used meta keys for this post type
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT pm.meta_key
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = %s 
             AND pm.meta_key NOT LIKE '\_%'
             GROUP BY pm.meta_key
             ORDER BY COUNT(*) DESC
             LIMIT 20",
            $postType
        ));
        
        return array_map(function($result) {
            return Helper::sanitizeInput($result, 'key');
        }, $results);
    }

    /**
     * Search custom post type meta keys in DB by search term, restricted to specific post type.
     *
     * @param string $search Search term
     * @param string $postType Custom post type name
     * @return array Meta keys matching the search
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function searchCustomPostTypeKeys(string $search, string $postType): array
    {
        if (empty($postType)) {
            return [];
        }

        global $wpdb;
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT pm.meta_key
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = %s AND pm.meta_key LIKE %s
             LIMIT 30",
            $postType,
            '%' . $wpdb->esc_like($search) . '%'
        ));

        $keys = array_map(function($result) {
            return Helper::sanitizeInput($result, 'key');
        }, $results);

        /**
         * Filters the search results for custom post type meta keys.
         *
         * @param string[] $keys     Array of meta key names from search.
         * @param string   $search   The search term used.
         * @param string   $type     Entity type (custom_posttype).
         * @param string   $postType Post type name.
         */
        return apply_filters(FilterHooks::DYNAMIC_KEYS_SEARCH_RESULTS, $keys, $search, 'custom_posttype', $postType);
    }

    /**
     * Search post meta keys in DB by search term, restricted to post post type.
     *
     * @param string $search
     * @return array
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function searchPostKeys(string $search): array
    {
        global $wpdb;
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT pm.meta_key
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = %s AND pm.meta_key LIKE %s
             LIMIT 30",
            'post',
            '%' . $wpdb->esc_like($search) . '%'
        ));

        $keys = array_map(function($result) {
            return Helper::sanitizeInput($result, 'key');
        }, $results);

        /**
         * Filters the search results for post meta keys.
         *
         * @param string[] $keys   Array of meta key names from search.
         * @param string   $search The search term used.
         * @param string   $type   Entity type (post).
         */
        return apply_filters(FilterHooks::DYNAMIC_KEYS_SEARCH_RESULTS, $keys, $search, 'post', '');
    }

    /**
     * Search page meta keys in DB by search term, restricted to page post type.
     *
     * @param string $search
     * @return array
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function searchPageKeys(string $search): array
    {
        global $wpdb;
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT pm.meta_key
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = %s AND pm.meta_key LIKE %s
             LIMIT 30",
            'page',
            '%' . $wpdb->esc_like($search) . '%'
        ));

        $keys = array_map(function($result) {
            return Helper::sanitizeInput($result, 'key');
        }, $results);

        /**
         * Filters the search results for page meta keys.
         *
         * @param string[] $keys   Array of meta key names from search.
         * @param string   $search The search term used.
         * @param string   $type   Entity type (page).
         */
        return apply_filters(FilterHooks::DYNAMIC_KEYS_SEARCH_RESULTS, $keys, $search, 'page', '');
    }

    /**
     * Search comment meta keys in DB by search term.
     *
     * @param string $search
     * @return array
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function searchCommentKeys(string $search): array
    {
        global $wpdb;
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT meta_key FROM {$wpdb->commentmeta} WHERE meta_key LIKE %s LIMIT 30",
            '%' . $wpdb->esc_like($search) . '%'
        ));

        $keys = array_map(function($result) {
            return Helper::sanitizeInput($result, 'key');
        }, $results);

        /**
         * Filters the search results for comment meta keys.
         *
         * @param string[] $keys   Array of meta key names from search.
         * @param string   $search The search term used.
         * @param string   $type   Entity type (comment).
         */
        return apply_filters(FilterHooks::DYNAMIC_KEYS_SEARCH_RESULTS, $keys, $search, 'comment', '');
    }
}
