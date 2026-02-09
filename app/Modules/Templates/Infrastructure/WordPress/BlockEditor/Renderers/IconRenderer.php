<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Renderers;

use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Traits\BlockRendererTrait;
use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\IconStyleBuilder;

defined('ABSPATH') || exit;

/**
 * Class IconRenderer
 *
 * Server-side renderer for the Icon Gutenberg block.
 * Handles dynamic content generation and styling.
 * Follows Notifal Laravel-like architecture with proper separation of concerns.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Renderers
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class IconRenderer
{
    use BlockRendererTrait;

    /**
     * Render Icon block with server-side processing.
     *
     * Generates dynamic icon HTML with styling support.
     * Handles SVG content rendering and accessibility features.
     *
     * @param array $attributes Block attributes from Gutenberg editor.
     * @param string $content Block content (usually empty for dynamic blocks).
     * @param mixed $block Block instance data.
     * @return string Rendered HTML output.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function render(array $attributes = [], string $content = '', $block = null): string
    {
        self::fireBeforeRenderHook('icon', $attributes, $content, $block);

        // Sanitize and set default attributes
        $attributes = self::sanitizeAttributes($attributes);

        // Build icon HTML
        $html = self::buildIconHtml($attributes);

        self::fireAfterRenderHook('icon', $html, $attributes, $content, $block);

        return $html;
    }

    /**
     * Sanitize and validate block attributes.
     *
     * Ensures all attributes have proper defaults and are properly sanitized.
     * Prevents XSS and validates user input for security.
     *
     * @param array $attributes Raw block attributes.
     * @return array Sanitized attributes with defaults.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function sanitizeAttributes(array $attributes): array
    {
        return [
            'svgContent' => self::sanitizeSvgContent($attributes['svgContent'] ?? ''),
            'alignment' => self::sanitizeFromAllowed($attributes['alignment'] ?? 'center', ['flex-start', 'center', 'flex-end'], 'center'),
            'alignmentTablet' => isset($attributes['alignmentTablet']) ? self::sanitizeFromAllowed($attributes['alignmentTablet'], ['flex-start', 'center', 'flex-end'], 'center') : null,
            'alignmentMobile' => isset($attributes['alignmentMobile']) ? self::sanitizeFromAllowed($attributes['alignmentMobile'], ['flex-start', 'center', 'flex-end'], 'center') : null,
            'size' => self::sanitizeInt($attributes['size'] ?? 24, 24, 8),
            'sizeTablet' => isset($attributes['sizeTablet']) ? self::sanitizeInt($attributes['sizeTablet'], 24, 8) : null,
            'sizeMobile' => isset($attributes['sizeMobile']) ? self::sanitizeInt($attributes['sizeMobile'], 24, 8) : null,
            'sizeUnit' => self::sanitizeFromAllowed($attributes['sizeUnit'] ?? 'px', ['px', 'em', 'rem'], 'px'),
            'width' => self::sanitizeInt($attributes['width'] ?? 0, 0, 0),
            'widthUnit' => self::sanitizeFromAllowed($attributes['widthUnit'] ?? 'px', ['px', '%', 'em', 'rem'], 'px'),
            'height' => self::sanitizeInt($attributes['height'] ?? 0, 0, 0),
            'heightUnit' => self::sanitizeFromAllowed($attributes['heightUnit'] ?? 'px', ['px', '%', 'em', 'rem'], 'px'),
            'primaryColor' => self::sanitizeColor($attributes['primaryColor'] ?? '#000000', '#000000'),
            'borderStyle' => self::sanitizeFromAllowed($attributes['borderStyle'] ?? 'none', ['none', 'solid', 'dashed', 'dotted', 'double'], 'none'),
            'borderTop' => self::sanitizeInt($attributes['borderTop'] ?? 0),
            'borderRight' => self::sanitizeInt($attributes['borderRight'] ?? 0),
            'borderBottom' => self::sanitizeInt($attributes['borderBottom'] ?? 0),
            'borderLeft' => self::sanitizeInt($attributes['borderLeft'] ?? 0),
            'borderColor' => self::sanitizeColor($attributes['borderColor'] ?? ''),
            'borderRadius' => self::sanitizeInt($attributes['borderRadius'] ?? 0),
            'backgroundColor' => self::sanitizeColor($attributes['backgroundColor'] ?? ''),
            'backgroundType' => self::sanitizeFromAllowed($attributes['backgroundType'] ?? 'simple', ['simple', 'gradient'], 'simple'),
            'gradientFrom' => self::sanitizeColor($attributes['gradientFrom'] ?? '#ffffff', '#ffffff'),
            'gradientTo' => self::sanitizeColor($attributes['gradientTo'] ?? '#f0f0f0', '#f0f0f0'),
            'gradientDirection' => self::sanitizeText($attributes['gradientDirection'] ?? 'to right'),
            'boxShadowColor' => self::sanitizeText($attributes['boxShadowColor'] ?? 'rgba(0, 0, 0, 0.2)'),
            'boxShadowH' => self::sanitizeInt($attributes['boxShadowH'] ?? 0),
            'boxShadowV' => self::sanitizeInt($attributes['boxShadowV'] ?? 0),
            'boxShadowBlur' => self::sanitizeInt($attributes['boxShadowBlur'] ?? 0),
            'boxShadowSpread' => self::sanitizeInt($attributes['boxShadowSpread'] ?? 0),
            'boxShadowInset' => self::sanitizeBool($attributes['boxShadowInset'] ?? false),
            'paddingTop' => self::sanitizeInt($attributes['paddingTop'] ?? 0),
            'paddingRight' => self::sanitizeInt($attributes['paddingRight'] ?? 0),
            'paddingBottom' => self::sanitizeInt($attributes['paddingBottom'] ?? 0),
            'paddingLeft' => self::sanitizeInt($attributes['paddingLeft'] ?? 0),
            'paddingTopTablet' => isset($attributes['paddingTopTablet']) ? self::sanitizeInt($attributes['paddingTopTablet']) : null,
            'paddingRightTablet' => isset($attributes['paddingRightTablet']) ? self::sanitizeInt($attributes['paddingRightTablet']) : null,
            'paddingBottomTablet' => isset($attributes['paddingBottomTablet']) ? self::sanitizeInt($attributes['paddingBottomTablet']) : null,
            'paddingLeftTablet' => isset($attributes['paddingLeftTablet']) ? self::sanitizeInt($attributes['paddingLeftTablet']) : null,
            'paddingTopMobile' => isset($attributes['paddingTopMobile']) ? self::sanitizeInt($attributes['paddingTopMobile']) : null,
            'paddingRightMobile' => isset($attributes['paddingRightMobile']) ? self::sanitizeInt($attributes['paddingRightMobile']) : null,
            'paddingBottomMobile' => isset($attributes['paddingBottomMobile']) ? self::sanitizeInt($attributes['paddingBottomMobile']) : null,
            'paddingLeftMobile' => isset($attributes['paddingLeftMobile']) ? self::sanitizeInt($attributes['paddingLeftMobile']) : null,
            'paddingUnit' => self::sanitizeFromAllowed($attributes['paddingUnit'] ?? 'px', ['px', 'em', 'rem'], 'px'),
        ];
    }

    /**
     * Sanitize SVG content to prevent XSS attacks.
     *
     * @param string $content Raw SVG content.
     * @return string Sanitized SVG content.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function sanitizeSvgContent(string $content): string
    {
        // Basic validation - ensure it looks like SVG
        $content = trim($content);
        if (empty($content) || !preg_match('/^<svg[^>]*>.*<\/svg>$/s', $content)) {
            return '';
        }

        // Remove potentially dangerous attributes and elements
        $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
        $content = preg_replace('/<iframe[^>]*>.*?<\/iframe>/is', '', $content);
        $content = preg_replace('/<object[^>]*>.*?<\/object>/is', '', $content);
        $content = preg_replace('/<embed[^>]*>.*?<\/embed>/is', '', $content);

        // Remove dangerous attributes
        $dangerous_attrs = [
            'on\w+',           // JavaScript event handlers
            'javascript:',     // JavaScript URLs
            'vbscript:',       // VBScript URLs
            'data:',           // Data URLs (can contain scripts)
            'href\s*=\s*["\'][^"\']*javascript:', // JavaScript in href
        ];

        foreach ($dangerous_attrs as $pattern) {
            $content = preg_replace('/\s+' . $pattern . '[^"\']*["\']?/i', '', $content);
        }

        return $content;
    }

    /**
     * Build complete icon HTML structure.
     *
     * Generates the full HTML markup for the icon including
     * wrapper, styling, and SVG content.
     *
     * @param array $attributes Sanitized block attributes.
     * @return string Complete HTML markup.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function buildIconHtml(array $attributes): string
    {
        // Generate unique selector for responsive CSS
        static $block_instance = 0;
        $block_instance++;
        $unique_id = '.notifal-icon-block-' . $block_instance;

        // Generate responsive CSS
        $responsive_css = IconStyleBuilder::buildResponsiveCss($unique_id, $attributes);

        // Generate wrapper styles
        $wrapper_styles = IconStyleBuilder::buildWrapperStyles($attributes);

        // Generate icon styles
        $icon_styles = IconStyleBuilder::buildIconStyles($attributes);

        // Generate SVG content
        $svg_content = self::generateSvgContent($attributes);

        // Build complete HTML
        ob_start();
        ?>
        <?php if (!empty($responsive_css)): ?>
            <style><?php 
                // Output CSS directly - it's already sanitized by IconStyleBuilder
                // DO NOT use wp_strip_all_tags() as it removes the <style> tag itself!
                // The CSS content should not contain any HTML tags, only CSS rules
                echo $responsive_css; 
            ?></style>
        <?php endif; ?>
        <div class="notifal-icon-block notifal-icon-block-<?php echo esc_attr($block_instance); ?>">
            <div class="notifal-icon-wrapper" style="<?php echo esc_attr($wrapper_styles); ?>">
                <div class="notifal-icon" style="<?php echo esc_attr($icon_styles); ?>">
                    <?php echo $svg_content; ?>
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Generate SVG content based on provided SVG code.
     *
     * @param array $attributes Sanitized attributes.
     * @return string SVG HTML content.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function generateSvgContent(array $attributes): string
    {
        // Check if SVG content is provided
        if (empty($attributes['svgContent'])) {
            // Return a placeholder or empty content
            return '';
        }

        $svg_content = $attributes['svgContent'];

        // Add accessibility attributes and ensure proper structure
        // Remove existing width/height attributes and add our own
        $svg_content = preg_replace('/\s+width\s*=\s*["\'][^"\']*["\']/', '', $svg_content);
        $svg_content = preg_replace('/\s+height\s*=\s*["\'][^"\']*["\']/', '', $svg_content);

        // Add our styling attributes
        $size = $attributes['size'] . $attributes['sizeUnit'];
        $svg_content = preg_replace(
            '/^(<svg[^>]*)(>)/',
            '$1 style="width: ' . esc_attr($size) . '; height: ' . esc_attr($size) . '; fill: currentColor;" aria-hidden="true"$2',
            $svg_content
        );

        return $svg_content;
    }
}