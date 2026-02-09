<?php

namespace Notifal\Modules\OnPageNotification\Helpers;

defined('ABSPATH') || exit;

/**
 * Helper class for parsing and building filters for pre-created notifications.
 *
 * Provides centralized logic for parsing URL parameters and building API query arguments
 * for pre-created notification filtering functionality.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class PreCreatedNotificationFilterHelper
{
    /**
     * Parse current filter state from URL parameters.
     *
     * Extracts and sanitizes all filter parameters from the current request.
     *
     * @since 2.0.0
     * @return array<string, mixed> Array of parsed filter values
     */
    public static function parseCurrentFilters(): array
    {
        return array(
            'search'    => UrlParameterParser::getTextField('search'),
            'orderby'   => UrlParameterParser::getTextField('orderby', 'recent'),
            'use_case'  => UrlParameterParser::parseCommaSeparated('use_cases'),
            'event'     => UrlParameterParser::parseCommaSeparated('events'),
            'industry'  => UrlParameterParser::parseCommaSeparated('industries'),
            'layout'    => UrlParameterParser::parseCommaSeparated('layouts'),
            'plugin'    => UrlParameterParser::parseCommaSeparated('used_plugins'),
            'is_pro'    => UrlParameterParser::getTextField('is_pro'),
        );
    }

    /**
     * Build API query parameters from filter values.
     *
     * Converts parsed filter values into API-compatible query parameters.
     *
     * @since 2.0.0
     * @param array<string, mixed> $currentFilters Parsed filter values
     * @param array<string, mixed> $defaults Default query parameters
     * @return array<string, mixed> API query parameters
     */
    public static function buildApiQueryArgs(array $currentFilters, array $defaults = []): array
    {
        $defaults = array_merge([
            'page' => 1,
            'per_page' => 12,
            'orderby' => $currentFilters['orderby'],
        ], $defaults);

        $apiArgs = $defaults;

        // Add search if provided
        if (!empty($currentFilters['search'])) {
            $apiArgs['search'] = $currentFilters['search'];
        }

        // Add taxonomy filters
        $taxonomyMappings = [
            'use_case' => 'use_cases',
            'event' => 'events',
            'industry' => 'industries',
            'layout' => 'layouts',
            'plugin' => 'used_plugins',
        ];

        foreach ($taxonomyMappings as $filterKey => $apiKey) {
            if (!empty($currentFilters[$filterKey])) {
                $apiArgs[$apiKey] = $currentFilters[$filterKey];
            }
        }

        // Add license filter - match marketplace logic
        if (isset($currentFilters['is_pro']) && $currentFilters['is_pro'] !== '') {
            $apiArgs['is_pro'] = sanitize_text_field($currentFilters['is_pro']);
        }

        return $apiArgs;
    }
}