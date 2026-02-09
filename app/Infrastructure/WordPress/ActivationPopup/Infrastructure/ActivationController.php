<?php
/**
 * Activation Controller
 *
 * Handles activation popup logic and AJAX requests.
 *
 * @package Notifal\Infrastructure\WordPress\ActivationPopup\Infrastructure
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\ActivationPopup\Infrastructure;

use Notifal\Infrastructure\WordPress\ActivationPopup\Domain\ActivationPopup;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class ActivationController
 */
class ActivationController
{
    /**
     * ActivationPopup domain instance
     *
     * @var ActivationPopup
     */
    private ActivationPopup $activation_popup;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->activation_popup = new ActivationPopup();
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
        add_action('wp_ajax_notifal_dismiss_activation_popup', [$instance, 'dismissActivationPopup']);
        add_action('wp_ajax_notifal_get_activation_popup_data', [$instance, 'getActivationPopupData']);

        // High priority admin_init to handle activation redirects (runs early)
        add_action('admin_init', [$instance, 'handleActivationRedirect'], 1);

        // Normal priority admin_init to show popup (runs after redirects)
        add_action('admin_init', [$instance, 'maybeShowActivationPopup'], 10);
    }

    /**
     * Handle activation redirect on admin_init
     *
     * Checks for pending activation redirect transient and redirects to notification list.
     * This runs with high priority (1) to ensure it happens before other admin_init actions.
     *
     * @return void
     * @since 2.0.0
     */
    public function handleActivationRedirect(): void
    {
        // Only run on admin pages, not during AJAX or network admin
        if (!is_admin() || wp_doing_ajax() || is_network_admin()) {
            return;
        }

        // Check if there's a pending activation redirect
        if (!get_transient('notifal_pending_activation_redirect')) {
            return;
        }

        // Check if popup should be shown (first-time activation)
        if (!$this->activation_popup->shouldShowActivationPopup()) {
            delete_transient('notifal_pending_activation_redirect');
            return;
        }

        // Delete the transient to prevent multiple redirects
        delete_transient('notifal_pending_activation_redirect');

        // Redirect to notification list page with activation parameter
        $redirect_url = add_query_arg([
            'notifal_activation' => 'true',
            'page' => 'notifal-onpage-notifications'
        ], admin_url('admin.php'));

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Maybe show activation popup on admin pages
     *
     * Shows popup in two scenarios:
     * 1. After automatic redirect to notification list page (notifal_activation=true)
     * 2. When manually added to URL and not on a Notifal page (notifal_activation=true)
     *
     * @return void
     * @since 2.0.0
     */
    public function maybeShowActivationPopup(): void
    {
        // Only run on admin pages, not during AJAX
        if (!is_admin() || wp_doing_ajax()) {
            return;
        }

        // Check if activation parameter is present
        if (!isset($_GET['notifal_activation']) || $_GET['notifal_activation'] !== 'true') {
            return;
        }

        // Check if popup should be shown (first-time activation)
        if (!$this->activation_popup->shouldShowActivationPopup()) {
            return;
        }

        // Determine if we should show popup based on current page context
        $should_show_popup = false;
        $current_page = isset($_GET['page']) ? Helper::sanitizeInput(wp_unslash($_GET['page']), 'text') : '';

        // Scenario 1: We're on the notification list page after activation redirect
        if ($current_page === 'notifal-onpage-notifications') {
            $should_show_popup = true;
        }
        // Scenario 2: Manual URL parameter on any page (allows testing on Notifal pages too)
        else {
            $should_show_popup = true;
        }

        if (!$should_show_popup) {
            return;
        }

        // Enqueue popup script
        add_action('admin_footer', [$this, 'renderActivationPopup']);
    }

    /**
     * Dismiss activation popup via AJAX
     *
     * @return void
     * @since 2.0.0
     */
    public function dismissActivationPopup(): void
    {
        try {
            // Verify AJAX request with nonce and capabilities
            notifal_verify_ajax_request('notifal_activation_popup', 'manage_options');

            // Mark popup as shown
            $this->activation_popup->markActivationPopupAsShown();

            notifal_json_success(null, __('Activation popup dismissed.', 'notifal'));

        } catch (\Exception $e) {
            Helper::logAdvanced('Error dismissing activation popup: ' . $e->getMessage(), 'ERROR');
            notifal_json_error(__('An error occurred while dismissing the popup.', 'notifal'));
        }
    }

    /**
     * Get activation popup data via AJAX
     *
     * @return void
     * @since 2.0.0
     */
    public function getActivationPopupData(): void
    {
        try {
            // Verify GET request with nonce and capabilities
            notifal_verify_get_request('notifal_activation_popup', 'manage_options');

            $data = [
                'welcome_message' => $this->activation_popup->getWelcomeMessage(),
                'welcome_description' => $this->activation_popup->getWelcomeDescription(),
                'action_buttons' => $this->activation_popup->getActionButtons(),
                'nonce' => wp_create_nonce('notifal_activation_popup'),
            ];

            notifal_json_success($data);

        } catch (\Exception $e) {
            Helper::logAdvanced('Error getting activation popup data: ' . $e->getMessage(), 'ERROR');
            notifal_json_error(__('An error occurred while loading popup data.', 'notifal'));
        }
    }

    /**
     * Render activation popup HTML
     *
     * @return void
     * @since 2.0.0
     */
    public function renderActivationPopup(): void
    {
        // Check if popup should be shown
        if (!$this->activation_popup->shouldShowActivationPopup()) {
            return;
        }

        // Don't render during AJAX requests
        if (wp_doing_ajax()) {
            return;
        }

        ?>
        <div id="notifal-activation-popup" class="notifal-activation-popup-overlay" style="display: none;">
            <div class="notifal-activation-popup-content">
                <div class="notifal-activation-popup-header">
                    <button type="button" class="notifal-activation-popup-close" aria-label="<?php esc_attr_e('Close', 'notifal'); ?>">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                    <div class="notifal-activation-popup-welcome-icon" aria-hidden="true">
                        <span class="notifal-popup-emoji">🎉</span>
                    </div>
                    <h2><?php echo esc_html($this->activation_popup->getWelcomeMessage()); ?></h2>
                </div>
                <div class="notifal-activation-popup-body">
                    <p class="notifal-activation-popup-description">
                        <?php echo esc_html($this->activation_popup->getWelcomeDescription()); ?>
                    </p>
                    <div class="notifal-activation-popup-actions">
                        <?php foreach ($this->activation_popup->getActionButtons() as $button): ?>
                            <a href="<?php echo esc_url($button['url']); ?>"
                               class="notifal-activation-popup-button <?php echo isset($button['primary']) && $button['primary'] ? 'notifal-activation-popup-button-primary' : ''; ?> <?php echo isset($button['external']) && $button['external'] ? 'notifal-activation-popup-button-external' : ''; ?>"
                               data-button-id="<?php echo esc_attr($button['id']); ?>"
                               <?php echo isset($button['external']) && $button['external'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                <span class="dashicons <?php echo esc_attr($button['icon']); ?>"></span>
                                <?php echo esc_html($button['text']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
