<?php

namespace Notifal\Domain\Tags;

defined('ABSPATH') || exit;

/**
 * Class TagsHelper
 *
 * Provides utility functions specifically for tag operations.
 *
 * @package Notifal\Domain\Tags
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class TagsHelper
{
    /**
     * Sanitize tag value to prevent HTML injection while preserving intended formatting.
     *
     * Allows safe HTML formatting for prices, links, etc. but prevents malicious scripts.
     *
     * @param string $value The tag value to sanitize.
     * @return string Sanitized value safe for HTML output.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function sanitizeTagValue(string $value): string
    {
        // If value is empty, return as-is
        if (empty($value)) {
            return $value;
        }

        // If value contains price formatting (WooCommerce), preserve it
        if (preg_match('/<span[^>]*class[^>]*woocommerce-Price/', $value)) {
            return wp_kses($value, [
                'span' => [
                    'class' => [],
                    'style' => [],
                ],
                'bdi' => [],
            ]);
        }

        // If value is a URL, preserve it as-is (for product links, etc.)
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return esc_url($value);
        }

        // For regular text, escape HTML but preserve basic formatting
        return esc_html($value);
    }

    /**
     * Determine whether tag rendering runs in admin/builder preview mode.
     *
     * @param array<string, mixed> $context Tag resolution context.
     * @return bool True when preview fallbacks should apply.
     * @since 2.4.0
     */
    public static function isPreviewContext(array $context): bool
    {
        return isset($context['is_preview']) && $context['is_preview'] === true;
    }

    /**
     * Sanitize a resolved tag value and preserve the placeholder in preview when empty.
     *
     * In the HTML Builder (and other previews), an empty resolved value should show
     * the original tag token (e.g. `{cart_first_product_name}`) so editors can see
     * which merge tags are present when sample data is unavailable.
     *
     * @param mixed                $value       Raw value returned by the tag resolver.
     * @param string               $placeholder Full tag token from the template content.
     * @param array<string, mixed> $context     Tag resolution context.
     * @return string Value safe for HTML output, or the untouched placeholder in preview.
     * @since 2.4.0
     */
    public static function formatRenderedTagValue($value, string $placeholder, array $context): string
    {
        // Normalize resolver output to a string before sanitization.
        $stringValue = is_scalar($value) ? (string) $value : '';

        // Apply standard output sanitization for non-empty values.
        $sanitized = self::sanitizeTagValue($stringValue);

        // In preview mode, keep the merge tag visible when no data was resolved.
        if ($sanitized === '' && self::isPreviewContext($context)) {
            return $placeholder;
        }

        return $sanitized;
    }

    /**
     * Group tags by their categories.
     *
     * This method provides a standardized way to group tags by category
     * ensuring consistent ordering and organization across the application.
     *
     * @param Tag[] $tags Array of Tag objects to group.
     * @param bool $includeTagData Whether to include full tag data (key, label, description) in the result.
     * @return array Grouped tags by category. If $includeTagData is true, returns array with tag data arrays,
     *               otherwise returns array with Tag objects.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function groupByCategory(array $tags, bool $includeTagData = false): array
    {
        $grouped = [];

        foreach ($tags as $tag) {
            $category = $tag->getCategory();

            if ($includeTagData) {
                $grouped[$category][] = [
                    'key'         => $tag->getKey(),
                    'label'       => $tag->getLabel(),
                    'description' => $tag->getDescription(),
                ];
            } else {
                if (!isset($grouped[$category])) {
                    $grouped[$category] = [];
                }
                $grouped[$category][] = $tag;
            }
        }

        // Sort categories by priority and name for consistent ordering
        $categoryOrder = ['users', 'products', 'orders', 'cart'];

        uksort($grouped, function($a, $b) use ($categoryOrder) {
            $aIndex = array_search($a, $categoryOrder);
            $bIndex = array_search($b, $categoryOrder);

            // If both are in the predefined order, sort by their position
            if ($aIndex !== false && $bIndex !== false) {
                return $aIndex - $bIndex;
            }

            // Predefined categories come first
            if ($aIndex !== false) return -1;
            if ($bIndex !== false) return 1;

            // For custom categories, sort alphabetically
            return strcmp($a, $b);
        });

        // Sort tags within each category by key
        if (!$includeTagData) {
            foreach ($grouped as $category => &$categoryTags) {
                usort($categoryTags, function($a, $b) {
                    return strcmp($a->getKey(), $b->getKey());
                });
            }
        }

        return $grouped;
    }
}
