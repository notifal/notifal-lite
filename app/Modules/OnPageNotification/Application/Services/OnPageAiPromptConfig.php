<?php

namespace Notifal\Modules\OnPageNotification\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\Templates\Application\Services\HtmlBuilderAiPromptExamples;
use Notifal\Modules\Templates\Application\Services\HtmlBuilderDisplayLayouts;
use Notifal\Modules\Templates\Application\Services\HtmlBuilderUseCases;

defined('ABSPATH') || exit;

/**
 * Configuration payload for the OnPage notification AI prompt modal.
 *
 * @since 2.4.1
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
        // Assemble config from shared HTML Builder option providers.
        $config = array(
            'plugin_version'     => defined('NOTIFAL_VERSION') ? NOTIFAL_VERSION : '2.4.1',
            'primary_color'      => self::resolvePrimaryColor(),
            'active_plugins'     => PluginDetector::getActivePluginNames(),
            'use_cases'          => HtmlBuilderUseCases::getOptions(),
            'display_layouts'    => HtmlBuilderDisplayLayouts::getOptions(),
            'ai_prompt_examples' => HtmlBuilderAiPromptExamples::getExamples(),
            'export_sample'      => OnPageAiPromptExportSample::getSample(),
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
