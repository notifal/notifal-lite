<?php

namespace Notifal\Modules\OnPageNotification\Application\Services;

use Notifal\Domain\Tags\Services\TagDetector;

defined('ABSPATH') || exit;

/**
 * Resolves OnPage content_source_type from template HTML merge tags.
 *
 * Static when the template has no Notifal tags; dynamic when any tag is present
 * (including site-level URL tags such as {cart_url} in text or attributes).
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class OnPageContentSourceTypeResolver
{
    /**
     * Allowed content source type slugs.
     *
     * @since 3.0.0
     * @var string[]
     */
    private const ALLOWED_TYPES = array('static', 'dynamic');

    /**
     * Apply content_source_type from inspected template HTML when content is available.
     *
     * @since 3.0.0
     * @param array<string, mixed> $contentSourceSettings Raw or partially normalized content source settings.
     * @param string               $templateHtml          Template HTML (or builder content string) to inspect.
     * @return array<string, mixed> Settings with content_source_type aligned to tag presence.
     */
    public static function applyFromTemplateHtml(array $contentSourceSettings, string $templateHtml): array
    {
        // Without template markup there is nothing to classify, keep incoming type.
        if ('' === trim($templateHtml)) {
            return self::sanitizeTypeKey($contentSourceSettings);
        }

        // Any registered Notifal merge tag (text, href, attributes) requires dynamic.
        $hasTags = TagDetector::hasAnyNotifalTags($templateHtml);

        // Align type strictly with tag presence for picker static/dynamic filtering.
        $contentSourceSettings['content_source_type'] = $hasTags ? 'dynamic' : 'static';

        return self::sanitizeTypeKey($contentSourceSettings);
    }

    /**
     * Ensure content_source_type is a safe static|dynamic slug.
     *
     * @since 3.0.0
     * @param array<string, mixed> $contentSourceSettings Content source settings.
     * @return array<string, mixed>
     */
    private static function sanitizeTypeKey(array $contentSourceSettings): array
    {
        // Read the current type when present.
        $type = isset($contentSourceSettings['content_source_type'])
            ? sanitize_key((string) $contentSourceSettings['content_source_type'])
            : '';

        // Fall back to static when missing or invalid after HTML-based resolution.
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            $contentSourceSettings['content_source_type'] = 'static';
        } else {
            $contentSourceSettings['content_source_type'] = $type;
        }

        return $contentSourceSettings;
    }
}
