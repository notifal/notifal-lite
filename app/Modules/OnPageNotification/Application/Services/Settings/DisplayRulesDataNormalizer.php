<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Settings;

defined('ABSPATH') || exit;

/**
 * Normalizes display rules between legacy (keyed by type) and list (items[]) storage.
 *
 * @since 2.3.5
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Application\Services\Settings
 */
class DisplayRulesDataNormalizer
{
    /**
     * Show the notification when combined rules match.
     *
     * @since 2.3.5
     */
    public const VISIBILITY_SHOW_IF = 'show_if';

    /**
     * Hide the notification when combined rules match.
     *
     * @since 2.3.5
     */
    public const VISIBILITY_HIDE_IF = 'hide_if';

    /**
     * Allowed visibility mode values.
     *
     * @since 2.3.5
     * @var array<int, string>
     */
    private const VISIBILITY_MODES = [
        self::VISIBILITY_SHOW_IF,
        self::VISIBILITY_HIDE_IF,
    ];

    /**
     * Legacy rule type keys that map to post_type in the admin UI.
     *
     * @since 2.3.5
     * @var array<int, string>
     */
    private const LEGACY_POST_TYPE_ALIASES = ['pages', 'posts', 'products'];

    /**
     * Whether stored data uses the list format with an `items` array.
     *
     * @param array<string, mixed> $data Raw display rules meta.
     * @return bool True when list format is detected.
     * @since 2.3.5
     */
    public static function isItemsFormat(array $data): bool
    {
        return isset($data['items']) && is_array($data['items']);
    }

    /**
     * Count rule entries regardless of storage format.
     *
     * @param array<string, mixed> $data Raw display rules meta.
     * @return int Number of rules.
     * @since 2.3.5
     */
    public static function countItems(array $data): int
    {
        return count(self::extractItems($data));
    }

    /**
     * Whether at least one display rule is configured.
     *
     * Empty legacy arrays and list payloads such as `{ "items": [] }` are treated as no rules.
     *
     * @param array<string, mixed>|mixed $data Raw display rules meta.
     * @return bool True when one or more rules exist.
     * @since 2.3.5
     */
    public static function hasActiveRules($data): bool
    {
        if (!is_array($data) || $data === []) {
            return false;
        }

        return self::countItems($data) > 0;
    }

    /**
     * Extract a flat list of rule items from any supported storage shape.
     *
     * Each item: `['id' => string, 'type' => string, 'data' => array]`.
     *
     * @param array<string, mixed> $data Raw display rules meta.
     * @return array<int, array<string, mixed>> Normalized rule items.
     * @since 2.3.5
     */
    public static function extractItems(array $data): array
    {
        if (empty($data)) {
            return [];
        }

        if (self::isItemsFormat($data)) {
            return self::normalizeItemList($data['items']);
        }

        $items = [];

        foreach ($data as $ruleType => $ruleData) {
            if (!is_string($ruleType) || !is_array($ruleData)) {
                continue;
            }

            $type = in_array($ruleType, self::LEGACY_POST_TYPE_ALIASES, true)
                ? 'post_type'
                : $ruleType;

            $normalizedData = $ruleData;

            if (in_array($ruleType, self::LEGACY_POST_TYPE_ALIASES, true)) {
                $normalizedData = self::convertLegacyPostTypeRule($ruleType, $ruleData);
            }

            $items[] = [
                'id'   => self::generateRuleId(),
                'type' => $type,
                'data' => $normalizedData,
            ];
        }

        return $items;
    }

    /**
     * Wrap sanitized items for post meta storage.
     *
     * @param array<int, array<string, mixed>> $items Sanitized rule items.
     * @return array<string, mixed> Storage payload.
     * @since 2.3.5
     */
    public static function wrapItems(array $items): array
    {
        return [
            'items' => array_values($items),
        ];
    }

    /**
     * Sanitize visibility mode from admin input.
     *
     * @param string $mode Raw visibility mode.
     * @return string Sanitized mode.
     * @since 2.3.5
     */
    public static function sanitizeVisibilityMode(string $mode): string
    {
        return in_array($mode, self::VISIBILITY_MODES, true)
            ? $mode
            : self::VISIBILITY_SHOW_IF;
    }

    /**
     * Generate a unique rule id for new admin rules.
     *
     * @return string Rule identifier.
     * @since 2.3.5
     */
    public static function generateRuleId(): string
    {
        return 'rule_' . wp_generate_password(12, false, false);
    }

    /**
     * Normalize and validate each entry in an items array.
     *
     * @param array<int, mixed> $items Raw items from storage or POST.
     * @return array<int, array<string, mixed>> Clean item list.
     * @since 2.3.5
     */
    private static function normalizeItemList(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = isset($item['type']) ? (string) $item['type'] : '';
            $data = isset($item['data']) && is_array($item['data']) ? $item['data'] : [];

            if ($type === '') {
                continue;
            }

            $id = isset($item['id']) && is_string($item['id']) && $item['id'] !== ''
                ? sanitize_key($item['id'])
                : self::generateRuleId();

            if (in_array($type, self::LEGACY_POST_TYPE_ALIASES, true)) {
                $type = 'post_type';
                $data = self::convertLegacyPostTypeRule((string) $item['type'], $data);
            }

            $normalized[] = [
                'id'   => $id,
                'type' => $type,
                'data' => $data,
            ];
        }

        return $normalized;
    }

    /**
     * Convert legacy pages/posts/products rule data to post_type shape.
     *
     * @param string               $legacyType Original rule type key.
     * @param array<string, mixed> $ruleData   Legacy rule payload.
     * @return array<string, mixed> Post type rule data.
     * @since 2.3.5
     */
    private static function convertLegacyPostTypeRule(string $legacyType, array $ruleData): array
    {
        return [
            'visibility'       => $ruleData['visibility'] ?? 'all',
            'post_types'       => $ruleData['post_types'] ?? [$legacyType],
            'items_visibility' => $ruleData['items_visibility'] ?? 'all',
            'post_items'       => $ruleData['post_items'] ?? [],
        ];
    }
}
