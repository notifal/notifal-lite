<?php

namespace Notifal\Infrastructure\WordPress\Admin\Support;

use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Resolves the remote AI support (NAS) REST base URL for client calls.
 *
 * Defaults to production notifal.com. Developers can retarget stage via
 * the notifal/ai/support_base_url filter without shipping host changes.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SupportUrlResolver
{
    /**
     * Return the REST base URL for the hosted AI support API.
     *
     * @since 3.0.0
     * @return string Filtered support REST base URL.
     */
    public function getBaseUrl(): string
    {
        // Start from the centralized notifal.com support constant.
        $url = Urls::API_BASE_AI_SUPPORT;

        /**
         * Filter the hosted AI support REST base URL.
         *
         * @since 3.0.0
         * @param string $url Support REST base URL.
         */
        return (string) apply_filters(FilterHooks::AI_SUPPORT_BASE_URL, $url);
    }

    /**
     * Build a fully qualified support route URL.
     *
     * @since 3.0.0
     * @param string $route Route suffix without leading slash.
     * @return string Absolute support route URL.
     */
    public function route(string $route): string
    {
        // Trim slashes to avoid malformed URLs.
        $route = ltrim($route, '/');

        // Concatenate the base URL with the route segment.
        return trailingslashit($this->getBaseUrl()) . $route;
    }
}
