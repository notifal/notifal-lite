<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Template;

use Notifal\Modules\Templates\Application\Services\TemplateBuilderDetector;
use Notifal\Modules\Templates\Infrastructure\WordPress\HtmlBuilder\Services\HtmlTemplateSanitizer;

defined('ABSPATH') || exit;

/**
 * Renders HTML Builder templates on the frontend.
 *
 * Self-contained HTML templates store markup in post_content. Processing
 * runs through the shared AbstractTemplateRenderer pipeline for tags,
 * class placeholders, and shortcodes.
 *
 * @since 2.4.0
 * @updated 3.0.0 Normalize @import urls so pretty-print damage cannot hide CSS.
 * @author Hossein <hossein@notifal.com>
 */
class HtmlTemplateRenderer extends AbstractTemplateRenderer
{
    /**
     * Render raw HTML template content without Gutenberg or Elementor filters.
     *
     * @param \WP_Post $template        Template post.
     * @param array    $frontendContext Frontend context (unused for raw HTML load).
     * @return string Raw HTML from post_content.
     * @since 2.4.0
     * @updated 3.0.0 Repair multiline Google Fonts @import urls before output.
     */
    protected function renderContent(\WP_Post $template, array $frontendContext): string
    {
        // HTML Builder stores the canonical markup in post_content.
        $content = (string) $template->post_content;

        // Restore single-line @import urls so browsers apply the rest of the CSS.
        return HtmlTemplateSanitizer::normalizeCssImportUrlsInHtml($content);
    }

    /**
     * HTML Builder templates embed CSS/JS inside post_content.
     *
     * @param \WP_Post $template Template post.
     * @return array Empty assets array.
     * @since 2.4.0
     */
    protected function getAssets(\WP_Post $template): array
    {
        return [];
    }

    /**
     * Builder type identifier sent to the frontend notification runtime.
     *
     * @return string Builder slug.
     * @since 2.4.0
     */
    protected function getBuilderType(): string
    {
        return TemplateBuilderDetector::BUILDER_HTML;
    }
}
