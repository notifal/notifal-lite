<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Template;

use Notifal\Modules\Templates\Infrastructure\WordPress\Repositories\TemplateQuery;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Service to filter templates based on content source type.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class TemplateFilterService
{
    /**
     * Validate content source type and builder parameters.
     *
     * @param string $contentSourceType The content source type to validate.
     * @param string $builder The builder type to validate.
     * @throws \InvalidArgumentException If parameters are invalid.
     * @since 2.0.0
     */
    private static function validateParameters(string $contentSourceType, string $builder): void
    {
        if (!in_array($contentSourceType, ['static', 'dynamic'], true)) {
            throw new \InvalidArgumentException('Invalid content source type. Must be "static" or "dynamic".');
        }

        if (!in_array($builder, ['elementor', 'block-editor'], true)) {
            throw new \InvalidArgumentException('Invalid builder type. Must be "elementor" or "block-editor".');
        }
    }

    /**
     * Filter templates based on content source type.
     *
     * @param string $contentSourceType The content source type ('static' or 'dynamic').
     * @param string $builder The builder type ('elementor' or 'block-editor').
     * @param int $limit Number of templates to retrieve. Use -1 for all templates.
     * @param int $offset Number of templates to skip for pagination.
     * @param int|null $selectedTemplateId Optional selected template ID to prioritize (null normalized to 0).
     * @return WP_Post[] Filtered templates.
     * @since 2.0.0
     */
    public static function filterTemplatesByContentType(string $contentSourceType, string $builder, int $limit = 6, int $offset = 0, ?int $selectedTemplateId = null): array
    {
        $selectedTemplateId = $selectedTemplateId ?? 0;

        try {
            self::validateParameters($contentSourceType, $builder);
        } catch (\InvalidArgumentException $e) {
            return [];
        }

        // Retrieve all templates for the specified builder
        $allTemplates = TemplateQuery::getAllByBuilder($builder);

        $filteredTemplates = [];
        $selectedTemplate = null;

        // First, check if selected template exists and belongs to this builder
        if ($selectedTemplateId > 0) {
            $selectedPost = TemplateQuery::get($selectedTemplateId);
            if ($selectedPost && TemplateQuery::hasTemplateContent($selectedPost, $builder)) {
                $hasNotifalTags = TemplateQuery::hasNotifalTags($selectedPost, $builder);

                // Check if selected template matches the content source type
                $matchesType = ($contentSourceType === 'static' && !$hasNotifalTags) ||
                              ($contentSourceType === 'dynamic' && $hasNotifalTags);

                if ($matchesType) {
                    $selectedTemplate = $selectedPost;
                    $filteredTemplates[] = $selectedPost;
                }
            }
        }

        $skippedCount = 0;

        foreach ($allTemplates as $template) {
            // Skip if this is the selected template (already added)
            if ($selectedTemplate && $template->ID === $selectedTemplate->ID) {
                continue;
            }

            $hasNotifalTags = TemplateQuery::hasNotifalTags($template, $builder);

            // Filter based on content source type
            $matchesType = ($contentSourceType === 'static' && !$hasNotifalTags) ||
                          ($contentSourceType === 'dynamic' && $hasNotifalTags);

            if ($matchesType) {
                // Skip templates based on offset
                if ($skippedCount < $offset) {
                    $skippedCount++;
                    continue;
                }

                $filteredTemplates[] = $template;

                // Stop when we have enough templates (unless limit is -1 for getting all)
                if ($limit !== -1 && count($filteredTemplates) >= $limit) {
                    break;
                }
            }
        }

        return $filteredTemplates;
    }
    
    /**
     * Get count of templates filtered by content source type.
     *
     * @param string $contentSourceType The content source type ('static' or 'dynamic').
     * @param string $builder The builder type ('elementor' or 'block-editor').
     * @return int Count of filtered templates.
     * @since 2.0.0
     */
    public static function getFilteredTemplateCount(string $contentSourceType, string $builder): int
    {
        try {
            self::validateParameters($contentSourceType, $builder);
        } catch (\InvalidArgumentException $e) {
            return 0;
        }

        // Retrieve all templates for the specified builder
        $allTemplates = TemplateQuery::getAllByBuilder($builder);
        
        $count = 0;
        foreach ($allTemplates as $template) {
            $hasNotifalTags = TemplateQuery::hasNotifalTags($template, $builder);
            
            // Count based on content source type
            if ($contentSourceType === 'static' && !$hasNotifalTags) {
                $count++;
            } elseif ($contentSourceType === 'dynamic' && $hasNotifalTags) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Check if a template matches the content source type.
     *
     * @param WP_Post $template The template post object.
     * @param string $contentSourceType The content source type ('static' or 'dynamic').
     * @param string $builder The builder type ('elementor' or 'block-editor').
     * @return bool True if template matches the content source type.
     * @since 2.0.0
     */
    public static function templateMatchesContentType(WP_Post $template, string $contentSourceType, string $builder): bool
    {
        try {
            self::validateParameters($contentSourceType, $builder);
        } catch (\InvalidArgumentException $e) {
            return false;
        }

        $hasNotifalTags = TemplateQuery::hasNotifalTags($template, $builder);
        
        if ($contentSourceType === 'static') {
            return !$hasNotifalTags;
        } elseif ($contentSourceType === 'dynamic') {
            return $hasNotifalTags;
        }
        
        return false;
    }
} 
