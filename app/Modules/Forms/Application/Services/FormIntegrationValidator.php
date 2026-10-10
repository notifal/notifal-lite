<?php

namespace Notifal\Modules\Forms\Application\Services;

use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormMetaRepository;
use RuntimeException;

defined('ABSPATH') || exit;

/**
 * Validates integration settings when an integration toggle is enabled.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormIntegrationValidator
{
    /**
     * @var FormMetaRepository
     */
    private $meta;

    /**
     * @param FormMetaRepository $meta Meta repository.
     * @since 3.0.0
     */
    public function __construct(FormMetaRepository $meta)
    {
        // Store repository used to read saved secrets on update.
        $this->meta = $meta;
    }

    /**
     * Validate integration payload before persisting a form.
     *
     * @param array<string, mixed>   $input      Raw request data.
     * @param int                    $formId     Existing form ID (0 on create).
     * @param array<int, string>     $fieldNames Sanitized field machine names.
     * @return void
     * @throws RuntimeException When a required integration setting is missing.
     * @since 3.0.0
     */
    public function validate(array $input, int $formId, array $fieldNames): void
    {
        // Load stored secrets so blank password fields can keep saved values.
        $existing = $formId > 0 ? $this->meta->get($formId) : [];

        // Webhook requires a valid HTTPS endpoint when enabled.
        if (!empty($input['webhook_enabled'])) {
            $this->validateWebhook($input);
        }

        // Mailchimp requires audience, email field mapping, and an API key when enabled.
        if (!empty($input['mailchimp_enabled'])) {
            $this->validateMailchimp($input, $existing, $fieldNames);
        }
    }

    /**
     * Validate webhook URL when the integration is enabled.
     *
     * @param array<string, mixed> $input Raw request data.
     * @return void
     * @throws RuntimeException When webhook URL is missing or not HTTPS.
     * @since 3.0.0
     */
    private function validateWebhook(array $input): void
    {
        // Read and normalize the webhook URL from the request.
        $url = isset($input['webhook_url'])
            ? esc_url_raw(wp_unslash((string) $input['webhook_url']), ['https'])
            : '';

        // Enabled integrations must point to a secure endpoint.
        if ($url === '' || stripos($url, 'https://') !== 0) {
            throw new RuntimeException(
                __(
                    'Webhook is enabled. Add an HTTPS webhook URL or turn the integration off.',
                    'notifal'
                )
            );
        }
    }

    /**
     * Validate Mailchimp settings when the integration is enabled.
     *
     * @param array<string, mixed> $input      Raw request data.
     * @param array<string, mixed> $existing   Stored meta (includes saved API key).
     * @param array<int, string>   $fieldNames Sanitized field machine names.
     * @return void
     * @throws RuntimeException When a required Mailchimp setting is missing.
     * @since 3.0.0
     */
    private function validateMailchimp(array $input, array $existing, array $fieldNames): void
    {
        // Audience / list ID is always required when Mailchimp is on.
        $audienceId = isset($input['mailchimp_audience_id'])
            ? sanitize_text_field(wp_unslash((string) $input['mailchimp_audience_id']))
            : '';

        if ($audienceId === '') {
            throw new RuntimeException(
                __(
                    'Mailchimp is enabled. Add an audience ID or turn the integration off.',
                    'notifal'
                )
            );
        }

        // Email field must map to an existing form field name.
        $emailField = isset($input['mailchimp_email_field'])
            ? sanitize_key(wp_unslash((string) $input['mailchimp_email_field']))
            : '';

        if ($emailField === '' || !in_array($emailField, $fieldNames, true)) {
            throw new RuntimeException(
                __(
                    'Mailchimp is enabled. Choose an email field from the Fields tab or turn the integration off.',
                    'notifal'
                )
            );
        }

        // Accept a newly entered key or a previously saved key (blank input keeps existing).
        $incomingKey = isset($input['mailchimp_api_key'])
            ? sanitize_text_field(wp_unslash((string) $input['mailchimp_api_key']))
            : '';
        $clearKey = !empty($input['mailchimp_api_key_clear']);
        $savedKey = isset($existing['mailchimp_api_key']) ? (string) $existing['mailchimp_api_key'] : '';
        $hasKey = ($incomingKey !== '' || ($savedKey !== '' && !$clearKey));

        if (!$hasKey) {
            throw new RuntimeException(
                __(
                    'Mailchimp is enabled. Add an API key or turn the integration off.',
                    'notifal'
                )
            );
        }
    }
}
