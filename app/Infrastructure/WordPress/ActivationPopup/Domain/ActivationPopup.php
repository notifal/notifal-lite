<?php
/**
 * Activation Popup Domain Logic
 *
 * Handles the core business logic for activation popup functionality.
 *
 * @package Notifal\Infrastructure\WordPress\ActivationPopup\Domain
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\ActivationPopup\Domain;

use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\UrlService;

defined('ABSPATH') || exit;

/**
 * Class ActivationPopup
 */
class ActivationPopup
{
    /**
     * Option key for tracking if activation popup has been shown
     */
    const ACTIVATION_POPUP_SHOWN_KEY = 'notifal_activation_popup_shown';

    /**
     * Option key for tracking activation timestamp
     */
    const ACTIVATION_TIME_KEY = 'notifal_activation_time';

    /**
     * Legacy option key from versions below 2.0.0
     */
    const LEGACY_PLUGIN_VERSION_KEY = 'notifal_plugin_version';

    /**
     * Check if activation popup should be shown
     *
     * Shows popup only for:
     * - Fresh installations (no previous plugin data)
     * Does NOT show for:
     * - Users upgrading from versions below 2.0.0 (detected by LEGACY_PLUGIN_VERSION_KEY)
     * - Users who have already seen the activation popup
     *
     * @return bool True if popup should be shown
     * @since 2.0.0
     */
    public function shouldShowActivationPopup(): bool
    {
        // Don't show if popup has already been shown
        if (get_option(self::ACTIVATION_POPUP_SHOWN_KEY, false)) {
            return false;
        }

        // Don't show for users upgrading from versions below 2.0.0
        if (get_option(self::LEGACY_PLUGIN_VERSION_KEY)) {
            return false;
        }

        // Show for fresh installations only
        return true;
    }

    /**
     * Mark activation popup as shown
     *
     * @return void
     * @since 2.0.0
     */
    public function markActivationPopupAsShown(): void
    {
        update_option(self::ACTIVATION_POPUP_SHOWN_KEY, true);
        update_option(self::ACTIVATION_TIME_KEY, time());
    }

    /**
     * Get activation timestamp
     *
     * @return int|null Unix timestamp of activation
     * @since 2.0.0
     */
    public function getActivationTime(): ?int
    {
        $time = get_option(self::ACTIVATION_TIME_KEY);
        return $time ? (int) $time : null;
    }

    /**
     * Get notification list page URL
     *
     * @return string URL to the notification list page
     * @since 2.0.0
     */
    public function getNotificationListUrl(): string
    {
        $urlService = notifal_app(UrlService::class);
        return $urlService->getListUrl();
    }

    /**
     * Get onboarding URL
     *
     * @return string URL to onboarding course
     * @since 2.0.0
     */
    public function getOnboardingUrl(): string
    {
        return Urls::getOnboardingUrl();
    }

    /**
     * Get tutorials/documentation URL
     *
     * @return string URL to tutorials
     * @since 2.0.0
     */
    public function getTutorialsUrl(): string
    {
        return Urls::getKnowledgeBaseUrl();
    }

    /**
     * Get community URL
     *
     * @return string URL to community
     * @since 2.0.0
     */
    public function getCommunityUrl(): string
    {
        return Urls::getCommunityUrl();
    }

    /**
     * Get license manager URL
     *
     * @return string URL to license manager
     * @since 2.0.0
     */
    public function getLicenseManagerUrl(): string
    {
        return Urls::LICENSE_MANAGER;
    }

    /**
     * Get welcome message for activation popup
     *
     * @return string Welcome message
     * @since 2.0.0
     */
    public function getWelcomeMessage(): string
    {
        return __('Welcome to Notifal!', 'notifal');
    }

    /**
     * Get welcome description for activation popup
     *
     * @return string Welcome description
     * @since 2.0.0
     */
    public function getWelcomeDescription(): string
    {
        return __("Let's get you started with powerful notification management. Choose how you'd like to begin your journey.", 'notifal');
    }

    /**
     * Get action buttons configuration
     *
     * @return array Action buttons configuration
     * @since 2.0.0
     */
    public function getActionButtons(): array
    {
        return [
            [
                'id' => 'start-onboarding',
                'text' => __('Start Onboarding', 'notifal'),
                'url' => $this->getOnboardingUrl(),
                'icon' => 'notifal-icon-rocket',
                'primary' => true,
            ],
            [
                'id' => 'upgrade-to-pro',
                'text' => __('Upgrade to Pro for Free', 'notifal'),
                'url' => $this->getLicenseManagerUrl(),
                'icon' => 'notifal-icon-crown',
                'external' => true,
            ],
            [
                'id' => 'join-community',
                'text' => __('Join Community', 'notifal'),
                'url' => $this->getCommunityUrl(),
                'icon' => 'notifal-icon-chat-left-dots',
                'external' => true,
            ],
        ];
    }
}
