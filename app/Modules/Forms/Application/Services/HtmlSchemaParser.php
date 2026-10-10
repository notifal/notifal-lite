<?php

namespace Notifal\Modules\Forms\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Forms\Application\Services\Security\RegexGuard;
use Notifal\Modules\Forms\Config\AttributeContract;

defined('ABSPATH') || exit;

/**
 * Builds a validation schema DTO from rendered Notifal form HTML attributes.
 *
 * Server and frontend share the same attribute rules; the server remains authoritative.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class HtmlSchemaParser
{
    /**
     * Parse the first .notifal-form in HTML into a schema array.
     *
     * @param string $html HTML fragment containing a Notifal form.
     * @return array<string, mixed>|null Schema or null when no form found.
     * @since 3.0.0
     */
    public function parse(string $html): ?array
    {
        if ($html === '' || strpos($html, AttributeContract::CLASS_FORM) === false) {
            return null;
        }

        $document = $this->createDocument($html);
        if (!$document) {
            return null;
        }

        $xpath = new \DOMXPath($document);
        $forms = $xpath->query(
            '//*[contains(concat(" ", normalize-space(@class), " "), " '
            . AttributeContract::CLASS_FORM
            . ' ")]'
        );

        if (!$forms || $forms->length === 0) {
            return null;
        }

        /** @var \DOMElement $form */
        $form = $forms->item(0);

        $schema = [
            'form_id'                 => absint($form->getAttribute('data-form-id')),
            'multi_step'              => $this->attrIsTrue($form->getAttribute('data-form-multi-step')),
            'success_message'         => sanitize_text_field($form->getAttribute('data-success-message')),
            'success_redirect'        => $form->getAttribute('data-success-redirect'),
            // After-submit mode: message, redirect, or both (@since 3.0.0).
            'success_behavior'        => $this->sanitizeSuccessBehavior($form->getAttribute('data-success-behavior')),
            // Custom error fallback shown when the server has no specific message.
            'error_message'           => sanitize_text_field($form->getAttribute('data-error-message')),
            // Brand accent mirrored on the Form CPT and applied as a CSS variable.
            'primary_color'           => $this->sanitizePrimaryColor($form->getAttribute('data-primary-color')),
            'submitter_email_field'   => sanitize_key($form->getAttribute('data-submitter-email-field')),
            'captcha'                 => $this->sanitizeCaptchaMode($form->getAttribute('data-captcha')),
            'steps'                   => [],
            'fields'                  => [],
        ];

        $schema['fields'] = $this->parseFields($xpath, $form);
        $schema['steps'] = $this->parseSteps($xpath, $form);

        /**
         * Filter parsed form schema before fingerprinting / validation.
         *
         * @param array<string, mixed> $schema Parsed schema.
         * @param string               $html   Source HTML.
         * @since 3.0.0
         */
        $schema = (array) apply_filters(FilterHooks::FORMS_SCHEMA_PARSED, $schema, $html);

        return $schema;
    }

    /**
     * Stable fingerprint of schema for signed tokens.
     *
     * @param array<string, mixed> $schema Schema DTO.
     * @return string
     * @since 3.0.0
     */
    public function fingerprint(array $schema): string
    {
        // Exclude volatile keys if any; encode deterministically.
        $normalized = $schema;
        ksort($normalized);

        return hash('sha256', wp_json_encode($normalized));
    }

    /**
     * Parse field elements under a form root.
     *
     * @param \DOMXPath   $xpath XPath helper.
     * @param \DOMElement $form  Form root element.
     * @return array<int, array<string, mixed>>
     * @since 3.0.0
     */
    private function parseFields(\DOMXPath $xpath, \DOMElement $form): array
    {
        $nodes = $xpath->query(
            './/*[contains(concat(" ", normalize-space(@class), " "), " '
            . AttributeContract::CLASS_FIELD
            . ' ")]',
            $form
        );

        $fields = [];
        $seen = [];

        if (!$nodes) {
            return $fields;
        }

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $name = sanitize_key($node->getAttribute('data-field-name'));
            if ($name === '') {
                $name = sanitize_key($node->getAttribute('name'));
            }

            if ($name === '' || isset($seen[$name])) {
                continue;
            }

            $type = sanitize_key($node->getAttribute('data-field-type'));
            if ($type === '' || !in_array($type, AttributeContract::FIELD_TYPES, true)) {
                $type = $this->inferTypeFromElement($node);
            }

            $regex = (string) $node->getAttribute('data-regex');
            $regexSafe = RegexGuard::sanitizePattern($regex);

            $field = [
                'name'                  => $name,
                'type'                  => $type,
                'required'              => $this->attrIsTrue($node->getAttribute('data-required')),
                // Default off: duplicate inbox values are allowed unless uniquely enforced.
                'unique'                => $this->attrIsTrue($node->getAttribute('data-unique')),
                'regex'                 => $regexSafe,
                'label'                 => sanitize_text_field($node->getAttribute('data-label')),
                // Prefer native placeholder attribute, fall back to data-placeholder.
                'placeholder'           => $this->resolvePlaceholder($node),
                'options'               => $this->parseOptions($node, $xpath),
                'conditional_field'     => sanitize_key($node->getAttribute('data-conditional-field')),
                'conditional_operator'  => $this->sanitizeOperator($node->getAttribute('data-conditional-operator')),
                'conditional_value'     => sanitize_text_field($node->getAttribute('data-conditional-value')),
                'step_index'            => $this->resolveStepIndex($node),
            ];

            $seen[$name] = true;
            $fields[] = $field;

            if (count($fields) >= AttributeContract::MAX_FIELD_COUNT) {
                break;
            }
        }

        return $fields;
    }

    /**
     * Parse multi-step wrappers.
     *
     * @param \DOMXPath   $xpath XPath helper.
     * @param \DOMElement $form  Form root.
     * @return array<int, array<string, mixed>>
     * @since 3.0.0
     */
    private function parseSteps(\DOMXPath $xpath, \DOMElement $form): array
    {
        $nodes = $xpath->query(
            './/*[contains(concat(" ", normalize-space(@class), " "), " '
            . AttributeContract::CLASS_STEP
            . ' ")]',
            $form
        );

        $steps = [];
        if (!$nodes) {
            return $steps;
        }

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $steps[] = [
                'index' => absint($node->getAttribute('data-step-index')),
                'title' => sanitize_text_field($node->getAttribute('data-step-title')),
            ];
        }

        return $steps;
    }

    /**
     * Resolve placeholder text from native or data attribute.
     *
     * @param \DOMElement $node Field element.
     * @return string
     * @since 3.0.0
     */
    private function resolvePlaceholder(\DOMElement $node): string
    {
        // Prefer the standard HTML placeholder attribute when present.
        $placeholder = sanitize_text_field($node->getAttribute('placeholder'));
        if ($placeholder !== '') {
            return $placeholder;
        }

        // Fall back to an explicit data attribute when authors set it that way.
        return sanitize_text_field($node->getAttribute('data-placeholder'));
    }

    /**
     * Collect option allowlist from select/radio/checkbox markup or data-options.
     *
     * @param \DOMElement $node  Field element.
     * @param \DOMXPath   $xpath XPath helper.
     * @return array<int, string>
     * @since 3.0.0
     */
    private function parseOptions(\DOMElement $node, \DOMXPath $xpath): array
    {
        $options = [];

        // Prefer child option / radio / checkbox values for AI clarity.
        if (strtolower($node->tagName) === 'select') {
            foreach ($node->getElementsByTagName('option') as $option) {
                if ($option instanceof \DOMElement) {
                    $options[] = sanitize_text_field($option->getAttribute('value') !== ''
                        ? $option->getAttribute('value')
                        : $option->textContent);
                }
            }
        }

        $json = trim((string) $node->getAttribute('data-options'));
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    $options[] = sanitize_text_field((string) $item);
                }
            }
        }

        // Radios/checkboxes may sit as siblings inside a wrap; use name group when needed.
        $name = $node->getAttribute('name');
        if ($name !== '' && in_array($node->getAttribute('type'), ['radio', 'checkbox'], true)) {
            $parent = $node->parentNode;
            if ($parent instanceof \DOMElement) {
                $siblings = $xpath->query('.//*[@name="' . $name . '"]', $parent->parentNode ?: $parent);
                if ($siblings) {
                    foreach ($siblings as $sibling) {
                        if ($sibling instanceof \DOMElement) {
                            $options[] = sanitize_text_field($sibling->getAttribute('value'));
                        }
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($options, static function ($value) {
            return $value !== '';
        })));
    }

    /**
     * Resolve step index from nearest step ancestor.
     *
     * @param \DOMElement $node Field node.
     * @return int
     * @since 3.0.0
     */
    private function resolveStepIndex(\DOMElement $node): int
    {
        $current = $node->parentNode;
        while ($current instanceof \DOMElement) {
            $class = ' ' . $current->getAttribute('class') . ' ';
            if (strpos($class, ' ' . AttributeContract::CLASS_STEP . ' ') !== false) {
                return absint($current->getAttribute('data-step-index'));
            }
            $current = $current->parentNode;
        }

        return 0;
    }

    /**
     * Infer field type from HTML element.
     *
     * @param \DOMElement $node Field element.
     * @return string
     * @since 3.0.0
     */
    private function inferTypeFromElement(\DOMElement $node): string
    {
        $tag = strtolower($node->tagName);
        if ($tag === 'textarea') {
            return 'textarea';
        }
        if ($tag === 'select') {
            return 'select';
        }

        $type = sanitize_key($node->getAttribute('type'));
        if (in_array($type, AttributeContract::FIELD_TYPES, true)) {
            return $type;
        }

        return 'text';
    }

    /**
     * @param string $value Attribute value.
     * @return bool
     */
    private function attrIsTrue(string $value): bool
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param string $mode Captcha mode attribute.
     * @return string
     */
    private function sanitizeCaptchaMode(string $mode): string
    {
        $mode = sanitize_key($mode);
        if ($mode === '') {
            return 'inherit';
        }

        return in_array($mode, AttributeContract::CAPTCHA_MODES, true) ? $mode : 'inherit';
    }

    /**
     * Sanitize after-submit behavior attribute.
     *
     * @param string $behavior Raw behavior value.
     * @return string
     * @since 3.0.0
     */
    private function sanitizeSuccessBehavior(string $behavior): string
    {
        // Normalize to a known key; default to showing a confirmation message.
        $behavior = sanitize_key($behavior);
        if ($behavior === '') {
            return 'message';
        }

        return in_array($behavior, AttributeContract::SUCCESS_BEHAVIORS, true)
            ? $behavior
            : 'message';
    }

    /**
     * Sanitize brand accent color for schema / CPT mirror.
     *
     * @param string $color Raw color value.
     * @return string
     * @since 3.0.0
     */
    private function sanitizePrimaryColor(string $color): string
    {
        $sanitized = sanitize_hex_color($color);

        return is_string($sanitized) && $sanitized !== '' ? $sanitized : '#7e2bd2';
    }

    /**
     * @param string $operator Conditional operator.
     * @return string
     */
    private function sanitizeOperator(string $operator): string
    {
        $operator = sanitize_key($operator);
        if ($operator === '') {
            return '';
        }

        return in_array($operator, AttributeContract::CONDITIONAL_OPERATORS, true) ? $operator : '';
    }

    /**
     * @param string $html HTML fragment.
     * @return \DOMDocument|null
     */
    private function createDocument(string $html): ?\DOMDocument
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><html><body><div id="notifal-form-parse-root">' . $html . '</div></body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $document : null;
    }
}
