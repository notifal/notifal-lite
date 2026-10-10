<?php

namespace Notifal\Modules\OnPageNotification\Helpers;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Database\MigrationService;
use Notifal\Modules\SetupWizard\Domain\Constants\SetupWizardOptions;

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

        // First activation on this site (Setup Wizard marker, with legacy fallback).
        $activationTime = get_option(SetupWizardOptions::FIRST_INSTALL, false);
        if (!$activationTime) {
            $activationTime = get_option('notifal_activation_time', false);
        }
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

    /**
     * Whether this site should expose revenue and order-influence analytics.
     *
     * Revenue metrics require WooCommerce or Easy Digital Downloads so amounts map to real orders.
     *
     * @return bool
     * @since 3.0.0
     */
    public static function supportsRevenueAnalytics(): bool
    {
        // Detect supported ecommerce plugins on the site.
        $supports = PluginDetector::isWooCommerceActive() || PluginDetector::isEDDActive();

        /**
         * Filters whether revenue analytics are enabled for the current site.
         *
         * @param bool $supports Default detection result.
         * @since 3.0.0
         */
        return (bool) apply_filters(FilterHooks::ONPAGE_ANALYTICS_SUPPORTS_REVENUE, $supports);
    }

    /**
     * Whether influenced store orders are included in conversion totals.
     *
     * True on WooCommerce and EDD sites. Form submissions are counted separately via {@see countsFormConversions()}.
     *
     * @return bool
     * @since 3.0.0
     */
    public static function countsOrderConversions(): bool
    {
        // Order conversions require a supported ecommerce plugin (same gate as revenue analytics).
        return self::supportsRevenueAnalytics();
    }

    /**
     * Whether on-notification form submissions are included in conversion totals.
     *
     * Always true: all sites count form fills. On ecommerce, totals add orders plus forms (separate data sources).
     *
     * @return bool
     * @since 3.0.0
     */
    public static function countsFormConversions(): bool
    {
        // Form submissions are a conversion signal on every site type.
        return true;
    }

    /**
     * Whether conversions are attributed to store orders (ecommerce context).
     *
     * @return bool
     * @since 3.0.0
     */
    public static function usesOrderBasedConversions(): bool
    {
        // Backward-compatible alias for order inclusion in conversion metrics.
        return self::countsOrderConversions();
    }

    /**
     * Whether conversions are attributed only to on-notification form submissions (non-ecommerce).
     *
     * False on ecommerce sites, where orders and forms are both counted.
     *
     * @return bool
     * @since 3.0.0
     */
    public static function usesFormBasedConversions(): bool
    {
        // Form-only conversion mode applies when orders are not part of the metric.
        return self::countsFormConversions() && ! self::countsOrderConversions();
    }

    /**
     * Admin-facing labels and helper copy for conversion metrics (context-aware).
     *
     * @return array{
     *     conversions_label: string,
     *     conversions_subtitle: string,
     *     conversion_rate_subtitle: string,
     *     conversions_tooltip: string,
     *     conversion_rate_label: string
     * }
     * @since 3.0.0
     */
    public static function getConversionContextLabels(): array
    {
        // Ecommerce sites: conversions combine influenced orders and notification form submissions.
        if (self::countsOrderConversions()) {
            return [
                'conversions_label'          => __('Conversions', 'notifal'),
                'conversions_subtitle'       => __('Influenced orders and form submissions', 'notifal'),
                'conversion_rate_subtitle'   => __('Unique visitors who ordered or submitted a form', 'notifal'),
                'conversions_tooltip'        => __('Counts paid orders attributed after a notification click, plus successful embedded Notifal form submissions on the same notification. Each order and each form submit counts once.', 'notifal'),
                'conversion_rate_label'      => __('Conversion Rate', 'notifal'),
            ];
        }

        // Lead and content sites: conversions mean form submissions on the notification.
        return [
            'conversions_label'          => __('Conversions', 'notifal'),
            'conversions_subtitle'       => __('Form submissions from this notification', 'notifal'),
            'conversion_rate_subtitle'   => __('Unique visitors who submitted a form after seeing the notification', 'notifal'),
            'conversions_tooltip'        => __('Counts when a visitor sees a notification and successfully submits an embedded Notifal form.', 'notifal'),
            'conversion_rate_label'      => __('Conversion Rate', 'notifal'),
        ];
    }

    /**
     * Total conversions for a notification in a date range (orders, forms, or both).
     *
     * Non-ecommerce: form submission rows only ({@see countFormSubmissionsForNotification()}).
     * Ecommerce: influenced order rows in the conversions table plus form submission rows (additive; no double count
     * because orders and forms use separate storage).
     *
     * @param int    $notificationId OnPage notification post ID.
     * @param string $startDate      Start date (Y-m-d).
     * @param string $endDate        End date (Y-m-d).
     * @param int    $campaignId     Optional campaign filter for order conversions (forms ignore campaign).
     * @return int
     * @since 3.0.0
     */
    public static function countTotalConversionsForNotification(
        int $notificationId,
        string $startDate,
        string $endDate,
        int $campaignId = 0
    ): int {
        // Non-ecommerce sites only count form submissions.
        if (! self::countsOrderConversions()) {
            return self::countFormSubmissionsForNotification($notificationId, $startDate, $endDate);
        }

        // Ecommerce: sum order conversions and form submissions from their source tables.
        $orderConversions = self::countOrderConversionsForNotification($notificationId, $startDate, $endDate, $campaignId);
        $formConversions  = self::countFormSubmissionsForNotification($notificationId, $startDate, $endDate);

        return $orderConversions + $formConversions;
    }

    /**
     * Count influenced order conversions stored in the conversions table.
     *
     * @param int    $notificationId OnPage notification post ID.
     * @param string $startDate      Start date (Y-m-d).
     * @param string $endDate        End date (Y-m-d).
     * @param int    $campaignId     When greater than zero, limit to that campaign.
     * @return int
     * @since 3.0.0
     */
    public static function countOrderConversionsForNotification(
        int $notificationId,
        string $startDate,
        string $endDate,
        int $campaignId = 0
    ): int {
        // Require a valid notification and order conversion mode.
        if ($notificationId <= 0 || ! self::countsOrderConversions()) {
            return 0;
        }

        global $wpdb;

        // Resolve the conversions table name from migration metadata.
        $tables            = MigrationService::getTableNames();
        $conversionsTable  = $tables['conversions'] ?? '';

        // Bail when the conversions table has not been created yet.
        if ($conversionsTable === '' || $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $conversionsTable)) !== $conversionsTable) {
            return 0;
        }

        // Optional campaign filter applies only to order rows.
        if ($campaignId > 0) {
            $sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$conversionsTable}
                WHERE notification_id = %d
                AND conversion_timestamp >= %s
                AND conversion_timestamp <= %s
                AND campaign_id = %d",
                $notificationId,
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
                $campaignId
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$conversionsTable}
                WHERE notification_id = %d
                AND conversion_timestamp >= %s
                AND conversion_timestamp <= %s",
                $notificationId,
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            );
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Count unique converters on ecommerce sites (orders and forms combined).
     *
     * Builds a deduplicated set of converter identity keys: logged-in order converters (user id),
     * guest order converters (click session id), and form submitters (hashed IP). The same person may
     * still appear twice if they convert as a logged-in buyer and later submit a form as a guest
     * because forms do not store WordPress user IDs.
     *
     * @param int    $notificationId OnPage notification post ID.
     * @param string $startDate      Start date (Y-m-d).
     * @param string $endDate        End date (Y-m-d).
     * @param int    $campaignId     Optional campaign filter for order identities only.
     * @return int
     * @since 3.0.0
     */
    public static function countUniqueCombinedConvertersForNotification(
        int $notificationId,
        string $startDate,
        string $endDate,
        int $campaignId = 0
    ): int {
        // Delegate to form-only counting on non-ecommerce sites.
        if (! self::countsOrderConversions()) {
            return self::countUniqueFormSubmittersForNotification($notificationId, $startDate, $endDate);
        }

        // Require a valid notification ID.
        if ($notificationId <= 0) {
            return 0;
        }

        global $wpdb;

        // Load analytics table names.
        $tables               = MigrationService::getTableNames();
        $conversionsTable     = $tables['conversions'] ?? '';
        $productClicksTable   = $tables['product_clicks'] ?? '';
        $formTable            = self::getFormSubmissionsTableName();

        // Require conversions and product click tables for order identities.
        if (
            $conversionsTable === ''
            || $productClicksTable === ''
            || $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $conversionsTable)) !== $conversionsTable
            || $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $productClicksTable)) !== $productClicksTable
        ) {
            // Fall back to form submitters when order tables are unavailable.
            return self::countUniqueFormSubmittersForNotification($notificationId, $startDate, $endDate);
        }

        // When forms storage is missing, count order identities only (legacy guest plus logged-in query).
        if (! self::formSubmissionsTableExists()) {
            return self::countUniqueOrderConvertersForNotification($notificationId, $startDate, $endDate, $campaignId);
        }

        // Campaign filter fragment for order-side identities.
        $campaignSql = $campaignId > 0 ? ' AND c.campaign_id = %d' : '';

        // Base parameters shared by order identity subqueries.
        $orderParams = [
            $notificationId,
            $startDate . ' 00:00:00',
            $endDate . ' 23:59:59',
        ];

        if ($campaignId > 0) {
            $orderParams[] = $campaignId;
        }

        // Form submitter parameters (campaign does not apply to forms).
        $formParams = [
            $notificationId,
            $startDate,
            $endDate,
        ];

        // Union distinct identity keys across orders and forms, then count them.
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM (
                SELECT CONCAT('u:', CAST(c.user_id AS CHAR)) AS identity
                FROM {$conversionsTable} c
                WHERE c.notification_id = %d
                AND c.conversion_timestamp >= %s
                AND c.conversion_timestamp <= %s
                {$campaignSql}
                AND c.user_id > 0
                UNION
                SELECT CONCAT('s:', pc.session_id) AS identity
                FROM {$conversionsTable} c
                INNER JOIN {$productClicksTable} pc ON pc.id = c.product_click_id
                WHERE c.notification_id = %d
                AND c.conversion_timestamp >= %s
                AND c.conversion_timestamp <= %s
                {$campaignSql}
                AND c.user_id = 0
                AND pc.session_id IS NOT NULL
                AND pc.session_id != ''
                UNION
                SELECT CONCAT('ip:', fs.ip_hash) AS identity
                FROM {$formTable} fs
                WHERE fs.notification_id = %d
                AND fs.ip_hash != ''
                AND DATE(fs.created_at) >= %s
                AND DATE(fs.created_at) <= %s
            ) AS combined_identities",
            array_merge($orderParams, $orderParams, $formParams)
        );

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Count unique order converters only (logged-in user IDs plus guest click session IDs).
     *
     * @param int    $notificationId OnPage notification post ID.
     * @param string $startDate      Start date (Y-m-d).
     * @param string $endDate        End date (Y-m-d).
     * @param int    $campaignId     Optional campaign filter.
     * @return int
     * @since 3.0.0
     */
    public static function countUniqueOrderConvertersForNotification(
        int $notificationId,
        string $startDate,
        string $endDate,
        int $campaignId = 0
    ): int {
        // Require a valid notification ID.
        if ($notificationId <= 0) {
            return 0;
        }

        global $wpdb;

        // Load table names for conversions and product clicks.
        $tables             = MigrationService::getTableNames();
        $conversionsTable   = $tables['conversions'] ?? '';
        $productClicksTable = $tables['product_clicks'] ?? '';

        if (
            $conversionsTable === ''
            || $productClicksTable === ''
            || $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $conversionsTable)) !== $conversionsTable
            || $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $productClicksTable)) !== $productClicksTable
        ) {
            return 0;
        }

        // Optional campaign filter for order rows.
        $campaignSql = $campaignId > 0 ? ' AND c.campaign_id = %d' : '';

        // Count distinct logged-in buyers.
        $paramsLoggedIn = [
            $notificationId,
            $startDate . ' 00:00:00',
            $endDate . ' 23:59:59',
        ];

        if ($campaignId > 0) {
            $paramsLoggedIn[] = $campaignId;
        }

        $loggedInSql = $wpdb->prepare(
            "SELECT COUNT(DISTINCT c.user_id)
            FROM {$conversionsTable} c
            WHERE c.notification_id = %d
            AND c.conversion_timestamp >= %s
            AND c.conversion_timestamp <= %s
            {$campaignSql}
            AND c.user_id > 0",
            $paramsLoggedIn
        );

        $loggedInConverters = (int) $wpdb->get_var($loggedInSql);

        // Count distinct guest sessions linked through product clicks.
        $paramsGuest = [
            $notificationId,
            $startDate . ' 00:00:00',
            $endDate . ' 23:59:59',
        ];

        if ($campaignId > 0) {
            $paramsGuest[] = $campaignId;
        }

        $guestSql = $wpdb->prepare(
            "SELECT COUNT(DISTINCT pc.session_id)
            FROM {$conversionsTable} c
            INNER JOIN {$productClicksTable} pc ON pc.id = c.product_click_id
            WHERE c.notification_id = %d
            AND c.conversion_timestamp >= %s
            AND c.conversion_timestamp <= %s
            {$campaignSql}
            AND c.user_id = 0
            AND pc.session_id IS NOT NULL
            AND pc.session_id != ''",
            $paramsGuest
        );

        $guestConverters = (int) $wpdb->get_var($guestSql);

        return $loggedInConverters + $guestConverters;
    }

    /**
     * Daily form submission counts for one or more notifications (for charts and exports).
     *
     * @param int[]  $notificationIds Notification post IDs.
     * @param string $startDate       Start date (Y-m-d).
     * @param string $endDate         End date (Y-m-d).
     * @return array<int, array{date: string, value: int}>
     * @since 3.0.0
     */
    public static function getFormSubmissionDailyCountsForNotifications(
        array $notificationIds,
        string $startDate,
        string $endDate
    ): array {
        // Normalize IDs and bail when forms storage is missing.
        $notificationIds = array_values(array_filter(array_map('absint', $notificationIds)));

        if (empty($notificationIds) || ! self::formSubmissionsTableExists()) {
            return [];
        }

        global $wpdb;

        $table         = self::getFormSubmissionsTableName();
        $placeholders  = implode(',', array_fill(0, count($notificationIds), '%d'));
        $params        = array_merge($notificationIds, [$startDate, $endDate]);

        // Aggregate submissions per calendar day.
        $sql = $wpdb->prepare(
            "SELECT DATE(created_at) AS date, COUNT(*) AS value
            FROM {$table}
            WHERE notification_id IN ({$placeholders})
            AND DATE(created_at) >= %s
            AND DATE(created_at) <= %s
            GROUP BY DATE(created_at)
            ORDER BY date ASC",
            $params
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (! is_array($rows)) {
            return [];
        }

        // Cast values to integers for chart consumers.
        return array_map(
            static function (array $row): array {
                return [
                    'date'  => (string) ($row['date'] ?? ''),
                    'value' => (int) ($row['value'] ?? 0),
                ];
            },
            $rows
        );
    }

    /**
     * Daily influenced order conversion counts for notifications (conversions table source).
     *
     * @param int[]  $notificationIds Notification post IDs.
     * @param string $startDate       Start date (Y-m-d).
     * @param string $endDate         End date (Y-m-d).
     * @param int    $campaignId      Optional campaign filter for order rows.
     * @return array<int, array{date: string, value: int}>
     * @since 3.0.0
     */
    public static function getOrderConversionDailyCountsForNotifications(
        array $notificationIds,
        string $startDate,
        string $endDate,
        int $campaignId = 0
    ): array {
        // Normalize IDs and require order conversion mode.
        $notificationIds = array_values(array_filter(array_map('absint', $notificationIds)));

        if (empty($notificationIds) || ! self::countsOrderConversions()) {
            return [];
        }

        global $wpdb;

        $tables           = MigrationService::getTableNames();
        $conversionsTable = $tables['conversions'] ?? '';

        if ($conversionsTable === '' || $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $conversionsTable)) !== $conversionsTable) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($notificationIds), '%d'));
        $campaignSql  = $campaignId > 0 ? ' AND campaign_id = %d' : '';
        $params       = array_merge(
            $notificationIds,
            [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
        );

        if ($campaignId > 0) {
            $params[] = $campaignId;
        }

        $sql = $wpdb->prepare(
            "SELECT DATE(conversion_timestamp) AS date, COUNT(*) AS value
            FROM {$conversionsTable}
            WHERE notification_id IN ({$placeholders})
            AND conversion_timestamp >= %s
            AND conversion_timestamp <= %s
            {$campaignSql}
            GROUP BY DATE(conversion_timestamp)
            ORDER BY date ASC",
            $params
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (! is_array($rows)) {
            return [];
        }

        return array_map(
            static function (array $row): array {
                return [
                    'date'  => (string) ($row['date'] ?? ''),
                    'value' => (int) ($row['value'] ?? 0),
                ];
            },
            $rows
        );
    }

    /**
     * Merge two daily count series by date, summing values for matching dates.
     *
     * @param array<int, array{date: string, value: int}> $seriesA First series.
     * @param array<int, array{date: string, value: int}> $seriesB Second series.
     * @return array<int, array{date: string, value: int}>
     * @since 3.0.0
     */
    public static function mergeDailyCountSeries(array $seriesA, array $seriesB): array
    {
        // Index both series by date for O(n) merging.
        $byDate = [];

        foreach (array_merge($seriesA, $seriesB) as $row) {
            $date = (string) ($row['date'] ?? '');

            if ($date === '') {
                continue;
            }

            $byDate[$date] = ($byDate[$date] ?? 0) + (int) ($row['value'] ?? 0);
        }

        // Sort chronologically for chart rendering.
        ksort($byDate);

        $merged = [];

        foreach ($byDate as $date => $value) {
            $merged[] = [
                'date'  => $date,
                'value' => (int) $value,
            ];
        }

        return $merged;
    }

    /**
     * Resolve the Notifal form submissions table name.
     *
     * @return string
     * @since 3.0.0
     */
    public static function getFormSubmissionsTableName(): string
    {
        // Table name follows the Forms module migration prefix.
        global $wpdb;

        return $wpdb->prefix . 'notifal_form_submissions';
    }

    /**
     * Whether the form submissions table exists in the database.
     *
     * @return bool
     * @since 3.0.0
     */
    public static function formSubmissionsTableExists(): bool
    {
        // Avoid queries when the Forms module has not created tables yet.
        global $wpdb;

        $table = self::getFormSubmissionsTableName();

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    /**
     * Count form submissions linked to a notification within a date range.
     *
     * @param int    $notificationId OnPage notification post ID.
     * @param string $startDate      Start date (Y-m-d).
     * @param string $endDate        End date (Y-m-d).
     * @return int
     * @since 3.0.0
     */
    public static function countFormSubmissionsForNotification(int $notificationId, string $startDate, string $endDate): int
    {
        // Bail when forms storage is unavailable or the notification ID is invalid.
        if ($notificationId <= 0 || ! self::formSubmissionsTableExists()) {
            return 0;
        }

        global $wpdb;

        $table = self::getFormSubmissionsTableName();

        // Count submissions tied to this notification inside the inclusive date window.
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
            WHERE notification_id = %d
            AND DATE(created_at) >= %s
            AND DATE(created_at) <= %s",
            $notificationId,
            $startDate,
            $endDate
        );

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Count unique form submitters (by hashed IP) for a notification in a date range.
     *
     * @param int    $notificationId OnPage notification post ID.
     * @param string $startDate      Start date (Y-m-d).
     * @param string $endDate        End date (Y-m-d).
     * @return int
     * @since 3.0.0
     */
    public static function countUniqueFormSubmittersForNotification(int $notificationId, string $startDate, string $endDate): int
    {
        // Bail when forms storage is unavailable or the notification ID is invalid.
        if ($notificationId <= 0 || ! self::formSubmissionsTableExists()) {
            return 0;
        }

        global $wpdb;

        $table = self::getFormSubmissionsTableName();

        // Distinct ip_hash values approximate unique visitors for form conversions.
        $sql = $wpdb->prepare(
            "SELECT COUNT(DISTINCT ip_hash) FROM {$table}
            WHERE notification_id = %d
            AND ip_hash != ''
            AND DATE(created_at) >= %s
            AND DATE(created_at) <= %s",
            $notificationId,
            $startDate,
            $endDate
        );

        return (int) $wpdb->get_var($sql);
    }
}
