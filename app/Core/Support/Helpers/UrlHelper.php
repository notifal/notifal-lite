<?php

namespace Notifal\Core\Support\Helpers;

defined('ABSPATH') || exit;

/**
 * Class UrlHelper
 *
 * Provides helper methods for generating WordPress admin and AJAX URLs.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class UrlHelper
{
    /**
     * Get full admin URL for a given path.
     *
     * @param string $path
     * @return string
     * @since 2.0.0
     */
    public static function admin(string $path): string
    {
        return admin_url(ltrim($path, '/'));
    }

    /**
     * Get the base AJAX URL for WordPress admin-ajax.php.
     *
     * @return string
     * @since 2.0.0
     */
    public static function baseAjax(): string
    {
        return admin_url('admin-ajax.php');
    }

    /**
     * Build an AJAX URL for a given action.
     *
     * @param string $action
     * @param array $params
     * @return string
     * @since 2.0.0
     */
    public static function ajax(string $action, array $params = []): string
    {
        $params = array_merge(['action' => $action], $params);
        return admin_url('admin-ajax.php?' . http_build_query($params));
    }

    /**
     * Build a REST API URL for a given route.
     *
     * @param string $route
     * @return string
     * @since 2.0.0
     */
    public static function rest(string $route): string
    {
        return get_rest_url(null, ltrim($route, '/'));
    }
}
