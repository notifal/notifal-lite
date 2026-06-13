<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Core;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\DisplayRulesDataNormalizer;

defined('ABSPATH') || exit;

/**
 * Builds client-side page targeting payload for frontend display rule evaluation.
 *
 * Page and post-type rules are re-checked in the browser so exit-intent and
 * cart refetch flows cannot show a notification on the wrong page.
 *
 * @since 2.3.10
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Application\Services\Core
 */
class ClientPageRulesBuilder
{
    /**
     * Rule types that target the current page or post type.
     *
     * @var array<int, string>
     */
    private const PAGE_RULE_TYPES = [
        'post_type',
        'pages',
        'posts',
        'products',
    ];

    /**
     * Extract client-evaluated page rules from notification display rules meta.
     *
     * @param int $notificationId On-page notification post ID.
     * @return array<string, mixed>|null Null when no page rule applies.
     * @since 2.3.10
     */
    public static function buildFromNotificationId(int $notificationId): ?array
    {
        // Read saved display rules for this notification.
        $displayRules = get_post_meta($notificationId, '_notifal_display_rules_data', true);

        if (!is_array($displayRules)) {
            return null;
        }

        // Collect page-targeting rule payloads from the saved items list.
        $pageItems = self::extractPageRuleItems($displayRules);

        // No page rule means the client does not need to re-evaluate page targeting.
        if ($pageItems === []) {
            return null;
        }

        // Read saved combination and visibility settings for the notification.
        $combinationLogic = get_post_meta($notificationId, '_notifal_rule_combination_logic', true) ?: 'OR';
        $visibilityMode   = get_post_meta($notificationId, '_notifal_display_rules_visibility_mode', true)
            ?: DisplayRulesDataNormalizer::VISIBILITY_SHOW_IF;

        $sanitizedRules = [];

        // Sanitize each page rule before sending it to the browser matcher.
        foreach ($pageItems as $pageRule) {
            $sanitizedRules[] = self::sanitizePageRulePayload($pageRule);
        }

        // Build the client payload shape expected by the frontend matcher.
        $clientRules = [
            'rules'            => $sanitizedRules,
            'logic'            => in_array($combinationLogic, ['AND', 'OR'], true) ? $combinationLogic : 'OR',
            'visibility_mode'  => DisplayRulesDataNormalizer::sanitizeVisibilityMode((string) $visibilityMode),
        ];

        /**
         * Filter client-side page display rules attached to a notification payload.
         *
         * @since 2.3.10
         * @param array<string, mixed>|null $clientRules    Client rules or null.
         * @param int $notificationId Notification post ID.
         */
        return apply_filters(FilterHooks::ONPAGE_CLIENT_PAGE_RULES, $clientRules, $notificationId);
    }

    /**
     * Collect page-targeting rule data from any supported storage format.
     *
     * @param array<string, mixed> $displayRules Saved display rules meta.
     * @return array<int, array<string, mixed>> Page rule arrays.
     * @since 2.3.10
     */
    private static function extractPageRuleItems(array $displayRules): array
    {
        $items     = DisplayRulesDataNormalizer::extractItems($displayRules);
        $pageItems = [];

        // Keep only rule types that depend on the current page or post type.
        foreach ($items as $item) {
            $ruleType = (string) ($item['type'] ?? '');

            if (!in_array($ruleType, self::PAGE_RULE_TYPES, true)) {
                continue;
            }

            $pageItems[] = [
                'type' => $ruleType,
                'data' => is_array($item['data'] ?? null) ? $item['data'] : [],
            ];
        }

        return $pageItems;
    }

    /**
     * Sanitize one page rule for the frontend matcher.
     *
     * @param array<string, mixed> $pageRule Raw page rule item.
     * @return array<string, mixed> Sanitized client payload.
     * @since 2.3.10
     */
    private static function sanitizePageRulePayload(array $pageRule): array
    {
        $ruleType = sanitize_key((string) ($pageRule['type'] ?? 'post_type'));
        $ruleData = is_array($pageRule['data'] ?? null) ? $pageRule['data'] : [];

        // Shared post-type rule fields.
        $sanitized = [
            'type'              => $ruleType,
            'visibility'        => sanitize_key((string) ($ruleData['visibility'] ?? 'all')),
            'post_types'        => array_map('sanitize_key', (array) ($ruleData['post_types'] ?? [])),
            'items_visibility'  => sanitize_key((string) ($ruleData['items_visibility'] ?? 'all')),
            'post_items'        => array_map('absint', (array) ($ruleData['post_items'] ?? [])),
            'mode'              => sanitize_key((string) ($ruleData['mode'] ?? 'include')),
            'targets'           => array_map('absint', (array) ($ruleData['targets'] ?? [])),
        ];

        return $sanitized;
    }
}
