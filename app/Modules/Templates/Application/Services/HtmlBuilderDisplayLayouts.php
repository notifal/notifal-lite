<?php

namespace Notifal\Modules\Templates\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\AppearanceSettingsService;

defined('ABSPATH') || exit;

/**
 * Display layout options for the HTML Builder AI prompt helper.
 *
 * Reuses OnPage Notification appearance display types so labels stay in sync.
 *
 * @since 2.4.1
 * @author Hossein <hossein@notifal.com>
 */
class HtmlBuilderDisplayLayouts
{
    /**
     * Return localized display layout options for the builder UI.
     *
     * @return array<int, array{slug: string, label: string, description: string}>
     * @since 2.4.1
     */
    public static function getOptions(): array
    {
        // Mirror appearance settings display types (toast, popup, topbar).
        $options = array();

        foreach (AppearanceSettingsService::getDisplayTypeOptions() as $option) {
            // Read the internal display type slug from the appearance option row.
            $slug = isset($option['value']) ? sanitize_key((string) $option['value']) : '';

            if ('' === $slug) {
                continue;
            }

            // Build each row with the same label and description used in Appearance settings.
            $options[] = array(
                'slug'        => $slug,
                'label'       => isset($option['label']) ? (string) $option['label'] : $slug,
                'description' => AppearanceSettingsService::getDisplayTypeDescription($slug),
            );
        }

        /**
         * Filter HTML Builder display layout options before they are sent to JavaScript.
         *
         * @since 2.4.1
         *
         * @param array<int, array{slug: string, label: string, description: string}> $options Layout rows.
         */
        return (array) apply_filters(FilterHooks::TEMPLATE_HTML_BUILDER_DISPLAY_LAYOUTS, $options);
    }
}
