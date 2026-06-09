<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Core;

use Notifal\Infrastructure\WordPress\Elementor\Helpers\ElementorHelper;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\ContentSourceService;
use Notifal\Modules\OnPageNotification\Application\Services\Tag\FrontendTagContextBuilder;
use Notifal\Modules\OnPageNotification\Application\Services\Template\FrontendTemplateRenderer;
use Notifal\Modules\OnPageNotification\Application\Services\Template\TemplateContextBuilder;
use Notifal\Modules\OnPageNotification\Application\Support\ContentSourceRequestContext;
use Notifal\Modules\OnPageNotification\Application\Support\PageContextEnricher;
use Notifal\Modules\OnPageNotification\Application\Support\PageContextHelper;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Pre-renders alternate content-pool variants for client-side retrigger (no extra HTTP).
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services\Core
 * @since 2.3.5
 * @author Hossein <hossein@notifal.com>
 */
class RetriggerPoolVariantsBuilder
{
    /**
     * Entity types that support pool-based client retrigger variants.
     *
     * @var string[]
     */
    private const POOL_ENTITY_TYPES = ['product', 'order', 'post', 'page', 'comment'];

    /**
     * Build pre-rendered variants when retrigger is enabled and the primary entity has a pool ≥ 2.
     *
     * @param \WP_Post $notification Notification post.
     * @param array    $notificationData Resolved notification configuration.
     * @param array    $context Request context merged with template_id and notification_id.
     * @return array{variants: array<int, array<string, mixed>>, primary_entity_id: ?int, primary_pool_index: ?int}
     * @since 2.3.5
     */
    public function build(\WP_Post $notification, array $notificationData, array $context): array
    {
        $emptyResult = [
            'variants' => [],
            'primary_entity_id' => null,
            'primary_pool_index' => null,
        ];

        $timingSettings = $notificationData['timing_settings'] ?? [];
        if (empty($timingSettings['allow_retrigger_after_hide'])) {
            return $emptyResult;
        }

        $templateId = (int) ($notificationData['template_id'] ?? 0);
        if ($templateId <= 0) {
            return $emptyResult;
        }

        $template = Helper::getPostSafe($templateId, 'notifal_template');
        if (!$template) {
            return $emptyResult;
        }

        $contentSourceSettings = $notificationData['content_source_settings'] ?? [];

        $templateContextBuilder = notifal_app(TemplateContextBuilder::class);
        $isElementor = ElementorHelper::hasBuilder($template);
        $rawContent = $isElementor
            ? $templateContextBuilder->extractRawContentForElementor($template)
            : $templateContextBuilder->extractRawContentFromBlocks($template);

        $tagContextBuilder = notifal_app(FrontendTagContextBuilder::class);
        $primaryEntityType = $tagContextBuilder->resolvePrimaryEntityType($rawContent, $contentSourceSettings);

        // Keep smart targeting page context available for pool resolution and variant rendering.
        $pageContext = $this->buildPageContextFromRequest($context, $notification, $rawContent);

        // On singular pages, retrigger variants rotate within the contextual taxonomy pool when level > 0.
        if (
            !empty($contentSourceSettings['smart_targeting_enabled'])
            && PageContextHelper::shouldUseContextPoolForRetrigger($pageContext, $contentSourceSettings)
        ) {
            $pageContext['smart_targeting_forced_phase'] = 'context';
        }

        // Archive pages already resolve the contextual pool; never force singular-style phase widening.
        if (PageContextHelper::isArchiveContext($pageContext)) {
            unset($pageContext['smart_targeting_forced_phase']);
        }

        // Archive API retriggers must pin to the contextual phase without widening to fallback.
        if (
            !empty($pageContext['force_fresh_content'])
            && PageContextHelper::isArchiveContext($pageContext)
            && !empty($contentSourceSettings['smart_targeting_enabled'])
        ) {
            $pageContext['smart_targeting_forced_phase'] = 'context';
        }

        ContentSourceRequestContext::setPageContext($pageContext);

        try {
            $poolMeta = $this->resolvePoolForPrimaryEntity($primaryEntityType, $contentSourceSettings);
        } catch (\Throwable $throwable) {
            ContentSourceRequestContext::reset();

            throw $throwable;
        }

        if ($poolMeta === null) {
            ContentSourceRequestContext::reset();

            return $emptyResult;
        }

        $fullPool = $poolMeta['pool'];
        $fullPoolCount = count($fullPool);

        // Require at least two pool members for alternate retrigger variants.
        if ($fullPoolCount < 2) {
            ContentSourceRequestContext::reset();

            return $emptyResult;
        }

        $poolCount = $fullPoolCount;

        $maxVariants = (int) apply_filters(
            FilterHooks::ONPAGE_RETRIGGER_CLIENT_VARIANTS_MAX,
            12,
            $notificationData,
            $notification
        );
        $maxVariants = max(1, min($maxVariants, $fullPoolCount - 1));

        $templateIdKey = (int) ($context['template_id'] ?? 0);
        if (!$templateIdKey && isset($context['notification_id'])) {
            $templateIdKey = (int) $context['notification_id'];
        }
        $requestId = $_SERVER['REQUEST_TIME_FLOAT'] ?? $_SERVER['REQUEST_TIME'] ?? microtime(true);

        // Match FrontendTagContextBuilder::buildPoolSelectionCacheKeyParts, including smart targeting page scope.
        $selectionKeyParts = [
            'template_id' => $templateIdKey,
            'request_id' => floor((float) $requestId),
            'content_source_settings' => $contentSourceSettings,
        ];
        if (!empty($contentSourceSettings['smart_targeting_enabled'])) {
            $selectionKeyParts['page_id'] = (int) ($pageContext['page_id'] ?? 0);
            $selectionKeyParts['post_type'] = sanitize_key((string) ($pageContext['post_type'] ?? ''));
        }
        $selectionCacheKey = $poolMeta['cache_prefix'] . md5(serialize($selectionKeyParts));
        $primaryIndex = null;
        $primaryEntityId = null;

        // Pin the first paint to the current singular object when smart targeting widens retrigger pools.
        if (PageContextHelper::shouldUseContextPoolForRetrigger($pageContext, $contentSourceSettings)) {
            $currentEntityId = (int) ($pageContext['page_id'] ?? 0);
            if ($currentEntityId > 0) {
                foreach ($fullPool as $idx => $item) {
                    $itemId = $this->resolvePoolItemId($fullPool, (int) $idx, $primaryEntityType);
                    if ($itemId === $currentEntityId) {
                        $primaryIndex = (int) $idx;
                        $primaryEntityId = $currentEntityId;
                        break;
                    }
                }
            }
        }

        if ($primaryIndex === null) {
            $primaryIndex = (crc32($selectionCacheKey) % $poolCount + $poolCount) % $poolCount;
            $primaryEntityId = $this->resolvePoolItemId($fullPool, $primaryIndex, $primaryEntityType);
        }

        $availableEntityIds = $this->collectPoolEntityIds($fullPool, $primaryEntityType);
        if (empty($contentSourceSettings['allow_duplicate_source'])) {
            $contentSourceService = notifal_app(ContentSourceService::class);
            $availablePool = $contentSourceService->excludeSeenSourcesFromPool(
                $primaryEntityType,
                $fullPool,
                $contentSourceSettings
            );
            $availableEntityIds = $this->collectPoolEntityIds($availablePool, $primaryEntityType);
        }

        $variantIndices = [];
        for ($idx = 0; $idx < $fullPoolCount; $idx++) {
            if ($idx === $primaryIndex) {
                continue;
            }
            $variantIndices[] = $idx;
            if (count($variantIndices) >= $maxVariants) {
                break;
            }
        }

        $variants = [];
        $templateRenderer = notifal_app(FrontendTemplateRenderer::class);

        try {
            foreach ($variantIndices as $poolIndex) {
                TemplateContextBuilder::clearContextCache();
                FrontendTemplateRenderer::clearContextCache();
                FrontendTagContextBuilder::clearRequestEntityCache();

                $variantEntityId = $this->resolvePoolItemId($fullPool, $poolIndex, $primaryEntityType);
                if ($variantEntityId === null) {
                    continue;
                }

                if (!in_array($variantEntityId, $availableEntityIds, true)) {
                    continue;
                }

                // Merge enriched page context so tag resolution uses the contextual smart-targeting pool.
                $variantContext = array_merge($pageContext, $context, [
                    'notifal_pool_entity_id' => $variantEntityId,
                    'notifal_skip_seen_source_tracking' => true,
                ]);

                // Singular retrigger variants render inside the widened taxonomy pool when level > 0.
                if (
                    !empty($contentSourceSettings['smart_targeting_enabled'])
                    && PageContextHelper::shouldUseContextPoolForRetrigger($pageContext, $contentSourceSettings)
                ) {
                    $variantContext['smart_targeting_forced_phase'] = 'context';
                }

                if (PageContextHelper::isArchiveContext($pageContext)) {
                    unset($variantContext['smart_targeting_forced_phase']);
                }

                $result = $templateRenderer->renderForFrontend($templateId, $variantContext, $contentSourceSettings);

                if (isset($result['no_matching_data']) && $result['no_matching_data'] === true) {
                    continue;
                }

                $html = $result['html'] ?? '';
                if ($html === '') {
                    continue;
                }

                if ($primaryEntityId !== null && $variantEntityId === $primaryEntityId) {
                    continue;
                }

                $entry = [
                    'template_content' => $html,
                    'content' => $html,
                    'template_assets' => $result['assets'] ?? [],
                    'builder_type' => $result['builder_type'] ?? null,
                    'cache_bust' => time() . '_' . uniqid('', true),
                    'pool_entity_id' => $variantEntityId,
                ];
                if (!empty($result['deferred_featured_image_html'])) {
                    $entry['deferred_featured_image_html'] = $result['deferred_featured_image_html'];
                }
                $variants[] = $entry;
            }
        } finally {
            ContentSourceRequestContext::reset();
        }

        return [
            'variants' => $variants,
            'primary_entity_id' => $primaryEntityId,
            'primary_pool_index' => $primaryIndex,
        ];
    }

