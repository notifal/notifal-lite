<?php

namespace Notifal\Modules\OnPageNotification\Config;

use Notifal\Core\Support\Paths\AbstractModulePaths;

defined('ABSPATH') || exit;

/**
 * Class Paths
 *
 * Provides scoped paths and URLs for the OnPageNotification module.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class Paths extends AbstractModulePaths
{
    /**
     * Get path to JS editor files.
     *
     * @return string
     * @since 2.0.0
     */
    public static function jsEditorPath(): string
    {
        return static::basePath() . 'Resources/Assets/js/admin/edit/';
    }

    /**
     * Get URL to JS editor files.
     *
     * @return string
     * @since 2.0.0
     */
    public static function jsEditorUrl(): string
    {
        return static::baseUrl() . 'Resources/Assets/js/admin/edit/';
    }

    /**
     * Get path to JS language files.
     *
     * @return string
     * @since 2.0.0
     */
    public static function jsLangPath(): string
    {
        return static::basePath() . 'Resources/Lang/js/';
    }
}
