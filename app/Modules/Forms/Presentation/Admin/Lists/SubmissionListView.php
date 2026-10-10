<?php

namespace Notifal\Modules\Forms\Presentation\Admin\Lists;

use Notifal\Core\Support\Helpers\UrlHelper;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\SubmissionRepository;
use Notifal\Shared\AdminUI\Toast\ToastManager;
use Notifal\Shared\Services\NotifalIconService;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Form submissions list UI matching BaseListView markup and Notifal admin patterns.
 *
 * Submissions live in custom tables, so this renderer mirrors BaseListView
 * structure (header, status tabs, search, bulk actions, table, pagination)
 * without using WP_Query / post types.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SubmissionListView
{
    /**
     * Admin page slug for this screen.
     *
     * @var string
     */
    private const PAGE_SLUG = 'notifal-form-submissions';

    /**
     * Submission repository.
     *
     * @var SubmissionRepository
     */
    private $repository;

    /**
     * Column key => label map.
     *
     * @var array<string, string>
     */
    private $columns = [];

    /**
     * Status tab key => label/count map.
     *
     * @var array<string, array{label: string, count: int}>
     */
    private $statusTabs = [];

    /**
     * Bulk action key => label map.
     *
     * @var array<string, string>
     */
    private $bulkActions = [];

    /**
     * Page title.
     *
     * @var string
     */
    private $title = '';

    /**
     * Items per page.
     *
     * @var int
     */
    private $perPage = 20;

    /**
     * Current page number.
     *
     * @var int
     */
    private $currentPage = 1;

    /**
     * Search query string.
     *
     * @var string
     */
    private $searchQuery = '';

    /**
     * Search input placeholder.
     *
     * @var string
     */
    private $searchPlaceholder = '';

    /**
     * Current status tab (all|new|read).
     *
     * @var string
     */
    private $currentStatus = 'all';

    /**
     * Optional form CPT filter.
     *
     * @var int
     */
    private $formId = 0;

    /**
     * Date from filter (Y-m-d).
     *
     * @var string
     */
    private $dateFrom = '';

    /**
     * Date to filter (Y-m-d).
     *
     * @var string
     */
    private $dateTo = '';

    /**
     * Cached query total for pagination.
     *
     * @var int|null
     */
    private $queryTotal = null;

    /**
     * Whether bulk actions were already handled on load.
     *
     * @var bool
     */
    private $bulkActionsHandled = false;

    /**
     * Form CPT options for the filter select.
     *
     * @var array<int, \WP_Post>
     */
    private $forms = [];

    /**
     * Cached form titles keyed by form ID.
     *
     * @var array<int, string>
     */
    private $formTitles = [];

    /**
     * @param array<string, mixed> $args List configuration.
     * @since 3.0.0
     */
    public function __construct(array $args = [])
    {
        // Resolve repository from DI when available, otherwise construct directly.
        $this->repository = isset($args['repository']) && $args['repository'] instanceof SubmissionRepository
            ? $args['repository']
            : new SubmissionRepository();

        // Read list filters from the request with WordPress sanitization.
        $this->currentStatus = Helper::sanitizeInput(
            isset($_GET['status']) ? wp_unslash($_GET['status']) : 'all',
            'key'
        );
        // Only All / New / Read are valid list statuses.
        if (!in_array($this->currentStatus, ['all', 'new', 'read'], true)) {
            $this->currentStatus = 'all';
        }
        $this->formId = isset($_GET['form_id']) ? absint(wp_unslash($_GET['form_id'])) : 0;
        // Normalize date filters to Y-m-d so empty/invalid values never reach SQL.
        $this->dateFrom = $this->normalizeRequestDate(
            isset($_GET['date_from']) ? wp_unslash($_GET['date_from']) : ''
        );
        $this->dateTo = $this->normalizeRequestDate(
            isset($_GET['date_to']) ? wp_unslash($_GET['date_to']) : ''
        );
        $this->searchQuery = Helper::sanitizeInput(
            isset($_GET['s']) ? wp_unslash($_GET['s']) : '',
            'text'
        );
        $pagedRaw = isset($_GET['paged']) ? wp_unslash($_GET['paged']) : '1';
        $this->currentPage = Helper::isPositiveInt($pagedRaw)
            ? (int) Helper::sanitizeInput($pagedRaw, 'int')
            : 1;

        // Apply constructor overrides after request defaults.
        $this->title = isset($args['title']) ? (string) $args['title'] : __('Form Submissions', 'notifal');
        $this->perPage = max(1, (int) ($args['per_page'] ?? 20));
        $this->searchPlaceholder = isset($args['search_placeholder'])
            ? (string) $args['search_placeholder']
            : __('Search fields and notes...', 'notifal');
        $this->bulkActionsHandled = !empty($args['bulk_actions_handled']);
        $this->forms = isset($args['forms']) && is_array($args['forms']) ? $args['forms'] : [];

        // Columns stay filterable for Pro / extensions.
        $columns = isset($args['columns']) && is_array($args['columns'])
            ? $args['columns']
            : [
                'id'         => __('ID', 'notifal'),
                'form'       => __('Form', 'notifal'),
                'status'     => __('Status', 'notifal'),
                'admin_note' => __('Admin note', 'notifal'),
                'date'       => __('Date', 'notifal'),
            ];
        $this->columns = apply_filters(FilterHooks::FORMS_SUBMISSION_LIST_COLUMNS, $columns);

        // Bulk actions: mark as read + delete.
        $bulk = isset($args['bulk_actions']) && is_array($args['bulk_actions'])
            ? $args['bulk_actions']
            : [
                'mark_as_read' => __('Mark as read', 'notifal'),
                'delete'       => __('Delete', 'notifal'),
            ];
        $this->bulkActions = apply_filters(FilterHooks::FORMS_SUBMISSION_LIST_BULK_ACTIONS, $bulk);

        // Build status tabs from repository counts for the current form scope.
        $counts = isset($args['status_tabs']) && is_array($args['status_tabs'])
            ? $args['status_tabs']
            : $this->buildDefaultStatusTabs();
        $this->statusTabs = $counts;

        // Preload form titles used in the Form column.
        $this->hydrateFormTitles();
    }

    /**
     * Handle bulk delete before any list markup is printed.
     *
     * @return bool True when a bulk action ran.
     * @since 3.0.0
     */
    public static function handleBulkActions(): bool
    {
        // Only process POST submissions from the list form.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return false;
        }

        // Verify nonce before reading action IDs.
        $nonce = isset($_POST['notifal_bulk_nonce']) ? wp_unslash($_POST['notifal_bulk_nonce']) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'notifal_bulk_action')) {
            return false;
        }

        // Capability gate for admin-only mutations.
        if (!current_user_can('manage_options')) {
            return false;
        }

        $action = isset($_POST['bulk_action'])
            ? Helper::sanitizeInput(wp_unslash($_POST['bulk_action']), 'key')
            : '';
        $ids = isset($_POST['ids']) && is_array($_POST['ids'])
            ? array_map('absint', $_POST['ids'])
            : [];
        $ids = array_values(array_filter($ids));

        if ($action === '' || $ids === []) {
            return false;
        }

        $repo = new SubmissionRepository();
        $redirect = self::buildListUrlFromRequest();

        // Mark selected submissions as read.
        if ($action === 'mark_as_read') {
            $updated = $repo->markManyAsRead($ids);

            /**
             * Fires after submissions are marked read in bulk.
             *
             * @since 3.0.0
             */
            do_action(ActionHooks::FORMS_SUBMISSIONS_BULK_MARKED_READ, $ids, $updated);

            if ($updated > 0) {
                ToastManager::success(
                    sprintf(
                        /* translators: %d: number of submissions */
                        _n('%d submission marked as read.', '%d submissions marked as read.', $updated, 'notifal'),
                        $updated
                    ),
                    $redirect
                );
                return true;
            }

            ToastManager::error(__('No submissions were updated.', 'notifal'), $redirect);
            return true;
        }

        // Permanent delete for selected rows.
        if ($action !== 'delete') {
            return false;
        }

        $deleted = $repo->deleteMany($ids);

        /**
         * Fires after submissions are removed via bulk delete.
         *
         * @since 3.0.0
         */
        do_action(ActionHooks::FORMS_SUBMISSIONS_BULK_DELETED, $ids, $deleted);

        if ($deleted > 0) {
            ToastManager::success(
                sprintf(
                    /* translators: %d: number of deleted submissions */
                    _n('%d submission deleted.', '%d submissions deleted.', $deleted, 'notifal'),
                    $deleted
                ),
                $redirect
            );
            return true;
        }

        ToastManager::error(__('No submissions were deleted.', 'notifal'), $redirect);

        return true;
    }

    /**
     * Render the full list screen markup.
     *
     * @return void
     * @since 3.0.0
     */
    public function render(): void
    {
        // Run bulk actions when the caller did not handle them on load.
        if (!$this->bulkActionsHandled) {
            self::handleBulkActions();
        }

        echo '<div class="notifal-admin-list notifal-form-submissions-list">';
        $this->renderHeader();
        $this->renderStatusTabs();
        $this->renderFilters();
        $this->renderSearchForm();
        $this->renderTable();
        $this->renderPagination();
        // Quick view modal host (filled by Forms admin JS).
        $this->renderQuickViewModal();
        echo '</div>';
    }

    /**
     * Render the empty quick-view modal shell.
     *
     * @return void
     * @since 3.0.0
     */
    private function renderQuickViewModal(): void
    {
        echo '<div id="notifal-submission-quickview-backdrop" class="notifal-modal-backdrop" hidden>';
        echo '<div class="notifal-modal notifal-submission-quickview-modal" role="dialog" aria-modal="true" aria-labelledby="notifal-submission-quickview-title">';
        echo '<div class="notifal-modal-header">';
        echo '<h3 id="notifal-submission-quickview-title">' . esc_html__('Submission details', 'notifal') . '</h3>';
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- NotifalIconService::render() escapes icon markup via esc_attr().
        echo '<button type="button" class="notifal-modal-close notifal-submission-quickview-close" aria-label="'
            . esc_attr__('Close', 'notifal') . '">'
            . NotifalIconService::render('x-circle', 20)
            . '</button>';
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';
        echo '<div class="notifal-modal-body notifal-submission-quickview-body">';
        echo '<div class="notifal-modal-loader notifal-submission-quickview-loader">';
        echo '<div class="notifal-modal-loader-spinner"></div>';
        echo '<p class="notifal-modal-loader-text">' . esc_html__('Loading…', 'notifal') . '</p>';
        echo '</div>';
        echo '<div class="notifal-submission-quickview-content notifal-hidden"></div>';
        echo '</div>';
        echo '<div class="notifal-modal-footer notifal-submission-quickview-footer notifal-hidden">';
        echo '<a href="#" class="notifal-button secondary notifal-submission-quickview-full">'
            . esc_html__('Open full details', 'notifal') . '</a>';
        echo '<button type="button" class="notifal-button notifal-submission-quickview-save-note">'
            . esc_html__('Save note', 'notifal') . '</button>';
        echo '</div>';
        echo '</div></div>';
    }

    /**
     * Render title row and Export CSV action.
     *
     * @return void
     * @since 3.0.0
     */
    private function renderHeader(): void
    {
        // Build export URL with current filters + nonce.
        $exportArgs = [
            'action' => 'notifal_export_form_submissions',
        ];
        if ($this->formId > 0) {
            $exportArgs['form_id'] = $this->formId;
        }
        if ($this->searchQuery !== '') {
            $exportArgs['s'] = $this->searchQuery;
        }
        if ($this->dateFrom !== '') {
            $exportArgs['date_from'] = $this->dateFrom;
        }
        if ($this->dateTo !== '') {
            $exportArgs['date_to'] = $this->dateTo;
        }
        if ($this->currentStatus !== 'all') {
            $exportArgs['status'] = $this->currentStatus;
        }

        $exportUrl = wp_nonce_url(
            add_query_arg($exportArgs, admin_url('admin-post.php')),
            'notifal_form_submissions'
        );

        echo '<div class="notifal-list-header">';
        echo '<h1 class="notifal-page-title">' . esc_html($this->title) . '</h1>';
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- NotifalIconService::render() escapes icon markup via esc_attr().
        echo '<a href="' . esc_url($exportUrl) . '" class="notifal-button secondary notifal-flex notifal-gap-10">';
        echo NotifalIconService::render('cloud-download', 20);
        echo esc_html__('Export CSV', 'notifal');
        echo '</a>';
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';
    }

    /**
     * Render All / New / Read status tabs.
     *
     * @return void
     * @since 3.0.0
     */
    private function renderStatusTabs(): void
    {
        if ($this->statusTabs === []) {
            return;
        }

        echo '<ul class="notifal-status-tabs" role="tablist">';

        foreach ($this->statusTabs as $key => $tab) {
            $isActive = $this->currentStatus === $key;
            $url = $this->buildListUrl(['status' => $key, 'paged' => null, 's' => null]);

            printf(
                '<li class="%s" role="presentation">
                    <a href="%s" role="tab" aria-selected="%s" aria-controls="notifal-list-content">
                        %s <span class="count">(%d)</span>
                    </a>
                </li>',
                esc_attr($isActive ? 'active' : ''),
                esc_url($url),
                $isActive ? 'true' : 'false',
                esc_html($tab['label']),
                (int) $tab['count']
            );
        }

        echo '</ul>';
    }

    /**
     * Render form, status, and date filters (GET).
     *
     * @return void
     * @since 3.0.0
     */
    private function renderFilters(): void
    {
        echo '<form method="get" class="notifal-submission-filters" role="search">';
        printf('<input type="hidden" name="page" value="%s" />', esc_attr(self::PAGE_SLUG));

        // Preserve active search text across filter submits.
        if ($this->searchQuery !== '') {
            printf('<input type="hidden" name="s" value="%s" />', esc_attr($this->searchQuery));
        }

        echo '<div class="notifal-submission-filters__fields">';

        echo '<div class="notifal-submission-filters__field">';
        echo '<label for="notifal-submission-form-id">' . esc_html__('Form', 'notifal') . '</label>';
        echo '<select name="form_id" id="notifal-submission-form-id" class="notifal-setting-select">';
        echo '<option value="0">' . esc_html__('All forms', 'notifal') . '</option>';
        foreach ($this->forms as $form) {
            if (!$form instanceof \WP_Post) {
                continue;
            }
            printf(
                '<option value="%d" %s>%s</option>',
                (int) $form->ID,
                selected($this->formId, (int) $form->ID, false),
                esc_html($form->post_title !== '' ? $form->post_title : __('(no title)', 'notifal'))
            );
        }
        echo '</select></div>';

        // Status dropdown stays in sync with the status tabs above.
        echo '<div class="notifal-submission-filters__field">';
        echo '<label for="notifal-submission-status">' . esc_html__('Status', 'notifal') . '</label>';
        echo '<select name="status" id="notifal-submission-status" class="notifal-setting-select">';
        $statusOptions = [
            'all'  => __('All statuses', 'notifal'),
            'new'  => __('New', 'notifal'),
            'read' => __('Read', 'notifal'),
        ];
        foreach ($statusOptions as $value => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($value),
                selected($this->currentStatus, $value, false),
                esc_html($label)
            );
        }
        echo '</select></div>';

        echo '<div class="notifal-submission-filters__field">';
        echo '<label for="notifal-submission-date-from">' . esc_html__('From', 'notifal') . '</label>';
        printf(
            '<input type="date" name="date_from" id="notifal-submission-date-from" value="%s" class="notifal-setting-input" />',
            esc_attr($this->dateFrom)
        );
        echo '</div>';

        echo '<div class="notifal-submission-filters__field">';
        echo '<label for="notifal-submission-date-to">' . esc_html__('To', 'notifal') . '</label>';
        printf(
            '<input type="date" name="date_to" id="notifal-submission-date-to" value="%s" class="notifal-setting-input" />',
            esc_attr($this->dateTo)
        );
        echo '</div>';

        echo '<div class="notifal-submission-filters__actions">';
        printf(
            '<button type="submit" class="notifal-button secondary">%s</button>',
            esc_html__('Filter', 'notifal')
        );

        // Clear form, status, dates, search, and pagination back to the default list.
        $resetUrl = $this->buildListUrl(
            [
                'status'    => 'all',
                'form_id'   => null,
                'date_from' => null,
                'date_to'   => null,
                's'         => null,
                'paged'     => null,
            ]
        );
        printf(
            '<a href="%s" class="notifal-button secondary notifal-submission-filters__reset">%s</a>',
            esc_url($resetUrl),
            esc_html__('Reset', 'notifal')
        );
        echo '</div>';

        echo '</div></form>';
    }

    /**
     * Render the search form matching BaseListView layout.
     *
     * @return void
     * @since 3.0.0
     */
    private function renderSearchForm(): void
    {
        echo '<form method="get" class="notifal-search-form" role="search">';
        printf('<input type="hidden" name="page" value="%s" />', esc_attr(self::PAGE_SLUG));

        // Preserve filters while searching.
        if ($this->currentStatus !== 'all') {
            printf('<input type="hidden" name="status" value="%s" />', esc_attr($this->currentStatus));
        }
        if ($this->formId > 0) {
            printf('<input type="hidden" name="form_id" value="%d" />', $this->formId);
        }
        if ($this->dateFrom !== '') {
            printf('<input type="hidden" name="date_from" value="%s" />', esc_attr($this->dateFrom));
        }
        if ($this->dateTo !== '') {
            printf('<input type="hidden" name="date_to" value="%s" />', esc_attr($this->dateTo));
        }

        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- NotifalIconService::render() escapes icon markup via esc_attr().
        printf(
            '<label class="screen-reader-text" for="notifal-submission-search">%s</label>
            <input type="search" id="notifal-submission-search" name="s" value="%s" placeholder="%s" />
            <button type="submit" class="notifal-search-button">
                %s
                <span class="screen-reader-text">%s</span>
                %s
            </button>',
            esc_html($this->searchPlaceholder),
            esc_attr($this->searchQuery),
            esc_attr($this->searchPlaceholder),
            NotifalIconService::render('search', 16),
            esc_html__('Search', 'notifal'),
            esc_html__('Search', 'notifal')
        );
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

        echo '</form>';
        // Clear the floated search form before the table.
        echo '<div class="notifal-clearfix></div>';
    }

    /**
     * Render bulk actions + data table.
     *
     * @return void
     * @since 3.0.0
     */
    private function renderTable(): void
    {
        $result = $this->getQueryResults();
        $items = $result['items'];
        $this->queryTotal = $result['total'];
        $totalColumns = count($this->columns) + 2;

        echo '<form method="post" id="notifal-list-form" class="notifal-list-form">';
        wp_nonce_field('notifal_bulk_action', 'notifal_bulk_nonce');

        if ($this->bulkActions !== []) {
            $this->renderBulkActions();
        }

        echo '<table class="notifal-table" role="table">';
        $this->renderTableHeader();
        echo '<tbody>';

        if ($items === []) {
            printf(
                '<tr><td colspan="%d" class="no-items">%s</td></tr>',
                (int) $totalColumns,
                esc_html__('No submissions found.', 'notifal')
            );
        } else {
            foreach ($items as $item) {
                $this->renderTableRow($item);
            }
        }

        echo '</tbody></table></form>';
    }

    /**
     * Render bulk actions dropdown.
     *
     * @return void
     * @since 3.0.0
     */
    private function renderBulkActions(): void
    {
        echo '<div class="notifal-bulk-actions">';
        echo '<select name="bulk_action" class="notifal-bulk-select" aria-label="'
            . esc_attr__('Select bulk action', 'notifal') . '">';
        echo '<option value="">' . esc_html__('Bulk Actions', 'notifal') . '</option>';

        foreach ($this->bulkActions as $key => $label) {
            printf('<option value="%s">%s</option>', esc_attr($key), esc_html($label));
        }

        echo '</select>';
        printf(
            '<button type="submit" class="notifal-button secondary">%s</button>',
            esc_html__('Apply', 'notifal')
        );
        echo '</div>';
    }

    /**
     * Render table header row.
     *
     * @return void
     * @since 3.0.0
     */
    private function renderTableHeader(): void
    {
        echo '<thead><tr role="row">';
        printf(
            '<th scope="col" class="check-column">
                <input type="checkbox" class="notifal-master-checkbox" aria-label="%s" />
            </th>',
            esc_attr__('Select all items', 'notifal')
        );

        foreach ($this->columns as $label) {
            printf('<th scope="col">%s</th>', esc_html($label));
        }

        printf('<th scope="col">%s</th>', esc_html__('Actions', 'notifal'));
        echo '</tr></thead>';
    }

    /**
     * Render one submission row.
     *
     * @param array<string, mixed> $item Submission row.
     * @return void
     * @since 3.0.0
     */
    private function renderTableRow(array $item): void
    {
        $id = isset($item['id']) ? absint($item['id']) : 0;

        echo '<tr>';
        printf(
            '<td class="check-column">
                <input type="checkbox" name="ids[]" value="%d" class="notifal-row-checkbox" aria-label="%s" />
            </td>',
            $id,
            esc_attr(sprintf(/* translators: %d: submission ID */ __('Select submission #%d', 'notifal'), $id))
        );

        foreach (array_keys($this->columns) as $colKey) {
            echo '<td>';
            $this->renderColumnContent($colKey, $item);
            echo '</td>';
        }

        echo '<td class="notifal-list-actions">';
        $this->renderRowActions($item);
        echo '</td>';
        echo '</tr>';
    }

    /**
     * Render a single column cell.
     *
     * @param string               $columnKey Column key.
     * @param array<string, mixed> $item      Submission row.
     * @return void
     * @since 3.0.0
     */
    private function renderColumnContent(string $columnKey, array $item): void
    {
        $id = isset($item['id']) ? absint($item['id']) : 0;
        $formId = isset($item['form_id']) ? absint($item['form_id']) : 0;
        $status = isset($item['status']) ? sanitize_key((string) $item['status']) : 'new';
        $createdAt = isset($item['created_at']) ? (string) $item['created_at'] : '';

        switch ($columnKey) {
            case 'id':
                $viewUrl = $this->getViewUrl($id);
                printf(
                    '<a href="%s" class="row-title">#%d</a>',
                    esc_url($viewUrl),
                    $id
                );
                break;

            case 'form':
                $title = $this->resolveFormTitle($formId);
                if ($formId > 0) {
                    $inboxUrl = $this->buildListUrl(['form_id' => $formId, 'paged' => null]);
                    printf(
                        '<a href="%s">%s</a>',
                        esc_url($inboxUrl),
                        esc_html($title)
                    );
                } else {
                    echo esc_html($title);
                }
                break;

            case 'status':
                $label = $status === 'read'
                    ? __('Read', 'notifal')
                    : __('New', 'notifal');
                printf(
                    '<span class="notifal-status-badge notifal-status-%s">%s</span>',
                    esc_attr($status !== '' ? $status : 'new'),
                    esc_html($label)
                );
                break;

            case 'admin_note':
                $note = isset($item['admin_note']) ? trim((string) $item['admin_note']) : '';
                if ($note === '') {
                    echo '<span class="notifal-submission-note-preview">&mdash;</span>';
                    break;
                }
                // Truncate long notes for the list cell.
                $preview = function_exists('mb_substr')
                    ? mb_substr($note, 0, 80)
                    : substr($note, 0, 80);
                if ($preview !== $note) {
                    $preview .= '…';
                }
                echo '<span class="notifal-submission-note-preview">' . esc_html($preview) . '</span>';
                break;

            case 'date':
                if ($createdAt === '') {
                    echo '&mdash;';
                    break;
                }
                // Treat created_at as WP local mysql time (matches current_time on insert).
                $display = mysql2date(
                    get_option('date_format') . ' ' . get_option('time_format'),
                    $createdAt
                );
                printf(
                    '<time datetime="%s">%s</time>',
                    esc_attr(mysql2date('c', $createdAt)),
                    esc_html(is_string($display) ? $display : $createdAt)
                );
                break;

            default:
                echo '&mdash;';
                break;
        }
    }

    /**
     * Render quick view, full view, mark-read, and delete row actions.
     *
     * @param array<string, mixed> $item Submission row.
     * @return void
     * @since 3.0.0
     */
    private function renderRowActions(array $item): void
    {
        $id = isset($item['id']) ? absint($item['id']) : 0;
        if ($id <= 0) {
            return;
        }

        $status = isset($item['status']) ? sanitize_key((string) $item['status']) : 'new';
        $viewUrl = $this->getViewUrl($id);
        $deleteUrl = $this->getDeleteUrl($id);
        $confirm = esc_js(
            sprintf(
                /* translators: %d: submission ID */
                __('Are you sure you want to permanently delete submission #%d?', 'notifal'),
                $id
            )
        );

        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- NotifalIconService::render() escapes icon markup via esc_attr().
        // Quick view opens the modal without leaving the list.
        printf(
            '<button type="button" class="notifal-button secondary notifal-submission-quickview" data-id="%d" title="%s" aria-label="%s">%s</button>',
            $id,
            esc_attr__('Quick view', 'notifal'),
            esc_attr(sprintf(/* translators: %d: submission ID */ __('Quick view submission #%d', 'notifal'), $id)),
            NotifalIconService::render('eye', 20)
        );

        // Full detail page.
        printf(
            '<a href="%s" class="notifal-button secondary" title="%s" aria-label="%s">%s</a>',
            esc_url($viewUrl),
            esc_attr__('Full details', 'notifal'),
            esc_attr(sprintf(/* translators: %d: submission ID */ __('Open full details for submission #%d', 'notifal'), $id)),
            NotifalIconService::render('file-earmark1', 20)
        );

        // Mark as read (hidden once already read).
        if ($status !== 'read') {
            printf(
                '<button type="button" class="notifal-button secondary notifal-submission-mark-read" data-id="%d" title="%s" aria-label="%s">%s</button>',
                $id,
                esc_attr__('Mark as read', 'notifal'),
                esc_attr(sprintf(/* translators: %d: submission ID */ __('Mark submission #%d as read', 'notifal'), $id)),
                NotifalIconService::render('check', 20)
            );
        }

        printf(
            '<a href="%s" class="notifal-button secondary delete-link" title="%s" data-confirm-message="%s" aria-label="%s">%s</a>',
            esc_url($deleteUrl),
            esc_attr__('Delete', 'notifal'),
            $confirm,
            esc_attr(sprintf(/* translators: %d: submission ID */ __('Delete submission #%d', 'notifal'), $id)),
            NotifalIconService::render('trash', 20)
        );
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Render pagination controls.
     *
     * @return void
     * @since 3.0.0
     */
    private function renderPagination(): void
    {
        $total = $this->queryTotal !== null ? (int) $this->queryTotal : 0;
        $totalPages = (int) ceil($total / $this->perPage);

        if ($totalPages <= 1) {
            return;
        }

        $range = 2;
        $start = max(1, $this->currentPage - $range);
        $end = min($totalPages, $this->currentPage + $range);

        echo '<div class="notifal-pagination-wrapper">';
        echo '<div class="notifal-pagination-info">';
        printf(
            esc_html__('Showing %1$d to %2$d of %3$d items', 'notifal'),
            (($this->currentPage - 1) * $this->perPage) + 1,
            min($this->currentPage * $this->perPage, $total),
            $total
        );
        echo '</div><div class="notifal-pagination">';

        if ($this->currentPage > 1) {
            echo '<a class="page-number" href="'
                . esc_url($this->buildListUrl(['paged' => $this->currentPage - 1]))
                . '">&laquo;</a>';
        }

        if ($start > 1) {
            echo '<a class="page-number" href="' . esc_url($this->buildListUrl(['paged' => 1])) . '">1</a>';
            if ($start > 2) {
                echo '<span class="page-dots">...</span>';
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $active = ($i === $this->currentPage) ? ' active' : '';
            echo '<a class="page-number' . esc_attr($active) . '" href="'
                . esc_url($this->buildListUrl(['paged' => $i])) . '">' . (int) $i . '</a>';
        }

        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                echo '<span class="page-dots">...</span>';
            }
            echo '<a class="page-number" href="'
                . esc_url($this->buildListUrl(['paged' => $totalPages]))
                . '">' . (int) $totalPages . '</a>';
        }

        if ($this->currentPage < $totalPages) {
            echo '<a class="page-number" href="'
                . esc_url($this->buildListUrl(['paged' => $this->currentPage + 1]))
                . '">&raquo;</a>';
        }

        echo '</div></div>';
    }

    /**
     * Query submissions for the current filters.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int}
     * @since 3.0.0
     */
    private function getQueryResults(): array
    {
        return $this->repository->query([
            'form_id'   => $this->formId,
            'status'    => $this->currentStatus,
            'search'    => $this->searchQuery,
            'date_from' => $this->dateFrom,
            'date_to'   => $this->dateTo,
            'page'      => $this->currentPage,
            'per_page'  => $this->perPage,
        ]);
    }

    /**
     * Default All / New / Read tabs with counts.
     *
     * @return array<string, array{label: string, count: int}>
     * @since 3.0.0
     */
    private function buildDefaultStatusTabs(): array
    {
        $counts = $this->repository->getStatusCounts($this->formId);

        return [
            'all' => [
                'label' => __('All', 'notifal'),
                'count' => (int) ($counts['all'] ?? 0),
            ],
            'new' => [
                'label' => __('New', 'notifal'),
                'count' => (int) ($counts['new'] ?? 0),
            ],
            'read' => [
                'label' => __('Read', 'notifal'),
                'count' => (int) ($counts['read'] ?? 0),
            ],
        ];
    }

    /**
     * Prefetch form titles for rows on this page.
     *
     * @return void
     * @since 3.0.0
     */
    private function hydrateFormTitles(): void
    {
        foreach ($this->forms as $form) {
            if (!$form instanceof \WP_Post) {
                continue;
            }
            $this->formTitles[(int) $form->ID] = $form->post_title !== ''
                ? $form->post_title
                : __('(no title)', 'notifal');
        }
    }

    /**
     * Resolve a display title for a form CPT ID.
     *
     * @param int $formId Form CPT ID.
     * @return string
     * @since 3.0.0
     */
    private function resolveFormTitle(int $formId): string
    {
        if ($formId <= 0) {
            return __('Unknown form', 'notifal');
        }

        if (isset($this->formTitles[$formId])) {
            return $this->formTitles[$formId];
        }

        $title = get_the_title($formId);
        $this->formTitles[$formId] = $title !== '' ? $title : sprintf(
            /* translators: %d: form ID */
            __('Form #%d', 'notifal'),
            $formId
        );

        return $this->formTitles[$formId];
    }

    /**
     * Build the detail screen URL for a submission.
     *
     * @param int $id Submission ID.
     * @return string
     * @since 3.0.0
     */
    private function getViewUrl(int $id): string
    {
        return add_query_arg(
            [
                'page'          => self::PAGE_SLUG,
                'submission_id' => $id,
            ],
            UrlHelper::admin('admin.php')
        );
    }

    /**
     * Build the delete URL for a submission row action.
     *
     * @param int $id Submission ID.
     * @return string
     * @since 3.0.0
     */
    private function getDeleteUrl(int $id): string
    {
        return add_query_arg(
            [
                'action'   => 'notifal_delete_form_submission',
                'id'       => $id,
                '_wpnonce' => wp_create_nonce('delete_form_submission_' . $id),
            ],
            UrlHelper::admin('admin.php')
        );
    }

    /**
     * Build a list URL preserving current filters with optional overrides.
     *
     * Pass null for a key to remove that query arg.
     *
     * @param array<string, mixed> $overrides Query overrides.
     * @return string
     * @since 3.0.0
     */
    private function buildListUrl(array $overrides = []): string
    {
        $args = [
            'page'      => self::PAGE_SLUG,
            'status'    => $this->currentStatus,
            'form_id'   => $this->formId > 0 ? $this->formId : null,
            'date_from' => $this->dateFrom !== '' ? $this->dateFrom : null,
            'date_to'   => $this->dateTo !== '' ? $this->dateTo : null,
            's'         => $this->searchQuery !== '' ? $this->searchQuery : null,
            'paged'     => $this->currentPage > 1 ? $this->currentPage : null,
        ];

        foreach ($overrides as $key => $value) {
            $args[$key] = $value;
        }

        // Drop empty / null args so URLs stay clean.
        $clean = [];
        foreach ($args as $key => $value) {
            if ($value === null || $value === '' || $value === false) {
                continue;
            }
            if ($key === 'status' && $value === 'all') {
                continue;
            }
            $clean[$key] = $value;
        }

        return add_query_arg($clean, UrlHelper::admin('admin.php'));
    }

    /**
     * Build a list redirect URL from the current request (bulk handler).
     *
     * @return string
     * @since 3.0.0
     */
    private static function buildListUrlFromRequest(): string
    {
        $args = [
            'page' => self::PAGE_SLUG,
        ];

        $status = Helper::sanitizeInput(
            isset($_GET['status']) ? wp_unslash($_GET['status']) : 'all',
            'key'
        );
        if ($status !== '' && $status !== 'all') {
            $args['status'] = $status;
        }

        $formId = isset($_GET['form_id']) ? absint(wp_unslash($_GET['form_id'])) : 0;
        if ($formId > 0) {
            $args['form_id'] = $formId;
        }

        $dateFrom = self::normalizeStaticRequestDate(
            isset($_GET['date_from']) ? wp_unslash($_GET['date_from']) : ''
        );
        if ($dateFrom !== '') {
            $args['date_from'] = $dateFrom;
        }

        $dateTo = self::normalizeStaticRequestDate(
            isset($_GET['date_to']) ? wp_unslash($_GET['date_to']) : ''
        );
        if ($dateTo !== '') {
            $args['date_to'] = $dateTo;
        }

        $search = Helper::sanitizeInput(
            isset($_GET['s']) ? wp_unslash($_GET['s']) : '',
            'text'
        );
        if ($search !== '') {
            $args['s'] = $search;
        }

        return add_query_arg($args, UrlHelper::admin('admin.php'));
    }

    /**
     * Normalize a request date to Y-m-d for list filters.
     *
     * @param mixed $raw Raw request value.
     * @return string
     * @since 3.0.0
     */
    private function normalizeRequestDate($raw): string
    {
        return self::normalizeStaticRequestDate($raw);
    }

    /**
     * Static date normalizer for bulk redirect URLs.
     *
     * @param mixed $raw Raw request value.
     * @return string
     * @since 3.0.0
     */
    private static function normalizeStaticRequestDate($raw): string
    {
        $raw = sanitize_text_field(is_string($raw) ? trim($raw) : '');
        if ($raw === '') {
            return '';
        }

        // Match HTML5 date inputs (Y-m-d).
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        if ($dt instanceof \DateTimeImmutable && $dt->format('Y-m-d') === $raw) {
            return $raw;
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return '';
        }

        return wp_date('Y-m-d', $timestamp);
    }
}
