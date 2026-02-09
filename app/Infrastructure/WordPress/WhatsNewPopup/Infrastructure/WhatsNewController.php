<?php
/**
 * What's New Controller
 *
 * Handles what's new popup logic and AJAX requests.
 *
 * @package Notifal\Infrastructure\WordPress\WhatsNewPopup\Infrastructure
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\WhatsNewPopup\Infrastructure;

use Notifal\Infrastructure\WordPress\WhatsNewPopup\Domain\WhatsNewPopup;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class WhatsNewController
 */
class WhatsNewController
{
    /**
     * WhatsNewPopup domain instance
     *
     * @var WhatsNewPopup
     */
    private WhatsNewPopup $whatsnew_popup;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->whatsnew_popup = new WhatsNewPopup();
    }

    /**
     * Register hooks and actions
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        // Create instance for handling callbacks
        $instance = new self();

        // AJAX handlers
        add_action('wp_ajax_notifal_dismiss_whatsnew_popup', [$instance, 'dismissWhatsNewPopup']);
        add_action('wp_ajax_notifal_get_whatsnew_popup_data', [$instance, 'getWhatsNewPopupData']);

        // Admin hooks
        add_action('admin_init', [$instance, 'maybeShowWhatsNewPopup'], 999); // Run very late to ensure get_current_screen is available
    }

    /**
     * Maybe show what's new popup on admin pages
     *
     * Shows popup on first visit to any Notifal page after version update.
     * For important updates, also shows on plugins.php page.
     *
     * @return void
     * @since 2.0.0
     */
    public function maybeShowWhatsNewPopup(): void
    {
        // Only run on admin pages, not during AJAX
        if (!is_admin() || wp_doing_ajax()) {
            return;
        }

        // Check if this is a Notifal page
        $current_page = isset($_GET['page']) ? Helper::sanitizeInput(wp_unslash($_GET['page']), 'text') : '';
        $is_notifal_page = strpos($current_page, 'notifal') === 0;

        // Always render popup HTML on Notifal pages for manual triggering
        if ($is_notifal_page) {
            add_action('admin_footer', [$this, 'renderWhatsNewPopup']);
            return;
        }

        // For non-Notifal pages, only render if it's an important update on plugins.php
        if ($this->whatsnew_popup->isImportantUpdate()) {
            global $pagenow;
            if ($pagenow === 'plugins.php') {
                add_action('admin_footer', [$this, 'renderWhatsNewPopup']);
            }
        }
    }

    /**
     * Dismiss what's new popup via AJAX
     *
     * @return void
     * @since 2.0.0
     */
    public function dismissWhatsNewPopup(): void
    {
        try {
            // Verify AJAX request with nonce and capabilities
            notifal_verify_ajax_request('notifal_whatsnew_popup', 'manage_options');

            // Mark current version as shown
            $this->whatsnew_popup->markCurrentVersionAsShown();

            notifal_json_success(null, __("What's new popup dismissed.", 'notifal'));

        } catch (\Exception $e) {
            Helper::logAdvanced('Error dismissing what\'s new popup: ' . $e->getMessage(), 'ERROR');
            notifal_json_error(__('An error occurred while dismissing the popup.', 'notifal'));
        }
    }

    /**
     * Get what's new popup data via AJAX
     *
     * @return void
     * @since 2.0.0
     */
    public function getWhatsNewPopupData(): void
    {
        try {
            // Verify AJAX request with nonce and capabilities
            notifal_verify_ajax_request('notifal_whatsnew_popup', 'manage_options');

            $config = $this->whatsnew_popup->getVersionConfig();

            $data = [
                'title' => $config['title'],
                'content' => $config['content'],
                'action_buttons' => $config['action_buttons'],
                'current_version' => $this->whatsnew_popup->getCurrentVersion(),
                'nonce' => wp_create_nonce('notifal_whatsnew_popup'),
            ];

            notifal_json_success($data);

        } catch (\Exception $e) {
            Helper::logAdvanced('Error getting what\'s new popup data: ' . $e->getMessage(), 'ERROR');
            notifal_json_error(__('An error occurred while loading popup data.', 'notifal'));
        }
    }

    /**
     * Check if popup should show on current page
     *
     * @return bool True if popup should show on current page
     * @since 2.0.0
     */
    private function shouldShowOnCurrentPage(): bool
    {
        // Check if this is a Notifal page
        $current_page = isset($_GET['page']) ? Helper::sanitizeInput(wp_unslash($_GET['page']), 'text') : '';
        $is_notifal_page = strpos($current_page, 'notifal') === 0;

        if ($is_notifal_page) {
            // Always show on Notifal pages
            return true;
        } else {
            // For important updates, also show on plugins.php page
            if ($this->whatsnew_popup->isImportantUpdate()) {
                // Check if we're on plugins.php page
                global $pagenow;
                if ($pagenow === 'plugins.php') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Render what's new popup HTML
     *
     * @return void
     * @since 2.0.0
     */
    public function renderWhatsNewPopup(): void
    {
        // Don't render during AJAX requests
        if (wp_doing_ajax()) {
            return;
        }

        $config = $this->whatsnew_popup->getVersionConfig();

        // Determine if popup should show automatically
        $should_auto_show = $this->whatsnew_popup->shouldShowWhatsNewPopup() &&
                           $this->shouldShowOnCurrentPage();

        ?>
        <div id="notifal-whatsnew-popup"
             class="notifal-whatsnew-popup-overlay"
             style="display: none;"
             data-auto-show="<?php echo $should_auto_show ? 'true' : 'false'; ?>">
            <div class="notifal-whatsnew-popup-content">
                <div class="notifal-whatsnew-popup-header">
                    <button type="button" class="notifal-whatsnew-popup-close" aria-label="<?php esc_attr_e('Close', 'notifal'); ?>">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                    <div class="notifal-whatsnew-popup-welcome-icon" aria-hidden="true">
                        <span class="notifal-popup-emoji">✨</span>
                    </div>
                    <h2><?php echo esc_html($config['title']); ?></h2>
                </div>
                <div class="notifal-whatsnew-popup-body">
                    <div class="notifal-whatsnew-popup-description">
                        <?php echo wp_kses_post($config['content']); ?>
                    </div>
                    <div class="notifal-whatsnew-popup-actions">
                        <?php foreach ($config['action_buttons'] as $button): ?>
                            <?php if (isset($button['close']) && $button['close']): ?>
                                <button type="button"
                                        class="notifal-whatsnew-popup-button notifal-whatsnew-popup-button-secondary"
                                        data-button-id="<?php echo esc_attr($button['id']); ?>">
                                    <span class="dashicons <?php echo esc_attr($button['icon']); ?>"></span>
                                    <?php echo esc_html($button['text']); ?>
                                </button>
                            <?php else: ?>
                                <a href="<?php echo esc_url($button['url']); ?>"
                                   class="notifal-whatsnew-popup-button <?php echo isset($button['primary']) && $button['primary'] ? 'notifal-whatsnew-popup-button-primary' : ''; ?> <?php echo isset($button['external']) && $button['external'] ? 'notifal-whatsnew-popup-button-external' : ''; ?>"
                                   data-button-id="<?php echo esc_attr($button['id']); ?>"
                                   <?php echo isset($button['external']) && $button['external'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                    <span class="dashicons <?php echo esc_attr($button['icon']); ?>"></span>
                                    <?php echo esc_html($button['text']); ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
