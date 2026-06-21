<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Template;

use Notifal\Modules\Templates\Application\Services\PreviewDataResolver;
use Notifal\Infrastructure\WordPress\Support\ContentExtractor;
use Notifal\Modules\OnPageNotification\Application\Services\Tag\FrontendTagContextBuilder;
use Notifal\Modules\OnPageNotification\Application\Support\ContentSourceRequestContext;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Builds frontend context for template rendering with tag processing.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 *
 */
class TemplateContextBuilder
{
    /**
     * @var FrontendTagContextBuilder
     */
    private $contextBuilder;

    /**
     * @var PreviewDataResolver
     */
    private $previewDataResolver;

    /**
     * Request-scoped context cache to prevent different random products per render
     */
    private static $contextCache = [];

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        try {
            $this->contextBuilder = notifal_app(FrontendTagContextBuilder::class);
        } catch (\Exception $e) {
            $this->contextBuilder = null;
        }

        try {
            $this->previewDataResolver = notifal_app(PreviewDataResolver::class);
        } catch (\Exception $e) {
            $this->previewDataResolver = null;
        }
    }

    /**
     * Build frontend context for tag processing using content source settings.
     *
     * @param string $rawContent Raw content for tag detection
     * @param array $context Original context data
     * @param array $contentSourceSettings Content source settings
     * @return array Built frontend context
     * @since 2.0.0
     */
    public function buildContext(string $rawContent, array $context, array $contentSourceSettings): array
    {
        // Create cache key based on content and settings to ensure consistent context
        $templateId = ($context['template_id'] ?? 0);
        if (!$templateId && isset($context['notification_id'])) {
            $templateId = $context['notification_id'];
        }

        // Use a more consistent request identifier
        $requestId = $_SERVER['REQUEST_TIME_FLOAT'] ?? $_SERVER['REQUEST_TIME'] ?? microtime(true);

        // For retrigger requests, include microtime to force fresh content generation
        $retriggerTimestamp = null;
        if (isset($context['force_fresh_content']) && $context['force_fresh_content']) {
            $retriggerTimestamp = microtime(true);
        }

        // Create consistent cache key that ensures same product for same notification rendering
        $contextKeyParts = [
            'template_id' => $templateId,
            'request_id' => floor($requestId),
            'content_source_settings' => $contentSourceSettings,
            'content_hash' => md5($rawContent),
            'retrigger_timestamp' => $retriggerTimestamp,
        ];
        if (isset($context['notifal_pool_variant_index']) && is_numeric($context['notifal_pool_variant_index'])) {
            $contextKeyParts['pool_variant_index'] = (int) $context['notifal_pool_variant_index'];
        }
        // Pin a unique cache entry per pre-rendered retrigger variant and smart targeting phase.
        if (!empty($context['notifal_pool_entity_id'])) {
            $contextKeyParts['pool_entity_id'] = (int) $context['notifal_pool_entity_id'];
        }
        if (!empty($context['smart_targeting_forced_phase'])) {
            $contextKeyParts['forced_phase'] = sanitize_key((string) $context['smart_targeting_forced_phase']);
        }
        $cacheKey = 'notifal_context_' . md5(serialize($contextKeyParts));

        // Check if we already built context for this exact combination (request-scoped)
        if (isset(self::$contextCache[$cacheKey])) {
            $cachedContext = self::$contextCache[$cacheKey];
            $cachedContext['_cache_hit'] = true;
            $cachedContext['_cache_key'] = $cacheKey;
            return $cachedContext;
        }

        // If no content source settings, fallback to preview behavior
        if (empty($contentSourceSettings) || !$this->contextBuilder) {
            $fallbackContext = $this->buildFallbackContext($rawContent, $context);
            $fallbackContext['_cache_key'] = $cacheKey;
            $fallbackContext['_is_fallback'] = true;
            self::$contextCache[$cacheKey] = $fallbackContext;
            return $fallbackContext;
        }

        // Build context using content source restrictions
        $pageContext = array_merge($context, [
            'template_content' => $rawContent,
            'template_id' => $templateId,
            'request_id' => $requestId
        ]);

        /**
         * Filter page context before content source resolution.
         *
         * @param array $pageContext Page context array.
         * @since 2.3.7
         */
        $pageContext = apply_filters(FilterHooks::ONPAGE_FRONTEND_CONTEXT, $pageContext);

        // Expose page context to content source services during this resolution pass.
        ContentSourceRequestContext::setPageContext($pageContext);

        try {
            $frontendContext = $this->contextBuilder->buildContext($contentSourceSettings, $pageContext);
        } finally {
            ContentSourceRequestContext::reset();
        }

        // Check if context indicates no matching data due to active filters
        if ($this->hasNoMatchingData($frontendContext)) {
            return [
                'html' => '',
                'assets' => [],
                'no_matching_data' => true,
                'applied_filters' => $frontendContext['applied_filters'] ?? []
            ];
        }

        // Add template content for tag processing
        $frontendContext['template_content'] = $rawContent;
        $frontendContext['_cache_key'] = $cacheKey;
        $frontendContext['_is_cached'] = false;

        // Cache the context for subsequent calls with same parameters
        self::$contextCache[$cacheKey] = $frontendContext;

        return $frontendContext;
    }

    /**
     * Extract raw content for tag detection from Elementor template.
     *
     * @param \WP_Post $template Template post
     * @return string Raw content containing tags for context detection
     * @since 2.0.0
     */
    public function extractRawContentForElementor(\WP_Post $template): string
    {
        return ContentExtractor::extractFromElementorTemplate($template);
    }

    /**
     * Extract raw content for tag detection from Block Editor template.
     *
     * @param \WP_Post $template Template post
     * @return string Raw content containing tags for context detection
     * @since 2.0.0
     */
    public function extractRawContentFromBlocks(\WP_Post $template): string
    {
        return ContentExtractor::extractFromBlockTemplate($template);
    }

    /**
     * Extract raw content for tag detection from HTML Builder templates.
     *
     * @param string $html Raw HTML string from post_content or unsaved editor state.
     * @return string Content used for tag/category detection.
     * @since 2.4.0
     */
    public function extractRawContentFromHtml(string $html): string
    {
        // HTML Builder stores tags directly inside post_content markup.
        return $html;
    }

    /**
     * Check if the frontend context indicates no matching data.
     *
     * @param array $frontendContext Frontend context array
     * @return bool True if no matching data found
     * @since 2.0.0
     */
    public function hasNoMatchingData(array $frontendContext): bool
    {
        return isset($frontendContext['no_matching_data']) && $frontendContext['no_matching_data'] === true;
    }

    /**
     * Clear the request-scoped context cache.
     *
     * @return void
     * @since 2.0.0
     */
    public static function clearContextCache(): void
    {
        self::$contextCache = [];
    }

    /**
     * Build fallback context when content source settings are not available.
     *
     * @param string $content Template content
     * @param array $context Original context data
     * @return array Fallback context
     * @since 2.0.0
     */
    private function buildFallbackContext(string $content, array $context): array
    {
        // Build the same rich preview context used by Elementor/Block Editor previews.
        if ($this->previewDataResolver) {
            try {
                $renderContext = $this->previewDataResolver->buildTagRenderContext($content);

                return array_merge($renderContext, $context, [
                    'template_content' => $content,
                    'is_preview'       => true,
                ]);
            } catch (\Exception $e) {
                // Preview data resolver failed - continue with empty context.
            }
        }

        // Return basic fallback context.
        return array_merge($context, [
            'template_content' => $content,
            'is_preview'       => true,
        ]);
    }
}
