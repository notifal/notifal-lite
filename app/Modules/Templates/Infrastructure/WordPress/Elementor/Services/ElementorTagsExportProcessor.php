<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Services;

use Notifal\Domain\Tags\TagManager;
use Notifal\Infrastructure\WordPress\Support\ContentExtractor;
use Notifal\Modules\Templates\Infrastructure\Shared\Traits\TagsExtractorTrait;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Class ElementorTagsExportProcessor
 *
 * Processes Notifal tags in Elementor content during template export.
 * Hooks into the export filter to extract tag information without modifying content.
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Services
 * @author Hossein <hossein@notifal.com>
 */
class ElementorTagsExportProcessor
{
    use TagsExtractorTrait;

    /**
     * Tag manager service
     *
     * @var TagManager
     * @since 2.0.0
     */
    private $tagManager;

    /**
     * Constructor
     *
     * @param TagManager $tagManager Tag manager service
     * @since 2.0.0
     */
    public function __construct(TagManager $tagManager)
    {
        $this->tagManager = $tagManager;
    }

    /**
     * Process export data to extract tags from Elementor content
     *
     * @param array $data Export data array
     * @param WP_Post $post Template post
     * @return array Modified export data with extracted tags information
     * @since 2.0.0
     */
    public function processExportData(array $data, WP_Post $post): array
    {
        // Only process Elementor templates
        if (!isset($data['builder']) || $data['builder'] !== 'elementor') {
            return $data;
        }

        // Extract tags from the content (keep content unchanged)
        if (isset($data['content']) && is_array($data['content'])) {
            $usedTags = $this->extractTagsFromElementorData($data['content']);
            if (!empty($usedTags)) {
                $data['used_tags'] = $usedTags;
            }
        }

        return $data;
    }

    /**
     * Extract Notifal tags from Elementor data structure
     *
     * @param array $elementorData Elementor data array
     * @return array Array of tag keys found in the Elementor data
     * @since 2.0.0
     */
    private function extractTagsFromElementorData(array $elementorData): array
    {
        // Recursively extract text content from Elementor data
        $textContent = ContentExtractor::extractTextFromElementorData($elementorData);

        // Use shared trait method for tag extraction
        return $this->extractTagsFromTextContent($textContent);
    }

}
