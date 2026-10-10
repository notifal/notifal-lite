<?php
namespace Notifal\Modules\SetupWizard\Application\Services;
defined('ABSPATH') || exit;
/**
 * Verifies WordPress can write plugin files before Plugin_Upgrader runs.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardProInstallFilesystemGate
{
    /**
     * Ensure WordPress can write plugin files before starting Plugin_Upgrader.
     *
     * @since 3.0.0
     * @return true|\WP_Error True when ready, WP_Error when the filesystem is blocked.
     */
    public function ensureReady()
    {
        // Honor WordPress sites that intentionally disable plugin installs.
        if (defined('DISALLOW_FILE_MODS') && \DISALLOW_FILE_MODS) {
            return new \WP_Error(
                'file_mods_disallowed',
                __('Plugin installs are disabled on this site (DISALLOW_FILE_MODS). Please install Notifal Pro manually.', 'notifal')
            );
        }
        // Ensure filesystem helpers exist before requesting credentials.
        if (!function_exists('request_filesystem_credentials')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        // Prefer direct filesystem writes when the plugins directory is writable (Laragon/local).
        add_filter('filesystem_method', [$this, 'preferDirectMethod'], 10, 1);
        // Initialize the global filesystem API the same way core plugin install AJAX does.
        ob_start();
        $credentials = request_filesystem_credentials(self_admin_url(), '', false, WP_PLUGIN_DIR, null);
        ob_end_clean();
        $connected = false !== $credentials && WP_Filesystem($credentials);
        // Always remove the temporary method preference after the connect attempt.
        remove_filter('filesystem_method', [$this, 'preferDirectMethod'], 10);
        if (!$connected) {
            return new \WP_Error(
                'filesystem_unavailable',
                __('WordPress could not access the filesystem to install plugins. Please check folder permissions or install Notifal Pro manually.', 'notifal')
            );
        }
        return true;
    }
    /**
     * Prefer the direct filesystem method when the plugins directory is writable.
     *
     * @since 3.0.0
     * @param string $method Current filesystem method candidate.
     * @return string
     */
    public function preferDirectMethod($method): string
    {
        // Keep the existing method when the plugins directory cannot be written directly.
        if (!is_writable((string) WP_PLUGIN_DIR)) {
            return is_string($method) ? $method : 'direct';
        }
        // Local writable environments (Laragon, Docker bind mounts) work best with direct I/O.
        return 'direct';
    }
}
