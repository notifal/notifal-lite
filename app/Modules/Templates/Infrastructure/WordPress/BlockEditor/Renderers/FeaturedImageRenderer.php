<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Renderers;

use Notifal\Core\Foundation\Container;
use Notifal\Domain\Products\ProductFetcherInterface;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\FeaturedImageStyleBuilder;
use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Traits\BlockRendererTrait;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\WidgetContextProvider;
use Notifal\Modules\Templates\Application\Services\FeaturedImageResolver;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class FeaturedImageRenderer
 *
 * Handles server-side rendering of Featured Image Gutenberg blocks.
 * Provides context-aware image rendering that adapts to notification content.
 * Follows Notifal separation of concerns principle by delegating styling to StyleBuilder.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FeaturedImageRenderer {
    use BlockRendererTrait;

    /**
     * Render Featured Image block with context-aware image data.
     *
     * Processes block attributes and generates HTML output with proper sanitization.
     * Uses FeaturedImageResolver to determine appropriate image based on notification context.
     * Delegates styling logic to FeaturedImageStyleBuilder for maintainability.
     *
     * @param array $attributes Block attributes from Gutenberg editor.
     * @param string $content Block content (usually empty for dynamic blocks).
     * @param mixed $block Block instance.
     * @return string Rendered HTML output.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function render(array $attributes = [], string $content = '', $block = null): string {
        self::fireBeforeRenderHook('featured_image', $attributes, $content, $block);

        // Generate unique selector for responsive CSS
        static $block_instance = 0;
        $block_instance++;
        $unique_id = '.notifal-featured-image-block-' . $block_instance;

        // Extract and sanitize attributes
        $image_attrs = self::extractImageAttributes($attributes);
        $style_attrs = self::extractStyleAttributes($attributes);

        // Merge attributes for responsive CSS generation
        $all_attributes = array_merge($attributes, $image_attrs, $style_attrs);

        // Generate responsive CSS
        $responsive_css = FeaturedImageStyleBuilder::buildResponsiveCss($unique_id, $all_attributes);

        // Fetch context for featured image display
        $context = self::fetchImageContext();

        // Generate CSS classes and styles using StyleBuilder service
        $container_classes = FeaturedImageStyleBuilder::buildContainerClasses($style_attrs['force_transparent']);
        $image_classes = FeaturedImageStyleBuilder::buildImageClasses();
        $container_style = FeaturedImageStyleBuilder::buildContainerStyle($image_attrs['alignment']);
        $image_styles = FeaturedImageStyleBuilder::buildImageStyles($image_attrs, $style_attrs);

        // Render HTML output
        $html = self::renderHtml(
            $context,
            $container_classes,
            $image_classes,
            $container_style,
            $image_styles,
            $image_attrs,
            $responsive_css,
            $block_instance
        );

        self::fireAfterRenderHook('featured_image', $html, $attributes, $content, $block);

        return $html;
    }

    /**
     * Extract and sanitize basic image attributes from block data.
     *
     * Validates and sanitizes all image-related attributes including dimensions,
     * loading behavior, resolution, and alignment settings.
     *
     * @param array $attributes Raw block attributes.
     * @return array Sanitized image attributes.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function extractImageAttributes(array $attributes): array {
        return [
            'lazy_load' => self::sanitizeBool($attributes['lazyLoad'] ?? true),
            'resolution' => self::sanitizeText($attributes['imageResolution'] ?? 'large'),
            'alignment' => self::sanitizeText($attributes['alignment'] ?? 'center'),
            'width' => self::sanitizeInt($attributes['width'] ?? 0),
            'widthTablet' => isset($attributes['widthTablet']) ? self::sanitizeInt($attributes['widthTablet']) : null,
            'widthMobile' => isset($attributes['widthMobile']) ? self::sanitizeInt($attributes['widthMobile']) : null,
            'width_unit' => self::sanitizeText($attributes['widthUnit'] ?? 'px'),
            'height' => self::sanitizeInt($attributes['height'] ?? 0),
            'heightTablet' => isset($attributes['heightTablet']) ? self::sanitizeInt($attributes['heightTablet']) : null,
            'heightMobile' => isset($attributes['heightMobile']) ? self::sanitizeInt($attributes['heightMobile']) : null,
            'height_unit' => self::sanitizeText($attributes['heightUnit'] ?? 'px'),
            'preview_image_source' => self::sanitizeText($attributes['previewImageSource'] ?? 'auto'),
        ];
    }

    /**
     * Extract and sanitize styling attributes from block data.
     *
     * Processes all styling-related attributes including borders, shadows, filters,
     * and advanced display options with proper sanitization.
     *
     * @param array $attributes Raw block attributes.
     * @return array Sanitized styling attributes.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function extractStyleAttributes(array $attributes): array {
        return [
            // Border attributes
            'border_style' => self::sanitizeText($attributes['borderStyle'] ?? 'none'),
            'border_top' => self::sanitizeInt($attributes['borderTop'] ?? 0),
            'border_right' => self::sanitizeInt($attributes['borderRight'] ?? 0),
            'border_bottom' => self::sanitizeInt($attributes['borderBottom'] ?? 0),
            'border_left' => self::sanitizeInt($attributes['borderLeft'] ?? 0),
            'border_color' => self::sanitizeColor($attributes['borderColor'] ?? ''),

            // Border radius attributes
            'radius_tl' => self::sanitizeInt($attributes['radiusTopLeft'] ?? 0),
            'radius_tr' => self::sanitizeInt($attributes['radiusTopRight'] ?? 0),
            'radius_br' => self::sanitizeInt($attributes['radiusBottomRight'] ?? 0),
            'radius_bl' => self::sanitizeInt($attributes['radiusBottomLeft'] ?? 0),
            'radiusTopLeftTablet' => isset($attributes['radiusTopLeftTablet']) ? self::sanitizeInt($attributes['radiusTopLeftTablet']) : null,
            'radiusTopRightTablet' => isset($attributes['radiusTopRightTablet']) ? self::sanitizeInt($attributes['radiusTopRightTablet']) : null,
            'radiusBottomRightTablet' => isset($attributes['radiusBottomRightTablet']) ? self::sanitizeInt($attributes['radiusBottomRightTablet']) : null,
            'radiusBottomLeftTablet' => isset($attributes['radiusBottomLeftTablet']) ? self::sanitizeInt($attributes['radiusBottomLeftTablet']) : null,
            'radiusTopLeftMobile' => isset($attributes['radiusTopLeftMobile']) ? self::sanitizeInt($attributes['radiusTopLeftMobile']) : null,
            'radiusTopRightMobile' => isset($attributes['radiusTopRightMobile']) ? self::sanitizeInt($attributes['radiusTopRightMobile']) : null,
            'radiusBottomRightMobile' => isset($attributes['radiusBottomRightMobile']) ? self::sanitizeInt($attributes['radiusBottomRightMobile']) : null,
            'radiusBottomLeftMobile' => isset($attributes['radiusBottomLeftMobile']) ? self::sanitizeInt($attributes['radiusBottomLeftMobile']) : null,

            // Box shadow attributes
            'shadow_color' => self::sanitizeText($attributes['boxShadowColor'] ?? 'rgba(0, 0, 0, 0.2)'),
            'shadow_h' => self::sanitizeInt($attributes['boxShadowH'] ?? 0),
            'shadow_v' => self::sanitizeInt($attributes['boxShadowV'] ?? 0),
            'shadow_blur' => self::sanitizeInt($attributes['boxShadowBlur'] ?? 0),
            'shadow_spread' => self::sanitizeInt($attributes['boxShadowSpread'] ?? 0),
            'shadow_inset' => self::sanitizeBool($attributes['boxShadowInset'] ?? false),

            // Advanced attributes
            'force_transparent' => self::sanitizeBool($attributes['forceTransparent'] ?? false),
            'object_fit' => self::sanitizeText($attributes['objectFit'] ?? 'cover'),

            // CSS Filter attributes
            'filter_brightness' => self::sanitizeInt($attributes['cssFilterBrightness'] ?? 100, 100, 0),
            'filter_contrast' => self::sanitizeInt($attributes['cssFilterContrast'] ?? 100, 100, 0),
            'filter_saturation' => self::sanitizeInt($attributes['cssFilterSaturation'] ?? 100, 100, 0),
            'filter_blur' => self::sanitizeInt($attributes['cssFilterBlur'] ?? 0, 0, 0),
            'filter_hue' => self::sanitizeInt($attributes['cssFilterHue'] ?? 0, 0, 0),
        ];
    }

    /**
     * Fetch context for featured image display.
     *
     * Uses WidgetContextProvider to get context from notification rendering when available,
     * otherwise falls back to random product for preview mode.
     *
     * @return array|null Context array or null if none found.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function fetchImageContext(): ?array {
        try {
            // Check if we have context from notification rendering
            if (WidgetContextProvider::isActive()) {
                return WidgetContextProvider::getContext();
            }

            // Use preview data resolver for better preview experience
            $previewDataResolver = notifal_app(\Notifal\Modules\Templates\Application\Services\PreviewDataResolver::class);
            $previewData = $previewDataResolver->resolve();
            
            // Create context with available sources (WooCommerce-aware)
            $context = [
                'post' => self::getSamplePost(),
                'page' => self::getSamplePage(),
                'comment' => self::getSampleComment(),
            ];
            
            // Only add product data if WooCommerce is active and we have product data
            if (PluginDetector::isWooCommerceActive() && $previewData && $previewData->getProduct()) {
                $context['product'] = $previewData->getProduct();
            }
                
            // Apply filter for backward compatibility
            $filteredContext = apply_filters('notifal_block_product_data', $context);
            
            return $filteredContext;
        } catch (\Exception $e) {
            // If preview data resolver fails, continue with fallback logic
        }

        // Final fallback - try WooCommerce only if available
        if (PluginDetector::isWooCommerceActive()) {
            try {
                $product_fetcher = notifal_app(ProductFetcherInterface::class);
                $product = $product_fetcher->getRandom();

                if ($product) {
                    // Apply filter for backward compatibility  
                    $filteredProduct = apply_filters('notifal_block_product_data', $product);
                    return ['product' => $filteredProduct];
                }
            } catch (\Exception $e) {
                // Continue to WordPress-only fallback
            }
        }
        
        // WordPress-only fallback - posts and pages
        return [
            'post' => self::getSamplePost(),
            'page' => self::getSamplePage(),
            'comment' => self::getSampleComment(),
        ];
    }

    /**
     * Render final HTML output for Featured Image block.
     *
     * Generates clean, semantic HTML with proper escaping and attributes.
     * Uses FeaturedImageResolver for context-aware image rendering.
     *
     * @param array|null $context Context array from WidgetContextProvider.
     * @param array $container_classes Container CSS classes.
     * @param array $image_classes Image CSS classes.
     * @param string $container_style Container inline styles.
     * @param string $image_styles Image inline styles.
     * @param array $image_attrs Image attributes.
     * @param string $responsive_css Responsive CSS with media queries.
     * @param int $block_instance Block instance number.
     * @return string Rendered HTML output.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function renderHtml(
        ?array $context,
        array $container_classes,
        array $image_classes,
        string $container_style,
        string $image_styles,
        array $image_attrs,
        string $responsive_css,
        int $block_instance
    ): string {
        // Start output buffering for clean HTML generation
        ob_start();
        ?>
        <?php if (!empty($responsive_css)): ?>
            <style><?php 
                // Output CSS directly - it's already sanitized by FeaturedImageStyleBuilder
                // DO NOT use wp_strip_all_tags() as it removes the <style> tag itself!
                echo $responsive_css; 
            ?></style>
        <?php endif; ?>
        <div class="<?php echo esc_attr(implode(' ', $container_classes)); ?> notifal-featured-image-block-<?php echo esc_attr($block_instance); ?>" style="<?php echo esc_attr($container_style); ?>">
            <div class="notifal-pulse-img notifal-featured-image">
                <?php
                echo FeaturedImageResolver::getFeaturedImageHtml(
                    $context,
                    $image_attrs['resolution'],
                    [
                        'loading' => $image_attrs['lazy_load'] ? 'lazy' : 'eager',
                        'class' => esc_attr(implode(' ', $image_classes)),
                        'style' => !empty($image_styles) ? esc_attr($image_styles) : '',
                    ],
                    $image_attrs['preview_image_source']
                );
                ?>
            </div>
        </div>
        <?php

        // Return clean HTML output
        return ob_get_clean();
    }

    /**
     * Get sample post for preview context.
     *
     * @return \WP_Post|null Sample post or null if none found.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function getSamplePost(): ?\WP_Post
    {
        $posts = get_posts([
            'post_type' => 'post',
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'orderby' => 'rand'
        ]);
        
        return !empty($posts) ? $posts[0] : null;
    }

    /**
     * Get sample page for preview context.
     *
     * @return \WP_Post|null Sample page or null if none found.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function getSamplePage(): ?\WP_Post
    {
        $pages = get_posts([
            'post_type' => 'page',
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'orderby' => 'rand'
        ]);
        
        return !empty($pages) ? $pages[0] : null;
    }

    /**
     * Get sample comment for preview context.
     *
     * @return \WP_Comment|null Sample comment or null if none found.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function getSampleComment(): ?\WP_Comment
    {
        $comments = get_comments([
            'number' => 1,
            'status' => 'approve',
            'orderby' => 'rand'
        ]);
        
        return !empty($comments) ? $comments[0] : null;
    }
}
