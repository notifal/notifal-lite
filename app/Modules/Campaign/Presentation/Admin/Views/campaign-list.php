<?php

/**
 * Campaigns List Admin Page
 *
 * Renders the admin list page for notifal_campaign posts with search, pagination,
 * and bulk actions using BaseListView.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Admin\Helpers\AdminStatsService;
use Notifal\Shared\AdminUI\Lists\BaseListView;
use Notifal\Shared\AdminUI\Toast\ToastRenderer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Initialize services using dependency injection.
$adminStatsService = notifal_app( AdminStatsService::class );

// Get data for rendering.
$campaign_total = $adminStatsService->getTotalPosts( 'notifal_campaign' );
$status_tabs = $adminStatsService->getStatusTabs( 'notifal_campaign' );
$add_new_url = admin_url( 'admin.php?page=notifal-campaign' );
?>

<div class="wrap notifal-admin-page">
    <?php do_action( ActionHooks::ADMIN_PAGE_CONTENT_BEFORE ); ?>
    <h1></h1>

    <div class="campaign-list-parent notifal-mt-20">
        <?php
        $listView = new BaseListView([
            'title'              => __( 'Campaigns', 'notifal' ),
            'post_type'          => 'notifal_campaign',
            'add_new_url'        => esc_url( $add_new_url ),
            'search_placeholder' => __( 'Search campaigns...', 'notifal' ),
            'columns'            => [
                'title'         => __( 'Title', 'notifal' ),
                'status'        => __( 'Status', 'notifal' ),
                'notifications' => __( 'Notifications', 'notifal' ),
                'schedule'      => __( 'Schedule', 'notifal' ),
                'date'          => __( 'Date', 'notifal' ),
            ],
            'per_page' => 15,
            'bulk_actions' => [
                'duplicate' => __( 'Duplicate', 'notifal' ),
                'delete'     => __( 'Move to Trash', 'notifal' ),
            ],
            'status_tabs' => $status_tabs,
            'bulk_actions_handled' => true,
        ]);

        $listView->render();
        ?>
    </div>
</div>

<?php
ToastRenderer::renderGlobalContainer();
?>

