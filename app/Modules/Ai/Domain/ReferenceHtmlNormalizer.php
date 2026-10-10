<?php

namespace Notifal\Modules\Ai\Domain;

defined('ABSPATH') || exit;

/**
 * Hardens user-pasted reference HTML before hosted AI generation.
 *
 * Preserves style blocks and structure for the model while removing executable
 * and high-risk constructs. Reference HTML must never be printed as admin HTML.
 *
 * Mirrors notifal-ai-gateway ReferenceHtmlNormalizer security rules.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ReferenceHtmlNormalizer
{
    /**
     * Maximum UTF-8 bytes accepted for one reference HTML payload.
     *
     * Must stay in sync with gateway ReferenceHtmlNormalizer::MAX_BYTES.
     *
     * @var int
     */
    public const MAX_BYTES = 100000;

    /**
     * Harden and bound raw reference HTML for model consumption.
     *
     * @since 3.0.0
     * @param mixed $raw Raw client reference HTML.
     * @return string Hardened markup or empty string when none / invalid.
     */
    public static function normalize($raw): string
    {
        // Require a non-empty string payload.
        if (!is_string($raw)) {
            return '';
        }

        // Reject empty reference payloads early.
        $html = trim($raw);
        if ('' === $html) {
            return '';
        }

        // Enforce the byte ceiling before heavier processing.
        if (self::MAX_BYTES < strlen($html)) {
            return '';
        }

        // Drop null bytes and invalid UTF-8 sequences.
        $html = str_replace("\0", '', $html);
        $html = (string) wp_check_invalid_utf8($html);

        // PHP tags are never allowed inside AI reference markup.
        $html = (string) preg_replace('/<\?(?:php)?[\s\S]*?\?>/i', '', $html);

        // Strip executable and embeddable tags while keeping <style> content.
        $html = (string) preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $html);
        $html = (string) preg_replace('/<(iframe|object|embed|applet)\b[^>]*>[\s\S]*?<\/\1>/i', '', $html);
        $html = (string) preg_replace('/<(iframe|object|embed|applet)\b[^>]*\/?>/i', '', $html);

        // Drop external stylesheet links (Google Fonts must use @import in <style>).
        $html = (string) preg_replace('/<link\b[^>]*>/i', '', $html);

        // Remove inline event handlers such as onclick / onload.
        $html = (string) preg_replace('/\s+on[a-z]+\s*=\s*(["\']).*?\1/i', '', $html);
        $html = (string) preg_replace('/\s+on[a-z]+\s*=\s*[^\s>]+/i', '', $html);

        // Neutralize javascript: / vbscript: URLs in common attributes.
        $html = (string) preg_replace(
            '/\s(href|src|xlink:href|action|formaction)\s*=\s*(["\'])\s*(?:javascript|vbscript):[^"\']*\2/i',
            ' $1=$2#$2',
            $html
        );

        // Neutralize data:text/html payloads that can carry nested markup.
        $html = (string) preg_replace(
            '/\s(href|src)\s*=\s*(["\'])\s*data:\s*text\/html[^"\']*\2/i',
            ' $1=$2#$2',
            $html
        );

        // Return the hardened markup for intent.reference_html.
        return trim($html);
    }

    /**
     * Whether a raw payload exceeds the accepted reference HTML byte ceiling.
     *
     * @since 3.0.0
     * @param mixed $raw Raw client reference HTML.
     * @return bool True when the payload is a too-long string.
     */
    public static function exceedsLimit($raw): bool
    {
        // Only string payloads can exceed the byte budget.
        return is_string($raw) && self::MAX_BYTES < strlen($raw);
    }
}
