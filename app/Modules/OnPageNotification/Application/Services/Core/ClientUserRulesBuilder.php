<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Core;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\DisplayRulesDataNormalizer;

defined('ABSPATH') || exit;

/**
 * Builds client-side user display rule payload for frontend evaluation.
 *
 * Visit-history filters (new / return / first session) run in the browser
 * so full-page cache stays safe. WordPress login status is still checked server-side.
 *
 * @since 2.3.5
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Application\Services\Core
 */
class ClientUserRulesBuilder
{
    /**
     * Extract client-evaluated user rules from notification display rules meta.
     *
     * @param int $notificationId On-page notification post ID.
     * @return array<string, string>|null Null when no client-side user rule applies.
     * @since 2.3.5
     */
    public static function buildFromNotificationId(int $notificationId): ?array
    {
        // Read saved display rules for this notification.
        $displayRules = get_post_meta($notificationId, '_notifal_display_rules_data', true);

        if (!is_array($displayRules)) {
            return null;
        }

        $visitorType = self::resolveVisitorType($displayRules);

        // Default / unset means server-only auth filtering; skip client payload.
        if ($visitorType === '' || $visitorType === 'any') {
            return null;
        }

        $clientRules = [
            'visitor_type' => $visitorType,
        ];

        /**
         * Filter client-side user display rules attached to a notification payload.
         *
         * @since 2.3.5
         * @param array<string, string>|null $clientRules Client rules or null.
         * @param int                          $notificationId Notification post ID.
         */
        return apply_filters(
            FilterHooks::ONPAGE_CLIENT_USER_RULES,
            $clientRules,
            $notificationId
        );
    }

    /**
     * Find the first users rule with a client-evaluated visitor type.
     *
     * @param array<string, mixed> $displayRules Saved display rules meta.
     * @return string Visitor type slug or empty string.
     * @since 2.3.5
     */
    private static function resolveVisitorType(array $displayRules): string
    {
        $items = DisplayRulesDataNormalizer::extractItems($displayRules);

        foreach ($items as $item) {
            if (($item['type'] ?? '') !== 'users') {
                continue;
            }

            $data = $item['data'] ?? [];
            $visitorType = isset($data['visitor_type'])
                ? sanitize_text_field((string) $data['visitor_type'])
                : 'any';

            if ($visitorType !== '' && $visitorType !== 'any') {
                return $visitorType;
            }
        }

        return '';
    }
}
