<?php

namespace Notifal\Modules\Ai\Presentation\Admin\Controllers\Ajax;

use Notifal\Modules\Ai\Application\Services\GatewayReadinessService;
use Notifal\Modules\Ai\Application\Services\GenerationBridgeService;
use Notifal\Modules\Ai\Config\Constants;

defined('ABSPATH') || exit;

/**
 * AJAX boundary for hosted AI generation lifecycle actions.
 *
 * @since 3.0.0
 * @updated 3.0.0 Release the PHP session before outbound gateway HTTP so cancel is not blocked.
 * @author Hossein <hossein@notifal.com>
 */
class AiGenerationAjaxController
{
    /**
     * Register WordPress AJAX handlers.
     *
     * @since 3.0.0
     * @return void
     */
    public static function register(): void
    {
        // Register generation lifecycle endpoints for authenticated editors.
        add_action('wp_ajax_notifal_ai_start_generation', [self::class, 'startGeneration']);
        add_action('wp_ajax_notifal_ai_generation_status', [self::class, 'generationStatus']);
        add_action('wp_ajax_notifal_ai_cancel_generation', [self::class, 'cancelGeneration']);
        add_action('wp_ajax_notifal_ai_generation_feedback', [self::class, 'generationFeedback']);
    }

    /**
     * Release the PHP session lock before long outbound gateway calls.
     *
     * Admin AJAX may hold a session lock. Status polls that wait on the gateway
     * would otherwise block cancel (and other) AJAX until that poll returns.
     *
     * @since 3.0.0
     * @return void
     */
    private static function releaseAjaxSession(): void
    {
        // Only close when a session is active; avoid notices on hosts without sessions.
        if (function_exists('session_status') && PHP_SESSION_ACTIVE === session_status()) {
            session_write_close();
        }
    }

    /**
     * Start one hosted generation job.
     *
     * @since 3.0.0
     * @return void
     */
    public static function startGeneration(): void
    {
        try {
            // Verify nonce and require editor capability for generation starts.
            notifal_verify_ajax_request(Constants::AJAX_NONCE_ACTION, 'edit_posts');

            // Free the session before the readiness probe and gateway start call.
            self::releaseAjaxSession();

            // Fail closed locally when gateway health reports generation is paused.
            $readiness = notifal_app(GatewayReadinessService::class)->getReadiness(false);
            $readyData = is_array($readiness['data'] ?? null) ? $readiness['data'] : [];
            if (empty($readyData['generation_ready'])) {
                notifal_json_error(
                    __('Hosted AI is temporarily unavailable. Please try again later.', 'notifal'),
                    ['code' => 'provider_unavailable']
                );
            }

            // Read the requested feature slug.
            $feature = isset($_POST['feature']) ? sanitize_key(wp_unslash($_POST['feature'])) : '';

            // Decode structured intent JSON from the browser boundary.
            $intent = self::decodeIntentPayload();

            // Read the client idempotency key used for safe retries.
            $idempotencyKey = isset($_POST['idempotency_key'])
                ? sanitize_text_field(wp_unslash($_POST['idempotency_key']))
                : '';

            // Reject missing idempotency keys before hitting the gateway.
            if ($idempotencyKey === '') {
                notifal_json_error(__('Missing generation retry key.', 'notifal'));
            }

            // Delegate to the generation bridge service.
            $result = self::generationService()->start($feature, $intent, $idempotencyKey);

            // Return structured success or error payloads.
            self::respond($result);
        } catch (\Throwable $exception) {
            notifal_json_error(__('An unexpected error occurred. Please try again.', 'notifal'));
        }
    }

    /**
     * Poll one hosted generation job.
     *
     * @since 3.0.0
     * @return void
     */
    public static function generationStatus(): void
    {
        try {
            // Verify nonce and require editor capability for polling.
            notifal_verify_ajax_request(Constants::AJAX_NONCE_ACTION, 'edit_posts');

            // Free the session before the outbound status poll.
            self::releaseAjaxSession();

            // Read the generation UUID from the request.
            $uuid = isset($_POST['uuid']) ? sanitize_text_field(wp_unslash($_POST['uuid'])) : '';

            // Delegate to the generation bridge service.
            $result = self::generationService()->status($uuid);

            // Return structured success or error payloads.
            self::respond($result);
        } catch (\Throwable $exception) {
            notifal_json_error(__('An unexpected error occurred. Please try again.', 'notifal'));
        }
    }

