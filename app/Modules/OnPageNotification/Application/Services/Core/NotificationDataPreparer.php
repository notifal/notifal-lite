<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Core;

use Notifal\Infrastructure\WordPress\Elementor\Helpers\ElementorHelper;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Traits\NotificationDataTrait;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\AppearanceSettingsService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\BehaviorSettingsService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\ContentSourceService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\TimingSettingsService;
use Notifal\Modules\OnPageNotification\Application\Services\Tag\FrontendTagContextBuilder;
use Notifal\Modules\OnPageNotification\Application\Services\Template\FrontendTemplateRenderer;
use Notifal\Modules\OnPageNotification\Application\Services\Template\TemplateContextBuilder;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class NotificationDataPreparer
 *
 * Handles preparation of notification data for frontend consumption.
 * Formats notification data for JavaScript consumption, including
 * rendered content, settings, and metadata.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class NotificationDataPreparer
{
    use NotificationDataTrait;
    /**
     * @var AppearanceSettingsService
     */
    private $appearanceService;

    /**
     * @var BehaviorSettingsService
     */
    private $behaviorService;

    /**
     * @var TimingSettingsService
     */
    private $timingService;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->appearanceService = notifal_app(AppearanceSettingsService::class);
        $this->behaviorService = notifal_app(BehaviorSettingsService::class);
        $this->timingService = notifal_app(TimingSettingsService::class);
    }

    /**
     * Prepare notification data for frontend consumption.
     *
     * Formats notification data for JavaScript consumption, including
     * rendered content, settings, and metadata.
     *
     * @param \WP_Post $notification Notification post
     * @param array $context Current page context
     * @return array|null Prepared notification data or null if no matching data found
     * @since 2.0.0
     * @since 2.2.0 Exposes `campaign_id`, `campaign_start_date`, and `campaign_end_date` for frontend schedule alignment.
     */
    public function prepareForFrontend(\WP_Post $notification, array $context): ?array
    {
        $notificationData = $this->getNotificationData($notification);

        // Apply content filter for dynamic content processing
        $notificationData = apply_filters(
            FilterHooks::ONPAGE_NOTIFICATION_CONTENT,
            $notificationData,
            $context
        );

        $templateIdForContext = (int) ($notificationData['template_id'] ?? 0);
        $context = array_merge($context, [
            'template_id' => $templateIdForContext,
            'notification_id' => (int) $notification->ID,
        ]);

        $renderedContent = $this->getRenderedTemplateContent($notificationData, $context);

        if ($renderedContent === null) {
            return null;
        }

        // Resolve linked campaign schedule so the frontend can mirror server eligibility when the notification schedule UI is disabled.
        $campaignId = (int) get_post_meta( $notification->ID, '_notifal_campaign_id', true );
        $campaignStartDate = '';
        $campaignEndDate   = '';
        if ( $campaignId > 0 ) {
            $campaignSettings = get_post_meta( $campaignId, '_notifal_campaign_settings', true );
            if ( is_array( $campaignSettings ) ) {
                $campaignStartDate = (string) ( $campaignSettings['start_date'] ?? '' );
                $campaignEndDate   = (string) ( $campaignSettings['end_date'] ?? '' );
            }
        }

        $frontendData = [
            'id' => $notification->ID,
            'title' => $notificationData['notif_title'] ?? $notification->post_title,
            'template_content' => $renderedContent['html'] ?? ($notificationData['template_content'] ?? ''),
            'content' => $renderedContent['html'] ?? ($notificationData['template_content'] ?? ''), // Backward compatibility
            'is_active' => (get_post_meta($notification->ID, '_notifal_notif_enabled', true) === '1'),
            'cache_bust' => time() . '_' . uniqid(),
            'display_type' => $notificationData['appearance_settings']['notification_display_type'] ?? 'toast',
            'trigger_type' => $notificationData['behavior_settings']['trigger_type'] ?? 'delay',
            'delay_seconds' => $notificationData['behavior_settings']['delay_seconds'] ?? 0,
            'scroll_percentage' => $notificationData['behavior_settings']['scroll_percentage'] ?? 0,
            'auto_close_seconds' => $notificationData['behavior_settings']['auto_close_seconds'] ?? 0,
            'is_dismissible' => $notificationData['behavior_settings']['is_dismissible'] ?? true,
            'frequency_cap_daily' => $notificationData['behavior_settings']['frequency_cap_daily'] ?? 0,
            'frequency_cap_total' => $notificationData['behavior_settings']['frequency_cap_total'] ?? 0,
            'start_date' => $notificationData['timing_settings']['start_date'] ?? null,
            'end_date' => $notificationData['timing_settings']['end_date'] ?? null,
            'campaign_id' => $campaignId > 0 ? $campaignId : null,
            'campaign_start_date' => $campaignStartDate !== '' ? $campaignStartDate : null,
            'campaign_end_date' => $campaignEndDate !== '' ? $campaignEndDate : null,

            'appearance' => $this->prepareAppearanceSettings($notificationData),
            'behavior' => $this->prepareBehaviorSettings($notificationData),
            'timing' => $this->prepareTimingSettings($notificationData),
            'template_assets' => $renderedContent['assets'] ?? [],
            'builder_type' => $renderedContent['builder_type'] ?? null,
        ];

        // For Elementor + immediate show: pass deferred featured image HTML for frontend to inject after delay
        if (!empty($renderedContent['deferred_featured_image_html'])) {
            $frontendData['deferred_featured_image_html'] = $renderedContent['deferred_featured_image_html'];
        }

        // Add calculated priority score for frontend conflict resolution
        $frontendData['priority'] = $this->getNotificationPriority($frontendData);

        $retriggerVariants = $this->buildRetriggerProductPoolVariants($notification, $notificationData, $context);
        if (!empty($retriggerVariants)) {
            $frontendData['retrigger_variants'] = $retriggerVariants;
        }

        return $frontendData;
    }

    /**
     * Pre-render alternate product-pool members for client-side retrigger (no extra HTTP).
     *
     * @param \WP_Post $notification Notification post
     * @param array $notificationData Resolved notification configuration
     * @param array $context Request context merged with template_id and notification_id
     * @return array<int, array<string, mixed>> List of variant payloads (template HTML, assets, optional deferred image)
     * @since 2.2.0
     * @author Hossein <hossein@notifal.com>
     */
    private function buildRetriggerProductPoolVariants(\WP_Post $notification, array $notificationData, array $context): array
    {
        $timingSettings = $notificationData['timing_settings'] ?? [];
        if (empty($timingSettings['allow_retrigger_after_hide'])) {
            return [];
        }

        $templateId = (int) ($notificationData['template_id'] ?? 0);
        if ($templateId <= 0) {
            return [];
        }

        $template = Helper::getPostSafe($templateId, 'notifal_template');
        if (!$template) {
            return [];
        }

        $contentSourceSettings = $notificationData['content_source_settings'] ?? [];

        $templateContextBuilder = notifal_app(TemplateContextBuilder::class);
        $isElementor = ElementorHelper::hasBuilder($template);
        $rawContent = $isElementor
            ? $templateContextBuilder->extractRawContentForElementor($template)
            : $templateContextBuilder->extractRawContentFromBlocks($template);

        $tagContextBuilder = notifal_app(FrontendTagContextBuilder::class);
        if ($tagContextBuilder->resolvePrimaryEntityType($rawContent, $contentSourceSettings) !== 'product') {
            return [];
        }

        $contentSourceService = notifal_app(ContentSourceService::class);
        $productPool = $contentSourceService->getProductPool($contentSourceSettings);
        $poolCount = count($productPool);
        if ($poolCount < 2) {
            return [];
        }

        $maxVariants = (int) apply_filters(
            FilterHooks::ONPAGE_RETRIGGER_CLIENT_VARIANTS_MAX,
            12,
            $notificationData,
            $notification
        );
        $maxVariants = max(1, min($maxVariants, $poolCount));

        $templateIdKey = (int) ($context['template_id'] ?? 0);
        if (!$templateIdKey && isset($context['notification_id'])) {
            $templateIdKey = (int) $context['notification_id'];
        }
        $requestId = $_SERVER['REQUEST_TIME_FLOAT'] ?? $_SERVER['REQUEST_TIME'] ?? microtime(true);
        $productCacheKeyParts = [
            'template_id' => $templateIdKey,
            'request_id' => floor((float) $requestId),
            'content_source_settings' => $contentSourceSettings,
        ];
        $productCacheKeyFull = 'notifal_product_context_' . md5(serialize($productCacheKeyParts));
        $crc = crc32($productCacheKeyFull);
        $primaryIndex = ($crc % $poolCount + $poolCount) % $poolCount;

        $variants = [];
        $templateRenderer = notifal_app(FrontendTemplateRenderer::class);

        for ($i = 0; $i < $maxVariants; $i++) {
            if ($i === $primaryIndex) {
                continue;
            }

            TemplateContextBuilder::clearContextCache();
            FrontendTemplateRenderer::clearContextCache();

            $variantContext = array_merge($context, [
                'notifal_pool_variant_index' => $i,
            ]);

            $result = $templateRenderer->renderForFrontend($templateId, $variantContext, $contentSourceSettings);
            if (isset($result['no_matching_data']) && $result['no_matching_data'] === true) {
                continue;
            }

            $html = $result['html'] ?? '';
            if ($html === '') {
                continue;
            }

            $entry = [
                'template_content' => $html,
                'content' => $html,
                'template_assets' => $result['assets'] ?? [],
                'builder_type' => $result['builder_type'] ?? null,
                'cache_bust' => time() . '_' . uniqid('', true),
            ];
            if (!empty($result['deferred_featured_image_html'])) {
                $entry['deferred_featured_image_html'] = $result['deferred_featured_image_html'];
            }
            $variants[] = $entry;
        }

        return $variants;
    }

    /**
     * Get fully rendered template content with styles using content source settings.
     *
     * @param array $notificationData Notification data
     * @param array $context Current page context
     * @return array|null Rendered template data or null if no matching data found
     * @since 2.0.0
     */
    private function getRenderedTemplateContent(array $notificationData, array $context): ?array
    {
        $templateId = $notificationData['template_id'] ?? 0;

        if (!$templateId) {
            // Return empty structure when no template is configured
            return [
                'html' => '',
                'assets' => [],
                'builder_type' => null,
                'note' => 'No template content available'
            ];
        }

        try {
            $templateRenderer = notifal_app(FrontendTemplateRenderer::class);

            $contentSourceSettings = $notificationData['content_source_settings'] ?? [];

            $result = $templateRenderer->renderForFrontend($templateId, $context, $contentSourceSettings);

            if (isset($result['no_matching_data']) && $result['no_matching_data'] === true) {
                return null;
            }

            return $result;

        } catch (\Exception $e) {
            // Fallback to raw template content if rendering fails
            $fallbackContent = $notificationData['template_content'] ?? '';

            return [
                'html' => $fallbackContent,
                'assets' => [],
                'builder_type' => null,
                'error' => $e->getMessage(),
                'fallback' => true
            ];
        }
    }


    /**
     * Prepare appearance settings for frontend.
     *
     * @param array $notificationData Notification data
     * @return array Appearance settings
     * @since 2.0.0
     */
    private function prepareAppearanceSettings(array $notificationData): array
    {
        $appearanceSettings = $notificationData['appearance_settings'] ?? [];

        // Use the dedicated service's generateFrontendConfig method
        return $this->appearanceService->generateFrontendConfig($appearanceSettings);
    }

    /**
     * Prepare behavior settings for frontend.
     *
     * @param array $notificationData Notification data
     * @return array Behavior settings
     * @since 2.0.0
     */
    private function prepareBehaviorSettings(array $notificationData): array
    {
        $behaviorSettings = $notificationData['behavior_settings'] ?? [];

        return $this->behaviorService->sanitizeSettings($behaviorSettings);
    }

    /**
     * Prepare timing settings for frontend.
     *
     * @param array $notificationData Notification data
     * @return array Timing settings
     * @since 2.0.0
     */
    private function prepareTimingSettings(array $notificationData): array
    {
        $timingSettings = $notificationData['timing_settings'] ?? [];

        // Use the dedicated service's generateFrontendConfig method
        return $this->timingService->generateFrontendConfig($timingSettings);
    }

    /**
     * Get notification priority score.
     *
     * @param array $notification Notification data
     * @return int Priority score
     * @since 2.0.0
     */
    private function getNotificationPriority(array $notification): int
    {
        $timingSettings = $notification['timing'] ?? [];
        $enablePriority = $timingSettings['enable_priority'] ?? false;

        if ($enablePriority) {
            // Use user-defined priority level (1-10, where 10 is highest)
            $userPriority = (int) ($timingSettings['priority_level'] ?? 5);
            // Convert to internal scale (multiply by 10 for better granularity)
            return $userPriority * 10;
        }

        // Automatic priority calculation based on notification characteristics
        $priority = 0;

        // Newer notifications get slight priority boost
        $priority += 10;

        // Notifications with action buttons are more important for user engagement
        if (!empty($notification['action_buttons'])) {
            $priority += 5;
        }

        // Modal notifications demand more attention than toast notifications
        if (($notification['display_type'] ?? '') === 'modal') {
            $priority += 3;
        }

        return $priority;
    }
}
