<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Analytics;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Shared\Config\Paths;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\UrlService;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Repositories\DatabaseRepository;

defined('ABSPATH') || exit;

/**
 * Class OrderAttributionService
 *
 * Handles displaying Notifal attribution data on WooCommerce and EDD order admin pages.
 * Shows whether an order was influenced by Notifal in the order list column and
 * in the single-order meta box (including per-line-item click attribution).
 *
 * Supports:
 * - WooCommerce legacy post-type-based orders (woocommerce < 8.0 without HPOS)
 * - WooCommerce HPOS orders stored in the wc_orders table
 * - Easy Digital Downloads payments
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services\Analytics
 * @since 2.3.0
 * @author Hossein <hossein@notifal.com>
 */
class OrderAttributionService
{
    /**
     * Tracks rendered order list cells to prevent duplicate output per request.
     *
     * @var array<string, bool>
     * @since 2.3.0
     */
    private static $renderedOrderListCells = [];

    /**
     * Tracks rendered single-order meta boxes to prevent duplicate output per request.
     *
     * @var array<string, bool>
     * @since 2.3.0
     */
    private static $renderedOrderMetaBoxes = [];

    /**
     * Database repository instance.
     *
     * @var DatabaseRepository
     * @since 2.3.0
     */
    private $databaseRepository;

    /**
     * URL helper for notification edit links.
     *
     * @var UrlService
     * @since 2.3.0
     */
    private $urlService;

