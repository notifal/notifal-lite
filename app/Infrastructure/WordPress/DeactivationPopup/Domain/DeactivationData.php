<?php
/**
 * Deactivation Data Model
 *
 * Represents the data structure for deactivation feedback.
 *
 * @package Notifal\Infrastructure\WordPress\DeactivationPopup\Domain
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\DeactivationPopup\Domain;

use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class DeactivationData
 *
 * Represents deactivation feedback data with validation and sanitization.
 * Handles user feedback collection during plugin deactivation process.
 *
 * @package Notifal\Infrastructure\WordPress\DeactivationPopup\Domain
 */
class DeactivationData
{
    /**
     * Domain of the WordPress site
     *
     * @var string
     */
    protected string $domain;

    /**
     * Admin email address
     *
     * @var string
     */
    protected string $admin_email;

    /**
     * Deactivation reason
     *
     * @var string
     */
    protected string $reason;

    /**
     * Name of the plugin found (if reason is found_better_plugin)
     *
     * @var string|null
     */
    protected ?string $plugin_found;

    /**
     * Additional feedback text
     *
     * @var string|null
     */
    protected ?string $additional_feedback;

    /**
     * Plugin version
     *
     * @var string|null
     */
    protected ?string $plugin_version;

    /**
     * PHP version
     *
     * @var string|null
     */
    protected ?string $php_version;

    /**
     * WordPress version
     *
     * @var string|null
     */
    protected ?string $wp_version;

    /**
     * Deactivation count
     *
     * @var int
     */
    protected int $deactivation_count;

    /**
     * First deactivation timestamp
     *
     * @var int|null
     */
    protected ?int $first_deactivation_time;

    /**
     * Last deactivation timestamp
     *
     * @var int|null
     */
    protected ?int $last_deactivation_time;

    /**
     * Constructor
     *
     * @param array $data Array of deactivation data
     * @since 2.0.0
     */
    public function __construct(array $data = [])
    {
        $this->domain = Helper::sanitizeInput($data['domain'] ?? '', 'text');
        $this->admin_email = Helper::sanitizeInput($data['admin_email'] ?? '', 'email');
        $this->reason = Helper::sanitizeInput($data['deactivation_reason'] ?? $data['reason'] ?? '', 'key');
        $this->plugin_found = isset($data['plugin_found']) ? Helper::sanitizeInput($data['plugin_found'], 'text') : null;
        $this->additional_feedback = isset($data['additional_feedback']) ? Helper::sanitizeInput($data['additional_feedback'], 'textarea') : null;
        $this->plugin_version = isset($data['plugin_version']) ? Helper::sanitizeInput($data['plugin_version'], 'text') : null;
        $this->php_version = isset($data['php_version']) ? Helper::sanitizeInput($data['php_version'], 'text') : null;
        $this->wp_version = isset($data['wp_version']) ? Helper::sanitizeInput($data['wp_version'], 'text') : null;
        $this->deactivation_count = Helper::sanitizeInput($data['deactivation_count'] ?? 1, 'int');
        $this->first_deactivation_time = isset($data['first_deactivation_time']) ? Helper::sanitizeInput($data['first_deactivation_time'], 'int') : null;
        $this->last_deactivation_time = isset($data['last_deactivation_time']) ? Helper::sanitizeInput($data['last_deactivation_time'], 'int') : null;
    }

    /**
     * Get domain
     *
     * @return string
     * @since 2.0.0
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Set domain
     *
     * @param string $domain
     * @return self
     * @since 2.0.0
     */
    public function setDomain(string $domain): self
    {
        $this->domain = Helper::sanitizeInput($domain, 'url');
        return $this;
    }

    /**
     * Get admin email
     *
     * @return string
     * @since 2.0.0
     */
    public function getAdminEmail(): string
    {
        return $this->admin_email;
    }

    /**
     * Set admin email
     *
     * @param string $admin_email
     * @return self
     * @since 2.0.0
     */
    public function setAdminEmail(string $admin_email): self
    {
        $this->admin_email = Helper::sanitizeInput($admin_email, 'email');
        return $this;
    }

    /**
     * Get deactivation reason
     *
     * @return string
     * @since 2.0.0
     */
    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * Set deactivation reason
     *
     * @param string $reason
     * @return self
     * @since 2.0.0
     */
    public function setReason(string $reason): self
    {
        $this->reason = Helper::sanitizeInput($reason, 'key');
        return $this;
    }

    /**
     * Get plugin found name
     *
     * @return string|null
     * @since 2.0.0
     */
    public function getPluginFound(): ?string
    {
        return $this->plugin_found;
    }

    /**
     * Set plugin found name
     *
     * @param string|null $plugin_found
     * @return self
     * @since 2.0.0
     */
    public function setPluginFound(?string $plugin_found): self
    {
        $this->plugin_found = $plugin_found !== null ? Helper::sanitizeInput($plugin_found, 'text') : null;
        return $this;
    }

    /**
     * Get additional feedback
     *
     * @return string|null
     * @since 2.0.0
     */
    public function getAdditionalFeedback(): ?string
    {
        return $this->additional_feedback;
    }

