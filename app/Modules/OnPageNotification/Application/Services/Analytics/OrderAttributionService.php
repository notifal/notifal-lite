<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Analytics;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Admin\Localization\LangLoader;
use Notifal\Infrastructure\WordPress\Security\NonceManager;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Core\Support\Helpers\UrlHelper;
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

        // Enqueue shared modal styles, icons, attribution CSS, and popup script (built via Vite)
        notifal_enqueue_style(
            'notifal-shared-admin-css',
            Paths::cssAdminBuildUrl() . 'SharedAdminStyle.css',
            []
        );

        notifal_enqueue_style(
            'notifal-order-attribution',
            Paths::cssAdminBuildUrl() . 'OrderAttributionStyle.css',
            ['notifal-icons', 'notifal-shared-admin-css']
        );

        notifal_enqueue_script(
            'notifal-order-attribution',
            Paths::jsAdminBuildUrl() . 'OrderAttributionScript.js',
            [],
            [
                'ajaxUrl' => UrlHelper::baseAjax(),
                'nonce'   => NonceManager::create('notifal_order_attribution_nonce'),
            ],
            'NotifalOrderAttributionConfig'
        );

        wp_localize_script(
            'notifal-order-attribution',
            'NotifalOrderAttributionStrings',
            LangLoader::load(__NAMESPACE__, 'order-attribution.php')
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

        // Base wrapper class; pending orders get a distinct amber badge treatment
        // "no-link" tells WooCommerce not to navigate to the order when this cell is clicked
        $wrapperClass = 'notifal-order-list-attribution no-link';

        if ($isPendingOnly) {
            $wrapperClass .= ' notifal-order-list-attribution--pending';
        }

        // Short label shown inside the gradient pill badge
        $badgeLabel = $isPendingOnly
            ? __('Pending', 'notifal')
            : __('Converted', 'notifal');

        // Icon glyph for the emblem overlay (check = paid, clock = unpaid pending)
        $overlayIconClass = $isPendingOnly ? 'notifal-icon-clock-history' : 'notifal-icon-check';

        echo '<div class="' . esc_attr($wrapperClass) . '" data-order-id="' . esc_attr((string) $orderId) . '" title="' . esc_attr($tooltip) . '">';

        // Eye-catching pill badge: Notifal logo + status overlay + label (opens details popup on click)
        echo '<span class="notifal-order-list-attribution__badge" aria-label="'
            . esc_attr(
                sprintf(
                    /* translators: %s: notification title */
                    __('Notifal influenced this order - %s', 'notifal'),
                    wp_strip_all_tags($rawTitle !== '' ? $rawTitle : __('(deleted notification)', 'notifal'))
                )
            )
            . '">';

        echo '<span class="notifal-order-list-attribution__emblem" aria-hidden="true">';
        echo '<span class="notifal-icon notifal-icon-logo"></span>';
        echo '<span class="notifal-order-list-attribution__overlay notifal-icon ' . esc_attr($overlayIconClass) . '"></span>';
        echo '</span>';
        echo '<span class="notifal-order-list-attribution__status">' . esc_html($badgeLabel) . '</span>';
        echo '</span>';

        // Compact notification reference below the badge
        echo '<div class="notifal-order-list-attribution__details">';

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

        echo '</span></div></div>';
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

        echo $this->buildAttributionDetailsHtml($orderId);
    }

    /**
     * Build attribution details HTML for the meta box and order list popup.
     *
     * @param int $orderId WooCommerce order ID or EDD payment ID
     * @return string Attribution markup
     * @since 2.3.5
     * @author Hossein <hossein@notifal.com>
     */
    public function buildAttributionDetailsHtml(int $orderId): string
    {
        $attributionData = $this->getOrderAttributionData($orderId);

        ob_start();

        /**
         * Fires before rendering order attribution details markup.
         *
         * @param int $orderId WooCommerce order ID or EDD payment ID.
         * @since 2.3.5
         */
        do_action(ActionHooks::ORDER_ATTRIBUTION_BEFORE_RENDER, $orderId);

        if (empty($attributionData)) {
            echo '<p class="notifal-order-meta-no-attribution">'
                . esc_html__('This order was not influenced by any Notifal notification.', 'notifal')
                . '</p>';
        } else {
            if ($this->isPendingAttributionOnly($attributionData)) {
                echo '<p class="notifal-order-meta-pending-notice">'
                    . esc_html__(
                        'This order was influenced by Notifal but is not paid yet. It will be counted in analytics revenue once payment is completed.',
                        'notifal'
                    )
                    . '</p>';
            }

            $this->renderAttributionRowsHtml($attributionData);
        }

        /**
         * Fires after rendering order attribution details markup.
         *
         * @param int $orderId WooCommerce order ID or EDD payment ID.
         * @since 2.3.5
         */
        do_action(ActionHooks::ORDER_ATTRIBUTION_AFTER_RENDER, $orderId);

        return (string) ob_get_clean();
    }

    /**
     * Render attribution summary and optional detail rows for meta box and popup.
     *
     * @param array $attributionData Enriched conversion rows for the order
     * @return void
     * @since 2.3.5
     * @author Hossein <hossein@notifal.com>
     */
    private function renderAttributionRowsHtml(array $attributionData): void
    {
        // Build once-per-order summary totals for the compact summary card
        $summary = $this->buildAttributionSummary($attributionData);

        echo '<div class="notifal-attribution-metabox">';

        // Summary card: influenced notifications, clicked products, revenue totals
        echo '<div class="notifal-attribution-summary">';

        $this->renderSummaryNotificationsList($summary);

        $this->renderSummaryProductsList($summary);

        $this->renderSummaryLine(
            'coin',
            __('Total clicked revenue', 'notifal'),
            $this->formatRevenueAmount($summary['total_clicked_revenue'])
        );

        $this->renderSummaryLine(
            'bag-check',
            __('Total influenced revenue', 'notifal'),
            $this->formatRevenueAmount($summary['total_influenced_revenue'])
        );

        echo '</div>';

        // Toggle expands per-notification detail rows without repeating order totals
        echo '<button type="button" class="notifal-attribution-details-toggle" aria-expanded="false">';
        echo esc_html__('Show details', 'notifal');
        echo '</button>';

        echo '<div class="notifal-attribution-details" hidden>';

        foreach ($attributionData as $index => $row) {
            if ($index > 0) {
                echo '<hr class="notifal-attr-divider" />';
            }

            $this->renderAttributionDetailRow($row);
        }

        echo '</div></div>';
    }

    /**
     * Render a single summary metric line inside the attribution summary card.
     *
     * @param string $iconClass Icon suffix without the notifal-icon- prefix
     * @param string $label     Metric label
     * @param string $value     Metric value (already escaped when needed)
     * @return void
     * @since 2.3.7
     * @author Hossein <hossein@notifal.com>
     */
    private function renderSummaryLine(string $iconClass, string $label, string $value): void
    {
        echo '<div class="notifal-attr-line notifal-attr-line--summary">';
        echo '<span class="notifal-icon notifal-icon-' . esc_attr($iconClass) . '" aria-hidden="true"></span>';
        echo '<div class="notifal-attr-line__content">';
        echo '<span class="notifal-attr-label">' . esc_html($label) . '</span>';
        echo '<span class="notifal-attr-value">' . $value . '</span>';
        echo '</div></div>';
    }

    /**
     * Render influenced notifications as separate linkable rows in the summary card.
     *
     * @param array $summary Summary payload from buildAttributionSummary()
     * @return void
     * @since 2.3.7
     * @author Hossein <hossein@notifal.com>
     */
    private function renderSummaryNotificationsList(array $summary): void
    {
        $notifications = $summary['influenced_notifications'] ?? [];

        echo '<div class="notifal-attr-line notifal-attr-line--summary notifal-attr-line--list">';
        echo '<span class="notifal-icon notifal-icon-megaphone" aria-hidden="true"></span>';
        echo '<div class="notifal-attr-line__content">';
        echo '<span class="notifal-attr-label">' . esc_html__('Notifications influenced on this order', 'notifal') . '</span>';

        if (empty($notifications)) {
            echo '<span class="notifal-attr-value">&mdash;</span>';
            echo '</div></div>';
            return;
        }

        echo '<ul class="notifal-attribution-summary-list">';

        foreach ($notifications as $notification) {
            $notificationId = (int) ($notification['notification_id'] ?? 0);
            $title          = (string) ($notification['title'] ?? '');
            $editUrl        = (string) ($notification['edit_url'] ?? '');
            $label          = '#' . $notificationId . ' &mdash; ' . $title;

            echo '<li class="notifal-attribution-summary-list__item">';

            if ($editUrl !== '') {
                echo '<a href="' . esc_url($editUrl) . '" class="notifal-attribution-summary-list__link">';
                echo esc_html($label);
                echo '</a>';
            } else {
                echo '<span class="notifal-attribution-summary-list__text">' . esc_html($label) . '</span>';
            }

            echo '</li>';
        }

        echo '</ul></div></div>';
    }

    /**
     * Render clicked products as separate linkable rows in the summary card.
     *
     * @param array $summary Summary payload from buildAttributionSummary()
     * @return void
     * @since 2.3.7
     * @author Hossein <hossein@notifal.com>
     */
    private function renderSummaryProductsList(array $summary): void
    {
        $clickedProducts = $summary['clicked_products'] ?? [];

        echo '<div class="notifal-attr-line notifal-attr-line--summary notifal-attr-line--list">';
        echo '<span class="notifal-icon notifal-icon-cursor" aria-hidden="true"></span>';
        echo '<div class="notifal-attr-line__content">';
        echo '<span class="notifal-attr-label">' . esc_html__('Clicked product', 'notifal') . '</span>';

        if (empty($clickedProducts)) {
            $fallback = !empty($summary['has_product_click'])
                ? esc_html__('(unknown product)', 'notifal')
                : '&mdash;';
            echo '<span class="notifal-attr-value">' . $fallback . '</span>';
            echo '</div></div>';
            return;
        }

        echo '<ul class="notifal-attribution-summary-list">';

        foreach ($clickedProducts as $product) {
            $productId   = (int) ($product['product_id'] ?? 0);
            $productName = (string) ($product['product_name'] ?? '');
            $editUrl     = (string) ($product['edit_url'] ?? '');

            if ($productName === '') {
                $productName = __('(unknown product)', 'notifal');
            }

            echo '<li class="notifal-attribution-summary-list__item">';

            if ($editUrl !== '') {
                echo '<a href="' . esc_url($editUrl) . '" class="notifal-attribution-summary-list__link">';
                echo esc_html($productName);
                echo '</a>';
            } else {
                echo '<span class="notifal-attribution-summary-list__text">' . esc_html($productName) . '</span>';
            }

            echo '</li>';
        }

        echo '</ul></div></div>';
    }

    /**
     * Resolve the admin edit URL for a WooCommerce product or EDD download.
     *
     * @param int $productId WooCommerce product/variation ID or EDD download ID
     * @return string Admin edit URL or empty string when unavailable
     * @since 2.3.7
     * @author Hossein <hossein@notifal.com>
     */
    private function getProductAdminEditUrl(int $productId): string
    {
        if ($productId <= 0) {
            return '';
        }

        // Prefer WooCommerce admin product edit screen when available
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($productId);

            if ($product) {
                $editPostId = $product->is_type('variation')
                    ? (int) $product->get_parent_id()
                    : $productId;

                $editLink = get_edit_post_link($editPostId, 'raw');

                if (is_string($editLink) && $editLink !== '') {
                    return $editLink;
                }
            }
        }

        // Fallback for EDD downloads and generic products
        $editLink = get_edit_post_link($productId, 'raw');

        return is_string($editLink) ? $editLink : '';
    }

    /**
     * Render a linkable product name for detail rows.
     *
     * @param int    $productId   Product ID
     * @param string $productName Display name
     * @return string Safe HTML for the product label
     * @since 2.3.7
     * @author Hossein <hossein@notifal.com>
     */
    private function formatProductNameHtml(int $productId, string $productName): string
    {
        if ($productName === '') {
            $productName = __('(unknown product)', 'notifal');
        }

        $editUrl = $this->getProductAdminEditUrl($productId);

        if ($editUrl !== '') {
            return '<a href="' . esc_url($editUrl) . '" class="notifal-attr-product-name">'
                . esc_html($productName)
                . '</a>';
        }

        return '<span class="notifal-attr-product-name">' . esc_html($productName) . '</span>';
    }

    /**
     * Format a revenue amount for WooCommerce or EDD admin display.
     *
     * @param float $amount Revenue amount
     * @return string Formatted amount or dash when zero
     * @since 2.3.7
     * @author Hossein <hossein@notifal.com>
     */
    private function formatRevenueAmount(float $amount): string
    {
        if ($amount <= 0) {
            return '&mdash;';
        }

        if (function_exists('wc_price')) {
            return wp_kses_post(wc_price($amount));
        }

        if (function_exists('edd_currency_filter') && function_exists('edd_format_amount')) {
            return esc_html(edd_currency_filter(edd_format_amount($amount)));
        }

        return esc_html(number_format($amount, 2));
    }

    /**
     * Render one per-notification detail row inside the collapsible section.
     *
     * @param array $row Attribution row
     * @return void
     * @since 2.3.7
     * @author Hossein <hossein@notifal.com>
     */
    private function renderAttributionDetailRow(array $row): void
    {
        $notificationId = (int) ($row['notification_id'] ?? 0);
        $notifTitle     = get_the_title($notificationId) ?: __('(deleted notification)', 'notifal');
        $productId      = (int) ($row['product_id'] ?? 0);
        $productName    = (string) ($row['product_name'] ?? '');
        $productRevenue = (float) ($row['product_revenue'] ?? 0);
        $editUrl        = $notificationId > 0 ? $this->urlService->getEditNotificationUrl($notificationId) : '';

        // Cookie/session influence rows have no clicked product
        if ($productId <= 0) {
            $productName = __('General notification influence', 'notifal');
        } elseif ($productName === '') {
            $productName = __('(unknown product)', 'notifal');
        }

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

        if ($productId > 0) {
            echo $this->formatProductNameHtml($productId, $productName);
        } else {
            echo '<span class="notifal-attr-product-name">' . esc_html($productName) . '</span>';
        }

        echo '</div></div>';

        if ($productId > 0) {
            echo '<div class="notifal-attr-line">';
            echo '<span class="notifal-icon notifal-icon-coin" aria-hidden="true"></span>';
            echo '<div class="notifal-attr-line__content">';
            echo '<span class="notifal-attr-label">' . esc_html__('Clicked revenue', 'notifal') . '</span>';
            echo '<span class="notifal-attr-product-revenue">' . $this->formatRevenueAmount($productRevenue) . '</span>';
            echo '</div></div>';
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

        if ($this->isPendingAttributionOnly($attributionData)) {
            echo '<p class="notifal-order-meta-pending-notice">'
                . esc_html__(
                    'This order was influenced by Notifal but is not paid yet. It will be counted in analytics revenue once payment is completed.',
                    'notifal'
                )
                . '</p>';
        }

        $this->renderAttributionRowsHtml($attributionData);

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

        // Remove duplicate cookie-influence rows when a product click exists for the same notification
        $rows = $this->normalizeAttributionRows($rows);

        // Backfill clicked revenue from order line items when stored revenue is missing
        $rows = $this->backfillDisplayProductRevenue($orderId, $rows);

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
     * Remove redundant cookie-influence rows when product clicks exist for the same notification.
     *
     * Cookie/session rows use product_id = 0 and appear as "(unknown product)" in the UI.
     *
     * @param array $rows Attribution rows
     * @return array Normalized rows
     * @since 2.3.7
     * @author Hossein <hossein@notifal.com>
     */
    private function normalizeAttributionRows(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        // Collect notification IDs that already have a real product click
        $notificationsWithProductClick = [];

        foreach ($rows as $row) {
            $notificationId = (int) ($row['notification_id'] ?? 0);
            $productId      = (int) ($row['product_id'] ?? 0);

            if ($notificationId > 0 && $productId > 0) {
                $notificationsWithProductClick[$notificationId] = true;
            }
        }

        $normalized = [];

        foreach ($rows as $row) {
            $notificationId = (int) ($row['notification_id'] ?? 0);
            $productId      = (int) ($row['product_id'] ?? 0);

            // Drop cookie/session rows when the same notification already has a clicked product
            if ($productId <= 0 && isset($notificationsWithProductClick[$notificationId])) {
                continue;
            }

            $normalized[] = $row;
        }

        return $normalized;
    }

    /**
     * Backfill product revenue on attribution rows for admin display.
     *
     * Covers pending snapshots and paid conversions where product_id is set but
     * product_revenue was stored as zero (for example after delayed payment when
     * live click lookup no longer matches). Resolves parent/variation ID differences.
     *
     * @param int   $orderId WooCommerce order ID or EDD payment ID
     * @param array $rows    Attribution rows
     * @return array Rows with display revenue when applicable
     * @since 2.3.7
     * @since 2.4.1 Backfills all verified product-click rows, not only pending snapshots
     * @author Hossein <hossein@notifal.com>
     */
    private function backfillDisplayProductRevenue(int $orderId, array $rows): array
    {
        // Skip when there is nothing to enrich or the order ID is invalid
        if (empty($rows) || $orderId <= 0) {
            return $rows;
        }

        // Build a map of order line subtotals keyed by product and variation IDs
        $lineTotals = $this->buildOrderLineTotalsMap($orderId);

        if (empty($lineTotals)) {
            return $rows;
        }

        foreach ($rows as &$row) {
            // Only enrich rows that represent a real clicked product attribution
            if (!$this->isClickedProductAttributionRow($row)) {
                continue;
            }

            $productId      = (int) ($row['product_id'] ?? 0);
            $productRevenue = (float) ($row['product_revenue'] ?? 0);

            // Keep rows that already have a stored clicked revenue value
            if ($productId <= 0 || $productRevenue > 0) {
                continue;
            }

            // Resolve line subtotal (price × quantity) from the order items
            $resolvedRevenue = $this->resolveOrderLineTotalForProductId($productId, $lineTotals);

            if ($resolvedRevenue > 0) {
                $row['product_revenue'] = $resolvedRevenue;
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Determine whether an attribution row represents a clicked product conversion.
     *
     * Cookie and fallback influence rows must not receive clicked revenue backfill.
     *
     * @param array $row Attribution row
     * @return bool True when the row is a verified product-click attribution
     * @since 2.4.1
     * @author Hossein <hossein@notifal.com>
     */
    private function isClickedProductAttributionRow(array $row): bool
    {
        $productId = (int) ($row['product_id'] ?? 0);

        if ($productId <= 0) {
            return false;
        }

        $attributionType = (string) ($row['attribution_type'] ?? '');

        // Cookie/session and fallback rows are influence-only, not product clicks
        if ($attributionType === 'fallback' || $attributionType === 'pending_cookie') {
            return false;
        }

        return true;
    }

    /**
     * Build a map of WooCommerce order line subtotals keyed by product and variation IDs.
     *
     * @param int $orderId WooCommerce order ID
     * @return array<int, float> Map of product ID to line subtotal
     * @since 2.4.1
     * @author Hossein <hossein@notifal.com>
     */
    private function buildOrderLineTotalsMap(int $orderId): array
    {
        $lineTotals = [];

        if (!function_exists('wc_get_order')) {
            return $lineTotals;
        }

        $order = wc_get_order($orderId);

        if (!$order) {
            return $lineTotals;
        }

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

            // Index by variation/simple ID and parent ID for cross-lookup
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
     * @param int              $productId  Product or variation ID from attribution data
     * @param array<int,float> $lineTotals Map from buildOrderLineTotalsMap()
     * @return float Line subtotal or 0 when not found
     * @since 2.4.1
     * @author Hossein <hossein@notifal.com>
     */
    private function resolveOrderLineTotalForProductId(int $productId, array $lineTotals): float
    {
        if ($productId <= 0 || empty($lineTotals)) {
            return 0.0;
        }

        if (isset($lineTotals[$productId])) {
            return (float) $lineTotals[$productId];
        }

        if (!function_exists('wc_get_product')) {
            return 0.0;
        }

        $product = wc_get_product($productId);

        if ($product && $product->is_type('variation')) {
            $parentId = (int) $product->get_parent_id();

            if ($parentId > 0 && isset($lineTotals[$parentId])) {
                return (float) $lineTotals[$parentId];
            }
        }

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
     * Build summary totals for the attribution summary card.
     *
     * @param array $attributionData Normalized attribution rows
     * @return array Summary payload for rendering
     * @since 2.3.7
     * @author Hossein <hossein@notifal.com>
     */
    private function buildAttributionSummary(array $attributionData): array
    {
        // Track unique notifications and products for the summary lists
        $influencedNotifications = [];
        $clickedProductsById       = [];
        $totalClickedRevenue       = 0.0;
        $totalInfluencedRevenue    = 0.0;
        $hasProductClick           = false;

        foreach ($attributionData as $row) {
            $notificationId = (int) ($row['notification_id'] ?? 0);
            $productId      = (int) ($row['product_id'] ?? 0);
            $productName    = (string) ($row['product_name'] ?? '');
            $productRevenue = (float) ($row['product_revenue'] ?? 0);
            $orderTotal     = (float) ($row['total_order_value'] ?? 0);

            // Influenced revenue is the full order total and must be shown once
            if ($orderTotal > $totalInfluencedRevenue) {
                $totalInfluencedRevenue = $orderTotal;
            }

            // Collect each unique notification that influenced this order
            if ($notificationId > 0 && !isset($influencedNotifications[$notificationId])) {
                $influencedNotifications[$notificationId] = [
                    'notification_id' => $notificationId,
                    'title'           => get_the_title($notificationId) ?: __('(deleted notification)', 'notifal'),
                    'edit_url'        => $this->urlService->getEditNotificationUrl($notificationId),
                ];
            }

            // Only count rows tied to a real clicked product
            if ($productId <= 0) {
                continue;
            }

            $hasProductClick = true;

            if (!isset($clickedProductsById[$productId])) {
                $clickedProductsById[$productId] = [
                    'product_id'   => $productId,
                    'product_name' => $productName,
                    'edit_url'     => $this->getProductAdminEditUrl($productId),
                    'revenue'      => $productRevenue,
                ];
                continue;
            }

            // Keep the highest revenue value when duplicate product rows exist
            if ($productRevenue > (float) ($clickedProductsById[$productId]['revenue'] ?? 0)) {
                $clickedProductsById[$productId]['revenue'] = $productRevenue;
            }

            if ($productName !== '' && ($clickedProductsById[$productId]['product_name'] ?? '') === '') {
                $clickedProductsById[$productId]['product_name'] = $productName;
            }
        }

        // Sum clicked revenue once per unique product
        foreach ($clickedProductsById as $productRow) {
            $totalClickedRevenue += (float) ($productRow['revenue'] ?? 0);
        }

        return [
            'influenced_notifications' => array_values($influencedNotifications),
            'clicked_products'           => array_values($clickedProductsById),
            'has_product_click'          => $hasProductClick,
            'total_clicked_revenue'      => $totalClickedRevenue,
            'total_influenced_revenue'   => $totalInfluencedRevenue,
        ];
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

        // Skip pending data only when paid conversions exist or were fully processed with rows
        if ($order->get_meta('_notifal_conversion_processed', true) && !empty($rows)) {
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
        $notificationsWithProductClick = [];

        foreach ($rows as $row) {
            $notificationId = (int) ($row['notification_id'] ?? 0);
            $productId = (int) ($row['product_id'] ?? 0);
            $existingKeys[$notificationId . '_' . $productId] = true;

            if ($notificationId > 0 && $productId > 0) {
                $notificationsWithProductClick[$notificationId] = true;
            }
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

            // Skip cookie influence pending rows when a product click already exists
            if ($productId <= 0 && isset($notificationsWithProductClick[$notificationId])) {
                continue;
            }

            $existingKeys[$dedupeKey] = true;

            if ($productId > 0) {
                $notificationsWithProductClick[$notificationId] = true;
            }

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
