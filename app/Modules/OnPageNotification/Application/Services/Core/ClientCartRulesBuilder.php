<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Core;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\DisplayRulesDataNormalizer;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\WooCommerceCartDisplayRulesService;

defined('ABSPATH') || exit;

/**
 * Builds client-side WooCommerce cart display rule payload for frontend evaluation.
 *
 * Cart conditions are re-evaluated in the browser when the cart changes so
 * notifications stay accurate after Ajax add-to-cart without a full page reload.
 *
 * @since 2.3.5
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Application\Services\Core
 */
class ClientCartRulesBuilder
{
    /**
     * Extract client-evaluated cart rules from notification display rules meta.
     *
     * @param int $notificationId On-page notification post ID.
     * @return array<string, mixed>|null Null when no cart rule applies.
     * @since 2.3.5
     */
    public static function buildFromNotificationId(int $notificationId): ?array
    {
        // Cart rules require WooCommerce.
        if (!PluginDetector::isWooCommerceActive()) {
            return null;
        }

        // Read saved display rules for this notification.
        $displayRules = get_post_meta($notificationId, '_notifal_display_rules_data', true);

        if (!is_array($displayRules)) {
            return null;
        }

        $cartItems = self::extractCartRuleItems($displayRules);

        // No cart rule — nothing for the client to evaluate.
        if (empty($cartItems)) {
            return null;
        }

        $combinationLogic = get_post_meta($notificationId, '_notifal_rule_combination_logic', true) ?: 'OR';
        $visibilityMode   = get_post_meta($notificationId, '_notifal_display_rules_visibility_mode', true)
            ?: DisplayRulesDataNormalizer::VISIBILITY_SHOW_IF;

        $sanitizedRules = [];

        foreach ($cartItems as $cartRule) {
            $sanitizedRules[] = self::sanitizeCartRulePayload($cartRule);
        }

        // Single rule: keep legacy flat shape for backward compatibility.
        if (count($sanitizedRules) === 1) {
            $clientRules = $sanitizedRules[0];
        } else {
            $clientRules = [
                'rules' => $sanitizedRules,
                'logic' => in_array($combinationLogic, ['AND', 'OR'], true) ? $combinationLogic : 'OR',
            ];
        }

        $clientRules['visibility_mode'] = DisplayRulesDataNormalizer::sanitizeVisibilityMode((string) $visibilityMode);

        /**
         * Filter client-side cart display rules attached to a notification payload.
         *
         * @since 2.3.5
         * @param array<string, mixed>|null $clientRules    Client rules or null.
         * @param int $notificationId Notification post ID.
         */
        return apply_filters(FilterHooks::ONPAGE_CLIENT_CART_RULES, $clientRules, $notificationId);
    }

    /**
     * Collect cart-type rule data from any supported storage format.
     *
     * @param array<string, mixed> $displayRules Saved display rules meta.
     * @return array<int, array<string, mixed>> Cart rule data arrays.
     * @since 2.3.5
     */
    private static function extractCartRuleItems(array $displayRules): array
    {
        $items     = DisplayRulesDataNormalizer::extractItems($displayRules);
        $cartItems = [];

        foreach ($items as $item) {
            if (($item['type'] ?? '') === WooCommerceCartDisplayRulesService::RULE_TYPE) {
                $cartItems[] = $item['data'] ?? [];
            }
        }

        return $cartItems;
    }

    /**
     * Sanitize cart rule fields for the frontend matcher.
     *
     * @param array<string, mixed> $cartRule Raw cart rule data.
     * @return array<string, mixed> Sanitized client payload.
     * @since 2.3.5
     */
    private static function sanitizeCartRulePayload(array $cartRule): array
    {
        return [
            'condition'    => sanitize_text_field((string) ($cartRule['condition'] ?? 'cart_not_empty')),
            'product_ids'  => array_map('absint', $cartRule['product_ids'] ?? []),
            'category_ids' => array_map('absint', $cartRule['category_ids'] ?? []),
            'operator'     => sanitize_text_field((string) ($cartRule['operator'] ?? 'gt')),
            'value'        => is_numeric($cartRule['value'] ?? null) ? (string) (0 + $cartRule['value']) : '0',
            'coupon_code'  => sanitize_text_field((string) ($cartRule['coupon_code'] ?? '')),
        ];
    }
}