    /**
     * Set additional feedback
     *
     * @param string|null $additional_feedback
     * @return self
     * @since 2.0.0
     */
    public function setAdditionalFeedback(?string $additional_feedback): self
    {
        $this->additional_feedback = $additional_feedback !== null ? Helper::sanitizeInput($additional_feedback, 'textarea') : null;
        return $this;
    }

    /**
     * Get plugin version
     *
     * @return string|null
     * @since 2.0.0
     */
    public function getPluginVersion(): ?string
    {
        return $this->plugin_version;
    }

    /**
     * Set plugin version
     *
     * @param string|null $plugin_version
     * @return self
     * @since 2.0.0
     */
    public function setPluginVersion(?string $plugin_version): self
    {
        $this->plugin_version = $plugin_version !== null ? Helper::sanitizeInput($plugin_version, 'text') : null;
        return $this;
    }

    /**
     * Get PHP version
     *
     * @return string|null
     * @since 2.0.0
     */
    public function getPhpVersion(): ?string
    {
        return $this->php_version;
    }

    /**
     * Set PHP version
     *
     * @param string|null $php_version
     * @return self
     * @since 2.0.0
     */
    public function setPhpVersion(?string $php_version): self
    {
        $this->php_version = $php_version !== null ? Helper::sanitizeInput($php_version, 'text') : null;
        return $this;
    }

    /**
     * Get WordPress version
     *
     * @return string|null
     * @since 2.0.0
     */
    public function getWpVersion(): ?string
    {
        return $this->wp_version;
    }

    /**
     * Set WordPress version
     *
     * @param string|null $wp_version
     * @return self
     * @since 2.0.0
     */
    public function setWpVersion(?string $wp_version): self
    {
        $this->wp_version = $wp_version !== null ? Helper::sanitizeInput($wp_version, 'text') : null;
        return $this;
    }

    /**
     * Get deactivation count
     *
     * @return int
     * @since 2.0.0
     */
    public function getDeactivationCount(): int
    {
        return $this->deactivation_count;
    }

    /**
     * Set deactivation count
     *
     * @param int $deactivation_count
     * @return self
     * @since 2.0.0
     */
    public function setDeactivationCount(int $deactivation_count): self
    {
        $this->deactivation_count = Helper::sanitizeInput($deactivation_count, 'int');
        return $this;
    }

    /**
     * Get first deactivation timestamp
     *
     * @return int|null
     * @since 2.0.0
     */
    public function getFirstDeactivationTime(): ?int
    {
        return $this->first_deactivation_time;
    }

    /**
     * Set first deactivation timestamp
     *
     * @param int|null $first_deactivation_time
     * @return self
     * @since 2.0.0
     */
    public function setFirstDeactivationTime(?int $first_deactivation_time): self
    {
        $this->first_deactivation_time = $first_deactivation_time !== null ? Helper::sanitizeInput($first_deactivation_time, 'int') : null;
        return $this;
    }

    /**
     * Get last deactivation timestamp
     *
     * @return int|null
     * @since 2.0.0
     */
    public function getLastDeactivationTime(): ?int
    {
        return $this->last_deactivation_time;
    }

    /**
     * Set last deactivation timestamp
     *
     * @param int|null $last_deactivation_time
     * @return self
     * @since 2.0.0
     */
    public function setLastDeactivationTime(?int $last_deactivation_time): self
    {
        $this->last_deactivation_time = $last_deactivation_time !== null ? Helper::sanitizeInput($last_deactivation_time, 'int') : null;
        return $this;
    }

    /**
     * Convert to array for API transmission
     *
     * @return array
     * @since 2.0.0
     */
    public function toArray(): array
    {
        return [
            'domain' => $this->domain,
            'admin_email' => $this->admin_email,
            'deactivation_reason' => $this->reason,
            'plugin_found' => $this->plugin_found,
            'additional_feedback' => $this->additional_feedback,
            'plugin_version' => $this->plugin_version,
            'php_version' => $this->php_version,
            'wp_version' => $this->wp_version,
            'deactivation_count' => $this->deactivation_count,
            'first_deactivation_time' => $this->first_deactivation_time,
            'last_deactivation_time' => $this->last_deactivation_time,
        ];
    }

    /**
     * Validate the data
     *
     * @return array Array of validation errors (empty if valid)
     * @since 2.0.0
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->domain)) {
            $errors[] = __('Domain is required', 'notifal');
        }

        if (empty($this->admin_email) || !is_email($this->admin_email)) {
            $errors[] = __('Valid admin email is required', 'notifal');
        }

        if (empty($this->reason) || !DeactivationReason::isValidReason($this->reason)) {
            $errors[] = __('Valid deactivation reason is required', 'notifal');
        }

        // Check if additional input is required
        if (DeactivationReason::requiresAdditionalInput($this->reason)) {
            if ($this->reason === DeactivationReason::FOUND_BETTER_PLUGIN && empty($this->plugin_found)) {
                $errors[] = __('Please specify which plugin you found', 'notifal');
            }

            if ($this->reason === DeactivationReason::OTHER && empty($this->additional_feedback)) {
                $errors[] = __('Please provide additional feedback', 'notifal');
            }
        }

        return $errors;
    }

    /**
     * Check if deactivation should be prevented
     *
     * @return bool
     * @since 2.0.0
     */
    public function shouldPreventDeactivation(): bool
    {
        return DeactivationReason::shouldPreventDeactivation($this->reason);
    }
}
