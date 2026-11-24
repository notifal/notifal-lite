<?php

namespace Notifal\Modules\Ai\Application\Services;

use Notifal\Modules\Ai\Config\Constants;

defined('ABSPATH') || exit;

/**
 * Stores each administrator's one-time consent to auto-apply approved Noti scripts.
 *
 * Consent is per user because the WordPress capability that allows keeping
 * scripts (unfiltered_html) is per user. A user without that capability can
 * never be "consented", whatever the stored value says.
 *
 * @since 3.0.3
 * @author Hossein <hossein@notifal.com>
 */
class ScriptAutoApplyConsentService
{
    /**
     * Whether the current user may have scripts applied automatically at all.
     *
     * @since 3.0.3
     * @return bool True when the user holds unfiltered_html.
     */
    public function isAvailable(): bool
    {
        // The WordPress capability is the hard boundary for keeping template scripts.
        return current_user_can('unfiltered_html');
    }

    /**
     * Whether the current user already allowed automatic script apply.
     *
     * @since 3.0.3
     * @return bool True when available and consented.
     */
    public function hasConsent(): bool
    {
        // Never report consent for users who cannot keep scripts.
        if (!$this->isAvailable()) {
            return false;
        }

        // Read the stored flag; only the exact "1" value counts as consent.
        return '1' === (string) get_user_meta(get_current_user_id(), Constants::USER_META_SCRIPT_AUTO_APPLY, true);
    }

    /**
     * Store or revoke the current user's consent.
     *
     * @since 3.0.3
     * @param bool $allowed True to allow automatic apply, false to revoke it.
     * @return bool True when the stored state now matches the request.
     */
    public function setConsent(bool $allowed): bool
    {
        // Refuse to store consent for users who cannot keep scripts.
        if ($allowed && !$this->isAvailable()) {
            return false;
        }

        // Persist an explicit value so revoking is distinguishable from never asked.
        update_user_meta(get_current_user_id(), Constants::USER_META_SCRIPT_AUTO_APPLY, $allowed ? '1' : '0');

        return $allowed === $this->hasConsent();
    }

    /**
     * Browser-safe state for the HTML Builder chat.
     *
     * @since 3.0.3
     * @return array{available: bool, consented: bool} Public consent state.
     */
    public function getPublicState(): array
    {
        return [
            'available' => $this->isAvailable(),
            'consented' => $this->hasConsent(),
        ];
    }
}