    /**
     * Request cancellation for one hosted generation job.
     *
     * @since 3.0.0
     * @updated 3.0.0 Release the session before the outbound cancel call.
     * @return void
     */
    public static function cancelGeneration(): void
    {
        try {
            // Verify nonce and require editor capability for cancellation.
            notifal_verify_ajax_request(Constants::AJAX_NONCE_ACTION, 'edit_posts');

            // Free the session so cancel is not queued behind a long status poll.
            self::releaseAjaxSession();

            // Read the generation UUID from the request.
            $uuid = isset($_POST['uuid']) ? sanitize_text_field(wp_unslash($_POST['uuid'])) : '';

            // Delegate to the generation bridge service.
            $result = self::generationService()->cancel($uuid);

            // Return structured success or error payloads.
            self::respond($result);
        } catch (\Throwable $exception) {
            notifal_json_error(__('An unexpected error occurred. Please try again.', 'notifal'));
        }
    }

    /**
     * Submit quality feedback for one generation.
     *
     * @since 3.0.0
     * @updated 3.0.0 Accept optional category badges and free-text comment.
     * @return void
     */
    public static function generationFeedback(): void
    {
        try {
            // Verify nonce and require editor capability for feedback submission.
            notifal_verify_ajax_request(Constants::AJAX_NONCE_ACTION, 'edit_posts');

            // Free the session before the outbound feedback call.
            self::releaseAjaxSession();

            // Read the generation UUID from the request.
            $uuid = isset($_POST['uuid']) ? sanitize_text_field(wp_unslash($_POST['uuid'])) : '';

            // Read the feedback slug from the request.
            $feedback = isset($_POST['feedback']) ? sanitize_key(wp_unslash($_POST['feedback'])) : '';

            // Optional JSON-encoded category badges (needs_improvement detail).
            $categories_raw = isset($_POST['categories']) ? wp_unslash($_POST['categories']) : '[]';
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and allowlisted in the bridge.
            $categories_decoded = json_decode(is_string($categories_raw) ? $categories_raw : '[]', true);

            // Optional free-text comment for needs_improvement detail.
            $comment = isset($_POST['comment'])
                ? sanitize_textarea_field(wp_unslash($_POST['comment']))
                : '';

            // Optional feature hint for gateway audit context.
            $feature = isset($_POST['feature']) ? sanitize_key(wp_unslash($_POST['feature'])) : '';

            // Delegate to the generation bridge service.
            $result = self::generationService()->feedback(
                $uuid,
                $feedback,
                [
                    'categories' => is_array($categories_decoded) ? $categories_decoded : [],
                    'comment'    => $comment,
                    'feature'    => $feature,
                ]
            );

            // Return structured success or error payloads.
            self::respond($result);
        } catch (\Throwable $exception) {
            notifal_json_error(__('An unexpected error occurred. Please try again.', 'notifal'));
        }
    }

    /**
     * Decode and validate structured intent JSON from POST data.
     *
     * @since 3.0.0
     * @return array<string, mixed> Structured intent array.
     */
    private static function decodeIntentPayload(): array
    {
        // Read raw JSON from the dedicated POST field.
        $raw = isset($_POST['intent']) ? wp_unslash($_POST['intent']) : '';

        // Accept either a JSON string or a pre-decoded array from FormData edge cases.
        if (is_array($raw)) {
            return $raw;
        }

        // Reject empty intent payloads early.
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        // Decode JSON without sanitize_text_field because it corrupts JSON payloads.
        $decoded = json_decode($raw, true);

        // Return only array payloads to downstream validators.
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Resolve the generation bridge service from the container.
     *
     * @since 3.0.0
     * @return GenerationBridgeService Generation bridge service instance.
     */
    private static function generationService(): GenerationBridgeService
    {
        // Resolve the singleton service from the Notifal container.
        return notifal_app(GenerationBridgeService::class);
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
