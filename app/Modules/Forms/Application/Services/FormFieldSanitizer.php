<?php

namespace Notifal\Modules\Forms\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Forms\Application\Services\Security\RegexGuard;
use Notifal\Modules\Forms\Config\AttributeContract;

defined('ABSPATH') || exit;

/**
 * Sanitize stored form field definitions shared by admin UI and HTML Builder sync.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormFieldSanitizer
{
    /**
     * Sanitize a list of field definitions from POST or JSON.
     *
     * @param mixed $raw Raw field list.
     * @return array<int, array<string, mixed>>
     * @since 3.0.0
     */
    public function sanitizeList($raw): array
    {
        // Decode JSON string payloads from admin or builder sync.
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode(wp_unslash($raw), true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($raw)) {
            return [];
        }

        $fields = [];
        $seen = [];

        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $field = $this->sanitizeOne($item);
            if ($field === null) {
                continue;
            }

            $name = (string) $field['name'];
            if ($name === '' || isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $fields[] = $field;

            if (count($fields) >= AttributeContract::MAX_FIELD_COUNT) {
                break;
            }
        }

        /**
         * Filter sanitized field definitions before persistence.
         *
         * @param array<int, array<string, mixed>> $fields Sanitized fields.
         * @since 3.0.0
         */
        return (array) apply_filters(FilterHooks::FORMS_FIELD_DEFINITIONS, $fields);
    }

    /**
     * Sanitize one field definition row.
     *
     * @param array<string, mixed> $item Raw field row.
     * @return array<string, mixed>|null
     * @since 3.0.0
     */
    private function sanitizeOne(array $item): ?array
    {
        $name = isset($item['name']) ? sanitize_key((string) $item['name']) : '';
        if ($name === '') {
            return null;
        }

        $type = isset($item['type']) ? sanitize_key((string) $item['type']) : 'text';
        if (!in_array($type, AttributeContract::FIELD_TYPES, true)) {
            $type = 'text';
        }

        $operator = isset($item['conditional_operator'])
            ? sanitize_key((string) $item['conditional_operator'])
            : '';
        if ($operator !== '' && !in_array($operator, AttributeContract::CONDITIONAL_OPERATORS, true)) {
            $operator = '';
        }

        $options = $this->sanitizeOptions($item['options'] ?? []);

        return [
            'name'                  => $name,
            'type'                  => $type,
            'label'                 => isset($item['label'])
                ? sanitize_text_field((string) $item['label'])
                : '',
            // Hint text shown inside an empty frontend input.
            'placeholder'           => isset($item['placeholder'])
                ? sanitize_text_field((string) $item['placeholder'])
                : '',
            'required'              => !empty($item['required']),
            // When true, identical inbox values for this field are rejected on submit.
            'unique'                => !empty($item['unique']),
            'regex'                 => RegexGuard::sanitizePattern(
                isset($item['regex']) ? (string) $item['regex'] : ''
            ),
            'options'               => $options,
            'conditional_field'     => isset($item['conditional_field'])
                ? sanitize_key((string) $item['conditional_field'])
                : '',
            'conditional_operator'  => $operator,
            'conditional_value'     => isset($item['conditional_value'])
                ? sanitize_text_field((string) $item['conditional_value'])
                : '',
        ];
    }

    /**
     * Sanitize select/radio/checkbox option values.
     *
     * @param mixed $raw Raw options.
     * @return array<int, string>
     * @since 3.0.0
     */
    private function sanitizeOptions($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $options = [];
        foreach ($raw as $option) {
            $value = sanitize_text_field((string) $option);
            if ($value === '') {
                continue;
            }
            $options[] = $value;
            if (count($options) >= 50) {
                break;
            }
        }

        return $options;
    }
}
