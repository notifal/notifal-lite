<?php
/**
 * Deactivation Popup Domain Logic
 *
 * Handles the core business logic for deactivation popup functionality.
 *
 * @package Notifal\Infrastructure\WordPress\DeactivationPopup\Domain
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\DeactivationPopup\Domain;

defined('ABSPATH') || exit;

use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;

/**
 * Class DeactivationPopup
 *
 * Handles deactivation popup business logic including feedback collection,
 * deactivation tracking, and prevention rules.
 */
class DeactivationPopup
{
    /**
     * Check if Notifal Pro is active
     *
     * @return bool True if Notifal Pro is active
     * @since 2.0.0
     */
    public function isNotifalProActive(): bool
    {
        return PluginDetector::isNotifalProActive();
    }

    /**
     * Get site information for API submission
     *
     * @return array Site information array
     * @since 2.0.0
     */
    public function getSiteInformation(): array
    {
        $site_url = get_site_url();
        $host = parse_url($site_url, PHP_URL_HOST);

        // For local development, include the path to identify the specific installation
        $domain = $host;
        if ($host === 'localhost' || $host === '127.0.0.1') {
            $path = parse_url($site_url, PHP_URL_PATH);
            if ($path && $path !== '/') {
                $domain = $host . $path;
            }
        }

        return [
            'domain' => $domain,
            'admin_email' => get_option('admin_email'),
            'plugin_version' => NOTIFAL_VERSION,
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
        ];
    }

    /**
     * Get deactivation count for this site
     *
     * @return int Number of previous deactivations
     * @since 2.0.0
     */
    public function getDeactivationCount(): int
    {
        $count = get_option('notifal_deactivation_count', 0);
        return (int) $count;
    }

    /**
     * Increment deactivation count
     *
     * @return void
     * @since 2.0.0
     */
    public function incrementDeactivationCount(): void
    {
        $count = $this->getDeactivationCount();
        update_option('notifal_deactivation_count', $count + 1);
    }

    /**
     * Get last deactivation timestamp
     *
     * @return int|null Unix timestamp of last deactivation
     * @since 2.0.0
     */
    public function getLastDeactivationTime(): ?int
    {
        $time = get_option('notifal_last_deactivation_time');
        return $time ? (int) $time : null;
    }

    /**
     * Update last deactivation timestamp
     *
     * @return void
     * @since 2.0.0
     */
    public function updateLastDeactivationTime(): void
    {
        update_option('notifal_last_deactivation_time', time());
    }

    /**
     * Get first deactivation timestamp
     *
     * @return int|null Unix timestamp of first deactivation
     * @since 2.0.0
     */
    public function getFirstDeactivationTime(): ?int
    {
        $time = get_option('notifal_first_deactivation_time');
        return $time ? (int) $time : null;
    }

    /**
     * Set first deactivation timestamp if not already set
     *
     * @return void
     * @since 2.0.0
     */
    public function setFirstDeactivationTimeIfNotSet(): void
    {
        if (!$this->getFirstDeactivationTime()) {
            update_option('notifal_first_deactivation_time', time());
        }
    }

  

    /**
     * Process deactivation feedback
     *
     * @param array $feedback_data Raw feedback data
     * @return DeactivationData Processed deactivation data
     * @since 2.0.0
     */
    public function processFeedback(array $feedback_data): DeactivationData
    {
        // Merge with site information
        $site_info = $this->getSiteInformation();
        $data = array_merge($site_info, $feedback_data);

        // Add deactivation tracking data
        $data['deactivation_count'] = $this->getDeactivationCount();
        $data['first_deactivation_time'] = $this->getFirstDeactivationTime();
        $data['last_deactivation_time'] = $this->getLastDeactivationTime();

        // Create and validate DeactivationData object
        $deactivation_data = new DeactivationData($data);
        $validation_errors = $deactivation_data->validate();

        if (!empty($validation_errors)) {
            $sanitized_errors = array_map('sanitize_text_field', $validation_errors);
            throw new \InvalidArgumentException(
                __('Invalid deactivation feedback data: ', 'notifal') . implode(', ', $sanitized_errors)
            );
        }

        return $deactivation_data;
    }

    /**
     * Check if deactivation should be prevented
     *
     * @param DeactivationData $data Deactivation data
     * @return bool True if deactivation should be prevented
     * @since 2.0.0
     */
    public function shouldPreventDeactivation(DeactivationData $data): bool
    {
        return $data->shouldPreventDeactivation() || $this->isNotifalProActive();
    }

    /**
     * Get prevention message for Pro users
     *
     * @return string Prevention message
     * @since 2.0.0
     */
    public function getProPreventionMessage(): string
    {
        return __("Wait! Don't deactivate Notifal. You have to activate both Notifal and Notifal Pro in order for the plugin to work.", 'notifal');
    }

    /**
     * Get help message for "couldn't get to work" reason
     *
     * @return string Help message
     * @since 2.0.0
     */
    public function getCouldNotWorkHelpMessage(): string
    {
        $knowledge_base_url = Urls::withPluginUtm(Urls::KNOWLEDGE_BASE, 'wordpress_plugin', 'deactivation_help');
        $support_url = Urls::withPluginUtm(Urls::SUPPORT_PAGE, 'wordpress_plugin', 'deactivation_help');

        return sprintf(
            __('We have many documents and onboarding resources on %s. Also, our team can set up notifications on your site for free—just open a support ticket on %s.', 'notifal'),
            '<a href="' . esc_url($knowledge_base_url) . '" target="_blank">notifal.com/knowledge-base/</a>',
            '<a href="' . esc_url($support_url) . '" target="_blank">notifal.com/my-account/support/</a>'
        );
    }

    /**
     * Schedule delayed API call for skip actions using transient
     *
     * @param DeactivationData $data Deactivation data
     * @return void
     * @since 2.0.0
     */
    public function scheduleDelayedApiCall(DeactivationData $data): void
    {
        // Store deactivation data in transient for 2 minutes
        $transient_key = 'notifal_deactivation_skip_' . time() . '_' . wp_rand();
        set_transient($transient_key, $data->toArray(), 2 * MINUTE_IN_SECONDS);

        // Schedule cleanup and API call
        wp_schedule_single_event(
            time() + 2 * MINUTE_IN_SECONDS,
            'notifal_delayed_deactivation_api_call',
            [$transient_key]
        );
    }
}
