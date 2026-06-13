<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Core;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\DisplayRulesDataNormalizer;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\DisplayRulesService;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Repositories\NotificationQuery;

defined('ABSPATH') || exit;

/**
 * Builds client-side user display rule payload for frontend evaluation.
 *
 * Login status and visit-history filters run in the browser for full-page cache
 * compatibility. Login and role restrictions are enforced server-side before HTML
 * is rendered; client checks remain a cache-safe UX layer only.
 *
 * @since 2.3.5
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Application\Services\Core
 */
class ClientUserRulesBuilder
{
    /**
     * Object cache key for the active-notifications Users rule index.
     *
     * @since 2.3.10
     */
    private const INDEX_CACHE_KEY = 'notifal_onpage_client_user_rules_index';

    /**
     * Object cache group for Users rule index lookups.
     *
     * @since 2.3.10
     */
    private const INDEX_CACHE_GROUP = 'notifal_onpage';

    /**
     * Build a cached map of client Users rule payloads for active notifications.
     *
     * Scans active notifications once per cache window (same pattern as cart rules usage).
     * Only notifications with guest/logged-in or visit-history filters are included.
     *
     * @return array<string, array<string, int|string|bool|array<int, string>>> Map of notification ID => rules.
     * @since 2.3.10
     */
    public static function buildActiveNotificationsIndex(): array
    {
        $cached = wp_cache_get(self::INDEX_CACHE_KEY, self::INDEX_CACHE_GROUP);

        if (is_array($cached)) {
            return $cached;
        }

        $index = self::scanActiveNotificationsIndex();

        wp_cache_set(self::INDEX_CACHE_KEY, $index, self::INDEX_CACHE_GROUP, HOUR_IN_SECONDS);

        return $index;
    }

    /**
     * Clear cached Users rule index after notification display rules change.
     *
     * @return void
     * @since 2.3.10
     */
    public static function clearIndexCache(): void
    {
        wp_cache_delete(self::INDEX_CACHE_KEY, self::INDEX_CACHE_GROUP);
    }

    /**
     * Scan active notifications and collect client-evaluated Users rule payloads.
     *
     * @return array<string, array<string, int|string|bool|array<int, string>>>
     * @since 2.3.10
     */
    private static function scanActiveNotificationsIndex(): array
    {
        $index = [];

        foreach (NotificationQuery::getAll() as $notificationPost) {
            if (!($notificationPost instanceof \WP_Post)) {
                continue;
            }

            $notificationId = (int) $notificationPost->ID;
            $clientRules = self::buildFromNotificationId($notificationId);

            if (!empty($clientRules)) {
                $index[(string) $notificationId] = $clientRules;
            }
        }

        return $index;
    }

    /**
     * Extract client-evaluated user rules from notification display rules meta.
     *
     * @param int $notificationId On-page notification post ID.
     * @return array<string, int|string|bool|array<int, string>>|null Null when no client-side user rule applies.
     * @since 2.3.5
     */
    public static function buildFromNotificationId(int $notificationId): ?array
    {
        // Read saved display rules for this notification.
        $displayRules = get_post_meta($notificationId, '_notifal_display_rules_data', true);

        if (!is_array($displayRules)) {
            return null;
        }

        $clientRules = self::resolveClientUserRules($displayRules);

        // Default / unset means server-only auth filtering; skip client payload.
        if ($clientRules === null) {
            return null;
        }

        /**
         * Filter client-side user display rules attached to a notification payload.
         *
         * @since 2.3.5
         * @param array<string, int|string|bool|array<int, string>>|null $clientRules Client rules or null.
         * @param int                                                      $notificationId Notification post ID.
         */
        return apply_filters(
            FilterHooks::ONPAGE_CLIENT_USER_RULES,
            $clientRules,
            $notificationId
        );
    }

    /**
     * Find the first users rule that needs client-side evaluation.
     *
     * @param array<string, mixed> $displayRules Saved display rules meta.
     * @return array<string, int|string|bool|array<int, string>>|null Client payload or null when not applicable.
     * @since 2.3.10
     */
    private static function resolveClientUserRules(array $displayRules): ?array
    {
        $items = DisplayRulesDataNormalizer::extractItems($displayRules);

        foreach ($items as $item) {
            if (($item['type'] ?? '') !== 'users') {
                continue;
            }

            $data = $item['data'] ?? [];
            $userType = isset($data['user_type'])
                ? sanitize_text_field((string) $data['user_type'])
                : DisplayRulesService::USER_LOGIN_STATUS_DEFAULT;
            $visitorType = isset($data['visitor_type'])
                ? sanitize_text_field((string) $data['visitor_type'])
                : 'any';

            $needsLoginCheck = $userType !== '' && $userType !== 'all';
            $needsVisitorCheck = $visitorType !== '' && $visitorType !== 'any';

            if (!$needsLoginCheck && !$needsVisitorCheck) {
                continue;
            }

            $clientRules = [];

            // Login status is mirrored client-side for cache-safe revalidation on cached pages.
            if ($needsLoginCheck) {
                $clientRules['user_type'] = $userType;

                // Role restrictions are Pro-only; expose role payload only when Pro is licensed.
                if ($userType === 'logged_in' && self::isProRoleRestrictionAllowed()) {
                    $clientRules['limit_by_roles'] = (bool) ($data['limit_by_roles'] ?? false);

                    if ($clientRules['limit_by_roles']) {
                        $clientRules['roles'] = self::sanitizeRoles($data['roles'] ?? []);
                    }
                }
            }

            if ($needsVisitorCheck) {
                $clientRules['visitor_type'] = $visitorType;

                if ($visitorType === 'return_visitor') {
                    $clientRules['inactivity_hours'] = self::sanitizeInactivityHours($data);
                }
            }

            return $clientRules;
        }

        return null;
    }

    /**
     * Whether Pro role restrictions may be exposed to the frontend client payload.
     *
     * @return bool True when the Pro plugin allows advanced display rules.
     * @since 2.3.10
     */
    private static function isProRoleRestrictionAllowed(): bool
    {
        return (bool) apply_filters('notifal_pro_multiple_display_rules_allowed', false);
    }

    /**
     * Sanitize role slugs for client-side logged-in role checks.
     *
     * @param mixed $roles Raw role list from saved rule data.
     * @return array<int, string> Sanitized role slugs.
     * @since 2.3.10
     */
    private static function sanitizeRoles($roles): array
    {
        if (!is_array($roles)) {
            return [];
        }

        $sanitized = [];

        foreach ($roles as $role) {
            $roleSlug = sanitize_key((string) $role);

            if ($roleSlug !== '') {
                $sanitized[] = $roleSlug;
            }
        }

        return array_values(array_unique($sanitized));
    }

    /**
     * Sanitize inactivity hours for return-visitor client payload.
     *
     * @param array<string, mixed> $data Users rule data.
     * @return int Sanitized inactivity hours.
     * @since 2.3.10
     */
    private static function sanitizeInactivityHours(array $data): int
    {
        $inactivityHours = isset($data['inactivity_hours'])
            ? absint($data['inactivity_hours'])
            : DisplayRulesService::USER_RETURN_VISITOR_DEFAULT_INACTIVITY_HOURS;

        if ($inactivityHours < DisplayRulesService::USER_RETURN_VISITOR_MIN_INACTIVITY_HOURS) {
            $inactivityHours = DisplayRulesService::USER_RETURN_VISITOR_DEFAULT_INACTIVITY_HOURS;
        }

        return min(
            DisplayRulesService::USER_RETURN_VISITOR_MAX_INACTIVITY_HOURS,
            $inactivityHours
        );
    }
}
