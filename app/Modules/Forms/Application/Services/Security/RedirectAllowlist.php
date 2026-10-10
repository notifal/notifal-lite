<?php

namespace Notifal\Modules\Forms\Application\Services\Security;

defined('ABSPATH') || exit;

/**
 * Same-site / host-allowlisted success redirect validation.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class RedirectAllowlist
{
    /**
     * Sanitize a success redirect URL; empty string when blocked.
     *
     * @param string $url Candidate redirect.
     * @return string Safe relative path or absolute same-host URL, or empty.
     * @since 3.0.0
     */
    public static function sanitize(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $lower = strtolower($url);
        if (strpos($lower, 'javascript:') === 0 || strpos($lower, 'data:') === 0) {
            return '';
        }

        // Relative path on same site.
        if (isset($url[0]) && $url[0] === '/' && (!isset($url[1]) || $url[1] !== '/')) {
            return esc_url_raw($url);
        }

        $clean = esc_url_raw($url, ['http', 'https']);
        if ($clean === '') {
            return '';
        }

        $siteHost = wp_parse_url(home_url(), PHP_URL_HOST);
        $urlHost = wp_parse_url($clean, PHP_URL_HOST);

        if (!$siteHost || !$urlHost || strtolower((string) $siteHost) !== strtolower((string) $urlHost)) {
            return '';
        }

        return $clean;
    }
}
