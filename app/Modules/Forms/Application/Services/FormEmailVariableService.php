<?php

namespace Notifal\Modules\Forms\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Merge tags for form email subject and body templates.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormEmailVariableService
{
    /**
     * System variables available in every email template.
     *
     * @return array<int, array<string, string>>
     * @since 3.0.0
     */
    public function getSystemVariables(): array
    {
        // Built-in tags that do not depend on form field definitions.
        $variables = [
            [
                'key'         => 'site_name',
                'label'       => __('Site name', 'notifal'),
                'description' => __('WordPress site title.', 'notifal'),
                'category'    => 'system',
            ],
            [
                'key'         => 'site_url',
                'label'       => __('Site URL', 'notifal'),
                'description' => __('Homepage address of this site.', 'notifal'),
                'category'    => 'system',
            ],
            [
                'key'         => 'form_title',
                'label'       => __('Form title', 'notifal'),
                'description' => __('Title of this form in the admin.', 'notifal'),
                'category'    => 'system',
            ],
            [
                'key'         => 'form_id',
                'label'       => __('Form ID', 'notifal'),
                'description' => __('Numeric ID of this form.', 'notifal'),
                'category'    => 'system',
            ],
            [
                'key'         => 'submission_id',
                'label'       => __('Submission ID', 'notifal'),
                'description' => __('Unique ID of the stored submission.', 'notifal'),
                'category'    => 'system',
            ],
            [
                'key'         => 'submission_date',
                'label'       => __('Submission date', 'notifal'),
                'description' => __('Localized date when the form was submitted.', 'notifal'),
                'category'    => 'system',
            ],
            [
                'key'         => 'submission_time',
                'label'       => __('Submission time', 'notifal'),
                'description' => __('Localized time when the form was submitted.', 'notifal'),
                'category'    => 'system',
            ],
            [
                'key'         => 'admin_email',
                'label'       => __('Admin email', 'notifal'),
                'description' => __('Notification recipient address.', 'notifal'),
                'category'    => 'system',
            ],
            [
                'key'         => 'current_year',
                'label'       => __('Current year', 'notifal'),
                'description' => __('Current calendar year (useful in footers).', 'notifal'),
                'category'    => 'system',
            ],
            [
                'key'         => 'all_fields',
                'label'       => __('All fields (plain)', 'notifal'),
                'description' => __('All submitted values as plain text lines.', 'notifal'),
                'category'    => 'system',
            ],
            [
                'key'         => 'all_fields_table',
                'label'       => __('All fields (HTML table)', 'notifal'),
                'description' => __('All submitted values as an HTML table (HTML emails only).', 'notifal'),
                'category'    => 'system',
            ],
        ];

        /**
         * Filters system merge tags for form emails.
         *
         * @param array<int, array<string, string>> $variables System variables.
         * @since 3.0.0
         */
        return (array) apply_filters(FilterHooks::FORMS_EMAIL_SYSTEM_VARIABLES, $variables);
    }

    /**
     * Build field variables from form field definitions.
     *
     * @param array<int, array<string, mixed>> $fields Field definitions.
     * @return array<int, array<string, string>>
     * @since 3.0.0
     */
    public function getFieldVariables(array $fields): array
    {
        // Collect one tag per configured field name.
        $variables = [];

        foreach ($fields as $field) {
            // Skip rows without a usable machine name.
            $name = isset($field['name']) ? sanitize_key((string) $field['name']) : '';
            if ($name === '') {
                continue;
            }

            // Prefer the human label when present.
            $label = isset($field['label']) ? sanitize_text_field((string) $field['label']) : $name;

            $variables[] = [
                'key'         => $name,
                'label'       => $label,
                'description' => sprintf(
                    /* translators: %s: field label */
                    __('Submitted value for "%s".', 'notifal'),
                    $label
                ),
                'category'    => 'fields',
            ];
        }

        /**
         * Filters field merge tags for form emails.
         *
         * @param array<int, array<string, string>> $variables Field variables.
         * @param array<int, array<string, mixed>>  $fields      Field definitions.
         * @since 3.0.0
         */
        return (array) apply_filters(FilterHooks::FORMS_EMAIL_FIELD_VARIABLES, $variables, $fields);
    }

    /**
     * Variables grouped for the admin emails tab UI.
     *
     * @param array<int, array<string, mixed>> $fields Field definitions.
     * @return array<string, mixed>
     * @since 3.0.0
     */
    public function getForAdminUi(array $fields): array
    {
        return [
            'system' => $this->getSystemVariables(),
            'fields' => $this->getFieldVariables($fields),
        ];
    }

