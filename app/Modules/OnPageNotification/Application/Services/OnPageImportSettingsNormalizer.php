<?php

namespace Notifal\Modules\OnPageNotification\Application\Services;

use Notifal\Modules\OnPageNotification\Application\Services\Settings\AppearanceSettingsService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\ContentSourceService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\DisplayRulesDataNormalizer;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\TimingSettingsService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\WooCommerceCartDisplayRulesService;

defined('ABSPATH') || exit;

/**
 * Normalizes AI-generated or loosely shaped OnPage notification settings before import sanitization.
 *
 * @since 2.4.1
 * @updated 3.0.0 Drop invented sitewide post_type visibility=all rules on import.
 * @author Hossein <hossein@notifal.com>
 */
class OnPageImportSettingsNormalizer
{
    /**
     * Normalize all notification settings blocks from an import payload.
     *
     * When template HTML is provided, content_source_type is forced to dynamic
     * if any Notifal merge tag is present, otherwise static.
     *
     * @since 2.4.1
     * @param array<string, mixed> $settings     Raw notification.settings array.
     * @param string               $templateHtml Optional template HTML used to resolve content_source_type.
     * @return array<string, mixed> Normalized settings.
     */
    public static function normalize(array $settings, string $templateHtml = ''): array
    {
        // Copy the settings array so the original import payload is not mutated.
        $normalized = $settings;

        // Normalize each supported settings group when present.
        if (isset($normalized['appearance']) && is_array($normalized['appearance'])) {
            $normalized['appearance'] = self::normalizeAppearanceSettings($normalized['appearance']);
        }

        if (isset($normalized['timing']) && is_array($normalized['timing'])) {
            $normalized['timing'] = self::normalizeTimingSettings($normalized['timing']);
        }

        // Ensure content_source exists so tag-based type resolution can always run.
        if (!isset($normalized['content_source']) || !is_array($normalized['content_source'])) {
            $normalized['content_source'] = array();
        }

        // Strip invented keys and sanitize supported content source fields first.
        $normalized['content_source'] = self::normalizeContentSourceSettings($normalized['content_source']);

        // Override content_source_type from template HTML tag presence when available.
        $normalized['content_source'] = OnPageContentSourceTypeResolver::applyFromTemplateHtml(
            $normalized['content_source'],
            $templateHtml
        );

        if (isset($normalized['display_rules']) && is_array($normalized['display_rules'])) {
            $normalized['display_rules'] = self::normalizeDisplayRules($normalized['display_rules']);
        }

        return $normalized;
    }

    /**
     * Reset appearance keys that do not apply to the selected display type.
     *
     * @since 2.4.1
     * @param array<string, mixed> $settings Appearance settings.
     * @return array<string, mixed>
     */
    public static function normalizeAppearanceSettings(array $settings): array
    {
        // Load canonical defaults from the appearance service.
        $defaults = notifal_app(AppearanceSettingsService::class)->getDefaultSettings();
        $merged   = array_merge($defaults, $settings);
        $type     = sanitize_key((string) ($merged['notification_display_type'] ?? 'toast'));

        // Top-bar fields apply only when the display type is topbar.
        if ($type !== 'topbar') {
            $merged['desktop_bar_position']    = $defaults['desktop_bar_position'];
            $merged['desktop_bar_distance']    = $defaults['desktop_bar_distance'];
            $merged['mobile_bar_position']     = $defaults['mobile_bar_position'];
            $merged['mobile_bar_distance']     = $defaults['mobile_bar_distance'];
            $merged['topbar_placement']        = $defaults['topbar_placement'];
            $merged['topbar_sticky_on_scroll'] = $defaults['topbar_sticky_on_scroll'];
        }

        // Popup backdrop fields apply only when the display type is popup.
        if ($type !== 'popup') {
            $merged['backdrop_bg_color'] = $defaults['backdrop_bg_color'];
            $merged['backdrop_blur']     = $defaults['backdrop_blur'];
        }

        // Toast and floating types use desktop/mobile position distances.
        if (!in_array($type, array('toast', 'floating', 'corner'), true)) {
            $merged['desktop_position']        = $defaults['desktop_position'];
            $merged['desktop_top_distance']    = $defaults['desktop_top_distance'];
            $merged['desktop_bottom_distance'] = $defaults['desktop_bottom_distance'];
            $merged['desktop_left_distance']   = $defaults['desktop_left_distance'];
            $merged['desktop_right_distance']  = $defaults['desktop_right_distance'];
            $merged['mobile_position']         = $defaults['mobile_position'];
            $merged['mobile_top_distance']     = $defaults['mobile_top_distance'];
            $merged['mobile_bottom_distance']  = $defaults['mobile_bottom_distance'];
        }

        return $merged;
    }

