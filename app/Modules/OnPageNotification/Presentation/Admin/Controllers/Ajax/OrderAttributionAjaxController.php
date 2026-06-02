<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\OnPageNotification\Application\Services\Analytics\OrderAttributionService;

defined('ABSPATH') || exit;

/**
 * Class OrderAttributionAjaxController
 *
 * Handles AJAX requests for loading order attribution details in the orders list popup.
 *
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax
 * @since 2.3.5
 * @author Hossein <hossein@notifal.com>
 */
class OrderAttributionAjaxController
{
    /**
     * Register AJAX handlers for order attribution popup.
     *
     * @return void
     * @since 2.3.5
     * @author Hossein <hossein@notifal.com>
     */
    public static function register(): void
    {
        add_action('wp_ajax_notifal_get_order_attribution_details', [self::class, 'handleGetDetails']);
    }

    /**
     * Entry point for the order attribution details AJAX request.
     *
     * @return void
     * @since 2.3.5
     * @author Hossein <hossein@notifal.com>
     */
    public static function handleGetDetails(): void
    {
        $instance = new self();
        $instance->handleGetDetailsInstance();
    }

    /**
     * Validate the request and return attribution HTML for the popup modal.
     *
     * @return void
     * @since 2.3.5
     * @author Hossein <hossein@notifal.com>
     */
    private function handleGetDetailsInstance(): void
    {
        // Resolve the capability required for the active commerce plugin
        $capability = $this->resolveAjaxCapability();

        // Verify nonce and base capability before loading any order data
        notifal_verify_ajax_request('notifal_order_attribution_nonce', $capability);

        // Read and sanitize the requested order/payment ID
        $orderId = absint($_POST['order_id'] ?? 0);

        if ($orderId <= 0) {
            notifal_json_error(__('Invalid order ID.', 'notifal'));
        }

        // Ensure the current user may view this specific order/payment
        if (!$this->canViewOrderAttribution($orderId)) {
            notifal_json_error(__('You do not have permission to view this order.', 'notifal'));
        }

        /** @var OrderAttributionService $service */
        $service = notifal_app(OrderAttributionService::class);
        $html    = $service->buildAttributionDetailsHtml($orderId);

        notifal_json_success(['html' => $html]);
    }

    /**
     * Resolve the user capability required for the attribution AJAX endpoint.
     *
     * @return string WordPress capability slug
     * @since 2.3.5
     * @author Hossein <hossein@notifal.com>
     */
    private function resolveAjaxCapability(): string
    {
        // Default capability depends on which commerce plugin is active
        if (PluginDetector::isWooCommerceActive()) {
            $defaultCapability = 'edit_shop_orders';
        } elseif (PluginDetector::isEDDActive()) {
            $defaultCapability = 'edit_shop_payments';
        } else {
            $defaultCapability = 'manage_options';
        }

        /**
         * Filter the capability required for order attribution AJAX requests.
         *
         * @param string $capability Default capability slug.
         * @since 2.3.5
         */
        return (string) apply_filters(FilterHooks::ORDER_ATTRIBUTION_AJAX_CAPABILITY, $defaultCapability);
    }

    /**
     * Check whether the current user can view attribution for a specific order/payment.
     *
     * @param int $orderId WooCommerce order ID or EDD payment ID
     * @return bool
     * @since 2.3.5
     * @author Hossein <hossein@notifal.com>
     */
    private function canViewOrderAttribution(int $orderId): bool
    {
        // Capability was verified in handleGetDetailsInstance(); only confirm the order exists
        if (function_exists('wc_get_order') && wc_get_order($orderId)) {
            return true;
        }

        if (function_exists('edd_get_payment') && edd_get_payment($orderId)) {
            return true;
        }

        /**
         * Filter whether the current user can view order attribution details via AJAX.
         *
         * @param bool $allowed Default false when no commerce order was matched.
         * @param int  $orderId WooCommerce order ID or EDD payment ID.
         * @since 2.3.5
         */
        return (bool) apply_filters(FilterHooks::ORDER_ATTRIBUTION_CAN_VIEW, false, $orderId);
    }
}
