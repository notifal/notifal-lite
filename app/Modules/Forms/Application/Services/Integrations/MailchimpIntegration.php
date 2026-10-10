<?php

namespace Notifal\Modules\Forms\Application\Services\Integrations;

defined('ABSPATH') || exit;

/**
 * Mailchimp list subscribe via server-side API (API key never in browser).
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class MailchimpIntegration
{
    /**
     * Subscribe the designated email field to a Mailchimp audience.
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
        unset($formId, $submissionId);

        if (empty($meta['mailchimp_enabled'])) {
            return;
        }

        $apiKey = isset($meta['mailchimp_api_key']) ? (string) $meta['mailchimp_api_key'] : '';
        $listId = isset($meta['mailchimp_audience_id'])
            ? sanitize_text_field((string) $meta['mailchimp_audience_id'])
            : '';
        $emailField = !empty($meta['mailchimp_email_field'])
            ? sanitize_key((string) $meta['mailchimp_email_field'])
            : 'email';

        if ($apiKey === '' || $listId === '' || empty($fields[$emailField])) {
            return;
        }

        $email = sanitize_email((string) $fields[$emailField]);
        if (!is_email($email)) {
            return;
        }

        $dc = '';
        if (strpos($apiKey, '-') !== false) {
            $parts = explode('-', $apiKey);
            $dc = sanitize_key((string) end($parts));
        }

        if ($dc === '') {
            return;
        }

        $endpoint = sprintf(
            'https://%s.api.mailchimp.com/3.0/lists/%s/members',
            $dc,
            rawurlencode($listId)
        );

        wp_remote_post(
            $endpoint,
            [
                'timeout' => 10,
                'headers' => [
                    'Authorization' => 'apikey ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'email_address' => $email,
                    'status'        => 'subscribed',
                ]),
            ]
        );
    }
}
