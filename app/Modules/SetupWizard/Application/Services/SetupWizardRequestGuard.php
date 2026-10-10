<?php
namespace Notifal\Modules\SetupWizard\Application\Services;
use Notifal\Modules\Ai\Application\Services\AiIndustryOptions;
use Notifal\Modules\SetupWizard\Domain\Constants\SetupWizardSteps;
use Notifal\Modules\Templates\Application\Services\HtmlBuilderUseCases;
defined('ABSPATH') || exit;
/**
 * Rate limiting, payload sanitization, and allowlist checks for Setup Wizard AJAX.
 *
 * @since 3.0.0
 * @updated 3.0.0 Rate-limit domain package preflight and allow domain_package progress source.
 * @updated 3.0.0 Rate-limit domain package force-refresh, profile sync, and replace.
 * @updated 3.0.0 Sanitize feedback source and optional package_id.
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardRequestGuard
{
    /**
     * Per-action rate limit windows keyed by action slug.
     *
     * @var array<string, array{limit: int, window: int}>
     */
    private const RATE_LIMITS = [
        'scan'                          => ['limit' => 3, 'window' => 300],
        'domain_package_preflight'      => ['limit' => 12, 'window' => 60],
        'domain_package_force_refresh'  => ['limit' => 4, 'window' => 600],
        'domain_package_sync_profile'   => ['limit' => 30, 'window' => 60],
        'domain_package_replace'        => ['limit' => 6, 'window' => 600],
        'recommendations'               => ['limit' => 12, 'window' => 60],
        'import'                        => ['limit' => 6, 'window' => 600],
        'trial'                         => ['limit' => 2, 'window' => 3600],
        'install_pro'                   => ['limit' => 3, 'window' => 3600],
        'activate_pro'                  => ['limit' => 5, 'window' => 3600],
        'report_progress'               => ['limit' => 30, 'window' => 60],
        'feedback'                      => ['limit' => 8, 'window' => 600],
    ];
    /**
     * Maximum UTF-8 bytes accepted for JSON progress payloads.
     *
     * @var int
     */
    private const MAX_PROGRESS_JSON_BYTES = 2048;
    /**
     * Maximum UTF-8 characters accepted for optional feedback comments.
     *
     * @var int
     */
    private const MAX_FEEDBACK_COMMENT_CHARS = 2000;
    /**
     * Reject the request when the action rate limit is exceeded.
     *
     * @since 3.0.0
     * @param string $action Action slug.
     * @return void
     */
    public function enforceRateLimit(string $action): void
    {
        // Read configured limit and window for the action.
        $action = sanitize_key($action);
        $config = self::RATE_LIMITS[$action] ?? null;
        if (!is_array($config)) {
            return;
        }
        // Build a per-user transient key for this action.
        $userId = get_current_user_id();
        $key = 'notifal_sw_rl_' . $action . '_' . $userId;
        $count = (int) get_transient($key);
        // Reject when the user exceeded the configured limit.
        if ($count >= (int) $config['limit']) {
            notifal_json_error(
                __('Too many Setup Wizard requests. Please wait a moment and try again.', 'notifal')
            );
        }
        // Increment the counter and preserve the remaining window TTL when present.
        $ttl = (int) $config['window'];
        $remainingTtl = (int) get_option('_transient_timeout_' . $key, 0) - time();
        if ($remainingTtl > 0 && $remainingTtl < $ttl) {
            $ttl = $remainingTtl;
        }
        // Persist the incremented counter for the active window.
        set_transient($key, $count + 1, max(1, $ttl));
    }
    /**
     * Return true when the goal slug is one of the supported use cases.
     *
     * @since 3.0.0
     * @param string $goalSlug Goal slug candidate.
     * @return bool
     */
    public function isValidGoalSlug(string $goalSlug): bool
    {
        // Normalize the incoming slug.
        $goalSlug = sanitize_key($goalSlug);
        if ('' === $goalSlug) {
            return false;
        }
        // Compare against the canonical Html Builder use-case allowlist.
        foreach (HtmlBuilderUseCases::getOptions() as $option) {
            if ($goalSlug === sanitize_key((string) ($option['slug'] ?? ''))) {
                return true;
            }
        }
        return false;
    }
    /**
     * Return true when the industry slug exactly matches a UI card slug.
     *
     * @since 3.0.0
     * @param string $industrySlug Industry slug candidate.
     * @return bool
     */
    public function isValidIndustrySlug(string $industrySlug): bool
    {
        // Exact card membership; gateway owns niche soft-aliases for AI output.
        return AiIndustryOptions::isAllowedSlug($industrySlug);
    }
    /**
     * Sanitize one progress payload before it is sent to the hosted gateway.
     *
     * @since 3.0.0
     * @param array<string, mixed> $payload Raw progress payload.
     * @return array<string, mixed> Sanitized progress payload.
     */
    public function sanitizeProgressPayload(array $payload): array
    {
        // Start with an empty outbound payload.
        $sanitized = [];
        // Allow only known step slugs reported by the gateway contract.
        if (isset($payload['step_reached'])) {
            $step = sanitize_key((string) $payload['step_reached']);
            if (in_array($step, $this->allowedProgressSteps(), true)) {
                $sanitized['step_reached'] = $step;
            }
        }
        // Bound optional industry and goal labels.
        if (isset($payload['industry'])) {
            $sanitized['industry'] = sanitize_text_field((string) $payload['industry']);
            if (strlen($sanitized['industry']) > 180) {
                $sanitized['industry'] = substr($sanitized['industry'], 0, 180);
            }
        }
        if (isset($payload['goal'])) {
            $sanitized['goal'] = sanitize_text_field((string) $payload['goal']);
            if (strlen($sanitized['goal']) > 120) {
                $sanitized['goal'] = substr($sanitized['goal'], 0, 120);
            }
        }
        // Allow only known template source slugs.
        if (isset($payload['template_source'])) {
            $source = sanitize_key((string) $payload['template_source']);
            if (in_array($source, ['marketplace', 'custom_ai', 'skipped', 'domain_package'], true)) {
                $sanitized['template_source'] = $source;
            }
        }
        // Normalize boolean terminal flags.
        if (!empty($payload['completed'])) {
            $sanitized['completed'] = true;
        }
        if (!empty($payload['skipped'])) {
            $sanitized['skipped'] = true;
        }
        return $sanitized;
    }
    /**
     * Sanitize one Setup Wizard experience feedback payload.
     *
     * @since 3.0.0
     * @updated 3.0.0 Include source (plugin|live) and optional package_id.
     * @param array<string, mixed> $payload Raw feedback payload.
     * @return array<string, mixed>|null Sanitized payload or null when invalid.
     */
    public function sanitizeFeedbackPayload(array $payload): ?array
    {
        // Allow only the two supported experience ratings.
        $rating = sanitize_key((string) ($payload['rating'] ?? ''));
        if (!in_array($rating, ['good', 'needs_improvement'], true)) {
            return null;
        }
        // Sanitize optional free-text details for needs_improvement replies.
        $comment = '';
        if (isset($payload['comment'])) {
            $comment = sanitize_textarea_field((string) $payload['comment']);
            if (strlen($comment) > self::MAX_FEEDBACK_COMMENT_CHARS) {
                $comment = substr($comment, 0, self::MAX_FEEDBACK_COMMENT_CHARS);
            }
        }
        // Drop free text for one-click positive ratings.
        if ('good' === $rating) {
            $comment = '';
        }
        // Bound an optional client plugin version for release correlation.
        $pluginVersion = sanitize_text_field((string) ($payload['plugin_version'] ?? ''));
        if (strlen($pluginVersion) > 30) {
            $pluginVersion = substr($pluginVersion, 0, 30);
        }
        // Plugin Setup Wizard defaults to source=plugin; reject unknown slugs.
        $source = sanitize_key((string) ($payload['source'] ?? 'plugin'));
        if (!in_array($source, ['plugin', 'live'], true)) {
            $source = 'plugin';
        }
        // Optional domain package UUID for correlating a wizard run.
        $packageId = strtolower(sanitize_text_field((string) ($payload['package_id'] ?? '')));
        if ('' !== $packageId && !wp_is_uuid($packageId, 4)) {
            $packageId = '';
        }
        // Return the gateway-safe feedback envelope.
        return [
            'rating'         => $rating,
            'comment'        => $comment,
            'plugin_version' => $pluginVersion,
            'source'         => $source,
            'package_id'     => $packageId,
        ];
    }
    /**
     * Decode and sanitize a JSON progress payload from admin-ajax POST data.
     *
     * @since 3.0.0
     * @param mixed $raw Raw POST value.
     * @return array<string, mixed>|null Sanitized payload or null when invalid.
     */
    public function decodeProgressPayload($raw)
    {
        // Reject non-string payloads early.
        if (!is_string($raw) || '' === $raw) {
            return [];
        }
        // Reject oversized JSON before decoding.
        if (strlen($raw) > self::MAX_PROGRESS_JSON_BYTES) {
            return null;
        }
        // Decode JSON from the browser boundary.
        $decoded = json_decode(wp_unslash($raw), true);
        if (!is_array($decoded)) {
            return null;
        }
        // Return sanitized gateway-safe progress fields.
        return $this->sanitizeProgressPayload($decoded);
    }
    /**
     * Return allowed remote progress step slugs.
     *
     * @since 3.0.0
     * @return string[]
     */
    private function allowedProgressSteps(): array
    {
        return [
            SetupWizardSteps::SCAN,
            SetupWizardSteps::INTRO,
            SetupWizardSteps::INDUSTRY,
            SetupWizardSteps::GOAL,
            SetupWizardSteps::RECOMMENDATIONS,
            SetupWizardSteps::CONNECT,
            SetupWizardSteps::TRIAL,
            SetupWizardSteps::SUCCESS,
            'skipped',
        ];
    }
}
