<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Template;

use Notifal\Modules\Templates\Application\Services\TemplateBuilderDetector;
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
     * Builder slugs accepted by the OnPage notification template picker.
     *
     * @since 2.4.0
     */
    private const ACCEPTED_BUILDERS = [
        'elementor',
        'block-editor',
        'html-builder',
        'notifal_html_builder',
    ];

    /**
     * Check whether a template was created with the given builder.
     *
     * @param WP_Post|null $post    The template post object.
     * @param string       $builder The builder type (elementor, block-editor, or html-builder).
     * @return bool True if the template belongs to the builder, false otherwise.
     * @since 2.0.3
     */
    public static function templateBelongsToBuilder(?WP_Post $post, string $builder): bool
    {
        if (!$post) {
            return false;
        }

        $normalizedRequested = TemplateBuilderDetector::normalizeBuilderSlug($builder);
        $templateBuilder = TemplateBuilderDetector::getBuilder($post);

        return $normalizedRequested === $templateBuilder;
    }

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

        if (!in_array($builder, self::ACCEPTED_BUILDERS, true)) {
            throw new \InvalidArgumentException('Invalid builder type. Must be "elementor", "block-editor", or "html-builder".');
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

        // When offset > 0 (load more), do not prepend selected template — it was already sent on the first page.
        $prependSelected = ($offset === 0);

        // First, check if selected template exists and belongs to this builder (only prepend on first page)
        if ($prependSelected && $selectedTemplateId > 0) {
            $selectedPost = TemplateQuery::get($selectedTemplateId);
            if ($selectedPost && self::templateBelongsToBuilder($selectedPost, $builder) && TemplateQuery::hasTemplateContent($selectedPost, $builder)) {
                $hasNotifalTags = TemplateQuery::hasNotifalTags($selectedPost, $builder);

                // Check if selected template matches the content source type
                $matchesType = ($contentSourceType === 'static' && !$hasNotifalTags) ||
                              ($contentSourceType === 'dynamic' && $hasNotifalTags);

                if ($matchesType) {
                    $selectedTemplate = $selectedPost;
                    $filteredTemplates[] = $selectedPost;
                }
            }
        } elseif ($selectedTemplateId > 0) {
            $selectedPost = TemplateQuery::get($selectedTemplateId);
            if ($selectedPost && self::templateBelongsToBuilder($selectedPost, $builder) && TemplateQuery::hasTemplateContent($selectedPost, $builder)) {
                $selectedTemplate = $selectedPost;
            }
        }

        // First page shows 1 selected + (limit-1) from list; load more sends total count as offset. So skip (offset - 1) when selected was prepended.
        $effectiveOffset = ($offset > 0 && $selectedTemplate !== null) ? $offset - 1 : $offset;
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
                // Skip templates based on offset (effectiveOffset accounts for selected already shown on first page)
                if ($skippedCount < $effectiveOffset) {
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
