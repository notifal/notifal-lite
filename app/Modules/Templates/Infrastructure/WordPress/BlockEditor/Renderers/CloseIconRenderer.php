<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Renderers;

use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Traits\BlockRendererTrait;
use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\CloseIconStyleBuilder;

defined('ABSPATH') || exit;

/**
 * Class CloseIconRenderer
 *
 * Server-side renderer for the Close Icon Gutenberg block.
 * Handles dynamic content generation and styling.
 * Follows Notifal Laravel-like architecture with proper separation of concerns.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Renderers
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class CloseIconRenderer
{
    use BlockRendererTrait;
    /**
     * Render Close Icon block with server-side processing.
     *
     * Generates dynamic close icon HTML with styling support.
     * Handles icon selection, styling, and accessibility features.
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
        self::fireBeforeRenderHook('close_icon', $attributes, $content, $block);

        // Sanitize and set default attributes
        $attributes = self::sanitizeAttributes($attributes);

        // Build icon HTML
        $html = self::buildIconHtml($attributes);

        self::fireAfterRenderHook('close_icon', $html, $attributes, $content, $block);

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
            'selectedIcon' => $attributes['selectedIcon'] ?? ['value' => 'eicon-close', 'library' => 'eicons'],
            'alignment' => self::sanitizeFromAllowed($attributes['alignment'] ?? 'flex-end', ['flex-start', 'center', 'flex-end'], 'flex-end'),
            'primaryColor' => self::sanitizeColor($attributes['primaryColor'] ?? '#000000', '#000000'),
            'size' => self::sanitizeInt($attributes['size'] ?? 30, 30, 8),
            'sizeTablet' => isset($attributes['sizeTablet']) ? self::sanitizeInt($attributes['sizeTablet'], 30, 8) : null,
            'sizeMobile' => isset($attributes['sizeMobile']) ? self::sanitizeInt($attributes['sizeMobile'], 30, 8) : null,
            'sizeUnit' => self::sanitizeText($attributes['sizeUnit'] ?? 'px'),
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
            'paddingUnit' => self::sanitizeText($attributes['paddingUnit'] ?? 'px'),
            'iconUrl' => self::sanitizeUrl($attributes['iconUrl'] ?? ''),
            'iconId' => self::sanitizeInt($attributes['iconId'] ?? 0),
            'iconType' => self::sanitizeText($attributes['iconType'] ?? 'image'),
        ];
    }

    /**
     * Build complete icon HTML structure.
     *
     * Generates the full HTML markup for the close icon including
     * wrapper, styling, and accessibility attributes.
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
        $unique_id = '.notifal-close-icon-block-' . $block_instance;

        // Generate responsive CSS
        $responsive_css = CloseIconStyleBuilder::buildResponsiveCss($unique_id, $attributes);

        // Generate wrapper styles
        $wrapper_styles = CloseIconStyleBuilder::buildWrapperStyles($attributes);

        // Generate icon styles
        $icon_styles = CloseIconStyleBuilder::buildIconStyles($attributes);

        // Generate icon content
        $icon_content = self::generateIconContent($attributes);
        
        // Build complete HTML
        ob_start();
        ?>
        <?php if (!empty($responsive_css)): ?>
            <style><?php 
                // Output CSS directly - it's already sanitized by CloseIconStyleBuilder
                // DO NOT use wp_strip_all_tags() as it removes the <style> tag itself!
                echo $responsive_css; 
            ?></style>
        <?php endif; ?>
        <div class="notifal-close-icon-block notifal-close-icon-block-<?php echo esc_attr($block_instance); ?>">
            <div class="notifal-close-icon-wrapper" style="<?php echo esc_attr($wrapper_styles); ?>">
                <?php if (!empty($attributes['iconUrl']) && $attributes['iconType'] === 'svg' &&
                          !empty($attributes['primaryColor']) &&
                          $attributes['primaryColor'] !== '#ffffff' &&
                          $attributes['primaryColor'] !== '#000000'): ?>
                    <span
                        class="notifal-close notifal-close-mask"
                        role="button"
                        aria-label="<?php echo esc_attr__('Close Notification', 'notifal'); ?>"
                        style="<?php echo esc_attr($icon_styles); ?>"
                        data-primary-color="<?php echo esc_attr($attributes['primaryColor']); ?>"
                        data-icon-url="<?php echo esc_attr($attributes['iconUrl']); ?>"
                        data-icon-size="<?php echo esc_attr($attributes['size'] . $attributes['sizeUnit']); ?>"
                    ></span>
                <?php else: ?>
                    <span
                        class="notifal-close"
                        role="button"
                        aria-label="<?php echo esc_attr__('Close Notification', 'notifal'); ?>"
                        style="<?php echo esc_attr($icon_styles); ?>"
                        data-primary-color="<?php echo esc_attr($attributes['primaryColor']); ?>"
                    >
                        <?php echo $icon_content; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }



    /**
     * Generate icon content based on selected icon.
     *
     * @param array $attributes Sanitized attributes.
     * @return string Icon HTML content.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function generateIconContent(array $attributes): string
    {
        // Check if custom icon is uploaded
        if (!empty($attributes['iconUrl'])) {
            $icon_url = esc_url($attributes['iconUrl']);
            $icon_styles = CloseIconStyleBuilder::buildImageStyles($attributes);

            return sprintf(
                '<img src="%s" alt="%s" style="%s;" />',
                $icon_url,
                esc_attr__('Close Icon', 'notifal'),
                esc_attr($icon_styles)
            );
        }

        // Default SVG close icon
        $size = $attributes['size'];
        $size_unit = $attributes['sizeUnit'];
        $primary_color = $attributes['primaryColor'];

        return sprintf(
            '<svg width="%d" height="%d" viewBox="0 0 24 24" fill="currentColor" style="width: %d%s; height: %d%s; color: %s;">
                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
            </svg>',
            $size,
            $size,
            $size,
            esc_attr($size_unit),
            $size,
            esc_attr($size_unit),
            esc_attr($primary_color)
        );
    }
} 
