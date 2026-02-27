<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Template;

use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Handles Block Editor-specific template rendering for frontend notifications.
 *
 * Extends AbstractTemplateRenderer to render Gutenberg block content via
 * the standard 'the_content' filter pipeline.  Asset capture is handled
 * entirely by the base class — any CSS/JS that blocks or third-party
 * plugins enqueue during content rendering is captured automatically
 * via queue snapshotting.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class BlockEditorTemplateRenderer extends AbstractTemplateRenderer
{
    /**
     * Render the actual Block Editor template content.
     *
     * Sets up the proper WordPress post context for block rendering to ensure
     * blocks have access to the correct post data and can render properly.
     * Uses WordPress core 'the_content' filter which processes all registered blocks.
     *
     * Asset-queue snapshotting is handled by the base class around this call,
     * so any CSS/JS enqueued by third-party blocks (e.g. theme countdown
     * blocks) is captured automatically.
     *
     * @param \WP_Post $template Template post.
     * @param array    $frontendContext Frontend context.
     * @return string Rendered HTML content.
     * @since 2.0.0
     */
    protected function renderContent(\WP_Post $template, array $frontendContext): string
    {
        return Helper::withPostContext($template, function () use ($template) {
            return apply_filters('the_content', $template->post_content);
        });
    }

    /**
     * Get Block Editor-specific assets for the template.
     *
     * Returns an empty array because Block Editor templates do not have
     * builder-specific asset files (unlike Elementor's Post-CSS).
     * All required CSS/JS is captured generically by the base class's
     * asset-queue snapshotting mechanism.
     *
     * @param \WP_Post $template Template post.
     * @return array Empty array — captured assets are merged by base class.
     * @since 2.0.0
     */
    protected function getAssets(\WP_Post $template): array
    {
        return [];
    }

    /**
     * Get the builder type identifier.
     *
     * @return string Builder type string.
     * @since 2.0.0
     */
    protected function getBuilderType(): string
    {
        return 'block_editor';
    }
}
