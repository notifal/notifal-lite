<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Analytics;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Security\NonceManager;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\OnPageNotification\Application\Services\Analytics\CampaignAttributionResolver;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Repositories\DatabaseRepository;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class ConversionTracker
 *
 * Handles conversion tracking for OnPage notifications with product-level attribution.
 * Tracks revenue from WooCommerce and Easy Digital Downloads purchases by attributing
 * them to specific notification product clicks within a configurable time window.
 *
 * Features:
 * - Product-level click tracking and attribution
 * - WooCommerce and EDD integration
 * - Session-based guest user tracking
 * - Configurable attribution windows
 * - Automatic duplicate prevention
 * - Fallback attribution for general notification clicks
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services\Analytics
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ConversionTracker
{
    /**
     * Database repository for OnPage notification data.
     *
     * @var DatabaseRepository
     * @since 2.0.0
     */
    private $databaseRepository;

    /**
     * Campaign attribution resolver.
     *
     * @var CampaignAttributionResolver
     * @since 2.2.0
     */
    private $campaignAttributionResolver;

    /**
     * Conversion attribution window in seconds.
     *
     * Default: 24 hours (86400 seconds)
     * Configurable via FilterHooks::ONPAGE_CONVERSION_ATTRIBUTION_WINDOW filter
     *
     * @var int
     * @since 2.0.0
     */
    private int $attributionWindow;

    /**
     * Flag to prevent multiple hook registrations.
     *
     * Ensures hooks are registered only once to prevent double-counting conversions.
     *
     * @var bool
     * @since 2.0.0
     */
    private static bool $hooksRegistered = false;

    /**
     * Constructor - Initialize conversion tracker with dependencies.
     *
     * Sets up database repository and attribution window from filter.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public function __construct()
    {
        // Resolve database repository from container
        $this->databaseRepository = notifal_app(DatabaseRepository::class);
        $this->campaignAttributionResolver = notifal_app(CampaignAttributionResolver::class);
        
        // Get attribution window from filter (default: 24 hours)
        $this->attributionWindow = apply_filters(FilterHooks::ONPAGE_CONVERSION_ATTRIBUTION_WINDOW, 24 * 60 * 60);
    }

    /**
     * Register conversion tracking hooks.
     *
     * Registers all WordPress hooks for conversion tracking including:
     * - WooCommerce order status changes
     * - Easy Digital Downloads purchases
     * - AJAX endpoints for manual tracking and product clicks
     * - Order attribution data storage
     *
     * Uses static flag to prevent duplicate hook registrations.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function register(): void
    {
        // Prevent multiple hook registrations that cause double counting
        if (self::$hooksRegistered) {
            return;
        }

        // Mark hooks as registered
        self::$hooksRegistered = true;
        
        // Create service instance
        $instance = new self();

        // Register WooCommerce conversion tracking
        if (PluginDetector::isWooCommerceActive()) {
            // WooCommerce is already loaded
            add_action('woocommerce_order_status_changed', [$instance, 'handleOrderStatusChange'], 10, 4);
        } else {
            // Wait for WooCommerce to load
            add_action('plugins_loaded', function() use ($instance) {
                if (PluginDetector::isWooCommerceActive()) {
                    add_action('woocommerce_order_status_changed', [$instance, 'handleOrderStatusChange'], 10, 4);
                }
            });
        }

        // Register Easy Digital Downloads conversion tracking
        if (PluginDetector::isEDDActive()) {
            add_action('edd_complete_purchase', [$instance, 'trackEDDConversion']);
        }

        // Register AJAX endpoint for manual conversion tracking
        add_action('wp_ajax_notifal_track_conversion', [$instance, 'handleManualConversion']);
        add_action('wp_ajax_nopriv_notifal_track_conversion', [$instance, 'handleManualConversion']);

        // Register AJAX endpoint for product click tracking
        add_action('wp_ajax_notifal_track_product_click', [$instance, 'handleProductClick']);
        add_action('wp_ajax_nopriv_notifal_track_product_click', [$instance, 'handleProductClick']);

        // Store attribution data in order meta during checkout (classic + block checkout)
        add_action('woocommerce_checkout_create_order', [$instance, 'storeAttributionInOrder'], 10, 1);
        add_action('woocommerce_checkout_order_created', [$instance, 'storeAttributionInOrder'], 10, 1);
        add_action('woocommerce_store_api_checkout_order_processed', [$instance, 'storeAttributionInOrderById'], 10, 1);
    }

    /**
     * WooCommerce order statuses that count as paid for influenced revenue.
     *
     * @var string[]
     * @since 2.3.0
     */
    private static $paidOrderStatuses = ['processing', 'completed'];

    /**
     * Track WooCommerce conversion on order completion.
     *
     * Processes each order item to find attributed product clicks and records conversions.
     * Uses product-level attribution for accurate revenue tracking per product.
     * Falls back to general attribution if no specific product clicks found.
     *
     * Prevents duplicate processing using order meta flag.
     *
     * @param int $orderId WooCommerce order ID
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public function trackWooCommerceConversion(int $orderId): void
    {
        // Validate order ID
        if (!$orderId) {
            return;
        }

        // Get WooCommerce order object
        $order = wc_get_order($orderId);
        if (!$order) {
            return;
        }

        // Prevent duplicate conversion tracking for the same order
        // Use WooCommerce order method for HPOS compatibility
        $alreadyProcessed = $order->get_meta('_notifal_conversion_processed', true);
        if ($alreadyProcessed) {
            return;
        }

        // Only track conversions for paid orders
        $orderStatus = $order->get_status();

        if (!in_array($orderStatus, self::$paidOrderStatuses, true)) {
            return;
        }

        // Collect all product IDs and their totals for bulk processing
        $orderItems = $order->get_items();
        $productData = [];
        $productIds = [];

        foreach ($orderItems as $item) {
            $productId = $this->getCorrectProductId($item);
            $productData[$productId] = [
                'item_total' => $item->get_total(),
                'quantity' => $item->get_quantity(),
            ];
            $productIds[] = $productId;
        }

        if (empty($productIds)) {
            return;
        }

        // Resolve guest session from order meta (stored at checkout) for reliable attribution
        $guestSessionId = (int) $order->get_user_id() > 0
            ? ''
            : (string) $order->get_meta('_notifal_session_id', true);

        // Find attributed clicks for all products in a single query
        $attributedClicks = $this->findAttributedProductClicksBulk($productIds, (int) $order->get_user_id(), $guestSessionId);

        // Process each product
        foreach ($productData as $productId => $data) {
            $productClicks = $attributedClicks[$productId] ?? [];

            if (empty($productClicks)) {
                // No specific product clicks found - try fallback attribution
                $this->tryFallbackAttribution($orderId, $productId, $data['item_total']);
                continue;
            }

            // Use the most recent click for attribution
            $mostRecentClick = $productClicks[0]; // Already ordered by click_timestamp DESC

            // Record conversion for this specific product
            $this->recordProductConversion([
                'notification_id' => $mostRecentClick['notification_id'],
                'product_click_id' => $mostRecentClick['id'],
                'order_id' => $orderId,
                'product_id' => $productId,
                'product_revenue' => $data['item_total'],
                'total_order_value' => $this->resolveOrderTotalValue($order),
                'currency' => $order->get_currency(),
                'click_timestamp' => $mostRecentClick['click_timestamp'],
                'conversion_timestamp' => current_time('mysql'),
                'attribution_type' => 'woocommerce',
                'user_id' => $order->get_user_id() ?: 0,
                'campaign_id' => isset($mostRecentClick['campaign_id']) ? (int) $mostRecentClick['campaign_id'] : 0,
            ]);

            // Mark all attributed clicks as converted to prevent double-counting
            foreach ($productClicks as $clickData) {
                $this->markClickAsConverted($clickData['id']);
            }
        }

        // Lock influenced revenue at payment time (refunds/cancellations must not change analytics)
        $lockedRevenue = $this->resolveOrderTotalValue($order);
        if ($lockedRevenue > 0) {
            $order->update_meta_data('_notifal_influenced_revenue_locked', $lockedRevenue);
        }

        // Pending influence is no longer needed once conversion is recorded
        $order->delete_meta_data('_notifal_pending_attribution');

        // Mark this order as processed to prevent duplicate tracking
        // Use WooCommerce order method for HPOS compatibility
        $order->update_meta_data('_notifal_conversion_processed', current_time('mysql'));
        $order->save();
    }

    /**
     * Store attribution data in order meta during checkout.
     *
     * Captures attribution data from session/cookies and stores it in order meta
     * for potential use in fallback attribution scenarios.
     *
     * @param \WC_Order $order WooCommerce order object
     * @return void
     * @since 2.0.2
     * @author Hossein <hossein@notifal.com>
     */
    public function storeAttributionInOrder($order): void
    {
        if (!$order instanceof \WC_Order) {
            return;
        }

        // Persist session ID on the order so guest conversions match checkout clicks
        $sessionId = Helper::getSessionId();
        $order->update_meta_data('_notifal_session_id', $sessionId);

        // Get attribution data from session/cookies
        $attributionData = $this->getAttributionData();

        if (!empty($attributionData)) {
            // Store attribution data in order meta for later use
            $order->update_meta_data('_notifal_attribution', $attributionData);
        }

        // Snapshot pending influence for unpaid orders (on-hold, pending payment, etc.)
        $pendingAttribution = $this->buildPendingAttributionSnapshot($order, $sessionId);

        if (!empty($pendingAttribution)) {
            $order->update_meta_data('_notifal_pending_attribution', $pendingAttribution);
        }
    }

    /**
     * Store attribution data for WooCommerce Blocks checkout payloads.
     *
     * WooCommerce may pass either a WC_Order object or a numeric order ID depending
     * on version/hook internals, so this method safely normalizes both formats.
     *
     * @param \WC_Order|int $orderOrId WooCommerce order object or order ID
     * @return void
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public function storeAttributionInOrderById($orderOrId): void
    {
        if ($orderOrId instanceof \WC_Order) {
            $this->storeAttributionInOrder($orderOrId);
            return;
        }

        $orderId = (int) $orderOrId;

        if ($orderId <= 0) {
            return;
        }

        $order = wc_get_order($orderId);

        if (!$order) {
            return;
        }

        $this->storeAttributionInOrder($order);
    }

    /**
     * Handle WooCommerce order status changes for conversion tracking.
     *
     * Triggers conversion tracking when order transitions to a paid status.
     * Only tracks once per order to prevent duplicate conversions.
     *
     * @param int $orderId WooCommerce order ID
     * @param string $oldStatus Previous order status
     * @param string $newStatus New order status
     * @param \WC_Order $order WooCommerce order object
     * @return void
     * @since 2.0.2
     * @author Hossein <hossein@notifal.com>
     */
    public function handleOrderStatusChange(int $orderId, string $oldStatus, string $newStatus, $order): void
    {
        // Only track when transitioning to a paid status from a non-paid status
        if (in_array($newStatus, self::$paidOrderStatuses, true) && !in_array($oldStatus, self::$paidOrderStatuses, true)) {
            $this->trackWooCommerceConversion($orderId);
        }
    }

    /**
     * Track Easy Digital Downloads conversion.
     *
     * Records conversions for EDD purchases using attribution data from session/cookies.
     * Only tracks conversions within the attribution window.
     *
     * @param int $paymentId EDD payment ID
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public function trackEDDConversion(int $paymentId): void
    {
        // Validate payment ID
        if (!$paymentId) {
            return;
        }

        // Get EDD payment object
        $payment = edd_get_payment($paymentId);
        if (!$payment) {
            return;
        }

        // Get attribution data
        $attributionData = $this->getAttributionData();

        if (empty($attributionData)) {
            return;
        }

        // Process each notification in attribution data
        foreach ($attributionData as $notification_id => $clickData) {
            // Only track if within attribution window
            if ($this->isWithinAttributionWindow($clickData['timestamp'])) {
                $this->recordConversion([
                    'notification_id' => $notification_id,
                    'order_id' => $paymentId,
                    'revenue' => $payment->total,
                    'currency' => edd_get_currency(),
                    'click_timestamp' => $clickData['timestamp'],
                    'conversion_timestamp' => current_time('mysql'),
                    'attribution_type' => 'edd',
                    'user_id' => $payment->user_id ?: 0,
                ]);
            }
        }
    }


    /**
     * Handle manual conversion tracking via AJAX.
     *
     * Allows manual tracking of conversions via AJAX requests.
     * Validates nonce, sanitizes input, and records conversion if valid.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public function handleManualConversion(): void
    {
        // Verify nonce for security
        if (!NonceManager::verify($_POST['nonce'] ?? '', 'notifal_conversion_tracking')) {
            wp_send_json_error(['message' => __('Invalid nonce', 'notifal')]);
            return;
        }

        // Sanitize and prepare conversion data
        $data = [
            'notification_id' => (int)($_POST['notification_id'] ?? 0),
            'revenue' => (float)($_POST['revenue'] ?? 0),
            'currency' => Helper::sanitizeInput($_POST['currency'] ?? get_option('woocommerce_currency', 'USD'), 'text'),
            'order_id' => Helper::sanitizeInput($_POST['order_id'] ?? '', 'text'),
            'attribution_type' => Helper::sanitizeInput($_POST['attribution_type'] ?? 'manual', 'text'),
        ];

        // Validate required fields
        if ($data['notification_id'] && $data['revenue'] > 0) {
            $this->recordConversion($data);
            wp_send_json_success(['message' => __('Conversion tracked successfully', 'notifal')]);
        } else {
            wp_send_json_error(['message' => __('Invalid conversion data', 'notifal')]);
        }
    }

    /**
     * Handle product click tracking via AJAX.
     *
     * Records product clicks from notifications for attribution tracking.
     * Captures user/session data, product info, and metadata for later conversion attribution.
     *
     * @return void
     * @since 2.0.2
     * @author Hossein <hossein@notifal.com>
     */
    public function handleProductClick(): void
    {
        // Verify nonce for security
        if (!NonceManager::verify($_POST['nonce'] ?? '', 'notifal_product_click_tracking')) {
            wp_send_json_error(['message' => __('Invalid nonce', 'notifal')]);
            return;
        }

        // Allow frontend to pass session_id; otherwise use persistent cookie-based ID
        $postedSessionId = isset($_POST['session_id'])
            ? sanitize_text_field(wp_unslash($_POST['session_id']))
            : '';

        // Sanitize and prepare product click data
        $data = [
            'notification_id' => (int)($_POST['notification_id'] ?? 0),
            'product_id' => (int)($_POST['product_id'] ?? 0),
            'user_id' => get_current_user_id(),
            'session_id' => $postedSessionId !== '' ? $postedSessionId : Helper::getSessionId(),
            'timestamp' => time(),
            'attribution_window_hours' => 24,
            'page_url' => esc_url_raw($_POST['page_url'] ?? ''),
            'referrer' => esc_url_raw($_POST['referrer'] ?? ''),
            'ip_address' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_textarea_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
        ];

        // Validate required fields and record click
        if ($data['notification_id'] && $data['product_id']) {
            $clickId = $this->recordProductClick($data);
            if ($clickId) {
                wp_send_json_success([
                    'message' => __('Product click tracked successfully', 'notifal'),
                    'click_id' => $clickId
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to track product click', 'notifal')]);
            }
        } else {
            wp_send_json_error(['message' => __('Invalid product click data', 'notifal')]);
        }
    }

    /**
     * Record a product click for attribution tracking.
     *
     * Stores product click information for later attribution to conversions.
     * Records notification ID, product ID, user/session data, and metadata.
     *
     * @param array $clickData Product click data including:
     *                         - notification_id: int
     *                         - product_id: int
     *                         - user_id: int
     *                         - session_id: string
     *                         - timestamp: int
     *                         - attribution_window_hours: int
     *                         - page_url: string
     *                         - referrer: string
     *                         - ip_address: string
     *                         - user_agent: string
     * @return int|false Click ID on success, false on failure
     * @since 2.0.2
     * @author Hossein <hossein@notifal.com>
     */
    public function recordProductClick(array $clickData)
    {
        global $wpdb;

        // Get product clicks table
        $tables = $this->databaseRepository->getTableNames();
        $table = $tables['product_clicks'];

        // Insert product click record
        $result = $wpdb->insert(
            $table,
            [
                'notification_id' => (int)($clickData['notification_id'] ?? 0),
                'product_id' => (int)($clickData['product_id'] ?? 0),
                'user_id' => (int)($clickData['user_id'] ?? 0),
                'session_id' => Helper::sanitizeInput($clickData['session_id'] ?? '', 'text'),
                'click_timestamp' => date('Y-m-d H:i:s', $clickData['timestamp'] ?? time()),
                'attribution_window_hours' => (int)($clickData['attribution_window_hours'] ?? 24),
                'page_url' => esc_url_raw($clickData['page_url'] ?? ''),
                'referrer' => esc_url_raw($clickData['referrer'] ?? ''),
                'ip_address' => Helper::sanitizeInput($clickData['ip_address'] ?? '', 'text'),
                'user_agent' => Helper::sanitizeInput($clickData['user_agent'] ?? '', 'text'),
                'campaign_id' => $this->campaignAttributionResolver->resolveCampaignIdForNotification(
                    (int) ($clickData['notification_id'] ?? 0)
                ),
                'status' => 'pending'
            ],
            [
                '%d', // notification_id
                '%d', // product_id
                '%d', // user_id
                '%s', // session_id
                '%s', // click_timestamp
                '%d', // attribution_window_hours
                '%s', // page_url
                '%s', // referrer
                '%s', // ip_address
                '%s', // user_agent
                '%d', // campaign_id
                '%s'  // status
            ]
        );

        // Return inserted ID or false on failure
        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Find attributed product clicks within the attribution window for multiple products.
     *
     *
     * @param array  $productIds Array of product IDs to search for
     * @param int    $userId     User ID (0 for guest users)
     * @param string $sessionId  Guest session ID from order meta or cookie (optional)
     * @return array Array keyed by product_id with attributed clicks arrays
     * @since 2.0.2
     * @since 2.3.0 Accepts order-stored session ID for guest checkout attribution.
     * @author Hossein <hossein@notifal.com>
     */
    private function findAttributedProductClicksBulk(array $productIds, int $userId = 0, string $sessionId = ''): array
    {
        if (empty($productIds)) {
            return [];
        }

        global $wpdb;

        // Get database tables
        $tables = $this->databaseRepository->getTableNames();
        $table = $tables['product_clicks'];

        // Calculate cutoff time for attribution window
        $cutoffTime = date('Y-m-d H:i:s', time() - $this->attributionWindow);

        // Create placeholders for product IDs
        $placeholders = implode(',', array_fill(0, count($productIds), '%d'));

        // Build query based on whether user is logged in or not
        if ($userId > 0) {
            // For logged-in users, search by user_id
            $sql = $wpdb->prepare(
                "SELECT * FROM $table
                WHERE product_id IN ($placeholders)
                AND user_id = %d
                AND click_timestamp >= %s
                AND status = 'pending'
                ORDER BY product_id, click_timestamp DESC",
                array_merge($productIds, [$userId, $cutoffTime])
            );
        } else {
            // For guest users, prefer session stored on the order, then cookie
            $resolvedSessionId = $sessionId !== '' ? $sessionId : Helper::getSessionId();

            if ($resolvedSessionId === '') {
                return [];
            }

            $sql = $wpdb->prepare(
                "SELECT * FROM $table
                WHERE product_id IN ($placeholders)
                AND session_id = %s
                AND click_timestamp >= %s
                AND status = 'pending'
                ORDER BY product_id, click_timestamp DESC",
                array_merge($productIds, [$resolvedSessionId, $cutoffTime])
            );
        }

        // Execute query
        $results = $wpdb->get_results($sql, ARRAY_A);
        if (!$results) {
            return [];
        }

        // Group results by product_id
        $groupedResults = [];
        foreach ($results as $row) {
            $productId = (int)$row['product_id'];
            if (!isset($groupedResults[$productId])) {
                $groupedResults[$productId] = [];
            }
            $groupedResults[$productId][] = $row;
        }

        return $groupedResults;
    }


    /**
     * Record a product-specific conversion.
     *
     * Stores detailed conversion data including product-level revenue attribution.
     * Updates daily statistics and triggers conversion recorded action hook.
     *
     * @param array $conversionData Conversion data including:
     *                              - notification_id: int
     *                              - product_click_id: int
     *                              - order_id: int
     *                              - product_id: int
     *                              - product_revenue: float
     *                              - total_order_value: float
     *                              - currency: string
     *                              - click_timestamp: string
     *                              - conversion_timestamp: string
     *                              - attribution_type: string
     *                              - user_id: int
     * @return bool True on success, false on failure
     * @since 2.0.2
     * @author Hossein <hossein@notifal.com>
     */
    private function recordProductConversion(array $conversionData): bool
    {
        global $wpdb;

        // Get conversions table
        $tables = $this->databaseRepository->getTableNames();
        $table = $tables['conversions'];

        // Insert conversion record
        $result = $wpdb->insert(
            $table,
            [
                'notification_id' => (int)($conversionData['notification_id'] ?? 0),
                'product_click_id' => (int)($conversionData['product_click_id'] ?? 0),
                'order_id' => (int)($conversionData['order_id'] ?? 0),
                'product_id' => (int)($conversionData['product_id'] ?? 0),
                'product_revenue' => (float)($conversionData['product_revenue'] ?? 0),
                'total_order_value' => (float)($conversionData['total_order_value'] ?? 0),
                'currency' => Helper::sanitizeInput($conversionData['currency'] ?? 'USD', 'text'),
                'click_timestamp' => $conversionData['click_timestamp'],
                'conversion_timestamp' => $conversionData['conversion_timestamp'],
                'attribution_type' => Helper::sanitizeInput($conversionData['attribution_type'] ?? 'woocommerce', 'text'),
                'user_id' => (int)($conversionData['user_id'] ?? 0),
                'campaign_id' => (int)($conversionData['campaign_id'] ?? 0),
            ],
            [
                '%d', // notification_id
                '%d', // product_click_id
                '%d', // order_id
                '%d', // product_id
                '%f', // product_revenue
                '%f', // total_order_value
                '%s', // currency
                '%s', // click_timestamp
                '%s', // conversion_timestamp
                '%s', // attribution_type
                '%d', // user_id
                '%d'  // campaign_id
            ]
        );

        if ($result) {
            // Update daily stats with conversion count
            $date = current_time('Y-m-d');
            $this->databaseRepository->updateDailyStats($conversionData['notification_id'], 'conversion', $date);

            // Update daily clicked revenue (revenue from the specific clicked product item)
            $revenue = (float)($conversionData['product_revenue'] ?? 0);
            if ($revenue > 0) {
                $this->updateDailyRevenue($conversionData['notification_id'], $revenue, $date);
            }

            // Update daily influenced revenue (total order value) and influenced orders count (since 2.3.0)
            $totalOrderValue = (float)($conversionData['total_order_value'] ?? 0);
            $orderId         = (int)($conversionData['order_id'] ?? 0);
            if ($totalOrderValue > 0 && $orderId > 0) {
                $this->updateDailyInfluencedRevenue($conversionData['notification_id'], $totalOrderValue, $orderId, $date);
            }

            /**
             * Fires after recording a product-specific conversion.
             *
             * @since 2.0.2
             * @param array $conversionData Conversion data
             */
            do_action(ActionHooks::ONPAGE_CONVERSION_RECORDED, $conversionData);

            return true;
        }

        return false;
    }

    /**
     * Mark a product click as converted.
     *
     * Updates the status of a product click to 'converted' after a successful conversion.
     * Prevents double-counting the same click for multiple conversions.
     *
     * @param int $clickId Click ID to mark as converted
     * @return bool True on success, false on failure
     * @since 2.0.2
     * @author Hossein <hossein@notifal.com>
     */
    private function markClickAsConverted(int $clickId): bool
    {
        global $wpdb;

        // Get product clicks table
        $tables = $this->databaseRepository->getTableNames();
        $table = $tables['product_clicks'];

        // Update click status to converted
        $result = $wpdb->update(
            $table,
            ['status' => 'converted'],
            ['id' => $clickId],
            ['%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Record a conversion in the database.
     *
     * Generic conversion recording for non-product-specific conversions.
     * Used for fallback attribution and EDD conversions.
     * Updates daily statistics and user statistics.
     *
     * @param array $conversionData Conversion data including:
     *                              - notification_id: int
     *                              - revenue: float
     *                              - order_id: string|int
     *                              - currency: string
     *                              - click_timestamp: string
     *                              - conversion_timestamp: string
     *                              - attribution_type: string
     *                              - user_id: int
     * @return bool True on success, false on failure
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function recordConversion(array $conversionData): bool
    {
        global $wpdb;

        // Validate required fields
        $notificationId = (int)($conversionData['notification_id'] ?? 0);
        $revenue = (float)($conversionData['revenue'] ?? 0);

        if (!$notificationId || $revenue <= 0) {
            return false;
        }

        // Update daily stats with conversion count
        $date = current_time('Y-m-d');
        $updated = $this->databaseRepository->updateDailyStats($notificationId, 'conversion', $date);

        if ($updated) {
            // Only update revenue for non-fallback conversions to prevent double counting
            $attributionType = $conversionData['attribution_type'] ?? '';
            if ($attributionType !== 'fallback' && $revenue > 0) {
                $this->updateDailyRevenue($notificationId, $revenue, $date);
            }

            // Update user stats if user is logged in
            $userId = $conversionData['user_id'] ?? get_current_user_id();
            if ($userId) {
                $this->databaseRepository->updateUserStats($notificationId, $userId, 'conversion');
            }

            /**
             * Fires after recording a conversion.
             *
             * @since 2.0.0
             * @param array $conversionData Conversion data
             */
            do_action(ActionHooks::ONPAGE_CONVERSION_RECORDED, $conversionData);

            return true;
        }

        return false;
    }

    /**
     * Update daily revenue statistics.
     *
     * Increments the revenue column in daily stats for the specified notification and date.
     *
     * @param int $notificationId Notification ID
     * @param float $revenue Revenue amount to add
     * @param string $date Date in Y-m-d format
     * @return bool True on success, false on failure
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function updateDailyRevenue(int $notificationId, float $revenue, string $date): bool
    {
        global $wpdb;

        // Get daily stats table
        $tables = $this->databaseRepository->getTableNames();
        $table = $tables['daily_stats'];

        // Update revenue in daily stats
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE $table SET
                revenue = revenue + %f,
                updated_at = CURRENT_TIMESTAMP
            WHERE notification_id = %d AND date = %s",
            $revenue,
            $notificationId,
            $date
        ));

        return $result !== false;
    }

    /**
     * Update daily influenced revenue and orders statistics.
     *
     * Tracks the total order value (not just the clicked product line) for orders that were
     * influenced by a Notifal notification. Each unique order_id is counted only once per
     * notification per day to avoid double-counting when multiple items in the same order
     * are attributed to the same notification.
     *
     * @param int    $notificationId  Notification ID
     * @param float  $totalOrderValue Full order total (WooCommerce get_total or EDD payment total)
     * @param int    $orderId         WooCommerce order ID or EDD payment ID (used for dedup check)
     * @param string $date            Date in Y-m-d format
     * @return bool True on success, false on failure
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    private function updateDailyInfluencedRevenue(int $notificationId, float $totalOrderValue, int $orderId, string $date): bool
    {
        global $wpdb;

        // Get table names from repository (supports custom prefixes)
        $tables            = $this->databaseRepository->getTableNames();
        $dailyStatsTable   = $tables['daily_stats'] ?? '';
        $conversionsTable  = $tables['conversions'] ?? '';

        if (empty($dailyStatsTable) || empty($conversionsTable)) {
            return false;
        }

        // Skip if this order was already counted for influenced metrics today (multiple line items)
        $conversionRowsForOrder = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$conversionsTable}`
                WHERE notification_id = %d
                AND order_id = %d
                AND DATE(conversion_timestamp) = %s",
                $notificationId,
                $orderId,
                $date
            )
        );

        if ($conversionRowsForOrder > 1) {
            return true;
        }

        // COALESCE guards against NULL influenced_* values on legacy daily_stats rows
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE `{$dailyStatsTable}` SET
                    influenced_revenue = COALESCE(influenced_revenue, 0) + %f,
                    influenced_orders  = COALESCE(influenced_orders, 0) + 1,
                    updated_at         = CURRENT_TIMESTAMP
                WHERE notification_id = %d AND date = %s",
                $totalOrderValue,
                $notificationId,
                $date
            )
        );

        if ($result === false) {
            return false;
        }

        // If no daily_stats row exists yet for today, create one with influenced metrics
        if ((int) $wpdb->rows_affected === 0) {
            $inserted = $wpdb->insert(
                $dailyStatsTable,
                [
                    'notification_id'   => $notificationId,
                    'date'              => $date,
                    'influenced_revenue'=> $totalOrderValue,
                    'influenced_orders' => 1,
                ],
                [
                    '%d',
                    '%s',
                    '%f',
                    '%d',
                ]
            );

            return $inserted !== false;
        }

        return true;
    }

    /**
     * Resolve the full order total used for influenced revenue attribution.
     *
     * @param \WC_Order $order WooCommerce order object
     * @return float Order total in store currency
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    private function resolveOrderTotalValue($order): float
    {
        if (!$order instanceof \WC_Order) {
            return 0.0;
        }

        $total = (float) $order->get_total('edit');

        if ($total > 0) {
            return $total;
        }

        // Fallback when totals are not yet calculated on the order object
        foreach ($order->get_items() as $item) {
            $total += (float) $item->get_total();
        }

        $total += (float) $order->get_shipping_total();
        $total += (float) $order->get_total_tax();
        $total -= (float) $order->get_total_discount();

        return max(0.0, $total);
    }

    /**
     * Try fallback attribution when no specific product clicks are found.
     *
     * Uses general notification attribution data from session/cookies when
     * no specific product click attribution is available. This ensures conversions
     * are still tracked even without product-level click data.
     *
     * @param int $orderId WooCommerce order ID
     * @param int $productId Product ID for the item
     * @param float $itemTotal Item revenue amount
     * @return void
     * @since 2.0.2
     * @author Hossein <hossein@notifal.com>
     */
    private function tryFallbackAttribution(int $orderId, int $productId, float $itemTotal): void
    {
        // Get general attribution data from session/cookies
        $attributionData = $this->getAttributionData();

        if (empty($attributionData)) {
            // Try to get attribution from order meta (if stored during checkout)
            // Use WooCommerce order method for HPOS compatibility
            $order = wc_get_order($orderId);
            if ($order) {
                $orderAttributionData = $order->get_meta('_notifal_attribution', true);
                if (!empty($orderAttributionData)) {
                    $attributionData = is_string($orderAttributionData) ? json_decode($orderAttributionData, true) : $orderAttributionData;
                }
            }

            if (empty($attributionData)) {
                return;
            }
        }

        // Find the most recent notification click within attribution window
        $mostRecentNotification = null;
        $mostRecentTimestamp = 0;

        foreach ($attributionData as $notificationId => $data) {
            // Parse timestamp (handle both string and millisecond formats)
            $clickTimestamp = isset($data['timestamp']) ?
                (is_string($data['timestamp']) ? strtotime($data['timestamp']) : $data['timestamp'] / 1000) :
                time();

            // Check if within attribution window and more recent
            if ($this->isWithinAttributionWindow($clickTimestamp) && $clickTimestamp > $mostRecentTimestamp) {
                $mostRecentNotification = $notificationId;
                $mostRecentTimestamp = $clickTimestamp;
            }
        }

        // Record conversion using fallback attribution if found (includes influenced revenue)
        if ($mostRecentNotification) {
            $orderObject = wc_get_order($orderId);
            $orderUserId = $orderObject ? (int) $orderObject->get_user_id() : 0;

            $this->recordProductConversion([
                'notification_id' => (int) $mostRecentNotification,
                'product_click_id' => 0,
                'order_id' => $orderId,
                'product_id' => $productId,
                'product_revenue' => $itemTotal,
                'total_order_value' => $orderObject ? $this->resolveOrderTotalValue($orderObject) : 0.0,
                'currency' => $orderObject ? $orderObject->get_currency() : get_option('woocommerce_currency', 'USD'),
                'click_timestamp' => date('Y-m-d H:i:s', $mostRecentTimestamp),
                'conversion_timestamp' => current_time('mysql'),
                'attribution_type' => 'fallback',
                'user_id' => $orderUserId,
                'campaign_id' => $this->campaignAttributionResolver->resolveCampaignIdForNotification((int) $mostRecentNotification),
            ]);
        }
    }

    /**
     * Build a pending attribution snapshot for an order at checkout.
     *
     * Used to show influence in the admin order list before payment and to count
     * total influenced orders separately from paid revenue in analytics.
     *
     * @param \WC_Order $order     WooCommerce order
     * @param string    $sessionId Guest session ID stored on the order
     * @return array Pending attribution rows
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    private function buildPendingAttributionSnapshot(\WC_Order $order, string $sessionId): array
    {
        $pendingRows = [];
        $seenKeys    = [];

        // Collect product IDs from the order for click lookup
        $productIds = [];

        foreach ($order->get_items() as $item) {
            if ($item instanceof \WC_Order_Item_Product) {
                $productIds[] = $this->getCorrectProductId($item);
            }
        }

        $productIds = array_values(array_unique(array_filter($productIds)));

        if (!empty($productIds)) {
            // Match pending product clicks using the checkout session ID
            $attributedClicks = $this->findAttributedProductClicksBulk(
                $productIds,
                (int) $order->get_user_id(),
                $sessionId
            );

            foreach ($attributedClicks as $productId => $clicks) {
                if (empty($clicks[0])) {
                    continue;
                }

                $clickRow = $clicks[0];
                $notificationId = (int) ($clickRow['notification_id'] ?? 0);
                $dedupeKey = $notificationId . '_' . (int) $productId;

                if ($notificationId <= 0 || isset($seenKeys[$dedupeKey])) {
                    continue;
                }

                $seenKeys[$dedupeKey] = true;
                $pendingRows[] = [
                    'notification_id' => $notificationId,
                    'product_id' => (int) $productId,
                    'product_revenue' => 0.0,
                    'total_order_value' => $this->resolveOrderTotalValue($order),
                    'attribution_type' => 'pending_product_click',
                    'is_pending' => true,
                ];
            }
        }

        // Include cookie/session notification attribution when no product click exists
        $attributionData = $this->getAttributionData();

        if (empty($attributionData)) {
            $storedAttribution = $order->get_meta('_notifal_attribution', true);

            if (!empty($storedAttribution)) {
                $attributionData = is_string($storedAttribution)
                    ? json_decode($storedAttribution, true)
                    : $storedAttribution;
            }
        }

        if (!is_array($attributionData)) {
            $attributionData = [];
        }

        foreach ($attributionData as $notificationId => $data) {
            $notificationId = (int) $notificationId;

            if ($notificationId <= 0) {
                continue;
            }

            $clickTimestamp = isset($data['timestamp'])
                ? (is_string($data['timestamp']) ? strtotime($data['timestamp']) : (int) ($data['timestamp'] / 1000))
                : time();

            if (!$this->isWithinAttributionWindow((int) $clickTimestamp)) {
                continue;
            }

            $dedupeKey = $notificationId . '_0';

            if (isset($seenKeys[$dedupeKey])) {
                continue;
            }

            $seenKeys[$dedupeKey] = true;
            $pendingRows[] = [
                'notification_id' => $notificationId,
                'product_id' => 0,
                'product_revenue' => 0.0,
                'total_order_value' => $this->resolveOrderTotalValue($order),
                'attribution_type' => 'pending_cookie',
                'is_pending' => true,
            ];
        }

        return $pendingRows;
    }

    /**
     * Get attribution data from session or cookies.
     *
     * Retrieves stored notification click attribution data from PHP session
     * or falls back to reading from cookies.
     *
     * @return array Attribution data array keyed by notification ID
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function getAttributionData(): array
    {
        // Try to get from PHP session first
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (isset($_SESSION['notifal_attribution'])) {
            return $_SESSION['notifal_attribution'];
        }

        // Fallback to cookies
        $attributionData = [];
        foreach ($_COOKIE as $name => $value) {
            // Look for Notifal attribution cookies
            if (strpos($name, 'notifal_attr_') === 0) {
                $notificationId = str_replace('notifal_attr_', '', $name);
                $attributionData[$notificationId] = [
                    'timestamp' => (int)$value,
                    'url' => esc_url_raw(wp_get_referer() ?: ''),
                    'referrer' => ''
                ];
            }
        }

        return $attributionData;
    }

    /**
     * Check if conversion is within attribution window.
     *
     * Determines if a click timestamp is recent enough to be attributed
     * to a conversion based on the configured attribution window.
     *
     * @param int $clickTimestamp Click timestamp (Unix timestamp)
     * @return bool True if within window, false otherwise
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function isWithinAttributionWindow(int $clickTimestamp): bool
    {
        $currentTime = time();
        return ($currentTime - $clickTimestamp) <= $this->attributionWindow;
    }

    /**
     * Update conversion status.
     *
     * Triggers action hook when conversion status is updated.
     * Can be extended for future conversion status management.
     *
     * @param int $orderId Order ID
     * @param string $status New conversion status
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function updateConversionStatus(int $orderId, string $status): void
    {
        /**
         * Fires when conversion status is updated.
         *
         * @since 2.0.0
         * @param int $orderId Order ID
         * @param string $status New status
         */
        do_action(ActionHooks::ONPAGE_CONVERSION_STATUS_UPDATED, $orderId, $status);
    }

    /**
     * Get the correct product ID for an order item.
     *
     * Returns the variation ID for variable products, or the product ID for simple products.
     * This is critical for proper revenue attribution tracking at the product level.
     *
     * @param \WC_Order_Item_Product $item WooCommerce order item
     * @return int Correct product ID (variation ID for variations, product ID for simple products)
     * @since 2.0.2
     * @author Hossein <hossein@notifal.com>
     */
    private function getCorrectProductId(\WC_Order_Item_Product $item): int
    {
        // Check if this is a variation (has variation_id)
        $variationId = $item->get_variation_id();

        if ($variationId > 0) {
            // This is a variation - return the variation ID
            return $variationId;
        }

        // This is a simple product - return the product ID
        return $item->get_product_id();
    }

    /**
     * Get conversion attribution report.
     *
     * Generates a report of conversions and revenue grouped by notification.
     * Used for analytics and performance tracking.
     *
     * @param array $filters Report filters including:
     *                       - start_date: string (Y-m-d format)
     *                       - end_date: string (Y-m-d format)
     * @return array Attribution report data with:
     *               - notification_id: int
     *               - total_conversions: int
     *               - total_revenue: float
     *               - avg_order_value: float
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public function getAttributionReport(array $filters = []): array
    {
        global $wpdb;

        // Get daily stats table
        $tables = $this->databaseRepository->getTableNames();
        $dailyStatsTable = $tables['daily_stats'];

        // Set default date range (last 30 days)
        $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $filters['end_date'] ?? date('Y-m-d');

        // Query conversion and revenue data
        $sql = $wpdb->prepare(
            "SELECT
                notification_id,
                SUM(conversions) as total_conversions,
                SUM(revenue) as total_revenue,
                AVG(revenue / NULLIF(conversions, 0)) as avg_order_value
            FROM $dailyStatsTable
            WHERE date BETWEEN %s AND %s
            AND (conversions > 0 OR revenue > 0)
            GROUP BY notification_id
            ORDER BY total_revenue DESC",
            $startDate,
            $endDate
        );

        // Execute query and return results
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }
}
