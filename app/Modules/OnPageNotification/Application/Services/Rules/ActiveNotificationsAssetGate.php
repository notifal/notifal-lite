<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Rules;

use Notifal\Modules\OnPageNotification\Application\Services\Core\ClientCartRulesBuilder;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\DisplayRulesDataNormalizer;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\DisplayRulesService;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Repositories\NotificationQuery;

defined('ABSPATH') || exit;

/**
 * Lightweight gate for OnPage frontend asset loading.
 *
 * Decides whether visitor-facing CSS/JS should load on the current request
 * without running the full eligibility pipeline (frequency, schedule, etc.).
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ActiveNotificationsAssetGate
{
    /**
     * Object cache key for the site-wide "has active notifications" flag.
     *
     * @since 3.0.0
     */
    private const CACHE_KEY_HAS_ACTIVE = 'notifal_onpage_has_active_notifications';

    /**
     * Object cache group for OnPage asset gate lookups.
     *
     * @since 3.0.0
     */
    private const CACHE_GROUP = 'notifal_onpage';

    /**
     * Whether at least one published and enabled OnPage notification exists.
     *
     * @return bool
     * @since 3.0.0
     */
    public static function hasActiveNotifications(): bool
    {
        // Prefer object cache to avoid repeating get_posts on every page view.
        $cached = wp_cache_get(self::CACHE_KEY_HAS_ACTIVE, self::CACHE_GROUP);
        if ($cached !== false) {
            return (bool) $cached;
        }

        // Query only IDs for a cheap existence check.
        $ids = get_posts(
            [
                'post_type'              => 'notifal_onpage_notif',
                'post_status'            => 'publish',
                'posts_per_page'         => 1,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query'             => [
                    [
                        'key'     => '_notifal_notif_enabled',
                        'value'   => '1',
                        'compare' => '=',
                    ],
                ],
            ]
        );

        $hasActive = !empty($ids);
        wp_cache_set(self::CACHE_KEY_HAS_ACTIVE, $hasActive ? 1 : 0, self::CACHE_GROUP, HOUR_IN_SECONDS);

        return $hasActive;
    }

    /**
     * Whether OnPage assets should load for the current frontend page context.
     *
     * Returns true when at least one active notification has no display rules
     * (show everywhere) or its display rules match the current page context.
     *
     * @param array $context Page context from FrontendAssetsRegistrar.
     * @return bool
     * @since 3.0.0
     */
    public static function shouldLoadAssetsForCurrentPage(array $context): bool
    {
        // No active notifications means nothing to render for visitors.
        if (!self::hasActiveNotifications()) {
            return false;
        }

        $pageId = isset($context['page_id']) ? absint($context['page_id']) : 0;
        $pageId = $pageId > 0 ? $pageId : null;

        foreach (NotificationQuery::getAll() as $notification) {
            if (!($notification instanceof \WP_Post)) {
                continue;
            }

            // Load meta used by display-rule evaluation only.
            $displayRules = get_post_meta($notification->ID, '_notifal_display_rules_data', true);
            $combinationLogic = get_post_meta($notification->ID, '_notifal_rule_combination_logic', true) ?: 'OR';
            $visibilityMode = get_post_meta($notification->ID, '_notifal_display_rules_visibility_mode', true) ?: 'show_if';

            // Empty rules: notification can appear on any public page.
            if (!is_array($displayRules) || !DisplayRulesDataNormalizer::hasActiveRules($displayRules)) {
                return true;
            }

            // Cart rules can flip true after Ajax cart updates without a full reload.
            // Keep the OnPage bundle available when an active notification uses them.
            if (ClientCartRulesBuilder::buildFromNotificationId((int) $notification->ID) !== null) {
                return true;
            }

            // Reuse the same display-rule engine the eligibility path uses.
            if (
                DisplayRulesService::shouldDisplay(
                    $displayRules,
                    (string) $combinationLogic,
                    $pageId,
                    $context,
                    (string) $visibilityMode
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear cached "has active notifications" flag after activation changes.
     *
     * @return void
     * @since 3.0.0
     */
    public static function clearCache(): void
    {
        wp_cache_delete(self::CACHE_KEY_HAS_ACTIVE, self::CACHE_GROUP);
    }
}
