<?php

namespace Notifal\Modules\Templates\Infrastructure\Shared\Traits;

use Notifal\Domain\Tags\TagManager;
use Notifal\Shared\AdminUI\Fields\TagsRenderer;

defined('ABSPATH') || exit;

/**
 * Trait TagsPanelTrait
 *
 * Provides common functionality for rendering tags panels across different editors.
 * Eliminates code duplication between BlockEditor and Elementor TagsPanel implementations.
 *
 * @package Notifal\Modules\Templates\Infrastructure\Shared\Traits
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
trait TagsPanelTrait
{
    /**
     * Get filtered tags for rendering in panels.
     *
     * @return array Array of Tag objects or empty array if no tags available.
     * @since 2.0.0
     */
    protected static function getFilteredTags(): array
    {
        /** @var TagManager $tagManager */
        $tagManager = notifal_app(TagManager::class);

        $tags = $tagManager->allFiltered();
        if (empty($tags)) {
            return [];
        }

        return $tags;
    }

    /**
     * Render tags using TagsRenderer with specified options.
     *
     * @param array $tags Array of Tag objects to render.
     * @param array $renderOptions Options to pass to TagsRenderer::render().
     * @return string Rendered HTML content.
     * @since 2.0.0
     */
    protected static function renderTags(array $tags, array $renderOptions = []): string
    {
        return TagsRenderer::render($tags, $renderOptions);
    }
}