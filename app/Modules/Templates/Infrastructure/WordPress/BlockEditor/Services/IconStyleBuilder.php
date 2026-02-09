<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services;

use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\Shared\CssBuilder;
use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\ResponsiveCssBuilder;

defined('ABSPATH') || exit;

/**
 * Builds CSS styles and classes for Icon Gutenberg blocks.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class IconStyleBuilder
{
    /**
     * Build CSS classes for container element.
     *
     * @return array Container CSS classes.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildContainerClasses(): array
    {
        return ['notifal-icon-block'];
    }

    /**
     * Build CSS classes for wrapper element.
     *
     * @return array Wrapper CSS classes.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildWrapperClasses(): array
    {
        return ['notifal-icon-wrapper'];
    }

    /**
     * Build CSS classes for icon element.
     *
     * @return array Icon CSS classes.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildIconClasses(): array
    {
        return ['notifal-icon'];
    }

    /**
     * Build inline CSS style for wrapper element (desktop alignment only; tablet/mobile from responsive CSS).
     *
     * @param array $attributes Sanitized block attributes.
     * @return string Wrapper CSS styles.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildWrapperStyles(array $attributes): string
    {
        $alignment = $attributes['alignment'] ?? 'center';
        return "display: flex; align-items: center; justify-content: {$alignment};";
    }

    /**
     * Build complete inline CSS styles for icon element.
     *
     * @param array $attributes Sanitized block attributes.
     * @return string Icon CSS styles.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildIconStyles(array $attributes): string
    {
        $size = $attributes['size'] ?? 24;
        $size_unit = $attributes['sizeUnit'] ?? 'px';

        // Use size for width/height by default; custom width/height override when set
        $width_value = !empty($attributes['width']) ? $attributes['width'] . ($attributes['widthUnit'] ?? 'px') : $size . $size_unit;
        $height_value = !empty($attributes['height']) ? $attributes['height'] . ($attributes['heightUnit'] ?? 'px') : $size . $size_unit;

        // Prevents icon from shrinking or disappearing when padding/background are set (global border-box would include padding in size).
        $styles = [
            'box-sizing: content-box',
            "width: {$width_value}",
            "height: {$height_value}",
            "line-height: 0",
            "transition: all 0.3s ease"
        ];

        $styles = array_merge($styles, CssBuilder::buildBorderStyles($attributes));
        $styles = array_merge($styles, CssBuilder::buildPaddingStyles($attributes));
        $styles = array_merge($styles, CssBuilder::buildBoxShadowStyles($attributes));
        $styles = array_merge($styles, CssBuilder::buildBackgroundStyles($attributes));

        if (isset($attributes['borderRadius'])) {
            $styles[] = "border-radius: {$attributes['borderRadius']}px";
        }

        // Always apply primary color for SVG icons
        $styles[] = "color: {$attributes['primaryColor']}";

        return CssBuilder::arrayToInlineStyle($styles);
    }

    /**
     * Build inline CSS styles for SVG element.
     *
     * @param array $attributes Sanitized block attributes.
     * @return string SVG CSS styles.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildSvgStyles(array $attributes): string
    {
        $size = $attributes['size'];
        $size_unit = $attributes['sizeUnit'];

        $styles = [
            "width: {$size}{$size_unit}",
            "height: {$size}{$size_unit}",
            "fill: currentColor"
        ];

        return CssBuilder::arrayToInlineStyle($styles);
    }

    /**
     * Build responsive CSS with media queries for tablet and mobile devices.
     *
     * Generates CSS rules that adapt icon styling across different screen sizes.
     * Follows mobile-first approach with cascading overrides for larger screens.
     *
     * @param string $selector Unique CSS selector for the icon block.
     * @param array $attributes Sanitized block attributes including responsive values.
     * @return string Complete CSS string with media queries.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildResponsiveCss(string $selector, array $attributes): string
    {
        $css = '';

        // Alignment (responsive) - wrapper justify-content for tablet/mobile
        $alignment = $attributes['alignment'] ?? 'center';
        $alignment_tablet = $attributes['alignmentTablet'] ?? null;
        $alignment_mobile = $attributes['alignmentMobile'] ?? null;
        $css .= ResponsiveCssBuilder::generatePropertyCssNoUnit(
            $selector . ' .notifal-icon-wrapper',
            'justify-content',
            $alignment,
            $alignment_tablet,
            $alignment_mobile,
            true
        );

        // Icon size via width/height (responsive) - default 24 to match block.json
        $css .= ResponsiveCssBuilder::generatePropertyCss(
            $selector . ' .notifal-icon',
            'width',
            $attributes['size'] ?? 24,
            $attributes['sizeTablet'] ?? null,
            $attributes['sizeMobile'] ?? null,
            $attributes['sizeUnit'] ?? 'px',
            true
        );
        $css .= ResponsiveCssBuilder::generatePropertyCss(
            $selector . ' .notifal-icon',
            'height',
            $attributes['size'] ?? 24,
            $attributes['sizeTablet'] ?? null,
            $attributes['sizeMobile'] ?? null,
            $attributes['sizeUnit'] ?? 'px',
            true
        );

        // Padding - Responsive Box Model
        $css .= ResponsiveCssBuilder::generateBoxModelCss(
            $selector . ' .notifal-icon',
            'padding',
            [
                'top' => $attributes['paddingTop'] ?? 0,
                'right' => $attributes['paddingRight'] ?? 0,
                'bottom' => $attributes['paddingBottom'] ?? 0,
                'left' => $attributes['paddingLeft'] ?? 0,
            ],
            [
                'top' => $attributes['paddingTopTablet'] ?? null,
                'right' => $attributes['paddingRightTablet'] ?? null,
                'bottom' => $attributes['paddingBottomTablet'] ?? null,
                'left' => $attributes['paddingLeftTablet'] ?? null,
            ],
            [
                'top' => $attributes['paddingTopMobile'] ?? null,
                'right' => $attributes['paddingRightMobile'] ?? null,
                'bottom' => $attributes['paddingBottomMobile'] ?? null,
                'left' => $attributes['paddingLeftMobile'] ?? null,
            ],
            $attributes['paddingUnit'] ?? 'px',
            true
        );

        return $css;
    }
}