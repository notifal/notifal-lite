<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services;

use Notifal\Domain\Tags\TagManager;
use Notifal\Modules\Templates\Application\Services\PreviewDataResolver;
use Notifal\Modules\Templates\Infrastructure\Shared\Traits\TagsExtractorTrait;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Processes Notifal tags in block editor content during template export.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class BlockEditorTagsExportProcessor
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
     * Preview data resolver service
     *
     * @var PreviewDataResolver
     * @since 2.0.0
     */
    private $previewDataResolver;

    /**
     * Constructor
     *
     * @param TagManager $tagManager Tag manager service
     * @param PreviewDataResolver $previewDataResolver Preview data resolver service
     * @since 2.0.0
     */
    public function __construct(TagManager $tagManager, PreviewDataResolver $previewDataResolver)
    {
        $this->tagManager = $tagManager;
        $this->previewDataResolver = $previewDataResolver;
    }

    /**
     * Process export data to extract tags from block editor content
     *
     * @param array $data Export data array
     * @param WP_Post $post Template post
     * @return array Modified export data with extracted tags information
     * @since 2.0.0
     */
    public function processExportData(array $data, WP_Post $post): array
    {
        // Skip during frontend notification rendering to avoid context conflicts
        if (class_exists('\Notifal\Modules\OnPageNotification\Application\Services\Utility\WidgetContextProvider')) {
            if (\Notifal\Modules\OnPageNotification\Application\Services\Utility\WidgetContextProvider::isActive()) {
                return $data;
            }
        }

        // Only process block editor templates
        if (!isset($data['builder']) || $data['builder'] !== 'block-editor') {
            return $data;
        }

        // Extract tags from the content (keep content unchanged)
        if (isset($data['content']) && is_string($data['content'])) {
            $usedTags = $this->extractTagsFromContent($data['content']);
            if (!empty($usedTags)) {
                $data['used_tags'] = $usedTags;
            }
        }

        return $data;
    }

    /**
     * Extract Notifal tags from block editor content
     *
     * @param string $content Block editor content (HTML blocks)
     * @return array Array of tag keys found in the content
     * @since 2.0.0
     */
    private function extractTagsFromContent(string $content): array
    {
        // Use shared trait method for tag extraction
        return $this->extractTagsFromTextContent($content);
    }
} 
