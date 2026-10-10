<?php
namespace Notifal\Modules\SetupWizard\Application\Services;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\OnPageNotification\Application\Services\API\PreCreatedNotificationsApiService;
use Notifal\Modules\OnPageNotification\Helpers\PreCreatedNotificationBuilderTypes;
defined('ABSPATH') || exit;
/**
 * Fetches marketplace recommendations for the Setup Wizard.
 *
 * @since 3.0.0
 * @updated 3.0.0 Map product-recommendation-quiz goal to marketplace suggestion vocabulary.
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardRecommendationService
{
    /**
     * Marketplace API service dependency.
     *
     * @var PreCreatedNotificationsApiService
     */
    private PreCreatedNotificationsApiService $apiService;
    /**
     * Create the recommendation service.
     *
     * @since 3.0.0
     * @param PreCreatedNotificationsApiService $apiService Marketplace API service.
     */
    public function __construct(PreCreatedNotificationsApiService $apiService)
    {
        // Store dependency used to query notifal.com marketplace templates.
        $this->apiService = $apiService;
    }
    /**
     * Fetch recommended marketplace notifications for one goal slug.
     *
     * @since 3.0.0
     * @param string $goalSlug Selected goal slug from HtmlBuilderUseCases.
     * @param string $industrySlug Optional detected industry slug.
     * @return array{success: bool, notifications?: array<int, array<string, mixed>>, error?: string}
     */
    public function getRecommendations(string $goalSlug, string $industrySlug = ''): array
    {
        // Map wizard goal slugs to marketplace use-case taxonomy slugs.
        $useCases = $this->mapGoalToUseCases(sanitize_key($goalSlug));
        // Build marketplace query args with builder availability filters.
        $args = [
            'page'                   => 1,
            'per_page'               => 6,
            'use_cases'              => $useCases,
            'html_builder_available' => '1',
            'elementor_available'    => PluginDetector::isElementorActive() ? '1' : '0',
        ];
        // Add industry filter when a slug was detected or confirmed.
        if ('' !== sanitize_key($industrySlug)) {
            $args['industries'] = [sanitize_key($industrySlug)];
        }
        // Query the marketplace notifications list endpoint.
        $response = $this->apiService->getNotifications($args);
        // Return API errors directly to the wizard UI.
        if (!empty($response['success']) && false === $response['success']) {
            return [
                'success' => false,
                'error'   => sanitize_text_field((string) ($response['error'] ?? __('Could not load recommendations.', 'notifal'))),
            ];
        }
        // Extract notification rows from the API envelope.
        $notifications = [];
        if (isset($response['data']['notifications']) && is_array($response['data']['notifications'])) {
            $notifications = $response['data']['notifications'];
        }
        // Normalize each notification card for the wizard UI.
        $cards = [];
        foreach ($notifications as $notification) {
            if (!is_array($notification)) {
                continue;
            }
            // Resolve the preferred import file type for this card.
            $fileType = $this->resolveImportFileType($notification);
            // Skip cards without a usable builder file on this site.
            if ('' === $fileType) {
                continue;
            }
            // Append a browser-safe card payload.
            $cards[] = [
                'id'          => absint($notification['id'] ?? 0),
                'title'       => sanitize_text_field((string) ($notification['title'] ?? '')),
                'description' => sanitize_textarea_field((string) ($notification['excerpt'] ?? ($notification['description'] ?? ''))),
                'thumbnail'   => esc_url_raw((string) ($notification['thumbnail'] ?? '')),
                'is_pro'      => !empty($notification['is_pro']),
                'file_type'   => $fileType,
            ];
        }
        // Return normalized recommendation cards.
        return [
            'success'       => true,
            'notifications' => $cards,
        ];
    }
    /**
     * Map wizard goal slugs to marketplace use-case taxonomy slugs.
     *
     * @since 3.0.0
     * @param string $goalSlug Goal slug from HtmlBuilderUseCases.
     * @return string[] Marketplace use-case slugs.
     */
    private function mapGoalToUseCases(string $goalSlug): array
    {
        // Map each wizard goal to one or more marketplace use-case slugs.
        $map = [
            'increase-sales'               => ['urgency', 'discount', 'seasonal_promo'],
            'grow-email-list'              => ['newsletter', 'lead_capture'],
            'lead-generation'              => ['lead_capture', 'welcome_offer'],
            'product-recommendation-quiz'  => ['product_recommendation_quiz'],
            'boost-engagement'             => ['announcement', 'social_proof'],
            'build-trust-and-social-proof' => ['social_proof', 'trust_badges'],
            'communicate-with-visitors'    => ['announcement', 'lead_capture'],
            'recover-or-retain-visitors'   => ['cart_recovery', 'urgency'],
            'promote-social-channels'      => ['announcement', 'social_proof'],
            'content-blocking'             => ['announcement'],
            'grow-aov'                     => ['discount', 'urgency'],
            'prevent-cart-abandonment'     => ['cart_recovery'],
        ];
        // Return mapped slugs or a generic fallback set.
        return $map[$goalSlug] ?? ['social_proof', 'lead_capture', 'announcement'];
    }
    /**
     * Resolve the best import file type supported on this site.
     *
     * @since 3.0.0
     * @param array<string, mixed> $notification Marketplace notification row.
     * @return string Import file type slug or empty string.
     */
    private function resolveImportFileType(array $notification): string
    {
        // Read builder availability flags from marketplace list responses.
        $files = is_array($notification['files'] ?? null) ? $notification['files'] : [];
        // Prefer HTML Builder when available on this site and in the template package.
        if (!empty($files['html_builder']) || !empty($files['html-builder'])) {
            return PreCreatedNotificationBuilderTypes::HTML_BUILDER;
        }
        // Use Elementor imports only when Elementor is active locally.
        if (PluginDetector::isElementorActive() && (!empty($files['elementor']) || !empty($files['json']))) {
            return PreCreatedNotificationBuilderTypes::ELEMENTOR;
        }
        // No compatible builder file exists for this installation.
        return '';
    }
    
}
