<?php

namespace Notifal\Infrastructure\WordPress\Admin\Support;

defined('ABSPATH') || exit;

/**
 * Registers backend AI support widget AJAX proxy for token minting.
 *
 * @since 2.3.0
 * @updated 3.0.0 Backend channel replaced; support API base is filterable via SupportUrlResolver.
 * @updated 3.0.0 Forward accurate Pro install, activation, license, and AI connection state.
 * @package Notifal\Infrastructure\WordPress\Admin\Support
 * @author Hossein <hossein@notifal.com>
 */
class NasSupportWidgetRegistrar
{
    /**
     * Register WordPress hooks for backend support widget loading.
     *
     * @return void
     * @since 2.3.0
     */
    public static function register(): void
    {
        add_action('wp_ajax_notifal_nas_mint_embed_token', [self::class, 'ajax_mint_embed_token']);
        NasSupportContextAjaxController::register();
    }

    /**
     * Build localized config for shared admin script loader.
     *
     * @return array<string,mixed>
     * @since 2.3.0
     * @updated 3.0.0
     */
    public static function localized_config(): array
    {
        // Resolve mint-time context from the current WordPress admin session.
        $mintContext = NasSupportContextService::build_mint_context();

        // Resolve support API base.
        $supportBase = (new SupportUrlResolver())->getBaseUrl();

        return [
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('notifal_nas_mint_embed_token'),
            'contextNonce'  => wp_create_nonce('notifal_nas_support_context'),
            'apiBase'       => esc_url_raw($supportBase),
            'locale'        => sanitize_text_field(get_locale()),
            'rtl'           => is_rtl(),
            'wpLocalHour'   => (int) current_time('G'),
            'contactName'   => sanitize_text_field((string) ($mintContext['contact_name'] ?? '')),
            'siteContext'   => $mintContext,
        ];
    }

    /**
     * Proxy embed token mint request to notifal.com server API.
     *
     * @return void
     * @since 2.3.0
     */
    public static function ajax_mint_embed_token(): void
    {
        check_ajax_referer('notifal_nas_mint_embed_token', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'forbidden'], 403);
        }

        $rate_limiter = new NasSupportMintRateLimiter();
        if (!$rate_limiter->is_allowed()) {
            wp_send_json_error(['message' => 'rate_limited'], 429);
        }

        $domain = NasEmbedClientProof::normalize_domain(
            (string) wp_parse_url(home_url(), PHP_URL_HOST)
        );

        if ('' === $domain) {
            wp_send_json_error(['message' => 'invalid_domain'], 400);
        }

        $channel = NasEmbedClientProof::CHANNEL_BACKEND;
        $headers = array_merge(
            [
                'Content-Type' => 'application/json',
                'User-Agent'   => 'Notifal/' . (defined('NOTIFAL_VERSION') ? NOTIFAL_VERSION : '0.0.0'),
            ],
            NasEmbedClientProof::build_headers($domain, $channel)
        );

        $install_proof = defined('NAS_INSTALL_PROOF') ? NAS_INSTALL_PROOF : getenv('NAS_INSTALL_PROOF');
        $install_proof = is_string($install_proof) ? trim($install_proof) : '';
        if ('' !== $install_proof) {
            $headers['X-Nas-Install-Proof'] = $install_proof;
        }

        $rate_limiter->hit();

        // Forward client site locale so backend greetings match the WordPress admin language.
        $locale = isset($_POST['locale'])
            ? sanitize_text_field(wp_unslash((string) $_POST['locale']))
            : sanitize_text_field(get_locale());

        // Decode optional site context JSON from the loader.
        $siteContext = NasSupportContextService::build_mint_context();
        if (isset($_POST['site_context'])) {
            $raw = wp_unslash((string) $_POST['site_context']);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $siteContext = array_merge(
                    $siteContext,
                    array_intersect_key(
                        $decoded,
                        array_flip([
                            'contact_name',
                            'wp_local_hour',
                            'admin_email',
                            'admin_page',
                            'notifal_pro',
                            'notifal_pro_installed',
                            'notifal_pro_active',
                            'notifal_pro_license',
                            'ai_connected',
                            'woocommerce',
                        ])
                    )
                );
            }
        }

        // Resolve mint endpoint so stage hosts can override production.
        $mintUrl = (new SupportUrlResolver())->route('embed/mint');

        $response = wp_remote_post(
            esc_url_raw($mintUrl),
            [
                'timeout' => 20,
                'headers' => $headers,
                'body'    => wp_json_encode(
                    [
                        'domain'       => $domain,
                        'channel'      => $channel,
                        'locale'       => $locale,
                        'site_context' => $siteContext,
                    ]
                ),
            ]
        );

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'mint_transport_failed'], 503);
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body        = json_decode((string) wp_remote_retrieve_body($response), true);

        if (!is_array($body) || empty($body['success'])) {
            $remote_code = 'mint_failed';

            if (is_array($body)) {
                if (!empty($body['code']) && is_string($body['code'])) {
                    $remote_code = sanitize_key($body['code']);
                } elseif (
                    !empty($body['data']['error']['code'])
                    && is_string($body['data']['error']['code'])
                ) {
                    $remote_code = sanitize_key($body['data']['error']['code']);
                }
            }

            wp_send_json_error(
                [
                    'message' => $remote_code,
                    'status'  => $status_code > 0 ? $status_code : 503,
                ],
                $status_code > 0 ? $status_code : 503
            );
        }

        wp_send_json_success($body['data'] ?? []);
    }
}