    /**
     * Replace merge tags in a template string.
     *
     * @param string               $template Template with {tags}.
     * @param array<string, mixed> $context  Replacement map.
     * @return string
     * @since 3.0.0
     */
    public function replace(string $template, array $context): string
    {
        // Nothing to replace when the template is empty.
        if ($template === '') {
            return '';
        }

        // Walk every context key and swap {key} placeholders.
        foreach ($context as $key => $value) {
            $token = '{' . sanitize_key((string) $key) . '}';
            $template = str_replace($token, (string) $value, $template);
        }

        /**
         * Filters the rendered email template after merge tag replacement.
         *
         * @param string               $template Rendered template.
         * @param array<string, mixed> $context  Replacement map.
         * @since 3.0.0
         */
        return (string) apply_filters(FilterHooks::FORMS_EMAIL_TEMPLATE_RENDERED, $template, $context);
    }

    /**
     * Build the replacement map for a submission.
     *
     * @param int                  $formId       Form CPT ID.
     * @param int                  $submissionId Submission ID.
     * @param array<string, mixed> $fields       Sanitized submission fields.
     * @param string               $formTitle    Form title.
     * @param string               $adminEmail   Recipient email.
     * @return array<string, string>
     * @since 3.0.0
     */
    public function buildContext(
        int $formId,
        int $submissionId,
        array $fields,
        string $formTitle,
        string $adminEmail
    ): array {
        // Timestamp for date/time tags uses WordPress timezone settings.
        $timestamp = current_time('timestamp');

        // Start with system values.
        $context = [
            'site_name'        => sanitize_text_field(get_bloginfo('name')),
            'site_url'         => esc_url(home_url('/')),
            'form_title'       => sanitize_text_field($formTitle),
            'form_id'          => (string) $formId,
            'submission_id'    => (string) $submissionId,
            'submission_date'  => wp_date(get_option('date_format'), $timestamp),
            'submission_time'  => wp_date(get_option('time_format'), $timestamp),
            'admin_email'      => sanitize_email($adminEmail),
            'current_year'     => wp_date('Y', $timestamp),
            'all_fields'       => $this->formatAllFieldsPlain($fields),
            'all_fields_table' => $this->formatAllFieldsTable($fields),
        ];

        // Add one entry per submitted field value.
        foreach ($fields as $name => $value) {
            $key = sanitize_key((string) $name);
            if ($key === '') {
                continue;
            }
            $context[$key] = $this->formatFieldValue($value);
        }

        /**
         * Filters merge tag values before email rendering.
         *
         * @param array<string, string> $context      Replacement map.
         * @param int                     $formId       Form CPT ID.
         * @param int                     $submissionId Submission ID.
         * @param array<string, mixed>    $fields       Sanitized fields.
         * @since 3.0.0
         */
        return (array) apply_filters(
            FilterHooks::FORMS_EMAIL_CONTEXT,
            $context,
            $formId,
            $submissionId,
            $fields
        );
    }

    /**
     * Format one field value for email output.
     *
     * @param mixed $value Raw value.
     * @return string
     * @since 3.0.0
     */
    private function formatFieldValue($value): string
    {
        // Arrays (e.g. multi-select) are joined with commas.
        if (is_array($value)) {
            $parts = array_map(
                static function ($item) {
                    return sanitize_text_field((string) $item);
                },
                $value
            );

            return implode(', ', $parts);
        }

        return sanitize_text_field((string) $value);
    }

    /**
     * Plain-text listing of all submitted fields.
     *
     * @param array<string, mixed> $fields Sanitized fields.
     * @return string
     * @since 3.0.0
     */
    private function formatAllFieldsPlain(array $fields): string
    {
        // Build label: value lines for every submitted field.
        $lines = [];

        foreach ($fields as $name => $value) {
            $label = sanitize_text_field((string) $name);
            $lines[] = $label . ': ' . $this->formatFieldValue($value);
        }

        return implode("\n", $lines);
    }

    /**
     * HTML table of all submitted fields.
     *
     * @param array<string, mixed> $fields Sanitized fields.
     * @return string
     * @since 3.0.0
     */
    private function formatAllFieldsTable(array $fields): string
    {
        // Empty state when no values were submitted.
        if ($fields === []) {
            return '<p style="margin:0;color:#667085;">' . esc_html__('No field values submitted.', 'notifal') . '</p>';
        }

        // Render a simple two-column table for HTML emails.
        $rows = '';

        foreach ($fields as $name => $value) {
            $label = esc_html(sanitize_text_field((string) $name));
            $text = esc_html($this->formatFieldValue($value));
            $rows .= '<tr>'
                . '<td style="padding:10px 12px;border-bottom:1px solid #e4e7ec;font-weight:600;width:35%;vertical-align:top;">'
                . $label
                . '</td>'
                . '<td style="padding:10px 12px;border-bottom:1px solid #e4e7ec;vertical-align:top;">'
                . $text
                . '</td>'
                . '</tr>';
        }

        return '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;border:1px solid #e4e7ec;border-radius:8px;overflow:hidden;">'
            . '<tbody>'
            . $rows
            . '</tbody>'
            . '</table>';
    }
}
