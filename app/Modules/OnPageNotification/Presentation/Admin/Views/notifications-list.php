<?php

/**
 * OnPage Notifications List View
 *
 * Renders the admin list page for OnPage Notifications with filtering, searching,
 * and bulk actions capabilities. Includes pre-created notifications archive and import functionality.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Views
 */

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Shared\AdminUI\Lists\BaseListView;
use Notifal\Shared\AdminUI\Toast\ToastRenderer;
use Notifal\Infrastructure\WordPress\Admin\Helpers\AdminStatsService;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\UrlService;
use Notifal\Shared\Services\NotifalIconService;

if (!defined('ABSPATH')) exit;

// Initialize services using dependency injection
$adminStatsService = notifal_app(AdminStatsService::class);
$urlService        = notifal_app(UrlService::class);

// Get data for rendering
$notification_total = $adminStatsService->getTotalPosts('notifal_onpage_notif');
$status_tabs        = $adminStatsService->getStatusTabs('notifal_onpage_notif');
$add_new_url        = $urlService->getCreateNotificationUrl();

/**
 * Fires before rendering OnPage Notifications list admin page.
 *
 * Allows developers to add custom content or modify data before the notifications list is rendered.
 *
 * @since 2.0.0
 * @param int $notification_total Total number of notifications
 * @param array $status_tabs Status tab data for filtering
 * @param string $add_new_url URL for creating new notifications
 */
do_action(ActionHooks::ADMIN_ONPAGE_NOTIFICATIONS_BEFORE, $notification_total, $status_tabs, $add_new_url);
?>

<div class="wrap notifal-admin-page">
    <?php do_action(ActionHooks::ADMIN_PAGE_CONTENT_BEFORE); ?>
    <h1></h1>

    <div class="notification-list-parent notifal-mt-20">
    <?php
        $listView = new BaseListView([
            'title'              => __('OnPage Notifications', 'notifal'),
            'post_type'          => 'notifal_onpage_notif',
            'add_new_url'        => esc_url($add_new_url),
            'search_placeholder' => __('Search notifications...', 'notifal'),
            'columns'            => [
                'title'    => __('Title', 'notifal'),
                'enabled'  => __('Status', 'notifal'),
                'labels'   => __('Labels', 'notifal'),
                'date'     => __('Date', 'notifal'),
            ],
            'per_page'     => 15,
            'bulk_actions' => [
                'duplicate' => __('Duplicate', 'notifal'),
                'export' => __('Export', 'notifal'),
                'delete' => __('Move to Trash', 'notifal'),
            ],
            'status_tabs'  => $status_tabs,
            'bulk_actions_handled' => true, // Bulk actions handled by MenuController
        ]);

        // Add custom header actions hook for pre-created notifications modal button
        add_action('notifal_list_header_actions', function() {
            echo '<button type="button" class="notifal-button secondary notifal-flex notifal-gap-10" id="notifal-explore-precreated-btn">'
                . NotifalIconService::render('lightbulb', 20)
                . esc_html__('Explore Pre-created Notifications', 'notifal')
                . '</button>';
        });

        $listView->render();
        ?>

        <!-- Pre-created Notifications Archive Container -->
        <div id="notifal-precreated-archive-container" class="notifal-precreated-archive-container">
            <?php include_once __DIR__ . '/components/precreated-notifications-archive.php'; ?>
        </div>
    </div>
</div>

<!-- Pre-created Notifications Modal -->
<?php include_once __DIR__ . '/components/precreated-notifications-modal.php'; ?>

<!-- Import Modal -->
<div class="notifal-modal-backdrop" id="notifal-import-modal">
    <div class="notifal-modal">
        <div class="notifal-modal-header">
            <h2><?php esc_html_e('Import OnPage Notifications', 'notifal'); ?></h2>
            <button type="button" class="notifal-modal-close" aria-label="<?php esc_attr_e('Close', 'notifal'); ?>"><span class="notifal-icon notifal-icon-x-circle size-16"></span></button>
        </div>

        <div class="notifal-modal-body">
            <p class="notifal-text-center notifal-text-muted">
                <?php esc_html_e('Upload a JSON or ZIP file to import notifications. Make sure the file was exported from a trusted source.', 'notifal'); ?>
            </p>

            <form id="notifal-import-form" enctype="multipart/form-data" class="notifal-mt-20">
                <?php
                $import_nonce = wp_create_nonce('notifal_import_onpage_notification_ajax_nonce');
                wp_nonce_field('notifal_import_onpage_notification_ajax_nonce', '_wpnonce', false);
                ?>
                <input type="hidden" name="action" value="notifal_import_onpage_notification_ajax">

                <div class="notifal-form-group">
                    <div class="notifal-file-upload-area" id="notifal-file-upload-area">
                        <div class="notifal-file-upload-icon">
                            <?php echo NotifalIconService::render('cloud-arrow-up', 48); ?>
                        </div>
                        <div class="notifal-file-upload-text">
                            <span class="notifal-file-upload-title"><?php esc_html_e('Drop your file here', 'notifal'); ?></span>
                            <span class="notifal-file-upload-subtitle"><?php esc_html_e('or click to browse', 'notifal'); ?></span>
                        </div>
                        <div class="notifal-file-upload-formats">
                            <?php esc_html_e('Supported formats: JSON, ZIP (max 10MB)', 'notifal'); ?>
                        </div>
                        <input type="file"
                               id="notifal_import_file"
                               name="notifal_import_file"
                               accept=".json,.zip"
                               class="notifal-file-input"
                               required>
                    </div>

                    <div class="notifal-file-info notifal-hidden" id="notifal-file-info">
                        <div class="notifal-file-info-content">
                            <span class="notifal-file-info-icon">
                                <?php echo NotifalIconService::render('file-earmark1', 20); ?>
                            </span>
                            <span class="notifal-file-info-name" id="notifal-file-name"></span>
                            <button type="button" class="notifal-file-remove" id="notifal-file-remove" aria-label="Remove file">
                                <?php echo NotifalIconService::render('x-circle', 16); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div id="notifal-import-progress" class="notifal-progress notifal-hidden">
                    <div class="notifal-progress-bar">
                        <div class="notifal-progress-fill"></div>
                    </div>
                    <p class="notifal-progress-text"><?php esc_html_e('Importing...', 'notifal'); ?></p>
                </div>

                <div class="notifal-text-center notifal-text-sm notifal-text-muted notifal-mt-20">
                    <p><?php esc_html_e('Note: Imported notifications will be created as drafts. You can review and publish them after import.', 'notifal'); ?></p>
                </div>
            </form>
        </div>

        <div class="notifal-modal-footer">
            <button type="submit" class="notifal-button notifal-full-width" id="notifal-import-submit" form="notifal-import-form">
                <?php esc_html_e('Import OnPage Notifications', 'notifal'); ?>
            </button>
        </div>
    </div>
</div>

<?php
/**
 * Fires after rendering OnPage Notifications list admin page.
 *
 * Allows developers to add custom content or perform cleanup after the notifications list is rendered.
 *
 * @since 2.0.0
 * @param int $notification_total Total number of notifications
 * @param array $status_tabs Status tab data for filtering
 * @param string $add_new_url URL for creating new notifications
 */
do_action(ActionHooks::ADMIN_ONPAGE_NOTIFICATIONS_AFTER, $notification_total, $status_tabs, $add_new_url);
?>

<?php
// Render global toast container for dynamic notifications
ToastRenderer::renderGlobalContainer();
?> 
