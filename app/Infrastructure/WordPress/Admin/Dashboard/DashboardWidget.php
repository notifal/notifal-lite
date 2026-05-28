<?php

namespace Notifal\Infrastructure\WordPress\Admin\Dashboard;

use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Services\Analytics\AnalyticsMoneyFormatter;
use Notifal\Modules\OnPageNotification\Application\Services\Analytics\AnalyticsService;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\UrlService;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Repositories\NotificationQuery;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Class DashboardWidget
 *
 * Registers and renders the Notifal WordPress dashboard widget.
 *
 * The widget is always shown when Notifal is installed (free or pro).
 * It displays:
 * - Free users:  Total clicked revenue, total influenced revenue, influenced orders count
 *                (pro analytics metrics are shown as upsell blurred data)
 * - Pro users:   All key metrics — impressions, clicks, CTR, conversions, both revenue types,
 *                influenced orders, and a link to full analytics
 * - All users:   List of currently active notifications with links to edit each one
 *
 * The widget intentionally does NOT require WooCommerce/EDD to display; revenue data shows
 * as zero if no ecommerce plugin is active.
 *
 * @package Notifal\Infrastructure\WordPress\Admin\Dashboard
 * @since 2.3.0
 * @author Hossein <hossein@notifal.com>
 */
class DashboardWidget
{
    /**
     * Widget ID used by WordPress dashboard API.
     *
     * @since 2.3.0
     */
    private const WIDGET_ID = 'notifal_dashboard_widget';

    /**
     * Maximum number of active notifications shown in the widget list.
     *
     * @since 2.3.0
     */
    private const MAX_VISIBLE_ACTIVE_NOTIFICATIONS = 8;

    /**
     * Register this class's hooks with WordPress.
     *
     * Called once during plugin boot.
     *
     * @return void
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function register(): void
    {
        // Late priority so other widgets register first, then we move ours to the top
        add_action('wp_dashboard_setup', [self::class, 'registerWidget'], 999);
    }

    /**
     * Register the dashboard widget with the WordPress API.
     *
     * @return void
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function registerWidget(): void
    {
        // Only show widget to admins / users who can manage options
        if (!current_user_can('manage_options')) {
            return;
        }

        // Add widget to the main (normal) dashboard column
        wp_add_dashboard_widget(
            self::WIDGET_ID,
            esc_html__('Notifal Analytics', 'notifal'),
            [self::class, 'renderWidget']
        );

        // Place this widget above all others in the normal column
        self::moveWidgetToTop();
    }

    /**
     * Move the Notifal dashboard widget to the top of the normal column.
     *
     * WordPress has no position argument on wp_add_dashboard_widget(); order is
     * controlled via $wp_meta_boxes after all widgets are registered.
     *
     * @return void
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function moveWidgetToTop(): void
    {
        // Access global meta box registry used by the dashboard screen
        global $wp_meta_boxes;

        // Bail if our widget was not registered in the expected location
        if (
            !isset($wp_meta_boxes['dashboard']['normal']['core'][self::WIDGET_ID])
            || !is_array($wp_meta_boxes['dashboard']['normal']['core'])
        ) {
            return;
        }

        // Pull our widget out of the stack
        $notifalWidget = [
            self::WIDGET_ID => $wp_meta_boxes['dashboard']['normal']['core'][self::WIDGET_ID],
        ];

        // Remove it from its current position
        unset($wp_meta_boxes['dashboard']['normal']['core'][self::WIDGET_ID]);

        // Prepend so it renders first (top) in the normal column
        $wp_meta_boxes['dashboard']['normal']['core'] = array_merge(
            $notifalWidget,
            $wp_meta_boxes['dashboard']['normal']['core']
        );
    }

    /**
     * Render the dashboard widget HTML.
     *
     * Fetches analytics data for the last 30 days and renders metric cards.
     * Free users see revenue + influenced data; pro analytics data is shown
     * with upsell overlay for non-pro users.
     *
     * @return void
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function renderWidget(): void
    {
        // Determine if Pro analytics are available
        $isProActive = (bool) apply_filters(FilterHooks::IS_PRO_ANALYTICS_ACTIVE, false);

        // Fetch last 30 days data
        $filters = ['date_range' => 'last_30_days'];

        /** @var AnalyticsService $analyticsService */
        $analyticsService = notifal_app(AnalyticsService::class);

