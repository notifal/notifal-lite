<?php

namespace Notifal\Modules\Ai\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Templates\Infrastructure\WordPress\HtmlBuilder\Services\HtmlTemplateSanitizer;

defined('ABSPATH') || exit;

/**
 * Applies a strict sanitization policy to hosted AI generated HTML content.
 *
 * Gateway output is contract-validated server-side. This layer must preserve
 * leading style blocks (including @import) while still stripping scripts
 * and other executable markup for every user capability level.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class GeneratedContentSanitizer
{
    /**
     * Sanitize generated HTML for safe builder or template import use.
     *
     * @since 3.0.0
     * @param string $html Raw generated HTML.
     * @return array{content: string, warnings: string[]} Sanitized HTML and warnings.
     */
    public function sanitizeHtml(string $html): array
    {
        // Reject empty payloads early.
        if ('' === trim($html)) {
            return [
                'content'  => '',
                'warnings' => [__('Generated HTML was empty.', 'notifal')],
            ];
        }

        // Use the trusted HTML pipeline so wp_kses does not strip <style> CSS.
        // Restricted kses removes stylesheet text and leaves markup unstyled in the builder.
        $result = HtmlTemplateSanitizer::sanitizeTrusted($html);

        // Read sanitized markup from the trusted pipeline.
        $content = (string) ($result['content'] ?? '');

        // Force-remove script tags for every account (trusted path may keep them for admins).
        $content = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $content);
        $content = is_string($content) ? $content : (string) ($result['content'] ?? '');

        // Strip dangerous embed/object/iframe tags from generated markup.
        $content = preg_replace('/<(embed|object|iframe)\b[^>]*>.*?<\/\1>/is', '', $content);
        $content = preg_replace('/<(embed|object|iframe)\b[^>]*\/?>/is', '', (string) $content);

        // Remove external stylesheet link tags (gateway allows @import inside <style> only).
        $content = preg_replace('/<link\b[^>]*rel\s*=\s*(["\']?)stylesheet\1[^>]*>/i', '', (string) $content);

        // Collect warnings from the base sanitizer.
        $warnings = is_array($result['warnings']) ? $result['warnings'] : [];

        /**
         * Filter sanitized hosted AI HTML before it is returned to clients.
         *
         * @since 3.0.0
         * @param string $content Sanitized HTML content.
         * @param string[] $warnings Sanitization warnings.
         */
        $content = (string) apply_filters(FilterHooks::AI_GENERATED_HTML_SANITIZED, $content, $warnings);

        // Return sanitized content and any user-facing warnings.
        return [
            'content'  => $content,
            'warnings' => $warnings,
        ];
    }
}
