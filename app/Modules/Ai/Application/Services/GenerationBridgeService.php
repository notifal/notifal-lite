<?php

namespace Notifal\Modules\Ai\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\Ai\Config\Constants;
use Notifal\Modules\Ai\Domain\AiFeature;
use Notifal\Modules\Ai\Domain\GatewayErrorMapper;
use Notifal\Modules\Ai\Domain\GenerationIntentValidator;
use Notifal\Modules\Ai\Domain\SiteIdentityBuilder;
use Notifal\Modules\Ai\Infrastructure\Gateway\AiGatewayClient;
use Notifal\Modules\Ai\Infrastructure\Gateway\GatewayResponse;

defined('ABSPATH') || exit;

/**
 * Bridges local generation requests to the hosted AI gateway.
 *
 * @since 3.0.0
 * @updated 3.0.0 Pass related validation fields through gateway failures for wizard UX.
 * @updated 3.0.0 Expose code/message aliases on failed job status for Pass 2 recovery UX.
 * @author Hossein <hossein@notifal.com>
 */
class GenerationBridgeService
{
    /**
     * Gateway HTTP client dependency.
     *
     * @var AiGatewayClient
     */
    private AiGatewayClient $gatewayClient;

    /**
     * Access token manager dependency.
     *
     * @var AccessTokenManager
     */
    private AccessTokenManager $accessTokenManager;

    /**
     * Account connection service dependency.
     *
     * @var AccountConnectionService
     */
    private AccountConnectionService $connectionService;

    /**
     * Installation credential store dependency.
     *
     * @var InstallationCredentialStore
     */
    private InstallationCredentialStore $credentialStore;

    /**
     * Site identity builder dependency.
     *
     * @var SiteIdentityBuilder
     */
    private SiteIdentityBuilder $siteIdentityBuilder;

    /**
     * Create the generation bridge service.
     *
     * @since 3.0.0
     * @param AiGatewayClient             $gatewayClient       Gateway HTTP client.
     * @param AccessTokenManager          $accessTokenManager  Access token manager.
     * @param AccountConnectionService    $connectionService   Account connection service.
     * @param InstallationCredentialStore $credentialStore     Credential store.
     * @param SiteIdentityBuilder         $siteIdentityBuilder Site identity builder.
     */
    public function __construct(
        AiGatewayClient $gatewayClient,
        AccessTokenManager $accessTokenManager,
        AccountConnectionService $connectionService,
        InstallationCredentialStore $credentialStore,
        SiteIdentityBuilder $siteIdentityBuilder
    ) {
        // Store dependencies used by generation workflows.
        $this->gatewayClient = $gatewayClient;
        $this->accessTokenManager = $accessTokenManager;
        $this->connectionService = $connectionService;
        $this->credentialStore = $credentialStore;
        $this->siteIdentityBuilder = $siteIdentityBuilder;
    }

    /**
     * Start an asynchronous hosted generation job.
     *
     * @since 3.0.0
     * @param string $feature Feature slug.
     * @param array<string, mixed> $intent Structured intent payload.
     * @param string $idempotencyKey Client retry key.
     * @return array<string, mixed> Normalized job state for polling UI.
     */
    public function start(string $feature, array $intent, string $idempotencyKey): array
    {
        // Normalize legacy feature slugs before local validation.
        $feature = AiFeature::normalize($feature);

        // Validate the requested feature slug locally.
        if (!AiFeature::isValid($feature)) {
            return $this->failure('invalid_request', __('Unsupported generation feature.', 'notifal'));
        }

        // Normalize and validate structured intent before any outbound gateway call.
        $intentCheck = GenerationIntentValidator::normalize($feature, $intent);
        if (empty($intentCheck['success'])) {
            return $this->failure(
                (string) ($intentCheck['code'] ?? 'invalid_request'),
                (string) ($intentCheck['message'] ?? GatewayErrorMapper::message('invalid_request')),
                is_array($intentCheck['fields'] ?? null) ? $intentCheck['fields'] : []
            );
        }

        // Use the gateway-safe intent produced by the validator.
        $intent = is_array($intentCheck['intent'] ?? null) ? $intentCheck['intent'] : [];

        // Apply a lightweight local throttle before hitting the gateway.
        if (!$this->allowLocalStart($feature)) {
            return $this->failure('rate_limited', GatewayErrorMapper::message('rate_limited'));
        }

        // Build the shared generation body used by authenticated and public wizard routes.
        $body = $this->buildGenerationBody($feature, $intent);

        /**
         * Filter the hosted generation request body before it is sent to the gateway.
         *
         * @since 3.0.0
         * @param array<string, mixed> $body Request body.
         * @param string $feature Feature slug.
         * @param array<string, mixed> $intent Structured intent payload.
         */
        $body = apply_filters(FilterHooks::AI_GENERATION_REQUEST_BODY, $body, $feature, $intent);

        // Setup Wizard analysis must work before Connect on fresh installs.
        if (AiFeature::WIZARD_SITE_ANALYSIS === $feature && !$this->credentialStore->isConnected()) {
            return $this->startWizardSiteAnalysis($body, $idempotencyKey);
        }

        try {
            // Resolve a valid bearer token for authenticated generation calls.
            $token = $this->accessTokenManager->getValidToken();
        } catch (\Throwable $exception) {
            return $this->failure(
                sanitize_key($exception->getMessage()),
                GatewayErrorMapper::message(sanitize_key($exception->getMessage()))
            );
        }

        // Submit the idempotent generation request to the gateway.
        $response = $this->gatewayClient->postAuthenticated(
            'generations',
            $body,
            $token,
            ['Idempotency-Key' => sanitize_text_field($idempotencyKey)]
        );

        // Normalize and return the public job state.
        return $this->normalizeJobResponse($response);
    }

