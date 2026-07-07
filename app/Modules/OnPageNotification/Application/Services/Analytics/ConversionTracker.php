<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Analytics;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Security\NonceManager;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\OnPageNotification\Application\Services\Analytics\CampaignAttributionResolver;
use Notifal\Modules\OnPageNotification\Helpers\AnalyticsHelper;
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
     * Tracks order IDs currently being converted within the same request.
     *
     * Prevents duplicate analytics when multiple WooCommerce hooks fire for one payment.
     *
     * @var array<int, bool>
     * @since 2.4.2
     */
    private static array $convertingOrderIds = [];

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
            // Backup hooks when status_changed is missed; maybeTrackWooCommerceConversion is idempotent
            add_action('woocommerce_payment_complete', [$instance, 'maybeTrackWooCommerceConversion'], 10, 1);
            add_action('woocommerce_order_status_processing', [$instance, 'maybeTrackWooCommerceConversion'], 10, 1);
            add_action('woocommerce_order_status_completed', [$instance, 'maybeTrackWooCommerceConversion'], 10, 1);
        } else {
            // Wait for WooCommerce to load
            add_action('plugins_loaded', function() use ($instance) {
                if (PluginDetector::isWooCommerceActive()) {
                    add_action('woocommerce_order_status_changed', [$instance, 'handleOrderStatusChange'], 10, 4);
                    add_action('woocommerce_payment_complete', [$instance, 'maybeTrackWooCommerceConversion'], 10, 1);
                    add_action('woocommerce_order_status_processing', [$instance, 'maybeTrackWooCommerceConversion'], 10, 1);
                    add_action('woocommerce_order_status_completed', [$instance, 'maybeTrackWooCommerceConversion'], 10, 1);
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
     * Safely attempt WooCommerce conversion tracking from multiple WooCommerce hooks.
     *
     * Idempotent entry point: skips duplicate work in the same request and when order meta
     * already marks the order as processed ({@see trackWooCommerceConversion()}).
     *
     * @param int|string $orderId WooCommerce order ID
     * @return void
     * @since 2.4.2
     * @author Hossein <hossein@notifal.com>
     */
    public function maybeTrackWooCommerceConversion($orderId): void
    {
        $this->trackWooCommerceConversion((int) $orderId);
    }

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

        // Same-request guard: payment_complete and status_* hooks can fire together
        if (isset(self::$convertingOrderIds[$orderId])) {
            return;
        }

        self::$convertingOrderIds[$orderId] = true;

        // Get WooCommerce order object
        $order = wc_get_order($orderId);
        if (!$order) {
            unset(self::$convertingOrderIds[$orderId]);
            return;
        }

        // Prevent duplicate conversion tracking for the same order
        // Use WooCommerce order method for HPOS compatibility
        $alreadyProcessed = $order->get_meta('_notifal_conversion_processed', true);
        if ($alreadyProcessed) {
            unset(self::$convertingOrderIds[$orderId]);
            return;
        }

        // Only track conversions for paid orders
        $orderStatus = $order->get_status();

        if (!AnalyticsHelper::isPaidWooCommerceOrderStatus($orderStatus)) {
            unset(self::$convertingOrderIds[$orderId]);
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
            // Paid order reached conversion processing; clear stale attribution markers.
            $this->clearAttributionStorage();
            unset(self::$convertingOrderIds[$orderId]);
            return;
        }

        // Always resolve the browser session stored on the order (guest clicks before login stay on session_id)
        $guestSessionId = $this->resolveOrderAttributionSessionId($order);

        // Include parent product IDs so variation line items match Post Link product clicks
        $lookupProductIds = $this->expandProductIdsForClickLookup($productIds);

        // Find attributed clicks for all products in a single query
        $attributedClicks = $this->findAttributedProductClicksBulk($lookupProductIds, (int) $order->get_user_id(), $guestSessionId);

        // Track how many conversions were actually persisted for this order
        $conversionsRecorded = 0;

        // Phase 1: attribute each order line from live product-click rows when still pending
        foreach ($productData as $productId => $data) {
            $productClicks = $this->resolveAttributedClicksForOrderProductId((int) $productId, $attributedClicks);

            if (empty($productClicks)) {
                // Checkout snapshot handles lines whose live click lookup expired or drifted
                continue;
            }

            // Use the most recent click for attribution
            $mostRecentClick = $productClicks[0]; // Already ordered by click_timestamp DESC

            // Record conversion for this specific product
            if ($this->recordProductConversion([
                'notification_id' => $mostRecentClick['notification_id'],
                'product_click_id' => $mostRecentClick['id'],
                'order_id' => $orderId,
                'product_id' => $productId,
                'product_revenue' => AnalyticsHelper::resolveOrderLineTotalFromProductData((int) $productId, $productData),
                'total_order_value' => $this->resolveOrderTotalValue($order),
                'currency' => $order->get_currency(),
                'click_timestamp' => $mostRecentClick['click_timestamp'],
                'conversion_timestamp' => current_time('mysql'),
                'attribution_type' => 'woocommerce',
                'user_id' => $order->get_user_id() ?: 0,
                'campaign_id' => isset($mostRecentClick['campaign_id']) ? (int) $mostRecentClick['campaign_id'] : 0,
            ])) {
                $conversionsRecorded++;

                // Mark all attributed clicks as converted to prevent double-counting
                foreach ($productClicks as $clickData) {
                    $this->markClickAsConverted($clickData['id']);
                }
            }
        }

        // Phase 2: reconcile remaining lines from checkout snapshot (per-notification product clicks)
        $conversionsRecorded += $this->recordConversionsFromPendingSnapshot($order, $productData);

        // Phase 3: order-level cookie influence only when no product attribution was recorded
        if ($conversionsRecorded <= 0 && $this->tryOrderLevelFallbackAttribution($order)) {
            $conversionsRecorded++;
        }

        // Keep pending attribution visible until at least one conversion row exists
        if ($conversionsRecorded <= 0) {
            // Always clear browser/server attribution markers after a paid order is processed
            // so future orders do not reuse stale influence data.
            $this->clearAttributionStorage();
            unset(self::$convertingOrderIds[$orderId]);
            return;
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

        // A completed attribution cycle must clear temporary click/session markers.
        $this->clearAttributionStorage();

        unset(self::$convertingOrderIds[$orderId]);
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
        $sessionId = $this->resolveOrderAttributionSessionId($order);

        if ($sessionId === '') {
            $sessionId = Helper::getSessionId();
        }

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
     * Rebuild and persist the pending attribution snapshot for an unpaid order.
     *
     * Re-queries product clicks from the database so admin views reflect clicks that
     * finished after checkout snapshot creation (async product-click race).
     *
     * @param \WC_Order $order WooCommerce order object
     * @return void
     * @since 2.4.2
     * @author Hossein <hossein@notifal.com>
     */
    public function refreshPendingAttributionSnapshot(\WC_Order $order): void
    {
        if (!$order instanceof \WC_Order) {
            return;
        }

        // Paid orders with stored conversions must not overwrite finalized attribution
        if ($order->get_meta('_notifal_conversion_processed', true)) {
            return;
        }

        $sessionId = $this->resolveOrderAttributionSessionId($order);

        if ($sessionId === '') {
            $sessionId = Helper::getSessionId();
        }

        $pendingAttribution = $this->buildPendingAttributionSnapshot($order, $sessionId);

        if (empty($pendingAttribution)) {
            return;
        }

        $order->update_meta_data('_notifal_pending_attribution', $pendingAttribution);
        $order->save();
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
        if (
            AnalyticsHelper::isPaidWooCommerceOrderStatus($newStatus)
            && !AnalyticsHelper::isPaidWooCommerceOrderStatus($oldStatus)
        ) {
            $this->maybeTrackWooCommerceConversion($orderId);
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
            // Even if no conversion is attributed, clear existing markers after payment completion.
            $this->clearAttributionStorage();
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

        // Clear browser/server attribution markers after a completed EDD payment
        // to prevent accidental reuse in later unrelated orders.
        $this->clearAttributionStorage();
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

        // Build query: logged-in checkout must still match guest session clicks recorded before login
        if ($userId > 0 && $sessionId !== '') {
            $sql = $wpdb->prepare(
                "SELECT * FROM $table
                WHERE product_id IN ($placeholders)
                AND (user_id = %d OR session_id = %s)
                AND click_timestamp >= %s
                AND status = 'pending'
                ORDER BY product_id, click_timestamp DESC",
                array_merge($productIds, [$userId, $sessionId, $cutoffTime])
            );
        } elseif ($userId > 0) {
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
     * Fetch all pending product clicks for a user or guest session.
     *
     * Used at checkout and admin refresh where clicks must be matched without
     * pre-filtering by order product IDs (click-first attribution).
     *
     * @param int    $userId                WordPress user ID (0 for guests)
     * @param string $sessionId             Guest session ID stored on the order
     * @param bool   $applyAttributionWindow When false, include clicks outside the live window
     * @return array<int, array<string, mixed>> Pending click rows
     * @since 2.4.2
     * @author Hossein <hossein@notifal.com>
     */
    private function findAllPendingProductClicksForAttribution(int $userId, string $sessionId, bool $applyAttributionWindow = false): array
    {
        global $wpdb;

        $tables = $this->databaseRepository->getTableNames();
        $table  = $tables['product_clicks'] ?? '';

        if (empty($table)) {
            return [];
        }

        if ($userId > 0 && $sessionId !== '') {
            if ($applyAttributionWindow) {
                $cutoffTime = date('Y-m-d H:i:s', time() - $this->attributionWindow);
                $results    = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$table}
                        WHERE status = 'pending'
                        AND (user_id = %d OR session_id = %s)
                        AND click_timestamp >= %s
                        ORDER BY click_timestamp DESC",
                        $userId,
                        $sessionId,
                        $cutoffTime
                    ),
                    ARRAY_A
                );
            } else {
                $results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$table}
                        WHERE status = 'pending'
                        AND (user_id = %d OR session_id = %s)
                        ORDER BY click_timestamp DESC",
                        $userId,
                        $sessionId
                    ),
                    ARRAY_A
                );
            }
        } elseif ($userId > 0) {
            if ($applyAttributionWindow) {
                $cutoffTime = date('Y-m-d H:i:s', time() - $this->attributionWindow);
                $results    = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$table}
                        WHERE user_id = %d
                        AND status = 'pending'
                        AND click_timestamp >= %s
                        ORDER BY click_timestamp DESC",
                        $userId,
                        $cutoffTime
                    ),
                    ARRAY_A
                );
            } else {
                $results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$table}
                        WHERE user_id = %d
                        AND status = 'pending'
                        ORDER BY click_timestamp DESC",
                        $userId
                    ),
                    ARRAY_A
                );
            }
        } else {
            $resolvedSessionId = $sessionId !== '' ? $sessionId : Helper::getSessionId();

            if ($resolvedSessionId === '') {
                return [];
            }

            if ($applyAttributionWindow) {
                $cutoffTime = date('Y-m-d H:i:s', time() - $this->attributionWindow);
                $results    = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$table}
                        WHERE session_id = %s
                        AND status = 'pending'
                        AND click_timestamp >= %s
                        ORDER BY click_timestamp DESC",
                        $resolvedSessionId,
                        $cutoffTime
                    ),
                    ARRAY_A
                );
            } else {
                $results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$table}
                        WHERE session_id = %s
                        AND status = 'pending'
                        ORDER BY click_timestamp DESC",
                        $resolvedSessionId
                    ),
                    ARRAY_A
                );
            }
        }

        return is_array($results) ? $results : [];
    }

    /**
     * Map a stored product click ID to an order line product or variation ID.
     *
     * @param int        $clickProductId  Product ID stored on the click row
     * @param array<int> $orderProductIds Product IDs collected from the order
     * @return int Matching order product ID or 0 when not in the order
     * @since 2.4.2
     * @author Hossein <hossein@notifal.com>
     */
    private function resolveOrderProductIdForClickProductId(int $clickProductId, array $orderProductIds): int
    {
        if ($clickProductId <= 0 || empty($orderProductIds)) {
            return 0;
        }

        if (in_array($clickProductId, $orderProductIds, true)) {
            return $clickProductId;
        }

        if (!function_exists('wc_get_product')) {
            return 0;
        }

        $clickProduct = wc_get_product($clickProductId);

        if ($clickProduct && $clickProduct->is_type('variation')) {
            $parentId = (int) $clickProduct->get_parent_id();

            if ($parentId > 0 && in_array($parentId, $orderProductIds, true)) {
                return $parentId;
            }
        }

        foreach ($orderProductIds as $orderProductId) {
            $orderProduct = wc_get_product((int) $orderProductId);

            if (!$orderProduct || !$orderProduct->is_type('variation')) {
                continue;
            }

            if ((int) $orderProduct->get_parent_id() === $clickProductId) {
                return (int) $orderProductId;
            }

            if (
                $clickProduct
                && $clickProduct->is_type('variation')
                && (int) $orderProduct->get_parent_id() === (int) $clickProduct->get_parent_id()
            ) {
                return (int) $orderProductId;
            }
        }

        return 0;
    }

    /**
     * Build an order line map keyed by product ID for revenue resolution.
     *
     * @param \WC_Order $order WooCommerce order object
     * @return array<int, array{item_total: float, quantity: int}> Order line map
     * @since 2.4.2
     * @author Hossein <hossein@notifal.com>
     */
    private function buildOrderProductDataMap(\WC_Order $order): array
    {
        $productData = [];

        foreach ($order->get_items() as $item) {
            if (!($item instanceof \WC_Order_Item_Product)) {
                continue;
            }

            $productId = $this->getCorrectProductId($item);

            if ($productId <= 0) {
                continue;
            }

            $productData[$productId] = [
                'item_total' => (float) $item->get_total(),
                'quantity'   => (int) $item->get_quantity(),
            ];
        }

        return $productData;
    }

    /**
     * Append a pending product-click row when not already captured.
     *
     * @param array<int, array<string, mixed>> $pendingRows  Pending rows collected so far
     * @param array<string, bool>              $seenKeys     Dedupe keys notification_product
     * @param \WC_Order                        $order        WooCommerce order
     * @param array<int, array<string, mixed>> $productData  Order line map
     * @param int                              $notificationId Notification ID
     * @param int                              $orderProductId Order line product ID
     * @return void
     * @since 2.4.2
     * @author Hossein <hossein@notifal.com>
     */
    private function appendPendingProductClickRow(
        array &$pendingRows,
        array &$seenKeys,
        \WC_Order $order,
        array $productData,
        int $notificationId,
        int $orderProductId
    ): void {
        if ($notificationId <= 0 || $orderProductId <= 0) {
            return;
        }

        $dedupeKey = $notificationId . '_' . $orderProductId;

        if (isset($seenKeys[$dedupeKey])) {
            return;
        }

        $seenKeys[$dedupeKey] = true;

        $pendingRows[] = [
            'notification_id'   => $notificationId,
            'product_id'          => $orderProductId,
            'product_revenue'     => AnalyticsHelper::resolveOrderLineTotalFromProductData($orderProductId, $productData),
            'total_order_value'   => $this->resolveOrderTotalValue($order),
            'attribution_type'    => 'pending_product_click',
            'is_pending'          => true,
        ];
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

        $notificationId = (int) ($conversionData['notification_id'] ?? 0);
        $productClickId   = (int) ($conversionData['product_click_id'] ?? 0);
        $productId        = (int) ($conversionData['product_id'] ?? 0);

        // Cookie-only rows share product_click_id=0 and product_id=0; use a synthetic click id so the legacy unique index stays distinct per notification
        if ($productClickId <= 0 && $productId <= 0 && $notificationId > 0) {
            $productClickId = 2000000000 + $notificationId;
        }

        // Insert conversion record
        $result = $wpdb->insert(
            $table,
            [
                'notification_id' => $notificationId,
                'product_click_id' => $productClickId,
                'order_id' => (int)($conversionData['order_id'] ?? 0),
                'product_id' => $productId,
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

            // Clicked revenue: line subtotal (price × qty) for verified product-click conversions only
            $revenue        = (float) ($conversionData['product_revenue'] ?? 0);
            $productId      = (int) ($conversionData['product_id'] ?? 0);
            if ($revenue > 0 && $productId > 0) {
                $this->updateDailyRevenue($conversionData['notification_id'], $revenue, $date);
            }

            // Influenced revenue: full order total whenever the order is attributed (clicked or influence-only).
            // updateDailyInfluencedRevenue deduplicates so multi-line orders count the order total once.
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
     * Try order-level cookie/session fallback when no product clicks were attributed.
     *
     * Records a single influence-only conversion (product_id = 0, clicked revenue = 0)
     * so multiple order lines are not incorrectly merged under one notification.
     *
     * @param \WC_Order $order WooCommerce order object
     * @return bool True when a fallback conversion row was stored
     * @since 2.0.2
     * @since 2.4.2 Order-level influence only; defers product clicks to checkout snapshot
     * @author Hossein <hossein@notifal.com>
     */
    private function tryOrderLevelFallbackAttribution(\WC_Order $order): bool
    {
        $orderId = (int) $order->get_id();

        if ($orderId <= 0) {
            return false;
        }

        // Get general attribution data from session/cookies
        $attributionData = $this->getAttributionData();

        if (empty($attributionData)) {
            // Try to get attribution from order meta captured during checkout
            $orderAttributionData = $order->get_meta('_notifal_attribution', true);

            if (!empty($orderAttributionData)) {
                $attributionData = is_string($orderAttributionData)
                    ? json_decode($orderAttributionData, true)
                    : $orderAttributionData;
            }

            if (empty($attributionData)) {
                return false;
            }
        }

        // Find the most recent notification click within attribution window
        $mostRecentNotification = null;
        $mostRecentTimestamp = 0;

        foreach ($attributionData as $notificationId => $data) {
            // Parse timestamp (handle both string and millisecond formats)
            $clickTimestamp = isset($data['timestamp'])
                ? (is_string($data['timestamp']) ? strtotime($data['timestamp']) : (int) ($data['timestamp'] / 1000))
                : time();

            // Keep the newest notification influence marker inside the attribution window
            if ($this->isWithinAttributionWindow((int) $clickTimestamp) && $clickTimestamp > $mostRecentTimestamp) {
                $mostRecentNotification = (int) $notificationId;
                $mostRecentTimestamp = (int) $clickTimestamp;
            }
        }

        if ($mostRecentNotification <= 0) {
            return false;
        }

        // Avoid duplicate influence-only rows for the same notification on one order
        if ($this->conversionExistsForOrderProduct($orderId, $mostRecentNotification, 0)) {
            return false;
        }

        return $this->recordProductConversion([
            'notification_id' => $mostRecentNotification,
            'product_click_id' => 0,
            'order_id' => $orderId,
            'product_id' => 0,
            'product_revenue' => 0.0,
            'total_order_value' => $this->resolveOrderTotalValue($order),
            'currency' => $order->get_currency(),
            'click_timestamp' => date('Y-m-d H:i:s', $mostRecentTimestamp),
            'conversion_timestamp' => current_time('mysql'),
            'attribution_type' => 'fallback',
            'user_id' => (int) $order->get_user_id(),
            'campaign_id' => $this->campaignAttributionResolver->resolveCampaignIdForNotification($mostRecentNotification),
        ]);
    }

    /**
     * Record conversions from the checkout pending attribution snapshot.
     *
     * Used when live click lookup fails (e.g. on-hold -> completed after the attribution window).
     * The snapshot was captured at checkout when influence was already validated.
     *
     * @param \WC_Order $order       WooCommerce order object
     * @param array     $productData Order line items keyed by product ID
     * @return int Number of conversions recorded
     * @since 2.3.5
     * @author Hossein <hossein@notifal.com>
     */
    private function recordConversionsFromPendingSnapshot(\WC_Order $order, array $productData): int
    {
        // Read the pending snapshot stored during checkout
        $pending = $order->get_meta('_notifal_pending_attribution', true);

        if (empty($pending)) {
            return 0;
        }

        if (is_string($pending)) {
            $pending = json_decode($pending, true);
        }

        if (!is_array($pending)) {
            return 0;
        }

        $orderId        = (int) $order->get_id();
        $guestSessionId = $this->resolveOrderAttributionSessionId($order);
        $recorded       = 0;

        foreach ($pending as $pendingRow) {
            if (!is_array($pendingRow)) {
                continue;
            }

            $notificationId = (int) ($pendingRow['notification_id'] ?? 0);
            $productId      = (int) ($pendingRow['product_id'] ?? 0);

            if ($notificationId <= 0) {
                continue;
            }

            // Skip rows that were already converted for this order
            if ($this->conversionExistsForOrderProduct($orderId, $notificationId, $productId)) {
                continue;
            }

            // Resolve line revenue from order totals (handles parent/variation ID differences)
            $itemTotal = AnalyticsHelper::resolveOrderLineTotalFromProductData($productId, $productData);

            $clickId          = 0;
            $clickTimestamp   = current_time('mysql');
            $attributionType  = (string) ($pendingRow['attribution_type'] ?? 'pending_snapshot');

            if ($productId > 0) {
                // Match the original product click without the live attribution window limit
                $storedClick = $this->findStoredProductClickForOrder(
                    $productId,
                    (int) $order->get_user_id(),
                    $guestSessionId,
                    $notificationId
                );

                if (!empty($storedClick)) {
                    $clickId        = (int) ($storedClick['id'] ?? 0);
                    $clickTimestamp = (string) ($storedClick['click_timestamp'] ?? $clickTimestamp);
                    $attributionType = 'woocommerce';
                }
            } else {
                // Cookie/session attribution rows use order meta timestamps when available
                $clickTimestamp = $this->resolveStoredAttributionClickTimestamp($order, $notificationId);
            }

            // Checkout snapshot rows with pending_product_click were validated at checkout
            $isVerifiedProductClick = $productId > 0
                && (
                    $clickId > 0
                    || $attributionType === 'pending_product_click'
                    || $attributionType === 'woocommerce'
                );

            if (!$this->recordProductConversion([
                'notification_id' => $notificationId,
                'product_click_id' => $clickId,
                'order_id' => $orderId,
                'product_id' => $productId,
                'product_revenue' => ($isVerifiedProductClick && $itemTotal > 0) ? $itemTotal : 0.0,
                'total_order_value' => $this->resolveOrderTotalValue($order),
                'currency' => $order->get_currency(),
                'click_timestamp' => $clickTimestamp,
                'conversion_timestamp' => current_time('mysql'),
                'attribution_type' => $attributionType,
                'user_id' => (int) $order->get_user_id(),
                'campaign_id' => $this->campaignAttributionResolver->resolveCampaignIdForNotification($notificationId),
            ])) {
                continue;
            }

            $recorded++;

            if ($clickId > 0) {
                $this->markClickAsConverted($clickId);
            }
        }

        return $recorded;
    }

    /**
     * Check whether a conversion row already exists for an order product pair.
     *
     * @param int $orderId        WooCommerce order ID
     * @param int $notificationId Notification ID
     * @param int $productId      Product ID (0 for cookie attribution)
     * @return bool
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    private function conversionExistsForOrderProduct(int $orderId, int $notificationId, int $productId): bool
    {
        global $wpdb;

        $tables = $this->databaseRepository->getTableNames();
        $table  = $tables['conversions'] ?? '';

        if ($orderId <= 0 || $notificationId <= 0 || empty($table)) {
            return false;
        }

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                WHERE order_id = %d
                AND notification_id = %d
                AND product_id = %d
                LIMIT 1",
                $orderId,
                $notificationId,
                $productId
            )
        );

        return $count > 0;
    }

    /**
     * Find a stored product click for an order without the live attribution window filter.
     *
     * @param int    $productId      Product ID
     * @param int    $userId         WordPress user ID
     * @param string $sessionId      Guest session ID from order meta
     * @param int    $notificationId Notification ID
     * @return array|null
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    private function findStoredProductClickForOrder(int $productId, int $userId, string $sessionId, int $notificationId): ?array
    {
        if ($productId <= 0 || $notificationId <= 0) {
            return null;
        }

        $candidateIds = $this->expandProductIdsForClickLookup([$productId]);

        foreach ($candidateIds as $candidateId) {
            $row = $this->fetchStoredProductClickRow((int) $candidateId, $userId, $sessionId, $notificationId);

            if (is_array($row)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Fetch the latest stored product click row for a single product ID.
     *
     * @param int    $productId      Product ID stored on the click row
     * @param int    $userId         WordPress user ID
     * @param string $sessionId      Guest session ID from order meta
     * @param int    $notificationId Notification ID
     * @return array<string, mixed>|null
     * @since 2.3.9
     */
    private function fetchStoredProductClickRow(int $productId, int $userId, string $sessionId, int $notificationId): ?array
    {
        global $wpdb;

        $tables = $this->databaseRepository->getTableNames();
        $table  = $tables['product_clicks'] ?? '';

        if ($productId <= 0 || $notificationId <= 0 || empty($table)) {
            return null;
        }

        if ($userId > 0) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table}
                    WHERE product_id = %d
                    AND notification_id = %d
                    AND user_id = %d
                    ORDER BY click_timestamp DESC
                    LIMIT 1",
                    $productId,
                    $notificationId,
                    $userId
                ),
                ARRAY_A
            );

            if (is_array($row)) {
                return $row;
            }
        }

        $resolvedSessionId = $sessionId !== '' ? $sessionId : Helper::getSessionId();

        if ($resolvedSessionId === '') {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE product_id = %d
                AND notification_id = %d
                AND session_id = %s
                ORDER BY click_timestamp DESC
                LIMIT 1",
                $productId,
                $notificationId,
                $resolvedSessionId
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Resolve the Notifal session ID stored on an order for attribution lookups.
     *
     * Guest product clicks remain keyed by session_id even when the customer logs in before checkout.
     *
     * @param \WC_Order $order WooCommerce order object
     * @return string Session ID or empty string when unavailable
     * @since 2.4.2
     */
    private function resolveOrderAttributionSessionId(\WC_Order $order): string
    {
        $sessionId = (string) $order->get_meta('_notifal_session_id', true);

        if ($sessionId !== '') {
            return $sessionId;
        }

        return Helper::getSessionId();
    }

    /**
     * Resolve click timestamp from order-stored notification attribution meta.
     *
     * @param \WC_Order $order          WooCommerce order
     * @param int       $notificationId Notification ID
     * @return string MySQL datetime string
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    private function resolveStoredAttributionClickTimestamp(\WC_Order $order, int $notificationId): string
    {
        $attributionData = $order->get_meta('_notifal_attribution', true);

        if (is_string($attributionData)) {
            $attributionData = json_decode($attributionData, true);
        }

        if (!is_array($attributionData) || empty($attributionData[$notificationId])) {
            return current_time('mysql');
        }

        $data = $attributionData[$notificationId];
        $clickTimestamp = isset($data['timestamp'])
            ? (is_string($data['timestamp']) ? strtotime($data['timestamp']) : (int) ($data['timestamp'] / 1000))
            : time();

        return date('Y-m-d H:i:s', (int) $clickTimestamp);
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
        $userId      = (int) $order->get_user_id();

        // Collect product IDs from the order for click lookup
        $productIds  = [];
        $productData = $this->buildOrderProductDataMap($order);

        foreach (array_keys($productData) as $orderProductId) {
            $productIds[] = (int) $orderProductId;
        }

        $productIds = array_values(array_unique(array_filter($productIds)));

        // Phase 1: click-first lookup (covers async click race and parent/variation IDs)
        $allPendingClicks = $this->findAllPendingProductClicksForAttribution($userId, $sessionId, false);

        foreach ($allPendingClicks as $clickRow) {
            $clickProductId = (int) ($clickRow['product_id'] ?? 0);
            $notificationId = (int) ($clickRow['notification_id'] ?? 0);
            $orderProductId = $this->resolveOrderProductIdForClickProductId($clickProductId, $productIds);

            $this->appendPendingProductClickRow(
                $pendingRows,
                $seenKeys,
                $order,
                $productData,
                $notificationId,
                $orderProductId
            );
        }

        if (!empty($productIds)) {
            // Phase 2: order-line lookup within the live attribution window (legacy path)
            $lookupProductIds = $this->expandProductIdsForClickLookup($productIds);
            $attributedClicks = $this->findAttributedProductClicksBulk(
                $lookupProductIds,
                $userId,
                $sessionId
            );

            foreach ($productIds as $orderProductId) {
                $clicks = $this->resolveAttributedClicksForOrderProductId((int) $orderProductId, $attributedClicks);

                if (empty($clicks[0])) {
                    continue;
                }

                $clickRow       = $clicks[0];
                $notificationId = (int) ($clickRow['notification_id'] ?? 0);

                $this->appendPendingProductClickRow(
                    $pendingRows,
                    $seenKeys,
                    $order,
                    $productData,
                    $notificationId,
                    (int) $orderProductId
                );
            }
        }

        // Phase 3: cookie/session notification attribution when no product click exists
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

            // Skip cookie influence when this notification already has a product click row
            $hasProductClickForNotification = false;

            foreach ($seenKeys as $seenKey => $seenValue) {
                if (strpos((string) $seenKey, $notificationId . '_') === 0 && $seenKey !== $notificationId . '_0') {
                    $hasProductClickForNotification = true;
                    break;
                }
            }

            if ($hasProductClickForNotification) {
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
     * Clear server-readable attribution storage after order completion.
     *
     * Removes PHP session attribution and browser cookies that feed fallback attribution.
     * This prevents old notification clicks from being reused in future orders.
     *
     * @return void
     * @since 2.3.5
     * @author Hossein <hossein@notifal.com>
     */
    private function clearAttributionStorage(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (isset($_SESSION['notifal_attribution'])) {
            unset($_SESSION['notifal_attribution']);
        }

        foreach ($_COOKIE as $name => $value) {
            if (strpos($name, 'notifal_attr_') !== 0) {
                continue;
            }

            // Expire the cookie for the default and site-specific cookie paths.
            setcookie($name, '', time() - HOUR_IN_SECONDS, COOKIEPATH ?: '/');

            if (defined('SITECOOKIEPATH') && SITECOOKIEPATH && SITECOOKIEPATH !== COOKIEPATH) {
                setcookie($name, '', time() - HOUR_IN_SECONDS, SITECOOKIEPATH);
            }

            unset($_COOKIE[$name]);
        }
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
     * Expand order product IDs with parent IDs for variable products.
     *
     * Post Link clicks store the parent product ID while orders may contain variation IDs.
     *
     * @param array<int> $productIds Order line product IDs
     * @return array<int> Unique product and parent IDs for click lookup
     * @since 2.3.9
     */
    private function expandProductIdsForClickLookup(array $productIds): array
    {
        $expanded = [];

        foreach ($productIds as $productId) {
            $productId = (int) $productId;

            if ($productId <= 0) {
                continue;
            }

            $expanded[$productId] = $productId;

            if (!function_exists('wc_get_product')) {
                continue;
            }

            $product = wc_get_product($productId);

            if (!$product || !$product->is_type('variation')) {
                continue;
            }

            $parentId = (int) $product->get_parent_id();

            if ($parentId > 0) {
                $expanded[$parentId] = $parentId;
            }
        }

        return array_values($expanded);
    }

    /**
     * Resolve attributed product clicks for an order line item.
     *
     * Falls back to the parent product ID when the click was stored against a variable product parent.
     *
     * @param int   $orderProductId   Product or variation ID from the order line
     * @param array $attributedClicks Clicks grouped by stored product_id
     * @return array<int, array<string, mixed>> Matching click rows
     * @since 2.3.9
     */
    private function resolveAttributedClicksForOrderProductId(int $orderProductId, array $attributedClicks): array
    {
        if ($orderProductId <= 0) {
            return [];
        }

        if (!empty($attributedClicks[$orderProductId])) {
            return $attributedClicks[$orderProductId];
        }

        if (!function_exists('wc_get_product')) {
            return [];
        }

        $product = wc_get_product($orderProductId);

        if (!$product || !$product->is_type('variation')) {
            return [];
        }

        $parentId = (int) $product->get_parent_id();

        if ($parentId > 0 && !empty($attributedClicks[$parentId])) {
            return $attributedClicks[$parentId];
        }

        return [];
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
