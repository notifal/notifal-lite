<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Hooks;

use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\BlockEditorTemplateBuilder;
use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Traits\TemplateProcessingTrait;

defined('ABSPATH') || exit;

/**
 * Class PreviewRenderer
 *
 * Hooks into block editor preview to replace tags with resolved data.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Hooks
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class PreviewRenderer
{
    use TemplateProcessingTrait;

    /**
     * Template builder service instance
     *
     * @var BlockEditorTemplateBuilder
     * @since 2.0.0
     */
    private $templateBuilder;

    /**
     * PreviewRenderer constructor.
     *
     * @param BlockEditorTemplateBuilder|null $templateBuilder Template builder service
     * @since 2.0.0
     */
    public function __construct(BlockEditorTemplateBuilder $templateBuilder = null)
    {
        $this->templateBuilder = $templateBuilder ?: notifal_app(BlockEditorTemplateBuilder::class);
    }

    /**
     * Register hooks for block editor preview and frontend preview.
     *
     * @return void
     * @since 2.0.0
     */
    public function register(): void
    {
        // Editor preview (backend)
        add_filter('the_content', [$this, 'renderPreview'], 20, 1);
        add_filter('render_block', [$this, 'renderBlockPreview'], 20, 2);
        // REST API preview (frontend)
        add_filter('rest_prepare_notifal_template', [$this, 'restPrepareTemplate'], 20, 3);
    }

    /**
     * Render preview for the_content in the block editor and frontend.
     *
     * @param string $content Content to process
     * @return string Processed content with tags rendered
     * @since 2.0.0
     */
    public function renderPreview(string $content): string
    {
        // Only process content for notifal_template post type
        if (!$this->shouldProcessContent()) {
            return $content;
        }

        // Skip Elementor templates - they should be processed by FrontendTemplateRenderer
        if ($this->isElementorTemplate()) {
            return $content;
        }

        // Skip during frontend notification rendering to avoid context conflicts
        if ($this->isInActiveNotificationContext()) {
            return $content;
        }

        return $this->templateBuilder->buildPreviewContent($content);
    }

    /**
     * Render preview for individual blocks.
     *
     * @param string $block_content Block content to process
     * @param array $block Block data
     * @return string Processed block content with tags rendered
     * @since 2.0.0
     */
    public function renderBlockPreview(string $block_content, array $block): string
    {
        // Skip during frontend notification rendering to avoid context conflicts
        if ($this->isInActiveNotificationContext()) {
            return $block_content;
        }

        // Only process content for notifal_template post type
        if (!$this->shouldProcessContent()) {
            return $block_content;
        }

        // Skip Elementor templates - they should be processed by FrontendTemplateRenderer
        if ($this->isElementorTemplate()) {
            return $block_content;
        }

        return $this->templateBuilder->buildPreviewContent($block_content);
    }

    /**
     * Prepare REST API response for template preview (frontend).
     *
     * @param \WP_REST_Response $response REST API response object
     * @param \WP_Post $post Post object
     * @param \WP_REST_Request $request REST API request object
     * @return \WP_REST_Response Modified REST API response
     * @since 2.0.0
     */
    public function restPrepareTemplate(\WP_REST_Response $response, \WP_Post $post, \WP_REST_Request $request): \WP_REST_Response
    {
        // Only process notifal_template post type
        if (empty($response->data['content']['rendered']) || $post->post_type !== 'notifal_template') {
            return $response;
        }

        $response->data['content']['rendered'] = $this->templateBuilder->buildPreviewContent($response->data['content']['rendered']);
        return $response;
    }
} 
