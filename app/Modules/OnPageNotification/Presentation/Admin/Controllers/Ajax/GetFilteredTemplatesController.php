<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Services\Template\TemplateFilterService;
use Notifal\Modules\Templates\Presentation\Admin\ViewComponents\TemplateRenderer;

defined('ABSPATH') || exit;

/**
 * AJAX Controller for retrieving filtered templates based on content source type.
 *
 * This controller handles filtering templates by content source type (static/dynamic)
 * and builder type (elementor/block-editor) with pagination support.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax
 */
class GetFilteredTemplatesController
{
    /**
     * Register AJAX handlers.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register(): void
    {
        add_action('wp_ajax_notifal_get_filtered_templates', [self::class, 'handle']);
    }

    /**
     * Handle the AJAX request to get filtered templates.
     *
     * Processes the request to filter templates by content source type and builder,
     * applies pagination, and returns rendered HTML along with pagination metadata.
     *
     * @since 2.0.0
     * @return void
     */
    public static function handle(): void
    {
        try {
            // Verify AJAX request with nonce and user capabilities
            notifal_verify_ajax_request('notifal_get_filtered_templates', 'edit_posts');

            // Sanitize and validate input parameters
            $contentSourceType = sanitize_text_field($_POST['content_source_type'] ?? 'dynamic');
            $builder = sanitize_text_field($_POST['builder'] ?? '');
            $offset = absint($_POST['offset'] ?? 0);
            $limit = absint($_POST['limit'] ?? 6);

            // Validate content source type parameter
            if (!in_array($contentSourceType, ['static', 'dynamic'], true)) {
                notifal_json_error(__('Invalid content source type.', 'notifal'));
            }

            // Validate builder parameter
            if (!in_array($builder, ['elementor', 'block-editor'], true)) {
                notifal_json_error(__('Invalid builder type.', 'notifal'));
            }

            /**
             * Fires before filtering templates via AJAX.
             *
             * @since 2.0.0
             * @param string $contentSourceType The content source type (static or dynamic)
             * @param string $builder The builder type (elementor or block-editor)
             * @param int $offset The offset for pagination
             * @param int $limit The number of templates to load
             */
            do_action(ActionHooks::TEMPLATES_FILTER_BEFORE, $contentSourceType, $builder, $offset, $limit);

            // Get the currently selected template ID from the request 
            $selectedTemplateId = 0;
            if (isset($_POST['selected_template_id']) && $_POST['selected_template_id'] !== '') {
                $selectedTemplateId = absint($_POST['selected_template_id']);
            }

            // Get filtered templates with pagination directly from service
            $templates = TemplateFilterService::filterTemplatesByContentType($contentSourceType, $builder, $limit, $offset, $selectedTemplateId);

            // Generate HTML for templates (null allowed for renderer)
            $html = self::renderTemplatesHtml($templates, $selectedTemplateId > 0 ? $selectedTemplateId : null);

            // Get total count for pagination
            $totalCount = TemplateFilterService::getFilteredTemplateCount($contentSourceType, $builder);
            $loadedCount = count($templates);
            $nextOffset = $offset + $loadedCount;
            $hasMore = $nextOffset < $totalCount;

            // Prepare response data
            $responseData = [
                'success' => true,
                'html' => $html,
                'loaded_count' => $loadedCount,
                'total_count' => $totalCount,
                'next_offset' => $nextOffset,
                'has_more' => $hasMore,
                'content_source_type' => $contentSourceType,
                'builder' => $builder,
            ];

            /**
             * Filter the filtered templates response data.
             *
             * @since 2.0.0
             * @param array $responseData The response data array
             * @param string $contentSourceType The content source type
             * @param string $builder The builder type
             * @param int $offset The current offset
             * @param int $limit The limit used
             */
            $responseData = apply_filters(FilterHooks::TEMPLATES_FILTER_RESPONSE, $responseData, $contentSourceType, $builder, $offset, $limit);

            /**
             * Fires after filtering templates via AJAX.
             *
             * @since 2.0.0
             * @param string $contentSourceType The content source type
             * @param string $builder The builder type
             * @param array $templates The loaded templates
             * @param array $responseData The response data
             */
            do_action(ActionHooks::TEMPLATES_FILTER_AFTER, $contentSourceType, $builder, $templates, $responseData);

            wp_send_json_success($responseData);

        } catch (\Throwable $e) {
            // Catch both Exception and Error
            error_log('Notifal GetFilteredTemplates Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            notifal_json_error(__('An unexpected error occurred. Please try again.', 'notifal'));
        }
    }

    /**
     * Render HTML for an array of templates.
     *
     * @since 2.0.0
     * @param \WP_Post[] $templates Array of template posts
     * @param int|null $selectedTemplateId The ID of the currently selected template
     * @return string Rendered HTML
     */
    private static function renderTemplatesHtml(array $templates, ?int $selectedTemplateId): string
    {
        $html = '';
        foreach ($templates as $template) {
            ob_start();
            TemplateRenderer::renderPreviewCard($template, $selectedTemplateId);
            $html .= ob_get_clean();
        }
        return $html;
    }
} 
