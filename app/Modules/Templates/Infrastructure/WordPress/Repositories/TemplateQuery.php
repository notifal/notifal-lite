<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\Repositories;

defined('ABSPATH') || exit;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Domain\Settings\Services\SettingsService;
use Notifal\Modules\Templates\Infrastructure\Shared\Traits\TemplateContentTrait;
use WP_Post;
use WP_Query;

/**
 * Class TemplateQuery
 *
 * Provides data access methods for notifal_template post type.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\Repositories
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class TemplateQuery
{
    use TemplateContentTrait;

    /**
     * Get a single template post by ID.
     *
     * @since 2.0.0
     * @param int $id
     * @return WP_Post|null
     */
    public static function get(int $id): ?WP_Post
    {
        $post = get_post($id);
        return ($post && $post->post_type === 'notifal_template') ? $post : null;
    }

    /**
     * Get template builder data from post meta.
     *
     * @since 2.0.0
     * @param int $id
     * @return array
     */
    public static function getBuilderData(int $id): array
    {
        $raw = get_post_meta($id, '_notifal_template_data', true);
        return is_string($raw) ? json_decode($raw, true) ?: [] : [];
    }

    /**
     * Retrieve templates created with a specific builder.
     *
     * @since 2.0.0
     * @param string $builder Accepted values: 'elementor' or 'block-editor'
     * @param int    $limit   Number of templates to retrieve
     * @param int    $selectedTemplateId Optional selected template ID to prioritize
     * @return WP_Post[]
     */
    public static function getByBuilder(string $builder, int $limit = 6, int $selectedTemplateId = 0): array
    {
        $metaQuery = [];

        if ($builder === 'elementor') {
            $metaQuery[] = [
                'key'     => '_elementor_edit_mode',
                'value'   => 'builder',
                'compare' => '='
            ];
        } else {
            $metaQuery[] = [
                'key'     => '_elementor_edit_mode',
                'compare' => 'NOT EXISTS'
            ];
        }

        $metaQuery = apply_filters(FilterHooks::TEMPLATES_BUILDER_META_QUERY, $metaQuery, $builder);

        $queryArgs = [
            'post_type'      => 'notifal_template',
            'post_status'    => 'publish',
            'posts_per_page' => $limit === -1 ? -1 : ($limit * 2), // Get all if limit is -1, otherwise get more to filter out empty ones
            'meta_query'     => $metaQuery,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ];

        $queryArgs = apply_filters(FilterHooks::TEMPLATES_BUILDER_QUERY_ARGS, $queryArgs, $builder, $limit);

        $query = new WP_Query($queryArgs);

        $templates = [];
        $selectedTemplate = null;

        // First, check if selected template exists and belongs to this builder
        if ($selectedTemplateId > 0) {
            $selectedPost = self::get($selectedTemplateId);
            if ($selectedPost && self::hasTemplateContent($selectedPost, $builder)) {
                $selectedTemplate = $selectedPost;
                $templates[] = $selectedPost;
            }
        }

        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                // Skip if this is the selected template (already added)
                if ($selectedTemplate && $post->ID === $selectedTemplate->ID) {
                    continue;
                }

                // Filter out truly empty templates
                if (self::hasTemplateContent($post, $builder)) {
                    $templates[] = $post;

                    // Stop when we have enough templates (unless limit is -1 for getting all)
                    if ($limit !== -1 && count($templates) >= $limit) {
                        break;
                    }
                }
            }
        }

        do_action(ActionHooks::TEMPLATES_QUERIED_BY_BUILDER, $builder, $templates);

        return $templates;
    }


    /**
     * Get total count of templates created with a specific builder.
     *
     * @since 2.0.0
     * @param string $builder Accepted values: 'elementor' or 'block-editor'
     * @return int
     */
    public static function getByBuilderCount(string $builder): int
    {
        $templates = self::getAllByBuilder($builder);
        return count($templates);
    }

    /**
     * Check if a template contains Notifal tags.
     *
     * @since 2.0.0
     * @param WP_Post $post The template post
     * @param string $builder The builder type
     * @return bool True if template has tags, false otherwise
     */
    public static function hasNotifalTags(WP_Post $post, string $builder): bool
    {
        $isElementor = \Notifal\Infrastructure\WordPress\Elementor\Helpers\ElementorHelper::hasBuilder($post);

        if ($isElementor) {
            // For Elementor templates, check Elementor data for tags
            $elementor_data = get_post_meta($post->ID, '_elementor_data', true);

            if (!empty($elementor_data)) {
                $data = json_decode($elementor_data, true);
                if (is_array($data)) {
                    if (self::elementorDataHasTags($data)) {
                        return true;
                    }
                }
            }

            // Also check post content as fallback (some Elementor templates might store content there too)
            $content = $post->post_content ?? '';
            return self::contentHasNotifalTags($content);
        }

        // For block editor templates, check post content for tags
        $content = $post->post_content ?? '';
        return self::contentHasNotifalTags($content);
    }

    /**
     * Check if Elementor data contains Notifal tags.
     *
     * @since 2.0.0
     * @param array $data Elementor data array
     * @return bool True if data has tags, false otherwise
     */
    private static function elementorDataHasTags(array $data): bool
    {
        foreach ($data as $element) {
            // Check for Notifal tags widget
            if (isset($element['widgetType']) && $element['widgetType'] === 'notifal-tags') {
                return true;
            }
            
            // Check for tag patterns in element content
            if (isset($element['settings'])) {
                $settings = $element['settings'];
                
                // Check all string fields in settings for tag patterns
                foreach ($settings as $field => $value) {
                    if (is_string($value) && !empty($value)) {
                        if (self::contentHasNotifalTags($value)) {
                            return true;
                        }
                    }
                }
            }
            
            // Recursively check nested elements
            if (isset($element['elements']) && is_array($element['elements'])) {
                if (self::elementorDataHasTags($element['elements'])) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Get all templates for a specific builder without any filtering or limits.
     * Used for filtering operations where we need all templates.
     *
     * @since 2.0.0
     * @param string $builder The builder type ('elementor' or 'block-editor')
     * @return WP_Post[] All templates for the builder
     */
    public static function getAllByBuilder(string $builder): array
    {
        $metaQuery = [];

        if ($builder === 'elementor') {
            $metaQuery[] = [
                'key'     => '_elementor_edit_mode',
                'value'   => 'builder',
                'compare' => '='
            ];
        } else {
            $metaQuery[] = [
                'key'     => '_elementor_edit_mode',
                'compare' => 'NOT EXISTS'
            ];
        }

        $metaQuery = apply_filters(FilterHooks::TEMPLATES_BUILDER_META_QUERY, $metaQuery, $builder);

        $queryArgs = [
            'post_type'      => 'notifal_template',
            'post_status'    => 'publish',
            'posts_per_page' => -1, // Get all posts
            'meta_query'     => $metaQuery,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ];

        $queryArgs = apply_filters(FilterHooks::TEMPLATES_BUILDER_QUERY_ARGS, $queryArgs, $builder, -1);

        $query = new WP_Query($queryArgs);

        $templates = [];
        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                // Only filter out truly empty templates, don't limit count
                if (self::hasTemplateContent($post, $builder)) {
                    $templates[] = $post;
                }
            }
        }

        return $templates;
    }

    /**
     * Check if content contains Notifal tags.
     *
     * @since 2.0.0
     * @param string $content The content to check
     * @return bool True if content has tags, false otherwise
     */
    private static function contentHasNotifalTags(string $content): bool
    {
        $matches = [];
        $tagPattern = '/(?<![\$\{])\{([a-zA-Z_][a-zA-Z0-9_\/\.\-]*)\}/';
        preg_match_all($tagPattern, $content, $matches);

        if (empty($matches[1]) || !is_array($matches[1])) {
            return false;
        }

        $knownPrefixes = self::getKnownTagPrefixes();

        foreach ($matches[1] as $candidateTag) {
            if (!is_string($candidateTag) || $candidateTag === '') {
                continue;
            }

            foreach ($knownPrefixes as $prefix) {
                if (strpos($candidateTag, $prefix) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get known Notifal tag prefixes for template classification.
     *
     * @since 2.3.0
     * @return string[]
     */
    private static function getKnownTagPrefixes(): array
    {
        $prefixes = [
            'product_',
            'order_',
            'user_',
            'post_',
            'page_',
            'comment_',
            'cpt_',
        ];

        if (!function_exists('notifal_app')) {
            return $prefixes;
        }

        try {
            /** @var SettingsService $settingsService */
            $settingsService = notifal_app(SettingsService::class);
            $generatedPostTypes = $settingsService->get('generated_posttype_list', []);

            if (is_array($generatedPostTypes)) {
                foreach ($generatedPostTypes as $postType) {
                    if (is_string($postType) && $postType !== '') {
                        $prefixes[] = $postType . '_';
                    }
                }
            }
        } catch (\Throwable $e) {
            return $prefixes;
        }

        return array_values(array_unique($prefixes));
    }
}
