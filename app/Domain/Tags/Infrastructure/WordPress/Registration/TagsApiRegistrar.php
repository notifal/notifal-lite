<?php

namespace Notifal\Domain\Tags\Infrastructure\WordPress\Registration;

use Notifal\Domain\Tags\Controllers\TagsApiController;

defined('ABSPATH') || exit;

/**
 * Class TagsApiRegistrar
 *
 * Registers REST API route for retrieving all tags.
 *
 * @package Notifal\Domain\Tags\Infrastructure\WordPress\Registration
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class TagsApiRegistrar
{
    /**
     * Register REST API route.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function register(): void
    {
        if (did_action('rest_api_init')) {
            // REST API already initialized, register route directly
            register_rest_route('notifal/v1', '/tags', self::getRouteArgs());
        } else {
            // Register via action as usual
            add_action('rest_api_init', function () {
                register_rest_route('notifal/v1', '/tags', self::getRouteArgs());
            });
        }
    }

    /**
     * Get route arguments for tags API endpoint.
     *
     * @return array Route configuration array
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function getRouteArgs(): array
    {
        return [
            'methods'             => 'GET',
            'callback'            => [new TagsApiController(), 'getTags'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ];
    }
}