        /** @var AnalyticsMoneyFormatter $formatter */
        $formatter = notifal_app(AnalyticsMoneyFormatter::class);

        // Get dashboard overview data (delegated to Pro service if active)
        $data = $analyticsService->getDashboardOverview($filters);

        // --- Metrics extraction ---
        $period = $data['current_period'] ?? [];

        // Revenue metrics (always available for free + pro)
        $clickedRevenue      = $formatter->formatPlain((float) ($period['total_revenue'] ?? 0));
        $influencedRevenue   = $formatter->formatPlain((float) ($period['influenced_revenue'] ?? 0));
        $influencedOrdersTotal = (int) ($period['influenced_orders'] ?? 0);
        $influencedOrdersPaid  = (int) ($period['influenced_orders_paid'] ?? $influencedOrdersTotal);

        // Analytics metrics (pro only)
        $totalImpressions    = (int) ($period['total_impressions'] ?? 0);
        $totalClicks         = (int) ($period['total_clicks'] ?? 0);
        $totalConversions      = (int) ($period['total_conversions'] ?? 0);
        $totalUniqueUsers      = (int) ($period['total_unique_users'] ?? 0);
        $totalUniqueConverters = (int) ($period['total_unique_converters'] ?? 0);

        // Calculated rates (aligned with OnPage Analytics dashboard)
        $ctr            = ($totalImpressions > 0) ? round(($totalClicks / $totalImpressions) * 100, 2) : 0;
        $conversionRate = ($totalUniqueUsers > 0) ? round(($totalUniqueConverters / $totalUniqueUsers) * 100, 2) : 0;

        // Analytics URL
        $analyticsUrl = admin_url('admin.php?page=notifal-onpage-analytics');

        // Upgrade URL for upsell links
        $upgradeUrl = Urls::withPluginUtm(Urls::PRICING, 'wordpress_plugin', 'notifal_pro_upgrade')
            . '&utm_medium=wp_dashboard_widget&utm_content=wp_dashboard_widget';

        // Active notifications for the widget list (available to free and pro users)
        $activeNotifications     = self::getActiveNotificationsForWidget();
        $activeNotificationCount = count($activeNotifications);
        $totalNotificationCount  = self::getTotalNotificationsCountForWidget();

        /** @var UrlService $urlService */
        $urlService = notifal_app(UrlService::class);

        $notificationsListUrl = $urlService->getListUrl();
        $createNotificationUrl = $urlService->getCreateNotificationUrl();

        /**
         * Fires before the Notifal dashboard widget markup is output.
         *
         * @since 2.3.0
         */
        do_action(ActionHooks::DASHBOARD_WIDGET_BEFORE_RENDER);

        ?>
        <div class="notifal-dashboard-widget">

            <!-- Revenue section: always visible for both free and pro users -->
            <div class="notifal-widget-section notifal-widget-revenue-section">
                <h4 class="notifal-widget-section-title"><?php esc_html_e('Last 30 Days Revenue', 'notifal'); ?></h4>

                <div class="notifal-widget-metrics-grid notifal-widget-metrics-grid--2col">

                    <!-- Clicked Revenue -->
                    <div class="notifal-widget-metric">
                        <div class="notifal-widget-metric__icon">
                            <span class="notifal-icon notifal-icon-coin"></span>
                        </div>
                        <div class="notifal-widget-metric__body">
                            <span class="notifal-widget-metric__label"><?php esc_html_e('Clicked Revenue', 'notifal'); ?></span>
                            <span class="notifal-widget-metric__value"><?php echo esc_html($clickedRevenue); ?></span>
                            <span class="notifal-widget-metric__sub"><?php esc_html_e('Clicked products revenue', 'notifal'); ?></span>
                        </div>
                    </div>

