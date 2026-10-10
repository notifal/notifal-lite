<?php

/**
 * Form submissions list admin page.
 * 
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\Forms\Presentation\Admin\Lists\SubmissionListView;
use Notifal\Shared\AdminUI\Toast\ToastRenderer;

defined('ABSPATH') || exit;

// Load forms for the filter dropdown (titles only, capped for performance).
$forms = get_posts([
    'post_type'      => 'notifal_form',
    'post_status'    => ['publish', 'draft'],
    'posts_per_page' => 100,
    'orderby'        => 'title',
    'order'          => 'ASC',
]);
?>

<div class="wrap notifal-admin-page">
    <?php do_action(ActionHooks::ADMIN_PAGE_CONTENT_BEFORE); ?>
    <h1></h1>

    <div class="notifal-form-submissions-list-parent notifal-mt-20">
        <?php
        // Render the shared-style list (header, tabs, search, table, pagination).
        $listView = new SubmissionListView([
            'title'                => __('Form Submissions', 'notifal'),
            'search_placeholder'   => __('Search fields and notes...', 'notifal'),
            'per_page'             => 20,
            'forms'                => is_array($forms) ? $forms : [],
            'bulk_actions_handled' => true,
        ]);
        $listView->render();
        ?>
    </div>
</div>

<?php
ToastRenderer::renderGlobalContainer();
?>
