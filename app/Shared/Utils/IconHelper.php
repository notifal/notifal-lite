<?php

namespace Notifal\Shared\Utils;

use Notifal\Shared\Services\IconService;

defined('ABSPATH') || exit;

/**
 * Class IconHelper
 *
 * Helper utility for easy icon usage throughout the Notifal plugin.
 * Provides convenient methods for common icon rendering scenarios with predefined sizes.
 *
 * @since 2.0.0
 * @package Notifal\Shared\Utils
 * @author Hossein <hossein@notifal.com>
 */
class IconHelper
{
    /**
     * Render a small icon (16x16px).
     *
     * @param array $attributes Optional attributes for the SVG element
     * @return string SVG icon HTML
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function small(array $attributes = []): string
    {
        $defaultAttributes = [
            'width' => '16',
            'height' => '16',
            'class' => 'notifal-icon notifal-icon-small',
        ];

        $attributes = array_merge($defaultAttributes, $attributes);

        return IconService::renderIcon($attributes);
    }

    /**
     * Render a medium icon (20x20px).
     *
     * @param array $attributes Optional attributes for the SVG element
     * @return string SVG icon HTML
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function medium(array $attributes = []): string
    {
        $defaultAttributes = [
            'width' => '20',
            'height' => '20',
            'class' => 'notifal-icon notifal-icon-medium',
        ];

        $attributes = array_merge($defaultAttributes, $attributes);

        return IconService::renderIcon($attributes);
    }

    /**
     * Render a large icon (24x24px).
     *
     * @param array $attributes Optional attributes for the SVG element
     * @return string SVG icon HTML
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function large(array $attributes = []): string
    {
        $defaultAttributes = [
            'width' => '24',
            'height' => '24',
            'class' => 'notifal-icon notifal-icon-large',
        ];

        $attributes = array_merge($defaultAttributes, $attributes);

        return IconService::renderIcon($attributes);
    }

    /**
     * Render an icon with custom size.
     *
     * @param int $size The size in pixels for both width and height
     * @param array $attributes Optional attributes for the SVG element
     * @return string SVG icon HTML
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function size(int $size, array $attributes = []): string
    {
        $defaultAttributes = [
            'width' => $size,
            'height' => $size,
            'class' => 'notifal-icon',
        ];

        $attributes = array_merge($defaultAttributes, $attributes);

        return IconService::renderIcon($attributes);
    }

    /**
     * Get icon as CSS background image.
     *
     * @param array $attributes Optional attributes for the SVG element
     * @return string CSS background-image value with data URL
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function asBackgroundImage(array $attributes = []): string
    {
        $base64 = IconService::getBase64Icon($attributes);
        return "url('{$base64}')";
    }

    /**
     * Echo an icon directly to output.
     *
     * @param array $attributes Optional attributes for the SVG element
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function echo(array $attributes = []): void
    {
        echo IconService::renderIcon($attributes);
    }
} 
