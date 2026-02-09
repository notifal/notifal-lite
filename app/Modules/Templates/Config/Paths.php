<?php

namespace Notifal\Modules\Templates\Config;

use Notifal\Core\Support\Paths\AbstractModulePaths;

defined('ABSPATH') || exit;

/**
 * Class Paths
 *
 * Provides scoped paths and URLs for the Templates module.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class Paths extends AbstractModulePaths
{
    /**
     * Get URL to CSS frontend files.
     *
     * @return string
     * @since 2.0.0
     */
    public static function cssFrontendUrl(): string
    {
        return static::baseUrl() . 'Resources/Assets/css/frontend/';
    }
}
