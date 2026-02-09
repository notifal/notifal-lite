<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Panel;

use Notifal\Modules\Templates\Infrastructure\Shared\Traits\TagsPanelTrait;

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Class TagsPanel
 *
 * Injects the Notifal Tags panel into the Gutenberg (Block Editor) sidebar
 * for notifal_template post type, only for text-based blocks.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Panel
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class TagsPanel
{
    use TagsPanelTrait;

    /**
     * Flag to prevent duplicate rendering.
     *
     * @var bool
     */
    private static bool $panelRendered = false;

    /**
     * Register hooks to inject the tags panel in the block editor.
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        add_action('admin_head', [self::class, 'renderPanel']);
        add_action('admin_footer', [self::class, 'renderPanel']);
    }

    /**
     * Render the tags panel markup for the block editor sidebar.
     * Only outputs for notifal_template post type.
     *
     * @return void
     * @since 2.0.0
     */
    public static function renderPanel(): void
    {
        // Prevent duplicate rendering
        if (self::$panelRendered) {
            return;
        }

        global $post;
        if (!isset($post) || get_post_type($post) !== 'notifal_template') {
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
            'container_class' => 'blockeditor-tags-container'
        ]);

        // Output hidden container for React panel to access
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- TagsRenderer already handles escaping
        echo '<div id="notifal-tags-panel-hidden" style="display:none"><div class="notifal-sidebar-content">' . $html . '</div></div>';

        // Mark as rendered
        self::$panelRendered = true;
    }
}
