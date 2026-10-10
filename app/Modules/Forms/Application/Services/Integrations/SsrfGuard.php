<?php

namespace Notifal\Modules\Forms\Application\Services\Integrations;

defined('ABSPATH') || exit;

/**
 * Blocks webhook SSRF to private/reserved IPs; HTTPS only.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SsrfGuard
{
    /**
     * Validate a webhook URL for outbound requests.
     *
     * @param string $url Candidate URL.
     * @return string Empty when invalid, otherwise the URL.
     * @since 3.0.0
     */
    public static function sanitizeUrl(string $url): string
    {
        $url = esc_url_raw($url, ['https']);
        if ($url === '') {
            return '';
        }

        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!$host || !is_string($host)) {
            return '';
        }

        $ip = gethostbyname($host);
        if ($ip === $host) {
            // Hostname did not resolve to an IP string distinct from host; try filter on host.
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                $ip = $host;
            } else {
                return '';
            }
        }

        if (self::isPrivateIp($ip)) {
            return '';
        }

        return $url;
    }

    /**
     * Whether an IP is private, loopback, link-local, or metadata range.
     *
     * @param string $ip IP address.
     * @return bool
     * @since 3.0.0
     */
    public static function isPrivateIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return !filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return !filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }

        return true;
    }
}
