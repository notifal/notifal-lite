<?php

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\OnPageNotification\Application\Services\API\PreCreatedNotificationsApiService;
use Notifal\Modules\OnPageNotification\Helpers\PreCreatedNotificationFilterHelper;
use Notifal\Modules\OnPageNotification\Presentation\Admin\Components\FilterRenderer;
use Notifal\Modules\OnPageNotification\Presentation\Admin\Components\NotificationDetailPopupComponent;
use Notifal\Shared\Services\NotifalIconService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pre-created Notifications Archive Component
 *
 * Displays the archive view of pre-created notifications from notifal.com API.
 * Provides filtering and browsing capabilities for pre-created notification templates.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

// Include required components
require_once __DIR__ . '/../../Components/NotificationCardComponent.php';
require_once __DIR__ . '/../../Components/NotificationDetailPopupComponent.php';

// Get service instances
$apiService = notifal_app(PreCreatedNotificationsApiService::class);
$filterRenderer = new FilterRenderer();

// Parse current filter state from URL parameters
$currentFilters = PreCreatedNotificationFilterHelper::parseCurrentFilters();

// Get taxonomies from API for filter sidebar
$taxonomies = $apiService->getTaxonomies();

// Component configuration
$hideHeader = isset($hide_header) ? $hide_header : false;
$componentId = isset($component_id) ? $component_id : 'precreated-notifications-archive';
$hideWrapper = isset($hide_wrapper) ? $hide_wrapper : false;

// Build API query parameters from filters
$apiArgs = PreCreatedNotificationFilterHelper::buildApiQueryArgs($currentFilters);

// Get notifications data from API
$apiResponse = $apiService->getNotifications($apiArgs);
$notifications = [];
$pagination = ['current' => 1, 'pages' => 1, 'total' => 0];
$hasError = false;
$errorMessage = '';

if (isset($apiResponse['success']) && !$apiResponse['success']) {
    $hasError = true;
    $errorMessage = $apiResponse['error'] ?? __('Failed to load notifications.', 'notifal');
} elseif (isset($apiResponse['data'])) {
    $notificationsData = $apiResponse['data'];
    $notifications = $notificationsData['notifications'] ?? [];
    $pagination = $notificationsData['pagination'] ?? $pagination;
}

/**
 * Fires before rendering the pre-created notifications archive.
 *
 * @since 2.0.0
 * @param array $apiResponse API response data
 */
do_action(ActionHooks::PRE_CREATED_NOTIFICATIONS_ARCHIVE_BEFORE, [], $apiResponse);
?>

<?php if (!$hideWrapper) : ?>
<div class="notifal-marketplace-archive" data-component="<?php echo esc_attr(sanitize_key($componentId)); ?>">
<?php endif; ?>
    <?php if (!$hideHeader) : ?>
        <div class="notifal-archive-header">
            <div class="notifal-archive-title-section">
                <h2 class="notifal-archive-title notifal-page-title">
                    <?php esc_html_e('Explore Pre-created Notifications', 'notifal'); ?>
                </h2>
            </div>
        </div>
    <?php endif; ?>

    <div class="archive-container">
        <aside class="archive-sidebar">
            <h3><?php esc_html_e('Available Filters', 'notifal'); ?></h3>
            <div class="filter-sidebar">
                <?php $filterRenderer->renderArchiveFilters($taxonomies, $currentFilters); ?>
            </div>
        </aside>

        <main class="archive-main-content">
            <div class="notifications-grid-wrapper">
                <?php if ($hasError) : ?>
                    <div class="notifal-archive-error notifal-text-center notifal-mt-30">
                        <div class="notifal-error-icon notifal-mb-20">
                            <?php echo NotifalIconService::render('exclamation-triangle', 48); ?>
                        </div>
                        <h3 class="notifal-error-title notifal-mb-10">
                            <?php esc_html_e('Unable to Load Notifications', 'notifal'); ?>
                        </h3>
                        <p class="notifal-error-message notifal-text-muted notifal-mb-20">
                            <?php echo esc_html($errorMessage); ?>
                        </p>
                        <button type="button" class="notifal-button primary" onclick="window.location.reload()">
                            <?php esc_html_e('Try Again', 'notifal'); ?>
                        </button>
                    </div>
                <?php elseif (!empty($notifications)) : ?>
                    <div class="notifications-grid"
                         data-columns="3"
                         data-current-page="<?php echo esc_attr($pagination['current'] ?? 1); ?>"
                         data-total-pages="<?php echo esc_attr($pagination['pages'] ?? 1); ?>"
                         data-has-more="<?php echo esc_attr(
                             (isset($pagination['total']) && $pagination['total'] > 12 &&
                              isset($pagination['current']) && isset($pagination['pages']) &&
                              $pagination['current'] < $pagination['pages']) ? '1' : '0'
                         ); ?>">
                        <?php foreach ($notifications as $notification) : ?>
                            <?php render_notification_card($notification); ?>
                        <?php endforeach; ?>
                    </div>

                    <?php if (isset($pagination['total']) && $pagination['total'] > 12 &&
                              isset($pagination['current']) && isset($pagination['pages']) &&
                              $pagination['current'] < $pagination['pages']) : ?>
                        <div class="load-more-container">
                            <button class="load-more-notifications load-more-btn"
                                    data-page="<?php echo esc_attr($pagination['current']); ?>">
                                <?php esc_html_e('Show More', 'notifal'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                <?php else : ?>
                    <div class="no-notifications">
                        <p><?php esc_html_e('No notifications found matching your criteria.', 'notifal'); ?></p>
                        <button id="reset-filters" class="reset-filters-btn">
                            <?php esc_html_e('Reset Filters', 'notifal'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- Notification Details Popup Modal -->
<div id="notifal-notification-popup" class="notifal-notification-popup notifal-popup-hidden">
    <div class="notifal-popup-overlay"></div>
    <div class="notifal-popup-container">
        <!-- Popup Header -->
        <div class="notifal-popup-header">
            <button type="button" class="notifal-popup-back-btn notifal-button secondary" id="notifalPopupHeaderBackBtn" aria-label="<?php esc_attr_e( 'Back', 'notifal' ); ?>">
                <?php echo NotifalIconService::render('arrow-left-short', 16); ?>
                <?php esc_html_e( 'Back', 'notifal' ); ?>
            </button>
            <h3 class="notifal-popup-title"><?php esc_html_e( 'Notification Details', 'notifal' ); ?></h3>
        </div>

        <!-- Popup Content -->
        <div class="notifal-popup-content">
            <?php render_notification_detail_popup('archive'); ?>
        </div>

        <!-- Popup Footer -->
        <div class="notifal-popup-footer">
            <button type="button" class="notifal-popup-back-btn notifal-button secondary" id="notifalPopupFooterBackBtn" aria-label="<?php esc_attr_e( 'Back', 'notifal' ); ?>">
                <?php echo NotifalIconService::render('arrow-left-short', 16); ?>
                <?php esc_html_e( 'Back', 'notifal' ); ?>
            </button>
            <?php render_notification_detail_popup_footer('archive'); ?>
        </div>
    </div>
<?php if (!$hideWrapper) : ?>
</div>
<?php endif; ?>

<?php
/**
 * Fires after rendering the pre-created notifications archive.
 *
 * @since 2.0.0
 * @param array $apiResponse API response data
 */
do_action(ActionHooks::PRE_CREATED_NOTIFICATIONS_ARCHIVE_AFTER, $apiResponse);
?>
