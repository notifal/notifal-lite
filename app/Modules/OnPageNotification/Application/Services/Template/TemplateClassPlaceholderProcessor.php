<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Template;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Templates\Application\Services\FeaturedImageAutoSourceResolver;
use Notifal\Modules\Templates\Application\Services\FeaturedImageResolver;

defined('ABSPATH') || exit;

/**
 * Processes class-based HTML placeholders in rendered notification templates.
 *
 * Allows template authors to use custom HTML with Notifal class hooks instead of
 * dedicated Elementor widgets or Block Editor blocks:
 *   - notifal-post-feature-image
 *   - notifal-close-button
 *   - notifal-action-button
 *   - notifal-countdown
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services\Template
 * @since 2.3.12
 * @author Hossein <hossein@notifal.com>
 */
class TemplateClassPlaceholderProcessor
{
    /**
     * CSS class for featured image placeholders.
     */
    public const CLASS_FEATURED_IMAGE = 'notifal-post-feature-image';

    /**
     * CSS class for close button placeholders.
     */
    public const CLASS_CLOSE_BUTTON = 'notifal-close-button';

    /**
     * CSS class for action button placeholders.
     */
    public const CLASS_ACTION_BUTTON = 'notifal-action-button';

    /**
     * CSS class for countdown timer placeholders.
     *
     * @since 2.4.4
     */
    public const CLASS_COUNTDOWN = 'notifal-countdown';

    /**
     * Process rendered template HTML and hydrate class-based placeholders.
     *
     * @param string $html Rendered template HTML.
     * @param array  $frontendContext Frontend context used for dynamic output.
     * @return string Processed HTML.
     * @since 2.3.12
     */
    public static function process(string $html, array $frontendContext): string
    {
        // Skip processing when HTML is empty or no known placeholders exist
        if ($html === '' || !self::containsPlaceholders($html)) {
            return $html;
        }

        /**
         * Filter HTML before class-placeholder processing.
         *
         * @param string $html Rendered template HTML.
         * @param array  $frontendContext Frontend context array.
         * @since 2.3.12
         */
        $html = apply_filters(FilterHooks::ONPAGE_TEMPLATE_CLASS_PLACEHOLDERS_BEFORE, $html, $frontendContext);

        // Load the HTML fragment into a DOM document for safe manipulation
        $document = self::createDocumentFromFragment($html);
        if (!$document) {
            return $html;
        }

        // Locate the temporary wrapper element that holds the fragment
        $root = $document->getElementById('notifal-ph-root');
        if (!$root) {
            return $html;
        }

        // Resolve action button context once per render pass for performance
        $actionContextMeta = TemplateActionButtonContextResolver::resolve($frontendContext);

        // Process each placeholder type inside the fragment
        self::processFeaturedImagePlaceholders($document, $root, $frontendContext);
        self::processCloseButtonPlaceholders($document, $root);
        self::processActionButtonPlaceholders($document, $root, $actionContextMeta);
        self::processCountdownPlaceholders($document, $root);

        // Serialize the processed fragment back to HTML
        $processedHtml = self::extractRootInnerHtml($document, $root);

        /**
         * Filter HTML after class-placeholder processing.
         *
         * @param string $processedHtml Processed template HTML.
         * @param array  $frontendContext Frontend context array.
         * @since 2.3.12
         */
        return apply_filters(FilterHooks::ONPAGE_TEMPLATE_CLASS_PLACEHOLDERS_AFTER, $processedHtml, $frontendContext);
    }

    /**
     * Quick check for any supported placeholder class in the HTML string.
     *
     * @param string $html Rendered HTML.
     * @return bool
     * @since 2.3.12
     */
    private static function containsPlaceholders(string $html): bool
    {
        return strpos($html, self::CLASS_FEATURED_IMAGE) !== false
            || strpos($html, self::CLASS_CLOSE_BUTTON) !== false
            || strpos($html, self::CLASS_ACTION_BUTTON) !== false
            || strpos($html, self::CLASS_COUNTDOWN) !== false;
    }

