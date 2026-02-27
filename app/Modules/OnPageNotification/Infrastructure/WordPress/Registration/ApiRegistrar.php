<?php

namespace Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Registration;

use Notifal\Modules\OnPageNotification\Presentation\Frontend\Controllers\OnPageNotificationApiController;

defined('ABSPATH') || exit;

/**
 * Class ApiRegistrar
 *
 * Registers REST API routes for OnPage notification functionality.
 * Handles eligibility checking and event tracking for frontend notifications.
 *
 * @package Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Registration
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ApiRegistrar
{
    /**
     * Register REST API routes.
     *
     * @since 2.0.0
     */
    public static function register(): void
    {
        if (did_action('rest_api_init')) {
            // REST API already initialized, register routes directly
            self::registerRoutes();
        } else {
            // Register via action as usual
            add_action('rest_api_init', [self::class, 'registerRoutes']);
        }
    }

    /**
     * Register all REST API routes for OnPage notification functionality.
     *
     * @since 2.0.0
     */
    public static function registerRoutes(): void
    {
        // Register eligibility endpoint
        register_rest_route('notifal/v1', '/onpage/eligible', self::getEligibleRouteArgs());

        // Register preview endpoint (admin only; same response shape as eligible)
        register_rest_route('notifal/v1', '/onpage/preview', self::getPreviewRouteArgs());

        // Register tracking endpoint
        register_rest_route('notifal/v1', '/onpage/track', self::getTrackingRouteArgs());

        // Register user preferences endpoints
        register_rest_route('notifal/v1', '/onpage/preferences', self::getPreferencesGetRouteArgs());
        register_rest_route('notifal/v1', '/onpage/preferences', self::getPreferencesPostRouteArgs());
    }

    /**
     * Get route arguments for eligible notifications endpoint.
     *
     * @return array Route configuration array
     * @since 2.0.0
     */
    private static function getEligibleRouteArgs(): array
    {
        return [
            'methods'             => 'GET',
            'callback'            => [new OnPageNotificationApiController(), 'getEligibleNotifications'],
            'permission_callback' => '__return_true',
            'args'                => [
                'page_id' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($param) {
                        return $param > 0;
                    },
                ],
                'url' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ],
                'user_id' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'device_type' => [
                    'required'          => false,
                    'type'              => 'string',
                    'enum'              => ['desktop', 'mobile', 'tablet'],
                    'default'           => 'desktop',
                ],
            ],
        ];
    }

    /**
     * Get route arguments for preview endpoint (admin only).
     *
     * @return array Route configuration array
     * @since 2.0.0
     */
    private static function getPreviewRouteArgs(): array
    {
        return [
            'methods'             => 'GET',
            'callback'            => [new OnPageNotificationApiController(), 'getPreviewNotification'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => static function ($param) {
                        return $param > 0;
                    },
                ],
                '_preview_token' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'device_type' => [
                    'required'          => false,
                    'type'              => 'string',
                    'enum'              => ['desktop', 'mobile', 'tablet'],
                    'default'           => 'desktop',
                ],
            ],
        ];
    }

    /**
     * Get route arguments for tracking endpoint.
     *
     * @return array Route configuration array
     * @since 2.0.0
     */
    private static function getTrackingRouteArgs(): array
    {
        return [
            'methods'             => 'POST',
            'callback'            => [new OnPageNotificationApiController(), 'trackEvent'],
            'permission_callback' => '__return_true',
            'args'                => [
                'notification_id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($param) {
                        return $param > 0;
                    },
                ],
                'event_type' => [
                    'required'          => true,
                    'type'              => 'string',
                    'enum'              => ['impression', 'click', 'close', 'dismiss'],
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'timestamp' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'default'           => time(),
                ],
                'user_agent' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'referrer' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ],
                'page_url' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ],
            ],
        ];
    }

    /**
     * Get route arguments for GET preferences endpoint.
     *
     * @return array Route configuration array
     * @since 2.0.0
     */
    private static function getPreferencesGetRouteArgs(): array
    {
        return [
            'methods'             => 'GET',
            'callback'            => [new OnPageNotificationApiController(), 'getUserPreferences'],
            'permission_callback' => '__return_true',
        ];
    }

    /**
     * Get route arguments for POST preferences endpoint.
     *
     * @return array Route configuration array
     * @since 2.0.0
     */
    private static function getPreferencesPostRouteArgs(): array
    {
        return [
            'methods'             => 'POST',
            'callback'            => [new OnPageNotificationApiController(), 'updateUserPreferences'],
            'permission_callback' => '__return_true',
            'args'                => [
                'preferences' => [
                    'required'          => true,
                    'type'              => 'object',
                    'properties'        => [
                        'enabled' => [
                            'type' => 'boolean',
                        ],
                        'frequency' => [
                            'type' => 'string',
                            'enum' => ['low', 'medium', 'high'],
                        ],
                        'categories' => [
                            'type'  => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
