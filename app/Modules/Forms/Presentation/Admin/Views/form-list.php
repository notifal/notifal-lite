<?php

/**
 * Forms list admin page.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Admin\Helpers\AdminStatsService;
use Notifal\Modules\Forms\Application\Services\FormLicenseGuard;
use Notifal\Shared\AdminUI\Lists\BaseListView;
use Notifal\Shared\AdminUI\Toast\ToastRenderer;

defined('ABSPATH') || exit;

$adminStatsService = notifal_app(AdminStatsService::class);
$form_total = $adminStatsService->getTotalPosts('notifal_form');
$status_tabs = $adminStatsService->getStatusTabs('notifal_form');
$can_create = FormLicenseGuard::canCreate();
$add_new_url = $can_create
    ? admin_url('admin.php?page=notifal-form')
    : '';
?>

<div class="wrap notifal-admin-page">
    <?php do_action(ActionHooks::ADMIN_PAGE_CONTENT_BEFORE); ?>
    <h1></h1>

    <div class="notifal-forms-list-parent notifal-mt-20">
        <?php
        $listView = new BaseListView([
            'title'              => __('Forms', 'notifal'),
            'post_type'          => 'notifal_form',
            'add_new_url'        => $add_new_url !== '' ? esc_url($add_new_url) : '',
            'search_placeholder' => __('Search forms...', 'notifal'),
            'columns'            => [
                'title' => __('Title', 'notifal'),
                'inbox' => __('Inbox', 'notifal'),
                'date'  => __('Date', 'notifal'),
            ],
            'per_page' => 15,
            'bulk_actions' => [
                'delete' => __('Move to Trash', 'notifal'),
            ],
            'status_tabs' => $status_tabs,
            'bulk_actions_handled' => true,
        ]);

        $listView->render();
        ?>
        <?php unset($form_total); ?>
    </div>
</div>

<?php
ToastRenderer::renderGlobalContainer();
?>
