<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Rules;

use Notifal\Shared\Helpers\UserHelper;

defined('ABSPATH') || exit;

/**
 * Resolves the current visitor authentication context for display rule evaluation.
 *
 * Reads user ID and roles from request context first, then falls back to WordPress
 * session state and the logged-in auth cookie so REST and page-load paths stay aligned.
 *
 * @since 2.3.10
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Application\Services\Rules
 */
class VisitorAuthContextResolver
{
    /**
     * Resolve authenticated visitor context for server-side Users rule checks.
     *
     * @param array<string, mixed> $context Optional page or REST context.
     * @return array{user_id: int, is_logged_in: bool, user_roles: array<int, string>}
     * @since 2.3.10
     */
    public static function resolve(array $context = []): array
    {
        // Prefer explicit user ID supplied by the caller (REST params or localized context).
        $userId = isset($context['user_id']) ? absint($context['user_id']) : UserHelper::getCurrentUserId();

        // Fall back to the logged-in auth cookie when WordPress has not loaded the user yet.
        if ($userId === 0) {
            $cookieUserId = self::resolveUserIdFromAuthCookie();

            if ($cookieUserId > 0) {
                $userId = $cookieUserId;
                wp_set_current_user($userId);
            }
        }

        $roles = [];

        if ($userId > 0) {
            // Reuse roles from context when already resolved upstream.
            if (!empty($context['user_roles']) && is_array($context['user_roles'])) {
                foreach ($context['user_roles'] as $role) {
                    $roleSlug = sanitize_key((string) $role);

                    if ($roleSlug !== '') {
                        $roles[] = $roleSlug;
                    }
                }
            } else {
                $user = wp_get_current_user();
                $roles = is_array($user->roles ?? null) ? $user->roles : [];
            }
        }

        return [
            'user_id'      => $userId,
            'is_logged_in' => $userId > 0,
            'user_roles'   => array_values(array_unique($roles)),
        ];
    }

    /**
     * Resolve user ID from the WordPress logged-in authentication cookie.
     *
     * @return int Logged-in user ID or 0 when no valid cookie exists.
     * @since 2.3.10
     */
    public static function resolveUserIdFromAuthCookie(): int
    {
        if (!function_exists('wp_validate_auth_cookie')) {
            return 0;
        }

        $validatedUserId = wp_validate_auth_cookie('', 'logged_in');

        if ($validatedUserId === false) {
            return 0;
        }

        return absint($validatedUserId);
    }
}
