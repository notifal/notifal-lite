<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Core;

use Notifal\Core\Support\Helpers\UrlHelper;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Shared\Utils\Helper;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\UrlService;
use Notifal\Modules\OnPageNotification\Application\Services\Core\NotificationActivationGuard;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\AppearanceSettingsService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\BehaviorSettingsService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\GeneralSettingsService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\TimingSettingsService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\ContentSourceService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\DisplayRulesService;
use Notifal\Modules\OnPageNotification\Application\Traits\SettingsServiceTrait;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NotificationSaveService
 *
 * Handles saving on-page notification settings with comprehensive validation,
 * sanitization, and error handling. Supports both AJAX and form submissions.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Application\Services
 */
class NotificationSaveService
{
    use SettingsServiceTrait;
    /**
     * @var GeneralSettingsService
     */
    private $generalService;

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
     * @var ContentSourceService
     */
    private $contentSourceService;


    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->generalService = notifal_app(GeneralSettingsService::class);
        $this->appearanceService = notifal_app(AppearanceSettingsService::class);
        $this->behaviorService = notifal_app(BehaviorSettingsService::class);
        $this->timingService = notifal_app(TimingSettingsService::class);
        $this->contentSourceService = notifal_app(ContentSourceService::class);
    }


    /**
     * Save notification settings
     *
     * @since 2.0.0
     * @param array $data Raw form data
     * @param int|null $notificationId Existing notification ID or null for new
     * @return array Save result with success status and message
     */
    public function saveNotification(array $data, ?int $notificationId = null): array
    {
        try {
            // Validate nonce
            if (!$this->validateNonce($data)) {
                return $this->createErrorResponse(__('Security check failed. Please refresh the page and try again.', 'notifal'));
            }

            // Validate basic required fields
            $validationResult = $this->validateBasicFields($data);
            if (!$validationResult['valid']) {
                return $this->createErrorResponse($validationResult['message']);
            }

            $proValidation = $this->validateProFeatures($data);
            if (!$proValidation['valid']) {
                return $this->createErrorResponse($proValidation['message']);
            }

            // Sanitize and validate all settings
            $sanitizedData = $this->sanitizeAllSettings($data);


            if ($sanitizedData['notif_enabled'] && !NotificationActivationGuard::canActivateNotification($notificationId)) {
                return $this->createErrorResponse(__('You can only have one active notification in the free version. Upgrade to Notifal Pro to activate multiple notifications simultaneously.', 'notifal'));
            }

            // Prepare post data
            $postData = $this->preparePostData($sanitizedData, $notificationId);

            // Save to database
            $postId = $this->saveToDatabase($postData);

            if (!$postId) {
                return $this->createErrorResponse(__('Failed to save notification. Please try again.', 'notifal'));
            }

            // Save meta data
            $this->saveMetaData($postId, $sanitizedData);


            // Fire action hooks
            do_action(ActionHooks::ONPAGE_NOTIFICATION_SAVED, $postId, $sanitizedData);

            return $this->createSuccessResponse(
                $notificationId ? __('Notification updated successfully.', 'notifal') : __('Notification created successfully.', 'notifal'),
                $postId
            );

        } catch (\Exception $e) {
            return $this->createErrorResponse(__('An unexpected error occurred. Please try again.', 'notifal'));
        }
    }

    /**
     * Validate nonce
     *
     * @since 2.0.0
     * @param array $data Form data
     * @return bool Validation result
     */
    private function validateNonce(array $data): bool
    {
        $nonce = $data['nonce'] ?? '';
        return wp_verify_nonce($nonce, 'notifal_save_notification');
    }

    /**
     * Validate basic required fields
     *
     * @since 2.0.0
     * @param array $data Form data
     * @return array Validation result
     */
    private function validateBasicFields(array $data): array
    {
        $title = Helper::sanitizeInput($data['notif_title'] ?? '', 'text');
        
        if (empty($title)) {
            return [
                'valid' => false,
                'message' => __('Notification title is required.', 'notifal')
            ];
        }

        if (strlen($title) > 255) {
            return [
                'valid' => false,
                'message' => __('Notification title must be less than 255 characters.', 'notifal')
            ];
        }

        // Validate that at least one label is selected
        $labels = $data['notifal_labels'] ?? [];
        if (empty($labels) || !is_array($labels)) {
            return [
                'valid' => false,
                'message' => __('At least one notification label must be selected.', 'notifal')
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate Pro features usage without license.
     *
     * @since 2.0.0
     * @param array $data Form data
     * @return array Validation result
     */
    private function validateProFeatures(array $data): array
    {
        if (apply_filters('notifal_pro_multiple_display_rules_allowed', false)) {
            return ['valid' => true];
        }

        // Check display rules for Pro features
        $displayRulesData = $this->parseJsonField($data['display_rules_data'] ?? '{}');
        $combinationLogic = Helper::sanitizeInput($data['rule_combination_logic'] ?? 'OR', 'text');

        // Validate display rules using the service
        $validationErrors = DisplayRulesService::validateRules($displayRulesData, $combinationLogic);
        
        if (!empty($validationErrors)) {
            return [
                'valid' => false,
                'message' => implode(' ', $validationErrors)
            ];
        }

        $proFeatureErrors = [];

        $timingData = $this->parseJsonField($data['timing_settings'] ?? '{}');
        if (!empty($timingData)) {
            $proTimingFeatures = ['on_exit_intent', 'on_scroll_percentage', 'on_time_on_page', 'on_page_views'];
            foreach ($proTimingFeatures as $feature) {
                if (isset($timingData[$feature]) && $timingData[$feature]) {
                    $proFeatureErrors[] = sprintf(__('Timing feature "%s" requires Notifal Pro.', 'notifal'), $feature);
                }
            }

            $showTiming = Helper::sanitizeInput($data['show_timing'] ?? '', 'text');
            $proShowTimingOptions = ['scroll', 'exit', 'idle', 'custom'];
            if (in_array($showTiming, $proShowTimingOptions)) {
                $optionLabels = [
                    'scroll' => __('On Scroll', 'notifal'),
                    'exit' => __('On Exit Intent', 'notifal'),
                    'idle' => __('After User Idle', 'notifal'),
                    'custom' => __('Custom Trigger', 'notifal')
                ];
                $proFeatureErrors[] = sprintf(__('Show Timing option "%s" requires Notifal Pro.', 'notifal'), $optionLabels[$showTiming]);
            }
        }

        // Check behavior settings for Pro features  
        $behaviorData = $this->parseJsonField($data['behavior_settings'] ?? '{}');
        if (!empty($behaviorData)) {
            $proBehaviorFeatures = ['swipe_to_dismiss', 'user_interaction_all'];
            foreach ($proBehaviorFeatures as $feature) {
                if (isset($behaviorData[$feature]) && $behaviorData[$feature]) {
                    $proFeatureErrors[] = sprintf(__('Behavior feature "%s" requires Notifal Pro.', 'notifal'), $feature);
                }
            }
        }

        $appearanceData = $this->parseJsonField($data['appearance_settings'] ?? '{}');
        if (!empty($appearanceData)) {
            if (!empty($appearanceData['custom_css'])) {
                $proFeatureErrors[] = __('Custom CSS requires Notifal Pro.', 'notifal');
            }
        }

        $contentSourceData = $this->parseJsonField($data['content_source_settings'] ?? '{}');
        if (!empty($contentSourceData)) {
            if (!empty($contentSourceData['multi_filter']) || !empty($contentSourceData['custom_meta_fields'])) {
                $proFeatureErrors[] = __('Advanced content source features require Notifal Pro.', 'notifal');
            }
        }

        if (!empty($proFeatureErrors)) {
            return [
                'valid' => false,
                'message' => __('Pro features detected: ', 'notifal') . implode(' ', $proFeatureErrors) . ' ' . 
                            __('Please upgrade to Notifal Pro or remove these features.', 'notifal')
            ];
        }

        return ['valid' => true];
    }

    /**
     * Sanitize all settings
     *
     * @since 2.0.0
     * @param array $data Raw form data
     * @return array Sanitized data
     */
    private function sanitizeAllSettings(array $data): array
    {
        $sanitized = [];

        // General settings
        $generalSettings = $this->generalService->sanitizeSettings([
            'notif_title' => $data['notif_title'] ?? '',
            'notif_enabled' => $data['notif_enabled'] ?? false,
            'content_source_type' => $data['content_source_type'] ?? 'dynamic',
            'notifal_labels' => $data['notifal_labels'] ?? [],
        ]);

        $sanitized = array_merge($sanitized, $generalSettings);

        // Appearance settings
        $appearanceData = $this->parseJsonField($data['appearance_settings'] ?? '{}');
        $sanitized['appearance_settings'] = $this->appearanceService->sanitizeSettings($appearanceData);

        // Behavior settings
        $behaviorData = $this->parseJsonField($data['behavior_settings'] ?? '{}');
        $sanitized['behavior_settings'] = $this->behaviorService->sanitizeSettings($behaviorData);

        // Timing settings
        $timingData = $this->parseJsonField($data['timing_settings'] ?? '{}');
        $sanitized['timing_settings'] = $this->timingService->sanitizeSettings($timingData);

        // Content source settings
        $contentSourceData = $this->parseJsonField($data['content_source_settings'] ?? '{}');
        $sanitized['content_source_settings'] = $this->contentSourceService->sanitizeSettings($contentSourceData);

        // Display rules settings
        $displayRulesData = $this->parseJsonField($data['display_rules_data'] ?? '{}');
        $sanitized['display_rules_data'] = DisplayRulesService::sanitizeSettings($displayRulesData);
        $sanitized['rule_combination_logic'] = Helper::sanitizeInput($data['rule_combination_logic'] ?? 'OR', 'text');

        // Template settings
        $sanitized['template_id'] = absint($data['template_id'] ?? 0);
        $sanitized['template_content'] = wp_kses_post($data['template_content'] ?? '');

        return apply_filters(FilterHooks::ONPAGE_NOTIFICATION_SANITIZED_DATA, $sanitized, $data);
    }


    /**
     * Parse JSON field safely
     *
     * Handles double-escaped JSON from JavaScript submissions.
     *
     * @since 2.0.0
     * @param string $json JSON string
     * @return array Parsed data or empty array
     */
    private function parseJsonField(string $json): array
    {
        if (empty($json)) {
            return [];
        }

        // Handle double-escaped JSON from JavaScript
        $cleanedJson = stripslashes($json);

        // Try parsing the cleaned JSON first
        if ($this->isValidJSON($cleanedJson)) {
            $data = json_decode($cleanedJson, true);
            return is_array($data) ? $data : [];
        }

        // Try parsing the original string as a fallback
        if ($this->isValidJSON($json)) {
            $data = json_decode($json, true);
            return is_array($data) ? $data : [];
        }

        return [];
    }

    /**
     * Prepare post data for database
     * CRITICAL: When notif_enabled=1, post_status must be 'publish' (synchronized)
     *
     * @since 2.0.0
     * @param array $sanitizedData Sanitized data
     * @param int|null $notificationId Existing notification ID
     * @return array Post data
     */
    private function preparePostData(array $sanitizedData, ?int $notificationId): array
    {
        // When _notifal_notif_enabled=1, post must be published
        // When _notifal_notif_enabled=0, post must be draft
        $postData = [
            'post_title' => $sanitizedData['notif_title'],
            'post_type' => 'notifal_onpage_notif',
            'post_status' => $sanitizedData['notif_enabled'] ? 'publish' : 'draft',
        ];

        if ($notificationId) {
            $postData['ID'] = $notificationId;
        }

        return apply_filters(FilterHooks::ONPAGE_NOTIFICATION_POST_DATA, $postData, $sanitizedData);
    }

    /**
     * Save to database
     *
     * @since 2.0.0
     * @param array $postData Post data
     * @return int|false Post ID or false on failure
     */
    private function saveToDatabase(array $postData)
    {
        $postId = wp_insert_post($postData, true);

        if (is_wp_error($postId)) {
            return false;
        }

        return $postId;
    }

    /**
     * Save meta data for the notification
     * CRITICAL: _notifal_notif_enabled is the source of truth for activation status
     *
     * @since 2.0.0
     * @param int $postId Post ID
     * @param array $sanitizedData Sanitized data
     * @return void
     */
    private function saveMetaData(int $postId, array $sanitizedData): void
    {
        // This meta key determines if notification is active, not post_status
        update_post_meta($postId, '_notifal_notif_enabled', $sanitizedData['notif_enabled'] ? '1' : '0');

        // Save appearance settings
        update_post_meta($postId, '_notifal_appearance_settings', $sanitizedData['appearance_settings']);

        // Save behavior settings
        update_post_meta($postId, '_notifal_behavior_settings', $sanitizedData['behavior_settings']);

        // Save timing settings
        update_post_meta($postId, '_notifal_timing_settings', $sanitizedData['timing_settings']);

        // Save content source settings
        update_post_meta($postId, '_notifal_content_source_settings', $sanitizedData['content_source_settings']);

        // Save display rules settings
        update_post_meta($postId, '_notifal_display_rules_data', $sanitizedData['display_rules_data']);
        update_post_meta($postId, '_notifal_rule_combination_logic', $sanitizedData['rule_combination_logic']);

        // Save template settings
        update_post_meta($postId, '_notifal_template_id', $sanitizedData['template_id']);
        update_post_meta($postId, '_notifal_template_content', $sanitizedData['template_content']);

        // Save content source type
        update_post_meta($postId, '_notifal_content_source_type', $sanitizedData['content_source_type']);

        // Save labels
        if (!empty($sanitizedData['notifal_labels'])) {
            $result = wp_set_object_terms($postId, $sanitizedData['notifal_labels'], 'notifal_label');
            if (is_wp_error($result)) {
                // Continue even if label saving fails - don't fail the entire save
            }
        } else {
            wp_delete_object_term_relationships($postId, 'notifal_label');
        }

        do_action(ActionHooks::ONPAGE_NOTIFICATION_META_SAVED, $postId, $sanitizedData);
    }

    /**
     * Create success response
     *
     * @since 2.0.0
     * @param string $message Success message
     * @param int $postId Post ID
     * @return array Response array
     */
    private function createSuccessResponse(string $message, int $postId): array
    {
        return [
            'success' => true,
            'message' => $message,
            'post_id' => $postId,
            'redirect_url' => notifal_app(UrlService::class)->getEditNotificationUrl($postId)
        ];
    }

    /**
     * Create error response
     *
     * @since 2.0.0
     * @param string $message Error message
     * @return array Response array
     */
    private function createErrorResponse(string $message): array
    {
        return [
            'success' => false,
            'message' => $message
        ];
    }

    /**
     * Check if pro features are allowed
     *
     * @return bool True if pro features are allowed
     * @since 2.0.0
     */
    private function isProFeatureAllowed(): bool
    {
        // Use secure hook that only the legitimate pro plugin can provide
        return apply_filters('notifal_pro_multiple_display_rules_allowed', false);
    }

    /**
     * Get notification data for editing
     *
     * @since 2.0.0
     * @param int $notificationId Notification ID
     * @return array|null Notification data or null if not found
     */
    public function getNotificationData(int $notificationId): ?array
    {
        $post = get_post($notificationId);
        
        if (!$post || $post->post_type !== 'notifal_onpage_notif') {
            return null;
        }

        $enabledMeta = get_post_meta($post->ID, '_notifal_notif_enabled', true);
        
        // If meta key doesn't exist, infer from post_status for backward compatibility
        // But this should only happen for old notifications created before this fix
        $notifEnabled = ($enabledMeta !== '') ? ($enabledMeta === '1') : ($post->post_status === 'publish');
        
        $rawDisplayRules = get_post_meta($post->ID, '_notifal_display_rules_data', true);

        // Ensure we always work with an array. Older notifications may have
        // stored this meta as a serialized string, so we need to safely
        // attempt unserialization before normalizing.
        $rulesArray = [];

        if (is_array($rawDisplayRules)) {
            $rulesArray = $rawDisplayRules;
        } elseif (is_string($rawDisplayRules) && $rawDisplayRules !== '') {
            $maybeUnserialized = maybe_unserialize($rawDisplayRules);
            if (is_array($maybeUnserialized)) {
                $rulesArray = $maybeUnserialized;
            }
        }

        $normalizedDisplayRules = $this->normalizeDisplayRulesDataForEdit($rulesArray);

        $data = [
            'post_id' => $post->ID,
            'post_title' => $post->post_title,
            'post_status' => $post->post_status,
            'notif_enabled' => $notifEnabled,
            'notif_title' => $post->post_title,
            'content_source_type' => get_post_meta($post->ID, '_notifal_content_source_type', true) ?: 'dynamic',
            'appearance_settings' => get_post_meta($post->ID, '_notifal_appearance_settings', true) ?: [],
            'behavior_settings' => get_post_meta($post->ID, '_notifal_behavior_settings', true) ?: [],
            'timing_settings' => get_post_meta($post->ID, '_notifal_timing_settings', true) ?: [],
            'content_source_settings' => get_post_meta($post->ID, '_notifal_content_source_settings', true) ?: [],
            'display_rules_data' => $normalizedDisplayRules,
            'rule_combination_logic' => get_post_meta($post->ID, '_notifal_rule_combination_logic', true) ?: 'OR',
            'template_id' => get_post_meta($post->ID, '_notifal_template_id', true) ?: 0,
            'template_content' => get_post_meta($post->ID, '_notifal_template_content', true) ?: '',
        ];

        // Get labels
        $labels = wp_get_object_terms($post->ID, 'notifal_label', ['fields' => 'slugs']);
        $data['notifal_labels'] = is_array($labels) ? $labels : [];

        return apply_filters(FilterHooks::ONPAGE_NOTIFICATION_LOADED_DATA, $data, $post);
    }

    /**
     * Normalize display rules data for admin editing.
     *
     * Lightweight normalization so the admin UI receives the original rules
     * structure while handling a few legacy container shapes. Detailed
     * sanitization and validation are delegated to DisplayRulesService when
     * saving or evaluating rules.
     *
     * @since 2.0.0
     * @param array $rawRules Raw rules data loaded from post meta.
     * @return array Normalized rules data suitable for the edit UI.
     */
    private function normalizeDisplayRulesDataForEdit(array $rawRules): array
    {
        if (empty($rawRules)) {
            return [];
        }

        // Unwrap common container formats such as ['rules' => [ ... ]].
        // Older saves and some import/duplicate flows may store rules under
        // a top-level "rules" key instead of directly by rule type.
        if (isset($rawRules['rules']) && is_array($rawRules['rules'])) {
            $rawRules = $rawRules['rules'];
        }

        // Return the structure as-is; JavaScript loader and DisplayRulesService
        // are responsible for handling edge cases (numeric keys, unsupported
        // types, empty values, etc.).
        return $rawRules;
    }
} 
