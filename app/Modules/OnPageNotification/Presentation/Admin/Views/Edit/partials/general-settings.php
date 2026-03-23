<?php

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\OnPageNotification\Contracts\LabelProviderInterface;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\GeneralSettingsService;
use Notifal\Shared\AdminUI\Fields\FieldRenderer;
use Notifal\Modules\Campaign\Infrastructure\WordPress\Repositories\CampaignQuery;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * General Settings Tab
 *
 * Handles the display and configuration of notification general settings
 * including enable/disable status, title, labels, and content source type.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Views\Edit\partials
 */

/**
 * Initialize general settings service and retrieve merged settings
 * Merges default settings with saved notification data if in edit mode
 */
$generalService = notifal_app(GeneralSettingsService::class);

// Retrieve default general settings from service
$general_settings = $generalService->getDefaultSettings();

// Merge with saved notification settings if editing an existing notification
if ($is_edit && isset($notification_data) && is_array($notification_data)) {
    $general_settings = array_merge($general_settings, array_intersect_key($notification_data, $general_settings));
}

// Define current tab identifier for hooks and styling
$tab = 'general';

do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_BEFORE, $tab));
?>

<div class="notifal-settings-section notifal-<?php echo esc_attr( $tab ); ?>-settings">

    <h1><?php esc_html_e( 'General Settings', 'notifal' ); ?></h1>

    <div class="notifal-tab-panel-fields notifal-mt-20">

        <!-- General Settings -->
        <div class="notifal-field-group">
            <h3><?php esc_html_e( 'General Settings', 'notifal' ); ?></h3>

            <?php
            // Enable/Disable Notification
            FieldRenderer::toggle(
                'notif_enabled',
                $general_settings['notif_enabled'],
                __( 'Enable Notification', 'notifal' ),
                __( 'Activating the notification will change the status to published and notification will be live.', 'notifal' )
            );

            // Notification Title (internal)
            FieldRenderer::textInput(
                'notif_title',
                $general_settings['notif_title'],
                __( 'Notification Name (Internal)', 'notifal' ),
                __( 'Used to identify this notification within admin panel.', 'notifal' ),
                [ "placeholder" => __( 'Add Notification Title', 'notifal' ) ]
            );

            // Notification Label Field (taxonomy)
            FieldRenderer::badgeSelector(
                'notifal_labels',
                notifal_app(LabelProviderInterface::class)->getOptions(),
                $general_settings['notifal_labels'],
                __( 'Notification Labels', 'notifal' ),
                __( 'Choose relevant tags to describe this notification. Click labels to assign them.', 'notifal' ),
                true
            );

            // Campaign selector (overrides notification schedule when assigned).
            $selectedCampaignId = $is_edit && isset( $notification_data['campaign_id'] ) ? absint( $notification_data['campaign_id'] ) : 0;
            $campaignOptions = CampaignQuery::getCampaignOptions();
            $options = [
                [
                    'value' => '0',
                    'label' => __( 'No Campaign', 'notifal' ),
                ],
            ];

            foreach ( $campaignOptions as $id => $title ) {
                $options[] = [
                    'value' => (string) (int) $id,
                    'label' => (string) $title,
                ];
            }

            FieldRenderer::select(
                'notifal_campaign_id',
                $options,
                (string) $selectedCampaignId,
                __( 'Campaign', 'notifal' ),
                __( 'Assign this notification to a campaign to override its schedule.', 'notifal' )
            );

            // Content Source Type
            FieldRenderer::select(
                'content_source_type',
                [
                    [ 'value' => 'dynamic', 'label' => __( 'Dynamic Content', 'notifal' ) ],
                    [ 'value' => 'static', 'label' => __( 'Static Content', 'notifal' ) ],
                ],
                $general_settings['content_source_type'],
                __( 'Content Source Type', 'notifal' ),
                __( 'Dynamic: Uses real data from your store. Static: Uses fixed values for consistent messaging.', 'notifal' )
            );
            ?>
        </div>

    </div>

</div>

<?php
do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_AFTER, $tab));
?>
