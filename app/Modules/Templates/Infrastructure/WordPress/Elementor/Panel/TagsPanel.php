<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Panel;

use Notifal\Modules\Templates\Infrastructure\Shared\Traits\TagsPanelTrait;

defined('ABSPATH') || exit;

/**
 * Class TagsPanel
 *
 * Displays a list of dynamic tags above Elementor widgets like Text Editor and Heading.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Panel
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class TagsPanel
{
    use TagsPanelTrait;

    /**
     * Boot Elementor hooks for displaying tag info box.
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        add_action('elementor/element/text-editor/section_editor/after_section_start', [self::class, 'render'], 10, 2);
        add_action('elementor/element/heading/section_title/after_section_start', [self::class, 'render'], 10, 2);
    }

    /**
     * Render the tags info box inside Elementor widget panels.
     *
     * @param \Elementor\Widget_Base $element The Elementor element instance.
     * @param array $args Additional arguments.
     * @return void
     * @since 2.0.0
     */
    public static function render($element, array $args): void
    {
        $post_id = get_the_ID();
        if (!$post_id || get_post_type($post_id) !== 'notifal_template') {
            return;
        }

        $tags = self::getFilteredTags();
        if (empty($tags)) {
            return;
        }

        $html = self::renderTags($tags, [
            'show_info' => true,
            'show_warning' => true,
            'show_search' => true,
            'show_categories' => true,
            'container_class' => 'elementor-tags-container'
        ]);

        $element->add_control(
            'notifal_tags',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- TagsRenderer already handles escaping
                'raw' => $html,
                'content_classes' => 'notifal-tags-container',
            ]
        );
    }
}
