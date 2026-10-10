<?php

namespace Notifal\Modules\Forms\Application\Services\Security;

defined('ABSPATH') || exit;

/**
 * Transient-based rate limiter keyed by IP hash + form ID.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class RateLimiter
{
    /**
     * Hash visitor IP for storage keys (no raw IP stored long-term).
     *
     * @return string
     * @since 3.0.0
     */
    public function getIpHash(): string
    {
        $ip = '';
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR']));
        }

        return hash_hmac('sha256', $ip, wp_salt('nonce'));
    }

    /**
     * Whether the visitor is within the allowed submission budget.
     *
     * @param int $formId Form CPT ID.
     * @param int $max    Max attempts.
     * @param int $window Window seconds.
     * @return bool True when allowed.
     * @since 3.0.0
     */
    public function isAllowed(int $formId, int $max, int $window): bool
    {
        $key = $this->buildKey($formId);
        $count = (int) get_transient($key);

        return $count < $max;
    }

    /**
     * Increment the attempt counter after a processed submit.
     *
     * @param int $formId Form CPT ID.
     * @param int $window Window seconds.
     * @return void
     * @since 3.0.0
     */
    public function hit(int $formId, int $window): void
    {
        $key = $this->buildKey($formId);
        $count = (int) get_transient($key);
        set_transient($key, $count + 1, max(30, $window));
    }

    /**
     * @param int $formId Form ID.
     * @return string
     */
    private function buildKey(int $formId): string
    {
        return 'notifal_form_rl_' . $formId . '_' . substr($this->getIpHash(), 0, 16);
    }
}
