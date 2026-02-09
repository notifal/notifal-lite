<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax;

use Notifal\Modules\OnPageNotification\Application\Services\Analytics\AnalyticsService;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class AnalyticsTableController
 *
 * Handles AJAX requests for analytics table pagination and sorting.
 *
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class AnalyticsTableController
{
    /**
     * Register AJAX handlers.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register(): void
    {
        add_action('wp_ajax_notifal_load_more_analytics', [self::class, 'loadMoreNotifications']);
        add_action('wp_ajax_notifal_get_sorted_analytics', [self::class, 'getSortedNotifications']);
        add_action('wp_ajax_notifal_get_chart_data', [self::class, 'getChartData']);
        add_action('wp_ajax_notifal_force_process_events', [self::class, 'forceProcessEvents']);
    }

    /**
     * AJAX: Load more notifications for pagination.
     *
     * @return void
     * @since 2.0.0
     */
    public static function loadMoreNotifications(): void
    {
        // Verify nonce and user capabilities
        notifal_verify_ajax_request('notifal_analytics_nonce', 'manage_options');

        // Get parameters from request
        $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
        $sort_by = Helper::sanitizeInput($_POST['sort_by'] ?? 'performance', 'text');
        $search = Helper::sanitizeInput($_POST['search'] ?? '', 'text');

        // Get current filters
        $filters = [
            'date_range' => Helper::sanitizeInput($_POST['date_range'] ?? 'last_30_days', 'text'),
            'notification_id' => isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : null,
            'status' => Helper::sanitizeInput($_POST['status'] ?? '', 'text'),
            'sort_by' => $sort_by,
            'limit' => $limit,
            'offset' => $offset,
        ];

        // Get analytics service
        $analyticsService = notifal_app(AnalyticsService::class);
        
        try {
            // Get paginated data
            $result = $analyticsService->getPaginatedNotificationsAnalytics($filters);
            
            // Filter by search if provided
            if (!empty($search)) {
                $result['notifications'] = array_filter($result['notifications'], function($notification) use ($search) {
                    return stripos($notification['title'], $search) !== false;
                });
                // Reindex array after filtering
                $result['notifications'] = array_values($result['notifications']);
            }

            // Generate HTML for the new rows
            $html = self::generateTableRowsHTML($result['notifications']);

            notifal_json_success([
                'html' => $html,
                'pagination' => $result['pagination'],
                'total_found' => count($result['notifications'])
            ]);

        } catch (\Exception $e) {
            notifal_json_error(__('Error loading notifications: ', 'notifal') . $e->getMessage());
        }
    }

    /**
     * AJAX: Get sorted notifications (full refresh with sorting).
     *
     * @return void
     * @since 2.0.0
     */
    public static function getSortedNotifications(): void
    {
        // Verify nonce and user capabilities
        notifal_verify_ajax_request('notifal_analytics_nonce', 'manage_options');

        // Get parameters from request
        $sort_by = Helper::sanitizeInput($_POST['sort_by'] ?? 'performance', 'text');
        $search = Helper::sanitizeInput($_POST['search'] ?? '', 'text');
        $limit = 10; // Always start with 10 for sorting

        // Get current filters
        $filters = [
            'date_range' => Helper::sanitizeInput($_POST['date_range'] ?? 'last_30_days', 'text'),
            'notification_id' => isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : null,
            'status' => Helper::sanitizeInput($_POST['status'] ?? '', 'text'),
            'sort_by' => $sort_by,
            'limit' => $limit,
            'offset' => 0, // Reset to first page when sorting
        ];

        // Get analytics service
        $analyticsService = notifal_app(AnalyticsService::class);
        
        try {
            // Get paginated data
            $result = $analyticsService->getPaginatedNotificationsAnalytics($filters);
            
            // Filter by search if provided
            if (!empty($search)) {
                $result['notifications'] = array_filter($result['notifications'], function($notification) use ($search) {
                    return stripos($notification['title'], $search) !== false;
                });
                // Reindex array after filtering
                $result['notifications'] = array_values($result['notifications']);
            }

            // Generate HTML for all rows
            $html = self::generateTableRowsHTML($result['notifications']);

            notifal_json_success([
                'html' => $html,
                'pagination' => $result['pagination'],
                'total_found' => count($result['notifications'])
            ]);

        } catch (\Exception $e) {
            notifal_json_error(__('Error sorting notifications: ', 'notifal') . $e->getMessage());
        }
    }

    /**
     * Generate HTML for table rows.
     *
     * @param array $notifications Array of notification data
     * @return string Generated HTML
     * @since 2.0.0
     */
    private static function generateTableRowsHTML(array $notifications): string
    {
        if (empty($notifications)) {
            return '';
        }

        $html = '';
        
        foreach ($notifications as $notification) {
            $html .= '<tr>';
            
            // Notification column
            $html .= '<td>';
            $html .= '<div class="notifal-notification-cell">';
            $html .= '<strong>' . esc_html($notification['title']) . '</strong>';
            $html .= '<small>' . esc_html(date('M j, Y', strtotime($notification['created_date']))) . '</small>';
            $html .= '</div>';
            $html .= '</td>';
            
            // Status column
            $html .= '<td>';
            $html .= '<span class="notifal-status-badge notifal-status-' . esc_attr($notification['status']) . '">';
            $html .= esc_html(ucfirst($notification['status']));
            $html .= '</span>';
            $html .= '</td>';
            
            // Impressions column
            $html .= '<td>' . esc_html(number_format($notification['stats']['total_impressions'])) . '</td>';
            
            // Clicks column
            $html .= '<td>' . esc_html(number_format($notification['stats']['total_clicks'])) . '</td>';
            
            // CTR column
            $html .= '<td>';
            $html .= '<span class="notifal-ctr-badge">';
            $html .= esc_html($notification['ctr']) . '%';
            $html .= '</span>';
            $html .= '</td>';
            
            // Conversions column
            $html .= '<td>' . esc_html(number_format($notification['stats']['total_conversions'])) . '</td>';
            
            // Revenue column
            // Use secure hook that only the legitimate pro plugin can provide
            $revenueClass = apply_filters('notifal_pro_enhanced_analytics_allowed', false) ? 'notifal-revenue-highlight' : 'notifal-revenue-always-visible';
            $html .= '<td class="' . $revenueClass . '">$' . esc_html(number_format($notification['revenue'], 2)) . '</td>';
            
            // Close Rate column
            $html .= '<td>';
            $closeRate = $notification['stats']['total_impressions'] > 0 
                ? round(($notification['stats']['total_closes'] / $notification['stats']['total_impressions']) * 100, 2) 
                : 0;
            $html .= esc_html($closeRate . '%');
            $html .= '</td>';
            
            // Actions column
            $html .= '<td>';
            $html .= '<div class="notifal-table-actions">';
            $html .= '<a href="?page=notifal-onpage-analytics&notification_id=' . esc_attr($notification['notification_id']) . '" ';
            $html .= 'class="notifal-button-icon" title="' . esc_attr__('View Details', 'notifal') . '">';
            $html .= '<span class="notifal-icon notifal-icon-eye"></span>';
            $html .= '</a>';
            $html .= '<a href="?page=notifal-onpage-notification&post=' . esc_attr($notification['notification_id']) . '&action=edit" ';
            $html .= 'class="notifal-button-icon" title="' . esc_attr__('Edit', 'notifal') . '">';
            $html .= '<span class="notifal-icon notifal-icon-pencil-square"></span>';
            $html .= '</a>';
            $html .= '</div>';
            $html .= '</td>';
            
            $html .= '</tr>';
        }

        return $html;
    }

    /**
     * AJAX: Get chart data for analytics dashboard.
     *
     * @return void
     * @since 2.0.0
     */
    public static function getChartData(): void
    {
        // Verify nonce and user capabilities
        notifal_verify_ajax_request('notifal_analytics_nonce', 'manage_options');

        // Get parameters from request
        $metric = Helper::sanitizeInput($_POST['metric'] ?? 'impressions', 'text');

        // Get current filters
        $notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : null;

        // If notification_id is 0 or empty, treat as null (all notifications)
        if ($notificationId === 0) {
            $notificationId = null;
        }

        $filters = [
            'date_range' => Helper::sanitizeInput($_POST['date_range'] ?? 'last_30_days', 'text'),
            'notification_id' => $notificationId,
            'status' => Helper::sanitizeInput($_POST['status'] ?? '', 'text'),
        ];

        // Get analytics service
        $analyticsService = notifal_app(AnalyticsService::class);
        
        try {
            // Get chart data
            $chartData = $analyticsService->getChartData($filters);
            
            // Get the specific metric data
            $metricKey = $metric . '_over_time';
            $timeSeriesData = $chartData[$metricKey] ?? [];
            
            // Format data for frontend chart
            $formattedData = self::formatChartData($timeSeriesData, $metric, $filters);

            notifal_json_success([
                'chart_data' => $formattedData,
                'metric' => $metric,
                'date_range' => $filters['date_range']
            ]);

        } catch (\Exception $e) {
            notifal_json_error(__('Error loading chart data: ', 'notifal') . $e->getMessage());
        }
    }

    /**
     * Format chart data for frontend consumption.
     *
     * @param array $timeSeriesData Raw time series data
     * @param string $metric Metric name
     * @param array $filters Current filters
     * @return array Formatted chart data
     * @since 2.0.0
     */
    private static function formatChartData(array $timeSeriesData, string $metric, array $filters): array
    {
        if (empty($timeSeriesData)) {
            return [
                'labels' => [],
                'datasets' => [[
                    'label' => ucfirst($metric),
                    'data' => [],
                    'borderColor' => self::getMetricColor($metric),
                    'backgroundColor' => self::getMetricColor($metric, true),
                    'tension' => 0.4,
                    'fill' => true
                ]]
            ];
        }

        // Extract labels and data
        $labels = [];
        $data = [];

        foreach ($timeSeriesData as $point) {
            $labels[] = self::formatDateLabel($point['date'], $filters['date_range']);
            $data[] = (float)$point['value'];
        }

        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => ucfirst($metric),
                'data' => $data,
                'borderColor' => self::getMetricColor($metric),
                'backgroundColor' => self::getMetricColor($metric, true),
                'tension' => 0.4,
                'fill' => true
            ]]
        ];
    }

    /**
     * Get color for chart metric.
     *
     * @param string $metric Metric name
     * @param bool $withAlpha Whether to include alpha transparency
     * @return string Color value
     * @since 2.0.0
     */
    private static function getMetricColor(string $metric, bool $withAlpha = false): string
    {
        $colors = [
            'impressions' => $withAlpha ? 'rgba(59, 130, 246, 0.1)' : 'rgb(59, 130, 246)',
            'clicks' => $withAlpha ? 'rgba(16, 185, 129, 0.1)' : 'rgb(16, 185, 129)',
            'conversions' => $withAlpha ? 'rgba(139, 92, 246, 0.1)' : 'rgb(139, 92, 246)',
            'revenue' => $withAlpha ? 'rgba(39, 174, 96, 0.1)' : 'rgb(39, 174, 96)',
            'ctr' => $withAlpha ? 'rgba(126, 43, 210, 0.1)' : 'rgb(126, 43, 210)',
        ];
        
        return $colors[$metric] ?? $colors['impressions'];
    }

    /**
     * Format date label based on date range.
     *
     * @param string $date Date string
     * @param string $dateRange Date range filter
     * @return string Formatted date label
     * @since 2.0.0
     */
    private static function formatDateLabel(string $date, string $dateRange): string
    {
        $dateTime = new \DateTime($date);
        
        switch ($dateRange) {
            case 'today':
            case 'yesterday':
                return $dateTime->format('H:i'); // Hours for single day
            case 'last_7_days':
                return $dateTime->format('D'); // Day abbreviation
            case 'last_30_days':
            case 'last_90_days':
                return $dateTime->format('M j'); // Month and day
            default:
                return $dateTime->format('M j'); // Default to month and day
        }
    }

    /**
     * AJAX: Force process pending analytics events.
     *
     * @return void
     * @since 2.0.0
     */
    public static function forceProcessEvents(): void
    {
        // Verify nonce and user capabilities
        notifal_verify_ajax_request('notifal_analytics_nonce', 'manage_options');

        // Get analytics service
        $analyticsService = notifal_app(AnalyticsService::class);
        
        try {
            // Force process all pending events
            $result = $analyticsService->forceProcessPendingEvents();

            if ($result['success']) {
                notifal_json_success([
                    'message' => $result['message'],
                    'total_processed' => $result['total_processed'],
                    'total_errors' => $result['total_errors'],
                    'iterations' => $result['iterations']
                ]);
            } else {
                notifal_json_error($result['message'] ?? __('Processing failed', 'notifal'));
            }

        } catch (\Exception $e) {
            notifal_json_error(__('Error processing events: ', 'notifal') . $e->getMessage());
        }
    }
}
