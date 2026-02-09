<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services;

use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\Shared\CssBuilder;
use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\ResponsiveCssBuilder;

defined('ABSPATH') || exit;

/**
 * Builds CSS styles and classes for Close Icon Gutenberg blocks.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class CloseIconStyleBuilder
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
        return ['notifal-close-icon-block'];
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
        return ['notifal-close-icon-wrapper'];
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
        return ['notifal-close'];
    }

    /**
     * Build CSS classes for mask icon element.
     *
     * @return array Mask icon CSS classes.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildMaskIconClasses(): array
    {
        return ['notifal-close', 'notifal-close-mask'];
    }

    /**
     * Build inline CSS style for wrapper element.
     *
     * @param array $attributes Sanitized block attributes.
     * @return string Wrapper CSS styles.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildWrapperStyles(array $attributes): string
    {
        return "display: flex; align-items: center; justify-content: {$attributes['alignment']};";
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
        $is_mask_case = !empty($attributes['iconUrl']) &&
                       $attributes['iconType'] === 'svg' &&
                       !empty($attributes['primaryColor']) &&
                       $attributes['primaryColor'] !== '#ffffff' &&
                       $attributes['primaryColor'] !== '#000000';

        $styles = [
            "font-size: {$attributes['size']}{$attributes['sizeUnit']}",
            "cursor: pointer",
            "transition: all 0.3s ease"
        ];

        $styles = array_merge($styles, CssBuilder::buildBorderStyles($attributes));
        $styles = array_merge($styles, CssBuilder::buildPaddingStyles($attributes));
        $styles = array_merge($styles, CssBuilder::buildBoxShadowStyles($attributes));
        $styles = array_merge($styles, CssBuilder::buildBackgroundStyles($attributes));

        if (isset($attributes['borderRadius'])) {
            $styles[] = "border-radius: {$attributes['borderRadius']}px";
        }

        if ($is_mask_case) {
            $styles = array_merge($styles, CssBuilder::buildIconMaskWrapperStyles($attributes));
        } else {
            $styles[] = "color: {$attributes['primaryColor']}";
        }

        return CssBuilder::arrayToInlineStyle($styles);
    }

    /**
     * Build inline CSS styles for image element.
     *
     * @param array $attributes Sanitized block attributes.
     * @return string Image CSS styles.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildImageStyles(array $attributes): string
    {
        $size = $attributes['size'];
        $size_unit = $attributes['sizeUnit'];

        $styles = [
            "width: {$size}{$size_unit}",
            "height: {$size}{$size_unit}",
            "object-fit: contain"
        ];

        // Add icon filter styles using CssBuilder
        $styles = array_merge($styles, CssBuilder::buildIconFilterStyles($attributes));

        return CssBuilder::arrayToInlineStyle($styles);
    }

    /**
     * Build responsive CSS with media queries for tablet and mobile devices.
     *
     * Generates CSS rules that adapt close icon styling across different screen sizes.
     * Follows mobile-first approach with cascading overrides for larger screens.
     *
     * @param string $selector Unique CSS selector for the close icon block.
     * @param array $attributes Sanitized block attributes including responsive values.
     * @return string Complete CSS string with media queries.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildResponsiveCss(string $selector, array $attributes): string
    {
        $css = '';

        // Icon Size - Responsive
        $css .= ResponsiveCssBuilder::generatePropertyCss(
            $selector . ' .notifal-close',
            'font-size',
            $attributes['size'] ?? 30,
            $attributes['sizeTablet'] ?? null,
            $attributes['sizeMobile'] ?? null,
            $attributes['sizeUnit'] ?? 'px',
            true
        );

        // Padding - Responsive Box Model
        $css .= ResponsiveCssBuilder::generateBoxModelCss(
            $selector . ' .notifal-close',
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