    /**
     * Resolve pool metadata for the template primary entity.
     *
     * @param string $primaryEntityType Primary entity slug.
     * @param array  $contentSourceSettings Content source settings.
     * @return array{entity:string,pool:array,cache_prefix:string,post_type?:string}|null
     */
    private function resolvePoolForPrimaryEntity(string $primaryEntityType, array $contentSourceSettings): ?array
    {
        $contentSourceService = notifal_app(ContentSourceService::class);

        if (in_array($primaryEntityType, self::POOL_ENTITY_TYPES, true)) {
            $pool = $this->fetchPoolByEntityType($contentSourceService, $primaryEntityType, $contentSourceSettings);

            return [
                'entity' => $primaryEntityType,
                'pool' => $pool,
                'cache_prefix' => 'notifal_' . $primaryEntityType . '_context_',
            ];
        }

        if ($primaryEntityType !== '' && $primaryEntityType !== 'mixed') {
            $customPostTypeSlug = $this->resolveCustomPostTypeSlug($primaryEntityType);
            if ($customPostTypeSlug !== '') {
                $pool = $contentSourceService->getCustomPostTypePool($customPostTypeSlug, $contentSourceSettings);

                return [
                    'entity' => $primaryEntityType,
                    'pool' => $pool,
                    'cache_prefix' => 'notifal_cpt_' . $customPostTypeSlug . '_context_',
                    'post_type' => $customPostTypeSlug,
                ];
            }
        }

        return null;
    }

