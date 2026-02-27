<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Template;

use Notifal\Infrastructure\WordPress\Elementor\Helpers\ElementorHelper;
use Notifal\Modules\Templates\Application\Services\FeaturedImageResolver;
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

            // Preserve immediate-display flag so Elementor can apply deferred featured image
            if (!empty($context['for_immediate_display'])) {
                $frontendContext['for_immediate_display'] = true;
            }

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
                $result = $this->elementorRenderer->render($template, $frontendContext);

                // For immediate display, Elementor may serve cached content so featured image has no context.
                // Replace featured image area with placeholder and attach real image HTML for deferred frontend swap.
                if (!empty($frontendContext['for_immediate_display']) && !empty($result['html'])) {
                    $result = self::applyDeferredFeaturedImageForElementor($result, $frontendContext);
                }

                return $result;
            }

            return $this->blockEditorRenderer->render($template, $frontendContext);

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

    /**
     * For Elementor + immediate display: replace featured image widget output with a placeholder
     * and attach the context-resolved featured image HTML for deferred frontend swap.
     *
     * Elementor may serve cached content when get_builder_content_for_display runs during
     * page load, so the Product Image widget can render without WidgetContextProvider and show
     * a placeholder. This method swaps that area for a known placeholder and provides the
     * correct image HTML (using the same context as tags) so the frontend can inject it after a short delay.
     *
     * @param array $result Render result with 'html', 'assets', 'builder_type'.
     * @param array $frontendContext Frontend context used for tag resolution (product, order, etc.).
     * @return array Result with modified 'html' and added 'deferred_featured_image_html' when applicable.
     * @since 2.0.0
     */
    private static function applyDeferredFeaturedImageForElementor(array $result, array $frontendContext): array
    {
        $html = $result['html'] ?? '';
        if ($html === '') {
            return $result;
        }

        // Match Elementor Product Image widget output: wrapper div containing inner notifal-pulse-img div
        $pattern = '/<div[^>]*notifal-featured-image-wrapper[^>]*>[\s\S]*?<\/div>\s*<\/div>/';
        $placeholder = '<div class="notifal-featured-image-deferred-placeholder" data-notifal-deferred-image="1"></div>';

        if (!preg_match($pattern, $html)) {
            return $result;
        }

        // Resolve featured image HTML with same context as tag resolution (matches product/order/post etc.)
        $imageHtml = FeaturedImageResolver::getFeaturedImageHtml(
            $frontendContext,
            'large',
            [
                'loading' => 'lazy',
                'class'   => 'notifal-featured-image',
            ],
            'auto'
        );

        // Wrap in same structure as widget so styling (e.g. wrapper alignment) still applies
        $deferredHtml = '<div class="notifal-featured-image-wrapper notifal-flex notifal-full-width">'
            . '<div class="notifal-pulse-img">'
            . $imageHtml
            . '</div></div>';

        $result['html'] = preg_replace($pattern, $placeholder, $html, 1);
        $result['deferred_featured_image_html'] = $deferredHtml;

        return $result;
    }
}
