<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\Registration;

use Notifal\Modules\Templates\Controllers\FeaturedImageApiController;

defined('ABSPATH') || exit;

/**
 * Class FeaturedImageApiRegister
 *
 * Registers REST API routes for fetching featured image data for editor preview.
 * Provides context-aware image resolution for the featured image block in templates.
 *
 * This class is responsible for registering the '/notifal/v1/featured-image-preview'
 * endpoint that allows the template editor to preview featured images dynamically.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\Registration
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FeaturedImageApiRegister
{
    /**
     * Register REST API routes for featured image preview.
     *
     * Registers the featured image preview endpoint with proper permissions
     * and callback handling for template editor integration.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register(): void
    {
        add_action('rest_api_init', function () {
            // Featured image endpoint for context-aware image resolution
            register_rest_route('notifal/v1', '/featured-image-preview', [
                'methods'             => 'GET',
                'callback'            => [new FeaturedImageApiController(), 'getFeaturedImagePreview'],
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
                'args'                => [
                    'source'             => [
                        'type'              => 'string',
                        'default'           => 'auto',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'notification_id'   => [
                        'type'              => 'integer',
                        'minimum'           => 1,
                        'sanitize_callback' => 'absint',
                    ],
                    'template_content'   => [
                        'type' => 'string',
                    ],
                ],
            ]);
        });
    }
}