<?php

namespace Notifal\Modules\Forms\Infrastructure\WordPress\Ajax;

use Notifal\Core\Foundation\Container;
use Notifal\Domain\Settings\Services\SettingsService;
use Notifal\Modules\Forms\Application\Services\FormSubmitService;
use Notifal\Modules\Forms\Application\Services\FormsSettingsService;
use Notifal\Modules\Forms\Application\Services\FormValidationService;
use Notifal\Modules\Forms\Application\Services\FormEmailService;
use Notifal\Modules\Forms\Application\Services\FormEmailTemplateDefaults;
use Notifal\Modules\Forms\Application\Services\FormEmailVariableService;
use Notifal\Modules\Forms\Application\Services\FormFieldSanitizer;
use Notifal\Modules\Forms\Application\Services\HtmlSchemaParser;
use Notifal\Modules\Forms\Application\Services\Integrations\IntegrationRegistry;
use Notifal\Modules\Forms\Application\Services\Security\CaptchaVerifier;
use Notifal\Modules\Forms\Application\Services\Security\RateLimiter;
use Notifal\Modules\Forms\Application\Services\Security\SchemaTokenService;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormFieldsRepository;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormMetaRepository;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\SubmissionRepository;

defined('ABSPATH') || exit;

/**
 * Public form submit AJAX (logged-in + nopriv).
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormSubmitAjaxController
{
    /**
     * AJAX action name.
     *
     * @var string
     */
    public const AJAX_ACTION = 'notifal_form_submit';

    /**
     * Nonce action.
     *
     * @var string
     */
    public const NONCE_ACTION = 'notifal_form_submit';

    /**
     * Register handlers.
     *
     * @return void
     * @since 3.0.0
     */
    public static function register(): void
    {
        add_action('wp_ajax_' . self::AJAX_ACTION, [self::class, 'handle']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [self::class, 'handle']);
    }

    /**
     * Handle submit request.
     *
     * @return void
     * @since 3.0.0
     */
    public static function handle(): void
    {
        $nonce = isset($_POST['nonce'])
            ? sanitize_text_field(wp_unslash((string) $_POST['nonce']))
            : '';

        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error([
                'message' => __('Unable to submit the form. Please try again.', 'notifal'),
            ]);
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded then validated by schema.
        $fieldsRaw = isset($_POST['fields']) ? wp_unslash($_POST['fields']) : '';
        $fields = [];
        if (is_string($fieldsRaw) && $fieldsRaw !== '') {
            $decoded = json_decode($fieldsRaw, true);
            $fields = is_array($decoded) ? $decoded : [];
        } elseif (is_array($fieldsRaw)) {
            $fields = $fieldsRaw;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Schema JSON validated via signed token fingerprint.
        $schemaJson = isset($_POST['schema_json']) ? (string) wp_unslash($_POST['schema_json']) : '';

        $request = [
            'form_id'         => isset($_POST['form_id']) ? absint(wp_unslash($_POST['form_id'])) : 0,
            'notification_id' => isset($_POST['notification_id']) ? absint(wp_unslash($_POST['notification_id'])) : 0,
            'schema_token'    => isset($_POST['schema_token'])
                ? sanitize_text_field(wp_unslash((string) $_POST['schema_token']))
                : '',
            'schema_json'     => $schemaJson,
            'honeypot'        => isset($_POST['honeypot'])
                ? sanitize_text_field(wp_unslash((string) $_POST['honeypot']))
                : '',
            'captcha_token'   => isset($_POST['captcha_token'])
                ? sanitize_text_field(wp_unslash((string) $_POST['captcha_token']))
                : '',
            'page_url'        => isset($_POST['page_url'])
                ? esc_url_raw(wp_unslash((string) $_POST['page_url']))
                : '',
            'fields'          => $fields,
        ];

        $service = self::resolveSubmitService();
        $result = $service->handle($request);

        if (!empty($result['ok'])) {
            wp_send_json_success([
                'message'  => isset($result['message']) ? $result['message'] : '',
                'redirect' => isset($result['redirect']) ? $result['redirect'] : '',
            ]);
        }

        wp_send_json_error([
            'message' => isset($result['message'])
                ? $result['message']
                : __('Unable to submit the form. Please try again.', 'notifal'),
            // Optional machine code for frontend UX (e.g. duplicate_value).
            'code'    => isset($result['code']) ? sanitize_key((string) $result['code']) : '',
            // Optional field name so the client can highlight the conflicting control.
            'field'   => isset($result['field']) ? sanitize_key((string) $result['field']) : '',
        ]);
    }

    /**
     * Build FormSubmitService with dependencies.
     *
     * @return FormSubmitService
     */
    private static function resolveSubmitService(): FormSubmitService
    {
        $container = Container::getInstance();
        $settingsService = $container->get(SettingsService::class);
        $formsSettings = new FormsSettingsService($settingsService);
        $parser = new HtmlSchemaParser();
        $tokens = new SchemaTokenService();
        $validator = new FormValidationService();
        $rateLimiter = new RateLimiter();
        $captcha = new CaptchaVerifier($formsSettings);
        $submissions = new SubmissionRepository();
        $emails = new FormEmailService(
            $formsSettings,
            new FormEmailTemplateDefaults(),
            new FormEmailVariableService()
        );
        $integrations = new IntegrationRegistry();
        $formMeta = new FormMetaRepository();
        $formFields = new FormFieldsRepository(new FormFieldSanitizer());

        return new FormSubmitService(
            $formsSettings,
            $parser,
            $tokens,
            $validator,
            $rateLimiter,
            $captcha,
            $submissions,
            $emails,
            $integrations,
            $formMeta,
            $formFields
        );
    }
}
