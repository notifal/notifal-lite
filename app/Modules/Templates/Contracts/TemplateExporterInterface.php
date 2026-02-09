<?php

namespace Notifal\Modules\Templates\Contracts;

use WP_Post;

defined('ABSPATH') || exit;

/**
 * Interface TemplateExporterInterface
 *
 * Defines the contract for exporting a notification template from any builder (Elementor, Block Editor, etc.)
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Contracts
 * @author Hossein <hossein@notifal.com>
 */
interface TemplateExporterInterface
{
    /**
     * Determine if this exporter supports the given post.
     *
     * @param WP_Post $post
     * @return bool
     *
     * @since 2.0.0
     */
    public function supports(WP_Post $post): bool;

    /**
     * Export the template's structure/content.
     *
     * @param WP_Post $post
     * @return array
     *
     * @since 2.0.0
     */
    public function export(WP_Post $post): array;
}
