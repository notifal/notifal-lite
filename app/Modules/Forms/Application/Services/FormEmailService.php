<?php

namespace Notifal\Modules\Forms\Application\Services;

use Notifal\Modules\Forms\Application\Services\FormsSettingsService;

defined('ABSPATH') || exit;

/**
 * Admin and submitter confirmation emails for form submissions.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormEmailService
{
    /**
     * @var FormsSettingsService
     */
    private $settings;

    /**
     * @var FormEmailTemplateDefaults
     */
    private $defaults;

    /**
     * @var FormEmailVariableService
     */
    private $variables;

    /**
     * @param FormsSettingsService      $settings  Settings.
     * @param FormEmailTemplateDefaults $defaults  Default templates.
     * @param FormEmailVariableService  $variables Merge tag service.
     * @since 3.0.0
     */
    public function __construct(
        FormsSettingsService $settings,
        FormEmailTemplateDefaults $defaults,
        FormEmailVariableService $variables
    ) {
        // Store collaborators used when rendering outbound emails.
        $this->settings = $settings;
        $this->defaults = $defaults;
        $this->variables = $variables;
    }

    /**
     * Send admin notification email.
     *
     * @param int                  $formId       Form CPT ID.
     * @param int                  $submissionId Submission ID.
     * @param array<string, mixed> $fields       Sanitized fields.
     * @param array<string, mixed> $meta         Form CPT meta.
     * @param array<string, mixed> $schema       Schema.
     * @return void
     * @since 3.0.0
     */
    public function sendAdminNotification(
        int $formId,
        int $submissionId,
        array $fields,
        array $meta,
        array $schema
    ): void {
        // Respect the per-form enable switch (default on for older meta).
        if (isset($meta['admin_email_enabled']) && empty($meta['admin_email_enabled'])) {
            return;
        }

        // Resolve recipients from form meta or global default.
        $recipients = $this->resolveAdminRecipients($meta);
        if ($recipients === []) {
            return;
        }

        // Primary address used for merge tags (first recipient).
        $primaryRecipient = $recipients[0];

        // Determine subject, format, and body template.
        $format = $this->defaults->normalizeFormat((string) ($meta['admin_email_format'] ?? 'plain'));
        $subjectTemplate = !empty($meta['admin_email_subject'])
            ? (string) $meta['admin_email_subject']
            : $this->defaults->getAdminSubject();
        $bodyTemplate = !empty($meta['admin_email_body'])
            ? (string) $meta['admin_email_body']
            : $this->defaults->getAdminBody($format);

        // Build merge tag context for this submission.
        $formTitle = get_the_title($formId);
        $context = $this->variables->buildContext(
            $formId,
            $submissionId,
            $fields,
            is_string($formTitle) ? $formTitle : '',
            $primaryRecipient
        );

        // Render subject and body after tag replacement.
        $subject = $this->sanitizeSubject(
            $this->variables->replace($subjectTemplate, $context)
        );
        $body = $this->variables->replace($bodyTemplate, $context);

        // WordPress accepts comma-separated recipients in the To field.
        $to = implode(', ', $recipients);
        $this->dispatch($to, $subject, $body, $format);
    }

    /**
     * Optional confirmation email to submitter from designated email field.
     *
     * @param int                  $formId       Form CPT ID.
     * @param int                  $submissionId Submission ID.
     * @param array<string, mixed> $fields       Sanitized fields.
     * @param array<string, mixed> $meta         Form meta.
     * @param array<string, mixed> $schema       Schema.
     * @return void
     * @since 3.0.0
     */
    public function sendSubmitterConfirmation(
        int $formId,
        int $submissionId,
        array $fields,
        array $meta,
        array $schema
    ): void {
        // Visitor emails are optional per form settings.
        if (empty($meta['confirmation_email_enabled'])) {
            return;
        }

        // Resolve which submitted field holds the visitor email address.
        $fieldName = !empty($schema['submitter_email_field'])
            ? (string) $schema['submitter_email_field']
            : (isset($meta['submitter_email_field']) ? (string) $meta['submitter_email_field'] : 'email');

        if ($fieldName === '' || empty($fields[$fieldName]) || !is_email((string) $fields[$fieldName])) {
            return;
        }

        // Prepare recipient and template settings.
        $to = sanitize_email((string) $fields[$fieldName]);
        $format = $this->defaults->normalizeFormat((string) ($meta['confirmation_email_format'] ?? 'plain'));
        $subjectTemplate = !empty($meta['confirmation_email_subject'])
            ? (string) $meta['confirmation_email_subject']
            : $this->defaults->getVisitorSubject();
        $bodyTemplate = !empty($meta['confirmation_email_body'])
            ? (string) $meta['confirmation_email_body']
            : $this->defaults->getVisitorBody($format);

        // Build merge tag context (admin email tag uses first admin recipient).
        $adminRecipients = $this->resolveAdminRecipients($meta);
        $adminEmail = $adminRecipients !== [] ? $adminRecipients[0] : $this->settings->getDefaultAdminEmail();
        $formTitle = get_the_title($formId);
        $context = $this->variables->buildContext(
            $formId,
            $submissionId,
            $fields,
            is_string($formTitle) ? $formTitle : '',
            $adminEmail
        );

        // Render and send the confirmation message.
        $subject = $this->sanitizeSubject(
            $this->variables->replace($subjectTemplate, $context)
        );
        $body = $this->variables->replace($bodyTemplate, $context);

        $this->dispatch($to, $subject, $body, $format);
    }

    /**
     * Resolve valid admin notification recipients.
     *
     * @param array<string, mixed> $meta Form meta.
     * @return string[]
     * @since 3.0.0
     */
    private function resolveAdminRecipients(array $meta): array
    {
        $raw = isset($meta['admin_email']) ? (string) $meta['admin_email'] : '';

        // Fall back to global Forms settings when the form has no recipients.
        if (trim($raw) === '') {
            $fallback = $this->settings->getDefaultAdminEmail();
            return ($fallback !== '' && is_email($fallback)) ? [$fallback] : [];
        }

        // Split stored comma/semicolon-separated list and keep only valid emails.
        $parts = preg_split('/[;,]+/', $raw);
        if (!is_array($parts)) {
            return [];
        }

        $recipients = [];
        foreach ($parts as $part) {
            $email = sanitize_email(trim((string) $part));
            if ($email !== '' && is_email($email) && !in_array($email, $recipients, true)) {
                $recipients[] = $email;
            }
        }

        return $recipients;
    }

    /**
     * Send email with the correct content type headers.
     *
     * @param string $to      Recipient (comma-separated allowed).
     * @param string $subject Subject line.
     * @param string $body    Message body.
     * @param string $format  plain|html.
     * @return void
     * @since 3.0.0
     */
    private function dispatch(string $to, string $subject, string $body, string $format): void
    {
        // Default headers; HTML adds a content type.
        $headers = [];

        if ($format === 'html') {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }

        wp_mail($to, $subject, $body, $headers);
    }

    /**
     * Strip CR/LF from subject lines to prevent header injection.
     *
     * @param string $value Raw subject.
     * @return string
     * @since 3.0.0
     */
    private function sanitizeSubject(string $value): string
    {
        return str_replace(["\r", "\n", "%0a", "%0d", "%0A", "%0D"], '', $value);
    }
}