    /**
     * Resolve a registered custom post type slug from a pool entity key.
     *
     * @param string $primaryEntityType Primary entity slug.
     * @return string
     * @since 2.3.7
     */
    private function resolveCustomPostTypeSlug(string $primaryEntityType): string
    {
        if (strpos($primaryEntityType, 'custom_posttype:') === 0) {
            $primaryEntityType = substr($primaryEntityType, strlen('custom_posttype:'));
        }

        $postType = sanitize_key($primaryEntityType);

        return ($postType !== '' && post_type_exists($postType)) ? $postType : '';
    }

    /**
     * Fetch pool array for a built-in entity type.
     *
     * @param ContentSourceService $contentSourceService Content source service.
     * @param string               $entityType Entity slug.
     * @param array                $contentSourceSettings Settings.
     * @return array<int, mixed>
     */
    private function fetchPoolByEntityType(ContentSourceService $contentSourceService, string $entityType, array $contentSourceSettings): array
    {
        switch ($entityType) {
            case 'product':
                return $contentSourceService->getProductPool($contentSourceSettings);
            case 'order':
                return $contentSourceService->getOrderPool($contentSourceSettings);
            case 'post':
                return $contentSourceService->getPostPool($contentSourceSettings);
            case 'page':
                return $contentSourceService->getPagePool($contentSourceSettings);
            case 'comment':
                return $contentSourceService->getCommentPool($contentSourceSettings);
            default:
                return [];
        }
    }

