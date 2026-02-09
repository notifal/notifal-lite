<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Hooks;

use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\BlockEditorTemplateBuilder;
use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Traits\TemplateProcessingTrait;

defined('ABSPATH') || exit;

/**
 * Class FrontendContentProcessor
 *
 * Processes tags in notifal_template content on the frontend.
 * Ensures tags are rendered when templates are displayed to users.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Hooks
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FrontendContentProcessor
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
     * FrontendContentProcessor constructor.
     *
     * @param BlockEditorTemplateBuilder|null $templateBuilder Template builder service
     * @since 2.0.0
     */
    public function __construct(BlockEditorTemplateBuilder $templateBuilder = null)
    {
        $this->templateBuilder = $templateBuilder ?: notifal_app(BlockEditorTemplateBuilder::class);
    }

    /**
     * Register hooks for frontend content processing.
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        $instance = new self();

        // Hook into the_content filter for frontend rendering
        add_filter('the_content', [$instance, 'processContent'], 10, 1);

        // Hook into individual block rendering for Gutenberg blocks
        add_filter('render_block', [$instance, 'processBlockContent'], 10, 2);
    }

    /**
     * Process content through tag rendering for notifal_template post types.
     *
     * @param string $content The post content
     * @return string Processed content with tags rendered
     * @since 2.0.0
     */
    public function processContent(string $content): string
    {
        // Only process on frontend and for notifal_template post type
        if (is_admin() || !$this->shouldProcessCurrentPost()) {
            return $content;
        }

        // Skip Elementor templates - they should be processed by FrontendTemplateRenderer
        // which handles content source settings and proper context building
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
     * Process individual block content for notifal_template post types.
     *
     * @param string $block_content The block content
     * @param array $block The block data
     * @return string Processed block content with tags rendered
     * @since 2.0.0
     */
    public function processBlockContent(string $block_content, array $block): string
    {
        // Use dynamic context for notification rendering if active
        if ($this->isInActiveNotificationContext()) {
            return $this->processContentForNotificationContext($block_content);
        }

        // Only process on frontend and for notifal_template post type
        if (is_admin() || !$this->shouldProcessCurrentPost()) {
            return $block_content;
        }

        // Skip Elementor templates - they should be processed by FrontendTemplateRenderer
        // which handles content source settings and proper context building
        if ($this->isElementorTemplate()) {
            return $block_content;
        }

        return $this->templateBuilder->buildPreviewContent($block_content);
    }

} 
