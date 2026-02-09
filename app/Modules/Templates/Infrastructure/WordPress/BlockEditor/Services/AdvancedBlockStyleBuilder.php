<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services;

defined('ABSPATH') || exit;

/**
 * Class AdvancedBlockStyleBuilder
 *
 * Builds CSS styles for blocks with advanced settings (Layout, Background, Border, etc.).
 * Handles server-side rendering of advanced block attributes including hover states.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class AdvancedBlockStyleBuilder
{
    /**
     * Core block name for Column block (uses flex-basis for width in flex layout).
     *
     * @var string
     * @since 2.0.0
     */
    public const CORE_COLUMN_BLOCK_NAME = 'core/column';

    /**
     * Generate inline styles from advanced block attributes.
     *
     * For core/column blocks, width is output as flex-basis so it works with core's flex layout.
     * For other blocks, width is output as width.
     *
     * @param array $attributes Block attributes containing notifal advanced settings
     * @param string $block_name Block name (e.g. 'core/column') for property selection
     * @return string CSS inline style string
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildInlineStyles(array $attributes, string $block_name = ''): string
    {
        $styles = [];
        $is_column_block = ($block_name === self::CORE_COLUMN_BLOCK_NAME);

        // Margin
        $margin_top = $attributes['notifalMarginTop'] ?? 0;
        $margin_right = $attributes['notifalMarginRight'] ?? 0;
        $margin_bottom = $attributes['notifalMarginBottom'] ?? 0;
        $margin_left = $attributes['notifalMarginLeft'] ?? 0;
        $margin_unit = $attributes['notifalMarginUnit'] ?? 'px';

        if ($margin_top || $margin_right || $margin_bottom || $margin_left) {
            $styles[] = sprintf('margin: %d%s %d%s %d%s %d%s',
                $margin_top, $margin_unit,
                $margin_right, $margin_unit,
                $margin_bottom, $margin_unit,
                $margin_left, $margin_unit
            );
        }

        // Padding
        $padding_top = $attributes['notifalPaddingTop'] ?? 0;
        $padding_right = $attributes['notifalPaddingRight'] ?? 0;
        $padding_bottom = $attributes['notifalPaddingBottom'] ?? 0;
        $padding_left = $attributes['notifalPaddingLeft'] ?? 0;
        $padding_unit = $attributes['notifalPaddingUnit'] ?? 'px';

        if ($padding_top || $padding_right || $padding_bottom || $padding_left) {
            $styles[] = sprintf('padding: %d%s %d%s %d%s %d%s',
                $padding_top, $padding_unit,
                $padding_right, $padding_unit,
                $padding_bottom, $padding_unit,
                $padding_left, $padding_unit
            );
        }

        // Width: for core/column output both width and flex-basis so it works when parent is row (flex-basis)
        // or column (width = cross-axis size when stacked); for others use width only
        $width_type = $attributes['notifalWidthType'] ?? 'default';
        if ($width_type === 'full-width') {
            if ($is_column_block) {
                $styles[] = 'width: 100%';
                $styles[] = 'flex-basis: 100%';
            } else {
                $styles[] = 'width: 100%';
            }
        } elseif ($width_type === 'inline') {
            $styles[] = 'display: inline-block';
            $styles[] = 'width: auto';
        } elseif ($width_type === 'custom') {
            $custom_width = $attributes['notifalCustomWidth'] ?? 100;
            $custom_width_unit = $attributes['notifalCustomWidthUnit'] ?? '%';
            if ($is_column_block) {
                $width_val = sprintf('%d%s', $custom_width, $custom_width_unit);
                $styles[] = sprintf('width: %s', $width_val);
                $styles[] = sprintf('flex-basis: %s', $width_val);
                // Enforce width when stacked (parent flex-direction: column); prevents core stacking styles from overriding
                $styles[] = sprintf('max-width: %s', $width_val);
                $styles[] = 'min-width: 0';
            } else {
                $styles[] = sprintf('width: %d%s', $custom_width, $custom_width_unit);
            }
        }

        // Position
        $position = $attributes['notifalPosition'] ?? 'default';
        if ($position !== 'default') {
            $styles[] = sprintf('position: %s', esc_attr($position));
            // Position orientation & offset (fixed/absolute only)
            if ($position === 'fixed' || $position === 'absolute') {
                $horizontal = $attributes['notifalPositionHorizontal'] ?? 'left';
                $horizontal_offset = isset($attributes['notifalPositionHorizontalOffset'])
                    ? (int) $attributes['notifalPositionHorizontalOffset']
                    : 0;
                $vertical = $attributes['notifalPositionVertical'] ?? 'top';
                $vertical_offset = isset($attributes['notifalPositionVerticalOffset'])
                    ? (int) $attributes['notifalPositionVerticalOffset']
                    : 0;
                if ($horizontal === 'left') {
                    $styles[] = sprintf('left: %dpx', $horizontal_offset);
                    $styles[] = 'right: auto';
                } else {
                    $styles[] = sprintf('right: %dpx', $horizontal_offset);
                    $styles[] = 'left: auto';
                }
                if ($vertical === 'top') {
                    $styles[] = sprintf('top: %dpx', $vertical_offset);
                    $styles[] = 'bottom: auto';
                } else {
                    $styles[] = sprintf('bottom: %dpx', $vertical_offset);
                    $styles[] = 'top: auto';
                }
            }
        }

        // Z-Index
        $z_index = $attributes['notifalZIndex'] ?? 0;
        if ($z_index !== 0) {
            $styles[] = sprintf('z-index: %d', intval($z_index));
        }

        // Align Self (using margin-based horizontal alignment)
        $align_self = $attributes['notifalAlignSelf'] ?? 'default';
        if ($align_self !== 'default') {
            switch ($align_self) {
                case 'start':
                    $styles[] = 'margin-left: 0';
                    $styles[] = 'margin-right: auto';
                    break;
                case 'center':
                    $styles[] = 'margin-left: auto';
                    $styles[] = 'margin-right: auto';
                    break;
                case 'end':
                    $styles[] = 'margin-left: auto';
                    $styles[] = 'margin-right: 0';
                    break;
                case 'stretch':
                    if ($is_column_block) {
                        $styles[] = 'flex-basis: 100%';
                    } else {
                        $styles[] = 'width: 100%';
                    }
                    break;
            }
        }

        // Background
        $background_type = $attributes['notifalBackgroundType'] ?? 'simple';
        if ($background_type === 'simple') {
            $background_color = $attributes['notifalBackgroundColor'] ?? '';
            if ($background_color) {
                $styles[] = sprintf('background-color: %s', esc_attr($background_color));
            }
        } elseif ($background_type === 'gradient') {
            $gradient_from = $attributes['notifalBackgroundGradientFrom'] ?? '#ffffff';
            $gradient_to = $attributes['notifalBackgroundGradientTo'] ?? '#000000';
            $gradient_direction = $attributes['notifalBackgroundGradientDirection'] ?? 'to right';
            $styles[] = sprintf(
                'background-image: linear-gradient(%s, %s, %s)',
                esc_attr($gradient_direction),
                esc_attr($gradient_from),
                esc_attr($gradient_to)
            );
        } elseif ($background_type === 'image') {
            $bg_image_url = $attributes['notifalBackgroundImageUrl'] ?? '';
            if ($bg_image_url !== '') {
                $styles[] = sprintf('background-image: url("%s")', esc_url($bg_image_url));
                $styles[] = sprintf('background-size: %s', esc_attr($attributes['notifalBackgroundImageSize'] ?? 'cover'));
                $styles[] = sprintf('background-position: %s', esc_attr($attributes['notifalBackgroundImagePosition'] ?? 'center center'));
                $styles[] = sprintf('background-repeat: %s', esc_attr($attributes['notifalBackgroundImageRepeat'] ?? 'no-repeat'));
                $styles[] = sprintf('background-attachment: %s', esc_attr($attributes['notifalBackgroundImageAttachment'] ?? 'scroll'));
            }
        }

        // Border
        $border_style = $attributes['notifalBorderStyle'] ?? 'none';
        if ($border_style !== 'none' && $border_style !== 'default') {
            $border_top = $attributes['notifalBorderTop'] ?? 0;
            $border_right = $attributes['notifalBorderRight'] ?? 0;
            $border_bottom = $attributes['notifalBorderBottom'] ?? 0;
            $border_left = $attributes['notifalBorderLeft'] ?? 0;
            $border_color = $attributes['notifalBorderColor'] ?? '';

            $styles[] = sprintf('border-style: %s', esc_attr($border_style));
            $styles[] = sprintf('border-width: %dpx %dpx %dpx %dpx',
                intval($border_top),
                intval($border_right),
                intval($border_bottom),
                intval($border_left)
            );

            if ($border_color) {
                $styles[] = sprintf('border-color: %s', esc_attr($border_color));
            }
        }

        // Border Radius
        $radius_tl = $attributes['notifalBorderRadiusTopLeft'] ?? 0;
        $radius_tr = $attributes['notifalBorderRadiusTopRight'] ?? 0;
        $radius_br = $attributes['notifalBorderRadiusBottomRight'] ?? 0;
        $radius_bl = $attributes['notifalBorderRadiusBottomLeft'] ?? 0;

        if ($radius_tl || $radius_tr || $radius_br || $radius_bl) {
            $styles[] = sprintf('border-radius: %dpx %dpx %dpx %dpx',
                intval($radius_tl),
                intval($radius_tr),
                intval($radius_br),
                intval($radius_bl)
            );
        }

        // Box Shadow
        $shadow_h = $attributes['notifalBoxShadowH'] ?? 0;
        $shadow_v = $attributes['notifalBoxShadowV'] ?? 0;
        $shadow_blur = $attributes['notifalBoxShadowBlur'] ?? 0;
        $shadow_spread = $attributes['notifalBoxShadowSpread'] ?? 0;
        $shadow_color = $attributes['notifalBoxShadowColor'] ?? 'rgba(0, 0, 0, 0)';
        $shadow_inset = $attributes['notifalBoxShadowInset'] ?? false;

        if ($shadow_h || $shadow_v || $shadow_blur || $shadow_spread) {
            $inset_str = $shadow_inset ? 'inset ' : '';
            $styles[] = sprintf(
                'box-shadow: %s%dpx %dpx %dpx %dpx %s',
                $inset_str,
                intval($shadow_h),
                intval($shadow_v),
                intval($shadow_blur),
                intval($shadow_spread),
                esc_attr($shadow_color)
            );
        }

        // Flexbox Direction
        $flex_direction = $attributes['notifalFlexDirection'] ?? 'default';
        if ($flex_direction && $flex_direction !== 'default') {
            $styles[] = 'display: flex';
            $styles[] = sprintf('flex-direction: %s', esc_attr($flex_direction));
        }

        // Flexbox Justify Content
        $justify_content = $attributes['notifalJustifyContent'] ?? 'default';
        if ($justify_content && $justify_content !== 'default') {
            // Add display: flex if not already added
            if ($flex_direction === 'default') {
                $styles[] = 'display: flex';
            }
            $styles[] = sprintf('justify-content: %s', esc_attr($justify_content));
        }

        // Flexbox Align Items
        $align_items = $attributes['notifalAlignItems'] ?? 'default';
        if ($align_items && $align_items !== 'default') {
            // Add display: flex if not already added
            if ($flex_direction === 'default' && $justify_content === 'default') {
                $styles[] = 'display: flex';
            }
            $styles[] = sprintf('align-items: %s', esc_attr($align_items));
        }

        // Flexbox Wrap
        $flex_wrap = $attributes['notifalFlexWrap'] ?? 'default';
        if ($flex_wrap && $flex_wrap !== 'default') {
            // Add display: flex if not already added
            if ($flex_direction === 'default' && $justify_content === 'default' && $align_items === 'default') {
                $styles[] = 'display: flex';
            }
            $styles[] = sprintf('flex-wrap: %s', esc_attr($flex_wrap));
        }

        // Flexbox Gap
        $gap_row = $attributes['notifalGapRow'] ?? 0;
        $gap_column = $attributes['notifalGapColumn'] ?? 0;
        $gap_unit = $attributes['notifalGapUnit'] ?? 'px';

        if ($gap_row || $gap_column) {
            // Add display: flex if not already added
            if ($flex_direction === 'default' && $justify_content === 'default' && $align_items === 'default' && $flex_wrap === 'default') {
                $styles[] = 'display: flex';
            }
            $styles[] = sprintf('row-gap: %d%s', intval($gap_row), esc_attr($gap_unit));
            $styles[] = sprintf('column-gap: %d%s', intval($gap_column), esc_attr($gap_unit));
        }

        return implode('; ', $styles);
    }

    /**
     * Generate CSS class string from advanced block attributes.
     *
     * @param array $attributes Block attributes
     * @return string Space-separated class names
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildCssClasses(array $attributes): string
    {
        $classes = [];

        // User-defined CSS classes
        $css_classes = $attributes['notifalCssClasses'] ?? '';
        if ($css_classes) {
            $classes[] = sanitize_text_field($css_classes);
        }

        // Add responsive class if any responsive settings are enabled
        $hide_on_desktop = $attributes['notifalHideOnDesktop'] ?? false;
        $hide_on_tablet = $attributes['notifalHideOnTablet'] ?? false;
        $hide_on_mobile = $attributes['notifalHideOnMobile'] ?? false;
        $block_id = $attributes['notifalBlockId'] ?? '';
        
        // Check for responsive flexbox settings
        $has_responsive_flexbox = 
            isset($attributes['notifalFlexDirectionTablet']) || 
            isset($attributes['notifalFlexDirectionMobile']) ||
            isset($attributes['notifalJustifyContentTablet']) || 
            isset($attributes['notifalJustifyContentMobile']) ||
            isset($attributes['notifalAlignItemsTablet']) || 
            isset($attributes['notifalAlignItemsMobile']) ||
            isset($attributes['notifalFlexWrapTablet']) || 
            isset($attributes['notifalFlexWrapMobile']) ||
            isset($attributes['notifalGapRowTablet']) || 
            isset($attributes['notifalGapRowMobile']) ||
            isset($attributes['notifalGapColumnTablet']) || 
            isset($attributes['notifalGapColumnMobile']);

        if (($hide_on_desktop || $hide_on_tablet || $hide_on_mobile || $has_responsive_flexbox) && $block_id) {
            $classes[] = sprintf('notifal-responsive-%s', esc_attr($block_id));
        }

        return implode(' ', $classes);
    }

    /**
     * Get CSS ID from advanced block attributes.
     *
     * @param array $attributes Block attributes
     * @return string Sanitized CSS ID
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildCssId(array $attributes): string
    {
        $css_id = $attributes['notifalCssId'] ?? '';
        return $css_id ? sanitize_html_class($css_id) : '';
    }

    /**
     * Parse and sanitize custom attributes.
     *
     * Converts custom attributes string (key|value format) to array.
     *
     * @param array $attributes Block attributes
     * @return array Associative array of custom attributes
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function parseCustomAttributes(array $attributes): array
    {
        $custom_attrs = $attributes['notifalCustomAttributes'] ?? '';
        if (empty($custom_attrs)) {
            return [];
        }

        $result = [];
        $lines = explode("\n", $custom_attrs);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Parse key|value format
            $parts = explode('|', $line, 2);
            if (count($parts) === 2) {
                $key = sanitize_key(trim($parts[0]));
                $value = sanitize_text_field(trim($parts[1]));

                // Security: Prevent dangerous attributes
                $disallowed_attrs = ['onclick', 'onload', 'onerror', 'onmouseover', 'onfocus', 'onblur'];
                if (!in_array(strtolower($key), $disallowed_attrs, true)) {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Build hover state CSS for a block.
     *
     * Generates CSS rules for hover effects based on advanced block attributes.
     *
     * @param string $block_id Unique block identifier (CSS selector)
     * @param array $attributes Block attributes
     * @return string CSS rules for hover state
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildHoverStyles(string $block_id, array $attributes): string
    {
        $hover_styles = [];

        // Background hover
        $background_type = $attributes['notifalBackgroundType'] ?? 'simple';
        if ($background_type === 'simple') {
            $bg_color_hover = $attributes['notifalBackgroundColorHover'] ?? '';
            if ($bg_color_hover) {
                $hover_styles[] = sprintf('background-color: %s', esc_attr($bg_color_hover));
            }
        } elseif ($background_type === 'gradient') {
            $gradient_from_hover = $attributes['notifalBackgroundGradientFromHover'] ?? '';
            $gradient_to_hover = $attributes['notifalBackgroundGradientToHover'] ?? '';
            $gradient_direction = $attributes['notifalBackgroundGradientDirection'] ?? 'to right';

            if ($gradient_from_hover && $gradient_to_hover) {
                $hover_styles[] = sprintf(
                    'background-image: linear-gradient(%s, %s, %s)',
                    esc_attr($gradient_direction),
                    esc_attr($gradient_from_hover),
                    esc_attr($gradient_to_hover)
                );
            }
        }

        // Border hover
        $border_style_hover = $attributes['notifalBorderStyleHover'] ?? 'default';
        if ($border_style_hover !== 'default' && $border_style_hover !== 'none') {
            $hover_styles[] = sprintf('border-style: %s', esc_attr($border_style_hover));
        }

        $border_color_hover = $attributes['notifalBorderColorHover'] ?? '';
        if ($border_color_hover) {
            $hover_styles[] = sprintf('border-color: %s', esc_attr($border_color_hover));
        }

        if (empty($hover_styles)) {
            return '';
        }

        // Generate CSS rule
        $selector = sprintf('[data-notifal-block-id="%s"]:hover', esc_attr($block_id));
        return sprintf('%s { %s }', $selector, implode('; ', $hover_styles));
    }

    /**
     * Build custom CSS for a block.
     *
     * Processes custom CSS with selector replacement.
     *
     * @param string $block_id Unique block identifier
     * @param array $attributes Block attributes
     * @return string Processed custom CSS
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildCustomCss(string $block_id, array $attributes): string
    {
        $custom_css = $attributes['notifalCustomCss'] ?? '';
        if (empty($custom_css)) {
            return '';
        }

        // Replace "selector" keyword with actual block selector
        $block_selector = sprintf('[data-notifal-block-id="%s"]', esc_attr($block_id));
        $custom_css = str_replace('selector', $block_selector, $custom_css);

        // Basic sanitization: Remove script tags and dangerous content
        $custom_css = wp_strip_all_tags($custom_css);
        $custom_css = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $custom_css);

        return $custom_css;
    }

    /**
     * Build responsive visibility CSS for a block.
     *
     * Generates CSS media queries to hide blocks on specific devices.
     * Uses standard breakpoints:
     * - Desktop: > 1024px
     * - Tablet: 768px - 1024px
     * - Mobile: < 768px
     *
     * @param string $block_id Unique block identifier
     * @param array $attributes Block attributes
     * @return string CSS rules for responsive visibility
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildResponsiveStyles(string $block_id, array $attributes): string
    {
        $hide_on_desktop = $attributes['notifalHideOnDesktop'] ?? false;
        $hide_on_tablet = $attributes['notifalHideOnTablet'] ?? false;
        $hide_on_mobile = $attributes['notifalHideOnMobile'] ?? false;

        // Return early if no responsive visibility is set
        if (!$hide_on_desktop && !$hide_on_tablet && !$hide_on_mobile) {
            return '';
        }

        $css_rules = [];
        $block_selector = sprintf('[data-notifal-block-id="%s"]', esc_attr($block_id));

        // Desktop: > 1024px
        if ($hide_on_desktop) {
            $css_rules[] = sprintf(
                '@media (min-width: 1025px) { %s { display: none !important; } }',
                $block_selector
            );
        }

        // Tablet: 768px - 1024px
        if ($hide_on_tablet) {
            $css_rules[] = sprintf(
                '@media (min-width: 768px) and (max-width: 1024px) { %s { display: none !important; } }',
                $block_selector
            );
        }

        // Mobile: < 768px
        if ($hide_on_mobile) {
            $css_rules[] = sprintf(
                '@media (max-width: 767px) { %s { display: none !important; } }',
                $block_selector
            );
        }

        return implode(' ', $css_rules);
    }

    /**
     * Build responsive property CSS with media queries.
     *
     * Generates CSS media queries for responsive properties (padding, margin, width, etc.).
     * Uses standard breakpoints
     *
     * @param string $block_id Unique block identifier
     * @param array $attributes Block attributes
     * @param string $block_name Block name (e.g. 'core/column') for property selection
     * @return string CSS rules for responsive properties
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildResponsivePropertyStyles(string $block_id, array $attributes, string $block_name = ''): string
    {
        $css_rules = [];
        $block_selector = sprintf('[data-notifal-block-id="%s"]', esc_attr($block_id));
        $is_column_block = ($block_name === self::CORE_COLUMN_BLOCK_NAME);

        // Helper function to check if a value is set
        $is_set = function($value) {
            return isset($value) && $value !== null && $value !== '';
        };

        // Get units
        $margin_unit = $attributes['notifalMarginUnit'] ?? 'px';
        $padding_unit = $attributes['notifalPaddingUnit'] ?? 'px';
        $custom_width_unit = $attributes['notifalCustomWidthUnit'] ?? '%';
        
        // Tablet styles (768px - 1024px)
        $tablet_styles = [];
        
        // Tablet Margin
        $margin_top_tablet = $attributes['notifalMarginTopTablet'] ?? null;
        $margin_right_tablet = $attributes['notifalMarginRightTablet'] ?? null;
        $margin_bottom_tablet = $attributes['notifalMarginBottomTablet'] ?? null;
        $margin_left_tablet = $attributes['notifalMarginLeftTablet'] ?? null;
        
        if ($is_set($margin_top_tablet) || $is_set($margin_right_tablet) || $is_set($margin_bottom_tablet) || $is_set($margin_left_tablet)) {
            if ($is_set($margin_top_tablet)) $tablet_styles[] = sprintf('margin-top: %d%s !important', intval($margin_top_tablet), $margin_unit);
            if ($is_set($margin_right_tablet)) $tablet_styles[] = sprintf('margin-right: %d%s !important', intval($margin_right_tablet), $margin_unit);
            if ($is_set($margin_bottom_tablet)) $tablet_styles[] = sprintf('margin-bottom: %d%s !important', intval($margin_bottom_tablet), $margin_unit);
            if ($is_set($margin_left_tablet)) $tablet_styles[] = sprintf('margin-left: %d%s !important', intval($margin_left_tablet), $margin_unit);
        }
        
        // Tablet Padding
        $padding_top_tablet = $attributes['notifalPaddingTopTablet'] ?? null;
        $padding_right_tablet = $attributes['notifalPaddingRightTablet'] ?? null;
        $padding_bottom_tablet = $attributes['notifalPaddingBottomTablet'] ?? null;
        $padding_left_tablet = $attributes['notifalPaddingLeftTablet'] ?? null;
        
        if ($is_set($padding_top_tablet) || $is_set($padding_right_tablet) || $is_set($padding_bottom_tablet) || $is_set($padding_left_tablet)) {
            if ($is_set($padding_top_tablet)) $tablet_styles[] = sprintf('padding-top: %d%s !important', intval($padding_top_tablet), $padding_unit);
            if ($is_set($padding_right_tablet)) $tablet_styles[] = sprintf('padding-right: %d%s !important', intval($padding_right_tablet), $padding_unit);
            if ($is_set($padding_bottom_tablet)) $tablet_styles[] = sprintf('padding-bottom: %d%s !important', intval($padding_bottom_tablet), $padding_unit);
            if ($is_set($padding_left_tablet)) $tablet_styles[] = sprintf('padding-left: %d%s !important', intval($padding_left_tablet), $padding_unit);
        }
        
        // Tablet Width: for core/column always output when custom (use desktop as fallback) so !important overrides core stacking
        $width_type = $attributes['notifalWidthType'] ?? 'default';
        $custom_width_desktop = $attributes['notifalCustomWidth'] ?? 100;
        $custom_width_tablet = $attributes['notifalCustomWidthTablet'] ?? null;
        if ($width_type === 'custom') {
            if ($is_column_block) {
                $custom_width_tablet_val = $is_set($custom_width_tablet) ? intval($custom_width_tablet) : intval($custom_width_desktop);
                $tablet_styles[] = sprintf('width: %d%s !important', $custom_width_tablet_val, $custom_width_unit);
                $tablet_styles[] = sprintf('flex-basis: %d%s !important', $custom_width_tablet_val, $custom_width_unit);
                $tablet_styles[] = sprintf('max-width: %d%s !important', $custom_width_tablet_val, $custom_width_unit);
                $tablet_styles[] = 'min-width: 0 !important';
            } elseif ($is_set($custom_width_tablet)) {
                $tablet_styles[] = sprintf('width: %d%s !important', intval($custom_width_tablet), $custom_width_unit);
            }
        }
        
        // Tablet Z-Index
        $z_index_tablet = $attributes['notifalZIndexTablet'] ?? null;
        if ($is_set($z_index_tablet)) {
            $tablet_styles[] = sprintf('z-index: %d !important', intval($z_index_tablet));
        }
        
        // Tablet Border Width
        $border_style = $attributes['notifalBorderStyle'] ?? 'none';
        if ($border_style !== 'none' && $border_style !== 'default') {
            $border_top_tablet = $attributes['notifalBorderTopTablet'] ?? null;
            $border_right_tablet = $attributes['notifalBorderRightTablet'] ?? null;
            $border_bottom_tablet = $attributes['notifalBorderBottomTablet'] ?? null;
            $border_left_tablet = $attributes['notifalBorderLeftTablet'] ?? null;
            
            if ($is_set($border_top_tablet)) $tablet_styles[] = sprintf('border-top-width: %dpx !important', intval($border_top_tablet));
            if ($is_set($border_right_tablet)) $tablet_styles[] = sprintf('border-right-width: %dpx !important', intval($border_right_tablet));
            if ($is_set($border_bottom_tablet)) $tablet_styles[] = sprintf('border-bottom-width: %dpx !important', intval($border_bottom_tablet));
            if ($is_set($border_left_tablet)) $tablet_styles[] = sprintf('border-left-width: %dpx !important', intval($border_left_tablet));
        }
        
        // Tablet Border Radius
        $radius_tl_tablet = $attributes['notifalBorderRadiusTopLeftTablet'] ?? null;
        $radius_tr_tablet = $attributes['notifalBorderRadiusTopRightTablet'] ?? null;
        $radius_br_tablet = $attributes['notifalBorderRadiusBottomRightTablet'] ?? null;
        $radius_bl_tablet = $attributes['notifalBorderRadiusBottomLeftTablet'] ?? null;
        
        if ($is_set($radius_tl_tablet)) $tablet_styles[] = sprintf('border-top-left-radius: %dpx !important', intval($radius_tl_tablet));
        if ($is_set($radius_tr_tablet)) $tablet_styles[] = sprintf('border-top-right-radius: %dpx !important', intval($radius_tr_tablet));
        if ($is_set($radius_br_tablet)) $tablet_styles[] = sprintf('border-bottom-right-radius: %dpx !important', intval($radius_br_tablet));
        if ($is_set($radius_bl_tablet)) $tablet_styles[] = sprintf('border-bottom-left-radius: %dpx !important', intval($radius_bl_tablet));
        
        // Generate tablet media query
        if (!empty($tablet_styles)) {
            $css_rules[] = sprintf(
                '@media (min-width: 768px) and (max-width: 1024px) { %s { %s } }',
                $block_selector,
                implode('; ', $tablet_styles)
            );
        }
        
        // Mobile styles (< 768px)
        $mobile_styles = [];
        
        // Mobile Margin
        $margin_top_mobile = $attributes['notifalMarginTopMobile'] ?? null;
        $margin_right_mobile = $attributes['notifalMarginRightMobile'] ?? null;
        $margin_bottom_mobile = $attributes['notifalMarginBottomMobile'] ?? null;
        $margin_left_mobile = $attributes['notifalMarginLeftMobile'] ?? null;
        
        if ($is_set($margin_top_mobile) || $is_set($margin_right_mobile) || $is_set($margin_bottom_mobile) || $is_set($margin_left_mobile)) {
            if ($is_set($margin_top_mobile)) $mobile_styles[] = sprintf('margin-top: %d%s !important', intval($margin_top_mobile), $margin_unit);
            if ($is_set($margin_right_mobile)) $mobile_styles[] = sprintf('margin-right: %d%s !important', intval($margin_right_mobile), $margin_unit);
            if ($is_set($margin_bottom_mobile)) $mobile_styles[] = sprintf('margin-bottom: %d%s !important', intval($margin_bottom_mobile), $margin_unit);
            if ($is_set($margin_left_mobile)) $mobile_styles[] = sprintf('margin-left: %d%s !important', intval($margin_left_mobile), $margin_unit);
        }
        
        // Mobile Padding
        $padding_top_mobile = $attributes['notifalPaddingTopMobile'] ?? null;
        $padding_right_mobile = $attributes['notifalPaddingRightMobile'] ?? null;
        $padding_bottom_mobile = $attributes['notifalPaddingBottomMobile'] ?? null;
        $padding_left_mobile = $attributes['notifalPaddingLeftMobile'] ?? null;
        
        if ($is_set($padding_top_mobile) || $is_set($padding_right_mobile) || $is_set($padding_bottom_mobile) || $is_set($padding_left_mobile)) {
            if ($is_set($padding_top_mobile)) $mobile_styles[] = sprintf('padding-top: %d%s !important', intval($padding_top_mobile), $padding_unit);
            if ($is_set($padding_right_mobile)) $mobile_styles[] = sprintf('padding-right: %d%s !important', intval($padding_right_mobile), $padding_unit);
            if ($is_set($padding_bottom_mobile)) $mobile_styles[] = sprintf('padding-bottom: %d%s !important', intval($padding_bottom_mobile), $padding_unit);
            if ($is_set($padding_left_mobile)) $mobile_styles[] = sprintf('padding-left: %d%s !important', intval($padding_left_mobile), $padding_unit);
        }
        
        // Mobile Width: for core/column always output when custom (use tablet/desktop as fallback) so !important overrides core stacking
        $custom_width_mobile = $attributes['notifalCustomWidthMobile'] ?? null;
        if ($width_type === 'custom') {
            if ($is_column_block) {
                $custom_width_mobile_val = $is_set($custom_width_mobile)
                    ? intval($custom_width_mobile)
                    : ($is_set($custom_width_tablet) ? intval($custom_width_tablet) : intval($custom_width_desktop));
                $mobile_styles[] = sprintf('width: %d%s !important', $custom_width_mobile_val, $custom_width_unit);
                $mobile_styles[] = sprintf('flex-basis: %d%s !important', $custom_width_mobile_val, $custom_width_unit);
                $mobile_styles[] = sprintf('max-width: %d%s !important', $custom_width_mobile_val, $custom_width_unit);
                $mobile_styles[] = 'min-width: 0 !important';
            } elseif ($is_set($custom_width_mobile)) {
                $mobile_styles[] = sprintf('width: %d%s !important', intval($custom_width_mobile), $custom_width_unit);
            }
        }
        
        // Mobile Z-Index
        $z_index_mobile = $attributes['notifalZIndexMobile'] ?? null;
        if ($is_set($z_index_mobile)) {
            $mobile_styles[] = sprintf('z-index: %d !important', intval($z_index_mobile));
        }
        
        // Mobile Border Width
        if ($border_style !== 'none' && $border_style !== 'default') {
            $border_top_mobile = $attributes['notifalBorderTopMobile'] ?? null;
            $border_right_mobile = $attributes['notifalBorderRightMobile'] ?? null;
            $border_bottom_mobile = $attributes['notifalBorderBottomMobile'] ?? null;
            $border_left_mobile = $attributes['notifalBorderLeftMobile'] ?? null;
            
            if ($is_set($border_top_mobile)) $mobile_styles[] = sprintf('border-top-width: %dpx !important', intval($border_top_mobile));
            if ($is_set($border_right_mobile)) $mobile_styles[] = sprintf('border-right-width: %dpx !important', intval($border_right_mobile));
            if ($is_set($border_bottom_mobile)) $mobile_styles[] = sprintf('border-bottom-width: %dpx !important', intval($border_bottom_mobile));
            if ($is_set($border_left_mobile)) $mobile_styles[] = sprintf('border-left-width: %dpx !important', intval($border_left_mobile));
        }
        
        // Mobile Border Radius
        $radius_tl_mobile = $attributes['notifalBorderRadiusTopLeftMobile'] ?? null;
        $radius_tr_mobile = $attributes['notifalBorderRadiusTopRightMobile'] ?? null;
        $radius_br_mobile = $attributes['notifalBorderRadiusBottomRightMobile'] ?? null;
        $radius_bl_mobile = $attributes['notifalBorderRadiusBottomLeftMobile'] ?? null;
        
        if ($is_set($radius_tl_mobile)) $mobile_styles[] = sprintf('border-top-left-radius: %dpx !important', intval($radius_tl_mobile));
        if ($is_set($radius_tr_mobile)) $mobile_styles[] = sprintf('border-top-right-radius: %dpx !important', intval($radius_tr_mobile));
        if ($is_set($radius_br_mobile)) $mobile_styles[] = sprintf('border-bottom-right-radius: %dpx !important', intval($radius_br_mobile));
        if ($is_set($radius_bl_mobile)) $mobile_styles[] = sprintf('border-bottom-left-radius: %dpx !important', intval($radius_bl_mobile));
        
        // Generate mobile media query
        if (!empty($mobile_styles)) {
            $css_rules[] = sprintf(
                '@media (max-width: 767px) { %s { %s } }',
                $block_selector,
                implode('; ', $mobile_styles)
            );
        }
        
        return implode(' ', $css_rules);
    }

    /**
     * Build responsive flexbox CSS with media queries.
     *
     * Generates CSS media queries for responsive flexbox properties.
     * Uses standard breakpoints:
     * - Desktop: > 1024px (base styles, no media query)
     * - Tablet: 768px - 1024px
     * - Mobile: < 768px
     *
     * @param string $block_id Unique block identifier
     * @param array $attributes Block attributes
     * @return string CSS rules for responsive flexbox
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildResponsiveFlexboxStyles(string $block_id, array $attributes): string
    {
        $css_rules = [];
        $block_selector = sprintf('[data-notifal-block-id="%s"]', esc_attr($block_id));
        
        // Helper function to check if a value is set
        $is_set = function($value) {
            return isset($value) && $value !== null && $value !== '' && $value !== 'default';
        };
        
        // Get gap unit
        $gap_unit = $attributes['notifalGapUnit'] ?? 'px';
        
        // Desktop styles (base styles, no media query)
        $desktop_styles = [];
        $desktop_has_flex = false;
        
        // Desktop Flex Direction
        $flex_direction = $attributes['notifalFlexDirection'] ?? 'default';
        if ($is_set($flex_direction)) {
            $desktop_has_flex = true;
            $desktop_styles[] = 'display: flex !important';
            $desktop_styles[] = sprintf('flex-direction: %s !important', esc_attr($flex_direction));
        }
        
        // Desktop Justify Content
        $justify_content = $attributes['notifalJustifyContent'] ?? 'default';
        if ($is_set($justify_content)) {
            if (!$desktop_has_flex) {
                $desktop_styles[] = 'display: flex !important';
                $desktop_has_flex = true;
            }
            $desktop_styles[] = sprintf('justify-content: %s !important', esc_attr($justify_content));
        }
        
        // Desktop Align Items
        $align_items = $attributes['notifalAlignItems'] ?? 'default';
        if ($is_set($align_items)) {
            if (!$desktop_has_flex) {
                $desktop_styles[] = 'display: flex !important';
                $desktop_has_flex = true;
            }
            $desktop_styles[] = sprintf('align-items: %s !important', esc_attr($align_items));
        }
        
        // Desktop Flex Wrap
        $flex_wrap = $attributes['notifalFlexWrap'] ?? 'default';
        if ($is_set($flex_wrap)) {
            if (!$desktop_has_flex) {
                $desktop_styles[] = 'display: flex !important';
                $desktop_has_flex = true;
            }
            $desktop_styles[] = sprintf('flex-wrap: %s !important', esc_attr($flex_wrap));
        }
        
        // Desktop Gap
        $gap_row = $attributes['notifalGapRow'] ?? 0;
        $gap_column = $attributes['notifalGapColumn'] ?? 0;
        
        if ($gap_row || $gap_column) {
            if (!$desktop_has_flex) {
                $desktop_styles[] = 'display: flex !important';
            }
            if ($gap_row) {
                $desktop_styles[] = sprintf('row-gap: %d%s !important', intval($gap_row), esc_attr($gap_unit));
            }
            if ($gap_column) {
                $desktop_styles[] = sprintf('column-gap: %d%s !important', intval($gap_column), esc_attr($gap_unit));
            }
        }
        
        // Generate desktop base styles (no media query)
        if (!empty($desktop_styles)) {
            $css_rules[] = sprintf(
                '%s { %s }',
                $block_selector,
                implode('; ', $desktop_styles)
            );
        }
        
        // Tablet styles (768px - 1024px)
        $tablet_styles = [];
        $tablet_has_flex = false;
        
        // Tablet Flex Direction
        $flex_direction_tablet = $attributes['notifalFlexDirectionTablet'] ?? null;
        if ($is_set($flex_direction_tablet)) {
            $tablet_has_flex = true;
            $tablet_styles[] = 'display: flex !important';
            $tablet_styles[] = sprintf('flex-direction: %s !important', esc_attr($flex_direction_tablet));
        }
        
        // Tablet Justify Content
        $justify_content_tablet = $attributes['notifalJustifyContentTablet'] ?? null;
        if ($is_set($justify_content_tablet)) {
            if (!$tablet_has_flex) {
                $tablet_styles[] = 'display: flex !important';
                $tablet_has_flex = true;
            }
            $tablet_styles[] = sprintf('justify-content: %s !important', esc_attr($justify_content_tablet));
        }
        
        // Tablet Align Items
        $align_items_tablet = $attributes['notifalAlignItemsTablet'] ?? null;
        if ($is_set($align_items_tablet)) {
            if (!$tablet_has_flex) {
                $tablet_styles[] = 'display: flex !important';
                $tablet_has_flex = true;
            }
            $tablet_styles[] = sprintf('align-items: %s !important', esc_attr($align_items_tablet));
        }
        
        // Tablet Flex Wrap
        $flex_wrap_tablet = $attributes['notifalFlexWrapTablet'] ?? null;
        if ($is_set($flex_wrap_tablet)) {
            if (!$tablet_has_flex) {
                $tablet_styles[] = 'display: flex !important';
                $tablet_has_flex = true;
            }
            $tablet_styles[] = sprintf('flex-wrap: %s !important', esc_attr($flex_wrap_tablet));
        }
        
        // Tablet Gap
        $gap_row_tablet = $attributes['notifalGapRowTablet'] ?? null;
        $gap_column_tablet = $attributes['notifalGapColumnTablet'] ?? null;
        
        if (isset($gap_row_tablet) || isset($gap_column_tablet)) {
            if (!$tablet_has_flex) {
                $tablet_styles[] = 'display: flex !important';
            }
            if (isset($gap_row_tablet)) {
                $tablet_styles[] = sprintf('row-gap: %d%s !important', intval($gap_row_tablet), esc_attr($gap_unit));
            }
            if (isset($gap_column_tablet)) {
                $tablet_styles[] = sprintf('column-gap: %d%s !important', intval($gap_column_tablet), esc_attr($gap_unit));
            }
        }
        
        // Generate tablet media query
        if (!empty($tablet_styles)) {
            $css_rules[] = sprintf(
                '@media (min-width: 768px) and (max-width: 1024px) { %s { %s } }',
                $block_selector,
                implode('; ', $tablet_styles)
            );
        }
        
        // Mobile styles (< 768px)
        $mobile_styles = [];
        $mobile_has_flex = false;
        
        // Mobile Flex Direction
        $flex_direction_mobile = $attributes['notifalFlexDirectionMobile'] ?? null;
        if ($is_set($flex_direction_mobile)) {
            $mobile_has_flex = true;
            $mobile_styles[] = 'display: flex !important';
            $mobile_styles[] = sprintf('flex-direction: %s !important', esc_attr($flex_direction_mobile));
        }
        
        // Mobile Justify Content
        $justify_content_mobile = $attributes['notifalJustifyContentMobile'] ?? null;
        if ($is_set($justify_content_mobile)) {
            if (!$mobile_has_flex) {
                $mobile_styles[] = 'display: flex !important';
                $mobile_has_flex = true;
            }
            $mobile_styles[] = sprintf('justify-content: %s !important', esc_attr($justify_content_mobile));
        }
        
        // Mobile Align Items
        $align_items_mobile = $attributes['notifalAlignItemsMobile'] ?? null;
        if ($is_set($align_items_mobile)) {
            if (!$mobile_has_flex) {
                $mobile_styles[] = 'display: flex !important';
                $mobile_has_flex = true;
            }
            $mobile_styles[] = sprintf('align-items: %s !important', esc_attr($align_items_mobile));
        }
        
        // Mobile Flex Wrap
        $flex_wrap_mobile = $attributes['notifalFlexWrapMobile'] ?? null;
        if ($is_set($flex_wrap_mobile)) {
            if (!$mobile_has_flex) {
                $mobile_styles[] = 'display: flex !important';
                $mobile_has_flex = true;
            }
            $mobile_styles[] = sprintf('flex-wrap: %s !important', esc_attr($flex_wrap_mobile));
        }
        
        // Mobile Gap
        $gap_row_mobile = $attributes['notifalGapRowMobile'] ?? null;
        $gap_column_mobile = $attributes['notifalGapColumnMobile'] ?? null;
        
        if (isset($gap_row_mobile) || isset($gap_column_mobile)) {
            if (!$mobile_has_flex) {
                $mobile_styles[] = 'display: flex !important';
            }
            if (isset($gap_row_mobile)) {
                $mobile_styles[] = sprintf('row-gap: %d%s !important', intval($gap_row_mobile), esc_attr($gap_unit));
            }
            if (isset($gap_column_mobile)) {
                $mobile_styles[] = sprintf('column-gap: %d%s !important', intval($gap_column_mobile), esc_attr($gap_unit));
            }
        }
        
        // Generate mobile media query
        if (!empty($mobile_styles)) {
            $css_rules[] = sprintf(
                '@media (max-width: 767px) { %s { %s } }',
                $block_selector,
                implode('; ', $mobile_styles)
            );
        }
        
        return implode(' ', $css_rules);
    }

    /**
     * Generate complete wrapper attributes for a block with advanced settings.
     *
     * @param string $block_id Unique block identifier
     * @param array $attributes Block attributes
     * @param string $block_name Block name (e.g. 'core/column') for property selection
     * @return array Wrapper attributes (style, class, id, data-*, etc.)
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function buildWrapperAttributes(string $block_id, array $attributes, string $block_name = ''): array
    {
        $wrapper_attrs = [];

        // Inline styles (pass block name so column uses flex-basis for width)
        $inline_styles = self::buildInlineStyles($attributes, $block_name);
        if ($inline_styles) {
            $wrapper_attrs['style'] = $inline_styles;
        }

        // CSS Classes
        $css_classes = self::buildCssClasses($attributes);
        if ($css_classes) {
            $wrapper_attrs['class'] = $css_classes;
        }

        // CSS ID
        $css_id = self::buildCssId($attributes);
        if ($css_id) {
            $wrapper_attrs['id'] = $css_id;
        }

        // Custom attributes
        $custom_attrs = self::parseCustomAttributes($attributes);
        foreach ($custom_attrs as $key => $value) {
            $wrapper_attrs[$key] = $value;
        }

        // Add block ID for hover/custom CSS targeting
        $wrapper_attrs['data-notifal-block-id'] = $block_id;

        return $wrapper_attrs;
    }
}
