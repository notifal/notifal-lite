<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Services;

use Notifal\Modules\Templates\Contracts\TemplateExporterInterface;
use Notifal\Infrastructure\WordPress\Elementor\Helpers\ElementorHelper;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Class ElementorTemplateExporter
 *
 * Exports templates built using Elementor.
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Services
 * @author Hossein <hossein@notifal.com>
 */
class ElementorTemplateExporter implements TemplateExporterInterface
{
    /**
     * Determine if this post is built with Elementor.
     *
     * @param WP_Post $post
     * @return bool
     * @since 2.0.0
     */
    public function supports(WP_Post $post): bool
    {
        return ElementorHelper::hasBuilder($post);
    }

    /**
     * Export the Elementor template data.
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
            'builder' => 'elementor',
            'content' => maybe_unserialize(get_post_meta($post->ID, '_elementor_data', true)),
        ];
    }
}
