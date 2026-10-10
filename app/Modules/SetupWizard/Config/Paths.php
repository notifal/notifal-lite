<?php
namespace Notifal\Modules\SetupWizard\Config;
use Notifal\Core\Support\Paths\AbstractModulePaths;
defined('ABSPATH') || exit;
/**
 * Path and URL helpers for the Setup Wizard module.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class Paths extends AbstractModulePaths
{
    /**
     * Return filesystem path to Setup Wizard JavaScript language files.
     *
     * @since 3.0.0
     * @return string Absolute lang JS directory path.
     */
    public static function langJsPath(): string
    {
        // Compose the lang/js directory under module resources.
        return static::basePath() . 'Resources/Lang/js/';
    }
    /**
     * Return URL to admin CSS build directory for Setup Wizard assets.
     *
     * @since 3.0.0
     * @return string Build CSS URL.
     */
    public static function cssAdminBuildUrl(): string
    {
        // Reuse the global Vite admin CSS build directory.
        return NOTIFAL_URL . 'public/build/css/admin/';
    }
    
    /**
     * Return URL to admin JS build directory for Setup Wizard assets.
     *
     * @since 3.0.0
     * @return string Build JS URL.
     */
    public static function jsAdminBuildUrl(): string
    {
        // Reuse the global Vite admin JS build directory.
        return NOTIFAL_URL . 'public/build/js/admin/';
    }
}
