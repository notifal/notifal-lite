<?php

namespace Notifal\Modules\Forms\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Centralizes Notifal Forms WordPress hooks with global and scoped variants.
 *
 * Global hooks run for every form. Dynamic hooks append the form CPT ID or field
 * machine name so developers can target a single form or field when needed.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormsHookDispatcher
{
    /**
     * Resolve form CPT ID from a parsed schema DTO.
     *
     * @param array<string, mixed> $schema Parsed schema DTO.
     * @return int
     * @since 3.0.0
     */
    public static function formId(array $schema): int
    {
        // Read form CPT ID from the signed schema snapshot.
        return isset($schema['form_id']) ? absint($schema['form_id']) : 0;
    }

    /**
     * Filter raw submitted fields before validation.
     *
     * @param array<string, mixed> $fields Submitted field map.
     * @param array<string, mixed> $schema Parsed schema DTO.
     * @return array<string, mixed>
     * @since 3.0.0
     */
    public static function filterSubmitFields(array $fields, array $schema): array
    {
        // Allow global mutation of the raw payload.
        $fields = (array) apply_filters(FilterHooks::FORMS_SUBMIT_FIELDS, $fields, $schema);

        // Allow form-scoped mutation when a CPT ID is known.
        $formId = self::formId($schema);
        if ($formId > 0) {
            $fields = (array) apply_filters(
                sprintf(FilterHooks::FORMS_SUBMIT_FIELDS_FORM, $formId),
                $fields,
                $schema
            );
        }

        return $fields;
    }

    /**
     * Determine whether a submit may proceed after security checks.
     *
     * @param array<string, mixed> $schema Parsed schema DTO.
     * @param array<string, mixed> $fields Raw submitted field map.
     * @return true|array{ok: false, message: string, code: string, field: string}
     * @since 3.0.0
     */
    public static function filterSubmitAllow(array $schema, array $fields)
    {
        // Default is allow; filters may return false or a structured block payload.
        $allowed = apply_filters(FilterHooks::FORMS_SUBMIT_ALLOW, true, $schema, $fields);

        $formId = self::formId($schema);
        if ($formId > 0) {
            $allowed = apply_filters(
                sprintf(FilterHooks::FORMS_SUBMIT_ALLOW_FORM, $formId),
                $allowed,
                $schema,
                $fields
            );
        }

        // True means the submit pipeline may continue.
        if ($allowed === true) {
            return true;
        }

        // Structured array lets extensions return a custom public message.
        if (is_array($allowed) && isset($allowed['message'])) {
            return [
                'ok'      => false,
                'message' => (string) $allowed['message'],
                'code'    => isset($allowed['code'])
                    ? sanitize_key((string) $allowed['code'])
                    : 'blocked',
                'field'   => isset($allowed['field'])
                    ? sanitize_key((string) $allowed['field'])
                    : '',
            ];
        }

        // False or any other value blocks with a generic public message.
        return [
            'ok'      => false,
            'message' => __('Unable to submit the form. Please try again.', 'notifal'),
            'code'    => 'blocked',
            'field'   => '',
        ];
    }

    /**
     * Fire submit-before actions (global + form-scoped).
     *
     * @param array<string, mixed> $schema Parsed schema DTO.
     * @param array<string, mixed> $fields Raw submitted field map.
     * @return void
     * @since 3.0.0
     */
    public static function doSubmitBefore(array $schema, array $fields): void
    {
        // Global side-effect hook for all forms.
        do_action(ActionHooks::FORMS_SUBMIT_BEFORE, $schema, $fields);

        // Form-scoped side-effect hook when CPT ID is present.
        $formId = self::formId($schema);
        if ($formId > 0) {
            do_action(sprintf(ActionHooks::FORMS_SUBMIT_BEFORE_FORM, $formId), $schema, $fields);
        }
    }

    /**
     * Fire validate-before actions (global + form-scoped).
     *
     * @param array<string, mixed> $schema Parsed schema DTO.
     * @param array<string, mixed> $fields Raw submitted field map.
     * @return void
     * @since 3.0.0
     */
    public static function doValidateBefore(array $schema, array $fields): void
    {
        // Global hook before field loop begins.
        do_action(ActionHooks::FORMS_VALIDATE_BEFORE, $schema, $fields);

        $formId = self::formId($schema);
        if ($formId > 0) {
            do_action(sprintf(ActionHooks::FORMS_VALIDATE_BEFORE_FORM, $formId), $schema, $fields);
        }
    }

    /**
     * Fire validate-after actions (global + form-scoped).
     *
     * @param array<string, mixed> $result Validation result.
     * @param array<string, mixed> $schema Parsed schema DTO.
     * @param array<string, mixed> $fields Raw submitted field map.
     * @return void
     * @since 3.0.0
     */
    public static function doValidateAfter(array $result, array $schema, array $fields): void
    {
        // Global hook after the validation result is assembled.
        do_action(ActionHooks::FORMS_VALIDATE_AFTER, $result, $schema, $fields);

        $formId = self::formId($schema);
        if ($formId > 0) {
            do_action(sprintf(ActionHooks::FORMS_VALIDATE_AFTER_FORM, $formId), $result, $schema, $fields);
        }
    }

    /**
     * Filter per-field validation errors (global, field, and form+field scoped).
     *
     * @param string[]                  $fieldErrors Error slugs for the field.
     * @param array<string, mixed>      $field       Field schema row.
     * @param mixed                     $value       Sanitized submitted value.
     * @param array<string, mixed>      $input       Raw submitted field map.
     * @param array<string, mixed>      $schema      Parsed schema DTO.
     * @return string[]
     * @since 3.0.0
     */
    public static function filterValidateField(
        array $fieldErrors,
        array $field,
        $value,
        array $input,
        array $schema
    ): array {
        // Global per-field filter.
        $fieldErrors = (array) apply_filters(
            FilterHooks::FORMS_VALIDATE_FIELD,
            $fieldErrors,
            $field,
            $value,
            $input,
            $schema
        );

        $name = isset($field['name']) ? sanitize_key((string) $field['name']) : '';
        if ($name !== '') {
            // Field machine name scoped filter.
            $fieldErrors = (array) apply_filters(
                sprintf(FilterHooks::FORMS_VALIDATE_FIELD_NAME, $name),
                $fieldErrors,
                $field,
                $value,
                $input,
                $schema
            );
        }

        $formId = self::formId($schema);
        if ($formId > 0 && $name !== '') {
            // Form CPT + field machine name scoped filter.
            $fieldErrors = (array) apply_filters(
                sprintf(FilterHooks::FORMS_VALIDATE_FIELD_FORM, $formId, $name),
                $fieldErrors,
                $field,
                $value,
                $input,
                $schema
            );
        }

        return $fieldErrors;
    }

    /**
     * Filter the full validation result (global + form-scoped).
     *
     * @param array<string, mixed> $result Validation result.
     * @param array<string, mixed> $schema Parsed schema DTO.
     * @param array<string, mixed> $input  Raw submitted field map.
     * @return array<string, mixed>
     * @since 3.0.0
     */
    public static function filterValidateResult(array $result, array $schema, array $input): array
    {
        // Global result filter can adjust valid flag, fields, or errors.
        $result = (array) apply_filters(FilterHooks::FORMS_VALIDATE_RESULT, $result, $schema, $input);

        $formId = self::formId($schema);
        if ($formId > 0) {
            $result = (array) apply_filters(
                sprintf(FilterHooks::FORMS_VALIDATE_RESULT_FORM, $formId),
                $result,
                $schema,
                $input
            );
        }

        return $result;
    }

    /**
     * Filter submission insert payload before persistence.
     *
     * @param array<string, mixed> $data   Submission insert payload.
     * @param array<string, mixed> $schema Parsed schema DTO.
     * @param array<string, mixed> $fields Sanitized submitted field map.
     * @return array<string, mixed>
     * @since 3.0.0
     */
    public static function filterSubmissionData(array $data, array $schema, array $fields): array
    {
        // Global filter for all forms.
        $data = (array) apply_filters(FilterHooks::FORMS_SUBMISSION_DATA, $data, $schema, $fields);

        $formId = self::formId($schema);
        if ($formId > 0) {
            $data = (array) apply_filters(
                sprintf(FilterHooks::FORMS_SUBMISSION_DATA_FORM, $formId),
                $data,
                $schema,
                $fields
            );
        }

        return $data;
    }

    /**
     * Fire submit-after actions (global + form-scoped).
     *
     * @param int                    $submissionId Stored submission ID.
     * @param array<string, mixed>   $schema       Parsed schema DTO.
     * @param array<string, mixed>   $fields       Sanitized submitted field map.
     * @return void
     * @since 3.0.0
     */
    public static function doSubmitAfter(int $submissionId, array $schema, array $fields): void
    {
        // Global post-save side-effect hook.
        do_action(ActionHooks::FORMS_SUBMIT_AFTER, $submissionId, $schema, $fields);

        $formId = self::formId($schema);
        if ($formId > 0) {
            do_action(
                sprintf(ActionHooks::FORMS_SUBMIT_AFTER_FORM, $formId),
                $submissionId,
                $schema,
                $fields
            );
        }
    }

    /**
     * Filter public success response (global + form-scoped).
     *
     * @param array<string, mixed> $response     Success payload.
     * @param int                    $submissionId Stored submission ID.
     * @param array<string, mixed>   $schema       Parsed schema DTO.
     * @return array<string, mixed>
     * @since 3.0.0
     */
    public static function filterSubmitResponse(array $response, int $submissionId, array $schema): array
    {
        // Global success payload filter (must not add secrets).
        $response = (array) apply_filters(FilterHooks::FORMS_SUBMIT_RESPONSE, $response, $submissionId);

        $formId = self::formId($schema);
        if ($formId > 0) {
            $response = (array) apply_filters(
                sprintf(FilterHooks::FORMS_SUBMIT_RESPONSE_FORM, $formId),
                $response,
                $submissionId
            );
        }

        return $response;
    }

    /**
     * Apply error filters and fire failed-submit actions.
     *
     * @param array<string, mixed> $error   Error payload.
     * @param array<string, mixed> $schema  Parsed schema DTO.
     * @param array<string, mixed> $context Failure context.
     * @return array<string, mixed>
     * @since 3.0.0
     */
    public static function finalizeSubmitError(array $error, array $schema, array $context = []): array
    {
        // Normalize required keys before filters run.
        $payload = [
            'ok'      => false,
            'message' => isset($error['message'])
                ? (string) $error['message']
                : __('Unable to submit the form. Please try again.', 'notifal'),
            'code'    => isset($error['code']) ? sanitize_key((string) $error['code']) : '',
            'field'   => isset($error['field']) ? sanitize_key((string) $error['field']) : '',
        ];

        // Global error filter.
        $payload = (array) apply_filters(FilterHooks::FORMS_SUBMIT_ERROR, $payload, $schema, $context);

        $formId = self::formId($schema);
        if ($formId > 0) {
            $payload = (array) apply_filters(
                sprintf(FilterHooks::FORMS_SUBMIT_ERROR_FORM, $formId),
                $payload,
                $schema,
                $context
            );
        }

        // Side-effect hooks for logging, analytics, or integrations.
        do_action(ActionHooks::FORMS_SUBMIT_FAILED, $payload, $schema, $context);
        if ($formId > 0) {
            do_action(sprintf(ActionHooks::FORMS_SUBMIT_FAILED_FORM, $formId), $payload, $schema, $context);
        }

        return $payload;
    }
}
