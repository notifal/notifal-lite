<?php

namespace Notifal\Modules\Forms\Config;

defined('ABSPATH') || exit;

/**
 * Canonical Forms attribute / class contract shared by parser, sanitizer, and docs.
 *
 * HTML is the source of truth for structure and behavior. Secrets never appear here.
 *
 * @since 3.0.0
 * @updated 3.0.0 Designable Success / Failed result panels; frontend hides form flow while result UI is visible.
 * @author Hossein <hossein@notifal.com>
 */
class AttributeContract
{
    /**
     * Root form CSS class.
     *
     * @var string
     */
    public const CLASS_FORM = 'notifal-form';

    /**
     * Step wrapper CSS class.
     *
     * @var string
     */
    public const CLASS_STEP = 'notifal-form-step';

    /**
     * Field control CSS class.
     *
     * @var string
     */
    public const CLASS_FIELD = 'notifal-form-field';

    /**
     * Field wrap CSS class.
     *
     * @var string
     */
    public const CLASS_FIELD_WRAP = 'notifal-form-field-wrap';

    /**
     * Label CSS class.
     *
     * @var string
     */
    public const CLASS_LABEL = 'notifal-form-label';

    /**
     * Navigation button CSS class.
     *
     * @var string
     */
    public const CLASS_NAV = 'notifal-form-nav';

    /**
     * Designable success result panel CSS class.
     *
     * @var string
     * @since 3.0.0
     */
    public const CLASS_SUCCESS = 'notifal-form-success';

    /**
     * Designable failed result panel CSS class.
     *
     * @var string
     * @since 3.0.0
     */
    public const CLASS_ERROR = 'notifal-form-error';

    /**
     * Syncable message node inside the success panel.
     *
     * @var string
     * @since 3.0.0
     */
    public const CLASS_SUCCESS_MESSAGE = 'notifal-form-success-message';

    /**
     * Syncable message node inside the failed panel.
     *
     * @var string
     * @since 3.0.0
     */
    public const CLASS_ERROR_MESSAGE = 'notifal-form-error-message';

    /**
     * Widget detector attribute value.
     *
     * @var string
     */
    public const WIDGET_ID = 'form';

    /**
     * Allowed field types for validation and sanitization.
     *
     * @var string[]
     */
    public const FIELD_TYPES = [
        'text',
        'email',
        'tel',
        'number',
        'textarea',
        'select',
        'radio',
        'checkbox',
        'hidden',
    ];

    /**
     * Allowed conditional operators.
     *
     * @var string[]
     */
    public const CONDITIONAL_OPERATORS = [
        'equals',
        'not_equals',
        'contains',
        'not_empty',
        'empty',
    ];

    /**
     * Allowed captcha mode values on the form root.
     *
     * @var string[]
     */
    public const CAPTCHA_MODES = [
        'inherit',
        'none',
        'google',
        'turnstile',
    ];

    /**
     * Allowed form navigation actions.
     *
     * @var string[]
     */
    public const FORM_ACTIONS = [
        'submit',
        'next',
        'back',
    ];

    /**
     * Allowed after-submit behavior values on the form root.
     *
     * @var string[]
     */
    public const SUCCESS_BEHAVIORS = [
        'message',
        'redirect',
        'both',
    ];

    /**
     * Allowed data-* attribute names for Notifal form markup (no secrets).
     *
     * @var string[]
     */
    public const ALLOWED_DATA_ATTRIBUTES = [
        'data-notifal-widget',
        'data-form-id',
        'data-form-multi-step',
        'data-success-message',
        'data-success-redirect',
        'data-success-behavior',
        'data-error-message',
        'data-primary-color',
        'data-submitter-email-field',
        'data-captcha',
        'data-step-index',
        'data-step-title',
        'data-field-type',
        'data-field-name',
        'data-required',
        // When true, the field value must be unique across submissions for this form.
        'data-unique',
        'data-regex',
        'data-options',
        'data-label',
        'data-placeholder',
        'data-conditional-field',
        'data-conditional-operator',
        'data-conditional-value',
        'data-form-action',
        'data-form-result',
        'data-schema-token',
        'data-notification-id',
    ];

    /**
     * Attribute name patterns that must never appear (secret leak prevention).
     *
     * @var string[]
     */
    public const FORBIDDEN_DATA_ATTRIBUTE_PATTERNS = [
        'data-api-key',
        'data-webhook',
        'data-mailchimp',
        'data-secret',
        'data-auth',
        'data-token-secret',
        'data-private',
    ];

    /**
     * Maximum regex pattern length (ReDoS mitigation).
     *
     * @var int
     */
    public const REGEX_MAX_LENGTH = 120;

    /**
     * Maximum fields per form submission.
     *
     * @var int
     */
    public const MAX_FIELD_COUNT = 40;

    /**
     * Maximum payload characters across all field values.
     *
     * @var int
     */
    public const MAX_PAYLOAD_CHARS = 20000;

    /**
     * Return kses-friendly bool map of allowed form data attributes.
     *
     * @return array<string, bool>
     * @since 3.0.0
     */
    public static function getKsesDataAttributes(): array
    {
        $map = [];

        // Map each allowlisted attribute name to true for wp_kses.
        foreach (self::ALLOWED_DATA_ATTRIBUTES as $attr) {
            $map[$attr] = true;
        }

        return $map;
    }

    /**
     * Whether an attribute name looks like a forbidden secret attribute.
     *
     * @param string $attribute Attribute name (lowercase preferred).
     * @return bool
     * @since 3.0.0
     */
    public static function isForbiddenAttribute(string $attribute): bool
    {
        $normalized = strtolower($attribute);

        foreach (self::FORBIDDEN_DATA_ATTRIBUTE_PATTERNS as $pattern) {
            if (strpos($normalized, $pattern) === 0 || $normalized === $pattern) {
                return true;
            }
        }

        return false;
    }
}
