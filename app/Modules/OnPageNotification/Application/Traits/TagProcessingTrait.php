<?php

namespace Notifal\Modules\OnPageNotification\Application\Traits;

defined('ABSPATH') || exit;

/**
 * Trait TagProcessingTrait
 *
 * Provides common tag processing functionality for template renderers.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
trait TagProcessingTrait
{
    /**
     * Process tags using an already-built frontend context.
     *
     * @param string $content Template content
     * @param array $frontendContext Already-built frontend context
     * @return string Processed content
     * @since 2.0.0
     */
    private function processTagsWithContext(string $content, array $frontendContext): string
    {
        $tagManager = notifal_app(\Notifal\Domain\Tags\TagManager::class);

        if (!$tagManager) {
            return $content;
        }

        try {
            return $tagManager->render($content, $frontendContext);
        } catch (\Exception $e) {
            return $content;
        }
    }
}