    /**
     * Reset timing keys that do not apply to the selected show_timing trigger.
     *
     * @since 2.4.1
     * @param array<string, mixed> $settings Timing settings.
     * @return array<string, mixed>
     */
    public static function normalizeTimingSettings(array $settings): array
    {
        // Load canonical defaults from the timing service.
        $defaults = notifal_app(TimingSettingsService::class)->getDefaultSettings();
        $merged   = array_merge($defaults, $settings);
        $trigger  = sanitize_key((string) ($merged['show_timing'] ?? 'immediate'));

        // Delay fields apply only for the delay trigger.
        if ($trigger !== 'delay') {
            $merged['delay_seconds'] = $defaults['delay_seconds'];
        }

        // Scroll fields apply only for the scroll trigger.
        if ($trigger !== 'scroll') {
            $merged['scroll_percentage'] = $defaults['scroll_percentage'];
        }

        // Idle fields apply only for the idle trigger.
        if ($trigger !== 'idle') {
            $merged['idle_seconds'] = $defaults['idle_seconds'];
        }

        // Custom trigger fields apply only for the custom trigger.
        if ($trigger !== 'custom') {
            $merged['custom_trigger_type']       = $defaults['custom_trigger_type'];
            $merged['custom_js_event']           = $defaults['custom_js_event'];
            $merged['custom_element_selector']   = $defaults['custom_element_selector'];
            $merged['visibility_element_selector'] = $defaults['visibility_element_selector'];
            $merged['visibility_threshold']    = $defaults['visibility_threshold'];
            $merged['custom_js_condition']       = $defaults['custom_js_condition'];
            $merged['multiple_triggers_config']  = $defaults['multiple_triggers_config'];
            $merged['custom_trigger_delay']      = $defaults['custom_trigger_delay'];
        }

        return $merged;
    }

    /**
     * Strip invented AI keys and keep only supported content source fields.
     *
     * @since 2.4.1
     * @param array<string, mixed> $settings Content source settings.
     * @return array<string, mixed>
     */
    public static function normalizeContentSourceSettings(array $settings): array
    {
        // Re-sanitize through the service so only valid keys remain.
        $service = notifal_app(ContentSourceService::class);

        // Remove unsupported AI-only keys before sanitization.
        unset($settings['dynamic_source'], $settings['filters']);

        return $service->sanitizeSettings($settings);
    }

