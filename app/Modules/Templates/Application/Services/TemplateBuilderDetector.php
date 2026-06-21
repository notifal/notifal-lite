<?php

namespace Notifal\Modules\Templates\Application\Services;

use Notifal\Infrastructure\WordPress\Elementor\Helpers\ElementorHelper;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Detects which page builder was used to create a Notifal template.
 *
 * Centralizes builder resolution so list tables, export, import,
 * and frontend rendering share the same logic.
 *
 * @since 2.4.0
 * @author Hossein <hossein@notifal.com>
 */
class TemplateBuilderDetector
{
    /**
     * HTML Builder slug stored in _notifal_builder post meta.
     *
     * @since 2.4.0
     */
    public const BUILDER_HTML = 'notifal_html_builder';

    /**
     * Elementor builder slug.
     *
     * @since 2.4.0
     */
    public const BUILDER_ELEMENTOR = 'elementor';

    /**
     * Block Editor builder slug.
     *
     * @since 2.4.0
     */
    public const BUILDER_BLOCK_EDITOR = 'block_editor';

    /**
     * Resolve the builder type for a template post.
     *
     * @param WP_Post $post Template post object.
     * @return string Builder slug (notifal_html_builder, elementor, or block_editor).
     * @since 2.4.0
     */
    public static function getBuilder(WP_Post $post): string
    {
        // Read persisted builder meta when available.
        $metaBuilder = get_post_meta($post->ID, '_notifal_builder', true);

        // HTML Builder templates are identified explicitly via meta.
        if ($metaBuilder === self::BUILDER_HTML) {
            return self::BUILDER_HTML;
        }

        // Elementor templates use Elementor's edit-mode meta.
        if (ElementorHelper::hasBuilder($post)) {
            return self::BUILDER_ELEMENTOR;
        }

        // Remaining templates are treated as Block Editor templates.
        return self::BUILDER_BLOCK_EDITOR;
    }

    /**
     * Check whether the given post is an HTML Builder template.
     *
     * @param WP_Post $post Template post object.
     * @return bool True when builder is notifal_html_builder.
     * @since 2.4.0
     */
    public static function isHtmlBuilder(WP_Post $post): bool
    {
        // Delegate to getBuilder() for a single source of truth.
        return self::getBuilder($post) === self::BUILDER_HTML;
    }

    /**
     * Normalize external builder slugs to internal constants.
     *
     * @param string $builder Builder slug from import or API payloads.
     * @return string Normalized builder slug.
     * @since 2.4.0
     */
    public static function normalizeBuilderSlug(string $builder): string
    {
        // Map legacy/import aliases to the canonical HTML Builder slug.
        $aliases = [
            'html_builder',
            'html-builder',
            'notifal_html_builder',
        ];

        if (in_array($builder, $aliases, true)) {
            return self::BUILDER_HTML;
        }

        // Map block editor aliases.
        if (in_array($builder, ['block-editor', 'gutenberg'], true)) {
            return self::BUILDER_BLOCK_EDITOR;
        }

        // Return the slug unchanged when no alias matches.
        return $builder;
    }
}
