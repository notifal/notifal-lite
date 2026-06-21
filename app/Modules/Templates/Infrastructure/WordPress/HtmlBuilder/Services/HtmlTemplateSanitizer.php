<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\HtmlBuilder\Services;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Sanitizes raw HTML template content for the HTML Builder.
 *
 * Preserves structure, style blocks, and scripts for trusted users while
 * stripping dangerous patterns for all users.
 *
 * @since 2.4.0
 * @author Hossein <hossein@notifal.com>
 */
class HtmlTemplateSanitizer
{
    /**
     * Sanitize HTML using the appropriate capability path.
     *
     * @param string $raw Raw HTML pasted by the user.
     * @return array{content: string, warnings: string[]} Sanitized content and user warnings.
     * @since 2.4.0
     */
    public static function sanitize(string $raw): array
    {
        // Trusted users may keep script tags after base sanitization.
        if (current_user_can('unfiltered_html')) {
            return self::sanitizeTrusted($raw);
        }

        // Restricted users lose script tags and pass through custom kses.
        return self::sanitizeRestricted($raw);
    }

    /**
     * Sanitize HTML for users with the unfiltered_html capability.
     *
     * @param string $raw Raw HTML pasted by the user.
     * @return array{content: string, warnings: string[]} Sanitized content and user warnings.
     * @since 2.4.0
     */
    public static function sanitizeTrusted(string $raw): array
    {
        // Allow extensions to preprocess raw HTML.
        $content = (string) apply_filters(FilterHooks::TEMPLATE_HTML_SANITIZE_BEFORE, $raw);
        $warnings = [];

        // Remove null bytes and invalid UTF-8 sequences.
        $content = self::stripNullBytes($content);
        $content = self::ensureValidUtf8($content);

        // PHP execution inside templates is never supported.
        if (self::containsPhpTags($content)) {
            $content = self::stripPhpTags($content);
            $warnings[] = __('PHP is not supported. Use Notifal tags or WordPress shortcodes.', 'notifal');
        }

        // Strip inline event handlers and javascript: URLs for every user.
        $content = self::stripEventHandlers($content);
        $content = self::stripJavascriptUrls($content);

        // Allow extensions to post-process sanitized HTML.
        $content = (string) apply_filters(FilterHooks::TEMPLATE_HTML_SANITIZE_AFTER, $content, $warnings);

        return [
            'content'  => $content,
            'warnings' => $warnings,
        ];
    }

    /**
     * Sanitize HTML for users without the unfiltered_html capability.
     *
     * @param string $raw Raw HTML pasted by the user.
     * @return array{content: string, warnings: string[]} Sanitized content and user warnings.
     * @since 2.4.0
     */
    public static function sanitizeRestricted(string $raw): array
    {
        // Run the shared trusted pipeline first.
        $result = self::sanitizeTrusted($raw);

        // Remove script tags for restricted accounts.
        if (self::containsScriptTags($result['content'])) {
            $result['content'] = self::stripScriptTags($result['content']);
            $result['warnings'][] = __(
                'Script tags were removed because your account does not have permission to use unfiltered HTML.',
                'notifal'
            );
        }

        // Apply filtered kses rules that preserve style blocks.
        $allowed = apply_filters(FilterHooks::TEMPLATE_HTML_KSES_ALLOWED, self::getRestrictedAllowedTags());
        $result['content'] = wp_kses($result['content'], $allowed);

        return $result;
    }

    /**
     * Build the default kses allow-list for restricted HTML Builder saves.
     *
     * @return array<string, array<string, bool>> wp_kses allowed tags array.
     * @since 2.4.0
     */
    private static function getRestrictedAllowedTags(): array
    {
        // Start from post context and extend with style support.
        $allowed = wp_kses_allowed_html('post');
        $allowed['style'] = [
            'type'  => true,
            'media' => true,
        ];

        return $allowed;
    }

    /**
     * Remove null bytes from a string.
     *
     * @param string $content Input string.
     * @return string Clean string.
     * @since 2.4.0
     */
    private static function stripNullBytes(string $content): string
    {
        return str_replace("\0", '', $content);
    }

    /**
     * Ensure the string is valid UTF-8, stripping invalid sequences.
     *
     * @param string $content Input string.
     * @return string UTF-8 safe string.
     * @since 2.4.0
     */
    private static function ensureValidUtf8(string $content): string
    {
        if (function_exists('mb_check_encoding') && !mb_check_encoding($content, 'UTF-8')) {
            return (string) mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        }

        return $content;
    }

    /**
     * Detect PHP open/close tags in HTML content.
     *
     * @param string $content HTML string.
     * @return bool True when PHP tags are present.
     * @since 2.4.0
     */
    public static function containsPhpTags(string $content): bool
    {
        return (bool) preg_match('/<\?(?:php)?[\s\S]*?\?>/i', $content);
    }

    /**
     * Strip PHP tags from HTML content.
     *
     * @param string $content HTML string.
     * @return string HTML without PHP tags.
     * @since 2.4.0
     */
    private static function stripPhpTags(string $content): string
    {
        return (string) preg_replace('/<\?(?:php)?[\s\S]*?\?>/i', '', $content);
    }

    /**
     * Detect script tags in HTML content.
     *
     * @param string $content HTML string.
     * @return bool True when script tags are present.
     * @since 2.4.0
     */
    public static function containsScriptTags(string $content): bool
    {
        return (bool) preg_match('/<script\b[^>]*>[\s\S]*?<\/script>/i', $content);
    }

    /**
     * Strip script tags from HTML content.
     *
     * @param string $content HTML string.
     * @return string HTML without script tags.
     * @since 2.4.0
     */
    private static function stripScriptTags(string $content): string
    {
        return (string) preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $content);
    }

    /**
     * Remove inline on* event handler attributes.
     *
     * @param string $content HTML string.
     * @return string HTML without inline event handlers.
     * @since 2.4.0
     */
    private static function stripEventHandlers(string $content): string
    {
        return (string) preg_replace('/\s+on[a-z]+\s*=\s*(["\']).*?\1/i', '', $content);
    }

    /**
     * Neutralize javascript: URLs in href and src attributes.
     *
     * @param string $content HTML string.
     * @return string HTML without javascript: URLs.
     * @since 2.4.0
     */
    private static function stripJavascriptUrls(string $content): string
    {
        return (string) preg_replace(
            '/\s(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i',
            ' $1=$2#$2',
            $content
        );
    }
}