                    <!-- Influenced Revenue -->
                    <div class="notifal-widget-metric notifal-widget-metric--highlight">
                        <div class="notifal-widget-metric__icon">
                            <span class="notifal-icon notifal-icon-bag-check"></span>
                        </div>
                        <div class="notifal-widget-metric__body">
                            <span class="notifal-widget-metric__label"><?php esc_html_e('Influenced Revenue', 'notifal'); ?></span>
                            <span class="notifal-widget-metric__value"><?php echo esc_html($influencedRevenue); ?></span>
                            <span class="notifal-widget-metric__sub">
                                <?php
                                echo esc_html(
                                    sprintf(
                                        /* translators: 1: total influenced orders, 2: paid orders counted in revenue */
                                        _n(
                                            '%1$d influenced order (%2$d paid, counted in revenue)',
                                            '%1$d influenced orders (%2$d paid, counted in revenue)',
                                            $influencedOrdersTotal,
                                            'notifal'
                                        ),
                                        $influencedOrdersTotal,
                                        $influencedOrdersPaid
                                    )
                                );
                                ?>
                            </span>
                        </div>
                    </div>

                </div><!-- .notifal-widget-metrics-grid -->
            </div><!-- .notifal-widget-revenue-section -->

            <!-- Analytics section: full data for Pro, upsell for free -->
            <div class="notifal-widget-section notifal-widget-analytics-section">
                <h4 class="notifal-widget-section-title">
                    <?php esc_html_e('Engagement Analytics', 'notifal'); ?>
                    <?php if (!$isProActive): ?>
                        <span class="notifal-pro-badge notifal-pro-badge-inline">
                            <span class="notifal-icon notifal-icon-crown"></span>
                            <?php esc_html_e('PRO', 'notifal'); ?>
                        </span>
                    <?php endif; ?>
                </h4>

                <?php if ($isProActive): ?>

                    <!-- Pro: full analytics metrics grid -->
                    <div class="notifal-widget-metrics-grid notifal-widget-metrics-grid--4col">

                        <div class="notifal-widget-metric">
                            <div class="notifal-widget-metric__icon">
                                <span class="notifal-icon notifal-icon-eye"></span>
                            </div>
                            <div class="notifal-widget-metric__body">
                                <span class="notifal-widget-metric__label"><?php esc_html_e('Impressions', 'notifal'); ?></span>
                                <span class="notifal-widget-metric__value"><?php echo esc_html(number_format($totalImpressions)); ?></span>
                            </div>
                        </div>

                        <div class="notifal-widget-metric">
                            <div class="notifal-widget-metric__icon">
                                <span class="notifal-icon notifal-icon-cursor"></span>
                            </div>
                            <div class="notifal-widget-metric__body">
                                <span class="notifal-widget-metric__label"><?php esc_html_e('Clicks', 'notifal'); ?></span>
                                <span class="notifal-widget-metric__value"><?php echo esc_html(number_format($totalClicks)); ?></span>
                                <span class="notifal-widget-metric__sub"><?php echo esc_html($ctr . '% CTR'); ?></span>
                            </div>
                        </div>

                        <div class="notifal-widget-metric">
                            <div class="notifal-widget-metric__icon">
                                <span class="notifal-icon notifal-icon-exchange-rate"></span>
                            </div>
                            <div class="notifal-widget-metric__body">
                                <span class="notifal-widget-metric__label"><?php esc_html_e('Conversions', 'notifal'); ?></span>
                                <span class="notifal-widget-metric__value"><?php echo esc_html(number_format($totalConversions)); ?></span>
                                <span class="notifal-widget-metric__sub"><?php echo esc_html($conversionRate . '% ' . __('conversion rate', 'notifal')); ?></span>
                            </div>
                        </div>

                        <div class="notifal-widget-metric">
                            <div class="notifal-widget-metric__icon">
                                <span class="notifal-icon notifal-icon-people"></span>
                            </div>
                            <div class="notifal-widget-metric__body">
                                <span class="notifal-widget-metric__label"><?php esc_html_e('Unique Visitors', 'notifal'); ?></span>
                                <span class="notifal-widget-metric__value"><?php echo esc_html(number_format($totalUniqueUsers)); ?></span>
                            </div>
                        </div>

                    </div><!-- .notifal-widget-metrics-grid -->

