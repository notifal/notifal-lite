<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Core;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Traits\NotificationDataTrait;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\AppearanceSettingsService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\BehaviorSettingsService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\TimingSettingsService;
use Notifal\Modules\OnPageNotification\Application\Services\Template\FrontendTemplateRenderer;
use Notifal\Modules\OnPageNotification\Application\Support\ContentSourceRequestContext;
use Notifal\Modules\OnPageNotification\Application\Support\PageContextHelper;

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
     * @since 2.3.5 Exposes `allow_duplicate_source` for frontend duplicate-source control.
     * @since 2.3.7 Exposes `smart_targeting_enabled` and `smart_targeting_category_level` for frontend retrigger guardrails.
     */
    public function prepareForFrontend(\WP_Post $notification, array $context): ?array
    {
        ContentSourceRequestContext::resetLastSelectedPoolEntityId();

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

        // Build retrigger variants before first paint so seen-source tracking does not shrink the pool.
        $retriggerVariantsBuilder = notifal_app(RetriggerPoolVariantsBuilder::class);
        $retriggerBuild = $retriggerVariantsBuilder->build($notification, $notificationData, $context);

        $renderContext = $context;
        if (isset($retriggerBuild['primary_entity_id']) && $retriggerBuild['primary_entity_id'] !== null) {
            $renderContext['notifal_pool_entity_id'] = (int) $retriggerBuild['primary_entity_id'];
        }

        $renderedContent = $this->getRenderedTemplateContent($notificationData, $renderContext);

        if ($renderedContent === null) {
            return null;
        }

        // Capture the entity used for the first paint after the pinned pool render completes.
        $firstPaintEntityId = ContentSourceRequestContext::getLastSelectedPoolEntityId();

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

        // Prepare timing once so legacy top-level fields stay aligned with timing settings.
        $timingSettings = $this->prepareTimingSettings($notificationData);
        $showTiming = isset($timingSettings['show_timing']) ? (string) $timingSettings['show_timing'] : 'immediate';
        $legacyDelaySeconds = ($showTiming === 'delay')
            ? (int) ($timingSettings['delay_seconds'] ?? 3)
            : 0;
        $legacyScrollPercentage = ($showTiming === 'scroll')
            ? (int) ($timingSettings['scroll_percentage'] ?? 50)
            : 0;

        $frontendData = [
            'id' => $notification->ID,
            'title' => $notificationData['notif_title'] ?? $notification->post_title,
            'template_content' => $renderedContent['html'] ?? ($notificationData['template_content'] ?? ''),
            'content' => $renderedContent['html'] ?? ($notificationData['template_content'] ?? ''), // Backward compatibility
            'allow_duplicate_source' => !empty($notificationData['content_source_settings']['allow_duplicate_source']),
            // Expose smart targeting settings for frontend retrigger guardrails.
            'smart_targeting_enabled' => !empty($notificationData['content_source_settings']['smart_targeting_enabled']),
            'smart_targeting_category_level' => PageContextHelper::getSmartTargetingCategoryLevel(
                is_array($notificationData['content_source_settings'] ?? null)
                    ? $notificationData['content_source_settings']
                    : []
            ),
            'is_active' => (get_post_meta($notification->ID, '_notifal_notif_enabled', true) === '1'),
            'cache_bust' => time() . '_' . uniqid(),
            'display_type' => $notificationData['appearance_settings']['notification_display_type'] ?? 'toast',
            'trigger_type' => $showTiming,
            'delay_seconds' => $legacyDelaySeconds,
            'scroll_percentage' => $legacyScrollPercentage,
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
            'timing' => $timingSettings,
            'template_assets' => $renderedContent['assets'] ?? [],
            'builder_type' => $renderedContent['builder_type'] ?? null,
        ];

        // For Elementor + immediate show: pass deferred featured image HTML for frontend to inject after delay
        if (!empty($renderedContent['deferred_featured_image_html'])) {
            $frontendData['deferred_featured_image_html'] = $renderedContent['deferred_featured_image_html'];
        }

        // Add calculated priority score for frontend conflict resolution
        $frontendData['priority'] = $this->getNotificationPriority($frontendData);

        // Client-side retrigger variants (pool-based dynamic content). @since 2.3.5
        $retriggerVariants = $retriggerBuild['variants'] ?? [];
        if (!empty($retriggerVariants)) {
            $frontendData['retrigger_variants'] = $retriggerVariants;
        }
        if (isset($retriggerBuild['primary_entity_id']) && $retriggerBuild['primary_entity_id'] !== null) {
            $frontendData['retrigger_primary_entity_id'] = (int) $retriggerBuild['primary_entity_id'];
        }
        if (isset($retriggerBuild['primary_pool_index']) && $retriggerBuild['primary_pool_index'] !== null) {
            $frontendData['retrigger_primary_pool_index'] = (int) $retriggerBuild['primary_pool_index'];
        }

        $selectedPoolEntityId = ContentSourceRequestContext::getLastSelectedPoolEntityId();

        // First paint tracking must reflect the rendered entity, not the retrigger pool index alone.
        if ($firstPaintEntityId > 0) {
            $frontendData['pool_entity_id'] = $firstPaintEntityId;
        } elseif (PageContextHelper::isSingularContext($context) && isset($retriggerBuild['primary_entity_id']) && $retriggerBuild['primary_entity_id'] !== null) {
            $frontendData['pool_entity_id'] = (int) $retriggerBuild['primary_entity_id'];
        } elseif ($selectedPoolEntityId > 0) {
            $frontendData['pool_entity_id'] = $selectedPoolEntityId;
        } elseif (PageContextHelper::isSingularContext($context)) {
            $singularPageId = (int) ($context['page_id'] ?? 0);
            if ($singularPageId > 0) {
                $frontendData['pool_entity_id'] = $singularPageId;
            }
        }

        // @since 2.3.5 Visit-history user rules evaluated client-side (cache-safe).
        $clientUserRules = ClientUserRulesBuilder::buildFromNotificationId((int) $notification->ID);
        if (!empty($clientUserRules)) {
            $frontendData['client_user_rules'] = $clientUserRules;
        }

        // @since 2.3.5 WooCommerce cart rules evaluated client-side when cart changes.
        $clientCartRules = ClientCartRulesBuilder::buildFromNotificationId((int) $notification->ID);
        if (!empty($clientCartRules)) {
            $frontendData['client_cart_rules'] = $clientCartRules;
        }

        return $frontendData;
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

            // Elementor may serve cached widget HTML during page-load rendering; flag immediate
            // notifications so the renderer can attach deferred featured image HTML for the frontend swap.
            $storedTimingSettings = $notificationData['timing_settings'] ?? [];
            $sanitizedTimingSettings = $this->timingService->sanitizeSettings(
                is_array($storedTimingSettings) ? $storedTimingSettings : []
            );
            if (($sanitizedTimingSettings['show_timing'] ?? '') === 'immediate') {
                $context['for_immediate_display'] = true;
            }

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
