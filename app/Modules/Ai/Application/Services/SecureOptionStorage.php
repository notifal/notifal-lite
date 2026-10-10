<?php

namespace Notifal\Modules\Ai\Application\Services;

defined('ABSPATH') || exit;

/**
 * Encrypts sensitive option payloads using site-specific WordPress salts.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SecureOptionStorage
{
    /**
     * Encrypt a plaintext secret for option storage.
     *
     * @since 3.0.0
     * @param string $plaintext Secret value to protect.
     * @return string Encrypted payload string.
     *
     * @throws \RuntimeException When OpenSSL encryption is unavailable.
     */
    public function encrypt(string $plaintext): string
    {
        // Require OpenSSL so refresh tokens are never stored in plaintext.
        if (!function_exists('openssl_encrypt')) {
            throw new \RuntimeException('encryption_unavailable');
        }

        // Derive a stable 256-bit key from WordPress auth salt material.
        $key = hash('sha256', wp_salt('auth'), true);

        // Generate a random 12-byte IV suitable for AES-GCM.
        $iv = random_bytes(12);

        // Encrypt using authenticated AES-256-GCM.
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        // Abort when encryption fails unexpectedly.
        if ($cipher === false) {
            throw new \RuntimeException('encryption_failed');
        }

        // Prefix the payload version for future rotation support.
        return 'v1:' . base64_encode($iv . $tag . $cipher);
    }

    /**
     * Decrypt a previously encrypted option payload.
     *
     * @since 3.0.0
     * @param string $payload Encrypted payload from {@see encrypt()}.
     * @return string Decrypted plaintext secret.
     *
     * @throws \RuntimeException When decryption fails or format is invalid.
     */
    public function decrypt(string $payload): string
    {
        // Require OpenSSL for symmetric decryption.
        if (!function_exists('openssl_decrypt')) {
            throw new \RuntimeException('encryption_unavailable');
        }

        // Validate the versioned envelope prefix.
        if (strpos($payload, 'v1:') !== 0) {
            throw new \RuntimeException('invalid_encrypted_payload');
        }

        // Decode the base64 ciphertext envelope.
        $raw = base64_decode(substr($payload, 3), true);
        if ($raw === false || strlen($raw) < 28) {
            throw new \RuntimeException('invalid_encrypted_payload');
        }

        // Split IV, authentication tag, and ciphertext segments.
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);

        // Derive the same site-specific encryption key.
        $key = hash('sha256', wp_salt('auth'), true);

        // Decrypt and authenticate the payload.
        $plaintext = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        // Reject tampered or corrupted payloads.
        if ($plaintext === false) {
            throw new \RuntimeException('decryption_failed');
        }

        // Return the recovered secret.
        return $plaintext;
    }
}
