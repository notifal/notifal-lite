<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services;

defined('ABSPATH') || exit;

/**
 * Class AdvancedBlockStyleInjector
 *
 * Injects hover states and custom CSS for blocks with advanced settings.
 * Processes block content to extract advanced attributes and generate dynamic styles.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class AdvancedBlockStyleInjector
{
    /**
     * Collected styles for all blocks on the page.
     *
     * @var array
     * @since 2.0.0
     */
    private static array $collected_styles = [];

    /**
     * Counter for generating unique block IDs.
     *
     * @var int
     * @since 2.0.0
     */
    private static int $block_counter = 0;

    /**
     * Register hooks for style injection.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function register(): void
    {
        // Process blocks to inject advanced styles
        add_filter('render_block', [self::class, 'processBlock'], 10, 2);

        // Output collected styles in footer
        add_action('wp_footer', [self::class, 'outputCollectedStyles'], 999);
    }

    /**
     * Process each block to inject advanced styling attributes.
     *
     * @param string $block_content Block HTML content
     * @param array $block Block data including attributes
     * @return string Modified block content
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function processBlock(string $block_content, array $block): string
    {
        // Skip if no advanced attributes present
        if (!self::hasAdvancedAttributes($block)) {
            return $block_content;
        }

        // Use existing block ID or generate unique one
        $attributes = $block['attrs'] ?? [];
        $block_id = $attributes['notifalBlockId'] ?? self::generateBlockId();
        $block_name = $block['blockName'] ?? '';

        // Get wrapper attributes (pass block name so core/column uses flex-basis for width)
        $wrapper_attrs = AdvancedBlockStyleBuilder::buildWrapperAttributes($block_id, $attributes, $block_name);

        // Collect hover styles
        $hover_styles = AdvancedBlockStyleBuilder::buildHoverStyles($block_id, $attributes);
        if ($hover_styles) {
            self::$collected_styles[] = $hover_styles;
        }

        // Collect responsive visibility styles
        $responsive_styles = AdvancedBlockStyleBuilder::buildResponsiveStyles($block_id, $attributes);
        if ($responsive_styles) {
            self::$collected_styles[] = $responsive_styles;
        }

        // Collect responsive property styles (padding, margin, width/flex-basis, etc.)
        $responsive_property_styles = AdvancedBlockStyleBuilder::buildResponsivePropertyStyles($block_id, $attributes, $block_name);
        if ($responsive_property_styles) {
            self::$collected_styles[] = $responsive_property_styles;
        }

        // Collect responsive flexbox styles
        $responsive_flexbox_styles = AdvancedBlockStyleBuilder::buildResponsiveFlexboxStyles($block_id, $attributes);
        if ($responsive_flexbox_styles) {
            self::$collected_styles[] = $responsive_flexbox_styles;
        }

        // Collect custom CSS
        $custom_css = AdvancedBlockStyleBuilder::buildCustomCss($block_id, $attributes);
        if ($custom_css) {
            self::$collected_styles[] = $custom_css;
        }

        // Inject wrapper attributes into block content
        $block_content = self::injectWrapperAttributes($block_content, $wrapper_attrs);

        return $block_content;
    }

    /**
     * Check if block has any advanced attributes.
     *
     * @param array $block Block data
     * @return bool True if block has advanced attributes
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function hasAdvancedAttributes(array $block): bool
    {
        $attrs = $block['attrs'] ?? [];

        // Check if any notifal advanced attribute exists
        foreach ($attrs as $key => $value) {
            if (strpos($key, 'notifal') === 0) {
                // Check if value is not default/empty
                if ($value !== 0 && $value !== '' && $value !== 'default' && $value !== 'none') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Generate unique block ID.
     *
     * @return string Unique block identifier
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function generateBlockId(): string
    {
        self::$block_counter++;
        return sprintf('notifal-block-%d', self::$block_counter);
    }

    /**
     * Inject wrapper attributes into block HTML content.
     *
     * @param string $content Block HTML content
     * @param array $attributes Attributes to inject
     * @return string Modified HTML content
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function injectWrapperAttributes(string $content, array $attributes): string
    {
        if (empty($attributes) || empty($content)) {
            return $content;
        }

        // Find the first HTML tag in the content, but skip <style>, <script>, and <meta> tags
        // These are not block wrappers and shouldn't receive wrapper attributes
        $offset = 0;
        while (preg_match('/<([a-z][a-z0-9]*)\b([^>]*)>/i', $content, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $tag_name = $matches[1][0];
            
            // Skip non-wrapper tags
            if (in_array(strtolower($tag_name), ['style', 'script', 'meta', 'link'])) {
                $offset = $matches[0][1] + strlen($matches[0][0]);
                continue;
            }
            
            // Found a valid wrapper tag
            $existing_attrs = $matches[2][0];
            $tag_position = $matches[0][1];
            $tag_length = strlen($matches[0][0]);
            break;
        }
        
        // If no valid wrapper tag found, return original content
        if (!isset($tag_position)) {
            return $content;
        }

        // Build new attributes string
        $new_attrs_parts = [];
        foreach ($attributes as $key => $value) {
            // Skip if attribute already exists in content
            if (strpos($existing_attrs, $key . '=') !== false) {
                // Merge with existing attribute if it's style or class
                if ($key === 'style') {
                    // Extract existing style and merge
                    if (preg_match('/style=["\']([^"\']*)["\']/', $existing_attrs, $style_matches)) {
                        $existing_style = $style_matches[1];
                        $value = $existing_style . '; ' . $value;
                        // Remove old style attribute
                        $existing_attrs = preg_replace('/style=["\'][^"\']*["\']/', '', $existing_attrs);
                    }
                } elseif ($key === 'class') {
                    // Extract existing classes and merge
                    if (preg_match('/class=["\']([^"\']*)["\']/', $existing_attrs, $class_matches)) {
                        $existing_classes = $class_matches[1];
                        $value = trim($existing_classes . ' ' . $value);
                        // Remove old class attribute
                        $existing_attrs = preg_replace('/class=["\'][^"\']*["\']/', '', $existing_attrs);
                    }
                } else {
                    // Skip other duplicate attributes
                    continue;
                }
            }

            $new_attrs_parts[] = sprintf('%s="%s"', esc_attr($key), esc_attr($value));
        }

        // Parse and escape existing attributes to prevent XSS (never output raw block HTML).
        $safe_existing_attrs = self::parseAndEscapeAttributes($existing_attrs);

        // Rebuild the opening tag with escaped existing attributes and new wrapper attributes
        $new_tag = sprintf(
            '<%1$s %2$s %3$s>',
            esc_attr($tag_name),
            $safe_existing_attrs,
            implode(' ', $new_attrs_parts)
        );

        // Replace the original tag
        $content = substr_replace($content, $new_tag, $tag_position, $tag_length);

        return $content;
    }

    /**
     * Parse an HTML attribute string and rebuild with escaped values for safe output.
     *
     * Only allows safe attribute names (id, class, style, dir, role, aria-*, data-*)
     * to prevent XSS when injecting into block content.
     *
     * @param string $attr_string Raw attribute string from block HTML (e.g. class="foo" id="bar").
     * @return string Rebuilt attribute string with each value escaped via esc_attr().
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function parseAndEscapeAttributes(string $attr_string): string
    {
        $attr_string = trim($attr_string);
        if ($attr_string === '') {
            return '';
        }

        $allowed_prefixes = ['aria-', 'data-'];
        $allowed_names = ['id', 'class', 'style', 'dir', 'role'];

        $parts = [];
        if (preg_match_all('/\s*([a-z][a-z0-9_-]*)\s*=\s*["\']([^"\']*)["\']/i', $attr_string, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $name = strtolower($m[1]);
                $is_allowed = in_array($name, $allowed_names, true);
                foreach ($allowed_prefixes as $prefix) {
                    if (strpos($name, $prefix) === 0) {
                        $is_allowed = true;
                        break;
                    }
                }
                if ($is_allowed) {
                    $parts[] = sprintf('%s="%s"', esc_attr($name), esc_attr($m[2]));
                }
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Output collected styles in the footer.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function outputCollectedStyles(): void
    {
        if (empty(self::$collected_styles)) {
            return;
        }

        // Output CSS in a style tag
        // We don't escape CSS content as it should not contain user-generated content
        // All CSS is generated server-side from validated block attributes
        ?>
        <style id="notifal-advanced-block-styles">
        /* Notifal Advanced Block Styles */
        <?php
        foreach (self::$collected_styles as $style) {
            // Output CSS directly - it's generated from block attributes, not user input
            echo $style . "\n";
        }
        ?>
        </style>
        <?php
    }
}
