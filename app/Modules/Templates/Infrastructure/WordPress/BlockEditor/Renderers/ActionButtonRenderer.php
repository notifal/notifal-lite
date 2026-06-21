<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Renderers;

use Notifal\Modules\OnPageNotification\Application\Services\Utility\WidgetContextProvider;
use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\ActionButtonStyleBuilder;
use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Traits\BlockRendererTrait;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class ActionButtonRenderer
 *
 * Server-side renderer for the Action Button Gutenberg block.
 * Handles dynamic content generation and analytics tracking integration.
 * Follows Notifal Laravel-like architecture with proper separation of concerns.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Renderers
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ActionButtonRenderer
{
    use BlockRendererTrait;
    /**
     * Render Action Button block with server-side processing.
     *
     * Generates dynamic button HTML with analytics tracking support.
     * Handles link types, styling, and accessibility features.
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
        self::fireBeforeRenderHook('action_button', $attributes, $content, $block);

        // Sanitize and set default attributes
        $attributes = self::sanitizeAttributes($attributes);

        // Generate unique button ID for analytics tracking
        $tracking_id = ! empty($attributes['trackingId'])
            ? sanitize_text_field((string) $attributes['trackingId'])
            : self::generateButtonId();

        // Build button HTML
        $html = self::buildButtonHtml($attributes, $tracking_id);

        self::fireAfterRenderHook('action_button', $html, $attributes, $content, $block);

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
            'buttonText' => self::sanitizeText($attributes['buttonText'] ?? __('Buy now', 'notifal')),
            'trackingId' => self::sanitizeText($attributes['trackingId'] ?? ''),
            'linkType' => self::sanitizeFromAllowed($attributes['linkType'] ?? 'product', ['product', 'copy', 'custom', 'close', 'custom-trigger', 'ajax-add-to-cart'], 'product'),
            'copyText' => self::sanitizeText($attributes['copyText'] ?? ''),
            'customUrl' => self::sanitizeUrl($attributes['customUrl'] ?? ''),
            'customUrlTarget' => self::sanitizeBool($attributes['customUrlTarget'] ?? false),
            'customUrlNofollow' => self::sanitizeBool($attributes['customUrlNofollow'] ?? false),
            'loadingText' => array_key_exists('loadingText', $attributes)
                ? (trim((string) $attributes['loadingText']) === '' ? '' : self::sanitizeText($attributes['loadingText']))
                : null,
            'addToCartQuantity' => isset($attributes['addToCartQuantity']) ? max(1, min(99, self::sanitizeInt($attributes['addToCartQuantity'], 1))) : 1,
            'addToCartRedirect' => self::sanitizeFromAllowed($attributes['addToCartRedirect'] ?? 'none', ['none', 'cart', 'checkout'], 'none'),
            'addToCartSuccessText' => self::sanitizeText($attributes['addToCartSuccessText'] ?? ''),
            'alignment' => self::sanitizeFromAllowed($attributes['alignment'] ?? 'center', ['left', 'center', 'right'], 'center'),
            'fontSize' => self::sanitizeInt($attributes['fontSize'] ?? 16, 16, 8),
            'fontSizeTablet' => isset($attributes['fontSizeTablet']) ? self::sanitizeInt($attributes['fontSizeTablet']) : null,
            'fontSizeMobile' => isset($attributes['fontSizeMobile']) ? self::sanitizeInt($attributes['fontSizeMobile']) : null,
            'fontWeight' => self::sanitizeFromAllowed($attributes['fontWeight'] ?? '400', ['300', '400', '500', '600', '700', '800'], '400'),
            'textTransform' => self::sanitizeFromAllowed($attributes['textTransform'] ?? 'none', ['none', 'uppercase', 'lowercase', 'capitalize'], 'none'),
            'letterSpacing' => self::sanitizeFloat($attributes['letterSpacing'] ?? 0),
            'backgroundColor' => self::sanitizeColor($attributes['backgroundColor'] ?? '#007cba', '#007cba'),
            'textColor' => self::sanitizeColor($attributes['textColor'] ?? '#ffffff', '#ffffff'),
            'backgroundType' => self::sanitizeFromAllowed($attributes['backgroundType'] ?? 'simple', ['simple', 'gradient'], 'simple'),
            'gradientFrom' => self::sanitizeColor($attributes['gradientFrom'] ?? '#007cba', '#007cba'),
            'gradientTo' => self::sanitizeColor($attributes['gradientTo'] ?? '#005a87', '#005a87'),
            'gradientDirection' => self::sanitizeText($attributes['gradientDirection'] ?? 'to right'),
            'borderStyle' => self::sanitizeFromAllowed($attributes['borderStyle'] ?? 'none', ['none', 'solid', 'dashed', 'dotted', 'double'], 'none'),
            'borderTop' => self::sanitizeInt($attributes['borderTop'] ?? 0),
            'borderRight' => self::sanitizeInt($attributes['borderRight'] ?? 0),
            'borderBottom' => self::sanitizeInt($attributes['borderBottom'] ?? 0),
            'borderLeft' => self::sanitizeInt($attributes['borderLeft'] ?? 0),
            'borderColor' => self::sanitizeColor($attributes['borderColor'] ?? ''),
            'radiusTopLeft' => self::sanitizeInt($attributes['radiusTopLeft'] ?? 4),
            'radiusTopRight' => self::sanitizeInt($attributes['radiusTopRight'] ?? 4),
            'radiusBottomRight' => self::sanitizeInt($attributes['radiusBottomRight'] ?? 4),
            'radiusBottomLeft' => self::sanitizeInt($attributes['radiusBottomLeft'] ?? 4),
            'radiusTopLeftTablet' => isset($attributes['radiusTopLeftTablet']) ? self::sanitizeInt($attributes['radiusTopLeftTablet']) : null,
            'radiusTopRightTablet' => isset($attributes['radiusTopRightTablet']) ? self::sanitizeInt($attributes['radiusTopRightTablet']) : null,
            'radiusBottomRightTablet' => isset($attributes['radiusBottomRightTablet']) ? self::sanitizeInt($attributes['radiusBottomRightTablet']) : null,
            'radiusBottomLeftTablet' => isset($attributes['radiusBottomLeftTablet']) ? self::sanitizeInt($attributes['radiusBottomLeftTablet']) : null,
            'radiusTopLeftMobile' => isset($attributes['radiusTopLeftMobile']) ? self::sanitizeInt($attributes['radiusTopLeftMobile']) : null,
            'radiusTopRightMobile' => isset($attributes['radiusTopRightMobile']) ? self::sanitizeInt($attributes['radiusTopRightMobile']) : null,
            'radiusBottomRightMobile' => isset($attributes['radiusBottomRightMobile']) ? self::sanitizeInt($attributes['radiusBottomRightMobile']) : null,
            'radiusBottomLeftMobile' => isset($attributes['radiusBottomLeftMobile']) ? self::sanitizeInt($attributes['radiusBottomLeftMobile']) : null,
            'paddingTop' => self::sanitizeInt($attributes['paddingTop'] ?? 12),
            'paddingRight' => self::sanitizeInt($attributes['paddingRight'] ?? 24),
            'paddingBottom' => self::sanitizeInt($attributes['paddingBottom'] ?? 12),
            'paddingLeft' => self::sanitizeInt($attributes['paddingLeft'] ?? 24),
            'paddingTopTablet' => isset($attributes['paddingTopTablet']) ? self::sanitizeInt($attributes['paddingTopTablet']) : null,
            'paddingRightTablet' => isset($attributes['paddingRightTablet']) ? self::sanitizeInt($attributes['paddingRightTablet']) : null,
            'paddingBottomTablet' => isset($attributes['paddingBottomTablet']) ? self::sanitizeInt($attributes['paddingBottomTablet']) : null,
            'paddingLeftTablet' => isset($attributes['paddingLeftTablet']) ? self::sanitizeInt($attributes['paddingLeftTablet']) : null,
            'paddingTopMobile' => isset($attributes['paddingTopMobile']) ? self::sanitizeInt($attributes['paddingTopMobile']) : null,
            'paddingRightMobile' => isset($attributes['paddingRightMobile']) ? self::sanitizeInt($attributes['paddingRightMobile']) : null,
            'paddingBottomMobile' => isset($attributes['paddingBottomMobile']) ? self::sanitizeInt($attributes['paddingBottomMobile']) : null,
            'paddingLeftMobile' => isset($attributes['paddingLeftMobile']) ? self::sanitizeInt($attributes['paddingLeftMobile']) : null,
            'boxShadowColor' => self::sanitizeText($attributes['boxShadowColor'] ?? 'rgba(0, 0, 0, 0.2)'),
            'boxShadowH' => self::sanitizeInt($attributes['boxShadowH'] ?? 0),
            'boxShadowV' => self::sanitizeInt($attributes['boxShadowV'] ?? 2),
            'boxShadowBlur' => self::sanitizeInt($attributes['boxShadowBlur'] ?? 4),
            'boxShadowSpread' => self::sanitizeInt($attributes['boxShadowSpread'] ?? 0),
            'boxShadowInset' => self::sanitizeBool($attributes['boxShadowInset'] ?? false),
            'hoverBackgroundColor' => self::sanitizeColor($attributes['hoverBackgroundColor'] ?? '#005a87', '#005a87'),
            'hoverTextColor' => self::sanitizeColor($attributes['hoverTextColor'] ?? '#ffffff', '#ffffff'),
            'iconSpacing' => self::sanitizeInt($attributes['iconSpacing'] ?? 8),
            'iconSpacingTablet' => isset($attributes['iconSpacingTablet']) ? self::sanitizeInt($attributes['iconSpacingTablet']) : null,
            'iconSpacingMobile' => isset($attributes['iconSpacingMobile']) ? self::sanitizeInt($attributes['iconSpacingMobile']) : null,
            'iconUrl' => self::sanitizeUrl($attributes['iconUrl'] ?? ''),
            'iconWidth' => self::sanitizeInt($attributes['iconWidth'] ?? 24),
            'iconHeight' => self::sanitizeInt($attributes['iconHeight'] ?? 24),
            'iconAlt' => self::sanitizeText($attributes['iconAlt'] ?? ''),
            'iconType' => self::sanitizeText($attributes['iconType'] ?? 'image'),
            'iconColor' => self::sanitizeColor($attributes['iconColor'] ?? '#ffffff', '#ffffff'),
            'hideElements' => self::sanitizeText($attributes['hideElements'] ?? ''),
            'showElements' => self::sanitizeText($attributes['showElements'] ?? ''),
            'viewCartTextColor' => self::sanitizeColor($attributes['viewCartTextColor'] ?? '#ffffff', '#ffffff'),
            'viewCartBackgroundColor' => self::sanitizeColor($attributes['viewCartBackgroundColor'] ?? '#007cba', '#007cba'),
            'viewCartFontSize' => self::sanitizeInt($attributes['viewCartFontSize'] ?? 14, 14, 8),
            'viewCartFontWeight' => self::sanitizeFromAllowed($attributes['viewCartFontWeight'] ?? '400', ['300', '400', '500', '600', '700', '800'], '400'),
            'viewCartPaddingTop' => self::sanitizeInt($attributes['viewCartPaddingTop'] ?? 10),
            'viewCartPaddingRight' => self::sanitizeInt($attributes['viewCartPaddingRight'] ?? 20),
            'viewCartPaddingBottom' => self::sanitizeInt($attributes['viewCartPaddingBottom'] ?? 10),
            'viewCartPaddingLeft' => self::sanitizeInt($attributes['viewCartPaddingLeft'] ?? 20),
            'viewCartSpacing' => self::sanitizeInt($attributes['viewCartSpacing'] ?? 10),
            'viewCartBorderRadius' => self::sanitizeInt($attributes['viewCartBorderRadius'] ?? 4),
            'viewCartBorderWidth' => self::sanitizeInt($attributes['viewCartBorderWidth'] ?? 0),
            'viewCartBorderColor' => self::sanitizeColor($attributes['viewCartBorderColor'] ?? 'transparent', 'transparent'),
        ];
    }

    /**
     * Generate unique button ID for analytics tracking.
     *
     * Creates a unique identifier for each button instance.
     * Used by analytics system to track button interactions.
     *
     * @return string Unique button identifier.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function generateButtonId(): string
    {
        return 'notifal-btn-' . uniqid();
    }

    /**
     * Build complete button HTML structure.
     *
     * Generates the full HTML markup for the action button including
     * wrapper, styling, and analytics attributes.
     *
     * @param array $attributes Sanitized block attributes.
     * @param string $button_id Unique button identifier.
     * @return string Complete HTML markup.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function buildButtonHtml(array $attributes, string $button_id): string
    {
        // Generate wrapper styles
        $wrapper_styles = ActionButtonStyleBuilder::buildContainerStyle($attributes['alignment']);

        // Generate button styles
        $button_styles = ActionButtonStyleBuilder::buildButtonStyles($attributes);

        // Generate link attributes
        $link_attributes = self::generateLinkAttributes($attributes, $button_id);

        // Generate icon styles with color filter for SVGs
        $icon_styles = ActionButtonStyleBuilder::buildIconStyles($attributes);
        $icon_wrapper_styles = ActionButtonStyleBuilder::buildIconWrapperStyles($attributes);
        
        // Generate responsive CSS for frontend
        $responsive_css = ActionButtonStyleBuilder::buildResponsiveCss($button_id, $attributes);

        // When Ajax Add to Cart: add View Cart link CSS and unique wrapper class for scoping
        $wrapper_extra_class = '';
        if (($attributes['linkType'] ?? '') === 'ajax-add-to-cart') {
            $wrapper_extra_class = ' notifal-action-button-block-' . esc_attr($button_id);
            $view_cart_css = ActionButtonStyleBuilder::buildViewCartCss('notifal-action-button-block-' . $button_id, $attributes);
            $responsive_css = $responsive_css . "\n" . $view_cart_css;
        }

        // Build complete HTML
        ob_start();
        ?>
        <?php if (!empty($responsive_css)): ?>
            <style><?php
                // Output CSS directly - it's already sanitized by ActionButtonStyleBuilder
                echo $responsive_css;
            ?></style>
        <?php endif; ?>
        <div class="notifal-action-button-block<?php echo $wrapper_extra_class; ?>" style="<?php echo esc_attr($wrapper_styles); ?>">
            <a <?php echo $link_attributes; ?> style="<?php echo esc_attr($button_styles); ?>">
                <?php if (!empty($attributes['iconUrl'])): ?>
                    <?php if (($attributes['iconType'] ?? 'image') === 'svg' && !empty($attributes['iconColor']) &&
                              $attributes['iconColor'] !== '#ffffff' && $attributes['iconColor'] !== '#000000'): ?>
                        <div
                            style="<?php echo esc_attr($icon_wrapper_styles); ?>"
                            class="notifal-action-button-icon-wrapper"
                        ></div>
                    <?php else: ?>
                        <img
                            src="<?php echo esc_url($attributes['iconUrl']); ?>"
                            alt="<?php echo esc_attr($attributes['iconAlt'] ?? ''); ?>"
                            style="<?php echo esc_attr($icon_styles); ?>"
                            class="notifal-action-button-icon"
                        />
                    <?php endif; ?>
                <?php endif; ?>
                <span class="notifal-action-button-text">
                    <?php echo esc_html($attributes['buttonText']); ?>
                </span>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }



    /**
     * Generate link element attributes.
     *
     * Creates all necessary attributes for the anchor element.
     * Handles different link types and analytics tracking.
     *
     * @param array $attributes Sanitized block attributes.
     * @param string $button_id Unique button identifier.
     * @return string HTML attributes string.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function generateLinkAttributes(array $attributes, string $button_id): string
    {
        $link_attrs = [
            'class="notifal-action-button notifal-track-click"',
            'id="' . esc_attr($button_id) . '"',
            'data-tracking-id="' . esc_attr($button_id) . '"',
            'data-button-text="' . esc_attr($attributes['buttonText']) . '"',
            'aria-label="' . esc_attr__('Notification Action Button', 'notifal') . '"',
            'data-button-type="' . esc_attr($attributes['linkType']) . '"',
            'data-hover-bg="' . esc_attr($attributes['hoverBackgroundColor']) . '"',
            'data-hover-color="' . esc_attr($attributes['hoverTextColor']) . '"',
        ];

        // Handle different link types
        switch ($attributes['linkType']) {
            case 'custom':
                if ($attributes['customUrl']) {
                    $link_attrs[] = 'href="' . esc_url($attributes['customUrl']) . '"';
                    if ($attributes['customUrlTarget']) {
                        $link_attrs[] = 'target="_blank"';
                        $rel_parts = ['noopener', 'noreferrer'];
                        if ($attributes['customUrlNofollow']) {
                            $rel_parts[] = 'nofollow';
                        }
                        $link_attrs[] = 'rel="' . esc_attr(implode(' ', $rel_parts)) . '"';
                    } elseif ($attributes['customUrlNofollow']) {
                        $link_attrs[] = 'rel="nofollow"';
                    }
                }
                $loading_display = $attributes['loadingText'] === null ? esc_attr__('Loading...', 'notifal') : esc_attr($attributes['loadingText']);
                $link_attrs[] = 'data-loading-text="' . $loading_display . '"';
                break;
            case 'copy':
                if ($attributes['copyText']) {
                    $link_attrs[] = 'href="#"';
                    $link_attrs[] = 'data-copy-text="' . esc_attr($attributes['copyText']) . '"';
                    $link_attrs[] = 'data-action="copy"';
                }
                break;
            case 'close':
                $link_attrs[] = 'href="#"';
                $link_attrs[] = 'data-action="close"';
                break;
            case 'ajax-add-to-cart':
                $link_attrs[] = 'href="#"';
                $link_attrs[] = 'data-action="ajax-add-to-cart"';
                $link_attrs[] = 'data-add-to-cart-quantity="' . esc_attr((string) $attributes['addToCartQuantity']) . '"';
                $link_attrs[] = 'data-add-to-cart-redirect="' . esc_attr($attributes['addToCartRedirect']) . '"';
                $link_attrs[] = 'data-add-to-cart-success-text="' . esc_attr($attributes['addToCartSuccessText'] ?: __('Added!', 'notifal')) . '"';
                if (class_exists(WidgetContextProvider::class) && WidgetContextProvider::isActive()) {
                    $context = WidgetContextProvider::getContext();
                    if (isset($context['product']) && $context['product']) {
                        $link_attrs = array_merge($link_attrs, self::buildProductActionLinkAttrs($context['product']));
                        $link_attrs[] = 'data-context-type="product"';
                        $link_attrs[] = 'data-is-product-context="true"';
                    }
                }
                break;
            case 'custom-trigger':
                $link_attrs[] = 'href="#"';
                $link_attrs[] = 'data-action="custom-trigger"';
                
                // Add hide elements data attribute if provided
                if (!empty($attributes['hideElements'])) {
                    $link_attrs[] = 'data-hide-elements="' . esc_attr(sanitize_text_field($attributes['hideElements'])) . '"';
                }
                
                // Add show elements data attribute if provided
                if (!empty($attributes['showElements'])) {
                    $link_attrs[] = 'data-show-elements="' . esc_attr(sanitize_text_field($attributes['showElements'])) . '"';
                }
                break;
            case 'product':
            default:
                $link_attrs[] = 'href="#"';
                $link_attrs[] = 'data-action="post-link"';

                $loading_display = $attributes['loadingText'] === null ? esc_attr__('Loading...', 'notifal') : esc_attr($attributes['loadingText']);
                $link_attrs[] = 'data-loading-text="' . $loading_display . '"';

                // Get context if available during frontend rendering
                $contextMeta = self::resolveBlockContextMeta();

                if (!empty($contextMeta['url'])) {
                    $link_attrs[] = 'data-post-url="' . esc_url($contextMeta['url']) . '"';
                }

                if (!empty($contextMeta['context_type'])) {
                    $link_attrs[] = 'data-context-type="' . esc_attr($contextMeta['context_type']) . '"';
                }

                if (!empty($contextMeta['is_product_context'])) {
                    $link_attrs[] = 'data-is-product-context="true"';
                }

                if (!empty($contextMeta['product_id'])) {
                    $link_attrs[] = 'data-product-id="' . esc_attr((string) $contextMeta['product_id']) . '"';
                }

                if (!empty($contextMeta['variation_id'])) {
                    $link_attrs[] = 'data-variation-id="' . esc_attr((string) $contextMeta['variation_id']) . '"';
                }

                if (!empty($contextMeta['product_url'])) {
                    $link_attrs[] = 'data-product-url="' . esc_url($contextMeta['product_url']) . '"';
                }
                break;
        }

        return implode(' ', $link_attrs);
    }

    /**
     * Resolve block-editor action button context metadata for revenue attribution.
     *
     * @return array{
     *     url?: string,
     *     context_type?: string,
     *     is_product_context?: bool,
     *     product_id?: int,
     *     product_url?: string
     * }
     * @since 2.3.9
     */
    private static function resolveBlockContextMeta(): array
    {
        $meta = [];

        if (!class_exists(WidgetContextProvider::class) || !WidgetContextProvider::isActive()) {
            return $meta;
        }

        $context = WidgetContextProvider::getContext();
        $contextData = null;

        if (isset($context['product']) && $context['product']) {
            $contextData = $context['product'];
            $meta['url'] = $contextData->getLink();
            $meta['context_type'] = 'product';
            $meta['is_product_context'] = true;
        } elseif (isset($context['order']) && $context['order']) {
            $order = $context['order'];
            $orderItems = $order->getItems();

            if (!empty($orderItems)) {
                $firstItem = reset($orderItems);
                $product = $firstItem->getProduct();

                if ($product) {
                    $contextData = $product;
                    $meta['url'] = $product->getPermalink();
                    $meta['context_type'] = 'product';
                    $meta['is_product_context'] = true;
                }
            }

            if (empty($meta['url']) && method_exists($order, 'getViewOrderUrl')) {
                $meta['url'] = $order->getViewOrderUrl();
                $meta['context_type'] = 'order';
            }
        } elseif (isset($context['post']) && $context['post']) {
            $meta['url'] = get_permalink($context['post']->ID);
            $meta['context_type'] = 'post';
        } elseif (isset($context['page']) && $context['page']) {
            $meta['url'] = get_permalink($context['page']->ID);
            $meta['context_type'] = 'page';
        } elseif (isset($context['comment']) && $context['comment']) {
            $meta['url'] = get_permalink($context['comment']->comment_post_ID);
            $meta['context_type'] = 'comment';
        } else {
            foreach ($context as $value) {
                if (is_object($value) && isset($value->ID, $value->post_type)) {
                    $meta['url'] = get_permalink($value->ID);
                    $meta['context_type'] = sanitize_key((string) $value->post_type);
                    break;
                }
            }
        }

        if ($contextData && method_exists($contextData, 'getId')) {
            $meta['product_id'] = (int) $contextData->getId();
            if (method_exists($contextData, 'getVariationContextId')) {
                $variationId = (int) ($contextData->getVariationContextId() ?? 0);
                if ($variationId > 0) {
                    $meta['variation_id'] = $variationId;
                }
            }
            if (method_exists($contextData, 'getLink')) {
                $meta['product_url'] = $contextData->getLink();
            }
        }

        return $meta;
    }

    /**
     * Build product action button data attributes for parent and variation context.
     *
     * @param mixed $product Product DTO or compatible object.
     * @return array<int, string>
     * @since 2.3.10
     */
    private static function buildProductActionLinkAttrs($product): array
    {
        $attrs = [];

        if (!$product || !method_exists($product, 'getId')) {
            return $attrs;
        }

        $attrs[] = 'data-product-id="' . esc_attr((string) $product->getId()) . '"';

        if (method_exists($product, 'getVariationContextId')) {
            $variationId = (int) ($product->getVariationContextId() ?? 0);
            if ($variationId > 0) {
                $attrs[] = 'data-variation-id="' . esc_attr((string) $variationId) . '"';
            }
        }

        if (method_exists($product, 'getLink')) {
            $productLink = $product->getLink();
            if (!empty($productLink)) {
                $attrs[] = 'data-product-url="' . esc_url($productLink) . '"';
            }
        }

        return $attrs;
    }
} 
