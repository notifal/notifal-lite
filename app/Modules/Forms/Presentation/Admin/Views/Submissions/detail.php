<?php

/**
 * Single form submission detail screen.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\SubmissionRepository;
use Notifal\Shared\AdminUI\Toast\ToastRenderer;
use Notifal\Shared\Services\NotifalIconService;

defined('ABSPATH') || exit;

// Resolve the submission ID from the query string.
$submissionId = isset($_GET['submission_id']) ? absint(wp_unslash($_GET['submission_id'])) : 0;
$repo = new SubmissionRepository();
$submission = $repo->get($submissionId);

// Hard stop when the row does not exist.
if (!$submission) {
    wp_die(esc_html__('Submission not found.', 'notifal'));
}

// Mark unread submissions as read when an admin opens the detail screen.
$status = isset($submission['status']) ? sanitize_key((string) $submission['status']) : 'new';
if ($status === 'new') {
    $repo->updateStatus($submissionId, 'read');
    $status = 'read';
    do_action(ActionHooks::FORMS_SUBMISSION_STATUS_UPDATED, $submissionId, 'read');
}

// Load field values and related display data.
$fields = $repo->getFields($submissionId);
$formId = isset($submission['form_id']) ? absint($submission['form_id']) : 0;
$formTitle = $formId > 0 ? get_the_title($formId) : '';
if ($formTitle === '') {
    $formTitle = $formId > 0
        ? sprintf(/* translators: %d: form ID */ __('Form #%d', 'notifal'), $formId)
        : __('Unknown form', 'notifal');
}

$backArgs = ['page' => 'notifal-form-submissions'];
if ($formId > 0) {
    $backArgs['form_id'] = $formId;
}
$backUrl = add_query_arg($backArgs, admin_url('admin.php'));

$deleteUrl = add_query_arg(
    [
        'action'   => 'notifal_delete_form_submission',
        'id'       => $submissionId,
        '_wpnonce' => wp_create_nonce('delete_form_submission_' . $submissionId),
    ],
    admin_url('admin.php')
);

$statusLabel = $status === 'read' ? __('Read', 'notifal') : __('New', 'notifal');
$createdAt = isset($submission['created_at']) ? (string) $submission['created_at'] : '';
$pageUrl = isset($submission['page_url']) ? (string) $submission['page_url'] : '';
$adminNote = isset($submission['admin_note']) ? (string) $submission['admin_note'] : '';
$notificationId = isset($submission['notification_id']) ? absint($submission['notification_id']) : 0;
// Treat created_at as WP local mysql time (same as insert via current_time).
$createdDisplay = $createdAt !== ''
    ? (string) mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $createdAt)
    : '';

// Notification ID is 0 for standalone embeds; only OnPage context stores a real ID.
$notificationTitle = '';
$notificationUrl = '';
if ($notificationId > 0) {
    $notificationTitle = get_the_title($notificationId);
    if ($notificationTitle === '') {
        $notificationTitle = sprintf(
            /* translators: %d: notification ID */
            __('Notification #%d', 'notifal'),
            $notificationId
        );
    }
    $notificationUrl = add_query_arg(
        [
            'page'   => 'notifal-onpage-notification',
            'post'   => $notificationId,
            'action' => 'edit',
        ],
        admin_url('admin.php')
    );
}

$noteAction = admin_url('admin-post.php');
?>

