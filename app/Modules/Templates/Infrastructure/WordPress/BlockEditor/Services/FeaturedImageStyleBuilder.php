<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services;

use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\Shared\CssBuilder;
use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\ResponsiveCssBuilder;

defined('ABSPATH') || exit;

/**
 * Builds CSS styles and classes for Featured Image Gutenberg blocks.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FeaturedImageStyleBuilder
{

    /**
     * Build CSS classes for container element.
     *
     * @param bool $force_transparent Whether to apply transparent styling.
     * @return array Container CSS classes.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildContainerClasses(bool $force_transparent): array {
        $classes = ['notifal-featured-image-block'];

        // Add transparent class if needed
        if ($force_transparent) {
            $classes[] = 'force-transparent';
        }

        return $classes;
    }

    /**
     * Build CSS classes for image element.
     *
     * @return array Image CSS classes.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildImageClasses(): array {
        return ['notifal-featured-image'];
    }

    /**
     * Build inline CSS style for container element.
     *
     * @param string $alignment Image alignment (left, center, right).
     * @return string Container CSS style.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildContainerStyle(string $alignment): string
    {
        return "text-align: {$alignment}; margin: 0; padding: 0; background: none;";
    }

    /**
     * Build complete inline CSS styles for image element.
     *
     * @param array $image_attrs Image attributes (width, height, etc.).
     * @param array $style_attrs Styling attributes (borders, shadows, etc.).
     * @return string CSS style string.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildImageStyles(array $image_attrs, array $style_attrs): string
    {
        $styles = [];

        // Dimensions
        $styles = array_merge($styles, self::buildDimensionStyles($image_attrs));

        // Alignment margins
        $styles = array_merge($styles, self::buildAlignmentStyles($image_attrs['alignment']));

        // Border styles using CssBuilder
        $styles = array_merge($styles, CssBuilder::buildBorderStyles($style_attrs));

        // Border radius using CssBuilder
        $styles = array_merge($styles, CssBuilder::buildBorderRadiusStyles($style_attrs));

        // Box shadow using CssBuilder
        $styles = array_merge($styles, CssBuilder::buildBoxShadowStyles($style_attrs));

        // Object fit
        $styles[] = "object-fit: {$style_attrs['object_fit']}";

        // CSS Filters
        $styles = array_merge($styles, self::buildFilterStyles($style_attrs));

        return CssBuilder::arrayToInlineStyle($styles);
    }

    /**
     * Build CSS dimension styles (width and height).
     *
     * @param array $image_attrs Image attributes.
     * @return array CSS dimension styles.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function buildDimensionStyles(array $image_attrs): array {
        $styles = [];
        
        // Add width if specified
        if ($image_attrs['width']) {
            $styles[] = "width: {$image_attrs['width']}{$image_attrs['width_unit']}";
        }
        
        // Add height if specified
        if ($image_attrs['height']) {
            $styles[] = "height: {$image_attrs['height']}{$image_attrs['height_unit']}";
        }
        
        return $styles;
    }

    /**
     * Build CSS margin styles for image alignment.
     *
     * @param string $alignment Image alignment.
     * @return array CSS margin styles.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function buildAlignmentStyles(string $alignment): array {
        switch ($alignment) {
            case 'center':
                return ["margin: 0 auto"];
            case 'right':
                return ["margin: 0 0 0 auto"];
            default: // left
                return ["margin: 0 auto 0 0"];
        }
    }


    /**
     * Build CSS filter styles.
     *
     * @param array $style_attrs Styling attributes.
     * @return array CSS filter styles.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function buildFilterStyles(array $style_attrs): array {
        $filters = [];
        
        // Add filters if they differ from default values
        if ($style_attrs['filter_brightness'] !== 100) {
            $filters[] = "brightness({$style_attrs['filter_brightness']}%)";
        }
        if ($style_attrs['filter_contrast'] !== 100) {
            $filters[] = "contrast({$style_attrs['filter_contrast']}%)";
        }
        if ($style_attrs['filter_saturation'] !== 100) {
            $filters[] = "saturate({$style_attrs['filter_saturation']}%)";
        }
        if ($style_attrs['filter_blur'] > 0) {
            $filters[] = "blur({$style_attrs['filter_blur']}px)";
        }
        if ($style_attrs['filter_hue'] !== 0) {
            $filters[] = "hue-rotate({$style_attrs['filter_hue']}deg)";
        }
        
        // Return filter declaration if any filters are active
        if (!empty($filters)) {
            return ["filter: " . implode(' ', $filters)];
        }
        
        return [];
    }

    /**
     * Build responsive CSS with media queries for tablet and mobile devices.
     *
     * Generates CSS rules that adapt featured image styling across different screen sizes.
     * Follows mobile-first approach with cascading overrides for larger screens.
     *
     * @param string $selector Unique CSS selector for the featured image block.
     * @param array $attributes Sanitized block attributes including responsive values.
     * @return string Complete CSS string with media queries.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildResponsiveCss(string $selector, array $attributes): string
    {
        $css = '';

        // Width - Responsive
        if (!empty($attributes['width'])) {
            $css .= ResponsiveCssBuilder::generatePropertyCss(
                $selector . ' .notifal-featured-image',
                'width',
                $attributes['width'],
                $attributes['widthTablet'] ?? null,
                $attributes['widthMobile'] ?? null,
                $attributes['widthUnit'] ?? 'px',
                true
            );
        }

        // Height - Responsive
        if (!empty($attributes['height'])) {
            $css .= ResponsiveCssBuilder::generatePropertyCss(
                $selector . ' .notifal-featured-image',
                'height',
                $attributes['height'],
                $attributes['heightTablet'] ?? null,
                $attributes['heightMobile'] ?? null,
                $attributes['heightUnit'] ?? 'px',
                true
            );
        }

        // Border Radius - Responsive
        $css .= ResponsiveCssBuilder::generateBorderRadiusCss(
            $selector . ' .notifal-featured-image',
            [
                'tl' => $attributes['radiusTopLeft'] ?? 0,
                'tr' => $attributes['radiusTopRight'] ?? 0,
                'br' => $attributes['radiusBottomRight'] ?? 0,
                'bl' => $attributes['radiusBottomLeft'] ?? 0,
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

        return $css;
    }
} 
