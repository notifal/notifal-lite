<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Hooks;

use Notifal\Modules\Templates\Infrastructure\Shared\Traits\PreviewContextTrait;
use Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Services\ElementorTemplateBuilder;

defined('ABSPATH') || exit;

/**
 * Class PreviewRenderer
 *
 * Hooks into Elementor live preview to replace tags with resolved data.
 * Ensures preview content displays resolved tag values while preventing
 * interference with frontend notification rendering.
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Hooks
 * @author Hossein <hossein@notifal.com>
 */
class PreviewRenderer
{
    use PreviewContextTrait;

    /**
     * Elementor template builder service
     *
     * @var ElementorTemplateBuilder
     * @since 2.0.0
     */
    private $templateBuilder;

    /**
     * PreviewRenderer constructor
     *
     * @param ElementorTemplateBuilder $templateBuilder Template builder service
     * @since 2.0.0
     */
    public function __construct(ElementorTemplateBuilder $templateBuilder)
    {
        $this->templateBuilder = $templateBuilder;
    }

    /**
     * Register WordPress hooks for Elementor preview rendering
     *
     * @return void
     * @since 2.0.0
     */
    public function register(): void
    {
        add_filter('elementor/frontend/the_content', [$this, 'renderPreview'], 10, 2);
        add_filter('elementor/widget/render_content', [$this, 'renderPreview'], 10, 2);
        add_action('elementor/frontend/after_render', [$this, 'renderPreviewLive'], 10, 1);
    }

    /**
     * Render live preview with resolved tag data for content filters
     *
     * @param string $content Content to process
     * @param mixed $widget Widget instance (optional)
     * @return string Processed content with resolved tags
     * @since 2.0.0
     */
    public function renderPreview(string $content, $widget = null): string
    {
        // Only process preview content in actual preview contexts
        // Don't interfere with frontend notification rendering
        if (!$this->isActualPreviewContext()) {
            return $content;
        }

        return $this->templateBuilder->buildPreviewContent($content);
    }

    /**
     * Render live preview for Elementor elements after rendering
     *
     * @param mixed $element Elementor element instance
     * @return void
     * @since 2.0.0
     */
    public function renderPreviewLive($element): void
    {
        // Only process preview content in actual preview contexts
        if (!$this->isActualPreviewContext()) {
            return;
        }

        $content = $element->get_settings('content') ?? '';

        if (empty($content)) {
            return;
        }

        $rendered = $this->templateBuilder->buildPreviewContent($content);
        $element->set_settings('content', $rendered);
    }
}
