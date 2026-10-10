<?php

namespace Notifal\Modules\Forms\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Default admin and visitor email templates (HTML and plain text).
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormEmailTemplateDefaults
{
    /**
     * Allowed email body formats.
     *
     * @var string[]
     */
    public const FORMATS = ['plain', 'html'];

    /**
     * Default admin notification subject.
     *
     * @return string
     * @since 3.0.0
     */
    public function getAdminSubject(): string
    {
        // Default subject uses a merge tag replaced at send time.
        return __('New submission: {form_title}', 'notifal');
    }

    /**
     * Default visitor confirmation subject.
     *
     * @return string
     * @since 3.0.0
     */
    public function getVisitorSubject(): string
    {
        return __('We received your message - {site_name}', 'notifal');
    }

    /**
     * Default admin body for the requested format.
     *
     * @param string $format plain|html.
     * @return string
     * @since 3.0.0
     */
    public function getAdminBody(string $format): string
    {
        // Normalize format to a supported value.
        $format = $this->normalizeFormat($format);

        // Build the default template for the selected format.
        if ($format === 'html') {
            $template = $this->getAdminHtmlBody();
        } else {
            $template = $this->getAdminPlainBody();
        }

        /**
         * Filters the default admin notification email body template.
         *
         * @param string $template Default body.
         * @param string $format   plain|html.
         * @since 3.0.0
         */
        return (string) apply_filters(FilterHooks::FORMS_EMAIL_ADMIN_DEFAULT_BODY, $template, $format);
    }

    /**
     * Default visitor body for the requested format.
     *
     * @param string $format plain|html.
     * @return string
     * @since 3.0.0
     */
    public function getVisitorBody(string $format): string
    {
        // Normalize format to a supported value.
        $format = $this->normalizeFormat($format);

        // Build the default template for the selected format.
        if ($format === 'html') {
            $template = $this->getVisitorHtmlBody();
        } else {
            $template = $this->getVisitorPlainBody();
        }

        /**
         * Filters the default visitor confirmation email body template.
         *
         * @param string $template Default body.
         * @param string $format   plain|html.
         * @since 3.0.0
         */
        return (string) apply_filters(FilterHooks::FORMS_EMAIL_VISITOR_DEFAULT_BODY, $template, $format);
    }

    /**
     * Bundle defaults for the admin UI (subjects + both formats).
     *
     * @return array<string, mixed>
     * @since 3.0.0
     */
    public function getForAdminUi(): array
    {
        return [
            'admin' => [
                'subject' => $this->getAdminSubject(),
                'plain'   => $this->getAdminBody('plain'),
                'html'    => $this->getAdminBody('html'),
            ],
            'visitor' => [
                'subject' => $this->getVisitorSubject(),
                'plain'   => $this->getVisitorBody('plain'),
                'html'    => $this->getVisitorBody('html'),
            ],
        ];
    }

    /**
     * Normalize stored format value.
     *
     * @param string $format Raw format.
     * @return string plain|html
     * @since 3.0.0
     */
    public function normalizeFormat(string $format): string
    {
        // Only html and plain are valid; default to plain.
        $format = sanitize_key($format);

        return in_array($format, self::FORMATS, true) ? $format : 'plain';
    }

    /**
     * Plain-text admin notification template.
     *
     * @return string
     * @since 3.0.0
     */
    private function getAdminPlainBody(): string
    {
        $lines = [
            __('You have a new form submission on {site_name}.', 'notifal'),
            '',
            __('Form: {form_title} (ID: {form_id})', 'notifal'),
            __('Submission ID: {submission_id}', 'notifal'),
            __('Submitted: {submission_date} {submission_time}', 'notifal'),
            '',
            __('--- Submission details ---', 'notifal'),
            '',
            '{all_fields}',
            '',
            __('View site: {site_url}', 'notifal'),
        ];

        return implode("\n", $lines);
    }

    /**
     * HTML admin notification template.
     *
     * @return string
     * @since 3.0.0
     */
    private function getAdminHtmlBody(): string
    {
        return implode(
            "\n",
            [
                '<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;line-height:1.6;color:#101828;max-width:600px;margin:0 auto;">',
                '  <div style="background:#7e2bd2;color:#ffffff;padding:20px 24px;border-radius:12px 12px 0 0;">',
                '    <h1 style="margin:0;font-size:20px;font-weight:700;">' . esc_html__('New form submission', 'notifal') . '</h1>',
                '    <p style="margin:8px 0 0;opacity:0.92;font-size:14px;">{form_title}</p>',
                '  </div>',
                '  <div style="border:1px solid #e4e7ec;border-top:none;padding:24px;border-radius:0 0 12px 12px;background:#ffffff;">',
                '    <p style="margin:0 0 16px;">' . esc_html__('You received a new submission on {site_name}.', 'notifal') . '</p>',
                '    {all_fields_table}',
                '    <p style="margin:24px 0 0;font-size:13px;color:#667085;">',
                '      ' . esc_html__('Submission #{submission_id} · {submission_date} at {submission_time}', 'notifal') . '<br>',
                '      ' . esc_html__('Form ID: {form_id}', 'notifal') . ' · <a href="{site_url}" style="color:#7e2bd2;">{site_url}</a>',
                '    </p>',
                '  </div>',
                '</div>',
            ]
        );
    }

    /**
     * Plain-text visitor confirmation template.
     *
     * @return string
     * @since 3.0.0
     */
    private function getVisitorPlainBody(): string
    {
        $lines = [
            __('Hi,', 'notifal'),
            '',
            __('Thank you for contacting {site_name}. We received your submission and will get back to you soon.', 'notifal'),
            '',
            '{site_url}',
        ];

        return implode("\n", $lines);
    }

    /**
     * HTML visitor confirmation template.
     *
     * @return string
     * @since 3.0.0
     */
    private function getVisitorHtmlBody(): string
    {
        return implode(
            "\n",
            [
                '<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;line-height:1.6;color:#101828;max-width:600px;margin:0 auto;padding:24px;">',
                '  <h1 style="margin:0 0 16px;font-size:22px;font-weight:700;color:#101828;">' . esc_html__('Thank you!', 'notifal') . '</h1>',
                '  <p style="margin:0 0 16px;">' . esc_html__('Hi,', 'notifal') . '</p>',
                '  <p style="margin:0 0 16px;">' . esc_html__('Thank you for contacting {site_name}. We received your submission and will get back to you soon.', 'notifal') . '</p>',
                '  <p style="margin:0 0 24px;font-size:14px;color:#667085;">' . esc_html__('If you did not submit this form, you can ignore this email.', 'notifal') . '</p>',
                '  <p style="margin:0;font-size:13px;"><a href="{site_url}" style="color:#7e2bd2;text-decoration:none;">{site_url}</a></p>',
                '</div>',
            ]
        );
    }
}
