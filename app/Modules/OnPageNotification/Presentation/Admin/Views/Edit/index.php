<?php


use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\UrlService;
use Notifal\Modules\OnPageNotification\Application\Support\ScheduleDateTimeHelper;
use Notifal\Shared\Services\NotifalIconService;
use Notifal\Shared\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin UI: Add/Edit On-Page Notification
 *
 * Main entry point for editing or creating on-page notifications in the admin panel.
 * Provides a tabbed interface with settings for General, Template, Display Rules,
 * Content Source, Appearance, Timing, and Behavior configurations.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Views\Edit
 */

// Enqueue required WordPress admin scripts for enhanced functionality
wp_enqueue_script('common');
wp_enqueue_script('wp-lists');
wp_enqueue_script('postbox');

// Initialize core variables with lazy loading for performance
$notification_data = null;
$is_edit = false;
$notification_id = 0;

// Initialize URL service for navigation
$urlService = notifal_app( UrlService::class );

do_action( ActionHooks::ADMIN_ONPAGE_NOTIFICATIONS_BEFORE );

// Lazy load notification data only when editing to improve performance
$get_action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
$get_id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
if ( $get_action === 'edit' && $get_id > 0 ) {
    $notification_id = $get_id;

    if ( $notification_id > 0 ) {
        try {
            $save_service = notifal_app( \Notifal\Modules\OnPageNotification\Application\Services\Core\NotificationSaveService::class );
            $notification_data = $save_service->getNotificationData( $notification_id );

            if ( $notification_data && is_array( $notification_data ) ) {
                $is_edit = true;
                // Make notification data globally available for included templates
                $GLOBALS['notifal_notification_data'] = $notification_data;
            }
        } catch ( \Exception $e ) {
            Helper::log(
                'Failed to load notification data for ID ' . $notification_id . ': ' . $e->getMessage()
            );
            $notification_data = null;
        }
    }
}

$notifal_timing_settings_hidden_json = '{}';
if ( $is_edit && isset( $notification_data['timing_settings'] ) && is_array( $notification_data['timing_settings'] ) ) {
    $notifal_timing_settings_hidden_json = wp_json_encode(
        ScheduleDateTimeHelper::withScheduleBoundariesForAdminDatetimeInputs( $notification_data['timing_settings'] )
    );
}

?>

