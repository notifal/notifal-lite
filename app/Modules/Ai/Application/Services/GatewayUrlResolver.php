<?php

namespace Notifal\Modules\Ai\Application\Services;

use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Resolves the remote AI gateway base URL for server-to-server calls.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class GatewayUrlResolver
{
    /**
     * Return the REST base URL for the hosted AI gateway.
     *
     * @since 3.0.0
     * @return string Filtered gateway REST base URL.
     */
    public function getBaseUrl(): string
    {
        // Start from the centralized notifal.com gateway constant.
        $url = Urls::API_BASE_AI_GATEWAY;

        /**
         * Filter the hosted AI gateway REST base URL.
         *
         * @since 3.0.0
         * @param string $url Gateway REST base URL.
         */
        return (string) apply_filters(FilterHooks::AI_GATEWAY_BASE_URL, $url);
    }

    /**
     * Build a fully qualified gateway route URL.
     *
     * @since 3.0.0
     * @param string $route Route suffix without leading slash.
     * @return string Absolute gateway route URL.
     */
    public function route(string $route): string
    {
        // Trim slashes to avoid malformed URLs.
        $route = ltrim($route, '/');

        // Concatenate the base URL with the route segment.
        return trailingslashit($this->getBaseUrl()) . $route;
    }
}
