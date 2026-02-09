<?php

namespace Notifal\Infrastructure\WordPress\Security;

defined('ABSPATH') || exit;

/**
 * Class NonceManager
 *
 * Handles creation and verification of WordPress nonces for secure AJAX operations.
 * Provides a centralized interface for nonce management throughout the Notifal plugin.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class NonceManager
{
    /**
     * Create a new nonce for a given action.
     *
     * @param string $action The nonce action identifier
     * @return string The generated nonce
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function create(string $action): string
    {
        return wp_create_nonce($action);
    }

    /**
     * Verify a nonce for a given action.
     *
     * @param string $nonce The nonce to verify
     * @param string $action The nonce action identifier
     * @return bool True if nonce is valid, false otherwise
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function verify(string $nonce, string $action): bool
    {
        return wp_verify_nonce($nonce, $action) === 1;
    }
}