    /**
     * Convert AI display rule shapes into Notifal items[] storage format.
     *
     * @since 2.4.1
     * @updated 3.0.0 Strip invented post_type visibility=all filler rules after conversion.
     * @param array<string, mixed> $displayRules Raw display rules block.
     * @return array<string, mixed>
     */
    public static function normalizeDisplayRules(array $displayRules): array
    {
        // Already in supported items format: still strip filler sitewide post_type rules.
        if (DisplayRulesDataNormalizer::isItemsFormat($displayRules)) {
            return DisplayRulesDataNormalizer::wrapItems(
                DisplayRulesDataNormalizer::stripInventedSitewidePostTypeRules(
                    DisplayRulesDataNormalizer::extractItems($displayRules)
                )
            );
        }

        // AI often uses { rules: [ { rule_type, operator, value } ] }.
        if (isset($displayRules['rules']) && is_array($displayRules['rules'])) {
            $items = array();

            foreach ($displayRules['rules'] as $rule) {
                if (!is_array($rule)) {
                    continue;
                }

                // Convert a single AI rule row into a Notifal item.
                $converted = self::convertAiDisplayRule($rule);
                if ($converted === null) {
                    continue;
                }

                $items[] = array(
                    'id'   => DisplayRulesDataNormalizer::generateRuleId(),
                    'type' => $converted['type'],
                    'data' => $converted['data'],
                );
            }

            return DisplayRulesDataNormalizer::wrapItems(
                DisplayRulesDataNormalizer::stripInventedSitewidePostTypeRules(
                    self::deduplicateDisplayRuleItems($items)
                )
            );
        }

        // Empty or unknown shapes become an empty items list.
        if (!is_array($displayRules) || $displayRules === array()) {
            return DisplayRulesDataNormalizer::wrapItems(array());
        }

        // Normalize legacy keyed shapes then drop filler post_type=all rows.
        return DisplayRulesDataNormalizer::wrapItems(
            DisplayRulesDataNormalizer::stripInventedSitewidePostTypeRules(
                DisplayRulesDataNormalizer::extractItems($displayRules)
            )
        );
    }

    /**
     * Map one AI display rule row to a Notifal rule item.
     *
     * @since 2.4.1
     * @param array<string, mixed> $rule Raw AI rule row.
     * @return array{type: string, data: array<string, mixed>}|null
     */
    private static function convertAiDisplayRule(array $rule): ?array
    {
        // Native Notifal item rows may already include type/data.
        if (isset($rule['type']) && is_string($rule['type']) && isset($rule['data']) && is_array($rule['data'])) {
            return array(
                'type' => $rule['type'],
                'data' => $rule['data'],
            );
        }

        $ruleType = sanitize_key((string) ($rule['rule_type'] ?? $rule['type'] ?? ''));
        $value    = sanitize_key((string) ($rule['value'] ?? ''));

        // Convert generic page_type AI rules into supported Notifal rule types.
        if ($ruleType === 'page_type') {
            if (in_array($value, array('cart', 'checkout'), true) && WooCommerceCartDisplayRulesService::isAvailable()) {
                return array(
                    'type' => WooCommerceCartDisplayRulesService::RULE_TYPE,
                    'data' => array('condition' => 'cart_not_empty'),
                );
            }

            if (in_array($value, array('product', 'shop'), true)) {
                return array(
                    'type' => 'post_type',
                    'data' => array(
                        'visibility'       => 'specific',
                        'post_types'       => array('product'),
                        'items_visibility' => 'all',
                        'post_items'       => array(),
                    ),
                );
            }

            if ($value === 'page') {
                return array(
                    'type' => 'post_type',
                    'data' => array(
                        'visibility'       => 'specific',
                        'post_types'       => array('page'),
                        'items_visibility' => 'all',
                        'post_items'       => array(),
                    ),
                );
            }
        }

        return null;
    }

    /**
     * Remove duplicate converted display rules by type and data fingerprint.
     *
     * @since 2.4.1
     * @param array<int, array<string, mixed>> $items Rule items.
     * @return array<int, array<string, mixed>>
     */
    private static function deduplicateDisplayRuleItems(array $items): array
    {
        $unique  = array();
        $seen    = array();

        foreach ($items as $item) {
            // Build a stable fingerprint for deduplication.
            $fingerprint = wp_json_encode(array($item['type'] ?? '', $item['data'] ?? array()));
            if ($fingerprint === false || isset($seen[ $fingerprint ])) {
                continue;
            }

            $seen[ $fingerprint ] = true;
            $unique[]             = $item;
        }

        return $unique;
    }
}