    /**
     * Poll one generation job owned by the connected installation.
     *
     * @since 3.0.0
     * @updated 3.0.0 Use a longer status timeout so large completed outputs can transfer.
     * @updated 3.0.0 Allow up to 90s for large design-first OnPage completion payloads.
     * @param string $uuid Generation UUID.
     * @return array<string, mixed> Normalized job state.
     */
    public function status(string $uuid): array
    {
        // Sanitize the requested generation UUID.
        $uuid = sanitize_text_field($uuid);

        // Reject malformed UUID values before calling the gateway.
        if (!wp_is_uuid($uuid, 4)) {
            return $this->failure('invalid_request', __('Invalid generation reference.', 'notifal'));
        }

        // Poll the public wizard analysis route while the site is still disconnected.
        if (!$this->credentialStore->isConnected()) {
            return $this->statusWizardSiteAnalysis($uuid);
        }

        try {
            // Resolve a valid bearer token for polling.
            $token = $this->accessTokenManager->getValidToken();
        } catch (\Throwable $exception) {
            return $this->failure(
                sanitize_key($exception->getMessage()),
                GatewayErrorMapper::message(sanitize_key($exception->getMessage()))
            );
        }

        // Allow enough transfer time for large completed OnPage payloads after two-pass work.
        $response = $this->gatewayClient->getAuthenticated(
            'generations/' . rawurlencode($uuid),
            $token,
            90
        );

        // Normalize and return the public job state.
        return $this->normalizeJobResponse($response);
    }

    /**
     * Request best-effort cancellation for one generation job.
     *
     * @since 3.0.0
     * @updated 3.0.0 Use a short gateway timeout so cancel is not blocked behind long polls.
     * @param string $uuid Generation UUID.
     * @return array<string, mixed> Cancellation result.
     */
    public function cancel(string $uuid): array
    {
        // Sanitize the requested generation UUID.
        $uuid = sanitize_text_field($uuid);

        // Reject malformed UUID values before calling the gateway.
        if (!wp_is_uuid($uuid, 4)) {
            return $this->failure('invalid_request', __('Invalid generation reference.', 'notifal'));
        }

        try {
            // Resolve a valid bearer token for cancellation.
            $token = $this->accessTokenManager->getValidToken();
        } catch (\Throwable $exception) {
            return $this->failure(
                sanitize_key($exception->getMessage()),
                GatewayErrorMapper::message(sanitize_key($exception->getMessage()))
            );
        }

        // Request cancellation from the gateway with a non-empty JSON body.
        // Keep the timeout short: cancel is a DB write, not a provider wait.
        $response = $this->gatewayClient->postAuthenticated(
            'generations/' . rawurlencode($uuid) . '/cancel',
            [
                'confirm' => true,
                'uuid'    => $uuid,
            ],
            $token,
            [],
            15
        );

        // Normalize and return the cancellation payload.
        return $this->normalizeJobResponse($response);
    }

