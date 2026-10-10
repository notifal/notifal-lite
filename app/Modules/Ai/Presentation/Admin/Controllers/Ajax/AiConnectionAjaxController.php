<?php

namespace Notifal\Modules\Ai\Presentation\Admin\Controllers\Ajax;

use Notifal\Modules\Ai\Application\Services\AccountConnectionService;
use Notifal\Modules\Ai\Application\Services\GatewayReadinessService;
use Notifal\Modules\Ai\Application\Services\LicenseConnectionProvisioner;
use Notifal\Modules\Ai\Config\Constants;

defined('ABSPATH') || exit;

/**
 * AJAX boundary for passwordless Notifal account connection.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class AiConnectionAjaxController
{
    /**
     * Register WordPress AJAX handlers.
     *
     * @since 3.0.0
     * @return void
     */
    public static function register(): void
    {
        // Register connection management endpoints for authenticated admins.
        add_action('wp_ajax_notifal_ai_request_code', [self::class, 'requestCode']);
        add_action('wp_ajax_notifal_ai_verify_code', [self::class, 'verifyCode']);
        add_action('wp_ajax_notifal_ai_connection_status', [self::class, 'connectionStatus']);
        add_action('wp_ajax_notifal_ai_disconnect', [self::class, 'disconnect']);
        add_action('wp_ajax_notifal_ai_provision_from_license', [self::class, 'provisionFromLicense']);
        add_action('wp_ajax_notifal_ai_usage', [self::class, 'usage']);
        add_action('wp_ajax_notifal_ai_readiness', [self::class, 'readiness']);
    }

    /**
     * Request a passwordless verification code.
     *
     * @since 3.0.0
     * @return void
     */
    public static function requestCode(): void
    {
        try {
            // Verify nonce and require connection management capability.
            notifal_verify_ajax_request(Constants::AJAX_NONCE_ACTION, 'manage_options');

            // Read and sanitize the submitted email address.
            $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';

            // Read the contact name collected on the connect form.
            $contactName = isset($_POST['contact_name']) ? sanitize_text_field(wp_unslash($_POST['contact_name'])) : '';

            // Read optional CRO tips marketing preference from the connect step.
            $marketingOptIn = isset($_POST['marketing_opt_in'])
                && '1' === sanitize_text_field(wp_unslash($_POST['marketing_opt_in']));

            // Delegate to the account connection service.
            $result = self::connectionService()->requestCode($email, $contactName, $marketingOptIn);

            // Return structured success or error payloads.
            self::respond($result);
        } catch (\Throwable $exception) {
            notifal_json_error(__('An unexpected error occurred. Please try again.', 'notifal'));
        }
    }

    /**
     * Verify a six-digit email code and store local credentials.
     *
     * @since 3.0.0
     * @return void
     */
    public static function verifyCode(): void
    {
        try {
            // Verify nonce and require connection management capability.
            notifal_verify_ajax_request(Constants::AJAX_NONCE_ACTION, 'manage_options');

            // Read the opaque transaction identifier.
            $transactionId = isset($_POST['transaction_id'])
                ? sanitize_text_field(wp_unslash($_POST['transaction_id']))
                : '';

            // Read the six-digit verification code.
            $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';

            // Read the contact name collected alongside the email address.
            $contactName = isset($_POST['contact_name']) ? sanitize_text_field(wp_unslash($_POST['contact_name'])) : '';

            // Read optional CRO tips marketing preference from the connect step.
            $marketingOptIn = isset($_POST['marketing_opt_in'])
                && '1' === sanitize_text_field(wp_unslash($_POST['marketing_opt_in']));

            // Delegate to the account connection service.
            $result = self::connectionService()->verifyCode($transactionId, $code, $contactName, $marketingOptIn);

            // Return structured success or error payloads.
            self::respond($result);
        } catch (\Throwable $exception) {
            notifal_json_error(__('An unexpected error occurred. Please try again.', 'notifal'));
        }
    }

    /**
     * Return current connection and optional usage snapshot.
     *
     * @since 3.0.0
     * @return void
     */
    public static function connectionStatus(): void
    {
        try {
            // Verify nonce and allow editors to read safe connection metadata.
            notifal_verify_ajax_request(Constants::AJAX_NONCE_ACTION, 'edit_posts');

            // Delegate to the account connection service.
            $result = self::connectionService()->getStatus();

            // Attach fresh wizard policy flags so admin JS does not rely on stale boot config.
            if (!empty($result['success']) && is_array($result['data'] ?? null)) {
                $connected = !empty($result['data']['connection']['connected']);
                $result['data']['wizard'] = self::licenseProvisioner()->buildWizardPolicy($connected);
            }

            // Return structured success payload.
            self::respond($result);
        } catch (\Throwable $exception) {
            notifal_json_error(__('An unexpected error occurred. Please try again.', 'notifal'));
        }
    }

    /**
     * Disconnect the local installation from the hosted AI account.
     *
     * @since 3.0.0
     * @return void
     */
    public static function disconnect(): void
    {
        try {
            // Verify nonce and require connection management capability.
            notifal_verify_ajax_request(Constants::AJAX_NONCE_ACTION, 'manage_options');

            // Delegate to the account connection service.
            $result = self::connectionService()->disconnect();

            // Return structured success payload.
            self::respond($result);
        } catch (\Throwable $exception) {
            notifal_json_error(__('An unexpected error occurred. Please try again.', 'notifal'));
        }
    }

    /**
     * Provision hosted AI credentials from an active Pro license.
     *
     * @since 3.0.0
     * @return void
     */
    public static function provisionFromLicense(): void
    {
        try {
            // Verify nonce and require connection management capability.
            notifal_verify_ajax_request(Constants::AJAX_NONCE_ACTION, 'manage_options');

            // Delegate to the license-backed provisioner service.
            $result = self::licenseProvisioner()->provisionFromActiveLicense();

            // Attach fresh wizard policy flags after provisioning attempts.
            if (!empty($result['success']) && is_array($result['data'] ?? null)) {
                $connected = !empty($result['data']['connection']['connected']);
                $result['data']['wizard'] = self::licenseProvisioner()->buildWizardPolicy($connected);
            }

            // Return structured success or error payloads.
            self::respond($result);
        } catch (\Throwable $exception) {
            notifal_json_error(__('An unexpected error occurred. Please try again.', 'notifal'));
        }
    }

    /**
     * Return current hosted AI usage summary.
     *
     * @since 3.0.0
     * @return void
     */
    public static function usage(): void
    {
        try {
            // Verify nonce and allow editors to read quota metadata.
            notifal_verify_ajax_request(Constants::AJAX_NONCE_ACTION, 'edit_posts');

            // Delegate to the account connection service status call.
            $result = self::connectionService()->getStatus();

            // Return only the usage portion to keep responses focused.
            if (!empty($result['success'])) {
                notifal_json_success([
                    'usage'      => $result['data']['usage'] ?? null,
                    'connection' => $result['data']['connection'] ?? [],
                ]);
            }

            // Forward structured service failures unchanged.
            self::respond($result);
        } catch (\Throwable $exception) {
            notifal_json_error(__('An unexpected error occurred. Please try again.', 'notifal'));
        }
    }

    /**
     * Return whether hosted generation is ready for wizard entry points.
     *
     * @since 3.0.0
     * @return void
     */
    public static function readiness(): void
    {
        try {
            // Verify nonce and allow editors to probe readiness before opening AI UI.
            notifal_verify_ajax_request(Constants::AJAX_NONCE_ACTION, 'edit_posts');

            // Optional force refresh when the admin wants a fresh health probe.
            $forceRefresh = isset($_POST['force_refresh'])
                && '1' === sanitize_text_field(wp_unslash($_POST['force_refresh']));

            // Delegate to the lightweight readiness service.
            $result = self::readinessService()->getReadiness($forceRefresh);

            // Return structured success payload.
            self::respond($result);
        } catch (\Throwable $exception) {
            notifal_json_error(__('An unexpected error occurred. Please try again.', 'notifal'));
        }
    }

    /**
     * Resolve the account connection service from the container.
     *
     * @since 3.0.0
     * @return AccountConnectionService Connection service instance.
     */
    private static function connectionService(): AccountConnectionService
    {
        // Resolve the singleton service from the Notifal container.
        return notifal_app(AccountConnectionService::class);
    }

    /**
     * Resolve the generation readiness service from the container.
     *
     * @since 3.0.0
     * @return GatewayReadinessService Readiness service instance.
     */
    private static function readinessService(): GatewayReadinessService
    {
        // Resolve the singleton service from the Notifal container.
        return notifal_app(GatewayReadinessService::class);
    }

    /**
     * Resolve the license connection provisioner from the container.
     *
     * @since 3.0.0
     * @return LicenseConnectionProvisioner Provisioner service instance.
     */
    private static function licenseProvisioner(): LicenseConnectionProvisioner
    {
        // Resolve the singleton service from the Notifal container.
        return notifal_app(LicenseConnectionProvisioner::class);
    }

    /**
     * Convert service arrays into standardized JSON AJAX responses.
     *
     * @since 3.0.0
     * @param array<string, mixed> $result Service result array.
     * @return void
     */
    private static function respond(array $result): void
    {
        // Emit success payloads through the shared JSON helper.
        if (!empty($result['success'])) {
            notifal_json_success($result['data'] ?? []);
        }

        // Extract structured error metadata when present.
        $error = is_array($result['error'] ?? null) ? $result['error'] : [];
        $message = sanitize_text_field((string) ($error['message'] ?? __('Request failed.', 'notifal')));

        // Emit error payloads without exposing secrets.
        notifal_json_error($message, $error);
    }
}
