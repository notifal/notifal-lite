<?php

namespace Notifal\Infrastructure\WordPress\Admin\Support;

defined('ABSPATH') || exit;

/**
 * Transient-based rate limiter for backend NAS embed token mint proxy.
 *
 * @since 3.0.0
 * @package Notifal\Infrastructure\WordPress\Admin\Support
 * @author Hossein <hossein@notifal.com>
 */
class NasSupportMintRateLimiter
{
    /**
     * Maximum mint attempts per identity within one window.
     *
     * @var int
     */
    private const MAX_ATTEMPTS = 20;

    /**
     * Rate limit window in seconds.
     *
     * @var int
     */
    private const WINDOW_SECONDS = 3600;

    /**
     * Determine whether one mint attempt is allowed for the current admin.
     *
     * @return bool
     */
    public function is_allowed(): bool
    {
        return (int) get_transient($this->build_key()) < self::MAX_ATTEMPTS;
    }

    /**
     * Record one mint attempt for the current admin.
     *
     * @return void
     */
    public function hit(): void
    {
        $key = $this->build_key();
        $count = (int) get_transient($key);
        set_transient($key, $count + 1, self::WINDOW_SECONDS);
    }

    /**
     * Build a hashed transient key from user and site host.
     *
     * @return string
     */
    private function build_key(): string
    {
        $user_id = get_current_user_id();
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        $host = is_string($host) ? strtolower($host) : 'unknown';

        return 'notifal_nas_mint_' . substr(
            hash_hmac('sha256', $user_id . '|' . $host, wp_salt('auth')),
            0,
            24
        );
    }
}
