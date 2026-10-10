<?php
namespace Notifal\Modules\SetupWizard\Application\Services;
use Notifal\Modules\SetupWizard\Domain\Constants\SetupWizardOptions;
use Notifal\Shared\Utils\Helper;
defined('ABSPATH') || exit;
/**
 * Persists in-progress Setup Wizard selections between steps.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardSessionService
{
    /**
     * Return the stored wizard session array.
     *
     * @since 3.0.0
     * @return array<string, mixed>
     */
    public function get(): array
    {
        // Read the non-autoloaded session option.
        $session = get_option(SetupWizardOptions::SESSION, []);
        // Normalize malformed stored values.
        return is_array($session) ? $session : [];
    }
    /**
     * Return a browser-safe session snapshot without install secrets.
     *
     * @since 3.0.0
     * @return array<string, mixed>
     */
    public function getPublicSnapshot(): array
    {
        // Copy the stored session and remove server-only install proof fields.
        $session = $this->get();
        unset($session['license_key'], $session['package_url']);
        return $session;
    }
    /**
     * Return true when a gateway trial created install proof on the server.
     *
     * @since 3.0.0
     * @return bool
     */
    public function hasTrialInstallProof(): bool
    {
        // Read server-side trial proof fields from the session option.
        $session = $this->get();
        return '' !== Helper::sanitizeInput((string) ($session['license_key'] ?? ''), 'text')
            && '' !== esc_url_raw((string) ($session['package_url'] ?? ''))
            && '' !== Helper::sanitizeInput((string) ($session['trial_domain'] ?? ''), 'text');
    }
    /**
     * Return server-side trial install proof for the Pro install step.
     *
     * @since 3.0.0
     * @return array{license_key: string, package_url: string, trial_domain: string}
     */
    public function getTrialInstallProof(): array
    {
        // Read and sanitize trial proof fields from the stored session.
        $session = $this->get();
        return [
            'license_key'  => Helper::sanitizeInput((string) ($session['license_key'] ?? ''), 'text'),
            'package_url'  => esc_url_raw((string) ($session['package_url'] ?? '')),
            'trial_domain' => Helper::sanitizeInput((string) ($session['trial_domain'] ?? ''), 'text'),
        ];
    }
    /**
     * Merge new session fields and persist them.
     *
     * @since 3.0.0
     * @param array<string, mixed> $fields Partial session update.
     * @return array<string, mixed> Saved session snapshot.
     */
    public function merge(array $fields): array
    {
        // Merge incoming fields into the existing session.
        $session = array_merge($this->get(), $fields);
        // Persist without autoload to keep options table lean.
        update_option(SetupWizardOptions::SESSION, $session, false);
        // Return the saved snapshot for AJAX responses.
        return $session;
    }
    /**
     * Store one selected goal slug from the wizard UI.
     *
     * @since 3.0.0
     * @param string $goalSlug Goal slug from HtmlBuilderUseCases.
     * @return array<string, mixed> Saved session snapshot.
     */
    public function saveGoal(string $goalSlug): array
    {
        // Sanitize the submitted goal slug.
        $goalSlug = sanitize_key($goalSlug);
        // Persist the goal for recommendations and progress reporting.
        return $this->merge(['goal' => $goalSlug]);
    }
    /**
     * Store recommendation selection metadata.
     *
     * @since 3.0.0
     * @param string $source Selection source: marketplace, custom_ai, or skipped.
     * @param int    $notificationId Marketplace notification ID when applicable.
     * @param string $fileType Builder file type for marketplace imports.
     * @return array<string, mixed> Saved session snapshot.
     */
    public function saveRecommendation(string $source, int $notificationId = 0, string $fileType = ''): array
    {
        // Sanitize recommendation metadata before persistence.
        return $this->merge([
            'template_source'   => sanitize_key($source),
            'notification_id'   => absint($notificationId),
            'import_file_type'  => sanitize_key($fileType),
            'custom_ai_pending' => 'custom_ai' === sanitize_key($source),
        ]);
    }
    /**
     * Store trial provisioning metadata after gateway success.
     *
     * @since 3.0.0
     * @param array<string, mixed> $trialPayload Trial payload from the gateway.
     * @return array<string, mixed> Saved session snapshot.
     */
    public function saveTrial(array $trialPayload): array
    {
        // Sanitize trial proof fields used by the install step.
        return $this->merge([
            'license_key'         => Helper::sanitizeInput((string) ($trialPayload['license_key'] ?? ''), 'text'),
            'package_url'         => esc_url_raw((string) ($trialPayload['package_url'] ?? '')),
            'pro_version'         => Helper::sanitizeInput((string) ($trialPayload['pro_version'] ?? ''), 'text'),
            'trial_domain'        => Helper::sanitizeInput((string) ($trialPayload['domain'] ?? ''), 'text'),
            'trial_provisioned_at'=> current_time('mysql'),
        ]);
    }
    /**
     * Clear stored session data after completion or uninstall.
     *
     * @since 3.0.0
     * @return void
     */
    public function clear(): void
    {
        // Remove the session option entirely.
        delete_option(SetupWizardOptions::SESSION);
    }
}
