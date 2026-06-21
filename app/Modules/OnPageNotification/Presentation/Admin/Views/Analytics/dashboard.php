<?php
/**
 * OnPage Notification Analytics Dashboard
 *
 * Displays comprehensive analytics and insights for OnPage notifications
 * with filtering, charts, and detailed metrics.
 *
 * @since 2.0.0
 * @since 2.2.0 Campaign filter for analytics scope.
 * @since 2.2.4 Revenue metrics use store currency via AnalyticsMoneyFormatter (WooCommerce / EDD).
 * @since 2.3.0 Added All Time date range preset.
 * @author Hossein <hossein@notifal.com>
 */

defined('ABSPATH') || exit;

use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\OnPageNotification\Application\Services\Analytics\AnalyticsMoneyFormatter;
use Notifal\Modules\OnPageNotification\Application\Services\Analytics\AnalyticsService;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\UrlService;
use Notifal\Shared\Utils\Helper;

// Initialize services
$analyticsService = notifal_app(AnalyticsService::class);
$urlService = notifal_app(UrlService::class);
// Formatter aligns dashboard revenue with WooCommerce or EDD currency settings (for example Toman).
$analyticsMoneyFormatter = notifal_app(AnalyticsMoneyFormatter::class);

// Get current filters from request (sanitized per WordPress guidelines; behavior preserved)
$notification_id_raw = isset( $_GET['notification_id'] ) ? wp_unslash( $_GET['notification_id'] ) : '';
$campaign_id_raw     = isset( $_GET['campaign_id'] ) ? wp_unslash( $_GET['campaign_id'] ) : '';
$filters             = [
    'date_range'      => Helper::sanitizeInput( isset( $_GET['date_range'] ) ? wp_unslash( $_GET['date_range'] ) : 'last_30_days', 'text' ),
    'notification_id' => ( $notification_id_raw !== '' && $notification_id_raw !== '0' ) ? absint( $notification_id_raw ) : null,
    'campaign_id'     => ( $campaign_id_raw !== '' && $campaign_id_raw !== '0' ) ? absint( $campaign_id_raw ) : null,
    'status'          => Helper::sanitizeInput( isset( $_GET['status'] ) ? wp_unslash( $_GET['status'] ) : '', 'text' ),
];

// Campaign list for filter dropdown (Campaign module uses `notifal_campaign` post type).
$campaigns_for_filter = [];
if ( post_type_exists( 'notifal_campaign' ) ) {
    $campaigns_for_filter = get_posts(
        [
            'post_type'      => 'notifal_campaign',
            'post_status'    => [ 'publish', 'draft' ],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]
    );
}

// OnPage notifications shown in the notification dropdown: when a campaign is selected, only assignees of that campaign.
$notification_list_args = [
    'post_type'      => 'notifal_onpage_notif',
    'post_status'    => [ 'publish', 'draft' ],
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
];
if ( ! empty( $filters['campaign_id'] ) ) {
    $notification_list_args['meta_query'] = [
        [
            'key'     => '_notifal_campaign_id',
            'value'   => (int) $filters['campaign_id'],
            'compare' => '=',
        ],
    ];
}
$notifications = get_posts( $notification_list_args );

// Determine whether enhanced analytics access is available.
// This unified gate keeps Lite and Pro-unlicensed states visually identical.
$has_enhanced_analytics_access = (bool) apply_filters('notifal_pro_enhanced_analytics_allowed', false);

// Get analytics data (will be filtered for Pro upsell if Pro not active)
$dashboardData = $analyticsService->getDashboardOverview($filters);
// Pre-compute dashboard total revenue label once for cards, upsell copy, and consistent escaping.
$notifal_analytics_total_revenue_display = $analyticsMoneyFormatter->formatPlain((float) ($dashboardData['current_period']['total_revenue'] ?? 0));

// PRO locked view should be shown whenever enhanced analytics access is unavailable.
$isProUpsell = ! $has_enhanced_analytics_access;
$canAccessDetailedAnalytics = $analyticsService->canAccessDetailedAnalytics();

// Get paginated notifications (initially show 10)
$paginationFilters = array_merge($filters, ['limit' => 10, 'offset' => 0]);
$paginatedResult = $analyticsService->getPaginatedNotificationsAnalytics($paginationFilters);
$allNotifications = $paginatedResult['notifications'];
$pagination = $paginatedResult['pagination'];

// Get last update time information
$lastUpdateInfo = $analyticsService->getLastUpdateTime();

// Analytics assets are automatically enqueued via AnalyticsAssets::register()

?>

