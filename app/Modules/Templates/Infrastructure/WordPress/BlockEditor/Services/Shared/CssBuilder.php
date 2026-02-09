<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\Shared;

defined('ABSPATH') || exit;

/**
 * Class CssBuilder
 *
 * Shared utility for building common CSS properties across style builders.
 * Provides reusable methods for borders, shadows, padding, backgrounds, and filters.
 * Reduces code duplication and ensures consistency across all block style builders.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\Shared
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class CssBuilder
{
    /**
     * Build border-related CSS properties.
     *
     * @param array $attributes Block attributes containing border properties.
     * @return array CSS border properties.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildBorderStyles(array $attributes): array
    {
        $styles = [];

        // Skip if no border style is set
        if (($attributes['borderStyle'] ?? 'none') === 'none') {
            return $styles;
        }

        $styles[] = 'border-style: ' . $attributes['borderStyle'];

        // Border widths
        if (isset($attributes['borderTop'])) {
            $styles[] = 'border-top-width: ' . $attributes['borderTop'] . 'px';
        }
        if (isset($attributes['borderRight'])) {
            $styles[] = 'border-right-width: ' . $attributes['borderRight'] . 'px';
        }
        if (isset($attributes['borderBottom'])) {
            $styles[] = 'border-bottom-width: ' . $attributes['borderBottom'] . 'px';
        }
        if (isset($attributes['borderLeft'])) {
            $styles[] = 'border-left-width: ' . $attributes['borderLeft'] . 'px';
        }

        // Border color
        if (!empty($attributes['borderColor'])) {
            $styles[] = 'border-color: ' . $attributes['borderColor'];
        }

        return $styles;
    }

    /**
     * Build border radius CSS properties.
     *
     * @param array $attributes Block attributes containing radius properties.
     * @return array CSS border radius properties.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildBorderRadiusStyles(array $attributes): array
    {
        $styles = [];

        $tl = $attributes['radiusTopLeft'] ?? 0;
        $tr = $attributes['radiusTopRight'] ?? 0;
        $br = $attributes['radiusBottomRight'] ?? 0;
        $bl = $attributes['radiusBottomLeft'] ?? 0;

        // Only add radius if at least one corner has radius
        if ($tl || $tr || $br || $bl) {
            $styles[] = "border-radius: {$tl}px {$tr}px {$br}px {$bl}px";
        }

        return $styles;
    }

    /**
     * Build box shadow CSS properties.
     *
     * @param array $attributes Block attributes containing shadow properties.
     * @return array CSS box shadow properties.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildBoxShadowStyles(array $attributes): array
    {
        $styles = [];

        $h = $attributes['boxShadowH'] ?? 0;
        $v = $attributes['boxShadowV'] ?? 0;
        $blur = $attributes['boxShadowBlur'] ?? 0;
        $spread = $attributes['boxShadowSpread'] ?? 0;

        // Only add shadow if at least one value is non-zero
        if ($h || $v || $blur || $spread) {
            $inset = !empty($attributes['boxShadowInset']) ? 'inset ' : '';
            $color = $attributes['boxShadowColor'] ?? 'transparent';
            $styles[] = "box-shadow: {$inset}{$h}px {$v}px {$blur}px {$spread}px {$color}";
        }

        return $styles;
    }

    /**
     * Build padding CSS properties.
     *
     * Uses paddingUnit from attributes when present (e.g. px, em, rem);
     * otherwise defaults to px for backward compatibility.
     *
     * @param array $attributes Block attributes containing padding properties and optional paddingUnit.
     * @return array CSS padding properties.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildPaddingStyles(array $attributes): array
    {
        $styles = [];
        $unit = isset($attributes['paddingUnit']) && in_array($attributes['paddingUnit'], ['px', 'em', 'rem'], true)
            ? $attributes['paddingUnit']
            : 'px';

        if (isset($attributes['paddingTop'], $attributes['paddingRight'], $attributes['paddingBottom'], $attributes['paddingLeft'])) {
            $styles[] = 'padding: ' . $attributes['paddingTop'] . $unit . ' ' .
                       $attributes['paddingRight'] . $unit . ' ' .
                       $attributes['paddingBottom'] . $unit . ' ' .
                       $attributes['paddingLeft'] . $unit;
        }

        return $styles;
    }

    /**
     * Build background CSS properties (solid color or gradient).
     *
     * @param array $attributes Block attributes containing background properties.
     * @return array CSS background properties.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildBackgroundStyles(array $attributes): array
    {
        $styles = [];
        $background_type = $attributes['backgroundType'] ?? 'solid';

        if ($background_type === 'gradient') {
            // Build gradient only if all required values are present
            $direction = $attributes['gradientDirection'] ?? '';
            $from = $attributes['gradientFrom'] ?? '';
            $to = $attributes['gradientTo'] ?? '';

            if (!empty($direction) && !empty($from) && !empty($to)) {
                $styles[] = "background-image: linear-gradient({$direction}, {$from}, {$to})";
            }
        } else {
            // Solid color background
            $background_color = $attributes['backgroundColor'] ?? '';
            if (!empty($background_color)) {
                $styles[] = "background-color: {$background_color}";
            }
        }

        return $styles;
    }

    /**
     * Build icon filter styles for SVG icons.
     *
     * Applies brightness filters to convert SVG icons to white or black.
     * Custom colors are handled via mask approach in buildIconMaskWrapperStyles.
     *
     * @param array $attributes Block attributes containing icon properties.
     * @return array CSS filter properties for icon color handling.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildIconFilterStyles(array $attributes): array
    {
        $styles = [];

        // Apply filters only for SVG icons with specific colors
        if (($attributes['iconType'] ?? 'image') === 'svg' && !empty($attributes['iconColor'])) {
            $icon_color = $attributes['iconColor'];

            if ($icon_color === '#ffffff') {
                // Convert any color SVG to white
                $styles[] = 'filter: brightness(0) invert(1)';
            } elseif ($icon_color === '#000000') {
                // Convert any color SVG to black
                $styles[] = 'filter: brightness(0)';
            }
            // Custom colors use mask-based approach instead of filters
        }

        return $styles;
    }

    /**
     * Build icon wrapper styles for mask-based SVG rendering.
     *
     * @param array $attributes Block attributes containing icon properties.
     * @return array CSS properties for icon wrapper with mask.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildIconMaskWrapperStyles(array $attributes): array
    {
        $styles = [];

        // Only apply mask styles for SVG icons with custom colors (not white/black)
        if (!empty($attributes['iconUrl']) &&
            ($attributes['iconType'] ?? 'image') === 'svg' &&
            !empty($attributes['iconColor'])) {

            $icon_color = $attributes['iconColor'];

            // Use mask approach only for custom colors, not predefined white/black
            if ($icon_color !== '#ffffff' && $icon_color !== '#000000') {
                $width = $attributes['iconWidth'] ?? 24;
                $height = $attributes['iconHeight'] ?? 24;
                $icon_url = $attributes['iconUrl'];

                $styles[] = "width: {$width}px";
                $styles[] = "height: {$height}px";
                $styles[] = 'display: inline-block';
                $styles[] = "background-color: {$icon_color}";
                $styles[] = "mask-image: url('{$icon_url}')";
                $styles[] = "-webkit-mask-image: url('{$icon_url}')";
                $styles[] = 'mask-size: contain';
                $styles[] = '-webkit-mask-size: contain';
                $styles[] = 'mask-repeat: no-repeat';
                $styles[] = '-webkit-mask-repeat: no-repeat';
                $styles[] = 'mask-position: center';
                $styles[] = '-webkit-mask-position: center';
            }
        }

        return $styles;
    }

    /**
     * Convert array of CSS properties to inline style string.
     *
     * Safely converts CSS property array to properly formatted inline style string.
     *
     * @param array $styles Array of CSS property strings (e.g., ['color: red', 'font-size: 14px']).
     * @return string Inline CSS style string ready for HTML attributes.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function arrayToInlineStyle(array $styles): string
    {
        // Filter out empty values and ensure we have valid styles
        $valid_styles = array_filter($styles, function ($style) {
            return is_string($style) && !empty(trim($style));
        });

        return !empty($valid_styles) ? implode('; ', $valid_styles) . ';' : '';
    }
}