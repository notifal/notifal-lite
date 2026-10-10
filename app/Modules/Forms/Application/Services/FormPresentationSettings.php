<?php

namespace Notifal\Modules\Forms\Application\Services;

use Notifal\Modules\Forms\Config\AttributeContract;

defined('ABSPATH') || exit;

/**
 * Presentation defaults shared by Form CPT meta and HTML Builder attrs.
 *
 * Runtime still reads template HTML; the CPT stores a mirror so admin and
 * builder stay aligned when a form is connected or synced.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormPresentationSettings
{
    /**
     * Default presentation values for new forms.
     *
     * @return array<string, mixed>
     * @since 3.0.0
     */
    public static function getDefaults(): array
    {
        return [
            'multi_step'        => false,
            'success_behavior'  => 'message',
            'success_message'   => __('Thanks! We will be in touch.', 'notifal'),
            'success_redirect'  => '',
            'error_message'     => __('Something went wrong. Please try again.', 'notifal'),
            'primary_color'     => '#7e2bd2',
            'captcha'           => 'inherit',
        ];
    }

    /**
     * Public keys stored on the Form CPT settings meta.
     *
     * @return string[]
     * @since 3.0.0
     */
    public static function getKeys(): array
    {
        return array_keys(self::getDefaults());
    }

    /**
     * Sanitize presentation values from admin or builder input.
     *
     * @param array<string, mixed> $input    Raw input (may include other keys).
     * @param array<string, mixed> $existing Existing presentation values.
     * @return array<string, mixed>
     * @since 3.0.0
     */
    public static function sanitize(array $input, array $existing = []): array
    {
        $defaults = self::getDefaults();
        $base = array_merge($defaults, self::pick($existing));
        $out = $base;

        // Checkbox fields are absent when unchecked in classic form posts.
        if (array_key_exists('multi_step', $input)) {
            $out['multi_step'] = self::toBool($input['multi_step']);
        }

        if (isset($input['success_behavior'])) {
            $out['success_behavior'] = self::sanitizeBehavior(
                (string) wp_unslash((string) $input['success_behavior'])
            );
        }

        if (isset($input['success_message'])) {
            $out['success_message'] = sanitize_text_field(
                wp_unslash((string) $input['success_message'])
            );
        }

        if (isset($input['success_redirect'])) {
            $out['success_redirect'] = self::sanitizeRedirect(
                (string) wp_unslash((string) $input['success_redirect'])
            );
        }

        if (isset($input['error_message'])) {
            $out['error_message'] = sanitize_text_field(
                wp_unslash((string) $input['error_message'])
            );
        }

        if (isset($input['primary_color'])) {
            $out['primary_color'] = self::sanitizeColor(
                (string) wp_unslash((string) $input['primary_color']),
                (string) $base['primary_color']
            );
        }

        if (isset($input['captcha'])) {
            $out['captcha'] = self::sanitizeCaptcha(
                (string) wp_unslash((string) $input['captcha'])
            );
        }

        return $out;
    }

    /**
     * Build presentation values from a parsed HTML schema.
     *
     * @param array<string, mixed> $schema Parsed form schema.
     * @return array<string, mixed>
     * @since 3.0.0
     */
    public static function fromSchema(array $schema): array
    {
        return self::sanitize([
            'multi_step'       => !empty($schema['multi_step']),
            'success_behavior' => $schema['success_behavior'] ?? 'message',
            'success_message'  => $schema['success_message'] ?? '',
            'success_redirect' => $schema['success_redirect'] ?? '',
            'error_message'    => $schema['error_message'] ?? '',
            'primary_color'    => $schema['primary_color'] ?? '#7e2bd2',
            'captcha'          => $schema['captcha'] ?? 'inherit',
        ]);
    }

    /**
     * Keep only presentation keys from a meta array.
     *
     * @param array<string, mixed> $meta Full or partial meta.
     * @return array<string, mixed>
     * @since 3.0.0
     */
    public static function pick(array $meta): array
    {
        $out = [];
        foreach (self::getKeys() as $key) {
            if (array_key_exists($key, $meta)) {
                $out[$key] = $meta[$key];
            }
        }

        return $out;
    }

    /**
     * @param mixed $value Raw bool-ish value.
     * @return bool
     */
    private static function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param string $behavior Raw behavior.
     * @return string
     */
    private static function sanitizeBehavior(string $behavior): string
    {
        $behavior = sanitize_key($behavior);

        return in_array($behavior, AttributeContract::SUCCESS_BEHAVIORS, true)
            ? $behavior
            : 'message';
    }

    /**
     * @param string $redirect Raw redirect.
     * @return string
     */
    private static function sanitizeRedirect(string $redirect): string
    {
        $redirect = trim($redirect);
        if ($redirect === '') {
            return '';
        }

        // Allow relative same-site paths without forcing a full URL.
        if (strpos($redirect, '/') === 0 && strpos($redirect, '//') !== 0) {
            return sanitize_text_field($redirect);
        }

        $url = esc_url_raw($redirect);

        return is_string($url) ? $url : '';
    }

    /**
     * @param string $color    Raw color.
     * @param string $fallback Fallback hex.
     * @return string
     */
    private static function sanitizeColor(string $color, string $fallback): string
    {
        $sanitized = sanitize_hex_color($color);

        return is_string($sanitized) && $sanitized !== '' ? $sanitized : $fallback;
    }

    /**
     * @param string $mode Raw captcha mode.
     * @return string
     */
    private static function sanitizeCaptcha(string $mode): string
    {
        $mode = sanitize_key($mode);

        return in_array($mode, AttributeContract::CAPTCHA_MODES, true) ? $mode : 'inherit';
    }
}
