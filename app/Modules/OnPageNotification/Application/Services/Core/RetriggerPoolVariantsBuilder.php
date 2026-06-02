<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Core;

use Notifal\Infrastructure\WordPress\Elementor\Helpers\ElementorHelper;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\ContentSourceService;
use Notifal\Modules\OnPageNotification\Application\Services\Tag\FrontendTagContextBuilder;
use Notifal\Modules\OnPageNotification\Application\Services\Template\FrontendTemplateRenderer;
use Notifal\Modules\OnPageNotification\Application\Services\Template\TemplateContextBuilder;
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
    private const POOL_ENTITY_TYPES = ['product', 'order', 'post', 'page'];

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

        $poolMeta = $this->resolvePoolForPrimaryEntity($primaryEntityType, $contentSourceSettings);
        if ($poolMeta === null) {
            return $emptyResult;
        }

        $pool = $poolMeta['pool'];
        $poolCount = count($pool);
        if ($poolCount < 2) {
            return $emptyResult;
        }

        $maxVariants = (int) apply_filters(
            FilterHooks::ONPAGE_RETRIGGER_CLIENT_VARIANTS_MAX,
            12,
            $notificationData,
            $notification
        );
        $maxVariants = max(1, min($maxVariants, $poolCount - 1));

        $templateIdKey = (int) ($context['template_id'] ?? 0);
        if (!$templateIdKey && isset($context['notification_id'])) {
            $templateIdKey = (int) $context['notification_id'];
        }
        $requestId = $_SERVER['REQUEST_TIME_FLOAT'] ?? $_SERVER['REQUEST_TIME'] ?? microtime(true);

        // Must match FrontendTagContextBuilder::buildPoolSelectionCacheKeyParts (no extra keys).
        $selectionKeyParts = [
            'template_id' => $templateIdKey,
            'request_id' => floor((float) $requestId),
            'content_source_settings' => $contentSourceSettings,
        ];
        $selectionCacheKey = $poolMeta['cache_prefix'] . md5(serialize($selectionKeyParts));
        $primaryIndex = (crc32($selectionCacheKey) % $poolCount + $poolCount) % $poolCount;
        $primaryEntityId = $this->resolvePoolItemId($pool, $primaryIndex, $primaryEntityType);

        $variantIndices = [];
        for ($idx = 0; $idx < $poolCount; $idx++) {
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

        foreach ($variantIndices as $poolIndex) {
            TemplateContextBuilder::clearContextCache();
            FrontendTemplateRenderer::clearContextCache();
            FrontendTagContextBuilder::clearRequestEntityCache();

            $variantContext = array_merge($context, [
                'notifal_pool_variant_index' => $poolIndex,
            ]);

            $result = $templateRenderer->renderForFrontend($templateId, $variantContext, $contentSourceSettings);
            if (isset($result['no_matching_data']) && $result['no_matching_data'] === true) {
                continue;
            }

            $html = $result['html'] ?? '';
            if ($html === '') {
                continue;
            }

            $variantEntityId = $this->resolvePoolItemId($pool, $poolIndex, $primaryEntityType);
            if ($primaryEntityId !== null && $variantEntityId !== null && $variantEntityId === $primaryEntityId) {
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

        if ($primaryEntityType !== '' && $primaryEntityType !== 'mixed' && post_type_exists($primaryEntityType)) {
            $pool = $contentSourceService->getCustomPostTypePool($primaryEntityType, $contentSourceSettings);

            return [
                'entity' => $primaryEntityType,
                'pool' => $pool,
                'cache_prefix' => 'notifal_cpt_' . $primaryEntityType . '_context_',
                'post_type' => $primaryEntityType,
            ];
        }

        return null;
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

        if ($item instanceof \WP_Post) {
            return (int) $item->ID;
        }

        return null;
    }
}
