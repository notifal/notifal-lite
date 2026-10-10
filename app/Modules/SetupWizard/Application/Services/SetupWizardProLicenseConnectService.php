<?php
namespace Notifal\Modules\SetupWizard\Application\Services;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Shared\Utils\Helper;
defined('ABSPATH') || exit;
/**
 * Activates Notifal Pro and connects a trial license like manual Connect & Activate.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardProLicenseConnectService
{
    /**
     * Expected Notifal Pro bootstrap file relative to wp-content/plugins.
     *
     * @var string
     */
    private const PRO_PLUGIN_FILE = 'notifal-pro/notifal-pro.php';
    /**
     * Fully-qualified Notifal Pro license validator class.
     *
     * @var string
     */
    private const LICENSE_VALIDATOR_CLASS = '\\NotifalPro\\Domain\\License\\Services\\LicenseValidator';
    /**
     * Fully-qualified Notifal Pro validation cache class.
     *
     * @var string
     */
    private const VALIDATION_CACHE_CLASS = '\\NotifalPro\\Domain\\License\\Services\\ValidationCache';
    /**
     * Destination recovery helper used to discover the Pro bootstrap path.
     *
     * @var SetupWizardProInstallDestinationRecovery
     */
    private SetupWizardProInstallDestinationRecovery $destinationRecovery;
    /**
     * Create the Pro license connect service.
     *
     * @since 3.0.0
     * @param SetupWizardProInstallDestinationRecovery $destinationRecovery Destination recovery helper.
     */
    public function __construct(SetupWizardProInstallDestinationRecovery $destinationRecovery)
    {
        // Reuse install-path discovery so activation finds the same bootstrap file.
        $this->destinationRecovery = $destinationRecovery;
    }
    /**
     * Activate Notifal Pro as a WordPress plugin and connect the trial license.
     *
     * Mirrors Notifal Pro Connect & Activate: validate remotely, then save locally.
     *
     * @since 3.0.0
     * @param string $licenseKey Trial license key.
     * @param string $domain     Activated license domain.
     * @return array{success: bool, plugin_activated: bool, license_saved: bool, error: string}
     */
    public function activateAndConnect(string $licenseKey, string $domain): array
    {
        // Sanitize license inputs before activation work.
        $licenseKey = Helper::sanitizeInput($licenseKey, 'text');
        $domain = Helper::sanitizeInput($domain, 'text');
        // Require both license proof fields before touching the plugin API.
        if ('' === $licenseKey || '' === $domain) {
            return $this->result(false, false, false, __('Missing trial license details.', 'notifal'));
        }
        // Ensure plugin admin APIs are available during admin-ajax requests.
        if (!function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        // Resolve the on-disk bootstrap path before calling activate_plugin().
        $pluginFile = $this->resolvePluginFile();
        if ('' === $pluginFile) {
            return $this->result(
                false,
                false,
                false,
                __('Notifal Pro plugin files were not found after install.', 'notifal')
            );
        }
        // Activate Notifal Pro when it is not already feature-active.
        $pluginActivated = PluginDetector::isNotifalProActive();
        if (!$pluginActivated) {
            $activation = activate_plugin($pluginFile, '', false, true);
            if (is_wp_error($activation)) {
                return $this->result(
                    false,
                    false,
                    false,
                    sanitize_text_field($activation->get_error_message())
                );
            }
            $pluginActivated = true;
        }
        // Connect and activate the trial license using the same Pro validator path.
        $licenseResult = $this->connectLicense($licenseKey, $domain);
        return $this->result(
            !empty($licenseResult['success']),
            $pluginActivated,
            !empty($licenseResult['success']),
            sanitize_text_field((string) ($licenseResult['error'] ?? ''))
        );
    }
    /**
     * Validate and persist one trial license through Notifal Pro services.
     *
     * @since 3.0.0
     * @param string $licenseKey Trial license key.
     * @param string $domain     Activated license domain.
     * @return array{success: bool, error?: string}
     */
    private function connectLicense(string $licenseKey, string $domain): array
    {
        // Ensure Pro license classes are available after activation in this request.
        if (!$this->ensureLicenseClassesLoaded()) {
            return [
                'success' => false,
                'error'   => __('Notifal Pro license services are unavailable.', 'notifal'),
            ];
        }
        $validatorClass = self::LICENSE_VALIDATOR_CLASS;
        $cacheClass = self::VALIDATION_CACHE_CLASS;
        // Clear cached validation so Connect & Activate always hits the live API.
        $cacheClass::clear_license_cache($licenseKey, $domain);
        // Validate the trial license against notifal.com over HTTPS.
        $validation = $validatorClass::validate_license($licenseKey, $domain);
        if (empty($validation['success'])) {
            return [
                'success' => false,
                'error'   => sanitize_text_field(
                    (string) ($validation['message'] ?? __('License validation failed.', 'notifal'))
                ),
            ];
        }
        // Persist validated license metadata locally, matching Connect & Activate.
        $validatorClass::save_license_info(
            [
                'license_key'    => $licenseKey,
                'license_status' => 'active',
                'expires_at'     => Helper::sanitizeInput((string) ($validation['expires_at'] ?? ''), 'text'),
                'domain'         => $domain,
                'email'          => sanitize_email((string) ($validation['email'] ?? '')),
            ]
        );
        return ['success' => true];
    }
    /**
     * Resolve the Pro bootstrap relative path when the files exist on disk.
     *
     * @since 3.0.0
     * @return string Relative plugin file or empty string.
     */
    private function resolvePluginFile(): string
    {
        // Prefer destination recovery discovery (canonical path, then plugin-header scan).
        $discovered = $this->destinationRecovery->resolveBootstrapFile();
        if ('' !== $discovered) {
            return $discovered;
        }
        // Keep the historical canonical fallback for older Pro package layouts.
        if (file_exists(WP_PLUGIN_DIR . '/' . self::PRO_PLUGIN_FILE)) {
            return self::PRO_PLUGIN_FILE;
        }
        return '';
    }
    /**
     * Ensure Notifal Pro license classes can be used in the current request.
     *
     * @since 3.0.0
     * @return bool
     */
    private function ensureLicenseClassesLoaded(): bool
    {
        // Classes are usually available after a successful activate_plugin include.
        if (class_exists(self::LICENSE_VALIDATOR_CLASS) && class_exists(self::VALIDATION_CACHE_CLASS)) {
            return true;
        }
        // Load Pro bootstrap once when activation did not fully boot services yet.
        $pluginFile = $this->resolvePluginFile();
        $bootstrap = '' !== $pluginFile
            ? WP_PLUGIN_DIR . '/' . $pluginFile
            : WP_PLUGIN_DIR . '/' . self::PRO_PLUGIN_FILE;
        if (!file_exists($bootstrap)) {
            return false;
        }
        // Avoid redefining constants when Pro already started in this request.
        if (!defined('NOTIFAL_PRO_VERSION')) {
            require_once $bootstrap;
        }
        return class_exists(self::LICENSE_VALIDATOR_CLASS) && class_exists(self::VALIDATION_CACHE_CLASS);
    }
    /**
     * Build a normalized activate-and-connect result array.
     *
     * @since 3.0.0
     * @param bool   $success         Overall success flag.
     * @param bool   $pluginActivated Whether the plugin is active.
     * @param bool   $licenseSaved    Whether the license was persisted.
     * @param string $error           Optional error message.
     * @return array{success: bool, plugin_activated: bool, license_saved: bool, error: string}
     */
    private function result(bool $success, bool $pluginActivated, bool $licenseSaved, string $error): array
    {
        return [
            'success'          => $success,
            'plugin_activated' => $pluginActivated,
            'license_saved'    => $licenseSaved,
            'error'            => sanitize_text_field($error),
        ];
    }
}
