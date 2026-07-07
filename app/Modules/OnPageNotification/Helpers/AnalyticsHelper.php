<?php

namespace Notifal\Modules\OnPageNotification\Helpers;

use Notifal\Infrastructure\WordPress\ActivationPopup\Domain\ActivationPopup;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Analytics Helper Class
 *
 * Provides common analytics utility functions used across free and pro analytics services.
 * Handles date range calculations, time series data processing, filtering logic, and data normalization
 * for consistent analytics reporting across the application.
 *
 * @package Notifal\Modules\OnPageNotification\Helpers
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class AnalyticsHelper
{
    /**
     * Calculate date range based on filters.
     *
     * @param array $filters Analytics filters
     * @return array Date range with start and end dates
     * @since 2.0.0
     * @since 2.3.0 Added `all_time` preset (install / earliest analytics through today).
     */
    public static function getDateRange(array $filters): array
    {
        // Read the selected preset from filters (defaults to last 30 days).
        $range = $filters["date_range"] ?? "last_30_days";
        // Use site-local "today" as the inclusive end date for rolling ranges.
        $today = current_time("Y-m-d");

        // All time: from first install / earliest analytics through today.
        if ($range === "all_time") {
            return [
                "start" => self::getAllTimeStartDate(),
                "end"   => $today,
            ];
        }

        // Define date range configurations
        $rangeConfigs = [
            "today" => ["days" => 0, "same_day" => true],
            "yesterday" => ["days" => 1, "same_day" => true],
            "last_7_days" => ["days" => 7, "same_day" => false],
            "last_30_days" => ["days" => 30, "same_day" => false],
            "last_90_days" => ["days" => 90, "same_day" => false],
        ];

        $config = $rangeConfigs[$range] ?? $rangeConfigs["last_30_days"];

        if ($config["same_day"]) {
            $targetDate = date("Y-m-d", strtotime("-{$config["days"]} day", strtotime($today)));
            return ["start" => $targetDate, "end" => $targetDate];
        }

        return [
            "start" => date("Y-m-d", strtotime("-{$config["days"]} days", strtotime($today))),
            "end" => $today
        ];
    }

    /**
     * Resolve the start date for the "All Time" analytics preset.
     *
     * Uses the earliest of: plugin activation time, first daily stat, or first tracking event.
     * Sites without stored history fall back to today. Result is filterable for extensions.
     *
     * @return string Start date in Y-m-d format.
     * @since 2.3.0
     */
    public static function getAllTimeStartDate(): string
    {
        // Cache per request to avoid repeated MIN() queries on the same page load.
        static $cachedStartDate = null;

        if ($cachedStartDate !== null) {
            return $cachedStartDate;
        }

        $today = current_time("Y-m-d");
        $candidates = [];

        // First activation on this site (fresh installs and popup completion).
        $activationTime = get_option(ActivationPopup::ACTIVATION_TIME_KEY);
        if ($activationTime) {
            $candidates[] = wp_date("Y-m-d", (int) $activationTime);
        }

        global $wpdb;

        // Earliest row in aggregated daily stats.
        $dailyStatsTable = $wpdb->prefix . "notifal_onpage_daily_stats";
        if ($wpdb->get_var("SHOW TABLES LIKE '{$dailyStatsTable}'") === $dailyStatsTable) {
            $earliestDaily = $wpdb->get_var("SELECT MIN(date) FROM {$dailyStatsTable}");
            if (is_string($earliestDaily) && $earliestDaily !== "") {
                $candidates[] = $earliestDaily;
            }
        }

        // Earliest raw tracking event (covers sites with data before daily aggregation).
        $trackingTable = $wpdb->prefix . "notifal_onpage_tracking";
        if ($wpdb->get_var("SHOW TABLES LIKE '{$trackingTable}'") === $trackingTable) {
            $earliestTracking = $wpdb->get_var(
                "SELECT MIN(DATE(COALESCE(NULLIF(timestamp, '0000-00-00 00:00:00'), created_at))) FROM {$trackingTable}"
            );
            if (is_string($earliestTracking) && $earliestTracking !== "") {
                $candidates[] = $earliestTracking;
            }
        }

        $startDate = !empty($candidates) ? min($candidates) : $today;

        // Never allow a future start date relative to today.
        if ($startDate > $today) {
            $startDate = $today;
        }

        /**
         * Filters the resolved start date for the all_time analytics range.
         *
         * @param string $startDate Start date (Y-m-d).
         * @since 2.3.0
         */
        $cachedStartDate = (string) apply_filters(FilterHooks::ONPAGE_ANALYTICS_ALL_TIME_START_DATE, $startDate);

        return $cachedStartDate;
    }

    /**
     * Get date range label for display.
     *
     * @param array $filters Analytics filters
     * @return string Date range label
     * @since 2.0.0
     * @since 2.3.0 Added `all_time` label.
     */
    public static function getDateRangeLabel(array $filters): string
    {
        $range = $filters["date_range"] ?? "last_30_days";

        $labels = [
            "today" => __("Today", "notifal"),
            "yesterday" => __("Yesterday", "notifal"),
            "last_7_days" => __("Last 7 Days", "notifal"),
            "last_30_days" => __("Last 30 Days", "notifal"),
            "last_90_days" => __("Last 90 Days", "notifal"),
            "all_time" => __("All Time", "notifal"),
        ];

        return $labels[$range] ?? $labels["last_30_days"];
    }

    /**
     * Fill missing dates in time series data with zero values.
     *
     * @param array $data Existing data with date and value
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array Complete data array with all dates
     * @since 2.0.0
     */
    public static function fillMissingDates(array $data, string $startDate, string $endDate): array
    {
        // Validate date range
        if ($startDate > $endDate) {
            return [];
        }

        // Create array indexed by date for fast lookup using array_column for better performance
        $dataByDate = [];
        if (!empty($data)) {
            $dates = array_column($data, 'date');
            $values = array_column($data, 'value');
            $dataByDate = array_combine($dates, array_map('floatval', $values));
        }

        // Generate all dates in range using DateTimeImmutable for better performance
        $result = [];
        $currentDate = new \DateTimeImmutable($startDate);
        $endDateTime = new \DateTimeImmutable($endDate);
        $interval = new \DateInterval('P1D');

        while ($currentDate <= $endDateTime) {
            $dateString = $currentDate->format('Y-m-d');
            $result[] = [
                'date' => $dateString,
                'value' => $dataByDate[$dateString] ?? 0.0
            ];
            $currentDate = $currentDate->add($interval);
        }

        return $result;
    }

    /**
     * Get filtered notification IDs based on filters.
     *
     * Resolves which OnPage notifications are included in analytics using AND logic.
     * A specific `notification_id` limits to that post. When `campaign_id` is set,
     * only notifications assigned to that campaign (`_notifal_campaign_id`) are included.
     * When `status` is set, only matching post statuses are included. If `notification_id`
     * and `campaign_id` are both set, the notification must belong to that campaign.
     *
     * @param array $filters Analytics filters (`notification_id`, `campaign_id`, `status`, …).
     * @return array<int> Notification post IDs.
     * @since 2.0.0
     * @since 2.2.0 Added `campaign_id` filtering and validation against `notification_id`.
     */
    public static function getFilteredNotificationIds(array $filters): array
    {
        $status = isset($filters['status']) && $filters['status'] !== '' ? $filters['status'] : '';

        $campaignId = isset($filters['campaign_id']) ? (int) $filters['campaign_id'] : 0;

        $notificationId = isset($filters['notification_id']) ? (int) $filters['notification_id'] : 0;

        if ($notificationId > 0) {
            // AND logic: when a campaign is selected, the notification must belong to that campaign.
            if ($campaignId > 0) {
                $assignedCampaignId = (int) get_post_meta($notificationId, '_notifal_campaign_id', true);
                if ($assignedCampaignId !== $campaignId) {
                    return apply_filters(FilterHooks::ONPAGE_ANALYTICS_FILTERED_NOTIFICATION_IDS, [], $filters);
                }
            }

            // AND logic: when a status is selected, the notification post must match it.
            if ($status !== '') {
                $postStatus = get_post_status($notificationId);
                if ($postStatus !== $status) {
                    return apply_filters(FilterHooks::ONPAGE_ANALYTICS_FILTERED_NOTIFICATION_IDS, [], $filters);
                }
            }

            $ids = [ $notificationId ];

            return apply_filters(FilterHooks::ONPAGE_ANALYTICS_FILTERED_NOTIFICATION_IDS, $ids, $filters);
        }

        $args = [
            'post_type' => 'notifal_onpage_notif',
            'post_status' => [ 'publish', 'draft' ],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        if ($status !== '') {
            $args['post_status'] = $status;
        }

        if ($campaignId > 0) {
            // AND logic: limit notifications to those assigned to the selected campaign.
            $args['meta_query'] = [
                [
                    'key'     => '_notifal_campaign_id',
                    'value'   => $campaignId,
                    'compare' => '=',
                ],
            ];
        }

        $ids = get_posts($args);
        $ids = is_array($ids) ? array_map('absint', $ids) : [];

        return apply_filters(FilterHooks::ONPAGE_ANALYTICS_FILTERED_NOTIFICATION_IDS, $ids, $filters);
    }

    /**
     * Option key storing the last analytics queue processing timestamp.
     *
     * @return string WordPress option name.
     * @since 2.3.0
     */
    public static function getLastProcessingOptionKey(): string
    {
        // Shared option used by manual refresh and scheduled cron processing.
        return 'notifal_onpage_analytics_last_processed';
    }

    /**
     * Cron hook name for OnPage analytics event processing.
     *
     * @return string WordPress cron hook.
     * @since 2.3.0
     */
    public static function getProcessEventsCronHook(): string
    {
        // Matches CronService::CRON_HOOK_PROCESS for next-run calculations.
        return 'notifal_onpage_process_events';
    }

    /**
     * Persist the latest analytics processing time.
     *
     * @return void
     * @since 2.3.0
     */
    public static function recordLastProcessingTime(): void
    {
        // Store site-local timestamp without autoload to keep options table lean.
        update_option(self::getLastProcessingOptionKey(), current_time('timestamp'), false);
    }

    /**
     * Build dashboard "Updated at / Next update" metadata.
     *
     * @param int|null $lastRunTimestamp Optional override for the last processing timestamp.
     * @return array Last update information for the analytics dashboard.
     * @since 2.3.0
     */
    public static function buildLastUpdateInfo(?int $lastRunTimestamp = null): array
    {
        // Resolve the last run from the stored option when no explicit timestamp is passed.
        if ($lastRunTimestamp === null) {
            $lastRunTimestamp = (int) get_option(self::getLastProcessingOptionKey(), 0);
        }

        // Fall back to the estimated previous cron run when nothing has been recorded yet.
        if ($lastRunTimestamp <= 0) {
            $nextScheduled = wp_next_scheduled(self::getProcessEventsCronHook());
            if ($nextScheduled) {
                $lastRunTimestamp = (int) ($nextScheduled - (5 * HOUR_IN_SECONDS));
            } else {
                $lastRunTimestamp = (int) current_time('timestamp');
            }
        }

        // Read the next scheduled cron run for the "Next update" label.
        $nextScheduled = wp_next_scheduled(self::getProcessEventsCronHook());
        $unknownLabel  = __('Unknown', 'notifal');

        return [
            'timestamp'           => $lastRunTimestamp,
            'formatted'           => wp_date(get_option('date_format') . ' ' . get_option('time_format'), $lastRunTimestamp),
            'human_diff'          => human_time_diff($lastRunTimestamp, current_time('timestamp')) . ' ' . __('ago', 'notifal'),
            'next_update'         => $nextScheduled
                ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $nextScheduled)
                : $unknownLabel,
            'next_update_human'   => $nextScheduled
                ? human_time_diff(current_time('timestamp'), $nextScheduled)
                : $unknownLabel,
        ];
    }

    /**
     * Sanitize optional button click metadata from tracking payloads.
     *
     * @param array $trackingData Raw tracking data from frontend or queue.
     * @return array Sanitized button_id, button_action, and button_text keys.
     * @since 2.3.11
     */
    public static function sanitizeButtonClickMeta(array $trackingData): array
    {
        // Default empty values when metadata is absent.
        $buttonMeta = [
            'button_id'     => '',
            'button_action' => '',
            'button_text'   => '',
        ];

        // Copy sanitized values when present in the incoming payload.
        if (isset($trackingData['button_id'])) {
            $buttonMeta['button_id'] = substr(sanitize_text_field((string) $trackingData['button_id']), 0, 100);
        }

        if (isset($trackingData['button_action'])) {
            $buttonMeta['button_action'] = substr(sanitize_text_field((string) $trackingData['button_action']), 0, 50);
        }

        if (isset($trackingData['button_text'])) {
            $buttonMeta['button_text'] = substr(sanitize_text_field((string) $trackingData['button_text']), 0, 255);
        }

        return $buttonMeta;
    }

    /**
     * Determine whether button click metadata is complete enough to aggregate.
     *
     * @param array $buttonMeta Sanitized button metadata.
     * @return bool True when at least button id or action is available.
     * @since 2.3.11
     */
    public static function hasButtonClickMeta(array $buttonMeta): bool
    {
        // Require a stable identifier or action type so rows remain distinguishable.
        return ($buttonMeta['button_id'] ?? '') !== '' || ($buttonMeta['button_action'] ?? '') !== '';
    }

    /**
     * Default WooCommerce order status slugs treated as paid for conversion analytics.
     *
     * @var string[]
     * @since 2.4.2
     */
    private static $defaultPaidWooOrderStatuses = ['processing', 'completed'];

    /**
     * Return WooCommerce order status slugs that count as paid for conversion tracking and revenue.
     *
     * Filterable via {@see FilterHooks::ONPAGE_CONVERSION_PAID_ORDER_STATUSES}.
     *
     * @return string[] Sanitized unique status slugs without the `wc-` prefix.
     * @since 2.4.2
     * @author Hossein <hossein@notifal.com>
     */
    public static function getPaidWooCommerceOrderStatuses(): array
    {
        // Allow developers to register custom gateway or store-specific paid statuses.
        $statuses = apply_filters(
            FilterHooks::ONPAGE_CONVERSION_PAID_ORDER_STATUSES,
            self::$defaultPaidWooOrderStatuses
        );

        if (!is_array($statuses)) {
            $statuses = self::$defaultPaidWooOrderStatuses;
        }

        $sanitized = [];

        foreach ($statuses as $status) {
            // Normalize each slug the same way WooCommerce stores order status keys.
            $slug = sanitize_key((string) $status);

            if ($slug !== '') {
                $sanitized[$slug] = $slug;
            }
        }

        if (empty($sanitized)) {
            return self::$defaultPaidWooOrderStatuses;
        }

        return array_values($sanitized);
    }

    /**
     * Whether a WooCommerce order status slug counts as paid for Notifal conversion analytics.
     *
     * @param string $status Order status slug (without `wc-` prefix).
     * @return bool True when the status is in the paid list.
     * @since 2.4.2
     * @author Hossein <hossein@notifal.com>
     */
    public static function isPaidWooCommerceOrderStatus(string $status): bool
    {
        return in_array(sanitize_key($status), self::getPaidWooCommerceOrderStatuses(), true);
    }

    /**
     * Whether a WooCommerce order has reached a paid status for conversion analytics.
     *
     * @param int $orderId WooCommerce order ID.
     * @return bool True when the order exists and its status is paid.
     * @since 2.4.2
     * @author Hossein <hossein@notifal.com>
     */
    public static function isPaidWooCommerceOrder(int $orderId): bool
    {
        if ($orderId <= 0 || !function_exists('wc_get_order')) {
            return false;
        }

        $order = wc_get_order($orderId);

        if (!$order instanceof \WC_Order) {
            return false;
        }

        return self::isPaidWooCommerceOrderStatus((string) $order->get_status());
    }

    /**
     * Resolve a WooCommerce order line subtotal from a checkout product map.
     *
     * Handles parent/variation ID differences between stored clicks and order lines.
     *
     * @param int   $productId   Product or variation ID from attribution data.
     * @param array $productData Map keyed by product ID with an item_total key.
     * @return float Line subtotal (price × quantity) or 0 when not found.
     * @since 2.4.2
     * @author Hossein <hossein@notifal.com>
     */
    public static function resolveOrderLineTotalFromProductData(int $productId, array $productData): float
    {
        // Reject invalid product IDs or empty order line maps.
        if ($productId <= 0 || empty($productData)) {
            return 0.0;
        }

        // Direct match against the order line product or variation ID.
        if (isset($productData[$productId]['item_total'])) {
            return (float) $productData[$productId]['item_total'];
        }

        if (!function_exists('wc_get_product')) {
            return 0.0;
        }

        // Attribution may reference a variation while the click stored the parent ID.
        $product = wc_get_product($productId);

        if ($product && $product->is_type('variation')) {
            $parentId = (int) $product->get_parent_id();

            if ($parentId > 0 && isset($productData[$parentId]['item_total'])) {
                return (float) $productData[$parentId]['item_total'];
            }
        }

        // Attribution may reference the parent while the order line uses a variation ID.
        foreach ($productData as $orderProductId => $data) {
            $orderProduct = wc_get_product((int) $orderProductId);

            if (!$orderProduct || !$orderProduct->is_type('variation')) {
                continue;
            }

            if ((int) $orderProduct->get_parent_id() === $productId) {
                return (float) ($data['item_total'] ?? 0);
            }
        }

        return 0.0;
    }

    /**
     * Build a map of WooCommerce order line subtotals keyed by product and variation IDs.
     *
     * @param int $orderId WooCommerce order ID.
     * @return array<int, float> Map of product ID to line subtotal.
     * @since 2.4.2
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildWooOrderLineTotalsMap(int $orderId): array
    {
        // Default to an empty map when WooCommerce is unavailable.
        $lineTotals = [];

        if ($orderId <= 0 || !function_exists('wc_get_order')) {
            return $lineTotals;
        }

        // Load the order object from WooCommerce.
        $order = wc_get_order($orderId);

        if (!$order) {
            return $lineTotals;
        }

        // Walk each product line and index totals by variation/simple and parent IDs.
        foreach ($order->get_items() as $item) {
            if (!($item instanceof \WC_Order_Item_Product)) {
                continue;
            }

            $productId   = (int) $item->get_product_id();
            $variationId = (int) $item->get_variation_id();
            $resolvedId  = $variationId > 0 ? $variationId : $productId;
            $lineTotal   = (float) $item->get_total();

            if ($resolvedId <= 0) {
                continue;
            }

            $lineTotals[$resolvedId] = $lineTotal;

            if ($productId > 0) {
                $lineTotals[$productId] = $lineTotal;
            }
        }

        return $lineTotals;
    }

    /**
     * Resolve an order line subtotal for a product or variation ID.
     *
     * @param int              $productId  Product or variation ID from attribution data.
     * @param array<int,float> $lineTotals Map from buildWooOrderLineTotalsMap().
     * @return float Line subtotal or 0 when not found.
     * @since 2.4.2
     * @author Hossein <hossein@notifal.com>
     */
    public static function resolveWooOrderLineTotalForProductId(int $productId, array $lineTotals): float
    {
        // Reject invalid IDs or empty lookup maps.
        if ($productId <= 0 || empty($lineTotals)) {
            return 0.0;
        }

        // Direct match against the indexed order line ID.
        if (isset($lineTotals[$productId])) {
            return (float) $lineTotals[$productId];
        }

        if (!function_exists('wc_get_product')) {
            return 0.0;
        }

        // Match a stored parent ID against a variation line item.
        $product = wc_get_product($productId);

        if ($product && $product->is_type('variation')) {
            $parentId = (int) $product->get_parent_id();

            if ($parentId > 0 && isset($lineTotals[$parentId])) {
                return (float) $lineTotals[$parentId];
            }
        }

        // Match a stored parent ID against any variation on the order.
        foreach ($lineTotals as $orderProductId => $lineTotal) {
            $orderProduct = wc_get_product((int) $orderProductId);

            if (!$orderProduct || !$orderProduct->is_type('variation')) {
                continue;
            }

            if ((int) $orderProduct->get_parent_id() === $productId) {
                return (float) $lineTotal;
            }
        }

        return 0.0;
    }

    /**
     * Determine whether an attribution row represents a clicked product conversion.
     *
     * Cookie and fallback influence rows without a product ID must not receive clicked revenue.
     * Legacy fallback rows that incorrectly stored an order product ID are still eligible for display backfill.
     *
     * @param array $row Attribution row.
     * @return bool True when the row is a verified or recoverable product-click attribution.
     * @since 2.4.2
     * @author Hossein <hossein@notifal.com>
     */
    public static function isClickedProductAttributionRow(array $row): bool
    {
        // Rows without a product ID are influence-only attribution.
        $productId = (int) ($row['product_id'] ?? 0);

        if ($productId <= 0) {
            return false;
        }

        $attributionType = (string) ($row['attribution_type'] ?? '');

        // Cookie/session influence rows never qualify as clicked revenue.
        if ($attributionType === 'pending_cookie') {
            return false;
        }

        return true;
    }

    /**
     * Backfill product revenue on attribution rows from WooCommerce order line items.
     *
     * Used when conversions were stored with product_id but zero product_revenue
     * (for example after cookie fallback or delayed payment reconciliation).
     *
     * @param int   $orderId WooCommerce order ID or EDD payment ID.
     * @param array $rows    Attribution rows.
     * @return array Rows with display revenue when applicable.
     * @since 2.4.2
     * @author Hossein <hossein@notifal.com>
     */
    public static function backfillAttributionProductRevenue(int $orderId, array $rows): array
    {
        // Skip when there is nothing to enrich or the order ID is invalid.
        if (empty($rows) || $orderId <= 0) {
            return $rows;
        }

        // Build a map of order line subtotals keyed by product and variation IDs.
        $lineTotals = self::buildWooOrderLineTotalsMap($orderId);

        if (empty($lineTotals)) {
            return $rows;
        }

        foreach ($rows as &$row) {
            // Only enrich rows that represent a real clicked product attribution.
            if (!self::isClickedProductAttributionRow($row)) {
                continue;
            }

            $productId      = (int) ($row['product_id'] ?? 0);
            $productRevenue = (float) ($row['product_revenue'] ?? 0);

            // Keep rows that already have a stored clicked revenue value.
            if ($productId <= 0 || $productRevenue > 0) {
                continue;
            }

            // Resolve line subtotal (price × quantity) from the order items.
            $resolvedRevenue = self::resolveWooOrderLineTotalForProductId($productId, $lineTotals);

            if ($resolvedRevenue > 0) {
                $row['product_revenue'] = $resolvedRevenue;
            }
        }
        unset($row);

        return $rows;
    }
}