<div class="wrap notifal-admin-page">
    <?php do_action(ActionHooks::ADMIN_PAGE_CONTENT_BEFORE); ?>
    <h1></h1>

    <div class="notification-edit-parent notifal-mt-20">

        <!-- Page Header with Title and Actions -->
        <div class="notifal-list-header">
            <h1 class="notifal-page-title">
                <?php echo esc_html( $is_edit ? __( 'Edit On-Page Notification', 'notifal' ) : __( 'Add New On-Page Notification', 'notifal' ) ); ?>
            </h1>

            <?php if ( $is_edit ): ?>
                <a href="#"
                   id="notifal-edit-page-add-new-btn"
                   class="notifal-button notifal-flex notifal-gap-10 notifal-align-center">
                    <?php echo NotifalIconService::render('plus-circle', 20); ?>
                    <?php esc_html_e( 'Add New On-Page Notification', 'notifal' ); ?>
                </a>
            <?php endif; ?>
        </div>

        <form method="post" class="notifal-notification-form">
            <?php wp_nonce_field( 'notifal_save_notification', 'notifal_save_nonce' ); ?>

            <?php if ( $is_edit && $notification_id ): ?>
                <input type="hidden" name="notification_id" value="<?php echo esc_attr( $notification_id ); ?>">
            <?php endif; ?>

        <!-- Hidden fields for settings data -->
        <input type="hidden" name="appearance_settings" value="<?php echo esc_attr( $is_edit && isset( $notification_data['appearance_settings'] ) ? wp_json_encode( $notification_data['appearance_settings'] ) : '{}' ); ?>">
        <input type="hidden" name="behavior_settings" value="<?php echo esc_attr( $is_edit && isset( $notification_data['behavior_settings'] ) ? wp_json_encode( $notification_data['behavior_settings'] ) : '{}' ); ?>">
        <input type="hidden" name="timing_settings" value="<?php echo esc_attr( $notifal_timing_settings_hidden_json ); ?>">
        <input type="hidden" name="content_source_settings" value="<?php echo esc_attr( $is_edit && isset( $notification_data['content_source_settings'] ) ? wp_json_encode( $notification_data['content_source_settings'] ) : '{}' ); ?>">
        <input type="hidden" name="display_rules_data" value="<?php echo esc_attr( $is_edit && isset( $notification_data['display_rules_data'] ) ? wp_json_encode( $notification_data['display_rules_data'] ) : '{}' ); ?>">
        <input type="hidden" name="rule_combination_logic" value="<?php echo esc_attr( $is_edit && isset( $notification_data['rule_combination_logic'] ) ? $notification_data['rule_combination_logic'] : 'OR' ); ?>">
        <input type="hidden" name="template_id" value="<?php echo esc_attr( $is_edit && isset( $notification_data['template_id'] ) ? $notification_data['template_id'] : '0' ); ?>">
        <input type="hidden" name="template_content" value="<?php echo esc_attr( $is_edit && isset( $notification_data['template_content'] ) ? $notification_data['template_content'] : '' ); ?>">

            <div class="notifal-flex notifal-gap-20 notifal-mt-20">

                <!-- Sidebar Navigation -->
            <aside class="notifal-card notifal-sidebar">
                <!-- Tab Navigation Buttons -->
                <div class="notifal-tabs notifal-flex notifal-flex-column notifal-gap-10">
                    <button type="button" class="notifal-button secondary" data-tab="general"><?php esc_html_e( 'General', 'notifal' ); ?></button>
                    <button type="button" class="notifal-button secondary" data-tab="template"><?php esc_html_e( 'Template', 'notifal' ); ?></button>
                    <button type="button" class="notifal-button secondary" data-tab="display-rules"><?php esc_html_e( 'Display Rules', 'notifal' ); ?></button>
                    <button type="button" class="notifal-button secondary" data-tab="content-source"><?php esc_html_e( 'Content Source', 'notifal' ); ?></button>
                    <button type="button" class="notifal-button secondary" data-tab="appearance"><?php esc_html_e( 'Appearance', 'notifal' ); ?></button>
                    <button type="button" class="notifal-button secondary" data-tab="timing"><?php esc_html_e( 'Timing', 'notifal' ); ?></button>
                    <button type="button" class="notifal-button secondary" data-tab="behavior"><?php esc_html_e( 'Behavior', 'notifal' ); ?></button>
                </div>

                <!-- Current Status Display -->
                <div class="notifal-status-display notifal-mt-20 notifal-justify-center notifal-flex">
                    <?php if ( $is_edit && isset( $notification_data['notif_enabled'] ) ): ?>
                        <div class="notifal-status-card <?php echo $notification_data['notif_enabled'] ? 'published' : 'draft'; ?>">
                            <div class="notifal-status-icon">
                                <?php echo $notification_data['notif_enabled'] ? '🟢' : '📝'; ?>
                            </div>
                            <div class="notifal-status-title">
                                <?php echo $notification_data['notif_enabled'] ? esc_html__( 'Live & Active', 'notifal' ) : esc_html__( 'Draft Mode', 'notifal' ); ?>
                            </div>
                            <div class="notifal-status-subtitle">
                                <?php echo $notification_data['notif_enabled'] ? esc_html__( 'Notification is published and visible to visitors', 'notifal' ) : esc_html__( 'Notification is saved but not visible to visitors. ', 'notifal' ); ?>
                                <?php if ( !$notification_data['notif_enabled'] ): ?>
                                    <?php
                                    /* translators: %s: link HTML for "Enable Notification" button */
                                    echo wp_kses_post(
                                        sprintf(
                                            __( 'If you want to make it live, turn %s on.', 'notifal' ),
                                            '<a href="#" class="notifal-enable-link" data-tab="general">' . esc_html__( 'Enable Notification', 'notifal' ) . '</a>'
                                        )
                                    );
                                    ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ( $is_edit && $notification_id ) : ?>
                <!-- Preview Button -->
                <div class="notifal-mt-20 notifal-justify-center notifal-flex">
                    <a href="<?php echo esc_url( $urlService->getPreviewUrl( $notification_id ) ); ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="notifal-button secondary notifal-flex notifal-gap-10 notifal-align-center">
                       <?php esc_html_e( 'Preview', 'notifal' ); ?>
                    </a>
                </div>
                <?php endif; ?>
                <!-- Primary Action Button -->
                <div class="notifal-justify-center notifal-flex">
                    <button type="button" class="notifal-button notifal-save-notification-btn">
                        <?php esc_html_e( 'Save Notification', 'notifal' ); ?>
                    </button>
                </div>
            </aside>

            <!-- Main Content Area with Tab Panels -->
            <section class="notifal-card notifal-content notifal-full-width notifal-flex notifal-flex-column notifal-gap-20">
                <div class="notifal-tab-panels">

                    <!-- General Settings Tab -->
                    <div id="tab-general" class="notifal-tab notifal-tab-panel">
                        <?php require_once __DIR__ . '/partials/general-settings.php'; ?>
                    </div>

                    <!-- Template Selection Tab -->
                    <div id="tab-template" class="notifal-tab notifal-tab-panel">
                        <?php require_once __DIR__ . '/partials/template-settings.php'; ?>
                    </div>

                    <!-- Appearance Settings Tab -->
                    <div id="tab-appearance" class="notifal-tab notifal-tab-panel">
                        <?php require_once __DIR__ . '/partials/appearance-settings.php'; ?>
                    </div>

                    <!-- Display Rules Tab -->
                    <div id="tab-display-rules" class="notifal-tab notifal-tab-panel">
                        <?php require_once __DIR__ . '/partials/display-rules-settings.php'; ?>
                    </div>

                    <!-- Content Source Tab -->
                    <div id="tab-content-source" class="notifal-tab notifal-tab-panel">
                        <?php require_once __DIR__ . '/partials/content-source-settings.php'; ?>
                    </div>

                    <!-- Timing Configuration Tab -->
                    <div id="tab-timing" class="notifal-tab notifal-tab-panel">
                        <?php require_once __DIR__ . '/partials/timing-settings.php'; ?>
                    </div>

                    <!-- Behavior Options Tab -->
                    <div id="tab-behavior" class="notifal-tab notifal-tab-panel">
                        <?php require_once __DIR__ . '/partials/behavior-settings.php'; ?>
                    </div>

                </div>
            </section>

            </div>

        </form>

    </div>

</div>

<!-- Pre-created Notifications Modal -->
<?php include_once __DIR__ . '/../components/precreated-notifications-modal.php'; ?>

<?php do_action( ActionHooks::ADMIN_ONPAGE_NOTIFICATIONS_AFTER ); ?>
