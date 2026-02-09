<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Template;

use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Handles Block Editor-specific template rendering for frontend notifications.
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
     * @param \WP_Post $template Template post
     * @param array $frontendContext Frontend context
     * @return string Rendered content
     * @since 2.0.0
     */
    protected function renderContent(\WP_Post $template, array $frontendContext): string
    {
        // Use helper method for safe post context management during block rendering
        // This ensures blocks have proper post data context for dynamic content
        return Helper::withPostContext($template, function() use ($template) {
            // Apply WordPress content filters to render blocks with proper context
            // the_content filter processes all registered blocks and their dynamic content
            return apply_filters('the_content', $template->post_content);
        });
    }

    /**
     * Get Block Editor-specific assets for the template.
     *
     * Block Editor templates embed their styles directly within the content
     * using WordPress block editor's inline styling approach. Unlike Elementor,
     * Block Editor does not require separate CSS files as styles are embedded
     * within the block HTML markup.
     *
     * @param \WP_Post $template Template post
     * @return array Asset URLs or inline content (empty for block editor)
     * @since 2.0.0
     */
    protected function getAssets(\WP_Post $template): array
    {
        // Block editor templates embed styles inline in content, no external assets needed
        // This differs from Elementor which requires separate CSS files
        return [];
    }

    /**
     * Get the builder type identifier.
     *
     * @return string Builder type string
     * @since 2.0.0
     */
    protected function getBuilderType(): string
    {
        return 'block_editor';
    }

}