                <?php else: ?>

                    <!-- Free: upsell overlay with blurred placeholder values -->
                    <div class="notifal-widget-upsell-block">
                        <div class="notifal-widget-upsell-blurred">
                            <div class="notifal-widget-metrics-grid notifal-widget-metrics-grid--4col">
                                <div class="notifal-widget-metric notifal-blurred">
                                    <span class="notifal-widget-metric__label"><?php esc_html_e('Impressions', 'notifal'); ?></span>
                                    <span class="notifal-widget-metric__value notifal-blurred-text">##,###</span>
                                </div>
                                <div class="notifal-widget-metric notifal-blurred">
                                    <span class="notifal-widget-metric__label"><?php esc_html_e('Clicks', 'notifal'); ?></span>
                                    <span class="notifal-widget-metric__value notifal-blurred-text">###</span>
                                </div>
                                <div class="notifal-widget-metric notifal-blurred">
                                    <span class="notifal-widget-metric__label"><?php esc_html_e('CTR', 'notifal'); ?></span>
                                    <span class="notifal-widget-metric__value notifal-blurred-text">#.##%</span>
                                </div>
                                <div class="notifal-widget-metric notifal-blurred">
                                    <span class="notifal-widget-metric__label"><?php esc_html_e('Conversions', 'notifal'); ?></span>
                                    <span class="notifal-widget-metric__value notifal-blurred-text">##</span>
                                </div>
                            </div>
                        </div>
                        <div class="notifal-widget-upsell-cta">
                            <span class="notifal-icon notifal-icon-crown"></span>
                            <p><?php esc_html_e('Unlock detailed analytics with Notifal Pro', 'notifal'); ?></p>
                            <a href="<?php echo esc_url($upgradeUrl); ?>" class="button button-primary notifal-widget-upgrade-btn" target="_blank" rel="noopener noreferrer">
                                <?php esc_html_e('Upgrade to Pro', 'notifal'); ?>
                            </a>
                        </div>
                    </div><!-- .notifal-widget-upsell-block -->

                <?php endif; ?>
            </div><!-- .notifal-widget-analytics-section -->

            <?php self::renderActiveNotificationsSection(
                $activeNotifications,
                $activeNotificationCount,
                $totalNotificationCount,
                $notificationsListUrl,
                $createNotificationUrl
            ); ?>
            <!-- Footer: link to full analytics (always shown) -->
            <div class="notifal-widget-footer">
                <a href="<?php echo esc_url($analyticsUrl); ?>" class="notifal-widget-view-all">
                    <span class="notifal-icon notifal-icon-arrow-right"></span>
                    <?php esc_html_e('View Full Analytics', 'notifal'); ?>
                </a>
                <span class="notifal-widget-period-label"><?php esc_html_e('Last 30 days', 'notifal'); ?></span>
            </div>

        </div><!-- .notifal-dashboard-widget -->
        <?php

