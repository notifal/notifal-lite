<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services;

use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\Shared\CssBuilder;
use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\ResponsiveCssBuilder;

defined('ABSPATH') || exit;

/**
 * Builds CSS styles and classes for Action Button Gutenberg blocks.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ActionButtonStyleBuilder
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
        return ['notifal-action-button-block'];
    }

    /**
     * Build CSS classes for button element.
     *
     * @return array Button CSS classes.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildButtonClasses(): array
    {
        return ['notifal-action-button', 'notifal-track-click'];
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
        return ['notifal-action-button-icon'];
    }

    /**
     * Build CSS classes for icon wrapper element.
     *
     * @return array Icon wrapper CSS classes.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildIconWrapperClasses(): array
    {
        return ['notifal-action-button-icon-wrapper'];
    }

    /**
     * Build CSS classes for text element.
     *
     * @return array Text CSS classes.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildTextClasses(): array
    {
        return ['notifal-action-button-text'];
    }

    /**
     * Build inline CSS style for container element.
     *
     * @param string $alignment Button alignment (left, center, right).
     * @return string Container CSS style.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildContainerStyle(string $alignment): string
    {
        $justify_content = 'center';
        if ($alignment === 'left') {
            $justify_content = 'flex-start';
        } elseif ($alignment === 'right') {
            $justify_content = 'flex-end';
        }

        return "display: flex; justify-content: {$justify_content}; width: 100%; margin: 10px 0;";
    }

    /**
     * Build complete inline CSS styles for button element.
     *
     * @param array $attributes Sanitized block attributes.
     * @return string Button CSS styles.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildButtonStyles(array $attributes): string
    {
        $styles = [
            'font-size: ' . $attributes['fontSize'] . 'px',
            'font-weight: ' . $attributes['fontWeight'],
            'text-transform: ' . $attributes['textTransform'],
            'letter-spacing: ' . $attributes['letterSpacing'] . 'px',
            'color: ' . $attributes['textColor'],
            'text-decoration: none',
            'display: inline-flex',
            'align-items: center',
            'justify-content: center',
            'cursor: pointer',
            'transition: all 0.3s ease',
        ];

        // Add shared CSS properties using CssBuilder
        $styles = array_merge($styles, CssBuilder::buildBorderStyles($attributes));
        $styles = array_merge($styles, CssBuilder::buildBorderRadiusStyles($attributes));
        $styles = array_merge($styles, CssBuilder::buildPaddingStyles($attributes));
        $styles = array_merge($styles, CssBuilder::buildBoxShadowStyles($attributes));
        $styles = array_merge($styles, CssBuilder::buildBackgroundStyles($attributes));

        // Add gap for icon
        if (!empty($attributes['iconUrl'])) {
            $styles[] = 'gap: ' . (isset($attributes['iconSpacing']) ? (int)$attributes['iconSpacing'] : 8) . 'px';
        }

        return CssBuilder::arrayToInlineStyle($styles);
    }

    /**
     * Build inline CSS styles for icon element.
     *
     * @param array $attributes Sanitized block attributes.
     * @return string Icon CSS styles.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildIconStyles(array $attributes): string
    {
        $styles = [
            'width: ' . (int)($attributes['iconWidth'] ?? 24) . 'px',
            'height: ' . (int)($attributes['iconHeight'] ?? 24) . 'px',
            'object-fit: contain',
            'margin-right: ' . (isset($attributes['iconSpacing']) ? (int)$attributes['iconSpacing'] : 8) . 'px',
            'vertical-align: middle'
        ];

        // Add icon filter styles using CssBuilder
        $styles = array_merge($styles, CssBuilder::buildIconFilterStyles($attributes));

        return CssBuilder::arrayToInlineStyle($styles);
    }

    /**
     * Build inline CSS styles for icon wrapper element.
     *
     * @param array $attributes Sanitized block attributes.
     * @return string Icon wrapper CSS styles.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildIconWrapperStyles(array $attributes): string
    {
        $styles = [
            'width: ' . (int)($attributes['iconWidth'] ?? 24) . 'px',
            'height: ' . (int)($attributes['iconHeight'] ?? 24) . 'px',
            'margin-right: ' . (isset($attributes['iconSpacing']) ? (int)$attributes['iconSpacing'] : 8) . 'px',
            'display: inline-block',
            'vertical-align: middle'
        ];

        // Add mask wrapper styles using CssBuilder
        $styles = array_merge($styles, CssBuilder::buildIconMaskWrapperStyles($attributes));

        return CssBuilder::arrayToInlineStyle($styles);
    }

    /**
     * Build responsive CSS rules for Action Button block.
     *
     * Generates media queries for responsive properties (font-size, padding, border-radius, icon spacing).
     * Uses standard breakpoints:
     * - Desktop: > 1024px (default, no media query)
     * - Tablet: 768px - 1024px
     * - Mobile: < 768px
     *
     * @param string $button_id Unique button identifier (used as CSS selector)
     * @param array $attributes Sanitized block attributes
     * @return string CSS rules with media queries
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildResponsiveCss(string $button_id, array $attributes): string
    {
        $css = '';
        $button_selector = '#' . esc_attr($button_id);

        // Font Size
        $css .= ResponsiveCssBuilder::generatePropertyCss(
            $button_selector,
            'font-size',
            $attributes['fontSize'] ?? 14,
            $attributes['fontSizeTablet'] ?? null,
            $attributes['fontSizeMobile'] ?? null,
            'px',
            true
        );

        // Padding
        $css .= ResponsiveCssBuilder::generateBoxModelCss(
            $button_selector,
            'padding',
            [
                'top' => $attributes['paddingTop'] ?? 5,
                'right' => $attributes['paddingRight'] ?? 15,
                'bottom' => $attributes['paddingBottom'] ?? 5,
                'left' => $attributes['paddingLeft'] ?? 15,
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
            'px',
            true
        );

        // Border Radius
        $css .= ResponsiveCssBuilder::generateBorderRadiusCss(
            $button_selector,
            [
                'tl' => $attributes['radiusTopLeft'] ?? 4,
                'tr' => $attributes['radiusTopRight'] ?? 4,
                'br' => $attributes['radiusBottomRight'] ?? 4,
                'bl' => $attributes['radiusBottomLeft'] ?? 4,
            ],
            [
                'tl' => $attributes['radiusTopLeftTablet'] ?? null,
                'tr' => $attributes['radiusTopRightTablet'] ?? null,
                'br' => $attributes['radiusBottomRightTablet'] ?? null,
                'bl' => $attributes['radiusBottomLeftTablet'] ?? null,
            ],
            [
                'tl' => $attributes['radiusTopLeftMobile'] ?? null,
                'tr' => $attributes['radiusTopRightMobile'] ?? null,
                'br' => $attributes['radiusBottomRightMobile'] ?? null,
                'bl' => $attributes['radiusBottomLeftMobile'] ?? null,
            ],
            'px',
            true
        );

        // Icon Spacing (gap property)
        if (!empty($attributes['iconUrl'])) {
            $css .= ResponsiveCssBuilder::generatePropertyCss(
                $button_selector,
                'gap',
                $attributes['iconSpacing'] ?? 8,
                $attributes['iconSpacingTablet'] ?? null,
                $attributes['iconSpacingMobile'] ?? null,
                'px',
                true
            );
        }

        return $css;
    }
}