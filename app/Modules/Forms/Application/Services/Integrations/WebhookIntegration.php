<?php

namespace Notifal\Modules\Forms\Application\Services\Integrations;

defined('ABSPATH') || exit;

/**
 * HTTPS webhook delivery with SSRF guards.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class WebhookIntegration
{
    /**
     * Send submission fields to the configured webhook URL.
     *
     * @param int                  $formId       Form ID.
     * @param int                  $submissionId Submission ID.
     * @param array<string, mixed> $fields       Fields.
     * @param array<string, mixed> $meta         Form meta.
     * @return void
     * @since 3.0.0
     */
    public function send(int $formId, int $submissionId, array $fields, array $meta): void
    {
        if (empty($meta['webhook_enabled'])) {
            return;
        }

        $url = isset($meta['webhook_url']) ? SsrfGuard::sanitizeUrl((string) $meta['webhook_url']) : '';
        if ($url === '') {
            return;
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        // Optional auth header from CPT secret (never from HTML).
        if (!empty($meta['webhook_auth_header']) && is_string($meta['webhook_auth_header'])) {
            $headers['Authorization'] = sanitize_text_field($meta['webhook_auth_header']);
        }

        $payload = [
            'form_id'       => $formId,
            'submission_id' => $submissionId,
            'fields'        => $fields,
        ];

        wp_remote_post(
            $url,
            [
                'timeout'     => 8,
                'redirection' => 0,
                'headers'     => $headers,
                'body'        => wp_json_encode($payload),
                'data_format' => 'body',
            ]
        );
    }
}