        /**
         * Fires after the Notifal dashboard widget markup is output.
         *
         * @since 2.3.0
         */
        do_action(ActionHooks::DASHBOARD_WIDGET_AFTER_RENDER);
    }

    /**
     * Build a list of active notifications for the dashboard widget.
     *
     * Uses NotificationQuery which filters by _notifal_notif_enabled = 1.
     *
     * @return array<int, array{id: int, title: string, edit_url: string}>
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function getActiveNotificationsForWidget(): array
    {
        // Fetch all notifications that are currently enabled
        $posts = NotificationQuery::getAll();

        if (empty($posts)) {
            return [];
        }

        /** @var UrlService $urlService */
        $urlService = notifal_app(UrlService::class);

        $items = [];

        foreach ($posts as $post) {
            // Skip invalid post objects
            if (!$post instanceof WP_Post) {
                continue;
            }

            $items[] = [
                'id'       => (int) $post->ID,
                'title'    => get_the_title($post) ?: __('(no title)', 'notifal'),
                'edit_url' => $urlService->getEditNotificationUrl((int) $post->ID),
            ];
        }

        /**
         * Filter active notifications shown in the WordPress dashboard widget.
         *
         * @param array $items Each item: id, title, edit_url.
         * @since 2.3.0
         */
        return (array) apply_filters(FilterHooks::DASHBOARD_WIDGET_ACTIVE_NOTIFICATIONS, $items);
    }

    /**
     * Get the total number of created notifications for widget empty-state decisions.
     *
     * This count includes common editable statuses and is used to determine whether
     * the dashboard CTA should suggest creating a notification or managing existing ones.
     *
     * @return int
     * @since 2.3.0
     */
    private static function getTotalNotificationsCountForWidget(): int
    {
        // Retrieve counts for the notification post type from WordPress core cache layer.
        $counts = wp_count_posts('notifal_onpage_notif');

        // Guard against unexpected return values.
        if (!$counts) {
            return 0;
        }

        // Count notifications that exist in normal workflow statuses.
        return (int) (
            (int) ($counts->publish ?? 0)
            + (int) ($counts->draft ?? 0)
            + (int) ($counts->pending ?? 0)
            + (int) ($counts->future ?? 0)
            + (int) ($counts->private ?? 0)
        );
    }

    /**
     * Render the active notifications list section in the dashboard widget.
     *
     * @param array  $activeNotifications     Full list of active notification rows.
     * @param int    $activeNotificationCount Total count of active notifications.
     * @param int    $totalNotificationCount  Total count of existing notifications.
     * @param string $notificationsListUrl    URL to the notifications list admin page.
     * @param string $createNotificationUrl   URL to create a new notification.
     * @return void
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function renderActiveNotificationsSection(
        array $activeNotifications,
        int $activeNotificationCount,
        int $totalNotificationCount,
        string $notificationsListUrl,
        string $createNotificationUrl
    ): void {
        // Only show the first N items; link to list page if there are more
        $visibleNotifications = array_slice(
            $activeNotifications,
            0,
            self::MAX_VISIBLE_ACTIVE_NOTIFICATIONS
        );

        $hasMore = $activeNotificationCount > self::MAX_VISIBLE_ACTIVE_NOTIFICATIONS;

        ?>
        <div class="notifal-widget-section notifal-widget-notifications-section">
            <div class="notifal-widget-section-header">
                <h4 class="notifal-widget-section-title">
                    <?php esc_html_e('Active Notifications', 'notifal'); ?>
                    <?php if ($activeNotificationCount > 0): ?>
                        <span class="notifal-widget-count-badge"><?php echo esc_html((string) $activeNotificationCount); ?></span>
                    <?php endif; ?>
                </h4>
                <a href="<?php echo esc_url($notificationsListUrl); ?>" class="notifal-widget-section-link">
                    <?php esc_html_e('View all', 'notifal'); ?>
                </a>
            </div>

            <?php if (empty($activeNotifications)): ?>

                <p class="notifal-widget-empty">
                    <?php esc_html_e('No active notifications right now.', 'notifal'); ?>
                    <?php if ($totalNotificationCount > 0): ?>
                        <a href="<?php echo esc_url($notificationsListUrl); ?>">
                            <?php esc_html_e('Manage Notifications', 'notifal'); ?>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo esc_url($createNotificationUrl); ?>">
                            <?php esc_html_e('Create one', 'notifal'); ?>
                        </a>
                    <?php endif; ?>
                </p>

            <?php else: ?>

                <ul class="notifal-widget-notification-list">
                    <?php foreach ($visibleNotifications as $notification): ?>
                        <li class="notifal-widget-notification-item">
                            <span class="notifal-widget-notification-status" aria-hidden="true"></span>
                            <a
                                href="<?php echo esc_url($notification['edit_url']); ?>"
                                class="notifal-widget-notification-title"
                            >
                                <?php echo esc_html($notification['title']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($hasMore): ?>
                    <p class="notifal-widget-more-link">
                        <a href="<?php echo esc_url($notificationsListUrl); ?>">
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: %d: total number of active notifications */
                                    __('View all %d active notifications', 'notifal'),
                                    $activeNotificationCount
                                )
                            );
                            ?>
                        </a>
                    </p>
                <?php endif; ?>

            <?php endif; ?>
        </div>
        <?php
    }
}
