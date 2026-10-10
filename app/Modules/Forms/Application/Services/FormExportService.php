<?php

namespace Notifal\Modules\Forms\Application\Services;

use Notifal\Modules\Forms\Application\Services\Security\CsvFormulaEscaper;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\SubmissionRepository;

defined('ABSPATH') || exit;

/**
 * CSV export for form submissions (formula-safe).
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormExportService
{
    /**
     * @var SubmissionRepository
     */
    private $submissions;

    /**
     * @param SubmissionRepository $submissions Repository.
     * @since 3.0.0
     */
    public function __construct(SubmissionRepository $submissions)
    {
        $this->submissions = $submissions;
    }

    /**
     * Stream a CSV download for filtered submissions.
     *
     * @param array<string, mixed> $filters Filter args (form_id, search, date_from, date_to, status).
     * @return void
     * @since 3.0.0
     */
    public function streamCsv(array $filters): void
    {
        // Normalize alternate search key from list export URLs.
        if (!isset($filters['search']) && isset($filters['s'])) {
            $filters['search'] = $filters['s'];
        }

        $rows = $this->submissions->query(array_merge($filters, [
            'per_page' => 5000,
            'page'     => 1,
        ]));

        $filename = 'notifal-form-submissions-' . gmdate('Y-m-d') . '.csv';

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            return;
        }

        // UTF-8 BOM for spreadsheet compatibility.
        fwrite($out, "\xEF\xBB\xBF");

        $headers = ['id', 'form_id', 'notification_id', 'status', 'admin_note', 'page_url', 'created_at', 'fields'];
        fputcsv($out, $headers);

        foreach ($rows['items'] as $item) {
            $fieldMap = $this->submissions->getFields((int) $item['id']);
            $fieldJson = wp_json_encode($fieldMap);

            fputcsv($out, [
                CsvFormulaEscaper::escape((string) $item['id']),
                CsvFormulaEscaper::escape((string) $item['form_id']),
                CsvFormulaEscaper::escape((string) $item['notification_id']),
                CsvFormulaEscaper::escape((string) $item['status']),
                CsvFormulaEscaper::escape(isset($item['admin_note']) ? (string) $item['admin_note'] : ''),
                CsvFormulaEscaper::escape((string) $item['page_url']),
                CsvFormulaEscaper::escape((string) $item['created_at']),
                CsvFormulaEscaper::escape((string) $fieldJson),
            ]);
        }

        fclose($out);
        exit;
    }
}
