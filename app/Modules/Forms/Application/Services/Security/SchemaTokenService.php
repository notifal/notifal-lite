<?php

namespace Notifal\Modules\Forms\Application\Services\Security;

defined('ABSPATH') || exit;

/**
 * Short-lived HMAC schema tokens for anti-tamper form submit.
 *
 * Token payload: form_id + notification_id + schema fingerprint + expiry.
 * Signing key is derived from wp_salt; never printed to the browser as raw key.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SchemaTokenService
{
    /**
     * Token lifetime in seconds.
     *
     * @var int
     */
    private const TTL = 7200;

    /**
     * Create a signed token for a rendered form schema.
     *
     * @param int    $formId         Form CPT ID (0 allowed for unbound forms).
     * @param int    $notificationId Notification context ID.
     * @param string $fingerprint    Schema fingerprint hash.
     * @return string Opaque token string.
     * @since 3.0.0
     */
    public function create(int $formId, int $notificationId, string $fingerprint): string
    {
        $expires = time() + self::TTL;
        $payload = $formId . '|' . $notificationId . '|' . $fingerprint . '|' . $expires;
        $signature = hash_hmac('sha256', $payload, $this->getSigningKey());

        return base64_encode($payload . '|' . $signature);
    }

    /**
     * Verify token and return payload parts or null on failure.
     *
     * @param string $token          Submitted token.
     * @param int    $formId         Expected form ID.
     * @param int    $notificationId Expected notification ID.
     * @param string $fingerprint    Expected schema fingerprint.
     * @return bool
     * @since 3.0.0
     */
    public function verify(string $token, int $formId, int $notificationId, string $fingerprint): bool
    {
        $decoded = base64_decode($token, true);
        if ($decoded === false || $decoded === '') {
            return false;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 5) {
            return false;
        }

        list($tokenFormId, $tokenNotificationId, $tokenFingerprint, $expires, $signature) = $parts;

        if (!hash_equals($fingerprint, (string) $tokenFingerprint)) {
            return false;
        }

        if ((int) $tokenFormId !== $formId || (int) $tokenNotificationId !== $notificationId) {
            return false;
        }

        if ((int) $expires < time()) {
            return false;
        }

        $payload = $tokenFormId . '|' . $tokenNotificationId . '|' . $tokenFingerprint . '|' . $expires;
        $expected = hash_hmac('sha256', $payload, $this->getSigningKey());

        return hash_equals($expected, (string) $signature);
    }

    /**
     * Derive HMAC key from WordPress salts (never localized).
     *
     * @return string
     * @since 3.0.0
     */
    private function getSigningKey(): string
    {
        return wp_salt('auth') . '|notifal-forms-schema';
    }
}
