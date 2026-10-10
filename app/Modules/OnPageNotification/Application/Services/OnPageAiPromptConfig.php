<?php

namespace Notifal\Modules\OnPageNotification\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\Ai\Application\Services\AiIndustryOptions;
use Notifal\Modules\Ai\Application\Services\SiteProfileService;
use Notifal\Modules\Templates\Application\Services\HtmlBuilderAiPromptExamples;
use Notifal\Modules\Templates\Application\Services\HtmlBuilderDisplayLayouts;
use Notifal\Modules\Templates\Application\Services\HtmlBuilderUseCases;
use Notifal\Modules\Templates\Application\Services\HtmlBuilderWidgets;

defined('ABSPATH') || exit;

/**
 * Configuration payload for the OnPage notification AI prompt modal.
 *
 * @since 2.4.1
 * @updated 3.0.0 Expose is_woocommerce on site_context for inline preview mocks.
 * @author Hossein <hossein@notifal.com>
 */
class OnPageAiPromptConfig
{
    /**
     * Build the configuration array localized to JavaScript.
     *
     * @since 2.4.1
     * @return array<string, mixed>
     */
    public static function getConfig(): array
    {
        // Resolve saved AI preferences for wizard defaults.
        $siteProfile = notifal_app(SiteProfileService::class)->get();

        // Prefer the last generation color, then the shared builder default.
        $primaryColor = !empty($siteProfile['primary_color'])
            ? (string) $siteProfile['primary_color']
            : self::resolvePrimaryColor();

        // Assemble config from shared HTML Builder option providers.
        $config = array(
            'plugin_version'     => defined('NOTIFAL_VERSION') ? NOTIFAL_VERSION : '2.4.1',
            'primary_color'      => $primaryColor,
            'active_plugins'     => PluginDetector::getActivePluginNames(),
            'use_cases'          => HtmlBuilderUseCases::getOptions(),
            'industries'         => AiIndustryOptions::getOptions(),
            'display_layouts'    => self::getWizardDisplayLayouts(),
            'ai_prompt_examples' => HtmlBuilderAiPromptExamples::getExamples(),
            'export_sample'      => OnPageAiPromptExportSample::getSample(),
            // Same widget structures as HTML Builder AI prompts (@since 3.0.0).
            'widgets'            => HtmlBuilderWidgets::getWidgets(),
            'site_profile'       => $siteProfile,
            // Browser-safe site facts for Noti thinking copy.
            'site_context'       => self::buildSiteContext(),
        );

        /**
         * Filter OnPage AI prompt configuration before it is sent to JavaScript.
         *
         * @since 2.4.1
         *
         * @param array<string, mixed> $config Localized config array.
         */
        return (array) apply_filters(FilterHooks::ONPAGE_AI_PROMPT_CONFIG, $config);
    }

    /**
     * Return wizard-friendly layout cards (labels, order, and short descriptions).
     *
     * @since 3.0.0
     * @return array<int, array{slug: string, label: string, description: string}>
     */
    private static function getWizardDisplayLayouts(): array
    {
        // Short labels/descriptions match the hosted AI wizard layout picker mock.
        $wizardCopy = array(
            'popup'  => array(
                'label'       => __('Center Popup', 'notifal'),
                'description' => __('High attention, middle of screen', 'notifal'),
            ),
            'toast'  => array(
                'label'       => __('Floating Side', 'notifal'),
                'description' => __('Subtle, stays in the corner', 'notifal'),
            ),
            'topbar' => array(
                'label'       => __('Top Banner', 'notifal'),
                'description' => __('Sticky header announcement', 'notifal'),
            ),
            'inline' => array(
                'label'       => __('Inline (in content)', 'notifal'),
                'description' => __('Embedded inside page content with shortcode or auto placement', 'notifal'),
            ),
        );

        // Keep popup first, then toast, topbar, and inline (Pro) when available.
        $order = array('popup', 'toast', 'topbar');

        // Append inline only when Pro is active so free sites keep the three-column grid.
        if (PluginDetector::isNotifalProActive() && function_exists('is_notifal_pro_active') && is_notifal_pro_active()) {
            $order[] = 'inline';
        }
        $indexed = array();

        foreach (HtmlBuilderDisplayLayouts::getOptions() as $row) {
            $slug = isset($row['slug']) ? sanitize_key((string) $row['slug']) : '';
            if ('' !== $slug) {
                $indexed[$slug] = $row;
            }
        }

        $options = array();

        foreach ($order as $slug) {
            if (!isset($indexed[$slug])) {
                continue;
            }

            $row = $indexed[$slug];

            if (isset($wizardCopy[$slug])) {
                $row['label'] = $wizardCopy[$slug]['label'];
                $row['description'] = $wizardCopy[$slug]['description'];
            }

            $options[] = $row;
        }

        return $options;
    }

    /**
     * Build browser-safe site context for Noti thinking messages.
     *
     * @since 3.0.0
     * @return array<string, mixed>
     */
    private static function buildSiteContext(): array
    {
        // Resolve the public site name and host for personalized thoughts.
        $site_name = (string) get_bloginfo('name');
        $home_url = (string) home_url('/');
        $domain = (string) wp_parse_url($home_url, PHP_URL_HOST);

        // Count OnPage notifications across useful statuses.
        $counts = wp_count_posts('notifal_onpage_notif');
        $publish = isset($counts->publish) ? (int) $counts->publish : 0;
        $draft = isset($counts->draft) ? (int) $counts->draft : 0;
        $private = isset($counts->private) ? (int) $counts->private : 0;
        $notification_count = $publish + $draft + $private;

        // Load saved site profile for greeting name used by Noti thoughts.
        $siteProfile = notifal_app(SiteProfileService::class)->get();

        return [
            'site_name'          => sanitize_text_field($site_name),
            'domain'             => sanitize_text_field($domain !== '' ? $domain : 'your site'),
            'home_url'           => esc_url_raw($home_url),
            'notification_count' => $notification_count,
            'has_notifications'  => $notification_count > 0,
            'contact_name'       => sanitize_text_field((string) ($siteProfile['contact_name'] ?? '')),
            // WooCommerce flag drives inline MacBook product-page mocks in AI previews.
            'is_woocommerce'     => PluginDetector::isWooCommerceActive(),
        ];
    }

    /**
     * Resolve brand primary color for AI prompt defaults.
     *
     * @since 2.4.1
     * @return string Hex color.
     */
    private static function resolvePrimaryColor(): string
    {
        // Reuse the HTML Builder primary color filter for consistency.
        $color = (string) apply_filters(FilterHooks::TEMPLATE_HTML_BUILDER_PRIMARY_COLOR, '#7e2bd2');

        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            return $color;
        }

        return '#7e2bd2';
    }
}
