<?php

namespace Notifal\Modules\Forms\Infrastructure\WordPress\Repositories;

use Notifal\Modules\Forms\Application\Services\UniqueValueNormalizer;

defined('ABSPATH') || exit;

/**
 * Persistence for form submissions and field values.
 *
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * Table names use $wpdb->prefix and are plugin-controlled identifiers.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SubmissionRepository
{
    /**
     * Submissions table name with prefix.
     *
     * @return string
     * @since 3.0.0
     */
    public function getSubmissionsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'notifal_form_submissions';
    }

    /**
     * Fields table name with prefix.
     *
     * @return string
     * @since 3.0.0
     */
    public function getFieldsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'notifal_form_submission_fields';
    }

    /**
     * Create a submission and its field rows.
     *
     * @param array<string, mixed> $data Submission data including fields map.
     * @return int Submission ID.
     * @since 3.0.0
     */
    public function create(array $data): int
    {
        global $wpdb;

        $table = $this->getSubmissionsTable();
        $now = current_time('mysql');

        $wpdb->insert(
            $table,
            [
                'form_id'         => isset($data['form_id']) ? absint($data['form_id']) : 0,
                'notification_id' => isset($data['notification_id']) ? absint($data['notification_id']) : 0,
                'status'          => isset($data['status']) ? sanitize_key((string) $data['status']) : 'new',
                'admin_note'      => isset($data['admin_note'])
                    ? sanitize_textarea_field((string) $data['admin_note'])
                    : '',
                'ip_hash'         => isset($data['ip_hash']) ? sanitize_text_field((string) $data['ip_hash']) : '',
                'user_agent'      => isset($data['user_agent']) ? sanitize_textarea_field((string) $data['user_agent']) : '',
                'page_url'        => isset($data['page_url']) ? esc_url_raw((string) $data['page_url']) : '',
                'created_at'      => $now,
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        $submissionId = (int) $wpdb->insert_id;
        if ($submissionId <= 0) {
            return 0;
        }

        $fields = isset($data['fields']) && is_array($data['fields']) ? $data['fields'] : [];
        $fieldsTable = $this->getFieldsTable();

        foreach ($fields as $name => $value) {
            $stored = is_array($value) ? wp_json_encode($value) : (string) $value;
            $wpdb->insert(
                $fieldsTable,
                [
                    'submission_id' => $submissionId,
                    'field_name'    => sanitize_key((string) $name),
                    'field_value'   => $stored,
                ],
                ['%d', '%s', '%s']
            );
        }

        return $submissionId;
    }

    /**
     * Whether a field value already exists for a form (case-insensitive).
     *
     * Used when a field is marked unique so duplicate inbox values are rejected.
     *
     * @param int    $formId    Form CPT ID.
     * @param string $fieldName Field machine name.
     * @param string $fieldValue Normalized stored value to compare.
     * @return bool
     * @since 3.0.0
     */
    public function fieldValueExists(int $formId, string $fieldName, string $fieldValue): bool
    {
        global $wpdb;

        // Invalid inputs cannot match an existing submission row.
        if ($formId <= 0 || $fieldName === '' || $fieldValue === '') {
            return false;
        }

        $submissionsTable = $this->getSubmissionsTable();
        $fieldsTable = $this->getFieldsTable();

        // Fold submitted casing in PHP so comparison matches stored mixed-case rows.
        $compareValue = UniqueValueNormalizer::toCompareString($fieldValue);
        if ($compareValue === '') {
            return false;
        }

        // Compare trimmed lowercase values so email/text uniqueness is case-insensitive.
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT f.submission_id
                FROM {$fieldsTable} f
                INNER JOIN {$submissionsTable} s ON s.id = f.submission_id
                WHERE s.form_id = %d
                    AND f.field_name = %s
                    AND LOWER(TRIM(f.field_value)) = %s
                LIMIT 1",
                $formId,
                sanitize_key($fieldName),
                $compareValue
            )
        );

        return $exists !== null && (int) $exists > 0;
    }

    /**
     * Query submissions with filters.
     *
     * @param array<string, mixed> $args Query args.
     * @return array{items: array<int, array<string, mixed>>, total: int}
     * @since 3.0.0
     */
    public function query(array $args): array
    {
        global $wpdb;

        $table = $this->getSubmissionsTable();
        $perPage = isset($args['per_page']) ? max(1, absint($args['per_page'])) : 20;
        $page = isset($args['page']) ? max(1, absint($args['page'])) : 1;
        $offset = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];

        if (!empty($args['form_id'])) {
            $where[] = 'form_id = %d';
            $params[] = absint($args['form_id']);
        }

        // Status tab / dropdown filter (all skips this clause).
        if (!empty($args['status']) && (string) $args['status'] !== 'all') {
            $where[] = 'status = %s';
            $params[] = sanitize_key((string) $args['status']);
        }

        // Compare by calendar day so From/To match the admin date pickers.
        $dateFrom = $this->normalizeDate(isset($args['date_from']) ? (string) $args['date_from'] : '');
        if ($dateFrom !== '') {
            $where[] = 'DATE(created_at) >= %s';
            $params[] = $dateFrom;
        }

        $dateTo = $this->normalizeDate(isset($args['date_to']) ? (string) $args['date_to'] : '');
        if ($dateTo !== '') {
            $where[] = 'DATE(created_at) <= %s';
            $params[] = $dateTo;
        }

        if (!empty($args['search'])) {
            // Match inbox field values or admin notes (scoped by other WHERE clauses such as form_id).
            $search = '%' . $wpdb->esc_like(sanitize_text_field((string) $args['search'])) . '%';
            $fieldsTable = $this->getFieldsTable();
            $where[] = "(admin_note LIKE %s OR id IN (SELECT submission_id FROM {$fieldsTable} WHERE field_value LIKE %s))";
            $params[] = $search;
            $params[] = $search;
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM {$table} WHERE {$whereSql}";
        $total = empty($params)
            ? (int) $wpdb->get_var($countSql)
            : (int) $wpdb->get_var($wpdb->prepare($countSql, $params));

        $listSql = "SELECT * FROM {$table} WHERE {$whereSql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $listParams = array_merge($params, [$perPage, $offset]);
        $items = $wpdb->get_results($wpdb->prepare($listSql, $listParams), ARRAY_A);

        return [
            'items' => is_array($items) ? $items : [],
            'total' => $total,
        ];
    }

    /**
     * Get one submission row.
     *
     * @param int $id Submission ID.
     * @return array<string, mixed>|null
     * @since 3.0.0
     */
    public function get(int $id): ?array
    {
        global $wpdb;

        $table = $this->getSubmissionsTable();
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Get field map for a submission.
     *
     * @param int $submissionId Submission ID.
     * @return array<string, string>
     * @since 3.0.0
     */
    public function getFields(int $submissionId): array
    {
        global $wpdb;

        $table = $this->getFieldsTable();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT field_name, field_value FROM {$table} WHERE submission_id = %d",
                $submissionId
            ),
            ARRAY_A
        );

        $map = [];
        if (!is_array($rows)) {
            return $map;
        }

        foreach ($rows as $row) {
            $map[(string) $row['field_name']] = (string) $row['field_value'];
        }

        return $map;
    }

    /**
     * Delete a submission and its fields.
     *
     * @param int $id Submission ID.
     * @return bool
     * @since 3.0.0
     */
    public function delete(int $id): bool
    {
        global $wpdb;

        $wpdb->delete($this->getFieldsTable(), ['submission_id' => $id], ['%d']);
        $deleted = $wpdb->delete($this->getSubmissionsTable(), ['id' => $id], ['%d']);

        return $deleted !== false;
    }

    /**
     * Delete many submissions and their field rows.
     *
     * @param array<int, int> $ids Submission IDs.
     * @return int Number of submission rows deleted.
     * @since 3.0.0
     */
    public function deleteMany(array $ids): int
    {
        // Normalize to positive unique integers only.
        $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        if ($ids === []) {
            return 0;
        }

        $deleted = 0;
        foreach ($ids as $id) {
            if ($this->delete($id)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Update a submission status key.
     *
     * @param int    $id     Submission ID.
     * @param string $status Status slug (e.g. new, read).
     * @return bool
     * @since 3.0.0
     */
    public function updateStatus(int $id, string $status): bool
    {
        global $wpdb;

        // Ignore invalid IDs early.
        if ($id <= 0) {
            return false;
        }

        $status = sanitize_key($status);
        if ($status === '') {
            return false;
        }

        $updated = $wpdb->update(
            $this->getSubmissionsTable(),
            ['status' => $status],
            ['id' => $id],
            ['%s'],
            ['%d']
        );

        return $updated !== false;
    }

    /**
     * Mark many submissions as read.
     *
     * @param array<int, int> $ids Submission IDs.
     * @return int Number of rows updated.
     * @since 3.0.0
     */
    public function markManyAsRead(array $ids): int
    {
        // Normalize to positive unique integers only.
        $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        if ($ids === []) {
            return 0;
        }

        $updated = 0;
        foreach ($ids as $id) {
            if ($this->updateStatus($id, 'read')) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Save an admin note for a submission.
     *
     * @param int    $id   Submission ID.
     * @param string $note Admin note text.
     * @return bool
     * @since 3.0.0
     */
    public function updateAdminNote(int $id, string $note): bool
    {
        global $wpdb;

        // Ignore invalid IDs early.
        if ($id <= 0) {
            return false;
        }

        $updated = $wpdb->update(
            $this->getSubmissionsTable(),
            ['admin_note' => sanitize_textarea_field($note)],
            ['id' => $id],
            ['%s'],
            ['%d']
        );

        return $updated !== false;
    }

    /**
     * Count submissions for status tabs, optionally scoped to one form.
     *
     * @param int $formId Optional form CPT ID filter (0 = all forms).
     * @return array{all: int, new: int, read: int}
     * @since 3.0.0
     */
    public function getStatusCounts(int $formId = 0): array
    {
        global $wpdb;

        $table = $this->getSubmissionsTable();
        $where = '1=1';
        $params = [];

        // Optional form scope for inbox deep-links.
        if ($formId > 0) {
            $where .= ' AND form_id = %d';
            $params[] = $formId;
        }

        $sql = "SELECT status, COUNT(*) AS total FROM {$table} WHERE {$where} GROUP BY status";
        $rows = empty($params)
            ? $wpdb->get_results($sql, ARRAY_A)
            : $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        $counts = [
            'all'  => 0,
            'new'  => 0,
            'read' => 0,
        ];

        if (!is_array($rows)) {
            return $counts;
        }

        foreach ($rows as $row) {
            $status = sanitize_key((string) ($row['status'] ?? ''));
            $total = (int) ($row['total'] ?? 0);
            $counts['all'] += $total;

            if (isset($counts[$status])) {
                $counts[$status] += $total;
            }
        }

        return $counts;
    }

    /**
     * Count submissions for one form.
     *
     * @param int $formId Form CPT ID.
     * @return int
     * @since 3.0.0
     */
    public function countByFormId(int $formId): int
    {
        global $wpdb;

        // Ignore invalid form IDs early.
        if ($formId <= 0) {
            return 0;
        }

        $table = $this->getSubmissionsTable();

        // Count rows that belong to this form CPT.
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE form_id = %d",
                $formId
            )
        );
    }

    /**
     * Get submission counts keyed by form ID (one query for list screens).
     *
     * @return array<int, int> Map of form_id => count.
     * @since 3.0.0
     */
    public function getCountsGroupedByForm(): array
    {
        global $wpdb;

        $table = $this->getSubmissionsTable();
        // Aggregate all forms in a single pass for the Forms list inbox column.
        $rows = $wpdb->get_results(
            "SELECT form_id, COUNT(*) AS total FROM {$table} GROUP BY form_id",
            ARRAY_A
        );

        $map = [];
        if (!is_array($rows)) {
            return $map;
        }

        foreach ($rows as $row) {
            $map[(int) $row['form_id']] = (int) $row['total'];
        }

        return $map;
    }

    /**
     * Normalize a filter date string to Y-m-d, or empty when invalid.
     *
     * @param string $raw Raw date input.
     * @return string
     * @since 3.0.0
     */
    private function normalizeDate(string $raw): string
    {
        $raw = sanitize_text_field(trim($raw));
        if ($raw === '') {
            return '';
        }

        // Preferred format from <input type="date">.
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        if ($dt instanceof \DateTimeImmutable && $dt->format('Y-m-d') === $raw) {
            return $raw;
        }

        // Accept common pasted formats without inventing invalid days.
        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return '';
        }

        return wp_date('Y-m-d', $timestamp);
    }
}
