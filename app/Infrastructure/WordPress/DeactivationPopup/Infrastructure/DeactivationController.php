<?php
/**
 * Deactivation Controller
 *
 * Handles AJAX requests and form processing for deactivation popup.
 *
 * @package Notifal\Infrastructure\WordPress\DeactivationPopup\Infrastructure
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\DeactivationPopup\Infrastructure;

use Notifal\Infrastructure\WordPress\DeactivationPopup\Domain\DeactivationPopup;
use Notifal\Infrastructure\WordPress\DeactivationPopup\Domain\DeactivationData;
use Notifal\Infrastructure\WordPress\DeactivationPopup\Domain\DeactivationReason;
use Notifal\Infrastructure\WordPress\DeactivationPopup\Application\DeactivationApiService;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class DeactivationController
 */
class DeactivationController
{
    /**
     * DeactivationPopup domain instance
     *
     * @var DeactivationPopup
     */
    private DeactivationPopup $deactivation_popup;

    /**
     * DeactivationApiService instance
     *
     * @var DeactivationApiService
     */
    private DeactivationApiService $api_service;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->deactivation_popup = new DeactivationPopup();
        $this->api_service = new DeactivationApiService();
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
        add_action('wp_ajax_notifal_process_deactivation_feedback', [$instance, 'processDeactivationFeedback']);
        add_action('wp_ajax_notifal_send_deactivation_feedback', [$instance, 'sendDeactivationFeedbackAsync']);

        // Plugin deactivation hook
        add_action('deactivate_plugin', [$instance, 'handlePluginDeactivation'], 10, 2);

        // Delayed API call hook
        add_action('notifal_delayed_deactivation_api_call', [$instance, 'processDelayedApiCall'], 10, 1);

        // Display prevention notice if transient exists
        add_action('admin_notices', [$instance, 'displayPreventionNotice']);

