<?php

namespace Notifal\Core\Support\Paths;

defined('ABSPATH') || exit;

/**
 * Abstract class for module-specific path resolution.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
abstract class AbstractModulePaths
{
    /**
     * Detect module slug automatically from the namespace.
     *
     * @return string
     * @since 2.0.0
     */
    protected static function moduleSlug(): string
    {
        // Example: Notifal\Modules\OnPageNotification\Config\Paths
        $namespaceParts = explode('\\', static::class);

        // Get the 3rd part of namespace as module name (Modules\{Name})
        $moduleKey = array_search('Modules', $namespaceParts, true);

        if ($moduleKey !== false && isset($namespaceParts[$moduleKey + 1])) {
            return $namespaceParts[$moduleKey + 1];
        }

        // Fallback
        return 'UnknownModule';
    }

    /**
     * Get base filesystem path of the module.
     *
     * @return string
     * @since 2.0.0
     */
    public static function basePath(): string
    {
        return NOTIFAL_MODULES_PATH . static::moduleSlug() . '/';
    }

    /**
     * Get base URL of the module.
     *
     * @return string
     * @since 2.0.0
     */
    public static function baseUrl(): string
    {
        return NOTIFAL_MODULES_URL . static::moduleSlug() . '/';
    }

    /**
     * Get URL to JS admin files.
     *
     * @return string
     * @since 2.0.0
     */
    public static function jsAdminUrl(): string
    {
        return static::baseUrl() . 'Resources/Assets/js/admin/';
    }

    /**
     * Get URL to CSS admin files.
     *
     * @return string
     * @since 2.0.0
     */
    public static function cssAdminUrl(): string
    {
        return static::baseUrl() . 'Resources/Assets/css/admin/';
    }

    /**
     * Get path to js language files.
     *
     * @return string
     * @since 2.0.0
     */
    public static function jsLangPath(): string
    {
        return static::baseUrl() . 'Resources/Lang/js';
    }
}
