<?php

namespace Notifal\Shared\Utils;

use Notifal\Domain\Settings\Constants\Urls;

defined('ABSPATH') || exit;

/**
 * Class LinkManager
 *
 * Centralized manager for all external Notifal links.
 * Automatically appends tracking parameters (source, medium) to URLs
 * for analytics and marketing purposes.
 *
 * @package Notifal\Shared\Utils
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class LinkManager
{
    /**
     * Base URL for Notifal website.
     *
     * @var string
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    protected static string $baseUrl = Urls::NOTIFAL_BASE_DOMAIN;

    /**
     * Build a full Notifal URL with tracking parameters.
     *
     * @param string $path Relative path to append to base URL (without leading slash)
     * @param string $source Source identifier (e.g., 'notifal', 'notifal-pro')
     * @param string $medium Medium identifier to track the section in the plugin
     * @return string Full URL with tracking query parameters
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildUrl(string $path = '', string $source = 'notifal', string $medium = ''): string
    {
        $baseUrl = trailingslashit(static::$baseUrl) . ltrim($path, '/');
        $query = [
            'source' => $source,
        ];

        if (!empty($medium)) {
            $query['medium'] = $medium;
        }

        return add_query_arg($query, $baseUrl);
    }

    /**
     * Get the full URL to the tags documentation page.
     *
     * @param string $source Source identifier. Default 'notifal'
     * @param string $medium Medium identifier for tracking
     * @return string Full URL to tags documentation
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function tagsDoc(string $source = 'notifal', string $medium = ''): string
    {
        return static::buildUrl('document/tags', $source, $medium);
    }

    /**
     * Get the full URL to the general documentation page.
     *
     * @param string $source Source identifier. Default 'notifal'
     * @param string $medium Medium identifier for tracking
     * @return string Full URL to general documentation
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function docs(string $source = 'notifal', string $medium = ''): string
    {
        return static::buildUrl('document', $source, $medium);
    }

    /**
     * Get the full URL to the Notifal homepage.
     *
     * @param string $source Source identifier. Default 'notifal'
     * @param string $medium Medium identifier for tracking
     * @return string Full URL to Notifal homepage
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function homepage(string $source = 'notifal', string $medium = ''): string
    {
        return static::buildUrl('', $source, $medium);
    }
}