        // Render deactivation popup on plugins page
        add_action('admin_footer', [$instance, 'renderDeactivationPopup'],1);

    }

    /**
     * Process deactivation feedback via AJAX
     *
     * @return void
     * @since 2.0.0
     */
    public function processDeactivationFeedback(): void
    {
        try {
            // Verify AJAX request with nonce and capabilities
            notifal_verify_ajax_request('notifal_deactivation_feedback', 'deactivate_plugins');

            // Get and sanitize feedback data
            $feedback_data = $this->sanitizeFeedbackData($_POST);

            // Process feedback through domain logic
            $deactivation_data = $this->deactivation_popup->processFeedback($feedback_data);

            // Check if deactivation should be prevented
            if ($this->deactivation_popup->shouldPreventDeactivation($deactivation_data)) {
                notifal_json_success([
                    'prevent_deactivation' => true,
                    'message' => $this->deactivation_popup->getProPreventionMessage(),
                ]);
                return;
            }

            // Send feedback to API
            $api_success = $this->api_service->sendDeactivationFeedback($deactivation_data);

            // Update deactivation tracking
            $this->deactivation_popup->incrementDeactivationCount();
            $this->deactivation_popup->updateLastDeactivationTime();
            $this->deactivation_popup->setFirstDeactivationTimeIfNotSet();

            notifal_json_success([
                'message' => __('Feedback submitted successfully. Deactivating plugin...', 'notifal'),
                'api_success' => $api_success,
            ]);

        } catch (\InvalidArgumentException $e) {
            notifal_json_error($e->getMessage());
        } catch (\Exception $e) {
            Helper::logAdvanced('Error processing deactivation feedback: ' . $e->getMessage(), 'ERROR');
            notifal_json_error(__('An error occurred while processing your feedback.', 'notifal'));
        }
    }

    /**
     * Handle plugin deactivation attempts
     *
     * @param string $plugin Plugin file path
     * @param bool $network_wide Whether it's a network-wide deactivation
     * @return void
     * @since 2.0.0
     */
    public function handlePluginDeactivation(string $plugin, bool $network_wide): void
    {
        // Only intercept Notifal plugin deactivation
        if ($plugin !== NOTIFAL_BASENAME) {
            return;
        }

        // If Pro is active, prevent deactivation
        if ($this->deactivation_popup->isNotifalProActive()) {
            // Show admin notice and prevent deactivation
            set_transient('notifal_deactivation_prevented_notice', $this->deactivation_popup->getProPreventionMessage(), 30);

            // Redirect back to plugins page
            wp_redirect(admin_url('plugins.php'));
            exit;
        }

        // Check if this is a skip deactivation and send data directly to API
        $skip_data = get_transient('notifal_skip_deactivation_data');
        if ($skip_data) {
            // Delete transient immediately to prevent duplicate sends
            delete_transient('notifal_skip_deactivation_data');

            // Send data directly to API (bypass AJAX to avoid 400 errors)
            $this->sendSkipFeedbackDirect($skip_data);
        }

        // Update deactivation tracking for all deactivations
        $this->deactivation_popup->incrementDeactivationCount();
        $this->deactivation_popup->updateLastDeactivationTime();
        $this->deactivation_popup->setFirstDeactivationTimeIfNotSet();
    }



    /**
     * Send deactivation feedback asynchronously
     *
     * @return void
     * @since 2.0.0
     */
    public function sendDeactivationFeedbackAsync(): void
    {
        try {
            // Verify nonce (wp_unslash required when reading from POST per WordPress)
            $nonce = isset($_POST['nonce']) ? wp_unslash($_POST['nonce']) : '';
            if (!wp_verify_nonce($nonce, 'notifal_deactivation_async')) {
                return; // Silent failure for async calls
            }

            // Only users who can deactivate plugins may send deactivation feedback
            if (!current_user_can('deactivate_plugins')) {
                return;
            }

            // Get deactivation data from POST (wp_unslash per WordPress; JSON validated after decode)
            $deactivation_data_json = isset($_POST['deactivation_data']) ? wp_unslash($_POST['deactivation_data']) : '';
            if (empty($deactivation_data_json)) {
                return;
            }

            $deactivation_data_array = json_decode($deactivation_data_json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return;
            }

            $deactivation_data = new DeactivationData($deactivation_data_array);
            $this->api_service->sendDeactivationFeedback($deactivation_data);

        } catch (\Exception $e) {
            // Silent failure for async calls, but log with details
            $domain = isset($deactivation_data_array['domain']) ? $deactivation_data_array['domain'] : 'unknown';
            Helper::logAdvanced(sprintf(
                'Async API call failed (Domain: %s): %s in %s:%s',
                $domain,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ), 'ERROR');
        }
    }

    /**
     * Process delayed API call
     *
     * @param array $args Arguments passed to the scheduled event
     * @return void
     * @since 2.0.0
     */
    public function processDelayedApiCall(array $args): void
    {
        try {
            $deactivation_data_array = $args['deactivation_data'] ?? [];
            if (empty($deactivation_data_array)) {
                return;
            }

            $deactivation_data = new DeactivationData($deactivation_data_array);
            $this->api_service->sendDeactivationFeedback($deactivation_data);

        } catch (\Exception $e) {
            $domain = isset($deactivation_data_array['domain']) ? $deactivation_data_array['domain'] : 'unknown';
            Helper::logAdvanced(sprintf(
                'Delayed API call failed (Domain: %s): %s in %s:%s',
                $domain,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ), 'ERROR');
        }
    }

    /**
     * Send skip feedback asynchronously via AJAX (similar to submit approach)
     *
     * @param array $skip_data Deactivation data to send
     * @return void
     * @since 2.0.0
     */
    private function sendSkipFeedbackDirect(array $skip_data): void
    {
        try {
            // Convert array to DeactivationData object
            $deactivation_data = new DeactivationData($skip_data);

            // Call the non-blocking API service for skip deactivation (instant deactivation)
            $this->api_service->sendDeactivationFeedbackNonBlocking($deactivation_data);

        } catch (\Exception $e) {
            // Silent failure for skip deactivation to avoid blocking
        }
    }


    /**
     * Display prevention notice if transient exists
     *
     * @return void
     * @since 2.0.0
     */
    public function displayPreventionNotice(): void
    {
        $notice = get_transient('notifal_deactivation_prevented_notice');
        if ($notice) {
            delete_transient('notifal_deactivation_prevented_notice');
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($notice) . '</p></div>';
        }
    }

    /**
     * Sanitize feedback data from POST request
     *
     * @param array $post_data Raw POST data
     * @return array Sanitized feedback data
     * @since 2.0.0
     */
    private function sanitizeFeedbackData(array $post_data): array
    {
        return Helper::sanitizePostData([
            'reason' => 'text',
            'plugin_found' => 'text',
            'additional_feedback' => 'textarea',
        ]);
    }

    /**
     * Render deactivation popup HTML
     *
     * @return void
     * @since 2.0.0
     * @since 2.3.5 Adds help panel, team note, reason-select wrapper, and per-reason help text for "Other".
     */
    public function renderDeactivationPopup(): void
    {
        // Only render on plugins page
        $current_screen = get_current_screen();
        if (!$current_screen || $current_screen->id !== 'plugins') {
            return;
        }

        // Don't render during AJAX requests
        if (wp_doing_ajax()) {
            return;
        }

        // Get reasons configuration
        $reasons = DeactivationReason::getReasonsConfiguration();

        ?>
        <div class="notifal-deactivation-overlay" id="notifal-deactivation-overlay" style="display: none;">
            <div class="notifal-deactivation-modal" id="notifal-deactivation-modal" role="dialog" aria-modal="true" aria-labelledby="notifal-deactivation-title">
                <div class="notifal-deactivation-header">
                    <h2 id="notifal-deactivation-title" class="notifal-deactivation-title">
                        <?php esc_html_e("Before you go, please share why you're deactivating Notifal", "notifal"); ?>
                    </h2>
                    <button type="button" class="notifal-deactivation-close" id="notifal-deactivation-close" aria-label="<?php esc_attr_e('Close', 'notifal'); ?>">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>
                <div class="notifal-deactivation-body">
                    <?php $this->renderDeactivationHelpPanel(); ?>
                    <form id="notifal-deactivation-form">
                        <?php $this->renderDeactivationTeamNote(); ?>
                        <ul class="notifal-deactivation-reasons" id="notifal-deactivation-reasons">
                            <?php foreach ($reasons as $reason): ?>
                                <li class="notifal-deactivation-reason">
                                    <div class="notifal-deactivation-reason-select">
                                        <input type="radio" name="notifal_deactivation_reason" value="<?php echo esc_attr($reason['value']); ?>" class="notifal-deactivation-reason-input" id="reason-<?php echo esc_attr($reason['value']); ?>">
                                        <label for="reason-<?php echo esc_attr($reason['value']); ?>" class="notifal-deactivation-reason-label">
                                            <?php echo esc_html($reason['label']); ?>
                                        </label>
                                    </div>
                                    <?php if (isset($reason['requiresInput']) && $reason['requiresInput']): ?>
                                        <div class="notifal-deactivation-input-container" style="display: none;">
                                            <?php $input_type = $reason['inputType'] ?? 'text'; ?>
                                            <?php if ($input_type === 'textarea'): ?>
                                                <textarea
                                                    name="<?php echo esc_attr($reason['inputName']); ?>"
                                                    class="notifal-deactivation-input notifal-deactivation-textarea"
                                                    placeholder="<?php echo esc_attr($reason['inputPlaceholder']); ?>"
                                                    rows="2"
                                                ></textarea>
                                            <?php else: ?>
                                                <input
                                                    type="<?php echo esc_attr($input_type); ?>"
                                                    name="<?php echo esc_attr($reason['inputName']); ?>"
                                                    class="notifal-deactivation-input"
                                                    placeholder="<?php echo esc_attr($reason['inputPlaceholder']); ?>"
                                                >
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($reason['showHelpText']) && $reason['showHelpText']): ?>
                                        <div class="notifal-deactivation-help-text" id="help-<?php echo esc_attr($reason['value']); ?>" style="display: none;">
                                            <?php
                                            if ($reason['value'] === DeactivationReason::OTHER) {
                                                echo esc_html($this->getOtherReasonHelpText());
                                            } else {
                                                echo wp_kses(
                                                    $this->getCouldNotWorkHelpText(),
                                                    ['a' => ['href' => [], 'target' => [], 'rel' => []]]
                                                );
                                            }
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </form>
                </div>
                <div class="notifal-deactivation-footer">
                    <div class="notifal-deactivation-buttons">
                        <button type="button" class="notifal-deactivation-btn notifal-deactivation-btn-primary" id="notifal-submit-deactivate" disabled>
                            <?php esc_html_e('Submit & Deactivate', 'notifal'); ?>
                        </button>
                        <button type="button" class="notifal-deactivation-btn notifal-deactivation-btn-secondary" id="notifal-skip-deactivate">
                            <?php esc_html_e('Skip & Deactivate', 'notifal'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render help panel with support and free configuration options.
     *
     * @return void
     * @since 2.3.5 Outputs two compact rows: support ticket (Urls::SUPPORT_PAGE) and free setup (Urls::FREE_CONFIGURATION).
     */
    private function renderDeactivationHelpPanel(): void
    {
        $support_url = Urls::withCustomUtm(Urls::SUPPORT_PAGE, [
            'utm_medium' => 'deactivation_popup',
            'utm_campaign' => 'notifal_support',
            'utm_content' => 'support_ticket_link',
        ]);

        $free_configuration_url = Urls::withCustomUtm(Urls::FREE_CONFIGURATION, [
            'utm_medium' => 'deactivation_popup',
            'utm_campaign' => 'notifal_free_configuration',
            'utm_content' => 'free_configuration_link',
        ]);
        ?>
        <div
            class="notifal-deactivation-help-panel"
            role="region"
            aria-label="<?php esc_attr_e('Get help before deactivating', 'notifal'); ?>"
        >
            <div class="notifal-deactivation-help-rows">
                <div class="notifal-deactivation-help-row notifal-deactivation-help-row--support">
                    <span class="notifal-deactivation-help-row__icon dashicons dashicons-sos" aria-hidden="true"></span>
                    <p class="notifal-deactivation-help-row__text">
                        <strong><?php esc_html_e('Need help?', 'notifal'); ?></strong>
                        <?php esc_html_e('Open a ticket. We reply within 2 hours.', 'notifal'); ?>
                    </p>
                    <a
                        class="notifal-deactivation-help-row__btn notifal-deactivation-help-row__btn--primary"
                        href="<?php echo esc_url($support_url); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <?php esc_html_e('Support', 'notifal'); ?>
                    </a>
                </div>
                <div class="notifal-deactivation-help-row notifal-deactivation-help-row--setup">
                    <span class="notifal-deactivation-help-row__icon dashicons dashicons-admin-tools" aria-hidden="true"></span>
                    <p class="notifal-deactivation-help-row__text">
                        <strong><?php esc_html_e('Free configuration', 'notifal'); ?></strong>
                        <?php esc_html_e('We set up notifications on your site for free.', 'notifal'); ?>
                    </p>
                    <a
                        class="notifal-deactivation-help-row__btn notifal-deactivation-help-row__btn--secondary"
                        href="<?php echo esc_url($free_configuration_url); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <?php esc_html_e('Get setup', 'notifal'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render a short note encouraging specific feedback.
     *
     * @return void
     * @since 2.3.5 Displays a team note above reasons asking users to pick the closest reason instead of "Other".
     */
    private function renderDeactivationTeamNote(): void
    {
        ?>
        <p class="notifal-deactivation-team-note">
            <?php esc_html_e('Our team spent 18+ months building Notifal. Please choose the reason that fits best, your answer helps us improve the plugin for everyone.', 'notifal'); ?>
        </p>
        <?php
    }

    /**
     * Get help text when "Other" is selected.
     *
     * @return string Plain help text
     * @since 2.3.5 Guides users toward a specific reason or an actionable note when "Other" is selected.
     */
    private function getOtherReasonHelpText(): string
    {
        return __(
            '«Other» is the hardest option for us to act on. If something did not work, try «I could not get the plugin to work» above, or leave a short, honest note here, we read every message.',
            'notifal'
        );
    }

    /**
     * Get help text for "couldn't get to work" reason
     *
     * @return string Help text with links
     * @since 2.0.0
     * @since 2.3.5 Updated copy and links to knowledge base plus support ticket (Urls::SUPPORT_PAGE) with 2-hour reply note.
     */
    private function getCouldNotWorkHelpText(): string
    {
        $support_url = esc_url(Urls::SUPPORT_PAGE);
        $knowledge_base_url = esc_url(Urls::KNOWLEDGE_BASE);

        return sprintf(
            /* translators: 1: knowledge base link, 2: support link */
            __('Browse guides in our %1$s or open a %2$s — we typically reply within 2 hours.', 'notifal'),
            '<a href="' . $knowledge_base_url . '" target="_blank" rel="noopener noreferrer">' . esc_html__('knowledge base', 'notifal') . '</a>',
            '<a href="' . $support_url . '" target="_blank" rel="noopener noreferrer">' . esc_html__('support ticket', 'notifal') . '</a>'
        );
    }

}
