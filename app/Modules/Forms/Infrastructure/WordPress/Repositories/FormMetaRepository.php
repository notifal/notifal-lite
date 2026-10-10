<?php

namespace Notifal\Modules\Forms\Infrastructure\WordPress\Repositories;

use Notifal\Modules\Forms\Application\Services\FormEmailTemplateDefaults;
use Notifal\Modules\Forms\Application\Services\FormPresentationSettings;

defined('ABSPATH') || exit;

/**
 * Form CPT meta for secrets, emails, integrations, and presentation defaults.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormMetaRepository
{
    /**
     * Post meta key.
     *
     * @var string
     */
    public const META_KEY = '_notifal_form_settings';

    /**
     * Default meta structure.
     *
     * @return array<string, mixed>
     * @since 3.0.0
     */
    public function getDefaults(): array
    {
        return array_merge(
            [
                'notes'                       => '',
                'admin_email_enabled'         => true,
                'admin_email'                 => '',
                'admin_email_subject'         => '',
                'admin_email_body'            => '',
                'admin_email_format'          => 'plain',
                'confirmation_email_enabled'  => false,
                'confirmation_email_subject'  => '',
                'confirmation_email_body'     => '',
                'confirmation_email_format'   => 'plain',
                'submitter_email_field'       => 'email',
                'webhook_enabled'             => false,
                'webhook_url'                 => '',
                'webhook_auth_header'         => '',
                'mailchimp_enabled'           => false,
                'mailchimp_api_key'           => '',
                'mailchimp_audience_id'       => '',
                'mailchimp_email_field'       => 'email',
            ],
            // Presentation mirrors HTML Builder form-root attrs.
            FormPresentationSettings::getDefaults()
        );
    }

    /**
     * Get meta for a form CPT.
     *
     * @param int $formId Form post ID.
     * @return array<string, mixed>
     * @since 3.0.0
     */
    public function get(int $formId): array
    {
        if ($formId <= 0) {
            return $this->getDefaults();
        }

        $stored = get_post_meta($formId, self::META_KEY, true);
        if (!is_array($stored)) {
            return $this->getDefaults();
        }

        return array_merge($this->getDefaults(), $stored);
    }

    /**
     * Public-safe meta for admin UI (secrets masked).
     *
     * @param int $formId Form ID.
     * @return array<string, mixed>
     * @since 3.0.0
     */
    public function getForAdmin(int $formId): array
    {
        $meta = $this->get($formId);
        $meta['webhook_auth_header_set'] = $meta['webhook_auth_header'] !== '';
        $meta['mailchimp_api_key_set'] = $meta['mailchimp_api_key'] !== '';
        $meta['webhook_auth_header'] = '';
        $meta['mailchimp_api_key'] = '';

        return $meta;
    }

    /**
     * Presentation slice used by the HTML Builder connect / sync flow.
     *
     * @param int $formId Form ID.
     * @return array<string, mixed>
     * @since 3.0.0
     */
    public function getPresentation(int $formId): array
    {
        return FormPresentationSettings::sanitize(
            FormPresentationSettings::pick($this->get($formId))
        );
    }

    /**
     * Merge presentation defaults without touching email / integration secrets.
     *
     * @param int                  $formId       Form ID.
     * @param array<string, mixed> $presentation Presentation payload.
     * @return array<string, mixed> Updated presentation slice.
     * @since 3.0.0
     */
    public function savePresentation(int $formId, array $presentation): array
    {
        $existing = $this->get($formId);
        $sanitized = FormPresentationSettings::sanitize($presentation, $existing);
        $meta = array_merge($existing, $sanitized);
        update_post_meta($formId, self::META_KEY, $meta);

        return $sanitized;
    }

    /**
     * Sanitize and save meta. Empty secret fields keep existing unless clear flags set.
     *
     * @param int                  $formId Form ID.
     * @param array<string, mixed> $input  Raw input.
     * @return array<string, mixed> Saved meta.
     * @since 3.0.0
     */
    public function save(int $formId, array $input): array
    {
        $existing = $this->get($formId);
        $meta = $this->getDefaults();

        $meta['notes'] = isset($input['notes'])
            ? sanitize_textarea_field(wp_unslash((string) $input['notes']))
            : '';

        $meta['admin_email_enabled'] = !empty($input['admin_email_enabled']);

        $adminEmailRaw = isset($input['admin_email'])
            ? wp_unslash((string) $input['admin_email'])
            : '';
        $meta['admin_email'] = $this->sanitizeRecipientList($adminEmailRaw);

        $meta['admin_email_subject'] = isset($input['admin_email_subject'])
            ? sanitize_text_field(wp_unslash((string) $input['admin_email_subject']))
            : '';

        $adminFormat = isset($input['admin_email_format'])
            ? sanitize_key(wp_unslash((string) $input['admin_email_format']))
            : 'plain';
        $meta['admin_email_format'] = (new FormEmailTemplateDefaults())->normalizeFormat($adminFormat);

        $adminBodyRaw = isset($input['admin_email_body'])
            ? wp_unslash((string) $input['admin_email_body'])
            : '';
        $meta['admin_email_body'] = $meta['admin_email_format'] === 'html'
            ? wp_kses_post($adminBodyRaw)
            : sanitize_textarea_field($adminBodyRaw);

        $meta['confirmation_email_enabled'] = !empty($input['confirmation_email_enabled']);
        $meta['confirmation_email_subject'] = isset($input['confirmation_email_subject'])
            ? sanitize_text_field(wp_unslash((string) $input['confirmation_email_subject']))
            : '';

        $visitorFormat = isset($input['confirmation_email_format'])
            ? sanitize_key(wp_unslash((string) $input['confirmation_email_format']))
            : 'plain';
        $meta['confirmation_email_format'] = (new FormEmailTemplateDefaults())->normalizeFormat($visitorFormat);

        $visitorBodyRaw = isset($input['confirmation_email_body'])
            ? wp_unslash((string) $input['confirmation_email_body'])
            : '';
        $meta['confirmation_email_body'] = $meta['confirmation_email_format'] === 'html'
            ? wp_kses_post($visitorBodyRaw)
            : sanitize_textarea_field($visitorBodyRaw);
        $meta['submitter_email_field'] = isset($input['submitter_email_field'])
            ? sanitize_key(wp_unslash((string) $input['submitter_email_field']))
            : 'email';

        $meta['webhook_enabled'] = !empty($input['webhook_enabled']);
        $webhookUrl = isset($input['webhook_url'])
            ? esc_url_raw(wp_unslash((string) $input['webhook_url']), ['https'])
            : '';
        $meta['webhook_url'] = $webhookUrl;

        $clearWebhookAuth = !empty($input['webhook_auth_header_clear']);
        $webhookAuth = isset($input['webhook_auth_header'])
            ? sanitize_text_field(wp_unslash((string) $input['webhook_auth_header']))
            : '';
        if ($clearWebhookAuth) {
            $meta['webhook_auth_header'] = '';
        } elseif ($webhookAuth !== '') {
            $meta['webhook_auth_header'] = $webhookAuth;
        } else {
            $meta['webhook_auth_header'] = $existing['webhook_auth_header'];
        }

        $meta['mailchimp_enabled'] = !empty($input['mailchimp_enabled']);
        $meta['mailchimp_audience_id'] = isset($input['mailchimp_audience_id'])
            ? sanitize_text_field(wp_unslash((string) $input['mailchimp_audience_id']))
            : '';
        $meta['mailchimp_email_field'] = isset($input['mailchimp_email_field'])
            ? sanitize_key(wp_unslash((string) $input['mailchimp_email_field']))
            : 'email';

        $clearMc = !empty($input['mailchimp_api_key_clear']);
        $mcKey = isset($input['mailchimp_api_key'])
            ? sanitize_text_field(wp_unslash((string) $input['mailchimp_api_key']))
            : '';
        if ($clearMc) {
            $meta['mailchimp_api_key'] = '';
        } elseif ($mcKey !== '') {
            $meta['mailchimp_api_key'] = $mcKey;
        } else {
            $meta['mailchimp_api_key'] = $existing['mailchimp_api_key'];
        }

        // Keep presentation when builder create omits those fields; update when present.
        $presentation = FormPresentationSettings::sanitize($input, $existing);
        $isAdminPresentation = $this->isFullAdminPresentationSave($input);

        foreach (FormPresentationSettings::getKeys() as $key) {
            // Checkbox is omitted when unchecked on the Form edit screen.
            if ($key === 'multi_step' && !array_key_exists('multi_step', $input) && $isAdminPresentation) {
                $meta['multi_step'] = false;
                continue;
            }

            if (array_key_exists($key, $input) || $isAdminPresentation) {
                $meta[$key] = $presentation[$key];
            } else {
                $meta[$key] = $existing[$key];
            }
        }

        update_post_meta($formId, self::META_KEY, $meta);

        return $meta;
    }

    /**
     * Whether the request looks like the full Form edit screen save.
     *
     * @param array<string, mixed> $input Raw input.
     * @return bool
     */
    private function isFullAdminPresentationSave(array $input): bool
    {
        return array_key_exists('success_behavior', $input)
            || array_key_exists('success_message', $input)
            || array_key_exists('primary_color', $input);
    }

    /**
     * Sanitize a comma-separated list of email addresses.
     *
     * @param string $raw Raw recipient string.
     * @return string Valid emails joined by comma, or empty string.
     * @since 3.0.0
     */
    public function sanitizeRecipientList(string $raw): string
    {
        $parts = preg_split('/[;,]+/', $raw);
        if (!is_array($parts)) {
            return '';
        }

        $valid = [];
        foreach ($parts as $part) {
            $email = sanitize_email(trim((string) $part));
            if ($email !== '' && is_email($email) && !in_array($email, $valid, true)) {
                $valid[] = $email;
            }
        }

        return implode(', ', $valid);
    }
}
