<?php
namespace Notifal\Modules\SetupWizard\Application\Services;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
defined('ABSPATH') || exit;
/**
 * Recovers from stale or incomplete Notifal Pro destination folders during wizard install.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardProInstallDestinationRecovery
{
    /**
     * Canonical Notifal Pro directory slug under wp-content/plugins.
     *
     * @var string
     */
    private const PRO_DIR_SLUG = 'notifal-pro';
    /**
     * Canonical Notifal Pro bootstrap relative to wp-content/plugins.
     *
     * @var string
     */
    private const PRO_PLUGIN_FILE = 'notifal-pro/notifal-pro.php';
    /**
     * Filesystem readiness gate used before destructive destination cleanup.
     *
     * @var SetupWizardProInstallFilesystemGate
     */
    private SetupWizardProInstallFilesystemGate $filesystemGate;
    /**
     * Create the destination recovery helper.
     *
     * @since 3.0.0
     * @param SetupWizardProInstallFilesystemGate $filesystemGate Filesystem readiness gate.
     */
    public function __construct(SetupWizardProInstallFilesystemGate $filesystemGate)
    {
        // Reuse the same FS gate that prepares Plugin_Upgrader writes.
        $this->filesystemGate = $filesystemGate;
    }
    /**
     * Return true when the canonical Notifal Pro bootstrap is present on disk.
     *
     * @since 3.0.0
     * @return bool
     */
    public function hasValidBootstrap(): bool
    {
        // PluginDetector is the single source of truth for "Pro is installed".
        return PluginDetector::isNotifalProInstalled();
    }
    /**
     * Return true when a notifal-pro directory exists without a valid bootstrap file.
     *
     * @since 3.0.0
     * @return bool
     */
    public function hasIncompleteDestination(): bool
    {
        // A valid bootstrap means the destination is a real plugin install, not stale debris.
        if ($this->hasValidBootstrap()) {
            return false;
        }
        // Incomplete destinations are folders (or broken paths) that still block Plugin_Upgrader.
        return $this->destinationPathExists();
    }
    /**
     * Return true when the notifal-pro path exists on disk under WP_PLUGIN_DIR.
     *
     * @since 3.0.0
     * @return bool
     */
    public function destinationPathExists(): bool
    {
        // Build the absolute destination path using the WordPress plugins directory.
        $absolute = $this->absoluteDestinationPath();
        // Treat both directories and leftover files with this name as blocking destinations.
        return file_exists($absolute) || is_dir($absolute) || is_link($absolute);
    }
    /**
     * Resolve the relative Pro bootstrap path when files exist on disk.
     *
     * @since 3.0.0
     * @return string Relative plugin file or empty string.
     */
    public function resolveBootstrapFile(): string
    {
        // Prefer the canonical bootstrap path used by PluginDetector.
        if ($this->hasValidBootstrap()) {
            return self::PRO_PLUGIN_FILE;
        }
        // Fall back to scanning the Pro directory for a WordPress plugin header.
        return $this->scanBootstrapFile();
    }
    /**
     * Remove an incomplete notifal-pro destination so Plugin_Upgrader can install cleanly.
     *
     * @since 3.0.0
     * @return true|\WP_Error True on success or when nothing needed removal.
     */
    public function removeIncompleteDestination()
    {
        // Never delete a destination that already has a valid Pro bootstrap.
        if ($this->hasValidBootstrap()) {
            return true;
        }
        // Nothing to clean when the destination path is already absent.
        if (!$this->destinationPathExists()) {
            return true;
        }
        // Refuse cleanup when the path does not resolve safely under WP_PLUGIN_DIR.
        $absolute = $this->absoluteDestinationPath();
        if (!$this->isSafeDestinationPath($absolute)) {
            return new \WP_Error(
                'unsafe_destination',
                __('Could not clear an incomplete Notifal Pro folder safely.', 'notifal')
            );
        }
        // Ensure WP_Filesystem is initialized before recursive delete.
        $fsReady = $this->filesystemGate->ensureReady();
        if (is_wp_error($fsReady)) {
            return $fsReady;
        }
        global $wp_filesystem;
        // Require a usable filesystem object after the gate succeeds.
        if (!$wp_filesystem instanceof \WP_Filesystem_Base) {
            return new \WP_Error(
                'filesystem_unavailable',
                __('WordPress could not access the filesystem to install plugins. Please check folder permissions or install Notifal Pro manually.', 'notifal')
            );
        }
        // Map the local absolute path into the active filesystem abstraction.
        $fsPath = $wp_filesystem->find_folder($absolute);
        if (!is_string($fsPath) || '' === $fsPath) {
            $fsPath = trailingslashit($wp_filesystem->wp_plugins_dir()) . self::PRO_DIR_SLUG;
        }
        // Delete recursively so leftover nested folders such as .git are removed too.
        $deleted = $wp_filesystem->delete($fsPath, true);
        if (!$deleted) {
            return new \WP_Error(
                'destination_cleanup_failed',
                __('An incomplete Notifal Pro folder could not be removed. Please delete wp-content/plugins/notifal-pro and try again.', 'notifal')
            );
        }
        // Confirm the blocking path is gone before allowing another install attempt.
        clearstatcache(true, $absolute);
        if ($this->destinationPathExists()) {
            return new \WP_Error(
                'destination_still_present',
                __('An incomplete Notifal Pro folder could not be removed. Please delete wp-content/plugins/notifal-pro and try again.', 'notifal')
            );
        }
        return true;
    }
    /**
     * Return true when the upgrader failed because the destination folder already exists.
     *
     * @since 3.0.0
     * @param mixed                  $installResult Raw Plugin_Upgrader::install() return value.
     * @param \Plugin_Upgrader|null  $upgrader      Upgrader instance after the install attempt.
     * @param \WP_Upgrader_Skin|null $skin          Skin used for the install attempt.
     * @return bool
     */
    public function isFolderExistsFailure($installResult, $upgrader = null, $skin = null): bool
    {
        // Prefer the structured return value when Plugin_Upgrader exposed folder_exists directly.
        if (is_wp_error($installResult) && 'folder_exists' === $installResult->get_error_code()) {
            return true;
        }
        // Soft null/false returns often keep the real folder_exists error on $upgrader->result.
        if ($upgrader instanceof \Plugin_Upgrader && is_wp_error($upgrader->result)) {
            if ('folder_exists' === $upgrader->result->get_error_code()) {
                return true;
            }
        }
        // Ajax skins retain error codes even when the install method returns null.
        if ($skin instanceof \WP_Ajax_Upgrader_Skin) {
            $skinErrors = $skin->get_errors();
            if (is_wp_error($skinErrors) && in_array('folder_exists', $skinErrors->get_error_codes(), true)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Absolute filesystem path to the Notifal Pro plugin directory.
     *
     * @since 3.0.0
     * @return string
     */
    private function absoluteDestinationPath(): string
    {
        // Keep separators normalized for Windows and Linux plugin directories.
        return wp_normalize_path(trailingslashit((string) WP_PLUGIN_DIR) . self::PRO_DIR_SLUG);
    }
    /**
     * Return true when the absolute path is exactly WP_PLUGIN_DIR/notifal-pro.
     *
     * @since 3.0.0
     * @param string $absolute Absolute destination path candidate.
     * @return bool
     */
    private function isSafeDestinationPath(string $absolute): bool
    {
        // Normalize the expected plugins root before containment checks.
        $pluginsRoot = realpath((string) WP_PLUGIN_DIR);
        if (false === $pluginsRoot) {
            return false;
        }
        $pluginsRoot = wp_normalize_path($pluginsRoot);
        $expected = wp_normalize_path(trailingslashit($pluginsRoot) . self::PRO_DIR_SLUG);
        // Prefer realpath when the destination already resolves on disk.
        $resolved = realpath($absolute);
        if (false !== $resolved) {
            return wp_normalize_path($resolved) === $expected;
        }
        // Allow cleanup of paths that exist but do not resolve (rare broken links).
        return wp_normalize_path($absolute) === $expected;
    }
    /**
     * Scan the Pro directory for a PHP file that declares a WordPress plugin header.
     *
     * @since 3.0.0
     * @return string Relative plugin file or empty string.
     */
    private function scanBootstrapFile(): string
    {
        // Only scan inside the dedicated Notifal Pro directory.
        $directory = $this->absoluteDestinationPath();
        if (!is_dir($directory)) {
            return '';
        }
        // Read top-level PHP files only, matching WordPress plugin discovery depth.
        $files = glob(trailingslashit($directory) . '*.php');
        if (!is_array($files)) {
            return '';
        }
        foreach ($files as $absoluteFile) {
            // Skip unreadable files before header inspection.
            if (!is_string($absoluteFile) || !is_readable($absoluteFile)) {
                continue;
            }
            // Read a small prefix so we do not load the entire plugin bootstrap.
            $header = (string) file_get_contents($absoluteFile, false, null, 0, 8192);
            if ('' === $header || false === stripos($header, 'Plugin Name:')) {
                continue;
            }
            // Convert the absolute path back into a plugins-relative bootstrap path.
            $relative = ltrim(
                str_replace('\\', '/', substr($absoluteFile, strlen((string) WP_PLUGIN_DIR))),
                '/'
            );
            if ('' !== $relative && file_exists(WP_PLUGIN_DIR . '/' . $relative)) {
                return $relative;
            }
        }
        return '';
    }
}
