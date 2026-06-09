<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Rules;

use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\OnPageNotification\Application\Services\Core\ClientCartRulesBuilder;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\CartProductPoolResolver;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Repositories\NotificationQuery;

defined('ABSPATH') || exit;

/**
 * Detects whether any active on-page notification uses WooCommerce cart display rules.
 *
 * Used to skip cart REST refresh and duplicate eligibility requests when cart rules
 * are not configured on any active notification.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services\Rules
 * @since 2.3.7
 * @author Hossein <hossein@notifal.com>
 */
class CartDisplayRulesUsageChecker
{
    /**
     * Object cache key for the site-wide cart rules usage flag.
     *
     * @since 2.3.7
     */
    private const CACHE_KEY = 'notifal_onpage_cart_rules_in_use';

    /**
     * Object cache group for cart rules usage lookups.
     *
     * @since 2.3.7
     */
    private const CACHE_GROUP = 'notifal_onpage';

    /**
     * Determine whether cart context REST refresh and cart-driven refetches are needed.
     *
     * @return bool True when at least one active notification has cart display rules.
     * @since 2.3.7
     * @since 2.3.9 Also checks cart product content source filters.
     */
    public static function anyActiveNotificationUsesCartRules(): bool
    {
        return self::anyActiveNotificationRequiresCartContext();
    }

    /**
     * Determine whether frontend cart context refresh is required.
     *
     * @return bool True when cart display rules or cart product content source is active.
     * @since 2.3.9
     */
    public static function anyActiveNotificationRequiresCartContext(): bool
    {
        // Cart context requires WooCommerce.
        if (!PluginDetector::isWooCommerceActive()) {
            return false;
        }

        // Read cached boolean when available.
        $cached = wp_cache_get(self::CACHE_KEY, self::CACHE_GROUP);
        if ($cached !== false) {
            return (bool) $cached;
        }

        // Scan active notifications once per cache window.
        $inUse = self::scanActiveNotificationsForCartContext();
        wp_cache_set(self::CACHE_KEY, $inUse ? 1 : 0, self::CACHE_GROUP, HOUR_IN_SECONDS);

        return $inUse;
    }

    /**
     * Clear cached cart rules usage after notification display rules change.
     *
     * @return void
     * @since 2.3.7
     */
    public static function clearCache(): void
    {
        wp_cache_delete(self::CACHE_KEY, self::CACHE_GROUP);
    }

    /**
     * Scan active notifications for cart display rules or cart product content source.
     *
     * @return bool
     * @since 2.3.7
     * @since 2.3.9 Includes cart product content source filters.
     */
    private static function scanActiveNotificationsForCartContext(): bool
    {
        foreach (NotificationQuery::getAll() as $notification) {
            if (!($notification instanceof \WP_Post)) {
                continue;
            }

            $notificationId = (int) $notification->ID;

            if (self::notificationHasCartDisplayRules($notificationId)) {
                return true;
            }

            if (self::notificationUsesCartProductSource($notificationId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether one notification uses cart product content source filters.
     *
     * @param int $notificationId Notification post ID.
     * @return bool
     * @since 2.3.9
     */
    private static function notificationUsesCartProductSource(int $notificationId): bool
    {
        if ($notificationId <= 0) {
            return false;
        }

        $settings = get_post_meta($notificationId, '_notifal_content_source_settings', true);

        if (!is_array($settings)) {
            return false;
        }

        return CartProductPoolResolver::settingsContainCartFilter($settings);
    }

    /**
     * Check whether one notification stores cart display rules in meta.
     *
     * @param int $notificationId Notification post ID.
     * @return bool
     * @since 2.3.7
     */
    private static function notificationHasCartDisplayRules(int $notificationId): bool
    {
        if ($notificationId <= 0) {
            return false;
        }

        // Reuse client cart rule extraction — same meta shape as server eligibility.
        return ClientCartRulesBuilder::buildFromNotificationId($notificationId) !== null;
    }
}