    /**
     * Build a DOMDocument from an HTML fragment.
     *
     * @param string $html HTML fragment.
     * @return \DOMDocument|null
     * @since 2.3.12
     */
    private static function createDocumentFromFragment(string $html): ?\DOMDocument
    {
        $document = new \DOMDocument();

        // Suppress libxml warnings for partial HTML fragments
        $previousState = libxml_use_internal_errors(true);

        // Wrap the fragment so DOMDocument can parse partial markup safely
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><html><body><div id="notifal-ph-root">' . $html . '</div></body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        return $loaded ? $document : null;
    }

    /**
     * Extract inner HTML from the temporary wrapper element.
     *
     * @param \DOMDocument   $document Parsed document.
     * @param \DOMElement    $root Wrapper element.
     * @return string
     * @since 2.3.12
     */
    private static function extractRootInnerHtml(\DOMDocument $document, \DOMElement $root): string
    {
        $html = '';
        foreach ($root->childNodes as $childNode) {
            $html .= $document->saveHTML($childNode);
        }

        return $html;
    }

    /**
     * Replace featured image placeholder elements with context-aware image markup.
     *
     * @param \DOMDocument $document Parsed document.
     * @param \DOMElement  $root Wrapper element.
     * @param array        $frontendContext Frontend context.
     * @return void
     * @since 2.3.12
     */
    private static function processFeaturedImagePlaceholders(
        \DOMDocument $document,
        \DOMElement $root,
        array $frontendContext
    ): void {
        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query(
            './/*[contains(concat(" ", normalize-space(@class), " "), " ' . self::CLASS_FEATURED_IMAGE . ' ")]',
            $root
        );

        if (!$nodes) {
            return;
        }

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            // Skip placeholders that were already processed in a previous pass
            if ($node->getAttribute('data-notifal-class-placeholder') === 'featured-image') {
                continue;
            }

            // Merge widget-compatible wrapper classes onto the placeholder element
            self::appendClass($node, 'notifal-featured-image-wrapper');
            self::appendClass($node, 'notifal-flex');
            self::appendClass($node, 'notifal-full-width');

            // Read optional data attributes from the placeholder element
            $source = sanitize_key((string) $node->getAttribute('data-preview-image-source'));
            if ($source === '') {
                $source = 'auto';
            }

            $resolution = sanitize_key((string) $node->getAttribute('data-image-resolution'));
            if ($resolution === '') {
                $resolution = 'large';
            }

            $lazyLoadRaw = strtolower(trim((string) $node->getAttribute('data-image-lazy-load')));
            $lazyLoad = !in_array($lazyLoadRaw, ['0', 'false', 'no', 'eager'], true);

            // Read optional image dimension styles authored in the HTML Builder
            $imageInlineStyle = self::buildFeaturedImageInlineStyle($node);

            // Resolve auto source from template tags, matching widget behavior
            if ($source === 'auto') {
                $templateContent = isset($frontendContext['template_content'])
                    ? (string) $frontendContext['template_content']
                    : '';
                $source = $templateContent !== ''
                    ? FeaturedImageAutoSourceResolver::resolve($templateContent)
                    : 'auto';
            }

            // Build image HTML using the same resolver as widgets and blocks
            $imageAttributes = [
                'loading' => $lazyLoad ? 'lazy' : 'eager',
                'class'   => 'notifal-featured-image',
            ];

            if ($imageInlineStyle !== '') {
                $imageAttributes['style'] = $imageInlineStyle;
            }

            $imageHtml = FeaturedImageResolver::getFeaturedImageHtml(
                $frontendContext,
                $resolution,
                $imageAttributes,
                $source
            );

            // Replace placeholder contents with the standard pulse wrapper structure
            while ($node->firstChild) {
                $node->removeChild($node->firstChild);
            }

            $pulseWrapper = $document->createElement('div');
            self::appendClass($pulseWrapper, 'notifal-pulse-img');

            $fragment = $document->createDocumentFragment();
            if (@$fragment->appendXML('<root>' . $imageHtml . '</root>') && $fragment->firstChild) {
                foreach (iterator_to_array($fragment->firstChild->childNodes) as $imageNode) {
                    $pulseWrapper->appendChild($document->importNode($imageNode, true));
                }
            } else {
                // Fallback when appendXML fails due to HTML entities in image markup
                $tempDocument = new \DOMDocument();
                libxml_use_internal_errors(true);
                $tempDocument->loadHTML(
                    '<?xml encoding="utf-8" ?><html><body><div id="notifal-img-root">' . $imageHtml . '</div></body></html>',
                    LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
                );
                libxml_clear_errors();
                $tempRoot = $tempDocument->getElementById('notifal-img-root');
                if ($tempRoot) {
                    foreach ($tempRoot->childNodes as $imageNode) {
                        $pulseWrapper->appendChild($document->importNode($imageNode, true));
                    }
                }
            }

            $node->appendChild($pulseWrapper);
            $node->setAttribute('data-notifal-class-placeholder', 'featured-image');
        }
    }

    /**
     * Enhance close button placeholder elements with widget-compatible behavior hooks.
     *
     * @param \DOMDocument $document Parsed document.
     * @param \DOMElement  $root Wrapper element.
     * @return void
     * @since 2.3.12
     */
    private static function processCloseButtonPlaceholders(\DOMDocument $document, \DOMElement $root): void
    {
        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query(
            './/*[contains(concat(" ", normalize-space(@class), " "), " ' . self::CLASS_CLOSE_BUTTON . ' ")]',
            $root
        );

        if (!$nodes) {
            return;
        }

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            // Skip native widget/block close icons that already use notifal-close
            if (self::elementHasClass($node, 'notifal-close')) {
                continue;
            }

            if (self::hasAncestorWithClass($node, 'notifal-close-icon-wrapper')) {
                continue;
            }

            // Add the legacy close class used by frontend dismissal handlers
            self::appendClass($node, 'notifal-close');

            // Ensure accessibility attributes exist on the interactive element
            if (!$node->hasAttribute('role')) {
                $node->setAttribute('role', 'button');
            }

            if (!$node->hasAttribute('tabindex')) {
                $node->setAttribute('tabindex', '0');
            }

            if (!$node->hasAttribute('aria-label')) {
                $node->setAttribute('aria-label', esc_attr__('Close Notification', 'notifal'));
            }

            $node->setAttribute('data-notifal-class-placeholder', 'close-button');
        }
    }

    /**
     * Normalize countdown placeholders and sync initial unit display text.
     *
     * Frontend JS ticks the timer from data-countdown-seconds. This pass only
     * sanitizes attributes and keeps visible unit spans aligned with the duration.
     *
     * @param \DOMDocument $document Parsed document.
     * @param \DOMElement  $root Wrapper element.
     * @return void
     * @since 2.4.4
     */
    private static function processCountdownPlaceholders(\DOMDocument $document, \DOMElement $root): void
    {
        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query(
            './/*[contains(concat(" ", normalize-space(@class), " "), " ' . self::CLASS_COUNTDOWN . ' ")]',
            $root
        );

        if (!$nodes) {
            return;
        }

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            // Sanitize duration to a non-negative integer second count.
            $raw_seconds = $node->getAttribute('data-countdown-seconds');
            $total_seconds = absint($raw_seconds);
            $node->setAttribute('data-countdown-seconds', (string) $total_seconds);

            // Default format hint for authors / AI (runtime uses unit spans).
            if (!$node->hasAttribute('data-countdown-format')) {
                $node->setAttribute('data-countdown-format', 'mm:ss');
            }

            // Normalize on-complete action for the frontend timer runtime.
            $on_complete = strtolower((string) $node->getAttribute('data-countdown-on-complete'));
            if (!in_array($on_complete, ['stay', 'hide', 'close'], true)) {
                $on_complete = 'stay';
            }
            $node->setAttribute('data-countdown-on-complete', $on_complete);

            if (!$node->hasAttribute('aria-label')) {
                $node->setAttribute('aria-label', esc_attr__('Countdown timer', 'notifal'));
            }

            // Keep nested value spans in sync with the configured duration.
            self::syncCountdownUnitDisplays($xpath, $node, $total_seconds);

            $node->setAttribute('data-notifal-class-placeholder', 'countdown');
        }
    }

    /**
     * Write padded unit values into [data-countdown-unit] spans.
     *
     * @param \DOMXPath   $xpath         Document XPath helper.
     * @param \DOMElement $countdownRoot Countdown root element.
     * @param int         $total_seconds Duration in seconds.
     * @return void
     * @since 2.4.4
     */
    private static function syncCountdownUnitDisplays(
        \DOMXPath $xpath,
        \DOMElement $countdownRoot,
        int $total_seconds
    ): void {
        $days = (int) floor($total_seconds / 86400);
        $hours = (int) floor(($total_seconds % 86400) / 3600);
        $minutes = (int) floor(($total_seconds % 3600) / 60);
        $seconds = (int) ($total_seconds % 60);

        $parts = [
            'days' => $days,
            'hours' => $hours,
            'minutes' => $minutes,
            'seconds' => $seconds,
        ];

        $unit_nodes = $xpath->query('.//*[@data-countdown-unit]', $countdownRoot);

        if (!$unit_nodes) {
            return;
        }

        foreach ($unit_nodes as $unit_node) {
            if (!$unit_node instanceof \DOMElement) {
                continue;
            }

            $unit = strtolower((string) $unit_node->getAttribute('data-countdown-unit'));

            if (!isset($parts[$unit])) {
                continue;
            }

            $value = (int) $parts[$unit];
            $unit_node->textContent = $value < 10 ? '0' . (string) $value : (string) $value;
        }
    }

    /**
     * Enhance custom action button placeholders with tracking and context attributes.
     *
     * @param \DOMDocument $document Parsed document.
     * @param \DOMElement  $root Wrapper element.
     * @param array        $contextMeta Resolved action button context metadata.
     * @return void
     * @since 2.3.12
     */
    private static function processActionButtonPlaceholders(
        \DOMDocument $document,
        \DOMElement $root,
        array $contextMeta
    ): void {
        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query(
            './/*[contains(concat(" ", normalize-space(@class), " "), " ' . self::CLASS_ACTION_BUTTON . ' ")]',
            $root
        );

        if (!$nodes) {
            return;
        }

        static $buttonIndex = 0;

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            // Skip widget/block output that already defines action behavior
            if ($node->hasAttribute('data-action')) {
                continue;
            }

            if (self::hasAncestorWithClass($node, 'notifal-action-button-wrapper')
                || self::hasAncestorWithClass($node, 'notifal-action-button-block')) {
                continue;
            }

            // Add shared tracking class used across Notifal action buttons
            self::appendClass($node, 'notifal-track-click');

            // Generate a stable tracking id when the author did not provide one
            if (!$node->hasAttribute('id') && !$node->hasAttribute('data-tracking-id')) {
                $buttonIndex++;
                $trackingId = 'notifal-btn-class-' . $buttonIndex;
                $node->setAttribute('id', $trackingId);
                $node->setAttribute('data-tracking-id', $trackingId);
            }

            if (!$node->hasAttribute('aria-label')) {
                $node->setAttribute('aria-label', esc_attr__('Notification Action Button', 'notifal'));
            }

            // Read explicit action type from data attributes when provided
            $action = sanitize_key((string) $node->getAttribute('data-notifal-action'));
            if ($action === '') {
                $action = sanitize_key((string) $node->getAttribute('data-link-type'));
            }

            $href = trim((string) $node->getAttribute('href'));
            $hasCustomHref = $href !== '' && $href !== '#';

            // When no explicit action is set, infer behavior from href or default to post-link
            if ($action === '') {
                $action = $hasCustomHref ? 'custom' : 'post-link';
            }

            switch ($action) {
                case 'copy':
                    $node->setAttribute('data-action', 'copy');
                    if (!$node->hasAttribute('href')) {
                        $node->setAttribute('href', '#');
                    }
                    break;

                case 'close':
                    $node->setAttribute('data-action', 'close');
                    $node->setAttribute('href', '#');
                    break;

                case 'custom-trigger':
                    $node->setAttribute('data-action', 'custom-trigger');
                    $node->setAttribute('href', '#');
                    self::applyCustomTriggerSelectorAttributes($node);
                    break;

                case 'ajax-add-to-cart':
                    $node->setAttribute('data-action', 'ajax-add-to-cart');
                    $node->setAttribute('href', '#');
                    self::applyProductContextAttributes($node, $contextMeta);
                    if (!$node->hasAttribute('data-add-to-cart-quantity')) {
                        $node->setAttribute('data-add-to-cart-quantity', '1');
                    }
                    if (!$node->hasAttribute('data-add-to-cart-redirect')) {
                        $node->setAttribute('data-add-to-cart-redirect', 'none');
                    }
                    if (!$node->hasAttribute('data-add-to-cart-success-text')) {
                        $node->setAttribute(
                            'data-add-to-cart-success-text',
                            esc_attr__('Added!', 'notifal')
                        );
                    }
                    break;

                case 'custom':
                    if (!$hasCustomHref) {
                        $customUrl = esc_url_raw((string) $node->getAttribute('data-custom-url'));
                        if ($customUrl !== '') {
                            $node->setAttribute('href', esc_url($customUrl));
                        } elseif (!$node->hasAttribute('href')) {
                            $node->setAttribute('href', '#');
                        }
                    }
                    if (!$node->hasAttribute('data-loading-text')) {
                        $node->setAttribute('data-loading-text', esc_attr__('Loading...', 'notifal'));
                    }
                    break;

                case 'post-link':
                case 'product':
                case 'product-link':
                default:
                    $node->setAttribute('data-action', 'post-link');
                    if (!$node->hasAttribute('href')) {
                        $node->setAttribute('href', '#');
                    }
                    if (!$node->hasAttribute('data-loading-text')) {
                        $node->setAttribute('data-loading-text', esc_attr__('Loading...', 'notifal'));
                    }
                    self::applyPostLinkContextAttributes($node, $contextMeta);
                    break;
            }

            $node->setAttribute('data-notifal-class-placeholder', 'action-button');
        }
    }

    /**
     * Sanitize and preserve custom-trigger show/hide selector attributes.
     *
     * @param \DOMElement $node Button element.
     * @return void
     * @since 2.4.2
     */
    private static function applyCustomTriggerSelectorAttributes(\DOMElement $node): void
    {
        // Read hide selector list from the author-provided data attribute
        $hideElements = sanitize_text_field((string) $node->getAttribute('data-hide-elements'));

        // Persist sanitized hide selectors when the author supplied them
        if ($hideElements !== '') {
            $node->setAttribute('data-hide-elements', esc_attr($hideElements));
        }

        // Read show selector list from the author-provided data attribute
        $showElements = sanitize_text_field((string) $node->getAttribute('data-show-elements'));

        // Persist sanitized show selectors when the author supplied them
        if ($showElements !== '') {
            $node->setAttribute('data-show-elements', esc_attr($showElements));
        }
    }

    /**
     * Apply post-link context data attributes to a placeholder button element.
     *
     * @param \DOMElement $node Button element.
     * @param array       $contextMeta Resolved context metadata.
     * @return void
     * @since 2.3.12
     */
    private static function applyPostLinkContextAttributes(\DOMElement $node, array $contextMeta): void
    {
        if (!empty($contextMeta['url'])) {
            $node->setAttribute('data-post-url', esc_url((string) $contextMeta['url']));
        }

        if (!empty($contextMeta['context_type'])) {
            $node->setAttribute('data-context-type', sanitize_key((string) $contextMeta['context_type']));
        }

        if (!empty($contextMeta['is_product_context'])) {
            $node->setAttribute('data-is-product-context', 'true');
        }

        self::applyProductContextAttributes($node, $contextMeta);
    }

    /**
     * Apply WooCommerce product identifiers to a placeholder button element.
     *
     * @param \DOMElement $node Button element.
     * @param array       $contextMeta Resolved context metadata.
     * @return void
     * @since 2.3.12
     */
    private static function applyProductContextAttributes(\DOMElement $node, array $contextMeta): void
    {
        if (!empty($contextMeta['product_id'])) {
            $node->setAttribute('data-product-id', (string) (int) $contextMeta['product_id']);
        }

        if (!empty($contextMeta['variation_id'])) {
            $node->setAttribute('data-variation-id', (string) (int) $contextMeta['variation_id']);
        }

        if (!empty($contextMeta['product_url'])) {
            $node->setAttribute('data-product-url', esc_url((string) $contextMeta['product_url']));
        }
    }

    /**
     * Build inline CSS for the dynamically injected featured image from placeholder data attributes.
     *
     * @param \DOMElement $node Placeholder element.
     * @return string Sanitized inline style string.
     * @since 2.4.0
     */
    private static function buildFeaturedImageInlineStyle(\DOMElement $node): string
    {
        $styleMap = [
            'data-notifal-image-width'          => 'width',
            'data-notifal-image-height'         => 'height',
            'data-notifal-image-border-radius'  => 'border-radius',
            'data-notifal-image-object-fit'   => 'object-fit',
        ];

        $styles = [];

        foreach ($styleMap as $attribute => $property) {
            $rawValue = trim((string) $node->getAttribute($attribute));

            if ($rawValue === '') {
                continue;
            }

            $sanitizedValue = self::sanitizeFeaturedImageStyleValue($property, $rawValue);

            if ($sanitizedValue !== '') {
                $styles[] = $property . ': ' . $sanitizedValue;
            }
        }

        return implode('; ', $styles);
    }

    /**
     * Sanitize a single featured-image CSS value from builder data attributes.
     *
     * @param string $property CSS property name.
     * @param string $value    Raw attribute value.
     * @return string Sanitized CSS value or empty string when invalid.
     * @since 2.4.0
     */
    private static function sanitizeFeaturedImageStyleValue(string $property, string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if ($property === 'object-fit') {
            $allowed = ['fill', 'contain', 'cover', 'none', 'scale-down'];

            return in_array($value, $allowed, true) ? $value : '';
        }

        if (preg_match('/^(auto|inherit|initial)$/i', $value)) {
            return strtolower($value);
        }

        if (preg_match('/^[\d.]+(px|%|vw|em|rem)$/i', $value)) {
            return $value;
        }

        if (preg_match('/^[\d.]+$/', $value)) {
            return $value . 'px';
        }

        return '';
    }

    /**
     * Append a CSS class to a DOM element when it is not already present.
     *
     * @param \DOMElement $element Target element.
     * @param string      $className Class name to append.
     * @return void
     * @since 2.3.12
     */
    private static function appendClass(\DOMElement $element, string $className): void
    {
        if (!self::elementHasClass($element, $className)) {
            $element->setAttribute('class', trim($element->getAttribute('class') . ' ' . $className));
        }
    }

    /**
     * Check whether a DOM element contains a specific CSS class.
     *
     * @param \DOMElement $element Target element.
     * @param string      $className Class name.
     * @return bool
     * @since 2.3.12
     */
    private static function elementHasClass(\DOMElement $element, string $className): bool
    {
        return strpos(' ' . $element->getAttribute('class') . ' ', ' ' . $className . ' ') !== false;
    }

    /**
     * Check whether any ancestor element contains a specific CSS class.
     *
     * @param \DOMElement $element Target element.
     * @param string      $className Class name.
     * @return bool
     * @since 2.3.12
     */
    private static function hasAncestorWithClass(\DOMElement $element, string $className): bool
    {
        $parent = $element->parentNode;
        while ($parent instanceof \DOMElement) {
            if (self::elementHasClass($parent, $className)) {
                return true;
            }
            $parent = $parent->parentNode;
        }

        return false;
    }
}
