<?php

namespace Notifal\Domain\Tags\Traits;

use Notifal\Domain\Tags\Tag;

/**
 * Trait TagResolverTrait
 *
 * Provides common functionality for tag resolution and registration
 * used across different tag management services.
 *
 * @package Notifal\Domain\Tags\Traits
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
trait TagResolverTrait
{
    /**
     * Create tag resolver for generated tags
     *
     * @param string $postTypeName Post type name
     * @param array $tagData Tag data
     * @return callable Tag resolver function
     * @since 2.0.0
     */
    private function createTagResolver(string $postTypeName, array $tagData): callable
    {
        $tagType = $tagData['type'] ?? 'standard';
        $field = $tagData['field'] ?? '';

        return function ($context) use ($postTypeName, $tagType, $field) {
            // Try to get post from context using the specific post type key
            $post = $context[$postTypeName] ?? null;

            // Fallback: Try generic 'post' key for backward compatibility
            if (!$post) {
                $post = $context['post'] ?? null;
            }

            // If no post in context, try to get current post
            if (!$post && function_exists('get_the_ID')) {
                $postId = get_the_ID();
                if ($postId) {
                    $post = get_post($postId);
                }
            }

            // Return empty if no post or wrong post type
            if (!$post || $post->post_type !== $postTypeName) {
                return '';
            }

            // Handle different tag types
            switch ($tagType) {
                case 'meta':
                    return get_post_meta($post->ID, $field, true) ?: '';

                case 'taxonomy':
                    $terms = get_the_terms($post->ID, $field);
                    if ($terms && !is_wp_error($terms)) {
                        return implode(', ', wp_list_pluck($terms, 'name'));
                    }
                    return '';

                case 'standard':
                default:
                    return $this->getStandardFieldValue($post, $field);
            }
        };
    }

    /**
     * Get value for standard post fields
     *
     * @param \WP_Post $post Post object
     * @param string $field Field name
     * @return string Field value
     * @since 2.0.0
     */
    private function getStandardFieldValue($post, string $field): string
    {
        switch ($field) {
            case 'title':
                return get_the_title($post->ID);
            case 'content':
                return get_the_content(null, false, $post->ID);
            case 'excerpt':
                return get_the_excerpt($post->ID);
            case 'date':
                return get_the_date('', $post->ID);
            case 'author':
                return get_the_author_meta('display_name', $post->post_author);
            case 'url':
                return get_permalink($post->ID);
            case 'id':
                return (string) $post->ID;
            case 'slug':
                return $post->post_name;
            default:
                return '';
        }
    }

    /**
     * Convert tag name to human readable label
     *
     * @param string $tagName Tag name
     * @return string Human readable label
     * @since 2.0.0
     */
    private function humanizeTagName(string $tagName): string
    {
        // Remove post type prefix if present
        $label = preg_replace('/^[a-zA-Z_]+_/', '', $tagName);

        // Convert underscores to spaces and capitalize
        $label = str_replace('_', ' ', $label);
        $label = ucwords($label);

        return $label;
    }

    /**
     * Register tags for a specific post type with the TagManager
     *
     * @param object $manager TagManager instance (must have registerTag method)
     * @param string $postTypeName Post type name
     * @param array $tags Tags data array
     * @return void
     * @since 2.0.0
     */
    private function registerPostTypeTags($manager, string $postTypeName, array $tags): void
    {
        foreach ($tags as $tagData) {
            try {
                // Create resolver based on tag type
                $resolver = $this->createTagResolver($postTypeName, $tagData);

                // Create category name (use lowercase post type name)
                $category = strtolower($postTypeName);

                // Create and register tag
                $tag = new Tag(
                    $tagData['name'],
                    $tagData['label'] ?? $this->humanizeTagName($tagData['name']),
                    $resolver,
                    $category,
                    sprintf(__('Generated tag for %s %s field', 'notifal'), $postTypeName, $tagData['field'] ?? 'unknown')
                );

                $manager->registerTag($tag);

            } catch (\Exception $e) {
                // Continue with other tags on error
            }
        }
    }
}