    /**
     * Read entity id at a pool offset.
     *
     * @param array  $pool Pool.
     * @param int    $index Index.
     * @param string $entityType Entity slug.
     * @return int|null
     */
    private function resolvePoolItemId(array $pool, int $index, string $entityType): ?int
    {
        if (!isset($pool[$index])) {
            return null;
        }

        $item = $pool[$index];

        if (is_object($item) && method_exists($item, 'getId')) {
            return (int) $item->getId();
        }

        if (is_object($item) && method_exists($item, 'get_id')) {
            return (int) $item->get_id();
        }

        if ($item instanceof \WP_Post) {
            return (int) $item->ID;
        }

        if ($item instanceof \WP_Comment) {
            return (int) $item->comment_ID;
        }

        return null;
    }

    /**
     * Collect entity IDs from a content pool.
     *
     * @param array  $pool       Content pool items.
     * @param string $entityType Entity scope key.
     * @return array<int, int>
     * @since 2.3.7
     */
    private function collectPoolEntityIds(array $pool, string $entityType): array
    {
        $ids = [];

        foreach ($pool as $index => $item) {
            $entityId = $this->resolvePoolItemId($pool, (int) $index, $entityType);
            if ($entityId !== null) {
                $ids[] = $entityId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Normalize API request context for smart targeting pool resolution.
     *
     * @param array    $context      Eligibility request context.
     * @param \WP_Post $notification Notification post.
     * @param string   $templateContent Raw template content for entity detection.
     * @return array<string, mixed>
     * @since 2.3.7
     */
    private function buildPageContextFromRequest(array $context, \WP_Post $notification, string $templateContent): array
    {
        // Start from the incoming request context (page_id, post_type, taxonomies).
        $pageContext = $context;

        // Attach notification id so Pro entity detection can read smart targeting settings.
        $pageContext['notification_id'] = (int) $notification->ID;

        // Include template content so smart targeting knows which entity types are active.
        $pageContext['template_content'] = $templateContent;

        /**
         * Filter retrigger variant page context before pool resolution.
         *
         * @param array    $pageContext  Page context array.
         * @param \WP_Post $notification Notification post.
         * @since 2.3.7
         */
        $pageContext = apply_filters(FilterHooks::ONPAGE_FRONTEND_CONTEXT, $pageContext);

        // Normalize singular/archive context the same way as the eligibility API.
        return (new PageContextEnricher())->enrich($pageContext);
    }
}
