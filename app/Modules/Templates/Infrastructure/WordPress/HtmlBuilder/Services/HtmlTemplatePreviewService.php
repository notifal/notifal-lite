<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\HtmlBuilder\Services;

use Notifal\Modules\OnPageNotification\Application\Services\Template\HtmlTemplateRenderer;
use Notifal\Modules\OnPageNotification\Application\Services\Template\TemplateContextBuilder;
use Notifal\Modules\Templates\Application\Services\TemplateBuilderDetector;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Server-side preview service for the HTML Builder admin screen.
 *
 * @since 2.4.0
 * @author Hossein <hossein@notifal.com>
 */
class HtmlTemplatePreviewService
{
    /**
     * Render preview HTML for saved or unsaved builder content.
     *
     * @param int         $templateId Template post ID.
     * @param string|null $html       Optional unsaved HTML override.
     * @return array{html: string, warnings: string[]} Preview payload.
     * @since 2.4.0
     */
    public function renderPreview(int $templateId, ?string $html = null): array
    {
        $template = get_post($templateId);
        if (!$template instanceof WP_Post || $template->post_type !== 'notifal_template') {
            return [
                'html'     => '',
                'warnings' => [__('Template not found.', 'notifal')],
            ];
        }

        if (get_post_meta($templateId, '_notifal_builder', true) !== TemplateBuilderDetector::BUILDER_HTML) {
            return [
                'html'     => '',
                'warnings' => [__('This template is not an HTML Builder template.', 'notifal')],
            ];
        }

        $warnings = [];
        $content = $html;

        // Sanitize unsaved HTML before running the preview pipeline.
        if ($content !== null) {
            $sanitized = HtmlTemplateSanitizer::sanitize($content);
            $content = $sanitized['content'];
            $warnings = $sanitized['warnings'];
        }

        $contentForPreview = $content ?? (string) $template->post_content;

        /** @var TemplateContextBuilder $contextBuilder */
        $contextBuilder = notifal_app(TemplateContextBuilder::class);
        $rawContent = $contextBuilder->extractRawContentFromHtml($contentForPreview);
        $frontendContext = $contextBuilder->buildContext($rawContent, ['is_preview' => true], []);

        $previewPost = clone $template;
        $previewPost->post_content = $contentForPreview;

        /** @var HtmlTemplateRenderer $renderer */
        $renderer = notifal_app(HtmlTemplateRenderer::class);
        $result = $renderer->render($previewPost, $frontendContext);

        return [
            'html'     => (string) ($result['html'] ?? ''),
            'warnings' => $warnings,
        ];
    }
}
