<?php

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\OnPageNotification\Application\Services\API\PreCreatedNotificationsApiService;
use Notifal\Modules\OnPageNotification\Helpers\PreCreatedNotificationFilterHelper;
use Notifal\Modules\OnPageNotification\Presentation\Admin\Components\FilterRenderer;
use Notifal\Shared\AdminUI\Fields\FieldRenderer;
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

// When preloaded data is provided (e.g. from AJAX), skip API calls so page load is not blocked
$usePreloaded = isset($preloaded_taxonomies) && isset($preloaded_api_response) && is_array($preloaded_taxonomies) && is_array($preloaded_api_response);

if ($usePreloaded) {
    $currentFilters = isset($preloaded_filters) && is_array($preloaded_filters) ? $preloaded_filters : PreCreatedNotificationFilterHelper::parseCurrentFilters();
    $taxonomies = $preloaded_taxonomies;
    $apiResponse = $preloaded_api_response;
    $trending = isset($preloaded_trending) && is_array($preloaded_trending) ? $preloaded_trending : $apiService->getTrendingCategories();
} else {
    // Parse current filter state from URL parameters
    $currentFilters = PreCreatedNotificationFilterHelper::parseCurrentFilters();
    // Get taxonomies from API for filter sidebar
    $taxonomies = $apiService->getTaxonomies();
    // Build API query parameters from filters
    $apiArgs = PreCreatedNotificationFilterHelper::buildApiQueryArgs($currentFilters);
    // Get notifications data from API
    $apiResponse = $apiService->getNotifications($apiArgs);
    // Get trending categories from API (use case slugs for filtering)
    $trending = $apiService->getTrendingCategories();
}

// Component configuration
$hideHeader = isset($hide_header) ? $hide_header : false;
$componentId = isset($component_id) ? $component_id : 'precreated-notifications-archive';
$hideWrapper = isset($hide_wrapper) ? $hide_wrapper : false;
$archiveContainerId = 'notifal-precreated-archive-container';
if ($componentId !== 'precreated-notifications-archive') {
    $archiveContainerId = sanitize_html_class($componentId) . '-root';
}

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

// Build cache "next update" text and tooltip for display (1 hour cache)
$cacheExpiresAt = isset($apiResponse['cache_expires_at']) ? (int) $apiResponse['cache_expires_at'] : 0;
$cacheNextUpdateText = '';
$cacheTooltipText = __(
    'This list is cached for 1 hour. If you don\'t see a new template or can\'t import a template\'s file builder, try again later after the cache refreshes.',
    'notifal'
);
if ($cacheExpiresAt > 0) {
    $secondsLeft = $cacheExpiresAt - time();
    if ($secondsLeft > 0) {
        $minutesLeft = (int) ceil($secondsLeft / 60);
        if ($minutesLeft >= 60) {
            $hours = (int) floor($minutesLeft / 60);
            $mins = $minutesLeft % 60;
            $cacheNextUpdateText = $mins > 0
                ? sprintf(
                    /* translators: 1: hours, 2: minutes */
                    __('Next update: %1$s hr %2$s min later', 'notifal'),
                    (string) $hours,
                    (string) $mins
                )
                : sprintf(
                    /* translators: %d: number of hours */
                    _n('Next update: %d hr later', 'Next update: %d hrs later', $hours, 'notifal'),
                    $hours
                );
        } else {
            $cacheNextUpdateText = sprintf(
                /* translators: %d: number of minutes */
                _n('Next update: %d min later', 'Next update: %d min later', $minutesLeft, 'notifal'),
                $minutesLeft
            );
        }
    } else {
        $cacheNextUpdateText = __('Next update: refreshing soon', 'notifal');
    }
} else {
    $cacheNextUpdateText = __('List refreshes every hour', 'notifal');
}
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
            <?php if ($cacheNextUpdateText !== '') : ?>
                <div class="notifal-archive-cache-info notifal-flex notifal-align-center notifal-gap-8">
                    <span class="notifal-archive-cache-time notifal-text-muted"><?php echo esc_html($cacheNextUpdateText); ?></span>
                    <?php FieldRenderer::tooltip($cacheTooltipText, ['data-position' => 'bottom']); ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="archive-container" id="<?php echo esc_attr($archiveContainerId); ?>">
        <aside class="archive-sidebar" id="notifal-archive-sidebar" aria-label="<?php esc_attr_e( 'Filters', 'notifal' ); ?>">
            <div class="filter-sidebar">
                <?php $filterRenderer->renderArchiveFilters( $taxonomies, $currentFilters ); ?>
            </div>
        </aside>

        <main class="archive-main-content">
            <?php if (!empty($trending)) : ?>
            <div class="notifal-precreated-archive-trending" role="navigation" aria-label="<?php esc_attr_e('Trending categories', 'notifal'); ?>">
                <span class="notifal-precreated-archive-trending-label"><?php esc_html_e('Trending', 'notifal'); ?></span>
                <div class="notifal-precreated-archive-trending-tags">
                    <?php foreach ($trending as $item) :
                        $is_active = !empty($currentFilters['use_case']) && in_array($item['use_case_slug'], (array) $currentFilters['use_case'], true);
                        ?>
                        <button type="button"
                                class="notifal-trending-tag<?php echo $is_active ? ' is-active' : ''; ?>"
                                data-use-case="<?php echo esc_attr($item['use_case_slug']); ?>"
                                aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>">
                            <?php echo esc_html($item['title']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <!-- Search: by name, template code, or taxonomy (aligned with marketplace) -->
            <div class="archive-search-section">
                <div class="archive-search-inner">
                    <input
                        type="search"
                        id="notifal-precreated-archive-search"
                        class="archive-search-input notifal-precreated-archive-search"
                        name="search"
                        placeholder="<?php esc_attr_e( 'Search by name, template code (e.g. 80 or 80,82), or category…', 'notifal' ); ?>"
                        value="<?php echo esc_attr( $currentFilters['search'] ?? '' ); ?>"
                        autocomplete="off"
                        aria-label="<?php esc_attr_e( 'Search notifications', 'notifal' ); ?>"
                    />
                </div>
            </div>

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
        <!-- Template request note (below footer, shown when template has no builder file) -->
        <?php render_notification_detail_popup_request_note('archive'); ?>
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
