<?php

namespace Notifal\Shared\Services;

use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\Ai\Config\AiCharacter;
use Notifal\Modules\Ai\Config\Paths as AiPaths;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardStateService;
use Notifal\Modules\SetupWizard\Presentation\Admin\Menu\MenuController;
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
 * @updated 3.0.0 Prefer Setup Wizard notice while onboarding is incomplete.
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
        // Load Noti face styles before admin notices render when the wizard invite will show.
        add_action('admin_enqueue_scripts', [self::class, 'enqueueWizardNoticeAssets']);
        add_action('admin_notices', [self::class, 'renderNotices']);
        add_action('admin_notices', [self::class, 'filterNoticesOnNotifalPages'], 1);
        add_action('wp_ajax_notifal_dismiss_notice', [self::class, 'dismissNotice']);
    }

    /**
     * Enqueue Noti face CSS for the Setup Wizard invite notice.
     *
     * @return void
     * @since 3.0.0
     */
    public static function enqueueWizardNoticeAssets(): void
    {
        // Skip when the wizard invite will not render on this request.
        if (!self::shouldRenderSetupWizardNotice()) {
            return;
        }

        // Skip when a parent style already bundled the Noti face rules.
        if (
            wp_style_is('notifal-ai-wizard-css', 'enqueued')
            || wp_style_is('notifal-ai-wizard-css', 'done')
            || wp_style_is('notifal-noti-face-css', 'enqueued')
            || wp_style_is('notifal-noti-face-css', 'done')
        ) {
            return;
        }

        // Enqueue the standalone Vite-built Noti face stylesheet.
        notifal_enqueue_style(
            'notifal-noti-face-css',
            AiPaths::cssAdminBuildUrl() . 'NotiFaceStyle.css',
            ['notifal-global-backend']
        );
    }

    /**
     * Whether the Setup Wizard invite notice should render.
     *
     * @return bool
     * @since 3.0.0
     */
    private static function shouldRenderSetupWizardNotice(): bool
    {
        // Never show the invite on the fullscreen wizard screen itself.
        if (MenuController::isCurrentScreen()) {
            return false;
        }

        // Resolve Setup Wizard completion state from the module container.
        $wizardState = notifal_app(SetupWizardStateService::class);

        // Completed setups no longer need the wizard invite.
        if (!$wizardState->isIncomplete()) {
            return false;
        }

        // Respect a prior dismiss so the admin can clear the prompt deliberately.
        $dismissed_notices = get_option('notifal_dismissed_notices', []);

        return !in_array('setup_wizard', $dismissed_notices, true);
    }

    /**
     * Render all active notices.
     *
     * @return void
     * @since 2.0.0
     * @updated 3.0.0 Show Setup Wizard notice before the Pro upgrade notice.
     */
    public static function renderNotices(): void
    {
        // Check system requirements first (critical)
        self::renderSystemRequirementsNotices();

        // Resolve Setup Wizard completion state from the module container.
        $wizardState = notifal_app(SetupWizardStateService::class);

        // While onboarding is incomplete, prioritize the Setup Wizard CTA over Pro upgrade.
        if ($wizardState->isIncomplete()) {
            self::renderSetupWizardNotice();
            return;
        }

        // After wizard completion, promote Pro when it is not active yet.
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
     * Render the Setup Wizard invite notice from Noti's voice.
     *
     * Shown while the wizard is incomplete so admins finish guided setup first.
     *
     * @return void
     * @since 3.0.0
     */
    private static function renderSetupWizardNotice(): void
    {
        // Share eligibility checks with the asset enqueue path.
        if (!self::shouldRenderSetupWizardNotice()) {
            return;
        }

        // Build the admin URL that opens the fullscreen Setup Wizard.
        $wizard_url = admin_url('admin.php?page=' . MenuController::PAGE_SLUG);

        // Resolve status so the CTA label matches first-run vs resume UX.
        $wizardState = notifal_app(SetupWizardStateService::class);
        $wizard_status = $wizardState->getStatus();

        // Use a resume label when the admin already started or skipped setup.
        $cta_label = in_array($wizard_status, ['in_progress', 'skipped'], true)
            ? __('Continue Setup', 'notifal')
            : __('Open Setup Wizard', 'notifal');

        ?>
        <div class="notice notice-info is-dismissible notifal-pro-notice notifal-wizard-notice" data-notice-id="setup_wizard">
            <div class="notifal-notice-content">
                <div class="notifal-notice-icon notifal-notice-icon--noti" aria-hidden="true">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- AiCharacter::renderFaceHtml() returns fixed safe markup.
                    echo AiCharacter::renderFaceHtml('md');
                    ?>
                </div>
                <div class="notifal-notice-text">
                    <h4><?php esc_html_e('Hey! I\'m Noti, ready to set up Notifal for you', 'notifal'); ?></h4>
                    <p><?php esc_html_e('I\'m already looking at your site. Open the Setup Wizard and I\'ll analyze it, suggest high-impact ideas that help grow sales, and configure Notifal for you from start to finish.', 'notifal'); ?></p>
                    <p>
                        <a href="<?php echo esc_url($wizard_url); ?>" class="button button-primary">
                            <?php echo esc_html($cta_label); ?>
                        </a>
                        <a href="#" class="notifal-notice-dismiss" data-notice-id="setup_wizard" data-nonce="<?php echo esc_attr(wp_create_nonce('notifal_dismiss_notice')); ?>">
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

        // License manager URL with UTM tracking for the admin pro upgrade notice CTA.
        $upgrade_url = Urls::withCustomUtm(Urls::LICENSE_MANAGER, [
            'utm_medium' => 'admin_notice',
            'utm_campaign' => 'notifal_pro_upgrade',
            'utm_content' => 'upgrade_notice_dismissible',
        ]);

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
                        <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Upgrade for Free!', 'notifal'); ?>
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
