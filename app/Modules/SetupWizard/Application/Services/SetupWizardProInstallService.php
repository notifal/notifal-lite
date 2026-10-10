<?php
namespace Notifal\Modules\SetupWizard\Application\Services;
use Notifal\Shared\Utils\Helper;
defined('ABSPATH') || exit;
/**
 * Downloads and installs Notifal Pro from a signed trial package URL.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardProInstallService
{
    /**
     * Expected Notifal Pro bootstrap file relative to wp-content/plugins.
     *
     * @var string
     */
    private const PRO_PLUGIN_FILE = 'notifal-pro/notifal-pro.php';
    /**
     * Package URL validator dependency.
     *
     * @var SetupWizardPackageUrlValidator
     */
    private SetupWizardPackageUrlValidator $packageUrlValidator;
    /**
     * Failure message builder dependency.
     *
     * @var SetupWizardProInstallFailureDescriptor
     */
    private SetupWizardProInstallFailureDescriptor $failureDescriptor;
    /**
     * Filesystem readiness gate dependency.
     *
     * @var SetupWizardProInstallFilesystemGate
     */
    private SetupWizardProInstallFilesystemGate $filesystemGate;
    /**
     * Destination folder recovery dependency.
     *
     * @var SetupWizardProInstallDestinationRecovery
     */
    private SetupWizardProInstallDestinationRecovery $destinationRecovery;
    /**
     * Create the Pro install service.
     *
     * @since 3.0.0
     * @param SetupWizardPackageUrlValidator           $packageUrlValidator Package URL validator.
     * @param SetupWizardProInstallFailureDescriptor   $failureDescriptor   Failure message builder.
     * @param SetupWizardProInstallFilesystemGate      $filesystemGate      Filesystem readiness gate.
     * @param SetupWizardProInstallDestinationRecovery $destinationRecovery Destination folder recovery.
     */
    public function __construct(
        SetupWizardPackageUrlValidator $packageUrlValidator,
        SetupWizardProInstallFailureDescriptor $failureDescriptor,
        SetupWizardProInstallFilesystemGate $filesystemGate,
        SetupWizardProInstallDestinationRecovery $destinationRecovery
    ) {
        // Store dependencies used before any outbound package download.
        $this->packageUrlValidator = $packageUrlValidator;
        $this->failureDescriptor = $failureDescriptor;
        $this->filesystemGate = $filesystemGate;
        $this->destinationRecovery = $destinationRecovery;
    }
    /**
     * Install Notifal Pro package files without activating the plugin.
     *
     * @since 3.0.0
     * @param string $packageUrl Signed package download URL.
     * @param string $domain     Activated license domain.
     * @return array<string, mixed> Install result payload.
     */
    public function installPackage(string $packageUrl, string $domain): array
    {
        // Sanitize install inputs before any filesystem work.
        $packageUrl = esc_url_raw($packageUrl);
        $domain = Helper::sanitizeInput($domain, 'text');
        // Reject incomplete install payloads early.
        if ('' === $packageUrl || '' === $domain) {
            return $this->failure(__('Missing trial install details.', 'notifal'), '');
        }
        // Reject install attempts for domains that do not match this WordPress site.
        if (!$this->domainMatchesCurrentSite($domain)) {
            return $this->failure(__('Trial domain does not match this site.', 'notifal'), '');
        }
        // Allow downloads only from the signed notifal.com update endpoint.
        if (!$this->packageUrlValidator->isAllowed($packageUrl)) {
            return $this->failure(__('Invalid Notifal Pro package URL.', 'notifal'), '');
        }
        // Skip download when Pro files are already present on disk.
        if ($this->destinationRecovery->hasValidBootstrap()) {
            $pluginFile = $this->destinationRecovery->resolveBootstrapFile();
            return $this->successPayload(
                true,
                '' !== $pluginFile ? $pluginFile : self::PRO_PLUGIN_FILE,
                $packageUrl
            );
        }
        // Load WordPress upgrader dependencies for plugin package installs.
        $this->loadUpgraderDependencies();
        // Fail early when WordPress cannot initialize a writable filesystem connection.
        $fsReady = $this->filesystemGate->ensureReady();
        if (is_wp_error($fsReady)) {
            return $this->failure(sanitize_text_field($fsReady->get_error_message()), $packageUrl);
        }
        // Clear stale incomplete destinations before the first upgrader attempt.
        $prepared = $this->prepareDestination();
        if (is_wp_error($prepared)) {
            return $this->failure(sanitize_text_field($prepared->get_error_message()), $packageUrl);
        }
        // Run the package install, recovering once from a leftover destination folder.
        return $this->runInstallWithRecovery($packageUrl);
    }
    /**
     * Install, activate, and connect a trial license in one orchestration call.
     *
     * @since 3.0.0
     * @param string $packageUrl Signed package download URL.
     * @param string $licenseKey Trial license key.
     * @param string $domain     Activated license domain.
     * @return array<string, mixed> Full provision result payload.
     */
    public function install(string $packageUrl, string $licenseKey, string $domain): array
    {
        // Install package files first so activation never runs against a missing path.
        $installResult = $this->installPackage($packageUrl, $domain);
        if (empty($installResult['success'])) {
            return $installResult;
        }
        // Activate the WordPress plugin and connect the trial license locally.
        $connectService = notifal_app(SetupWizardProLicenseConnectService::class);
        $connectResult = $connectService->activateAndConnect($licenseKey, $domain);
        return [
            'success'             => !empty($connectResult['success']),
            'already_installed'   => !empty($installResult['already_installed']),
            'plugin_file'         => sanitize_text_field((string) ($installResult['plugin_file'] ?? self::PRO_PLUGIN_FILE)),
            'license_saved'       => !empty($connectResult['license_saved']),
            'plugin_activated'    => !empty($connectResult['plugin_activated']),
            'error'               => sanitize_text_field((string) ($connectResult['error'] ?? '')),
            'manual_download_url' => esc_url_raw((string) ($installResult['manual_download_url'] ?? $packageUrl)),
        ];
    }
    /**
     * Clear incomplete destination debris before Plugin_Upgrader runs.
     *
     * @since 3.0.0
     * @return true|\WP_Error
     */
    private function prepareDestination()
    {
        // Skip cleanup when there is no blocking incomplete folder.
        if (!$this->destinationRecovery->hasIncompleteDestination()) {
            return true;
        }
        return $this->destinationRecovery->removeIncompleteDestination();
    }
    /**
     * Run Plugin_Upgrader once, recovering from folder_exists when needed.
     *
     * @since 3.0.0
     * @param string $packageUrl Signed package download URL.
     * @return array<string, mixed>
     */
    private function runInstallWithRecovery(string $packageUrl): array
    {
        // First attempt after any proactive destination cleanup.
        $first = $this->attemptUpgraderInstall($packageUrl);
        if (!empty($first['success'])) {
            return $first;
        }
        // Recover only from destination folder conflicts, never from unrelated failures.
        if (empty($first['folder_exists'])) {
            return $first;
        }
        // Valid on-disk Pro means the folder conflict is actually an already-installed state.
        if ($this->destinationRecovery->hasValidBootstrap()) {
            $pluginFile = $this->destinationRecovery->resolveBootstrapFile();
            return $this->successPayload(
                true,
                '' !== $pluginFile ? $pluginFile : self::PRO_PLUGIN_FILE,
                $packageUrl
            );
        }
        // Incomplete destinations block install and must be removed before one retry.
        $removed = $this->destinationRecovery->removeIncompleteDestination();
        if (is_wp_error($removed)) {
            return $this->failure(sanitize_text_field($removed->get_error_message()), $packageUrl);
        }
        // Retry the package install exactly once after a successful cleanup.
        $retry = $this->attemptUpgraderInstall($packageUrl);
        if (!empty($retry['success'])) {
            return $retry;
        }
        // Surface the real retry failure to the wizard UI.
        return $retry;
    }
    /**
     * Execute one Plugin_Upgrader::install() attempt and normalize the result.
     *
     * @since 3.0.0
     * @param string $packageUrl Signed package download URL.
     * @return array<string, mixed>
     */
    private function attemptUpgraderInstall(string $packageUrl): array
    {
        // Use the Ajax skin so soft failures still expose WP_Error and feedback text.
        $skin = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader($skin);
        $installResult = $upgrader->install($packageUrl);
        // Detect folder_exists from the return value, upgrader result, or ajax skin errors.
        $folderExists = $this->destinationRecovery->isFolderExistsFailure(
            $installResult,
            $upgrader,
            $skin
        );
        // Treat folder-already-exists as success when the Pro bootstrap is on disk.
        if ($folderExists && $this->destinationRecovery->hasValidBootstrap()) {
            $pluginFile = $this->destinationRecovery->resolveBootstrapFile();
            return $this->successPayload(
                true,
                '' !== $pluginFile ? $pluginFile : self::PRO_PLUGIN_FILE,
                $packageUrl
            );
        }
        // Keep folder_exists failures structured so the caller can recover and retry.
        if ($folderExists) {
            $described = $this->failureDescriptor->describe($installResult, $upgrader, $skin);
            return [
                'success'             => false,
                'folder_exists'       => true,
                'error'               => sanitize_text_field((string) ($described['message'] ?? '')),
                'manual_download_url' => esc_url_raw($packageUrl),
            ];
        }
        // Plugin_Upgrader may return null/false on soft filesystem failures; only true is success.
        if (true !== $installResult) {
            $described = $this->failureDescriptor->describe($installResult, $upgrader, $skin);
            return $this->failure((string) ($described['message'] ?? ''), $packageUrl);
        }
        // Refresh plugin caches before validating the installed bootstrap file.
        wp_clean_plugins_cache(true);
        $pluginFile = $this->resolveInstalledPluginFile($upgrader);
        if ('' === $pluginFile || !$this->pluginFileExists($pluginFile)) {
            return $this->failure(
                __('Notifal Pro was downloaded but the plugin files could not be found.', 'notifal'),
                $packageUrl
            );
        }
        return $this->successPayload(false, $pluginFile, $packageUrl);
    }
    /**
     * Return true when the trial domain matches the current site host.
     *
     * @since 3.0.0
     * @param string $domain Trial domain from the gateway.
     * @return bool
     */
    private function domainMatchesCurrentSite(string $domain): bool
    {
        // Normalize both domains for comparison.
        $expected = strtolower((string) parse_url(home_url('/'), PHP_URL_HOST));
        $provided = strtolower(trim($domain));
        // Strip optional scheme prefixes when the gateway stored a full URL.
        $provided = (string) preg_replace('#^https?://#i', '', $provided);
        $provided = (string) strtok($provided, '/');
        return '' !== $expected && $expected === $provided;
    }
    /**
     * Load WordPress admin includes required by Plugin_Upgrader.
     *
     * @since 3.0.0
     * @return void
     */
    private function loadUpgraderDependencies(): void
    {
        // Ensure filesystem helpers are available for credentialed installs.
        if (!function_exists('request_filesystem_credentials')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }
    /**
     * Resolve the installed Pro bootstrap path from the upgrader or disk.
     *
     * @since 3.0.0
     * @param \Plugin_Upgrader $upgrader Upgrader used for the install attempt.
     * @return string Relative plugin file or empty string.
     */
    private function resolveInstalledPluginFile(\Plugin_Upgrader $upgrader): string
    {
        // Prefer the path WordPress detected inside the extracted package.
        $detected = $upgrader->plugin_info();
        if (is_string($detected) && '' !== $detected && $this->pluginFileExists($detected)) {
            return $detected;
        }
        // Fall back to destination recovery discovery (canonical path, then header scan).
        return $this->destinationRecovery->resolveBootstrapFile();
    }
    /**
     * Return true when a plugin-relative file exists under WP_PLUGIN_DIR.
     *
     * @since 3.0.0
     * @param string $pluginFile Relative plugin file path.
     * @return bool
     */
    private function pluginFileExists(string $pluginFile): bool
    {
        // Normalize and reject empty relative paths.
        $pluginFile = ltrim(str_replace('\\', '/', $pluginFile), '/');
        if ('' === $pluginFile) {
            return false;
        }
        return file_exists(WP_PLUGIN_DIR . '/' . $pluginFile);
    }
    /**
     * Build a standard success payload for package install.
     *
     * @since 3.0.0
     * @param bool   $alreadyInstalled Whether Pro files were already on disk.
     * @param string $pluginFile        Relative plugin bootstrap path.
     * @param string $manualDownloadUrl Fallback package URL.
     * @return array<string, mixed>
     */
    private function successPayload(bool $alreadyInstalled, string $pluginFile, string $manualDownloadUrl): array
    {
        return [
            'success'             => true,
            'already_installed'   => $alreadyInstalled,
            'plugin_file'         => sanitize_text_field($pluginFile),
            'error'               => '',
            'manual_download_url' => esc_url_raw($manualDownloadUrl),
        ];
    }
    /**
     * Build a standard failure payload with optional manual download URL.
     *
     * @since 3.0.0
     * @param string $message          Failure message.
     * @param string $manualDownloadUrl Fallback package URL.
     * @return array<string, mixed>
     */
    private function failure(string $message, string $manualDownloadUrl): array
    {
        return [
            'success'             => false,
            'folder_exists'       => false,
            'error'               => sanitize_text_field($message),
            'manual_download_url' => esc_url_raw($manualDownloadUrl),
        ];
    }
}