    /**
     * Submit quality feedback for one completed generation.
     *
     * @since 3.0.0
     * @updated 3.0.0 Optional category badges and free-text comment for needs_improvement.
     * @param string               $uuid Generation UUID.
     * @param string               $feedback Feedback slug (helpful|needs_improvement).
     * @param array<string, mixed> $details Optional categories and comment.
     * @return array<string, mixed> Feedback result.
     */
    public function feedback(string $uuid, string $feedback, array $details = []): array
    {
        // Sanitize UUID and feedback values.
        $uuid = sanitize_text_field($uuid);
        $feedback = sanitize_key($feedback);

        // Allow only the two supported feedback labels.
        if (!in_array($feedback, ['helpful', 'needs_improvement'], true)) {
            return $this->failure('invalid_request', __('Invalid feedback value.', 'notifal'));
        }

        // Normalize optional category badges against the local allowlist.
        $categories = $this->normalizeFeedbackCategories($details['categories'] ?? []);

        // Sanitize an optional free-text comment for needs_improvement details.
        $comment = '';
        if (isset($details['comment'])) {
            $comment = sanitize_textarea_field((string) $details['comment']);
            $comment = substr($comment, 0, 2000);
        }

        try {
            // Resolve a valid bearer token for feedback submission.
            $token = $this->accessTokenManager->getValidToken();
        } catch (\Throwable $exception) {
            return $this->failure(
                sanitize_key($exception->getMessage()),
                GatewayErrorMapper::message(sanitize_key($exception->getMessage()))
            );
        }

        // Submit feedback (and optional detail fields) to the gateway.
        $response = $this->gatewayClient->postAuthenticated(
            'generations/' . rawurlencode($uuid) . '/feedback',
            [
                'feedback'   => $feedback,
                'categories' => $categories,
                'comment'    => $comment,
                'feature'    => sanitize_key((string) ($details['feature'] ?? '')),
            ],
            $token
        );

        // Normalize and return the feedback payload.
        return $this->normalizeJobResponse($response);
    }

    /**
     * Keep only known generation feedback category slugs.
     *
     * @since 3.0.0
     * @param mixed $raw Incoming categories value.
     * @return array<int, string> Sanitized category slugs.
     */
    private function normalizeFeedbackCategories($raw): array
    {
        // Accept JSON strings from FormData payloads.
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($raw)) {
            return [];
        }

        // Default badges for Noti / HTML Builder quality review.
        $allowed = [
            'incorrect_template_change',
            'ignored_instruction',
            'broke_layout_or_styles',
            'form_or_countdown_issue',
            'unhelpful_chat_answer',
            'other',
        ];

        // Allow Pro or future modules to extend the allowlist.
        $allowed = array_values(array_unique(array_map('sanitize_key', (array) apply_filters(
            FilterHooks::AI_FEEDBACK_CATEGORIES,
            $allowed
        ))));

        $normalized = [];
        foreach ($raw as $value) {
            $slug = sanitize_key((string) $value);
            if ($slug === '' || !in_array($slug, $allowed, true)) {
                continue;
            }
            $normalized[$slug] = $slug;
        }

