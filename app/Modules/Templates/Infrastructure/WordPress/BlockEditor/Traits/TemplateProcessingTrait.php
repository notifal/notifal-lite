<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Traits;

use Notifal\Infrastructure\WordPress\Elementor\Helpers\ElementorHelper;

/**
 * Trait TemplateProcessingTrait
 *
 * Provides common template processing logic for BlockEditor hooks.
 * Consolidates duplicated logic for content processing checks and Elementor detection.
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Traits
 * @author Hossein <hossein@notifal.com>
 */
trait TemplateProcessingTrait
{
    /**
     * Check if we should process content for tag rendering.
     *
     * Determines whether the current context requires template tag processing
     * based on post type, admin context, and REST API requests.
     *
     * @return bool True if content should be processed, false otherwise
     * @since 2.0.0
     */
    private function shouldProcessContent(): bool
    {
        global $post;

        // Check if we have a post and it's a notifal_template
        if (isset($post) && $post instanceof \WP_Post && $post->post_type === 'notifal_template') {
            return true;
        }

        // For REST API requests, check the post type from the request
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $request = rest_get_server()->get_route_options('/wp/v2/notifal_template');
            if (!empty($request)) {
                return true;
            }
        }

        // For block editor context (admin area with notifal_template)
        if (is_admin() && isset($_GET['post'])) {
            $post_id = absint($_GET['post']);
            $post_obj = get_post($post_id);
            if ($post_obj && $post_obj->post_type === 'notifal_template') {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if we should process the current post for tag rendering.
     *
     * Alternative method for checking if the current global post should be processed.
     * Used in frontend content processing contexts.
     *
     * @return bool True if the current post should be processed, false otherwise
     * @since 2.0.0
     */
    private function shouldProcessCurrentPost(): bool
    {
        global $post;

        // Check if we have a post and it's a notifal_template
        if (isset($post) && $post instanceof \WP_Post && $post->post_type === 'notifal_template') {
            return true;
        }

        // Check if we're in a context where post data might not be available in global
        if (is_singular('notifal_template')) {
            return true;
        }

        return false;
    }

    /**
     * Check if the current template is built with Elementor.
     *
     * Determines if the current post/template uses Elementor page builder
     * to avoid conflicts with Elementor's content processing.
     *
     * @return bool True if template uses Elementor, false otherwise
     * @since 2.0.0
     */
    private function isElementorTemplate(): bool
    {
        global $post;

        if (!isset($post) || !$post instanceof \WP_Post) {
            return false;
        }

        return ElementorHelper::hasBuilder($post);
    }

    /**
     * Check if we're in an active frontend notification context.
     *
     * Determines if the WidgetContextProvider is active, indicating that
     * frontend notification rendering is in progress and template processing
     * should be skipped to avoid context conflicts.
     *
     * @return bool True if in active notification context, false otherwise
     * @since 2.0.0
     */
    private function isInActiveNotificationContext(): bool
    {
        return class_exists('\Notifal\Modules\OnPageNotification\Application\Services\Utility\WidgetContextProvider')
            && \Notifal\Modules\OnPageNotification\Application\Services\Utility\WidgetContextProvider::isActive();
    }

    /**
     * Process content for notification context with dynamic tag rendering.
     *
     * When in an active notification context, uses the notification's context
     * data to render tags dynamically instead of using preview data.
     *
     * @param string $content The content to process
     * @return string Processed content with dynamic tags
     * @since 2.0.0
     */
    private function processContentForNotificationContext(string $content): string
    {
        if (!$this->isInActiveNotificationContext()) {
            return $content;
        }

        $context = \Notifal\Modules\OnPageNotification\Application\Services\Utility\WidgetContextProvider::getContext();
        if ($context) {
            $tagManager = notifal_app(\Notifal\Domain\Tags\TagManager::class);
            return $tagManager->render($content, $context);
        }

        return $content;
    }
}