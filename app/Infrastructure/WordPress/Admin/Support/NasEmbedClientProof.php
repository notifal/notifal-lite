<?php

namespace Notifal\Infrastructure\WordPress\Admin\Support;

defined('ABSPATH') || exit;

/**
 * Builds signed server-to-server proof headers for NAS backend embed minting.
 *
 * Shared signing material must stay in sync with notifal-ai-support on notifal.com.
 *
 * @since 3.0.0
 * @package Notifal\Infrastructure\WordPress\Admin\Support
 * @author Hossein <hossein@notifal.com>
 */
class NasEmbedClientProof
{
    /**
     * Attestation payload version.
     *
     * @var string
     */
    public const PROOF_VERSION = 'v1';

    /**
     * Backend support channel slug.
     *
     * @var string
     */
    public const CHANNEL_BACKEND = 'backend';

    /**
     * Client slug included in signed payloads.
     *
     * @var string
     */
    public const CLIENT_SLUG = 'notifal';

    /**
     * Shared key material mirrored on notifal.com NAS server.
     *
     * @var string
     */
    public const KEY_MATERIAL = 'notifal-nas-backend-embed-attestation-v1';

    /**
     * Build signed HTTP headers for embed mint authorization.
     *
     * @param string $domain  Current site host.
     * @param string $channel NAS channel slug.
     * @return array<string, string>
     */
    public static function build_headers(string $domain, string $channel = self::CHANNEL_BACKEND): array
    {
        $domain = self::normalize_domain($domain);
        $channel = sanitize_key($channel);
        $timestamp = time();
        $client_version = defined('NOTIFAL_VERSION') ? (string) NOTIFAL_VERSION : '0.0.0';
        $client_version = substr(sanitize_text_field($client_version), 0, 40);
        $signature = self::sign($channel, $domain, $timestamp, $client_version);

        return [
            'X-Notifal-Embed-Timestamp' => (string) $timestamp,
            'X-Notifal-Embed-Signature' => $signature,
            'X-Notifal-Client-Version'  => $client_version,
            'X-Notifal-Client-Slug'     => self::CLIENT_SLUG,
        ];
    }

    /**
     * Sign one embed mint attestation payload.
     *
     * @param string $channel        NAS channel slug.
     * @param string $domain         Site host.
     * @param int    $timestamp      Unix timestamp.
     * @param string $client_version Notifal plugin version.
     * @return string Hex-encoded HMAC signature.
     */
    public static function sign(string $channel, string $domain, int $timestamp, string $client_version): string
    {
        $payload = implode(
            '|',
            [
                self::PROOF_VERSION,
                sanitize_key($channel),
                self::normalize_domain($domain),
                (string) $timestamp,
                substr(sanitize_text_field($client_version), 0, 40),
                self::CLIENT_SLUG,
            ]
        );

        return hash_hmac('sha256', $payload, self::signing_key());
    }

    /**
     * Derive the binary signing key from shared key material.
     *
     * @return string Binary key for hash_hmac().
     */
    public static function signing_key(): string
    {
        return hash('sha256', self::KEY_MATERIAL, true);
    }

    /**
     * Normalize and validate one site host for signing.
     *
     * @param string $domain Raw domain or host.
     * @return string Normalized host.
     */
    public static function normalize_domain(string $domain): string
    {
        $domain = strtolower(sanitize_text_field($domain));

        if ('' === $domain || !self::is_valid_domain($domain)) {
            return '';
        }

        return $domain;
    }

    /**
     * Validate one hostname for embed mint attestation.
     *
     * @param string $domain Hostname.
     * @return bool
     */
    public static function is_valid_domain(string $domain): bool
    {
        if ('localhost' === $domain) {
            return true;
        }

        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            return true;
        }

        if (preg_match('/\.(local|test|localhost)$/i', $domain)) {
            return true;
        }

        return (bool) preg_match(
            '/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/i',
            $domain
        );
    }
}
