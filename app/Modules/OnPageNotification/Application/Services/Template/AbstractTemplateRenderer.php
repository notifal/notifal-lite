<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Template;

use Notifal\Modules\OnPageNotification\Application\Traits\TagProcessingTrait;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\WidgetContextProvider;

defined('ABSPATH') || exit;

/**
 * Abstract base class for template renderers.
 *
 * Provides common functionality for rendering templates with context processing,
 * widget context management, and error handling.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
abstract class AbstractTemplateRenderer
{
    use TagProcessingTrait;

    /**
     * Render a template with frontend context.
     *
     * @param \WP_Post $template Template post
     * @param array $frontendContext Already-built frontend context
     * @return array Rendered template data
     * @since 2.0.0
     */
    public function render(\WP_Post $template, array $frontendContext): array
    {
        try {
            // Set up widget context before rendering
            WidgetContextProvider::setContext($frontendContext);

            // Render content using builder-specific logic
            $content = $this->renderContent($template, $frontendContext);

            // Process tags with the same frontend context
            $content = $this->processTagsWithContext($content, $frontendContext);

            // Get builder-specific assets
            $assets = $this->getAssets($template);

            // Clear widget context after rendering
            WidgetContextProvider::clearContext();

            return [
                'html' => $content,
                'assets' => $assets,
                'builder_type' => $this->getBuilderType()
            ];

        } catch (\Exception $e) {
            // Ensure context is cleared even on error
            WidgetContextProvider::clearContext();

            return $this->handleRenderError($template, $e);
        }
    }

    /**
     * Render the actual template content using builder-specific logic.
     *
     * @param \WP_Post $template Template post
     * @param array $frontendContext Frontend context
     * @return string Rendered content
     * @since 2.0.0
     */
    abstract protected function renderContent(\WP_Post $template, array $frontendContext): string;

    /**
     * Get builder-specific assets for the template.
     *
     * @param \WP_Post $template Template post
     * @return array Asset URLs or inline content
     * @since 2.0.0
     */
    abstract protected function getAssets(\WP_Post $template): array;

    /**
     * Get the builder type identifier.
     *
     * @return string Builder type string
     * @since 2.0.0
     */
    abstract protected function getBuilderType(): string;


    /**
     * Handle rendering errors with fallback to raw content.
     *
     * @param \WP_Post $template Template post
     * @param \Exception $exception The caught exception
     * @return array Error response with fallback content
     * @since 2.0.0
     */
    private function handleRenderError(\WP_Post $template, \Exception $exception): array
    {
        return [
            'html' => $template->post_content,
            'assets' => [],
            'error' => 'Error rendering ' . $this->getBuilderType() . ' template: ' . $exception->getMessage(),
            'builder_type' => $this->getBuilderType()
        ];
    }
}
