<?php

namespace Notifal\Infrastructure\WordPress\Admin\Support;

defined('ABSPATH') || exit;

/**
 * AJAX endpoints for consented NAS support site-context snapshots.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class NasSupportContextAjaxController
{
    /**
     * Register AJAX handlers for backend support context reads.
     *
     * @return void
     * @since 3.0.0
     */
    public static function register(): void
    {
        add_action('wp_ajax_notifal_nas_support_context_summary', [self::class, 'summary']);
        add_action('wp_ajax_notifal_nas_support_context_analytics', [self::class, 'analytics']);
        add_action('wp_ajax_notifal_nas_support_context_notifications', [self::class, 'notifications']);
        add_action('wp_ajax_notifal_nas_support_context_templates', [self::class, 'templates']);
    }

    /**
     * Return a lightweight site summary for support turns.
     *
     * @return void
     * @since 3.0.0
     */
    public static function summary(): void
    {
        self::guard_request(false);
        wp_send_json_success(NasSupportContextService::build_summary());
    }

    /**
     * Return analytics snapshot after explicit consent.
     *
     * @return void
     * @since 3.0.0
     */
    public static function analytics(): void
    {
        self::guard_request(true);
        wp_send_json_success(NasSupportContextService::build_analytics_snapshot());
    }

    /**
     * Return notification snapshot after explicit consent.
     *
     * @return void
     * @since 3.0.0
     */
    public static function notifications(): void
    {
        self::guard_request(true);
        wp_send_json_success(NasSupportContextService::build_notifications_snapshot());
    }

    /**
     * Return template snapshot after explicit consent.
     *
     * @return void
     * @since 3.0.0
     */
    public static function templates(): void
    {
        self::guard_request(true);
        wp_send_json_success(NasSupportContextService::build_templates_snapshot());
    }

    /**
     * Verify nonce, capability, optional consent, and rate limit.
     *
     * @param bool $requiresConsent Whether explicit consent flag is required.
     * @return void
     * @since 3.0.0
     */
    private static function guard_request(bool $requiresConsent): void
    {
        // Verify the NAS context nonce from shared admin script config.
        check_ajax_referer('notifal_nas_support_context', 'nonce');

        // Restrict reads to administrators only.
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'forbidden'], 403);
        }

        // Require explicit consent for richer snapshots.
        if ($requiresConsent) {
            $consent = isset($_POST['consent'])
                ? sanitize_key(wp_unslash((string) $_POST['consent']))
                : '';
            if ('1' !== $consent && 'true' !== $consent) {
                wp_send_json_error(['message' => 'consent_required'], 403);
            }
        }

        // Apply a lightweight per-user rate limit.
        $userId = get_current_user_id();
        $key = 'notifal_nas_ctx_' . $userId;
        if (get_transient($key)) {
            wp_send_json_error(['message' => 'rate_limited'], 429);
        }
        set_transient($key, 1, 30);
    }
}