        return array_values($normalized);
    }

    /**
     * Build client capability flags sent with every generation request.
     *
     * @since 3.0.0
     * @return array<string, bool> Capability map.
     */
    private function buildCapabilities(): array
    {
        // Compose baseline capability flags from the local installation.
        // Cast every flag to bool so JSON always emits true/false for known keys.
        $capabilities = [
            'woocommerce'       => (bool) PluginDetector::isWooCommerceActive(),
            'notifal_pro'       => (bool) apply_filters('notifal_pro_multiple_notifications_allowed', false),
            'pro_display_rules' => (bool) apply_filters('notifal_pro_multiple_notifications_allowed', false),
            'comment_tags'      => (bool) (post_type_supports('comment', 'editor') || comments_open()),
            // WordPress capability gate for keeping <script> on HTML Builder save.
            'unfiltered_html'   => (bool) current_user_can('unfiltered_html'),
        ];

        /**
         * Filter client capability flags sent to the hosted AI gateway.
         *
         * @since 3.0.0
         * @param array<string, bool> $capabilities Capability map.
         */
        $filtered = (array) apply_filters(FilterHooks::AI_GENERATION_CAPABILITIES, $capabilities);

        // Re-assert required keys after filters so the gateway always receives them.
        return [
            'woocommerce'       => !empty($filtered['woocommerce']),
            'notifal_pro'       => !empty($filtered['notifal_pro']),
            'pro_display_rules' => !empty($filtered['pro_display_rules']),
            'comment_tags'      => !empty($filtered['comment_tags']),
            'unfiltered_html'   => !empty($filtered['unfiltered_html']),
        ];
    }

    /**
     * Build the sanitized active plugin name list for hosted AI prompts.
     *
     * Shared by OnPage full-notification generation and HTML Builder create/edit.
     *
     * @since 3.0.0
     * @return string[] Sorted unique plugin display names.
     */
    private function buildActivePlugins(): array
    {
        // Resolve human-readable names of plugins active on this WordPress site.
        $names = PluginDetector::getActivePluginNames();

        // Sanitize each label before the outbound gateway request.
        $names = array_values(
            array_filter(
                array_map(
                    static function ($name): string {
                        // Keep only plain text plugin titles from the plugins API.
                        return sanitize_text_field((string) $name);
                    },
                    $names
                ),
                static function (string $name): bool {
                    // Drop empty strings after sanitization.
                    return '' !== $name;
                }
            )
        );

        /**
         * Filter active plugin display names sent to the hosted AI gateway.
         *
         * @since 3.0.0
         * @param string[] $names Sorted unique plugin display names.
         */
        $filtered = apply_filters(FilterHooks::AI_GENERATION_ACTIVE_PLUGINS, $names);

        // Guarantee a string list even when a filter returns an unexpected type.
        if (!is_array($filtered)) {
            return $names;
        }

        return array_values(
            array_filter(
                array_map('sanitize_text_field', $filtered),
                static function (string $name): bool {
                    return '' !== $name;
                }
            )
        );
    }

    /**
     * Apply a simple per-user local throttle for generation starts.
     *
     * @since 3.0.0
     * @param string $feature Feature slug.
     * @return bool True when the start request is allowed.
     */
    private function allowLocalStart(string $feature): bool
    {
        // Build a per-user transient key for start throttling.
        $userId = get_current_user_id();
        $key = 'notifal_ai_start_' . $userId . '_' . sanitize_key($feature);

        // Reject when a recent start already occurred within the window.
        if (get_transient($key)) {
            return false;
        }

        // Reserve the throttle window for three seconds.
        set_transient($key, 1, 3);

        // Allow the generation start request.
        return true;
    }

    /**
     * Normalize one gateway job response for AJAX consumers.
     *
     * @since 3.0.0
     * @updated 3.0.0 Attach code/message aliases when a successful poll returns status=failed.
     * @param GatewayResponse $response Gateway response object.
     * @return array<string, mixed> Normalized payload.
     */
    private function normalizeJobResponse(GatewayResponse $response): array
    {
        // Return success payloads directly to polling clients.
        if ($response->isSuccess()) {
            // Enrich failed job rows so hosts can map error_code without a second lookup.
            $data = $response->getData();
            if (is_array($data)) {
                $data = $this->enrichFailedJobState($data);
            }

            return [
                'success' => true,
                'data'    => $data,
            ];
        }

        // Map stable gateway codes to localized messages.
        $code = $response->getErrorCode();

        // Collect related field hints when the gateway identified invalid wizard inputs.
        $fields = $response->getFields();

        // Fall back to code-based field hints when older gateways omit the fields array.
        if ($fields === []) {
            $fields = GatewayErrorMapper::fieldsForCode($code);
        }

        // Temporary diagnostics: confirm whether start failed after the HTTP round-trip.
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Temporary generation validation diagnostics.
            error_log(
                'Notifal AI generation gateway failure: ' . wp_json_encode(
                    [
                        'code'       => sanitize_key($code),
                        'fields'     => $fields,
                        'request_id' => $response->getRequestId(),
                        'status'     => $response->getStatusCode(),
                    ]
                )
            );
        }

        // Compose structured error metadata for JavaScript.
        $error = [
            'code'               => sanitize_key($code),
            'message'            => GatewayErrorMapper::message($code, $response->getErrorMessage()),
            'retry_after'        => $response->getRetryAfter(),
            'requires_reconnect' => GatewayErrorMapper::requiresReconnect($code),
            'quota_exceeded'     => GatewayErrorMapper::isQuotaExceeded($code),
            'request_id'         => $response->getRequestId(),
        ];

        // Attach field hints so the wizard can highlight the failing inputs.
        if ($fields !== []) {
            $error['fields'] = array_map('sanitize_key', $fields);
        }

        return [
            'success' => false,
            'error'   => $error,
        ];
    }

    /**
     * Add client-facing code/message aliases on terminal failed job state.
     *
     * Gateway public status uses error_code. Poller hosts and recovery UI expect
     * code + message the same way start() failures are shaped.
     *
     * @since 3.0.0
     * @param array<string, mixed> $data Public job state from the gateway.
     * @return array<string, mixed> Job state with recovery-friendly aliases.
     */
    private function enrichFailedJobState(array $data): array
    {
        // Only terminal failures need code/message aliases for recovery chrome.
        $status = sanitize_key((string) ($data['status'] ?? ''));
        if ('failed' !== $status) {
            return $data;
        }

        // Prefer gateway error_code; fall back to a pre-existing code alias.
        $code = sanitize_key((string) ($data['error_code'] ?? $data['code'] ?? ''));
        if ('' === $code) {
            return $data;
        }

        // Mirror start-failure shape so JS hosts can reuse one feedback resolver.
        $data['code'] = $code;
        if (!isset($data['message']) || '' === trim((string) $data['message'])) {
            $data['message'] = GatewayErrorMapper::message($code);
        }

        return $data;
    }

    /**
     * Build a standardized failure envelope.
     *
     * @since 3.0.0
     * @updated 3.0.0 Log local validation failures for temporary diagnostics.
     * @param string $code Stable error code.
     * @param string $message Localized message.
     * @param string[] $fields Optional related intent field slugs.
     * @return array<string, mixed> Failure payload.
     */
    private function failure(string $code, string $message, array $fields = []): array
    {
        // Temporary diagnostics: local failures never create a gateway generation row.
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Temporary generation validation diagnostics.
            error_log(
                'Notifal AI generation local failure: ' . wp_json_encode(
                    [
                        'code'    => sanitize_key($code),
                        'fields'  => array_map('sanitize_key', $fields),
                        'message' => sanitize_text_field($message),
                    ]
                )
            );
        }

        // Compose the base error metadata for AJAX consumers.
        $error = [
            'code'               => sanitize_key($code),
            'message'            => $message,
            'requires_reconnect' => GatewayErrorMapper::requiresReconnect($code),
            'quota_exceeded'     => GatewayErrorMapper::isQuotaExceeded($code),
        ];

        // Attach field hints when local validation identified specific wizard inputs.
        if ($fields === []) {
            $fields = GatewayErrorMapper::fieldsForCode($code);
        }
        if ($fields !== []) {
            $error['fields'] = array_map('sanitize_key', $fields);
        }

        // Return a consistent failure structure for AJAX handlers.
        return [
            'success' => false,
            'error'   => $error,
        ];
    }

    /**
     * Build the shared generation request body for gateway routes.
     *
     * @since 3.0.0
     * @param string               $feature Feature slug.
     * @param array<string, mixed> $intent  Normalized intent.
     * @return array<string, mixed> Generation request body.
     */
    private function buildGenerationBody(string $feature, array $intent): array
    {
        // active_plugins is collected on this site so the hosted prompt can suggest real shortcodes.
        return [
            'feature'            => $feature,
            'intent'             => $intent,
            'capabilities'       => $this->buildCapabilities(),
            'active_plugins'     => $this->buildActivePlugins(),
            'prompt_version'     => Constants::PROMPT_VERSION,
            'schema_version'     => Constants::SCHEMA_VERSION,
            'client_version'     => 'notifal ' . (defined('NOTIFAL_VERSION') ? NOTIFAL_VERSION : '0.0.0'),
            'notifal_version'    => defined('NOTIFAL_VERSION') ? NOTIFAL_VERSION : '0.0.0',
            'disclosure_version' => $this->connectionService->getDisclosureVersion(),
        ];
    }

    /**
     * Start public Setup Wizard site analysis without account credentials.
     *
     * @since 3.0.0
     * @param array<string, mixed> $body           Generation request body.
     * @param string               $idempotencyKey Client retry key.
     * @return array<string, mixed> Normalized job state.
     */
    private function startWizardSiteAnalysis(array $body, string $idempotencyKey): array
    {
        // Attach the persistent site identity required by the public wizard route.
        $body['site'] = $this->siteIdentityBuilder->build();

        // Post without a bearer token while still attaching the idempotency header.
        $response = $this->gatewayClient->postAuthenticated(
            'wizard/site-analysis',
            $body,
            '',
            ['Idempotency-Key' => sanitize_text_field($idempotencyKey)]
        );

        // Normalize and return the public job state.
        return $this->normalizeJobResponse($response);
    }

    /**
     * Poll one public Setup Wizard site analysis job.
     *
     * @since 3.0.0
     * @param string $uuid Generation UUID.
     * @return array<string, mixed> Normalized job state.
     */
    private function statusWizardSiteAnalysis(string $uuid): array
    {
        // Resolve the same site UUID used when the job was created.
        $siteUuid = $this->credentialStore->getOrCreateSiteUuid();

        // Poll the public wizard analysis route with a short timeout.
        $response = $this->gatewayClient->get(
            'wizard/site-analysis/' . rawurlencode($uuid) . '?site_uuid=' . rawurlencode($siteUuid),
            15
        );

        // Normalize and return the public job state.
        return $this->normalizeJobResponse($response);
    }

}
