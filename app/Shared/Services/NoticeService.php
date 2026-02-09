<?php

namespace Notifal\Shared\Services;

use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Shared\Helpers\AdminScreenDetector;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class NoticeService
 *
 * Handles WordPress admin notices for Notifal plugin.
 * Provides functionality to show dismissible notices with proper styling.
 *
 * @since 2.0.0
 * @package Notifal\Shared\Services
 * @author Hossein <hossein@notifal.com>
 */
class NoticeService
{
    /**
     * Register WordPress hooks for notice handling.
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        add_action('admin_notices', [self::class, 'renderNotices']);
        add_action('admin_notices', [self::class, 'filterNoticesOnNotifalPages'], 1);
        add_action('wp_ajax_notifal_dismiss_notice', [self::class, 'dismissNotice']);
    }

    /**
     * Render all active notices.
     *
     * @return void
     * @since 2.0.0
     */
    public static function renderNotices(): void
    {
        // Check system requirements first (critical)
        self::renderSystemRequirementsNotices();

        // Check if Notifal Pro is not active (not activated, not just files exist)
        if (!PluginDetector::isNotifalProActive()) {
            self::renderProUpgradeNotice();
        }
    }

    /**
     * Render system requirements notices.
     *
     * Checks for critical PHP extensions and displays warning notices if missing.
     *
     * @return void
     * @since 2.0.0
     */
    private static function renderSystemRequirementsNotices(): void
    {
        // Check if ZIP extension is available
        if (!class_exists('ZipArchive')) {
            self::renderZipExtensionNotice();
        }
    }

