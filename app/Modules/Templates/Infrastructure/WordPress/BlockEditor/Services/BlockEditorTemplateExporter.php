<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services;

use Notifal\Modules\Templates\Contracts\TemplateExporterInterface;
use Notifal\Infrastructure\WordPress\Elementor\Helpers\ElementorHelper;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Class BlockEditorTemplateExporter
 *
 * Exports templates created using the WordPress Block Editor (Gutenberg).
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services
 * @author Hossein <hossein@notifal.com>
 */
class BlockEditorTemplateExporter implements TemplateExporterInterface
{
    /**
     * Checks if the template is not an Elementor template.
     *
     * @param WP_Post $post
     * @return bool
     * @since 2.0.0
     */
    public function supports(WP_Post $post): bool
    {
        return ! ElementorHelper::hasBuilder($post);
    }

    /**
     * Export the block editor (Gutenberg) template data.
     *
     * @param WP_Post $post
     * @return array
     * @since 2.0.0
     */
    public function export(WP_Post $post): array
    {
        return [
            'id'      => $post->ID,
            'title'   => $post->post_title,
            'builder' => 'block-editor',
            'content' => $post->post_content,
        ];
    }
}
