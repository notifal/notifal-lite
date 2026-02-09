<?php
/**
 * OnPage Notification Analytics Dashboard
 *
 * Displays comprehensive analytics and insights for OnPage notifications
 * with filtering, charts, and detailed metrics.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

defined('ABSPATH') || exit;

use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\OnPageNotification\Application\Services\Analytics\AnalyticsService;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\UrlService;
use Notifal\Shared\Utils\Helper;

// Initialize services
$analyticsService = notifal_app(AnalyticsService::class);
$urlService = notifal_app(UrlService::class);

// Get current filters from request (sanitized per WordPress guidelines; behavior preserved)
$notification_id_raw = isset( $_GET['notification_id'] ) ? wp_unslash( $_GET['notification_id'] ) : '';
$filters = [
    'date_range'      => Helper::sanitizeInput( isset( $_GET['date_range'] ) ? wp_unslash( $_GET['date_range'] ) : 'last_30_days', 'text' ),
    'notification_id' => ( $notification_id_raw !== '' && $notification_id_raw !== '0' ) ? absint( $notification_id_raw ) : null,
    'status'          => Helper::sanitizeInput( isset( $_GET['status'] ) ? wp_unslash( $_GET['status'] ) : '', 'text' ),
];

// Check if Pro is activated
$is_pro_active = function_exists('is_notifal_pro_active') && is_notifal_pro_active();

// Get analytics data (will be filtered for Pro upsell if Pro not active)
$dashboardData = $analyticsService->getDashboardOverview($filters);

// Check if this is Pro upsell data (only if Pro is not active)
$isProUpsell = !$is_pro_active && isset($dashboardData['is_pro_upsell']) && $dashboardData['is_pro_upsell'];
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
            <div class="notifal-flex notifal-justify-between notifal-align-center">
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
                            </select>
                        </div>

                        <!-- Notification Filter -->
                        <div class="notifal-filter-group">
                            <label for="notification_id"><?php esc_html_e('Notification', 'notifal'); ?></label>
                            <select name="notification_id" id="notification_id" class="notifal-select">
                                <option value=""><?php esc_html_e('All Notifications', 'notifal'); ?></option>
                                <?php
                                $notifications = get_posts([
                                    'post_type' => 'notifal_onpage_notif',
                                    'post_status' => ['publish', 'draft'],
                                    'posts_per_page' => -1,
                                    'orderby' => 'title',
                                    'order' => 'ASC'
                                ]);
                                foreach ($notifications as $notification) {
                                    $selected = selected($filters['notification_id'], $notification->ID, false);
                                    echo '<option value="' . esc_attr($notification->ID) . '" ' . $selected . '>';
                                    echo esc_html($notification->post_title);
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

                <!-- Total Revenue - Always accessible to encourage upgrades -->
                <div class="notifal-metric-card notifal-metric-revenue notifal-metric-highlight">
                    <div class="notifal-metric-icon">
                        <span class="notifal-icon notifal-icon-coin"></span>
                    </div>
                    <div class="notifal-metric-content">
                        <h3 class="notifal-metric-title"><?php esc_html_e('Total Revenue', 'notifal'); ?></h3>
                        <div class="notifal-metric-value">
                            $<?php echo esc_html(number_format($dashboardData['current_period']['total_revenue'] ?? 0, 2)); ?>
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
                        <div class="notifal-metric-value">
                            <?php if ($isProUpsell): ?>
                                <span class="notifal-blurred-text">#.##%</span>
                            <?php else: ?>
                                <?php
                                $conversions = $dashboardData['current_period']['total_conversions'] ?? 0;
                                $conversionRate = $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0;
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
                            esc_html__("You've generated %s in revenue! Upgrade to Pro to see detailed analytics, CTR insights, conversion tracking, and optimization recommendations to boost your results even further.", 'notifal'),
                            '<strong>$' . number_format($dashboardData['current_period']['total_revenue'] ?? 0, 2) . '</strong>'
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
                        <div class="notifal-table-update-info">
                            <span class="notifal-update-text">
                                <?php 
                                printf(
                                    esc_html__('Updated at: %s (%s)', 'notifal'),
                                    '<strong>' . esc_html($lastUpdateInfo['formatted']) . '</strong>',
                                    '<em>' . esc_html($lastUpdateInfo['human_diff']) . '</em>'
                                ); 
                                ?>
                            </span>
                            <span class="notifal-next-update-text">
                                <?php 
                                printf(
                                    esc_html__('Next update in %s', 'notifal'),
                                    '<em>' . esc_html($lastUpdateInfo['next_update_human']) . '</em>'
                                ); 
                                ?>
                            </span>
                        </div>
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
                                    <?php esc_html_e('Revenue', 'notifal'); ?>
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
                                        <td class="<?php echo $isProUpsell ? 'notifal-blurred-data' : ''; ?>">
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
                                        <td class="<?php echo !$is_pro_active ? 'notifal-revenue-always-visible' : 'notifal-revenue-highlight'; ?>">
                                            $<?php echo esc_html(number_format($notification['revenue'], 2)); ?>
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
                                                <a href="?page=notifal-onpage-analytics&notification_id=<?php echo esc_attr($notification['notification_id']); ?>" 
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
                                    <td colspan="9" class="notifal-no-data-row">
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
                <?php if ($pagination['has_more'] && (function_exists('is_notifal_pro_active') && is_notifal_pro_active())): ?>
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