<div class="wrap notifal-admin-page">
    <?php do_action(ActionHooks::ADMIN_PAGE_CONTENT_BEFORE); ?>
    <h1></h1>

    <div class="notifal-admin-list notifal-form-submission-detail notifal-mt-20">
        <div class="notifal-list-header">
            <h1 class="notifal-page-title">
                <?php
                printf(
                    /* translators: %d: submission ID */
                    esc_html__('Submission #%d', 'notifal'),
                    (int) $submissionId
                );
                ?>
            </h1>
            <a href="<?php echo esc_url($backUrl); ?>" class="notifal-button secondary notifal-flex notifal-gap-10">
                <?php
                // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- NotifalIconService::render() escapes icon markup via esc_attr().
                echo NotifalIconService::render('arrow-left-short', 20);
                // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
                echo esc_html__('Back to submissions', 'notifal');
                ?>
            </a>
            <a href="<?php echo esc_url($deleteUrl); ?>"
               class="notifal-button secondary delete-link notifal-flex notifal-gap-10"
               data-confirm-message="<?php
                echo esc_attr(
                    sprintf(
                        /* translators: %d: submission ID */
                        __('Are you sure you want to permanently delete submission #%d?', 'notifal'),
                        $submissionId
                    )
                );
                ?>">
                <?php
                // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- NotifalIconService::render() escapes icon markup via esc_attr().
                echo NotifalIconService::render('trash', 20);
                // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
                echo esc_html__('Delete', 'notifal');
                ?>
            </a>
        </div>

        <h2 class="notifal-form-submission-section-title"><?php echo esc_html__('Overview', 'notifal'); ?></h2>
        <table class="notifal-table" role="table">
            <tbody>
                <tr>
                    <th scope="row"><?php echo esc_html__('Form', 'notifal'); ?></th>
                    <td><?php echo esc_html($formTitle); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Status', 'notifal'); ?></th>
                    <td>
                        <span class="notifal-status-badge notifal-status-<?php echo esc_attr($status); ?>">
                            <?php echo esc_html($statusLabel); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Created', 'notifal'); ?></th>
                    <td><?php echo esc_html($createdDisplay); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Notification', 'notifal'); ?></th>
                    <td>
                        <?php if ($notificationId > 0) : ?>
                            <a href="<?php echo esc_url($notificationUrl); ?>">
                                <?php echo esc_html($notificationTitle); ?>
                            </a>
                            <span class="description">
                                <?php
                                printf(
                                    /* translators: %d: notification ID */
                                    esc_html__('(ID: %d)', 'notifal'),
                                    $notificationId
                                );
                                ?>
                            </span>
                        <?php else : ?>
                            <span><?php echo esc_html__('None', 'notifal'); ?></span>
                            <p class="description">
                                <?php
                                echo esc_html__(
                                    'This submission was not linked to an OnPage notification (for example a standalone form embed). The notification ID is stored only when the form is submitted from a notification.',
                                    'notifal'
                                );
                                ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Page URL', 'notifal'); ?></th>
                    <td>
                        <?php if ($pageUrl !== '') : ?>
                            <a href="<?php echo esc_url($pageUrl); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo esc_html($pageUrl); ?>
                            </a>
                        <?php else : ?>
                            &mdash;
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <p class="description notifal-mt-20">
            <?php echo esc_html__('Visitor IP is stored as a hash only and is not shown here.', 'notifal'); ?>
        </p>

        <h2 class="notifal-form-submission-section-title"><?php echo esc_html__('Submitted fields', 'notifal'); ?></h2>
        <table class="notifal-table" role="table">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html__('Field', 'notifal'); ?></th>
                    <th scope="col"><?php echo esc_html__('Value', 'notifal'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($fields === []) : ?>
                    <tr>
                        <td colspan="2" class="no-items">
                            <?php echo esc_html__('No field values found for this submission.', 'notifal'); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($fields as $name => $value) : ?>
                        <tr>
                            <td><?php echo esc_html($name); ?></td>
                            <td><?php echo esc_html($value); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <h2 class="notifal-form-submission-section-title"><?php echo esc_html__('Admin note', 'notifal'); ?></h2>
        <form method="post" action="<?php echo esc_url($noteAction); ?>" class="notifal-submission-note-form">
            <input type="hidden" name="action" value="notifal_save_form_submission_note" />
            <input type="hidden" name="submission_id" value="<?php echo esc_attr((string) $submissionId); ?>" />
            <?php wp_nonce_field('notifal_save_submission_note'); ?>
            <label class="screen-reader-text" for="notifal-submission-admin-note">
                <?php echo esc_html__('Admin note', 'notifal'); ?>
            </label>
            <textarea
                id="notifal-submission-admin-note"
                name="admin_note"
                class="notifal-setting-input notifal-submission-note-textarea"
                rows="5"
                placeholder="<?php echo esc_attr__('Add an internal note for this submission…', 'notifal'); ?>"
            ><?php echo esc_textarea($adminNote); ?></textarea>
            <div class="notifal-submission-note-actions">
                <button type="submit" class="notifal-button">
                    <?php echo esc_html__('Save note', 'notifal'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
ToastRenderer::renderGlobalContainer();
?>
