<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Utility;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Contracts\LabelProviderInterface;

defined('ABSPATH') || exit;

/**
 * Class LabelService
 *
 * Provides helper methods to interact with `notifal_label` taxonomy.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Application\Services
 */
class LabelService implements LabelProviderInterface
{
    /**
     * Register hooks for cache management.
     *
     * @since 2.0.0
     */
    public function register(): void
    {
        // Clear cache when terms are modified
        add_action('created_term', [$this, 'clearCacheOnTermChange'], 10, 3);
        add_action('edited_term', [$this, 'clearCacheOnTermChange'], 10, 3);
        add_action('delete_term', [$this, 'clearCacheOnTermChange'], 10, 3);
    }

    /**
     * Clear cache when label terms are modified.
     *
     * @param int $term_id Term ID
     * @param int $tt_id Term taxonomy ID
     * @param string $taxonomy Taxonomy name
     * @since 2.0.0
     */
    public function clearCacheOnTermChange(int $term_id, int $tt_id, string $taxonomy): void
    {
        if ($taxonomy === 'notifal_label') {
            $this->clearCache();
        }
    }
    /**
     * Cache key for label options.
     *
     * @since 2.0.0
     */
    private const CACHE_KEY = 'notifal_label_options';

    /**
     * Cache expiration time in seconds (5 minutes).
     *
     * @since 2.0.0
     */
    private const CACHE_EXPIRATION = 300;

    /**
     * Get all label terms for notifal_label taxonomy.
     *
     * Uses caching for better performance since label options are accessed frequently.
     *
     * @return array [slug => ['name' => string, 'id' => int]]
     * @since 2.0.0
     */
    public function getOptions(): array
    {
        // Try to get from cache first
        $cached_options = get_transient(self::CACHE_KEY);
        if (false !== $cached_options) {
            /**
             * Filters the cached label options array.
             *
             * @param array $cached_options Array of [slug => ['name' => string, 'id' => int]].
             * @since 2.0.0
             */
            return apply_filters(FilterHooks::ONPAGE_LABEL_OPTIONS, $cached_options);
        }

        $args = [
            'taxonomy'   => 'notifal_label',
            'hide_empty' => false,
        ];

        /**
         * Filters the arguments passed to get_terms for fetching label options.
         *
         * @param array $args The arguments array.
         * @since 2.0.0
         */
        $args = apply_filters(FilterHooks::ONPAGE_LABEL_GET_TERMS_ARGS, $args);

        $terms = get_terms($args);

        $options = [];
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $options[$term->slug] = [
                    'name' => $term->name,
                    'id'   => $term->term_id,
                ];
            }
        } else {
            // Log error for debugging but don't expose to user
            if (WP_DEBUG) {
                error_log(sprintf(
                    'Notifal LabelService: Failed to get label terms - %s',
                    $terms->get_error_message()
                ));
            }
        }

        // Cache the results
        set_transient(self::CACHE_KEY, $options, self::CACHE_EXPIRATION);

        /**
         * Filters the final label options array.
         *
         * @param array $options Array of [slug => ['name' => string, 'id' => int]].
         * @since 2.0.0
         */
        return apply_filters(FilterHooks::ONPAGE_LABEL_OPTIONS, $options);
    }

    /**
     * Clear the label options cache.
     *
     * Should be called when label terms are created, updated, or deleted.
     *
     * @return bool True on success, false on failure
     * @since 2.0.0
     */
    public function clearCache(): bool
    {
        return delete_transient(self::CACHE_KEY);
    }
}
