<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax;

defined('ABSPATH') || exit;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Templates\Infrastructure\WordPress\Repositories\TemplateQuery;
use Notifal\Modules\Templates\Presentation\Admin\ViewComponents\TemplateRenderer;

/**
 * Class LoadMoreTemplatesController
 *
 * Handles AJAX requests for loading more templates in the OnPage Notification editor.
 * Supports both Elementor and Block Editor templates with pagination.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax
 */
class LoadMoreTemplatesController
{
    /**
     * Register AJAX handlers.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register(): void
    {
        add_action('wp_ajax_notifal_load_more_templates', [self::class, 'handleLoadMore']);
    }

    /**
     * AJAX: Load more templates for a specific builder.
     *
     * @since 2.0.0
     * @return void
     */
    public static function handleLoadMore(): void
    {
        try {
            // Verify AJAX request with nonce and user capabilities
            notifal_verify_ajax_request('notifal_load_more_templates', 'edit_posts');

            // Sanitize and validate input parameters
            $builder = sanitize_text_field($_POST['builder'] ?? '');
            $offset = absint($_POST['offset'] ?? 0);
            $limit = 6; // Load 6 templates per request to match frontend expectations

            // Validate builder parameter
            if (!in_array($builder, ['elementor', 'block-editor'], true)) {
                notifal_json_error(__('Invalid builder type.', 'notifal'));
            }

            /**
             * Fires before loading more templates via AJAX.
             *
             * @since 2.0.0
             * @param string $builder The builder type (elementor or block-editor)
             * @param int $offset The offset for pagination
             * @param int $limit The number of templates to load
             */
            do_action(ActionHooks::TEMPLATES_LOAD_MORE_BEFORE, $builder, $offset, $limit);

            // Get templates with offset and limit
            $templates = self::getTemplatesWithOffset($builder, $offset, $limit);

            // Get total count for this builder
            $totalCount = TemplateQuery::getByBuilderCount($builder);

            // Calculate if there are more templates to load
            $loadedCount = count($templates);
            $hasMore = ($loadedCount > 0) && ($loadedCount >= $limit) && (($offset + $loadedCount) < $totalCount);

            // Render template HTML
            $html = '';
            foreach ($templates as $template) {
                ob_start();
                TemplateRenderer::renderPreviewCard($template);
                $html .= ob_get_clean();
            }

            // Prepare response data
            $responseData = [
                'html' => $html,
                'has_more' => $hasMore,
                'total_count' => $totalCount,
                'loaded_count' => $loadedCount,
                'next_offset' => $offset + $limit,
            ];

            /**
             * Filter the load more templates response data.
             *
             * @since 2.0.0
             * @param array $responseData The response data array
             * @param string $builder The builder type
             * @param int $offset The current offset
             * @param int $limit The limit used
             */
            $responseData = apply_filters(FilterHooks::TEMPLATES_LOAD_MORE_RESPONSE, $responseData, $builder, $offset, $limit);

            /**
             * Fires after loading more templates via AJAX.
             *
             * @since 2.0.0
             * @param string $builder The builder type
             * @param array $templates The loaded templates
             * @param array $responseData The response data
             */
            do_action(ActionHooks::TEMPLATES_LOAD_MORE_AFTER, $builder, $templates, $responseData);

            wp_send_json_success($responseData);

        } catch (\Exception $e) {
            notifal_json_error(__('An unexpected error occurred. Please try again.', 'notifal'));
        }
    }

    /**
     * Get templates with offset and limit for pagination.
     *
     * Uses an efficient approach to get exactly the required number of templates
     * by first fetching a larger set and then filtering for valid content.
     *
     * @since 2.0.0
     * @param string $builder The builder type (elementor or block-editor)
     * @param int $offset The offset for pagination
     * @param int $limit The number of templates to retrieve
     * @return \WP_Post[] Array of template posts
     */
    private static function getTemplatesWithOffset(string $builder, int $offset, int $limit): array
    {
        $metaQuery = [];

        if ($builder === 'elementor') {
            $metaQuery[] = [
                'key'     => '_elementor_edit_mode',
                'value'   => 'builder',
                'compare' => '='
            ];
        } else {
            $metaQuery[] = [
                'key'     => '_elementor_edit_mode',
                'compare' => 'NOT EXISTS'
            ];
        }

        $metaQuery = apply_filters(FilterHooks::TEMPLATES_BUILDER_META_QUERY, $metaQuery, $builder);

   
        $postsToFetch = $limit + $offset + 20; // Add buffer for empty templates

        $queryArgs = [
            'post_type'      => 'notifal_template',
            'post_status'    => 'publish',
            'posts_per_page' => $postsToFetch,
            'offset'         => 0, // We'll handle offset in PHP after filtering
            'meta_query'     => $metaQuery,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ];

        $queryArgs = apply_filters(FilterHooks::TEMPLATES_BUILDER_QUERY_ARGS, $queryArgs, $builder, $limit);

        $query = new \WP_Query($queryArgs);

        $validTemplates = [];
        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                // Filter out truly empty templates
                if (TemplateQuery::hasTemplateContent($post, $builder)) {
                    $validTemplates[] = $post;
                }
            }
        }

        // Apply offset and limit to the valid templates
        return array_slice($validTemplates, $offset, $limit);
    }

} 
