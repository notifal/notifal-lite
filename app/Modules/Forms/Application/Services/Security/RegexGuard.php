<?php

namespace Notifal\Modules\Forms\Application\Services\Security;

defined('ABSPATH') || exit;

/**
 * Fail-closed regex helpers for form field patterns (ReDoS mitigation).
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class RegexGuard
{
    /**
     * Maximum allowed pattern length.
     *
     * @var int
     */
    private const MAX_LENGTH = 120;

    /**
     * Sanitize a user/AI-provided regex pattern or return empty when unsafe.
     *
     * @param string $pattern Raw pattern without delimiters.
     * @return string Safe pattern or empty string.
     * @since 3.0.0
     */
    public static function sanitizePattern(string $pattern): string
    {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return '';
        }

        if (strlen($pattern) > self::MAX_LENGTH) {
            return '';
        }

        // Reject nested quantifiers and other catastrophic shapes.
        if (preg_match('/(\+|\*|\{\d+,?\d*\})\s*(\+|\*|\{\d+,?\d*\})/', $pattern)) {
            return '';
        }

        if (preg_match('/\(\?[^)]*\)\+/', $pattern)) {
            return '';
        }

        // Compile check with delimiter; fail closed on error.
        set_error_handler(static function () {
            return true;
        });
        $ok = @preg_match('#' . str_replace('#', '\#', $pattern) . '#u', '');
        restore_error_handler();

        if ($ok === false) {
            return '';
        }

        return $pattern;
    }

    /**
     * Match a value against a sanitized pattern; fail closed on compile issues.
     *
     * @param string $pattern Sanitized pattern.
     * @param string $value   Subject value.
     * @return bool
     * @since 3.0.0
     */
    public static function matches(string $pattern, string $value): bool
    {
        $safe = self::sanitizePattern($pattern);
        if ($safe === '') {
            return false;
        }

        set_error_handler(static function () {
            return true;
        });
        $result = @preg_match('#' . str_replace('#', '\#', $safe) . '#u', $value);
        restore_error_handler();

        return $result === 1;
    }
}
