<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Rules;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\DisplayRulesService;

defined('ABSPATH') || exit;

/**
 * Server-side matcher for Users display rule login status (lite enforcement).
 *
 * Handles guest / logged-in / all checks so restricted HTML is never included in
 * public REST responses. Role restrictions are a Pro feature and enforced in Pro.
 *
 * @since 2.3.10
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Application\Services\Rules
 */
class UserDisplayRulesMatcher
{
    /**
     * Check whether the current visitor matches Users rule login status.
     *
     * @param array<string, mixed> $ruleData Saved Users rule configuration.
     * @param array<string, mixed> $context  Optional page or REST context.
     * @return bool True when the visitor matches login-status constraints.
     * @since 2.3.10
     */
    public static function matchesLoginStatus(array $ruleData, array $context = []): bool
    {
        // Default to all users when login status is omitted.
        $userType = isset($ruleData['user_type'])
            ? sanitize_text_field((string) $ruleData['user_type'])
            : DisplayRulesService::USER_LOGIN_STATUS_DEFAULT;

        // Resolve the authenticated visitor from context, session, or auth cookie.
        $authContext = VisitorAuthContextResolver::resolve($context);
        $userId = (int) ($authContext['user_id'] ?? 0);
        $isLoggedIn = $userId > 0;

        $matches = self::evaluateLoginStatus($userType, $isLoggedIn);

        /**
         * Filter server-side Users login-status match result.
         *
         * @since 2.3.10
         * @param bool                 $matches     Whether the visitor matches login constraints.
         * @param array<string, mixed> $ruleData    Users rule configuration.
         * @param array<string, mixed> $authContext Resolved visitor authentication context.
         */
        return (bool) apply_filters(
            FilterHooks::ONPAGE_USER_DISPLAY_RULES_MATCH,
            $matches,
            $ruleData,
            $authContext
        );
    }

    /**
     * Evaluate login status only (guest, logged_in, or all).
     *
     * @param string $userType   Target login status.
     * @param bool   $isLoggedIn Whether the visitor is authenticated.
     * @return bool True when login constraints pass.
     * @since 2.3.10
     */
    private static function evaluateLoginStatus(string $userType, bool $isLoggedIn): bool
    {
        // All Users skips login-status filtering (guests and logged-in both match).
        if ($userType === 'all' || $userType === '') {
            return true;
        }

        // Reject logged-in visitors for guest-only notifications.
        if ($userType === 'guest' && $isLoggedIn) {
            return false;
        }

        // Reject guests for logged-in-only notifications.
        if ($userType === 'logged_in' && !$isLoggedIn) {
            return false;
        }

        return true;
    }
}
