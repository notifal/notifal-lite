<?php

namespace Notifal\Modules\OnPageNotification\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\AppearanceSettingsService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\BehaviorSettingsService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\ContentSourceService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\TimingSettingsService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\WooCommerceCartDisplayRulesService;
use Notifal\Modules\Templates\Application\Services\TemplateBuilderDetector;

defined('ABSPATH') || exit;

/**
 * Builds a reference export JSON sample for the OnPage AI prompt generator.
 *
 * Uses real default settings keys so AI output matches the Notifal import format.
 *
 * @since 2.4.1
 * @author Hossein <hossein@notifal.com>
 */
class OnPageAiPromptExportSample
{
    /**
     * Return a minimal valid notification export array for AI prompt embedding.
     *
     * @since 2.4.1
     * @return array<string, mixed> Export-shaped array.
     */
    public static function getSample(): array
    {
        // Load default settings from each settings service.
        $appearanceService = notifal_app(AppearanceSettingsService::class);
        $behaviorService   = notifal_app(BehaviorSettingsService::class);
        $timingService     = notifal_app(TimingSettingsService::class);
        $contentSource     = notifal_app(ContentSourceService::class);

        // Build a popup + exit-intent example with only relevant conditional fields.
        $appearance = OnPageImportSettingsNormalizer::normalizeAppearanceSettings(
            array_merge(
                $appearanceService->getDefaultSettings(),
                array(
                    'notification_display_type' => 'popup',
                    'show_animation_type'       => 'zoom',
                    'backdrop_bg_color'         => 'rgba(15, 23, 42, 0.65)',
                    'backdrop_blur'             => 3,
                )
            )
        );

        $timing = OnPageImportSettingsNormalizer::normalizeTimingSettings(
            array_merge(
                $timingService->getDefaultSettings(),
                array(
                    'show_timing'            => 'exit_intent',
                    'display_duration'       => 'until_dismissed',
                    'show_frequency'         => 'once_per_session',
                    'respect_user_dismissal' => true,
                )
            )
        );

        // Build the reference export payload.
        $sample = array(
            'version' => defined('NOTIFAL_VERSION') ? NOTIFAL_VERSION : '2.4.1',
            'type'    => 'notifal_onpage_notif',
            'notification' => array(
                'title'  => __('Your Notification Title', 'notifal'),
                'labels' => array(),
                'settings' => array(
                    'appearance'                    => $appearance,
                    'behavior'                      => $behaviorService->getDefaultSettings(),
                    'timing'                        => $timing,
                    'content_source'                => $contentSource->sanitizeSettings(
                        array('content_source_type' => 'dynamic')
                    ),
                    'display_rules'                 => self::getDisplayRulesExample(),
                    'rule_combination_logic'        => 'OR',
                    'display_rules_visibility_mode' => 'show_if',
                ),
                'template' => array(
                    'builder'      => TemplateBuilderDetector::BUILDER_HTML,
                    'content'      => self::getPlaceholderTemplateHtml(),
                    'dependencies' => array('images' => array()),
                ),
            ),
        );

        /**
         * Filter the OnPage AI prompt export sample before it is sent to JavaScript.
         *
         * @since 2.4.1
         *
         * @param array<string, mixed> $sample Reference export array.
         */
        return (array) apply_filters(FilterHooks::ONPAGE_AI_PROMPT_EXPORT_SAMPLE, $sample);
    }

    /**
     * Example display rules block using the supported items[] format.
     *
     * @since 2.4.1
     * @return array<string, mixed>
     */
    private static function getDisplayRulesExample(): array
    {
        // Default to a product page post_type rule in lite environments.
        $items = array(
            array(
                'id'   => 'rule_example_product_pages',
                'type' => 'post_type',
                'data' => array(
                    'visibility'       => 'specific',
                    'post_types'       => array('product'),
                    'items_visibility' => 'all',
                    'post_items'       => array(),
                ),
            ),
        );

        // Add a WooCommerce cart rule example when WooCommerce is active.
        if (PluginDetector::isWooCommerceActive()) {
            $items[] = array(
                'id'   => 'rule_example_cart_not_empty',
                'type' => WooCommerceCartDisplayRulesService::RULE_TYPE,
                'data' => array(
                    'condition' => 'cart_not_empty',
                ),
            );
        }

        return array('items' => $items);
    }

    /**
     * Minimal HTML Builder template placeholder shown in the export sample.
     *
     * @since 2.4.1
     * @return string
     */
    private static function getPlaceholderTemplateHtml(): string
    {
        return '<style>'
            . '.notifal-ai-card{width:100%;max-width:400px;padding:20px;border-radius:16px;background:#fff;font-family:Arial,sans-serif;box-sizing:border-box;display:flex;gap:12px;align-items:center;}'
            . '.notifal-ai-card__media{flex:0 0 64px;width:64px;height:64px;border-radius:10px;overflow:hidden;background:#f3f0fb;}'
            . '.notifal-ai-card__media .notifal-post-feature-image{width:100%;height:100%;object-fit:cover;border-radius:10px;}'
            . '.notifal-ai-card__body{flex:1;min-width:0;}'
            . '</style>'
            . '<div class="notifal-ai-card">'
            . '<div class="notifal-ai-card__media"><div class="notifal-post-feature-image"></div></div>'
            . '<div class="notifal-ai-card__body"><p class="notifal-ai-card__text">{order_meta_billing_first_name} bought {product_name}</p>'
            . '<a href="{product_link}" class="notifal-action-button">View product</a></div>'
            . '<button type="button" class="notifal-close-button" aria-label="Close">×</button></div>';
    }
}
