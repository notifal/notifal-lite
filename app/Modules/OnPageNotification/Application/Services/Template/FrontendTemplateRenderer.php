<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Template;

use Notifal\Infrastructure\WordPress\Elementor\Helpers\ElementorHelper;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class FrontendTemplateRenderer
 *
 * Main service for rendering templates with full styles for frontend notifications.
 * Orchestrates the rendering process using specialized renderer classes.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FrontendTemplateRenderer
{
    /**
     * @var TemplateContextBuilder
     */
    private $contextBuilder;

    /**
     * @var ElementorTemplateRenderer
     */
    private $elementorRenderer;

    /**
     * @var BlockEditorTemplateRenderer
     */
    private $blockEditorRenderer;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->contextBuilder = notifal_app(TemplateContextBuilder::class);
        $this->elementorRenderer = notifal_app(ElementorTemplateRenderer::class);
        $this->blockEditorRenderer = notifal_app(BlockEditorTemplateRenderer::class);
    }

    /**
     * Render a template with full styles for frontend display.
     *
     * @param int $templateId Template ID
     * @param array $context Context data for tag rendering
     * @param array $contentSourceSettings Content source settings for proper data fetching
     * @return array Array with 'html' and 'assets' keys
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public function renderForFrontend(int $templateId, array $context = [], array $contentSourceSettings = []): array
    {
        try {
            $template = Helper::getPostSafe($templateId, 'notifal_template');
            if (!$template) {
                return [
                    'html' => '',
                    'assets' => [],
                    'error' => 'Template not found'
                ];
            }

            $isElementor = ElementorHelper::hasBuilder($template);

            // Extract raw content for context building
            if ($isElementor) {
                $rawContent = $this->contextBuilder->extractRawContentForElementor($template);
            } else {
                $rawContent = $this->contextBuilder->extractRawContentFromBlocks($template);
            }

            // Build frontend context
            $frontendContext = $this->contextBuilder->buildContext($rawContent, $context, $contentSourceSettings);

            // Check for no matching data
            if ($this->contextBuilder->hasNoMatchingData($frontendContext)) {
                return [
                    'html' => '',
                    'assets' => [],
                    'no_matching_data' => true,
                    'applied_filters' => $frontendContext['applied_filters'] ?? [],
                    'builder_type' => $isElementor ? 'elementor' : 'block_editor'
                ];
            }

            // Render using appropriate renderer
            if ($isElementor) {
                return $this->elementorRenderer->render($template, $frontendContext);
            } else {
                return $this->blockEditorRenderer->render($template, $frontendContext);
            }

        } catch (\Exception $e) {
            // Fallback: return the raw template content so notifications can still display
            $template = get_post($templateId);
            if ($template) {
                return [
                    'html' => $template->post_content,
                    'assets' => [],
                    'error' => 'Error in template renderer: ' . $e->getMessage(),
                    'builder_type' => 'unknown',
                    'fallback' => true
                ];
            }

            return [
                'html' => '',
                'assets' => [],
                'error' => 'Error in template renderer: ' . $e->getMessage(),
                'builder_type' => 'unknown'
            ];
        }
    }

    /**
     * Clear the request-scoped context cache.
     *
     * This should be called when you want to ensure fresh random data
     * for different notifications or page requests.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function clearContextCache(): void
    {
        TemplateContextBuilder::clearContextCache();
    }
}
