<?php

namespace Notifal\Modules\Forms\Application\Services;

use Notifal\Modules\Forms\Application\Services\Security\RegexGuard;
use Notifal\Modules\Forms\Config\AttributeContract;

defined('ABSPATH') || exit;

/**
 * Authoritative server-side form field validation against a parsed schema.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormValidationService
{
    /**
     * Validate submitted fields against schema.
     *
     * @param array<string, mixed> $schema Schema from HtmlSchemaParser.
     * @param array<string, mixed> $input  Submitted field map (name => value).
     * @return array{valid: bool, fields: array<string, mixed>, errors: string[]}
     * @since 3.0.0
     */
    public function validate(array $schema, array $input): array
    {
        // Extension point before the field loop runs.
        FormsHookDispatcher::doValidateBefore($schema, $input);

        $errors = [];
        $clean = [];
        $fields = isset($schema['fields']) && is_array($schema['fields']) ? $schema['fields'] : [];

        // Reject unknown field names (mass-assignment safe).
        $allowedNames = [];
        foreach ($fields as $field) {
            if (!empty($field['name'])) {
                $allowedNames[(string) $field['name']] = true;
            }
        }

        foreach (array_keys($input) as $key) {
            if (!isset($allowedNames[$key])) {
                unset($input[$key]);
            }
        }

        $payloadSize = 0;

        foreach ($fields as $field) {
            $name = (string) $field['name'];
            $active = $this->isFieldActive($field, $input);

            if (!$active) {
                continue;
            }

            $raw = isset($input[$name]) ? $input[$name] : '';
            $sanitized = $this->sanitizeByType($field, $raw);
            $payloadSize += is_array($sanitized)
                ? strlen(wp_json_encode($sanitized))
                : strlen((string) $sanitized);

            if ($payloadSize > AttributeContract::MAX_PAYLOAD_CHARS) {
                $errors[] = 'payload';
                break;
            }

            // Collect built-in error slugs before extension filters run.
            $fieldErrors = $this->collectFieldErrors($field, $sanitized);

            // Allow plugins to append or clear per-field validation errors.
            $fieldErrors = FormsHookDispatcher::filterValidateField(
                $fieldErrors,
                $field,
                $sanitized,
                $input,
                $schema
            );

            if ($fieldErrors !== []) {
                $errors[] = $name;
                continue;
            }

            $clean[$name] = $sanitized;
        }

        $result = [
            'valid'  => empty($errors),
            'fields' => $clean,
            'errors' => array_values(array_unique($errors)),
        ];

        // Final result filter and validate-after actions.
        $result = FormsHookDispatcher::filterValidateResult($result, $schema, $input);
        FormsHookDispatcher::doValidateAfter($result, $schema, $input);

        return $result;
    }

    /**
     * Collect built-in validation error slugs for one active field.
     *
     * @param array<string, mixed> $field     Field schema row.
     * @param mixed                $sanitized Sanitized submitted value.
     * @return string[]
     * @since 3.0.0
     */
    private function collectFieldErrors(array $field, $sanitized): array
    {
        $fieldErrors = [];

        // Required check on sanitized value.
        if (!empty($field['required']) && $this->isEmpty($sanitized)) {
            $fieldErrors[] = 'required';
        }

        // Type check when value is present.
        if (!$this->isEmpty($sanitized) && !$this->matchesType($field, $sanitized)) {
            $fieldErrors[] = 'type';
        }

        // Regex check when configured and value is present.
        $regex = isset($field['regex']) ? (string) $field['regex'] : '';
        if ($regex !== '' && !$this->isEmpty($sanitized)) {
            $subject = is_array($sanitized) ? implode(',', $sanitized) : (string) $sanitized;
            if (!RegexGuard::matches($regex, $subject)) {
                $fieldErrors[] = 'regex';
            }
        }

        return $fieldErrors;
    }

    /**
     * Re-evaluate conditionals server-side.
     *
     * @param array<string, mixed> $field Field schema row.
     * @param array<string, mixed> $input Submitted values.
     * @return bool
     * @since 3.0.0
     */
    public function isFieldActive(array $field, array $input): bool
    {
        $dep = isset($field['conditional_field']) ? (string) $field['conditional_field'] : '';
        $op = isset($field['conditional_operator']) ? (string) $field['conditional_operator'] : '';

        if ($dep === '' || $op === '') {
            return true;
        }

        $depValue = isset($input[$dep]) ? $input[$dep] : '';
        $expected = isset($field['conditional_value']) ? (string) $field['conditional_value'] : '';
        $depString = is_array($depValue) ? implode(',', $depValue) : (string) $depValue;

        switch ($op) {
            case 'equals':
                return $depString === $expected;
            case 'not_equals':
                return $depString !== $expected;
            case 'contains':
                return $expected !== '' && strpos($depString, $expected) !== false;
            case 'not_empty':
                return trim($depString) !== '';
            case 'empty':
                return trim($depString) === '';
            default:
                return true;
        }
    }

    /**
     * Type-aware sanitization.
     *
     * @param array<string, mixed> $field Field schema.
     * @param mixed                $raw   Raw value.
     * @return mixed
     * @since 3.0.0
     */
    private function sanitizeByType(array $field, $raw)
    {
        $type = isset($field['type']) ? (string) $field['type'] : 'text';
        $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];

        switch ($type) {
            case 'email':
                // Lowercase the full address so unique checks and storage stay consistent.
                $email = sanitize_email(is_string($raw) ? $raw : '');
                return $email === '' ? '' : UniqueValueNormalizer::toCompareString($email);
            case 'textarea':
                return sanitize_textarea_field(is_string($raw) ? $raw : '');
            case 'number':
                return is_numeric($raw) ? (float) $raw : '';
            case 'checkbox':
                if (is_array($raw)) {
                    $clean = [];
                    foreach ($raw as $item) {
                        $item = sanitize_text_field((string) $item);
                        if ($options === [] || in_array($item, $options, true)) {
                            $clean[] = $item;
                        }
                    }
                    return $clean;
                }
                $value = sanitize_text_field((string) $raw);
                if ($options !== [] && !in_array($value, $options, true) && $value !== '1' && $value !== 'on') {
                    return '';
                }
                return $value;
            case 'select':
            case 'radio':
                $value = sanitize_text_field((string) $raw);
                if ($options !== [] && !in_array($value, $options, true)) {
                    return '';
                }
                return $value;
            case 'tel':
            case 'text':
            case 'hidden':
            default:
                return sanitize_text_field(is_string($raw) || is_numeric($raw) ? (string) $raw : '');
        }
    }

    /**
     * Type-level validity after sanitize.
     *
     * @param array<string, mixed> $field Field schema.
     * @param mixed                $value Sanitized value.
     * @return bool
     */
    private function matchesType(array $field, $value): bool
    {
        $type = isset($field['type']) ? (string) $field['type'] : 'text';

        if ($type === 'email' && $value !== '' && !is_email((string) $value)) {
            return false;
        }

        if ($type === 'number' && $value !== '' && !is_numeric($value)) {
            return false;
        }

        return true;
    }

    /**
     * @param mixed $value Value.
     * @return bool
     */
    private function isEmpty($value): bool
    {
        if (is_array($value)) {
            return $value === [];
        }

        return trim((string) $value) === '';
    }
}