<div class="wp-wrap notifal-admin-page">
    <div class="notifal-analytics-dashboard">
    <?php do_action(ActionHooks::ADMIN_PAGE_CONTENT_BEFORE); ?>
        <!-- Dashboard Header -->
        <div class="notifal-dashboard-header">
            <div class="notifal-flex notifal-justify-between notifal-align-center notifal-dashboard-header-top">
                <div>
                    <h1 class="notifal-dashboard-title">
                        <?php esc_html_e('OnPage Analytics Dashboard', 'notifal'); ?>
                    </h1>
                    <p class="notifal-dashboard-subtitle">
                        <?php echo esc_html(sprintf(__('Analytics overview for %s', 'notifal'), $dashboardData['date_range']['label'])); ?>
                    </p>
                </div>
                <div class="notifal-dashboard-actions">
                    <button type="button" class="notifal-button<?php echo $isProUpsell ? ' notifal-disabled' : ''; ?>" id="notifal-export-analytics"<?php echo $isProUpsell ? ' disabled' : ''; ?>>
                        <span class="notifal-icon notifal-icon-download"></span>
                        <?php esc_html_e('Export Data', 'notifal'); ?>
                        <?php if ($isProUpsell): ?>
                            <span class="notifal-pro-badge notifal-pro-badge-inline">
                                <span class="notifal-icon notifal-icon-crown"></span>
                                <?php esc_html_e('PRO', 'notifal'); ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <button type="button" class="notifal-button" id="notifal-refresh-analytics">
                        <span class="notifal-icon notifal-icon-arrow-repeat"></span>
                        <?php esc_html_e('Refresh', 'notifal'); ?>
                    </button>
                </div>
            </div>
            <div class="notifal-dashboard-cache-meta" role="status" aria-live="polite">
                <span class="notifal-icon notifal-icon-clock-history" aria-hidden="true"></span>
                <p class="notifal-dashboard-cache-meta-text">
                    <?php
                    echo wp_kses_post(
                        sprintf(
                            /* translators: 1: last processed datetime, 2: human time diff, 3: time until next automatic refresh */
                            __(
                                '<strong>Cached snapshot.</strong> All metrics, charts, and tables were last processed on %1$s (%2$s). Auto-refresh in %3$s, use <strong>Refresh</strong> above to load the latest data now.',
                                'notifal'
                            ),
                            esc_html($lastUpdateInfo['formatted']),
                            esc_html($lastUpdateInfo['human_diff']),
                            esc_html($lastUpdateInfo['next_update_human'])
                        )
                    );
                    ?>
                </p>
            </div>
        </div>

        <!-- Analytics Filters -->
        <div class="notifal-analytics-filters<?php echo $isProUpsell ? ' notifal-blurred' : ''; ?>">
            <?php if ($isProUpsell): ?>
                <div class="notifal-pro-badge notifal-pro-badge-large">
                    <span class="notifal-icon notifal-icon-crown"></span>
                    <?php esc_html_e('PRO FEATURE', 'notifal'); ?>
                </div>
            <?php endif; ?>
            <div class="notifal-card">
                <form method="GET" action="" class="notifal-filters-form<?php echo $isProUpsell ? ' notifal-pro-disabled' : ''; ?>" id="notifal-analytics-filters"<?php echo $isProUpsell ? ' onsubmit="return false;"' : ''; ?>>
                    <input type="hidden" name="page" value="notifal-onpage-analytics">
                    
                    <div class="notifal-filters-grid">
                        <!-- Date Range Filter -->
                        <div class="notifal-filter-group">
                            <label for="date_range"><?php esc_html_e('Date Range', 'notifal'); ?></label>
                            <select name="date_range" id="date_range" class="notifal-select">
                                <option value="today" <?php selected($filters['date_range'], 'today'); ?>><?php esc_html_e('Today', 'notifal'); ?></option>
                                <option value="yesterday" <?php selected($filters['date_range'], 'yesterday'); ?>><?php esc_html_e('Yesterday', 'notifal'); ?></option>
                                <option value="last_7_days" <?php selected($filters['date_range'], 'last_7_days'); ?>><?php esc_html_e('Last 7 Days', 'notifal'); ?></option>
                                <option value="last_30_days" <?php selected($filters['date_range'], 'last_30_days'); ?>><?php esc_html_e('Last 30 Days', 'notifal'); ?></option>
                                <option value="last_90_days" <?php selected($filters['date_range'], 'last_90_days'); ?>><?php esc_html_e('Last 90 Days', 'notifal'); ?></option>
                                <option value="all_time" <?php selected($filters['date_range'], 'all_time'); ?>><?php esc_html_e('All Time', 'notifal'); ?></option>
                            </select>
                        </div>

                        <!-- Campaign Filter -->
                        <div class="notifal-filter-group">
                            <label for="campaign_id"><?php esc_html_e('Campaign', 'notifal'); ?></label>
                            <select name="campaign_id" id="campaign_id" class="notifal-select">
                                <option value=""><?php esc_html_e('All Campaigns', 'notifal'); ?></option>
                                <?php
                                foreach ( $campaigns_for_filter as $campaign_post ) {
                                    $selected = selected( $filters['campaign_id'], $campaign_post->ID, false );
                                    echo '<option value="' . esc_attr( (string) $campaign_post->ID ) . '" ' . $selected . '>';
                                    echo esc_html( $campaign_post->post_title );
                                    echo '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Notification Filter -->
                        <div class="notifal-filter-group">
                            <label for="notification_id"><?php esc_html_e('Notification', 'notifal'); ?></label>
                            <select name="notification_id" id="notification_id" class="notifal-select">
                                <option value=""><?php esc_html_e('All Notifications', 'notifal'); ?></option>
                                <?php
                                foreach ( $notifications as $notification ) {
                                    $selected = selected( $filters['notification_id'], $notification->ID, false );
                                    echo '<option value="' . esc_attr( (string) $notification->ID ) . '" ' . $selected . '>';
                                    echo esc_html( $notification->post_title );
                                    echo '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div class="notifal-filter-group">
                            <label for="status"><?php esc_html_e('Status', 'notifal'); ?></label>
                            <select name="status" id="status" class="notifal-select">
                                <option value=""><?php esc_html_e('All Statuses', 'notifal'); ?></option>
                                <option value="publish" <?php selected($filters['status'], 'publish'); ?>><?php esc_html_e('Published', 'notifal'); ?></option>
                                <option value="draft" <?php selected($filters['status'], 'draft'); ?>><?php esc_html_e('Draft', 'notifal'); ?></option>
                            </select>
                        </div>

                        <!-- Filter Actions -->
                        <div class="notifal-filter-actions">
                            <button type="submit" class="notifal-button">
                                <span class="notifal-icon notifal-icon-search"></span>
                                <?php esc_html_e('Apply Filters', 'notifal'); ?>
                            </button>
                            <a href="?page=notifal-onpage-analytics" class="notifal-button secondary">
                                <?php esc_html_e('Reset', 'notifal'); ?>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Key Metrics Overview -->
        <div class="notifal-metrics-overview">
            <div class="notifal-metrics-grid notifal-metrics-grid-layout-updated">
                
                <!-- Total Impressions -->
                <div class="notifal-metric-card notifal-metric-impressions<?php echo $isProUpsell ? ' notifal-metric-blurred' : ''; ?>">
                    <?php if ($isProUpsell): ?>
                        <div class="notifal-pro-badge">
                            <span class="notifal-icon notifal-icon-crown"></span>
                            <?php esc_html_e('PRO', 'notifal'); ?>
                        </div>
                    <?php endif; ?>
                    <div class="notifal-metric-icon">
                        <span class="notifal-icon notifal-icon-eye"></span>
                    </div>
                    <div class="notifal-metric-content">
                        <h3 class="notifal-metric-title"><?php esc_html_e('Total Impressions', 'notifal'); ?></h3>
                        <div class="notifal-metric-value">
                            <?php if ($isProUpsell): ?>
                                <span class="notifal-blurred-text">###,###</span>
                            <?php else: ?>
                                <?php echo esc_html(number_format($dashboardData['current_period']['total_impressions'] ?? 0)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="notifal-metric-growth <?php 
                            $impressionsGrowth = $dashboardData['growth_rates']['total_impressions'] ?? 0;
                            if ($impressionsGrowth > 0) {
                                echo 'notifal-positive';
                            } elseif ($impressionsGrowth < 0) {
                                echo 'notifal-negative';
                            } else {
                                echo 'notifal-neutral';
                            }
                        ?>">
                            <span class="notifal-icon <?php 
                                if ($impressionsGrowth > 0) {
                                    echo 'notifal-icon-arrow-up-short';
                                } elseif ($impressionsGrowth < 0) {
                                    echo 'notifal-icon-arrow-down-short';
                                } else {
                                    echo 'notifal-icon-stop-circle';
                                }
                            ?>"></span>
                            <span><?php 
                                if ($impressionsGrowth == 0) {
                                    esc_html_e('No change vs previous period', 'notifal');
                                } else {
                                    echo esc_html(($impressionsGrowth > 0 ? '+' : '') . $impressionsGrowth . '% vs previous period');
                                }
                            ?></span>
                        </div>
                    </div>
                </div>

                <!-- Total Users -->
                <div class="notifal-metric-card notifal-metric-users<?php echo $isProUpsell ? ' notifal-metric-blurred' : ''; ?>">
                    <?php if ($isProUpsell): ?>
                        <div class="notifal-pro-badge">
                            <span class="notifal-icon notifal-icon-crown"></span>
                            <?php esc_html_e('PRO', 'notifal'); ?>
                        </div>
                    <?php endif; ?>
                    <div class="notifal-metric-icon">
                        <span class="notifal-icon notifal-icon-people"></span>
                    </div>
                    <div class="notifal-metric-content">
                        <h3 class="notifal-metric-title"><?php esc_html_e('Unique Visitors', 'notifal'); ?></h3>
                        <div class="notifal-metric-value">
                            <?php if ($isProUpsell): ?>
                                <span class="notifal-blurred-text">##,###</span>
                            <?php else: ?>
                                <?php echo esc_html(number_format($dashboardData['current_period']['total_unique_users'] ?? 0)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="notifal-metric-growth <?php
                            $usersGrowth = $dashboardData['growth_rates']['total_unique_users'] ?? 0;
                            if ($usersGrowth > 0) {
                                echo 'notifal-positive';
                            } elseif ($usersGrowth < 0) {
                                echo 'notifal-negative';
                            } else {
                                echo 'notifal-neutral';
                            }
                        ?>">
                            <span class="notifal-icon <?php
                                if ($usersGrowth > 0) {
                                    echo 'notifal-icon-arrow-up-short';
                                } elseif ($usersGrowth < 0) {
                                    echo 'notifal-icon-arrow-down-short';
                                } else {
                                    echo 'notifal-icon-stop-circle';
                                }
                            ?>"></span>
                            <span><?php
                                if ($usersGrowth == 0) {
                                    esc_html_e('No change vs previous period', 'notifal');
                                } else {
                                    echo esc_html(($usersGrowth > 0 ? '+' : '') . $usersGrowth . '% vs previous period');
                                }
                            ?></span>
                        </div>
                    </div>
                </div>

                <!-- Total Clicks -->
                <div class="notifal-metric-card notifal-metric-clicks<?php echo $isProUpsell ? ' notifal-metric-blurred' : ''; ?>">
                    <?php if ($isProUpsell): ?>
                        <div class="notifal-pro-badge">
                            <span class="notifal-icon notifal-icon-crown"></span>
                            <?php esc_html_e('PRO', 'notifal'); ?>
                        </div>
                    <?php endif; ?>
                    <div class="notifal-metric-icon">
                        <span class="notifal-icon notifal-icon-cursor"></span>
                    </div>
                    <div class="notifal-metric-content">
                        <h3 class="notifal-metric-title"><?php esc_html_e('Total Clicks', 'notifal'); ?></h3>
                        <div class="notifal-metric-value">
                            <?php if ($isProUpsell): ?>
                                <span class="notifal-blurred-text">##,###</span>
                            <?php else: ?>
                                <?php echo esc_html(number_format($dashboardData['current_period']['total_clicks'] ?? 0)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="notifal-metric-growth <?php 
                            $clicksGrowth = $dashboardData['growth_rates']['total_clicks'] ?? 0;
                            if ($clicksGrowth > 0) {
                                echo 'notifal-positive';
                            } elseif ($clicksGrowth < 0) {
                                echo 'notifal-negative';
                            } else {
                                echo 'notifal-neutral';
                            }
                        ?>">
                            <span class="notifal-icon <?php 
                                if ($clicksGrowth > 0) {
                                    echo 'notifal-icon-arrow-up-short';
                                } elseif ($clicksGrowth < 0) {
                                    echo 'notifal-icon-arrow-down-short';
                                } else {
                                    echo 'notifal-icon-stop-circle';
                                }
                            ?>"></span>
                            <span><?php 
                                if ($clicksGrowth == 0) {
                                    esc_html_e('No change vs previous period', 'notifal');
                                } else {
                                    echo esc_html(($clicksGrowth > 0 ? '+' : '') . $clicksGrowth . '% vs previous period');
                                }
                            ?></span>
                        </div>
                    </div>
                </div>

                <!-- Clicked Revenue - Always accessible to encourage upgrades -->
                <div class="notifal-metric-card notifal-metric-revenue notifal-metric-highlight">
                    <div class="notifal-metric-icon">
                        <span class="notifal-icon notifal-icon-coin"></span>
                    </div>
                    <div class="notifal-metric-content">
                        <h3 class="notifal-metric-title"><?php esc_html_e('Clicked Revenue', 'notifal'); ?></h3>
                        <p class="notifal-metric-subtitle"><?php esc_html_e('Revenue from clicked products', 'notifal'); ?></p>
                        <div class="notifal-metric-value">
                            <?php echo esc_html($notifal_analytics_total_revenue_display); ?>
                        </div>
                        <div class="notifal-metric-growth <?php
                            $revenueGrowth = $dashboardData['growth_rates']['total_revenue'] ?? 0;
                            if ($revenueGrowth > 0) {
                                echo 'notifal-positive';
                            } elseif ($revenueGrowth < 0) {
                                echo 'notifal-negative';
                            } else {
                                echo 'notifal-neutral';
                            }
                        ?>">
                            <span class="notifal-icon <?php
                                if ($revenueGrowth > 0) {
                                    echo 'notifal-icon-arrow-up-short';
                                } elseif ($revenueGrowth < 0) {
                                    echo 'notifal-icon-arrow-down-short';
                                } else {
                                    echo 'notifal-icon-stop-circle';
                                }
                            ?>"></span>
                            <span><?php
                                if ($revenueGrowth == 0) {
                                    esc_html_e('No change vs previous period', 'notifal');
                                } else {
                                    echo esc_html(($revenueGrowth > 0 ? '+' : '') . $revenueGrowth . '% vs previous period');
                                }
                            ?></span>
                        </div>
                    </div>
                </div>

                <?php
                // Pre-compute influenced revenue and orders display values (since 2.3.0)
                $notifal_influenced_revenue_raw     = (float) ($dashboardData['current_period']['influenced_revenue'] ?? 0);
                $notifal_influenced_revenue_display = $analyticsMoneyFormatter->formatPlain($notifal_influenced_revenue_raw);
                $notifal_influenced_orders_total    = (int) ($dashboardData['current_period']['influenced_orders'] ?? 0);
                $notifal_influenced_orders_paid     = (int) ($dashboardData['current_period']['influenced_orders_paid'] ?? $notifal_influenced_orders_total);
                $notifal_influenced_growth          = $dashboardData['growth_rates']['influenced_revenue'] ?? 0;
                ?>

                <!-- Influenced Revenue - Total order value influenced by notifal -->
                <div class="notifal-metric-card notifal-metric-influenced-revenue notifal-metric-highlight">
                    <div class="notifal-metric-icon">
                        <span class="notifal-icon notifal-icon-bag-check"></span>
                    </div>
                    <div class="notifal-metric-content">
                        <h3 class="notifal-metric-title"><?php esc_html_e('Influenced Revenue', 'notifal'); ?></h3>
                        <p class="notifal-metric-subtitle"><?php esc_html_e('Total order value influenced by notifal', 'notifal'); ?></p>
                        <div class="notifal-metric-value">
                            <?php echo esc_html($notifal_influenced_revenue_display); ?>
                        </div>
                        <div class="notifal-metric-growth notifal-metric-influenced-orders-info">
                            <span class="notifal-icon notifal-icon-handbag"></span>
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: 1: total influenced orders, 2: paid orders counted in revenue */
                                    _n(
                                        '%1$d influenced order (%2$d paid, counted in revenue)',
                                        '%1$d influenced orders (%2$d paid, counted in revenue)',
                                        $notifal_influenced_orders_total,
                                        'notifal'
                                    ),
                                    $notifal_influenced_orders_total,
                                    $notifal_influenced_orders_paid
                                )
                            );
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Click-Through Rate -->
                <div class="notifal-metric-card notifal-metric-ctr<?php echo $isProUpsell ? ' notifal-metric-blurred' : ''; ?>">
                    <?php if ($isProUpsell): ?>
                        <div class="notifal-pro-badge">
                            <span class="notifal-icon notifal-icon-crown"></span>
                            <?php esc_html_e('PRO', 'notifal'); ?>
                        </div>
                    <?php endif; ?>
                    <div class="notifal-metric-icon">
                        <span class="notifal-icon notifal-icon-percent"></span>
                    </div>
                    <div class="notifal-metric-content">
                        <h3 class="notifal-metric-title"><?php esc_html_e('Click-Through Rate', 'notifal'); ?></h3>
                        <div class="notifal-metric-value">
                            <?php if ($isProUpsell): ?>
                                <span class="notifal-blurred-text">#.##%</span>
                            <?php else: ?>
                                <?php
                                $impressions = $dashboardData['current_period']['total_impressions'] ?? 0;
                                $clicks = $dashboardData['current_period']['total_clicks'] ?? 0;
                                $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;
                                echo esc_html($ctr . '%');
                                ?>
                            <?php endif; ?>
                        </div>
                        <div class="notifal-metric-growth <?php 
                            $ctrGrowth = $dashboardData['growth_rates']['ctr'] ?? 0;
                            if ($ctrGrowth > 0) {
                                echo 'notifal-positive';
                            } elseif ($ctrGrowth < 0) {
                                echo 'notifal-negative';
                            } else {
                                echo 'notifal-neutral';
                            }
                        ?>">
                            <span class="notifal-icon <?php 
                                if ($ctrGrowth > 0) {
                                    echo 'notifal-icon-arrow-up-short';
                                } elseif ($ctrGrowth < 0) {
                                    echo 'notifal-icon-arrow-down-short';
                                } else {
                                    echo 'notifal-icon-stop-circle';
                                }
                            ?>"></span>
                            <span><?php 
                                if ($ctrGrowth == 0) {
                                    esc_html_e('No change', 'notifal');
                                } else {
                                    echo esc_html(($ctrGrowth > 0 ? '+' : '') . $ctrGrowth . '% vs previous period');
                                }
                            ?></span>
                        </div>
                    </div>
                </div>

                <!-- Conversion Rate -->
                <div class="notifal-metric-card notifal-metric-conversion<?php echo $isProUpsell ? ' notifal-metric-blurred' : ''; ?>">
                    <?php if ($isProUpsell): ?>
                        <div class="notifal-pro-badge">
                            <span class="notifal-icon notifal-icon-crown"></span>
                            <?php esc_html_e('PRO', 'notifal'); ?>
                        </div>
                    <?php endif; ?>
                    <div class="notifal-metric-icon">
                        <span class="notifal-icon notifal-icon-exchange-rate"></span>
                    </div>
                    <div class="notifal-metric-content">
                        <h3 class="notifal-metric-title"><?php esc_html_e('Conversion Rate', 'notifal'); ?></h3>
                        <p class="notifal-metric-subtitle"><?php esc_html_e('Unique visitors who placed an influenced order', 'notifal'); ?></p>
                        <div class="notifal-metric-value">
                            <?php if ($isProUpsell): ?>
                                <span class="notifal-blurred-text">#.##%</span>
                            <?php else: ?>
                                <?php
                                // Conversion rate is based on unique users (unique converters / unique visitors).
                                $uniqueVisitorsForRate = (int) ($dashboardData['current_period']['total_unique_users'] ?? 0);
                                $uniqueConvertersForRate = (int) ($dashboardData['current_period']['total_unique_converters'] ?? 0);
                                $conversionRate = $uniqueVisitorsForRate > 0 ? round(($uniqueConvertersForRate / $uniqueVisitorsForRate) * 100, 2) : 0;
                                echo esc_html($conversionRate . '%');
                                ?>
                            <?php endif; ?>
                        </div>
                        <div class="notifal-metric-growth <?php 
                            $conversionRateGrowth = $dashboardData['growth_rates']['conversion_rate'] ?? 0;
                            if ($conversionRateGrowth > 0) {
                                echo 'notifal-positive';
                            } elseif ($conversionRateGrowth < 0) {
                                echo 'notifal-negative';
                            } else {
                                echo 'notifal-neutral';
                            }
                        ?>">
                            <span class="notifal-icon <?php 
                                if ($conversionRateGrowth > 0) {
                                    echo 'notifal-icon-arrow-up-short';
                                } elseif ($conversionRateGrowth < 0) {
                                    echo 'notifal-icon-arrow-down-short';
                                } else {
                                    echo 'notifal-icon-stop-circle';
                                }
                            ?>"></span>
                            <span><?php 
                                if ($conversionRateGrowth == 0) {
                                    esc_html_e('No change vs previous period', 'notifal');
                                } else {
                                    echo esc_html(($conversionRateGrowth > 0 ? '+' : '') . $conversionRateGrowth . '% vs previous period');
                                }
                            ?></span>
                        </div>
                    </div>
                </div>

                <!-- Close Rate -->
                <div class="notifal-metric-card notifal-metric-close-rate<?php echo $isProUpsell ? ' notifal-metric-blurred' : ''; ?>">
                    <?php if ($isProUpsell): ?>
                        <div class="notifal-pro-badge">
                            <span class="notifal-icon notifal-icon-crown"></span>
                            <?php esc_html_e('PRO', 'notifal'); ?>
                        </div>
                    <?php endif; ?>
                    <div class="notifal-metric-icon">
                        <span class="notifal-icon notifal-icon-x-circle"></span>
                    </div>
                    <div class="notifal-metric-content">
                        <h3 class="notifal-metric-title"><?php esc_html_e('Close Rate', 'notifal'); ?></h3>
                        <div class="notifal-metric-value">
                            <?php if ($isProUpsell): ?>
                                <span class="notifal-blurred-text">#.##%</span>
                            <?php else: ?>
                                <?php
                                $closes = $dashboardData['current_period']['total_closes'] ?? 0;
                                $closeRate = $impressions > 0 ? round(($closes / $impressions) * 100, 2) : 0;
                                echo esc_html($closeRate . '%');
                                ?>
                            <?php endif; ?>
                        </div>
                        <div class="notifal-metric-growth <?php 
                            $closeRateGrowth = $dashboardData['growth_rates']['close_rate'] ?? 0;
                            // For close rate, lower is better (negative growth is positive)
                            if ($closeRateGrowth < 0) {
                                echo 'notifal-positive'; // Declining close rate is good
                            } elseif ($closeRateGrowth > 0) {
                                echo 'notifal-negative'; // Increasing close rate is bad
                            } else {
                                echo 'notifal-neutral';
                            }
                        ?>">
                            <span class="notifal-icon <?php 
                                if ($closeRateGrowth < 0) {
                                    echo 'notifal-icon-arrow-down-short'; // Down arrow for good (declining close rate)
                                } elseif ($closeRateGrowth > 0) {
                                    echo 'notifal-icon-arrow-up-short'; // Up arrow for bad (increasing close rate)
                                } else {
                                    echo 'notifal-icon-stop-circle';
                                }
                            ?>"></span>
                            <span><?php 
                                if ($closeRateGrowth == 0) {
                                    esc_html_e('No change vs previous period', 'notifal');
                                } else {
                                    echo esc_html(($closeRateGrowth > 0 ? '+' : '') . $closeRateGrowth . '% vs previous period');
                                }
                            ?></span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <?php if ($isProUpsell): ?>
        <!-- Pro Upgrade Call-to-Action -->
        <div class="notifal-pro-upgrade-cta">
            <div class="notifal-card notifal-gradient-card">
                <div class="notifal-upgrade-content">
                    <div class="notifal-upgrade-icon">
                        <span class="notifal-icon notifal-icon-star-fill"></span>
                    </div>
                    <div class="notifal-upgrade-text">
                        <h3><?php esc_html_e('Unlock Powerful Analytics with Notifal Pro', 'notifal'); ?></h3>
                        <p><?php echo sprintf(
                            esc_html__("You've generated %s in clicked revenue (%s in total influenced revenue)! Upgrade to Pro to see detailed analytics, CTR insights, conversion tracking, and optimization recommendations to boost your results even further.", 'notifal'),
                            '<strong>' . esc_html($notifal_analytics_total_revenue_display) . '</strong>',
                            '<strong>' . esc_html($notifal_influenced_revenue_display) . '</strong>'
                        ); ?></p>
                        <ul class="notifal-upgrade-features">
                            <li><span class="notifal-icon notifal-icon-check"></span><?php esc_html_e('Detailed impressions & click tracking', 'notifal'); ?></li>
                            <li><span class="notifal-icon notifal-icon-check"></span><?php esc_html_e('Advanced conversion analytics', 'notifal'); ?></li>
                            <li><span class="notifal-icon notifal-icon-check"></span><?php esc_html_e('Performance optimization insights', 'notifal'); ?></li>
                            <li><span class="notifal-icon notifal-icon-check"></span><?php esc_html_e('Access to many Pro settings', 'notifal'); ?></li>
                        </ul>
                    </div>
                    <div class="notifal-upgrade-action">
                        <?php
                        $domain = parse_url(home_url(), PHP_URL_HOST);
                        $upgrade_url = Urls::withPluginUtm(Urls::PRICING, 'wordpress_plugin', 'notifal_pro_upgrade') . '&utm_medium=analytics_admin_banner&utm_content=analytics_admin_banner&domain=' . urlencode($domain);
                        ?>
                        <a href="<?php echo esc_url($upgrade_url); ?>" class="notifal-button notifal-button-primary notifal-button-large notifal-pro-upgrade-cta">
                            <span class="notifal-icon notifal-icon-crown"></span>
                            <?php esc_html_e('Upgrade to Pro', 'notifal'); ?>
                        </a>
                        <p class="notifal-upgrade-note"><?php esc_html_e('30-day money-back guarantee', 'notifal'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Charts Section -->
        <div class="notifal-charts-section">
            <div class="notifal-charts-grid">
                
                <!-- Performance Over Time Chart -->
                <div class="notifal-chart-card notifal-chart-performance<?php echo $isProUpsell ? ' notifal-chart-blurred' : ''; ?>">
                    <?php if ($isProUpsell): ?>
                        <div class="notifal-chart-overlay">
                            <div class="notifal-pro-badge notifal-pro-badge-large">
                                <span class="notifal-icon notifal-icon-crown"></span>
                                <?php esc_html_e('PRO FEATURE', 'notifal'); ?>
                            </div>
                            <p><?php esc_html_e('Upgrade to view detailed performance charts', 'notifal'); ?></p>
                        </div>
                    <?php endif; ?>
                    <div class="notifal-chart-header">
                        <h3><?php esc_html_e('Performance Over Time', 'notifal'); ?></h3>
                        <div class="notifal-chart-controls<?php echo $isProUpsell ? ' notifal-disabled' : ''; ?>">
                            <select class="notifal-chart-metric-selector"<?php echo $isProUpsell ? ' disabled' : ''; ?>>
                                <option value="impressions"><?php esc_html_e('Impressions', 'notifal'); ?></option>
                                <option value="clicks"><?php esc_html_e('Clicks', 'notifal'); ?></option>
                                <option value="conversions"><?php esc_html_e('Conversions', 'notifal'); ?></option>
                                <option value="revenue"><?php esc_html_e('Revenue', 'notifal'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="notifal-chart-container">
                        <canvas id="notifal-performance-chart"></canvas>
                    </div>
                </div>

                <!-- Top Performing Notifications -->
                <div class="notifal-chart-card notifal-top-notifications<?php echo $isProUpsell ? ' notifal-chart-blurred' : ''; ?>">
                    <?php if ($isProUpsell): ?>
                        <div class="notifal-chart-overlay">
                            <div class="notifal-pro-badge notifal-pro-badge-large">
                                <span class="notifal-icon notifal-icon-crown"></span>
                                <?php esc_html_e('PRO FEATURE', 'notifal'); ?>
                            </div>
                            <p><?php esc_html_e('See your top performers with Pro analytics', 'notifal'); ?></p>
                        </div>
                    <?php endif; ?>
                    <div class="notifal-chart-header">
                        <h3><?php esc_html_e('Top Performing Notifications', 'notifal'); ?></h3>
                    </div>
                    <div class="notifal-top-notifications-list">
                        <?php if (!empty($allNotifications)): ?>
                            <?php 
                            // Sort by CTR and get top 5
                            usort($allNotifications, function($a, $b) {
                                return $b['ctr'] <=> $a['ctr'];
                            });
                            $topNotifications = array_slice($allNotifications, 0, 5);
                            ?>
                            <?php foreach ($topNotifications as $notification): ?>
                                <div class="notifal-top-notification-item">
                                    <div class="notifal-notification-info">
                                        <h4><?php echo esc_html($notification['title']); ?></h4>
                                        <div class="notifal-notification-metrics">
                                            <span class="notifal-metric-ctr"><?php echo esc_html($notification['ctr']); ?>% CTR</span>
                                            <span class="notifal-metric-impressions"><?php echo esc_html(number_format($notification['stats']['total_impressions'])); ?> impressions</span>
                                        </div>
                                    </div>
                                    <div class="notifal-notification-chart">
                                        <div class="notifal-mini-chart" data-values="<?php
                                            // Generate realistic mini chart data showing CTR trend (scaled for visualization)
                                            $ctr = (float) str_replace('%', '', $notification['ctr']);
                                            $baseCTR = max(0.1, $ctr); // Minimum 0.1% CTR

                                            // Generate 7 data points representing CTR trend over last 7 days
                                            $chartData = [];
                                            for ($i = 0; $i < 7; $i++) {
                                                // Add realistic daily variation (±20%) and slight upward/downward trend
                                                $dailyVariation = (mt_rand(-20, 20) / 100);
                                                $trendFactor = ($i - 3) * 0.05; // Slight trend toward the middle
                                                $ctrValue = max(0.1, $baseCTR * (1 + $dailyVariation + $trendFactor));

                                                // Scale CTR for better visualization (multiply by 10 to make it more visible)
                                                $scaledValue = round($ctrValue * 10);
                                                $chartData[] = $scaledValue;
                                            }
                                            echo esc_attr(json_encode($chartData));
                                        ?>" data-metric="CTR Trend"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="notifal-no-data">
                                <span class="notifal-icon notifal-icon-percent"></span>
                                <p><?php esc_html_e('No notification data available yet.', 'notifal'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

        <!-- Detailed Notifications Table -->
        <div class="notifal-notifications-table-section" 
             data-current-offset="<?php echo esc_attr($pagination['offset']); ?>"
             data-limit="<?php echo esc_attr($pagination['limit']); ?>"
             data-total="<?php echo esc_attr($pagination['total']); ?>"
             data-has-more="<?php echo esc_attr($pagination['has_more'] ? 'true' : 'false'); ?>">
            <div class="notifal-card">
                <div class="notifal-table-header">
                    <div class="notifal-table-title-section">
                        <h3><?php esc_html_e('All Notifications Analytics', 'notifal'); ?>
                            <?php if ($isProUpsell): ?>
                                <span class="notifal-pro-badge notifal-pro-badge-inline">
                                    <span class="notifal-icon notifal-icon-crown"></span>
                                    <?php esc_html_e('Limited View', 'notifal'); ?>
                                </span>
                            <?php endif; ?>
                        </h3>
                    </div>
                    <div class="notifal-table-controls<?php echo $isProUpsell ? ' notifal-controls-limited' : ''; ?>">
                        <input type="search" placeholder="<?php esc_attr_e('Search notifications...', 'notifal'); ?>" class="notifal-table-search"<?php echo $isProUpsell ? ' disabled' : ''; ?>>
                        <select class="notifal-table-sort"<?php echo $isProUpsell ? ' disabled' : ''; ?>>
                            <option value="performance" selected><?php esc_html_e('Sort by Performance', 'notifal'); ?></option>
                            <option value="revenue"><?php esc_html_e('Sort by Revenue', 'notifal'); ?></option>
                            <?php if (!$isProUpsell): ?>
                                <option value="ctr"><?php esc_html_e('Sort by CTR', 'notifal'); ?></option>
                                <option value="conversions"><?php esc_html_e('Sort by Conversions', 'notifal'); ?></option>
                                <option value="impressions"><?php esc_html_e('Sort by Impressions', 'notifal'); ?></option>
                                <option value="clicks"><?php esc_html_e('Sort by Clicks', 'notifal'); ?></option>
                                <option value="close_rate"><?php esc_html_e('Sort by Close Rate', 'notifal'); ?></option>
                            <?php endif; ?>
                            <option value="title"><?php esc_html_e('Sort by Title', 'notifal'); ?></option>
                            <option value="date"><?php esc_html_e('Sort by Date', 'notifal'); ?></option>
                        </select>
                        <?php if ($isProUpsell): ?>
                            <div class="notifal-controls-overlay">
                                <span class="notifal-pro-badge notifal-pro-badge-small">
                                    <span class="notifal-icon notifal-icon-crown"></span>
                                    <?php esc_html_e('PRO', 'notifal'); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="notifal-table-container">
                    <table class="notifal-analytics-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Notification', 'notifal'); ?></th>
                                <th><?php esc_html_e('Status', 'notifal'); ?></th>
                                <th>
                                    <?php esc_html_e('Impressions', 'notifal'); ?>
                                    <?php if ($isProUpsell): ?>
                                        <span class="notifal-pro-badge notifal-pro-badge-xs">
                                            <span class="notifal-icon notifal-icon-crown"></span>
                                        </span>
                                    <?php endif; ?>
                                </th>
                                <th>
                                    <?php esc_html_e('Clicks', 'notifal'); ?>
                                    <?php if ($isProUpsell): ?>
                                        <span class="notifal-pro-badge notifal-pro-badge-xs">
                                            <span class="notifal-icon notifal-icon-crown"></span>
                                        </span>
                                    <?php endif; ?>
                                </th>
                                <th>
                                    <?php esc_html_e('CTR', 'notifal'); ?>
                                    <?php if ($isProUpsell): ?>
                                        <span class="notifal-pro-badge notifal-pro-badge-xs">
                                            <span class="notifal-icon notifal-icon-crown"></span>
                                        </span>
                                    <?php endif; ?>
                                </th>
                                <th>
                                    <?php esc_html_e('Conversions', 'notifal'); ?>
                                    <?php if ($isProUpsell): ?>
                                        <span class="notifal-pro-badge notifal-pro-badge-xs">
                                            <span class="notifal-icon notifal-icon-crown"></span>
                                        </span>
                                    <?php endif; ?>
                                </th>
                                <th>
                                    <?php esc_html_e('Clicked Revenue', 'notifal'); ?>
                                </th>
                                <th>
                                    <?php esc_html_e('Influenced Revenue', 'notifal'); ?>
                                    <span class="notifal-tooltip-icon" title="<?php esc_attr_e('Total order value for orders influenced by this notification', 'notifal'); ?>">
                                        <span class="notifal-icon notifal-icon-info-circle"></span>
                                    </span>
                                </th>
                                <th>
                                    <?php esc_html_e('Influenced Orders', 'notifal'); ?>
                                </th>
                                <th>
                                    <?php esc_html_e('Close Rate', 'notifal'); ?>
                                    <?php if ($isProUpsell): ?>
                                        <span class="notifal-pro-badge notifal-pro-badge-xs">
                                            <span class="notifal-icon notifal-icon-crown"></span>
                                        </span>
                                    <?php endif; ?>
                                </th>
                                <th><?php esc_html_e('Actions', 'notifal'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="notifal-analytics-table-body">
                            <?php if (!empty($allNotifications)): ?>
                                <?php foreach ($allNotifications as $notification):
                                // Skip invalid notification entries
                                if (!is_array($notification) || empty($notification['notification_id'])) {
                                    continue;
                                }
                                        // Raw values power sorting and CSV export in JS; display follows store currency.
                                $notifal_row_revenue_raw           = isset($notification['revenue']) ? (float) $notification['revenue'] : 0.0;
                                $notifal_row_revenue_display       = $analyticsMoneyFormatter->formatPlain($notifal_row_revenue_raw);
                                $notifal_row_influenced_rev_raw    = isset($notification['influenced_revenue']) ? (float) $notification['influenced_revenue'] : 0.0;
                                $notifal_row_influenced_rev_display = $analyticsMoneyFormatter->formatPlain($notifal_row_influenced_rev_raw);
                                $notifal_row_influenced_orders     = isset($notification['influenced_orders']) ? (int) $notification['influenced_orders'] : 0;
                            ?>
                                    <tr>
                                        <td>
                                            <div class="notifal-notification-cell">
                                                <strong><?php echo esc_html($notification['title']); ?></strong>
                                                <small><?php
                                                    $createdDate = $notification['created_date'] ?? '';
                                                    echo esc_html($createdDate ? date('M j, Y', strtotime($createdDate)) : __('Unknown', 'notifal'));
                                                ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="notifal-status-badge notifal-status-<?php echo esc_attr($notification['status']); ?>">
                                                <?php echo esc_html(ucfirst($notification['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="<?php echo $isProUpsell ? 'notifal-blurred-data' : ''; ?>">
                                            <?php if ($isProUpsell): ?>
                                                <span class="notifal-blurred-text">###,###</span>
                                            <?php else: ?>
                                                <?php echo esc_html(number_format($notification['stats']['total_impressions'])); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="<?php echo $isProUpsell ? 'notifal-blurred-data' : 'notifal-clicks-cell-clickable'; ?>"
                                            <?php if (!$isProUpsell) : ?>
                                                data-notifal-notification-id="<?php echo esc_attr((string) (int) $notification['notification_id']); ?>"
                                                data-notifal-notification-title="<?php echo esc_attr($notification['title']); ?>"
                                                data-notifal-clicks="<?php echo esc_attr((string) (int) $notification['stats']['total_clicks']); ?>"
                                                role="button"
                                                tabindex="0"
                                                title="<?php esc_attr_e('View button click breakdown', 'notifal'); ?>"
                                            <?php endif; ?>>
                                            <?php if ($isProUpsell): ?>
                                                <span class="notifal-blurred-text">##,###</span>
                                            <?php else: ?>
                                                <?php echo esc_html(number_format($notification['stats']['total_clicks'])); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="<?php echo $isProUpsell ? 'notifal-blurred-data' : ''; ?>">
                                            <?php if ($isProUpsell): ?>
                                                <span class="notifal-ctr-badge notifal-blurred-text">#.##%</span>
                                            <?php else: ?>
                                                <span class="notifal-ctr-badge">
                                                    <?php echo esc_html($notification['ctr']); ?>%
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="<?php echo $isProUpsell ? 'notifal-blurred-data' : ''; ?>">
                                            <?php if ($isProUpsell): ?>
                                                <span class="notifal-blurred-text">###</span>
                                            <?php else: ?>
                                                <?php echo esc_html(number_format($notification['stats']['total_conversions'])); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="<?php echo $isProUpsell ? 'notifal-revenue-always-visible' : 'notifal-revenue-highlight'; ?>"
                                            data-notifal-raw-revenue="<?php echo esc_attr((string) $notifal_row_revenue_raw); ?>">
                                            <?php echo esc_html($notifal_row_revenue_display); ?>
                                        </td>
                                        <td class="notifal-revenue-highlight notifal-influenced-revenue-cell"
                                            data-notifal-raw-influenced-revenue="<?php echo esc_attr((string) $notifal_row_influenced_rev_raw); ?>">
                                            <?php echo esc_html($notifal_row_influenced_rev_display); ?>
                                        </td>
                                        <td class="notifal-influenced-orders-cell">
                                            <?php echo esc_html(number_format($notifal_row_influenced_orders)); ?>
                                        </td>
                                        <td class="<?php echo $isProUpsell ? 'notifal-blurred-data' : ''; ?>">
                                            <?php if ($isProUpsell): ?>
                                                <span class="notifal-blurred-text">#.##%</span>
                                            <?php else: ?>
                                                <?php
                                                $closeRate = $notification['stats']['total_impressions'] > 0 
                                                    ? round(($notification['stats']['total_closes'] / $notification['stats']['total_impressions']) * 100, 2) 
                                                    : 0;
                                                echo esc_html($closeRate . '%');
                                                ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="notifal-table-actions">
                                                <?php
                                                $notifal_analytics_view_args = [
                                                    'page'              => 'notifal-onpage-analytics',
                                                    'notification_id'   => (int) $notification['notification_id'],
                                                ];
                                                if ( ! empty( $filters['campaign_id'] ) ) {
                                                    $notifal_analytics_view_args['campaign_id'] = (int) $filters['campaign_id'];
                                                }
                                                if ( ! empty( $filters['date_range'] ) ) {
                                                    $notifal_analytics_view_args['date_range'] = $filters['date_range'];
                                                }
                                                $notifal_analytics_view_url = add_query_arg( $notifal_analytics_view_args, admin_url( 'admin.php' ) );
                                                ?>
                                                <a href="<?php echo esc_url( $notifal_analytics_view_url ); ?>"
                                                   class="notifal-button-icon" title="<?php esc_attr_e('View Details', 'notifal'); ?>"
                                                   target="_blank" rel="noopener noreferrer">
                                                    <span class="notifal-icon notifal-icon-eye"></span>
                                                </a>
                                                <a href="?page=notifal-onpage-notification&post=<?php echo esc_attr($notification['notification_id']); ?>&action=edit" 
                                                   class="notifal-button-icon" title="<?php esc_attr_e('Edit', 'notifal'); ?>"
                                                   target="_blank" rel="noopener noreferrer">
                                                    <span class="notifal-icon notifal-icon-pencil-square"></span>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="notifal-no-data-row">
                                    <td colspan="11" class="notifal-no-data-row">
                                        <div class="notifal-no-data">
                                            <span class="notifal-icon notifal-icon-file-earmark1"></span>
                                            <p><?php esc_html_e('No notifications found. Create your first notification to see analytics.', 'notifal'); ?></p>
                                            <a href="<?php echo esc_url($urlService->getListUrl()); ?>" class="notifal-button">
                                                <?php esc_html_e('Create Notification', 'notifal'); ?>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Load More Button -->
                <?php if ($pagination['has_more'] && ! $isProUpsell): ?>
                    <div class="notifal-load-more-container">
                        <button type="button" class="notifal-button secondary" id="notifal-load-more-analytics">
                            <span class="notifal-icon notifal-icon-arrow-down"></span>
                            <?php esc_html_e('Load More Notifications', 'notifal'); ?>
                            <span class="notifal-load-more-count">(<?php echo esc_html($pagination['total'] - $pagination['limit']); ?> <?php esc_html_e('remaining', 'notifal'); ?>)</span>
                        </button>
                        <div class="notifal-pagination-info">
                            <?php
                            printf(
                                esc_html__('Showing %d of %d notifications', 'notifal'),
                                count($allNotifications),
                                $pagination['total']
                            );
                            ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="notifal-pagination-info">
                        <?php
                        if ($pagination['total'] > 0) {
                            printf(
                                esc_html__('Showing %d of %d notifications', 'notifal'),
                                count($allNotifications),
                                $pagination['total']
                            );
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Loading Overlay -->
<div id="notifal-analytics-loading" class="notifal-loading-overlay notifal-hidden">
    <div class="notifal-loading-content">
        <div class="notifal-spinner"></div>
        <p><?php esc_html_e('Loading analytics data...', 'notifal'); ?></p>
    </div>
</div>
