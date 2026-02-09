<?php

namespace Notifal\Modules\Templates\Infrastructure\Shared\Traits;

defined('ABSPATH') || exit;

/**
 * Trait TagsExtractorTrait
 *
 * Provides functionality to extract Notifal tags from text content.
 * Used by export processors to identify which tags are used in templates.
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Infrastructure\Shared\Traits
 * @author Hossein <hossein@notifal.com>
 */
trait TagsExtractorTrait
{
    /**
     * Extract Notifal tag keys from text content.
     *
     * Finds all tag placeholders in the format {tag_key} and returns an array of unique tag keys.
     *
     * @param string $textContent The text content to search for tags.
     * @return array Array of unique tag keys found in the content.
     * @since 2.0.0
     */
    protected function extractTagsFromTextContent(string $textContent): array
    {
        $tags = [];

        // Pattern to match Notifal tags: {tag_key}
        // Supports alphanumeric characters, underscores, and dots
        $pattern = '/\{([a-zA-Z0-9_.]+)\}/';

        if (preg_match_all($pattern, $textContent, $matches)) {
            // $matches[1] contains the captured tag keys (without braces)
            $tags = array_unique($matches[1]);
        }

        return array_values($tags);
    }
}