    /**
     * Render notice about missing ZIP extension.
     *
     * @return void
     * @since 2.0.0
     */
    private static function renderZipExtensionNotice(): void
    {
        // Check if notice was dismissed
        $dismissed_notices = get_option('notifal_dismissed_notices', []);
        if (in_array('zip_extension_missing', $dismissed_notices)) {
            return;
        }

        ?>
        <div class="notice notice-warning is-dismissible notifal-system-notice" data-notice-id="zip_extension_missing">
            <div class="notifal-notice-content">
                <div class="notifal-notice-icon">
                    <span class="dashicons dashicons-warning" style="color: #f56e28; font-size: 24px;"></span>
                </div>
                <div class="notifal-notice-text">
                    <h4><?php esc_html_e('PHP ZIP Extension Not Available', 'notifal'); ?></h4>
                    <p>
                        <?php 
                        esc_html_e('The PHP ZIP extension is not enabled on your server. This extension is required for importing and exporting pre-created notifications with bundled media files.', 'notifal'); 
                        ?>
                    </p>
                    <p>
                        <strong><?php esc_html_e('What you can still do:', 'notifal'); ?></strong><br>
                        <?php esc_html_e('You can still import/export JSON files without media, but importing notifications with bundled images will not work.', 'notifal'); ?>
                    </p>
                    <p>
                        <strong><?php esc_html_e('How to fix:', 'notifal'); ?></strong><br>
                        <?php esc_html_e('Contact your hosting provider to enable the PHP ZIP extension, or enable it in your PHP configuration if you have server access.', 'notifal'); ?>
                    </p>
                    <p>
                        <a href="#" class="notifal-notice-dismiss" data-notice-id="zip_extension_missing" data-nonce="<?php echo esc_attr(wp_create_nonce('notifal_dismiss_notice')); ?>">
                            <?php esc_html_e('Dismiss', 'notifal'); ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the Notifal Pro upgrade notice.
     *
     * @return void
     * @since 2.0.0
     */
    private static function renderProUpgradeNotice(): void
    {
        // Check if notice was dismissed
        $dismissed_notices = get_option('notifal_dismissed_notices', []);
        if (in_array('pro_upgrade', $dismissed_notices)) {
            return;
        }

        $pricing_url = add_query_arg([
            'utm_source' => 'wordpress_plugin',
            'utm_medium' => 'admin_notice',
            'utm_campaign' => 'notifal_pro_upgrade',
            'utm_content' => 'upgrade_notice_dismissible',
            'domain' => parse_url(get_site_url(), PHP_URL_HOST)
        ], Urls::PRICING);

        ?>
        <div class="notice notice-info is-dismissible notifal-pro-notice" data-notice-id="pro_upgrade">
            <div class="notifal-notice-content">
                <div class="notifal-notice-icon">
                    <span class="notifal-icon notifal-icon-rocket size-24"></span>
                </div>
                <div class="notifal-notice-text">
                    <h4><?php esc_html_e('Unlock Advanced Features with Notifal Pro', 'notifal'); ?></h4>
                    <p><?php esc_html_e('Get advanced targeting, multiple notifications, custom styling, and detailed analytics with Notifal Pro.', 'notifal'); ?></p>
                    <p>
                        <a href="<?php echo esc_url($pricing_url); ?>" class="button button-primary" target="_blank">
                            <?php esc_html_e('Upgrade Now', 'notifal'); ?>
                        </a>
                        <a href="#" class="notifal-notice-dismiss" data-notice-id="pro_upgrade" data-nonce="<?php echo esc_attr(wp_create_nonce('notifal_dismiss_notice')); ?>">
                            <?php esc_html_e('Dismiss', 'notifal'); ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Filter admin notices on Notifal pages to show only Notifal-related notices.
     *
     * Removes all admin notices except those generated by Notifal and Notifal Pro plugins
     * when the user is on any Notifal admin page.
     *
     * @return void
     * @since 2.0.0
     */
    public static function filterNoticesOnNotifalPages(): void
    {
        // Only filter notices if we're on a Notifal page
        if (!AdminScreenDetector::isNotifalPage()) {
            return;
        }

        // Get all admin notice actions
        global $wp_filter;
        if (!isset($wp_filter['admin_notices']) || !isset($wp_filter['admin_notices']->callbacks)) {
            return;
        }

        $callbacks = $wp_filter['admin_notices']->callbacks;

        // Loop through all priorities
        foreach ($callbacks as $priority => $priority_callbacks) {
            foreach ($priority_callbacks as $callback_key => $callback) {
                // Skip if this is our own renderNotices method
                if (is_array($callback['function']) &&
                    $callback['function'][0] === self::class &&
                    $callback['function'][1] === 'renderNotices') {
                    continue;
                }

                // Skip if this is our own filter method
                if (is_array($callback['function']) &&
                    $callback['function'][0] === self::class &&
                    $callback['function'][1] === 'filterNoticesOnNotifalPages') {
                    continue;
                }

                // Check if this is a Notifal Pro notice
                $is_notifal_pro_notice = false;
                if (is_array($callback['function'])) {
                    $class_name = is_object($callback['function'][0]) ? get_class($callback['function'][0]) : $callback['function'][0];
                    $method_name = $callback['function'][1] ?? '';

                    // Check if it's from Notifal Pro namespace or class
                    if (strpos($class_name, 'NotifalPro\\') === 0 ||
                        strpos($class_name, 'Notifal_Pro\\') === 0 ||
                        $class_name === 'NotifalPro\Domain\License\Services\LicenseValidator' ||
                        strpos($method_name, 'notifal_pro_') === 0) {
                        $is_notifal_pro_notice = true;
                    }
                } elseif (is_string($callback['function'])) {
                    // Check function name for Notifal Pro indicators
                    if (strpos($callback['function'], 'notifal_pro_') === 0 ||
                        strpos($callback['function'], 'notifalpro_') === 0) {
                        $is_notifal_pro_notice = true;
                    }
                }

                // Remove the callback if it's not from Notifal Pro
                if (!$is_notifal_pro_notice) {
                    remove_action('admin_notices', $callback['function'], $priority);
                }
            }
        }
    }

    /**
     * Handle AJAX request to dismiss a notice.
     *
     * @return void
     * @since 2.0.0
     */
    public static function dismissNotice(): void
    {
        // Verify nonce and user capabilities
        notifal_verify_ajax_request('notifal_dismiss_notice', 'manage_options', 'nonce');

        $notice_id = Helper::sanitizeInput($_POST['notice_id'] ?? '', 'key');
        if (empty($notice_id)) {
            notifal_json_error(__('Invalid notice ID', 'notifal'));
            return;
        }

        // Get current dismissed notices
        $dismissed_notices = get_option('notifal_dismissed_notices', []);

        // Add this notice to dismissed list
        if (!in_array($notice_id, $dismissed_notices)) {
            $dismissed_notices[] = $notice_id;
            update_option('notifal_dismissed_notices', $dismissed_notices);
        }

        notifal_json_success(__('Notice dismissed successfully.', 'notifal'));
    }
}
