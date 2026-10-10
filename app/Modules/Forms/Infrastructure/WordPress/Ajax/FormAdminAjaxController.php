<?php

namespace Notifal\Modules\Forms\Infrastructure\WordPress\Ajax;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\Forms\Application\Services\FormExportService;
use Notifal\Modules\Forms\Application\Services\FormFieldSanitizer;
use Notifal\Modules\Forms\Application\Services\FormFieldSyncService;
use Notifal\Modules\Forms\Application\Services\FormLicenseGuard;
use Notifal\Modules\Forms\Application\Services\FormService;
use Notifal\Modules\Forms\Application\Services\HtmlSchemaParser;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormFieldsRepository;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormMetaRepository;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\SubmissionRepository;
use Notifal\Shared\AdminUI\Toast\ToastManager;

defined('ABSPATH') || exit;

/**
 * Admin AJAX for Forms CPT save, submission delete, and CSV export.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormAdminAjaxController
{
    /**
     * Register admin AJAX actions.
     *
     * @return void
     * @since 3.0.0
     */
    public static function register(): void
    {
        add_action('wp_ajax_notifal_save_form', [self::class, 'saveForm']);
        add_action('wp_ajax_notifal_sync_form_fields', [self::class, 'syncFormFields']);
        add_action('wp_ajax_notifal_delete_form_submission', [self::class, 'deleteSubmission']);
        add_action('wp_ajax_notifal_get_form_submission', [self::class, 'getSubmission']);
        add_action('wp_ajax_notifal_mark_form_submission_read', [self::class, 'markSubmissionRead']);
        add_action('wp_ajax_notifal_save_form_submission_note', [self::class, 'saveSubmissionNote']);
        add_action('wp_ajax_notifal_export_form_submissions', [self::class, 'exportSubmissions']);
        add_action('admin_post_notifal_export_form_submissions', [self::class, 'exportSubmissionsPost']);
        add_action('admin_post_notifal_save_form_submission_note', [self::class, 'saveSubmissionNotePost']);
    }

    /**
     * Build shared Form service dependencies.
     *
     * @return FormService
     * @since 3.0.0
     */
    private static function formService(): FormService
    {
        $sanitizer = new FormFieldSanitizer();
        $fields = new FormFieldsRepository($sanitizer);

        return new FormService(new FormMetaRepository(), $fields);
    }

    /**
     * Build field sync service.
     *
     * @return FormFieldSyncService
     * @since 3.0.0
     */
    private static function fieldSyncService(): FormFieldSyncService
    {
        $sanitizer = new FormFieldSanitizer();

        return new FormFieldSyncService(
            new FormFieldsRepository($sanitizer),
            new HtmlSchemaParser(),
            new FormMetaRepository()
        );
    }

    /**
     * Save Form CPT + secrets meta.
     *
     * @return void
     * @since 3.0.0
     */
    public static function saveForm(): void
    {
        if (!notifal_verify_ajax_request('notifal_form_save', 'manage_options')) {
            return;
        }

        try {
            $service = self::formService();
            $formId = $service->save($_POST);
            $fieldsRepo = new FormFieldsRepository(new FormFieldSanitizer());
            $metaRepo = new FormMetaRepository();

            wp_send_json_success([
                'form_id'      => $formId,
                'title'        => get_the_title($formId),
                'can_create'   => FormLicenseGuard::canCreate(),
                'fields'       => $fieldsRepo->get($formId),
                'presentation' => $metaRepo->getPresentation($formId),
                'message'      => __('Form saved.', 'notifal'),
                'edit_url'     => admin_url('admin.php?page=notifal-form&form_id=' . $formId),
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sync field definitions from HTML Builder markup or JSON payload.
     *
     * @return void
     * @since 3.0.0
     */
    public static function syncFormFields(): void
    {
        if (!notifal_verify_ajax_request('notifal_form_save', 'manage_options')) {
            return;
        }

        $formId = isset($_POST['form_id']) ? absint(wp_unslash($_POST['form_id'])) : 0;
        if ($formId <= 0 || get_post_type($formId) !== 'notifal_form') {
            wp_send_json_error(['message' => __('Invalid form.', 'notifal')]);
        }

        try {
            $sync = self::fieldSyncService();
            $html = isset($_POST['form_html']) ? (string) wp_unslash($_POST['form_html']) : '';

            if ($html !== '') {
                // Builder connect sends sync_presentation=0 so CPT settings are not overwritten.
                $syncPresentation = !isset($_POST['sync_presentation'])
                    || (string) wp_unslash($_POST['sync_presentation']) !== '0';
                $result = $sync->syncFromHtml(
                    $formId,
                    $html,
                    [
                        'sync_presentation' => $syncPresentation,
                    ]
                );
            } else {
                $raw = isset($_POST['form_fields']) ? wp_unslash($_POST['form_fields']) : '[]';
                $result = $sync->syncFromPayload($formId, $raw);
            }

            wp_send_json_success([
                'form_id'      => $formId,
                'fields'       => $result['fields'],
                'presentation' => $result['presentation'],
                'message'      => __('Form fields synced.', 'notifal'),
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete a single submission.
     *
     * @return void
     * @since 3.0.0
     */
    public static function deleteSubmission(): void
    {
        if (!notifal_verify_ajax_request('notifal_form_submissions', 'manage_options')) {
            return;
        }

        $id = isset($_POST['submission_id']) ? absint(wp_unslash($_POST['submission_id'])) : 0;
        if ($id <= 0) {
            wp_send_json_error(['message' => __('Invalid submission.', 'notifal')]);
        }

        $repo = new SubmissionRepository();
        $repo->delete($id);

        /**
         * Fires after a submission is deleted via AJAX.
         *
         * @since 3.0.0
         */
        do_action(ActionHooks::FORMS_SUBMISSION_DELETED, $id);

        wp_send_json_success(['message' => __('Submission deleted.', 'notifal')]);
    }

    /**
     * Return submission payload for the quick-view modal.
     *
     * @return void
     * @since 3.0.0
     */
    public static function getSubmission(): void
    {
        if (!notifal_verify_ajax_request('notifal_form_submissions', 'manage_options')) {
            return;
        }

        $id = isset($_POST['submission_id']) ? absint(wp_unslash($_POST['submission_id'])) : 0;
        $payload = self::buildSubmissionPayload($id);
        if ($payload === null) {
            wp_send_json_error(['message' => __('Submission not found.', 'notifal')]);
        }

        // Opening quick view marks new submissions as read.
        if (($payload['status'] ?? '') === 'new') {
            $repo = new SubmissionRepository();
            $repo->updateStatus($id, 'read');
            $payload['status'] = 'read';
            $payload['status_label'] = __('Read', 'notifal');
            do_action(ActionHooks::FORMS_SUBMISSION_STATUS_UPDATED, $id, 'read');
        }

        wp_send_json_success($payload);
    }

    /**
     * Mark one submission as read via AJAX.
     *
     * @return void
     * @since 3.0.0
     */
    public static function markSubmissionRead(): void
    {
        if (!notifal_verify_ajax_request('notifal_form_submissions', 'manage_options')) {
            return;
        }

        $id = isset($_POST['submission_id']) ? absint(wp_unslash($_POST['submission_id'])) : 0;
        if ($id <= 0) {
            wp_send_json_error(['message' => __('Invalid submission.', 'notifal')]);
        }

        $repo = new SubmissionRepository();
        if (!$repo->get($id)) {
            wp_send_json_error(['message' => __('Submission not found.', 'notifal')]);
        }

        $repo->updateStatus($id, 'read');
        do_action(ActionHooks::FORMS_SUBMISSION_STATUS_UPDATED, $id, 'read');

        wp_send_json_success([
            'message'      => __('Submission marked as read.', 'notifal'),
            'status'       => 'read',
            'status_label' => __('Read', 'notifal'),
        ]);
    }

    /**
     * Save an admin note via AJAX (list quick view).
     *
     * @return void
     * @since 3.0.0
     */
    public static function saveSubmissionNote(): void
    {
        if (!notifal_verify_ajax_request('notifal_form_submissions', 'manage_options')) {
            return;
        }

        $id = isset($_POST['submission_id']) ? absint(wp_unslash($_POST['submission_id'])) : 0;
        $note = isset($_POST['admin_note'])
            ? sanitize_textarea_field(wp_unslash((string) $_POST['admin_note']))
            : '';

        if ($id <= 0) {
            wp_send_json_error(['message' => __('Invalid submission.', 'notifal')]);
        }

        $repo = new SubmissionRepository();
        if (!$repo->get($id)) {
            wp_send_json_error(['message' => __('Submission not found.', 'notifal')]);
        }

        if (!$repo->updateAdminNote($id, $note)) {
            wp_send_json_error(['message' => __('Failed to save note.', 'notifal')]);
        }

        do_action(ActionHooks::FORMS_SUBMISSION_NOTE_UPDATED, $id, $note);

        wp_send_json_success([
            'message'    => __('Admin note saved.', 'notifal'),
            'admin_note' => $note,
        ]);
    }

    /**
     * Save an admin note from the detail screen (POST redirect).
     *
     * @return void
     * @since 3.0.0
     */
    public static function saveSubmissionNotePost(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to save notes.', 'notifal'));
        }

        $nonce = isset($_POST['_wpnonce'])
            ? sanitize_text_field(wp_unslash((string) $_POST['_wpnonce']))
            : '';
        if (!wp_verify_nonce($nonce, 'notifal_save_submission_note')) {
            wp_die(esc_html__('Security check failed.', 'notifal'));
        }

        $id = isset($_POST['submission_id']) ? absint(wp_unslash($_POST['submission_id'])) : 0;
        $note = isset($_POST['admin_note'])
            ? sanitize_textarea_field(wp_unslash((string) $_POST['admin_note']))
            : '';

        $redirect = add_query_arg(
            [
                'page'          => 'notifal-form-submissions',
                'submission_id' => max(1, $id),
            ],
            admin_url('admin.php')
        );

        if ($id <= 0) {
            wp_safe_redirect($redirect);
            exit;
        }

        $repo = new SubmissionRepository();
        if ($repo->get($id) && $repo->updateAdminNote($id, $note)) {
            do_action(ActionHooks::FORMS_SUBMISSION_NOTE_UPDATED, $id, $note);
            ToastManager::success(__('Admin note saved.', 'notifal'), $redirect);
            return;
        }

        ToastManager::error(__('Failed to save note.', 'notifal'), $redirect);
    }

    /**
     * Build a sanitized submission payload for admin UI.
     *
     * @param int $id Submission ID.
     * @return array<string, mixed>|null
     * @since 3.0.0
     */
    private static function buildSubmissionPayload(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $repo = new SubmissionRepository();
        $row = $repo->get($id);
        if (!$row) {
            return null;
        }

        $formId = isset($row['form_id']) ? absint($row['form_id']) : 0;
        $notificationId = isset($row['notification_id']) ? absint($row['notification_id']) : 0;
        $status = isset($row['status']) ? sanitize_key((string) $row['status']) : 'new';
        $createdAt = isset($row['created_at']) ? (string) $row['created_at'] : '';
        // Treat created_at as WP local mysql time (matches current_time on insert).
        $createdDisplay = $createdAt !== ''
            ? (string) mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $createdAt)
            : '';
        $formTitle = $formId > 0 ? get_the_title($formId) : '';
        if ($formTitle === '') {
            $formTitle = $formId > 0
                ? sprintf(/* translators: %d: form ID */ __('Form #%d', 'notifal'), $formId)
                : __('Unknown form', 'notifal');
        }

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

        return [
            'id'                 => $id,
            'form_id'            => $formId,
            'form_title'         => $formTitle,
            'notification_id'    => $notificationId,
            'notification_title' => $notificationTitle,
            'notification_url'   => $notificationUrl,
            'status'             => $status,
            'status_label'       => $status === 'read' ? __('Read', 'notifal') : __('New', 'notifal'),
            'admin_note'         => isset($row['admin_note']) ? (string) $row['admin_note'] : '',
            'page_url'           => isset($row['page_url']) ? (string) $row['page_url'] : '',
            'created_at'         => $createdAt,
            'created_display'    => $createdDisplay,
            'fields'             => $repo->getFields($id),
            'detail_url'         => add_query_arg(
                [
                    'page'          => 'notifal-form-submissions',
                    'submission_id' => $id,
                ],
                admin_url('admin.php')
            ),
        ];
    }

    /**
     * AJAX CSV export entry (redirects to admin-post for streaming).
     *
     * @return void
     * @since 3.0.0
     */
    public static function exportSubmissions(): void
    {
        if (!notifal_verify_ajax_request('notifal_form_submissions', 'manage_options')) {
            return;
        }

        $url = wp_nonce_url(
            admin_url('admin-post.php?action=notifal_export_form_submissions'),
            'notifal_form_submissions'
        );

        $formId = isset($_POST['form_id']) ? absint(wp_unslash($_POST['form_id'])) : 0;
        if ($formId > 0) {
            $url = add_query_arg('form_id', $formId, $url);
        }

        wp_send_json_success(['url' => $url]);
    }

    /**
     * Stream CSV via admin-post (capability + nonce).
     *
     * @return void
     * @since 3.0.0
     */
    public static function exportSubmissionsPost(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to export submissions.', 'notifal'));
        }

        $nonce = isset($_GET['_wpnonce'])
            ? sanitize_text_field(wp_unslash((string) $_GET['_wpnonce']))
            : '';
        if (!wp_verify_nonce($nonce, 'notifal_form_submissions')) {
            wp_die(esc_html__('Security check failed.', 'notifal'));
        }

        $filters = [
            'form_id'   => isset($_GET['form_id']) ? absint(wp_unslash($_GET['form_id'])) : 0,
            'search'    => isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '',
            'date_from' => isset($_GET['date_from'])
                ? sanitize_text_field(wp_unslash((string) $_GET['date_from']))
                : '',
            'date_to'   => isset($_GET['date_to'])
                ? sanitize_text_field(wp_unslash((string) $_GET['date_to']))
                : '',
            'status'    => isset($_GET['status'])
                ? sanitize_key(wp_unslash((string) $_GET['status']))
                : 'all',
        ];

        $exporter = new FormExportService(new SubmissionRepository());
        $exporter->streamCsv($filters);
    }
}
