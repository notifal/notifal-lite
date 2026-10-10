<?php

namespace Notifal\Modules\Forms\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Forms\Application\Services\Integrations\IntegrationRegistry;
use Notifal\Modules\Forms\Application\Services\Security\CaptchaVerifier;
use Notifal\Modules\Forms\Application\Services\Security\RateLimiter;
use Notifal\Modules\Forms\Application\Services\Security\RedirectAllowlist;
use Notifal\Modules\Forms\Application\Services\Security\SchemaTokenService;
use Notifal\Modules\Forms\Config\AttributeContract;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormFieldsRepository;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormMetaRepository;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\SubmissionRepository;

defined('ABSPATH') || exit;

/**
 * Orchestrates the public form submit security pipeline and persistence.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormSubmitService
{
    /**
     * @var FormsSettingsService
     */
    private $settings;

    /**
     * @var HtmlSchemaParser
     */
    private $parser;

    /**
     * @var SchemaTokenService
     */
    private $tokens;

    /**
     * @var FormValidationService
     */
    private $validator;

    /**
     * @var RateLimiter
     */
    private $rateLimiter;

    /**
     * @var CaptchaVerifier
     */
    private $captcha;

    /**
     * @var SubmissionRepository
     */
    private $submissions;

    /**
     * @var FormEmailService
     */
    private $emails;

    /**
     * @var IntegrationRegistry
     */
    private $integrations;

    /**
     * @var FormMetaRepository
     */
    private $formMeta;

    /**
     * @var FormFieldsRepository
     */
    private $formFields;

    /**
     * @param FormsSettingsService  $settings      Settings.
     * @param HtmlSchemaParser      $parser        Schema parser.
     * @param SchemaTokenService    $tokens        Token service.
     * @param FormValidationService $validator     Validator.
     * @param RateLimiter           $rateLimiter   Rate limiter.
     * @param CaptchaVerifier       $captcha       Captcha verifier.
     * @param SubmissionRepository  $submissions   Submission store.
     * @param FormEmailService      $emails        Email service.
     * @param IntegrationRegistry   $integrations  Integrations.
     * @param FormMetaRepository    $formMeta      Form CPT meta.
     * @param FormFieldsRepository  $formFields    Form CPT field definitions.
     * @since 3.0.0
     */
    public function __construct(
        FormsSettingsService $settings,
        HtmlSchemaParser $parser,
        SchemaTokenService $tokens,
        FormValidationService $validator,
        RateLimiter $rateLimiter,
        CaptchaVerifier $captcha,
        SubmissionRepository $submissions,
        FormEmailService $emails,
        IntegrationRegistry $integrations,
        FormMetaRepository $formMeta,
        FormFieldsRepository $formFields
    ) {
        $this->settings = $settings;
        $this->parser = $parser;
        $this->tokens = $tokens;
        $this->validator = $validator;
        $this->rateLimiter = $rateLimiter;
        $this->captcha = $captcha;
        $this->submissions = $submissions;
        $this->emails = $emails;
        $this->integrations = $integrations;
        $this->formMeta = $formMeta;
        $this->formFields = $formFields;
    }

    /**
     * Process a public submit request.
     *
     * @param array<string, mixed> $request Sanitized request bag.
     * @return array{ok: bool, fake_success?: bool, message: string, redirect?: string, submission_id?: int, code?: string, field?: string}
     * @since 3.0.0
     */
    public function handle(array $request): array
    {
        // 1. Kill switch.
        if (!$this->settings->isEnabled()) {
            return $this->genericError();
        }

        $formId = isset($request['form_id']) ? absint($request['form_id']) : 0;
        $notificationId = isset($request['notification_id']) ? absint($request['notification_id']) : 0;
        $schemaJson = isset($request['schema_json']) ? (string) $request['schema_json'] : '';
        $token = isset($request['schema_token']) ? (string) $request['schema_token'] : '';
        $honeypot = isset($request['honeypot']) ? (string) $request['honeypot'] : '';
        $captchaToken = isset($request['captcha_token']) ? (string) $request['captcha_token'] : '';
        $fields = isset($request['fields']) && is_array($request['fields']) ? $request['fields'] : [];

        // 3. Honeypot: fake success, store nothing.
        if ($this->settings->isHoneypotEnabled() && $honeypot !== '') {
            return [
                'ok'           => true,
                'fake_success' => true,
                'message'      => __('Thank you.', 'notifal'),
            ];
        }

        // Schema authority from signed snapshot (preferred) or trusted re-parse later.
        $schema = json_decode($schemaJson, true);
        if (!is_array($schema)) {
            return $this->genericError();
        }

        $fingerprint = $this->parser->fingerprint($schema);
        $expectedFormId = isset($schema['form_id']) ? absint($schema['form_id']) : $formId;

        // 2. HMAC token + identity match.
        if (!$this->tokens->verify($token, $expectedFormId, $notificationId, $fingerprint)) {
            return $this->genericError();
        }

        // 4. Rate limit.
        if (!$this->rateLimiter->isAllowed(
            $expectedFormId,
            $this->settings->getRateLimitMax(),
            $this->settings->getRateLimitWindowSeconds()
        )) {
            return $this->genericError(__('Too many submissions. Please try again later.', 'notifal'));
        }

        // 5. Captcha (fail closed when required).
        $captchaMode = isset($schema['captcha']) ? (string) $schema['captcha'] : 'inherit';
        if (!$this->captcha->verify($captchaToken, $captchaMode)) {
            return $this->genericError(__('Captcha verification failed.', 'notifal'));
        }

        // Allow extensions to block submit before fields are mutated or validated.
        $allow = FormsHookDispatcher::filterSubmitAllow($schema, $fields);
        if ($allow !== true) {
            return FormsHookDispatcher::finalizeSubmitError((array) $allow, $schema, ['stage' => 'allow']);
        }

        // Filter raw submitted values before validation (global + form-scoped).
        $fields = FormsHookDispatcher::filterSubmitFields($fields, $schema);

        /**
         * Fires before validated form fields are stored.
         *
         * @param array $schema Schema.
         * @param array $fields Raw fields.
         * @since 3.0.0
         */
        FormsHookDispatcher::doSubmitBefore($schema, $fields);

        // 6–10. Schema validation, allowlist, type sanitize, regex, conditionals.
        $result = $this->validator->validate($schema, $fields);
        if (!$result['valid']) {
            $firstError = isset($result['errors'][0]) ? sanitize_key((string) $result['errors'][0]) : '';

            return FormsHookDispatcher::finalizeSubmitError(
                [
                    'ok'      => false,
                    'message' => __('Please check your form fields and try again.', 'notifal'),
                    'code'    => 'validation_failed',
                    'field'   => $firstError !== 'payload' ? $firstError : '',
                ],
                $schema,
                [
                    'stage'      => 'validation',
                    'validation' => $result,
                ]
            );
        }

        // Reject duplicate values for fields marked unique on the Form CPT (or schema).
        $uniqueViolation = $this->findUniqueViolation(
            $expectedFormId,
            isset($schema['fields']) && is_array($schema['fields']) ? $schema['fields'] : [],
            $result['fields']
        );
        if ($uniqueViolation !== null) {
            return FormsHookDispatcher::finalizeSubmitError(
                $uniqueViolation,
                $schema,
                ['stage' => 'unique']
            );
        }

        $pageUrl = isset($request['page_url']) ? esc_url_raw((string) $request['page_url']) : '';
        $userAgent = isset($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_USER_AGENT']))
            : '';

        $submissionData = FormsHookDispatcher::filterSubmissionData(
            [
                'form_id'         => $expectedFormId,
                'notification_id' => $notificationId,
                'status'          => 'new',
                'ip_hash'         => $this->rateLimiter->getIpHash(),
                'user_agent'      => $userAgent,
                'page_url'        => $pageUrl,
                'fields'          => $result['fields'],
            ],
            $schema,
            $result['fields']
        );

        $submissionId = $this->submissions->create($submissionData);

        $this->rateLimiter->hit($expectedFormId, $this->settings->getRateLimitWindowSeconds());

        // Emails + integrations use CPT secrets only.
        $meta = $this->formMeta->get($expectedFormId);
        $this->emails->sendAdminNotification($expectedFormId, $submissionId, $result['fields'], $meta, $schema);
        $this->emails->sendSubmitterConfirmation(
            $expectedFormId,
            $submissionId,
            $result['fields'],
            $meta,
            $schema
        );
        $this->integrations->dispatch($expectedFormId, $submissionId, $result['fields'], $meta);

        /**
         * Fires after a form submission is stored.
         *
         * @param int   $submissionId Submission ID.
         * @param array $schema       Schema.
         * @param array $fields       Sanitized fields.
         * @since 3.0.0
         */
        FormsHookDispatcher::doSubmitAfter($submissionId, $schema, $result['fields']);

        $redirect = RedirectAllowlist::sanitize(
            isset($schema['success_redirect']) ? (string) $schema['success_redirect'] : ''
        );
        $message = isset($schema['success_message']) && $schema['success_message'] !== ''
            ? (string) $schema['success_message']
            : __('Thank you. Your submission was received.', 'notifal');
        // After-submit mode controls whether the client shows a message and/or redirects.
        $behavior = isset($schema['success_behavior'])
            ? sanitize_key((string) $schema['success_behavior'])
            : 'message';
        if (!in_array($behavior, AttributeContract::SUCCESS_BEHAVIORS, true)) {
            $behavior = 'message';
        }

        $response = [
            'ok'            => true,
            'message'       => $message,
            'submission_id' => $submissionId,
        ];

        // Only include redirect when the chosen behavior allows it.
        if ($redirect !== '' && ($behavior === 'redirect' || $behavior === 'both')) {
            $response['redirect'] = $redirect;
        }

        /**
         * Filter public success payload (must not add secrets).
         *
         * @param array $response Response.
         * @param int   $submissionId ID.
         * @since 3.0.0
         */
        return FormsHookDispatcher::filterSubmitResponse($response, $submissionId, $schema);
    }

    /**
     * Find the first unique-field collision for this form submission.
     *
     * CPT field definitions are authoritative; schema unique flags are a fallback.
     *
     * @param int                              $formId          Form CPT ID.
     * @param array<int, array<string, mixed>> $schemaFields    Schema field rows.
     * @param array<string, mixed>             $submittedFields Sanitized submitted map.
     * @return array{ok: bool, message: string, code: string, field: string}|null
     * @since 3.0.0
     */
    private function findUniqueViolation(int $formId, array $schemaFields, array $submittedFields): ?array
    {
        if ($formId <= 0) {
            return null;
        }

        // Prefer stored Form CPT definitions so admin unique toggles apply immediately.
        $definitions = $this->resolveUniqueFieldDefinitions(
            $this->formFields->get($formId),
            $schemaFields
        );

        foreach ($definitions as $field) {
            if (!is_array($field) || empty($field['unique'])) {
                continue;
            }

            $name = isset($field['name']) ? sanitize_key((string) $field['name']) : '';
            if ($name === '' || !array_key_exists($name, $submittedFields)) {
                continue;
            }

            $value = $submittedFields[$name];
            // Empty values never participate in uniqueness (required handles blanks).
            if ($this->isEmptySubmittedValue($value)) {
                continue;
            }

            $stored = UniqueValueNormalizer::toCompareString($value);
            if ($stored === '') {
                continue;
            }

            if (!$this->submissions->fieldValueExists($formId, $name, $stored)) {
                continue;
            }

            // Prefer admin label for visitor-facing copy; fall back to machine name.
            $label = isset($field['label']) ? sanitize_text_field((string) $field['label']) : '';
            if ($label === '') {
                $label = $name;
            }

            $defaultMessage = sprintf(
                /* translators: %s: field label shown to visitors */
                __('%s has already been submitted.', 'notifal'),
                $label
            );

            /**
             * Filter the public duplicate unique-value message.
             *
             * @param string               $message Default message.
             * @param string               $name    Field machine name.
             * @param string               $label   Field label for visitors (falls back to name).
             * @param int                  $formId  Form CPT ID.
             * @param array<string, mixed> $field   Field definition.
             * @since 3.0.0
             */
            $message = (string) apply_filters(
                FilterHooks::FORMS_UNIQUE_VALUE_DUPLICATE_MESSAGE,
                $defaultMessage,
                $name,
                $label,
                $formId,
                $field
            );

            return [
                'ok'      => false,
                'message' => $message !== '' ? $message : $defaultMessage,
                'code'    => 'duplicate_value',
                'field'   => $name,
            ];
        }

        return null;
    }

    /**
     * Merge CPT field rows with signed schema unique flags for submit checks.
     *
     * CPT rows are authoritative, but schema unique flags are honored when CPT
     * definitions are missing or a row omits the unique toggle.
     *
     * @param array<int, array<string, mixed>> $cptFields    Stored Form CPT fields.
     * @param array<int, array<string, mixed>> $schemaFields Signed schema field rows.
     * @return array<int, array<string, mixed>>
     * @since 3.0.0
     */
    private function resolveUniqueFieldDefinitions(array $cptFields, array $schemaFields): array
    {
        // Build a map of schema fields marked unique for fallback merging.
        $schemaUnique = [];
        foreach ($schemaFields as $field) {
            if (!is_array($field) || empty($field['unique'])) {
                continue;
            }

            $name = isset($field['name']) ? sanitize_key((string) $field['name']) : '';
            if ($name === '') {
                continue;
            }

            $schemaUnique[$name] = $field;
        }

        // When CPT has no rows, rely entirely on the signed schema snapshot.
        if ($cptFields === []) {
            return $schemaFields;
        }

        // Overlay schema unique flags onto matching CPT rows.
        $definitions = $cptFields;
        $knownNames = [];
        foreach ($definitions as $index => $field) {
            if (!is_array($field)) {
                continue;
            }

            $name = isset($field['name']) ? sanitize_key((string) $field['name']) : '';
            if ($name === '') {
                continue;
            }

            $knownNames[$name] = true;
            if (empty($field['unique']) && isset($schemaUnique[$name])) {
                $definitions[$index]['unique'] = true;
            }
        }

        // Append schema-only unique fields that are not stored on the CPT yet.
        foreach ($schemaUnique as $name => $field) {
            if (isset($knownNames[$name])) {
                continue;
            }

            $definitions[] = $field;
            $knownNames[$name] = true;
        }

        return $definitions;
    }

    /**
     * Whether a sanitized submitted value is empty for uniqueness checks.
     *
     * @param mixed $value Submitted value.
     * @return bool
     * @since 3.0.0
     */
    private function isEmptySubmittedValue($value): bool
    {
        if (is_array($value)) {
            return $value === [];
        }

        return trim((string) $value) === '';
    }

    /**
     * Generic error payload (no schema/secret hints).
     *
     * @param string|null $message Optional public message.
     * @return array{ok: bool, message: string}
     */
    private function genericError(?string $message = null): array
    {
        return [
            'ok'      => false,
            'message' => $message !== null
                ? $message
                : __('Unable to submit the form. Please try again.', 'notifal'),
        ];
    }
}
