<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services;

defined('ABSPATH') || exit;

/**
 * Class ResponsiveCssBuilder
 *
 * Generates responsive CSS with media queries for custom block properties.
 * Provides utilities for building responsive styles with proper inheritance and breakpoints.
 * Works alongside AdvancedBlockStyleBuilder for comprehensive responsive support.
 *
 * Standard Breakpoints:
 * - Desktop: > 1024px (default, no media query)
 * - Tablet: 768px - 1024px
 * - Mobile: < 768px
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ResponsiveCssBuilder
{
    /**
     * Check if a responsive value is explicitly set.
     *
     * @param mixed $value Value to check
     * @return bool True if value is explicitly set
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function isResponsiveValueSet($value): bool
    {
        return isset($value) && $value !== null && $value !== '';
    }

    /**
     * Generate responsive CSS for a single property.
     *
     * Creates CSS rules with media queries for responsive properties.
     * Only generates rules for devices where values are explicitly set.
     * Uses !important flag to ensure proper override precedence.
     *
     * @param string $selector CSS selector (e.g., '.notifal-btn-123')
     * @param string $property CSS property (e.g., 'font-size')
     * @param mixed $desktop_value Desktop value (base value)
     * @param mixed $tablet_value Tablet value (optional)
     * @param mixed $mobile_value Mobile value (optional)
     * @param string $unit Unit (e.g., 'px', '%', 'em')
     * @param bool $use_important Whether to use !important flag (default: true)
     * @return string CSS rules with media queries
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function generatePropertyCss(
        string $selector,
        string $property,
        $desktop_value,
        $tablet_value = null,
        $mobile_value = null,
        string $unit = 'px',
        bool $use_important = true
    ): string {
        $css = '';
        $important = $use_important ? ' !important' : '';

        // Desktop (base styles, no media query needed)
        if (self::isResponsiveValueSet($desktop_value)) {
            $css .= sprintf(
                "%s { %s: %s%s%s; }\n",
                $selector,
                $property,
                $desktop_value,
                $unit,
                $important
            );
        }

        // Tablet (768px - 1024px)
        if (self::isResponsiveValueSet($tablet_value)) {
            $css .= sprintf(
                "@media (min-width: 768px) and (max-width: 1024px) {\n    %s { %s: %s%s%s; }\n}\n",
                $selector,
                $property,
                $tablet_value,
                $unit,
                $important
            );
        }

        // Mobile (< 768px)
        if (self::isResponsiveValueSet($mobile_value)) {
            $css .= sprintf(
                "@media (max-width: 767px) {\n    %s { %s: %s%s%s; }\n}\n",
                $selector,
                $property,
                $mobile_value,
                $unit,
                $important
            );
        }

        return $css;
    }

    /**
     * Generate responsive CSS for a property that has no unit (e.g. justify-content, display).
     *
     * Creates CSS rules with media queries. Only generates rules for devices where values are set.
     *
     * @param string $selector CSS selector (e.g., '.notifal-icon-wrapper')
     * @param string $property CSS property (e.g., 'justify-content')
     * @param string $desktop_value Desktop value (base value)
     * @param string|null $tablet_value Tablet value (optional)
     * @param string|null $mobile_value Mobile value (optional)
     * @param bool $use_important Whether to use !important flag (default: true)
     * @return string CSS rules with media queries
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function generatePropertyCssNoUnit(
        string $selector,
        string $property,
        $desktop_value,
        $tablet_value = null,
        $mobile_value = null,
        bool $use_important = true
    ): string {
        $css = '';
        $important = $use_important ? ' !important' : '';

        // Desktop (base styles)
        if (self::isResponsiveValueSet($desktop_value)) {
            $css .= sprintf(
                "%s { %s: %s%s; }\n",
                $selector,
                $property,
                $desktop_value,
                $important
            );
        }

        // Tablet (768px - 1024px)
        if (self::isResponsiveValueSet($tablet_value)) {
            $css .= sprintf(
                "@media (min-width: 768px) and (max-width: 1024px) {\n    %s { %s: %s%s; }\n}\n",
                $selector,
                $property,
                $tablet_value,
                $important
            );
        }

        // Mobile (< 768px)
        if (self::isResponsiveValueSet($mobile_value)) {
            $css .= sprintf(
                "@media (max-width: 767px) {\n    %s { %s: %s%s; }\n}\n",
                $selector,
                $property,
                $mobile_value,
                $important
            );
        }

        return $css;
    }

    /**
     * Generate responsive CSS for multiple box model properties (padding, margin, border-width).
     *
     * Creates CSS rules for top, right, bottom, left properties with responsive support.
     * Useful for padding, margin, and border-width properties.
     *
     * @param string $selector CSS selector
     * @param string $property_prefix CSS property prefix (e.g., 'padding', 'margin', 'border')
     * @param array $desktop_values Desktop values ['top' => 10, 'right' => 15, 'bottom' => 10, 'left' => 15]
     * @param array $tablet_values Tablet values (same format, optional)
     * @param array $mobile_values Mobile values (same format, optional)
     * @param string $unit Unit (default: 'px')
     * @param bool $use_important Whether to use !important flag (default: true)
     * @return string CSS rules with media queries
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function generateBoxModelCss(
        string $selector,
        string $property_prefix,
        array $desktop_values,
        array $tablet_values = [],
        array $mobile_values = [],
        string $unit = 'px',
        bool $use_important = true
    ): string {
        $css = '';
        $sides = ['top', 'right', 'bottom', 'left'];

        // Process each side
        foreach ($sides as $side) {
            $desktop_val = $desktop_values[$side] ?? null;
            $tablet_val = $tablet_values[$side] ?? null;
            $mobile_val = $mobile_values[$side] ?? null;

            $property = sprintf('%s-%s', $property_prefix, $side);
            $css .= self::generatePropertyCss(
                $selector,
                $property,
                $desktop_val,
                $tablet_val,
                $mobile_val,
                $unit,
                $use_important
            );
        }

        return $css;
    }

    /**
     * Generate responsive CSS for border-radius properties.
     *
     * Creates CSS rules for border-radius corners with responsive support.
     *
     * @param string $selector CSS selector
     * @param array $desktop_values Desktop values ['tl' => 4, 'tr' => 4, 'br' => 4, 'bl' => 4]
     * @param array $tablet_values Tablet values (same format, optional)
     * @param array $mobile_values Mobile values (same format, optional)
     * @param string $unit Unit (default: 'px')
     * @param bool $use_important Whether to use !important flag (default: true)
     * @return string CSS rules with media queries
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function generateBorderRadiusCss(
        string $selector,
        array $desktop_values,
        array $tablet_values = [],
        array $mobile_values = [],
        string $unit = 'px',
        bool $use_important = true
    ): string {
        $css = '';
        $corners = [
            'tl' => 'top-left',
            'tr' => 'top-right',
            'br' => 'bottom-right',
            'bl' => 'bottom-left'
        ];

        // Process each corner
        foreach ($corners as $key => $corner) {
            $desktop_val = $desktop_values[$key] ?? null;
            $tablet_val = $tablet_values[$key] ?? null;
            $mobile_val = $mobile_values[$key] ?? null;

            $property = sprintf('border-%s-radius', $corner);
            $css .= self::generatePropertyCss(
                $selector,
                $property,
                $desktop_val,
                $tablet_val,
                $mobile_val,
                $unit,
                $use_important
            );
        }

        return $css;
    }

    /**
     * Build responsive CSS from attributes for a block.
     *
     * Convenience method to generate all responsive CSS from block attributes.
     * Handles common responsive properties automatically.
     *
     * @param string $block_selector Unique block selector (e.g., '.notifal-btn-123')
     * @param array $attributes Block attributes
     * @param array $property_map Property mapping ['cssProperty' => 'attributePrefix']
     * @param string $default_unit Default unit for properties (default: 'px')
     * @return string Complete CSS rules with media queries
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildFromAttributes(
        string $block_selector,
        array $attributes,
        array $property_map,
        string $default_unit = 'px'
    ): string {
        $css = '';

        // Process each property in the map
        foreach ($property_map as $css_property => $attr_prefix) {
            // Get desktop, tablet, mobile values
            $desktop_value = $attributes[$attr_prefix] ?? null;
            $tablet_value = $attributes["{$attr_prefix}Tablet"] ?? null;
            $mobile_value = $attributes["{$attr_prefix}Mobile"] ?? null;

            // Get unit if specified
            $unit = $attributes["{$attr_prefix}Unit"] ?? $default_unit;

            // Only generate CSS if at least desktop value exists
            if (self::isResponsiveValueSet($desktop_value) ||
                self::isResponsiveValueSet($tablet_value) ||
                self::isResponsiveValueSet($mobile_value)) {
                $css .= self::generatePropertyCss(
                    $block_selector,
                    $css_property,
                    $desktop_value,
                    $tablet_value,
                    $mobile_value,
                    $unit,
                    true
                );
            }
        }

        return $css;
    }

    /**
     * Sanitize and extract responsive values from attributes.
     *
     * Helper method to extract desktop, tablet, and mobile values for a property.
     *
     * @param array $attributes Block attributes
     * @param string $property_prefix Attribute prefix (e.g., 'fontSize', 'iconSpacing')
     * @return array ['desktop' => value, 'tablet' => value, 'mobile' => value]
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function extractResponsiveValues(array $attributes, string $property_prefix): array
    {
        return [
            'desktop' => $attributes[$property_prefix] ?? null,
            'tablet' => $attributes["{$property_prefix}Tablet"] ?? null,
            'mobile' => $attributes["{$property_prefix}Mobile"] ?? null,
        ];
    }

    /**
     * Get breakpoint media query strings.
     *
     * Returns standard media query strings for responsive design.
     *
     * @return array Associative array of breakpoint media queries
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function getBreakpoints(): array
    {
        return [
            'desktop' => '@media (min-width: 1025px)',
            'tablet' => '@media (min-width: 768px) and (max-width: 1024px)',
            'mobile' => '@media (max-width: 767px)',
        ];
    }
}