    /**
     * Constructor.
     *
     * @param DatabaseRepository $databaseRepository Database repository instance
     * @param UrlService         $urlService         OnPage notification URL helper
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public function __construct(DatabaseRepository $databaseRepository, UrlService $urlService)
    {
        $this->databaseRepository = $databaseRepository;
        $this->urlService         = $urlService;
    }

    /**
     * Register all hooks for order attribution display.
     *
     * Called once from the ServiceProvider to attach admin hooks.
     *
     * @return void
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function register(): void
    {
        // Guard: only execute on admin pages to avoid frontend performance impact
        if (!is_admin()) {
            return;
        }

        // Resolve from container so dependencies (UrlService, etc.) are injected correctly
        $instance = notifal_app(self::class);

        // WooCommerce integration hooks
        if (PluginDetector::isWooCommerceActive()) {
            $instance->registerWooCommerceHooks();
        }

        // EDD integration hooks
        if (PluginDetector::isEDDActive()) {
            $instance->registerEDDHooks();
        }
    }

    /**
     * Register all WooCommerce-specific hooks for order attribution.
     *
     * Supports both legacy (post-type) and HPOS (wc_orders) order storage.
     *
     * @return void
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    private function registerWooCommerceHooks(): void
    {
        // Enqueue attribution styles on WooCommerce order admin pages
        add_action('admin_enqueue_scripts', [$this, 'enqueueOrderAttributionAssets']);

        // Register list column for HPOS or legacy storage only (never both — prevents duplicate cells)
        if ($this->isWooCommerceHposEnabled()) {
            add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'addOrderListColumn']);
            add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'renderOrderListColumnHpos'], 10, 2);
        } else {
            add_filter('manage_edit-shop_order_columns', [$this, 'addOrderListColumn']);
            add_action('manage_shop_order_posts_custom_column', [$this, 'renderOrderListColumnLegacy'], 10, 2);
        }

        // Single order edit: sidebar meta box only (no inline banner — avoids duplicate with WC order details)
        add_action('add_meta_boxes', [$this, 'addOrderMetaBox']);
    }

    /**
     * Register EDD-specific hooks for payment attribution display.
     *
     * @return void
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    private function registerEDDHooks(): void
    {
        // EDD payment history: add custom column
        add_filter('edd_payments_table_columns', [$this, 'addEddPaymentListColumn']);
        add_action('edd_payments_table_column', [$this, 'renderEddPaymentListColumn'], 10, 3);

        // EDD single payment details page
        add_action('edd_view_order_details_billing_after', [$this, 'renderEddPaymentDetails']);
    }

    // =========================================================================
    // Asset Enqueueing
    // =========================================================================

    /**
     * Enqueue order attribution CSS on WooCommerce and EDD order admin pages.
     *
     * @return void
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public function enqueueOrderAttributionAssets(): void
    {
        // Detect the current admin screen
        $screen = get_current_screen();

        if (!$screen) {
            return;
        }

        // Determine if we are on a WooCommerce or EDD order/payment admin page
        $isWooOrderPage = in_array($screen->id, ['shop_order', 'woocommerce_page_wc-orders'], true)
            || (isset($screen->post_type) && $screen->post_type === 'shop_order');

        $isEddPage = in_array($screen->id, ['download-activity', 'edd-payment-history', 'edd_payment_history'], true)
            || strpos($screen->id, 'edd') !== false;

        if (!$isWooOrderPage && !$isEddPage) {
            return;
        }

        // Enqueue icons and order attribution stylesheet (built via Vite)
        notifal_enqueue_style(
            'notifal-order-attribution',
            Paths::cssAdminBuildUrl() . 'OrderAttributionStyle.css',
            ['notifal-icons']
        );
    }

    // =========================================================================
    // WooCommerce: Order List Column
    // =========================================================================

    /**
     * Add a Notifal column to the WooCommerce orders list table.
     *
     * @param array $columns Existing columns
     * @return array Modified columns with Notifal column added
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public function addOrderListColumn(array $columns): array
    {
        // Insert before the last column (actions) for better UX
        $newColumns = [];
        foreach ($columns as $key => $label) {
            // Insert before the 'wc_actions' column
            if ($key === 'wc_actions') {
                $newColumns['notifal_influenced'] = esc_html__('Notifal', 'notifal');
            }
            $newColumns[$key] = $label;
        }

        // If 'wc_actions' not found, append at the end
        if (!isset($newColumns['notifal_influenced'])) {
            $newColumns['notifal_influenced'] = esc_html__('Notifal', 'notifal');
        }

        return $newColumns;
    }

    /**
     * Render the Notifal column value for HPOS-based WooCommerce orders.
     *
     * @param string    $column  The current column key
     * @param \WC_Order $order   WooCommerce order object
     * @return void
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public function renderOrderListColumnHpos(string $column, $order): void
    {
        if ($column !== 'notifal_influenced') {
            return;
        }

        if (!($order instanceof \WC_Order)) {
            return;
        }

        $this->renderOrderInfluenceBadge((int) $order->get_id());
    }

    /**
     * Render the Notifal column value for legacy post-type WooCommerce orders.
     *
     * @param string $column  The current column key
     * @param int    $postId  The order post ID
     * @return void
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public function renderOrderListColumnLegacy(string $column, int $postId): void
    {
        if ($column !== 'notifal_influenced') {
            return;
        }

        $this->renderOrderInfluenceBadge($postId);
    }

    /**
     * Render a compact influence badge for the order list.
     *
     * Shows a green icon if the order was influenced by notifal, dash otherwise.
     *
     * @param int $orderId WooCommerce order ID
     * @return void
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    private function renderOrderInfluenceBadge(int $orderId): void
    {
        $renderKey = 'order_' . $orderId;

        if (isset(self::$renderedOrderListCells[$renderKey])) {
            return;
        }

        self::$renderedOrderListCells[$renderKey] = true;

        $attributionData = $this->getOrderAttributionData($orderId);

        if (empty($attributionData)) {
            echo '<span class="notifal-order-list-attribution notifal-order-list-attribution--empty">&mdash;</span>';
            return;
        }

        $notificationIds = array_values(array_unique(array_map('intval', array_column($attributionData, 'notification_id'))));
        $primaryId       = (int) ($notificationIds[0] ?? 0);
        $rawTitle        = $primaryId > 0 ? (string) get_the_title($primaryId) : '';
        $title           = $this->truncateNotificationTitle($rawTitle !== '' ? $rawTitle : __('(deleted notification)', 'notifal'));
        $extraCount      = count($notificationIds) - 1;
        $tooltip         = $this->buildOrderListAttributionTooltip($notificationIds);
        $isPendingOnly   = $this->isPendingAttributionOnly($attributionData);

        if ($isPendingOnly) {
            $tooltip .= "\n\n" . __(
                'This order was influenced by Notifal but is not paid yet, so it is not counted in analytics revenue.',
                'notifal'
            );
        }

        $editUrl = $primaryId > 0 ? $this->urlService->getEditNotificationUrl($primaryId) : '';

        $wrapperClass = 'notifal-order-list-attribution';

        if ($isPendingOnly) {
            $wrapperClass .= ' notifal-order-list-attribution--pending';
        }

        echo '<div class="' . esc_attr($wrapperClass) . '" title="' . esc_attr($tooltip) . '">';

        if ($editUrl !== '') {
            echo '<a href="' . esc_url($editUrl) . '" class="notifal-order-list-attribution__id" onclick="event.stopPropagation();">'
                . '#' . esc_html((string) $primaryId) . '</a>';
        } else {
            echo '<span class="notifal-order-list-attribution__id">#' . esc_html((string) $primaryId) . '</span>';
        }

        echo '<span class="notifal-order-list-attribution__title">' . esc_html($title);

        if ($extraCount > 0) {
            echo ' <span class="notifal-order-list-attribution__more">+' . esc_html((string) $extraCount) . '</span>';
        }

        echo '</span></div>';
    }

    /**
     * Check whether WooCommerce HPOS (custom order tables) is enabled.
     *
     * @return bool
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    private function isWooCommerceHposEnabled(): bool
    {
        if (!class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            return false;
        }

        return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }

    // =========================================================================
    // WooCommerce: Single Order Meta Box
    // =========================================================================

    /**
     * Register the Notifal meta box on the single WooCommerce order admin page.
     *
     * Registers for both legacy (shop_order) and HPOS (wc-orders) screens.
     *
     * @return void
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public function addOrderMetaBox(): void
    {
        // Register on the active order screen only (HPOS or legacy — never both)
        $screenId = $this->isWooCommerceHposEnabled()
            ? 'woocommerce_page_wc-orders'
            : 'shop_order';

        add_meta_box(
            'notifal_order_attribution',
            esc_html__('Notifal Attribution', 'notifal'),
            [$this, 'renderOrderMetaBoxContent'],
            $screenId,
            'side',
            'default'
        );
    }

    /**
     * Render the Notifal attribution meta box content for WooCommerce single order.
     *
     * Shows which notification influenced the order and which line items were clicked.
     * Works for both legacy and HPOS order storage.
     *
     * @param \WP_Post|\WC_Order $postOrOrder Post object (legacy) or WC_Order (HPOS)
     * @return void
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public function renderOrderMetaBoxContent($postOrOrder): void
    {
        // Resolve order ID from either a WP_Post or WC_Order object
        if ($postOrOrder instanceof \WC_Order) {
            $orderId = (int) $postOrOrder->get_id();
        } else {
            $orderId = (int) $postOrOrder->ID;
        }

        $metaBoxKey = 'order_meta_' . $orderId;

        if (isset(self::$renderedOrderMetaBoxes[$metaBoxKey])) {
            return;
        }

        self::$renderedOrderMetaBoxes[$metaBoxKey] = true;

        $attributionData = $this->getOrderAttributionData($orderId);

        if (empty($attributionData)) {
            echo '<p class="notifal-order-meta-no-attribution">'
                . esc_html__('This order was not influenced by any Notifal notification.', 'notifal')
                . '</p>';
            return;
        }

        if ($this->isPendingAttributionOnly($attributionData)) {
            echo '<p class="notifal-order-meta-pending-notice">'
                . esc_html__(
                    'This order was influenced by Notifal but is not paid yet. It will be counted in analytics revenue once payment is completed.',
                    'notifal'
                )
                . '</p>';
        }

        echo '<div class="notifal-attribution-metabox">';

        foreach ($attributionData as $index => $row) {
            if ($index > 0) {
                echo '<hr class="notifal-attr-divider" />';
            }

            $notificationId = (int) ($row['notification_id'] ?? 0);
            $notifTitle     = get_the_title($notificationId) ?: __('(deleted notification)', 'notifal');
            $productName    = $row['product_name'] ?? __('(unknown product)', 'notifal');
            $productRevenue = (float) ($row['product_revenue'] ?? 0);
            $orderTotal     = (float) ($row['total_order_value'] ?? 0);
            $editUrl        = $notificationId > 0 ? $this->urlService->getEditNotificationUrl($notificationId) : '';

            echo '<div class="notifal-attr-row">';

            echo '<div class="notifal-attr-line notifal-attr-line--notification">';
            echo '<span class="notifal-icon notifal-icon-megaphone" aria-hidden="true"></span>';
            echo '<div class="notifal-attr-line__content">';
            echo '<span class="notifal-attr-label">' . esc_html__('Notification', 'notifal') . '</span>';
            echo '<span class="notifal-attr-value">';
            if ($editUrl !== '') {
                echo '<a href="' . esc_url($editUrl) . '">';
                echo '#' . esc_html((string) $notificationId) . ' &mdash; ' . esc_html($notifTitle);
                echo '</a>';
            } else {
                echo '#' . esc_html((string) $notificationId) . ' &mdash; ' . esc_html($notifTitle);
            }
            echo '</span></div></div>';

            echo '<div class="notifal-attr-line">';
            echo '<span class="notifal-icon notifal-icon-cursor" aria-hidden="true"></span>';
            echo '<div class="notifal-attr-line__content">';
            echo '<span class="notifal-attr-label">' . esc_html__('Clicked product', 'notifal') . '</span>';
            echo '<span class="notifal-attr-product-name">' . esc_html($productName) . '</span>';
            echo '</div></div>';

            if ($productRevenue > 0 && function_exists('wc_price')) {
                echo '<div class="notifal-attr-line">';
                echo '<span class="notifal-icon notifal-icon-coin" aria-hidden="true"></span>';
                echo '<div class="notifal-attr-line__content">';
                echo '<span class="notifal-attr-label">' . esc_html__('Clicked revenue', 'notifal') . '</span>';
                echo '<span class="notifal-attr-product-revenue">' . wp_kses_post(wc_price($productRevenue)) . '</span>';
                echo '</div></div>';
            }

            if ($orderTotal > 0 && function_exists('wc_price')) {
                echo '<div class="notifal-attr-line">';
                echo '<span class="notifal-icon notifal-icon-bag-check" aria-hidden="true"></span>';
                echo '<div class="notifal-attr-line__content">';
                echo '<span class="notifal-attr-label">' . esc_html__('Order total', 'notifal') . '</span>';
                echo '<span class="notifal-attr-product-revenue">' . wp_kses_post(wc_price($orderTotal)) . '</span>';
                echo '</div></div>';
            }

            echo '</div>';
        }

        echo '</div>';
    }

    // =========================================================================
    // EDD: Payment List Column
    // =========================================================================

    /**
     * Add Notifal column to EDD payments list table.
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public function addEddPaymentListColumn(array $columns): array
    {
        $columns['notifal_influenced'] = esc_html__('Notifal', 'notifal');
        return $columns;
    }

    /**
     * Render Notifal column value in EDD payments list.
     *
     * @param \EDD_Payment $payment    EDD payment object
     * @param string       $column_id  Column key
     * @param int          $value      Column value (unused here)
     * @return void
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public function renderEddPaymentListColumn($payment, string $column_id, int $value): void
    {
        if ($column_id !== 'notifal_influenced') {
            return;
        }

        $paymentId = is_object($payment) ? (int) $payment->ID : (int) $payment;
        $this->renderOrderInfluenceBadge($paymentId);
    }

    /**
     * Render Notifal attribution section on EDD single payment details page.
     *
     * @param int $paymentId EDD payment ID
     * @return void
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public function renderEddPaymentDetails(int $paymentId): void
    {
        $attributionData = $this->getOrderAttributionData($paymentId);

        if (empty($attributionData)) {
            return;
        }

        echo '<div class="edd-order-overview-addresses notifal-edd-attribution">';
        echo '<div class="edd-order-overview-addresses__title">' . esc_html__('Notifal Attribution', 'notifal') . '</div>';
        echo '<div class="edd-order-overview-addresses__address">';

        echo '<div class="notifal-attribution-metabox">';

        foreach ($attributionData as $index => $row) {
            if ($index > 0) {
                echo '<hr class="notifal-attr-divider" />';
            }

            $notificationId = (int) ($row['notification_id'] ?? 0);
            $notifTitle     = get_the_title($notificationId) ?: __('(deleted notification)', 'notifal');
            $productName    = $row['product_name'] ?? __('(unknown product)', 'notifal');
            $revenue        = (float) ($row['product_revenue'] ?? 0);
            $editUrl = $notificationId > 0 ? $this->urlService->getEditNotificationUrl($notificationId) : '';

            echo '<div class="notifal-attr-row">';

            echo '<div class="notifal-attr-line notifal-attr-line--notification">';
            echo '<span class="notifal-icon notifal-icon-megaphone" aria-hidden="true"></span>';
            echo '<div class="notifal-attr-line__content">';
            echo '<span class="notifal-attr-label">' . esc_html__('Notification', 'notifal') . '</span>';
            echo '<span class="notifal-attr-value">';
            if ($editUrl !== '') {
                echo '<a href="' . esc_url($editUrl) . '">' . esc_html($notifTitle) . '</a>';
            } else {
                echo esc_html($notifTitle);
            }
            echo '</span></div></div>';

            echo '<div class="notifal-attr-line">';
            echo '<span class="notifal-icon notifal-icon-cursor" aria-hidden="true"></span>';
            echo '<div class="notifal-attr-line__content">';
            echo '<span class="notifal-attr-label">' . esc_html__('Clicked product', 'notifal') . '</span>';
            echo '<span class="notifal-attr-product-name">' . esc_html($productName) . '</span>';
            echo '</div></div>';

            if ($revenue > 0 && function_exists('edd_currency_filter') && function_exists('edd_format_amount')) {
                echo '<div class="notifal-attr-line">';
                echo '<span class="notifal-icon notifal-icon-coin" aria-hidden="true"></span>';
                echo '<div class="notifal-attr-line__content">';
                echo '<span class="notifal-attr-label">' . esc_html__('Clicked revenue', 'notifal') . '</span>';
                echo '<span class="notifal-attr-product-revenue">' . esc_html(edd_currency_filter(edd_format_amount($revenue))) . '</span>';
                echo '</div></div>';
            }

            echo '</div>';
        }

        echo '</div>';

        echo '</div></div>';
    }

    // =========================================================================
    // Data Access Methods
    // =========================================================================

    /**
     * Check if a given order/payment was influenced by any Notifal notification.
     *
     * Queries the conversions table for any row matching the order_id.
     *
     * @param int $orderId WooCommerce order ID or EDD payment ID
     * @return bool True if order was influenced by Notifal
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public function isOrderInfluencedByNotifal(int $orderId): bool
    {
        if ($orderId <= 0) {
            return false;
        }

        global $wpdb;

        $tables = $this->databaseRepository->getTableNames();
        $table  = $tables['conversions'] ?? '';

        if (empty($table) || $wpdb->get_var("SHOW TABLES LIKE \"{$table}\"") !== $table) {
            return false;
        }

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE order_id = %d LIMIT 1",
                $orderId
            )
        );

        return $count > 0;
    }

    /**
     * Get full attribution data for an order/payment.
     *
     * Returns all conversion rows for the order, enriched with product names.
     *
     * @param int $orderId WooCommerce order ID or EDD payment ID
     * @return array Attribution rows, each with notification_id, product_id, product_name, product_revenue, attribution_type
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public function getOrderAttributionData(int $orderId): array
    {
        if ($orderId <= 0) {
            return [];
        }

        global $wpdb;

        $tables = $this->databaseRepository->getTableNames();
        $table  = $tables['conversions'] ?? '';
        $rows   = [];

        if (!empty($table) && $wpdb->get_var("SHOW TABLES LIKE \"{$table}\"") === $table) {
            // Get all conversion rows for this order (paid / recorded conversions)
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT notification_id, product_id, product_revenue, total_order_value, attribution_type
                    FROM {$table}
                    WHERE order_id = %d
                    ORDER BY product_id ASC",
                    $orderId
                ),
                ARRAY_A
            );

            if (!is_array($rows)) {
                $rows = [];
            }
        }

        // Merge pending attribution stored at checkout for unpaid orders (on-hold, pending, etc.)
        $rows = $this->mergePendingAttributionRows($orderId, $rows);

        if (empty($rows)) {
            return [];
        }

        // Enrich with product name
        foreach ($rows as &$row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId > 0 && function_exists('wc_get_product')) {
                // WooCommerce product name
                $product = wc_get_product($productId);
                $row['product_name'] = $product ? $product->get_name() : __('(unknown product)', 'notifal');
            } elseif ($productId > 0 && function_exists('edd_get_download')) {
                // EDD download name
                $download = edd_get_download($productId);
                $row['product_name'] = $download ? get_the_title($productId) : __('(unknown product)', 'notifal');
            } else {
                $row['product_name'] = __('(unknown product)', 'notifal');
            }

            // Backfill order total when legacy conversions rows stored 0 for total_order_value
            if ((float) ($row['total_order_value'] ?? 0) <= 0 && function_exists('wc_get_order')) {
                $order = wc_get_order($orderId);

                if ($order) {
                    $row['total_order_value'] = (float) $order->get_total('edit');
                }
            }
        }
        unset($row);

        /**
         * Filter the order attribution data before it is displayed.
         *
         * @hook notifal/order_attribution/data
         * @param array $rows     Enriched conversion rows for the order.
         * @param int   $orderId  WooCommerce order ID or EDD payment ID.
         * @return array
         * @since 2.3.0
         */
        return (array) apply_filters(FilterHooks::ORDER_ATTRIBUTION_DATA, $rows, $orderId);
    }

    /**
     * Merge pending checkout attribution with paid conversion rows.
     *
     * @param int   $orderId WooCommerce order ID
     * @param array $rows    Existing conversion rows
     * @return array Combined rows
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    private function mergePendingAttributionRows(int $orderId, array $rows): array
    {
        if (!function_exists('wc_get_order')) {
            return $rows;
        }

        $order = wc_get_order($orderId);

        if (!$order) {
            return $rows;
        }

        // Skip pending data when conversion was already processed
        if ($order->get_meta('_notifal_conversion_processed', true)) {
            return $rows;
        }

        $pending = $order->get_meta('_notifal_pending_attribution', true);

        if (empty($pending)) {
            return $rows;
        }

        if (is_string($pending)) {
            $pending = json_decode($pending, true);
        }

        if (!is_array($pending)) {
            return $rows;
        }

        $existingKeys = [];

        foreach ($rows as $row) {
            $notificationId = (int) ($row['notification_id'] ?? 0);
            $productId = (int) ($row['product_id'] ?? 0);
            $existingKeys[$notificationId . '_' . $productId] = true;
        }

        foreach ($pending as $pendingRow) {
            if (!is_array($pendingRow)) {
                continue;
            }

            $notificationId = (int) ($pendingRow['notification_id'] ?? 0);
            $productId = (int) ($pendingRow['product_id'] ?? 0);
            $dedupeKey = $notificationId . '_' . $productId;

            if ($notificationId <= 0 || isset($existingKeys[$dedupeKey])) {
                continue;
            }

            $existingKeys[$dedupeKey] = true;
            $pendingRow['is_pending'] = true;
            $rows[] = $pendingRow;
        }

        return $rows;
    }

    /**
     * Whether attribution rows are pending only (not yet counted in revenue).
     *
     * @param array $attributionData Attribution rows for the order
     * @return bool
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    private function isPendingAttributionOnly(array $attributionData): bool
    {
        if (empty($attributionData)) {
            return false;
        }

        foreach ($attributionData as $row) {
            if (empty($row['is_pending'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Truncate a notification title for compact table display.
     *
     * @param string $title Raw notification title
     * @param int    $max   Maximum character length before truncation
     * @return string
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    private function truncateNotificationTitle(string $title, int $max = 36): string
    {
        $title = wp_strip_all_tags($title);

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($title) <= $max) {
                return $title;
            }

            return mb_substr($title, 0, $max) . '…';
        }

        if (strlen($title) <= $max) {
            return $title;
        }

        return substr($title, 0, $max) . '…';
    }

    /**
     * Build a tooltip string listing all notifications that influenced an order.
     *
     * @param int[] $notificationIds Unique notification IDs
     * @return string
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    private function buildOrderListAttributionTooltip(array $notificationIds): string
    {
        $parts = [];

        foreach ($notificationIds as $notificationId) {
            $title   = get_the_title((int) $notificationId) ?: __('(deleted notification)', 'notifal');
            $parts[] = '#' . (int) $notificationId . ' — ' . wp_strip_all_tags($title);
        }

        return implode("\n", $parts);
    }
}
