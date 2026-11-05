<?php

namespace Notifal\Modules\SetupWizard\Application\Services;

use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\Ai\Application\Services\InstallationCredentialStore;
use Notifal\Modules\SetupWizard\Presentation\Admin\Menu\MenuController;

defined('ABSPATH') || exit;

/**
 * Fire-and-forget Setup Wizard analytics pings to notifal.com.
 *
 * Separate from AI gateway progress reporting so install / open / skip
 * signals work before account connection and never block the admin UI.
 *
 * @since 3.0.1
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardTelemetryService
{
    /**
     * Transient key used to throttle repeated opened events.
     *
     * @var string
     */
    private const OPENED_THROTTLE_KEY = 'notifal_setup_wizard_opened_ping';

    /**
     * Seconds between opened pings for the same installation.
     *
     * @var int
     */
    private const OPENED_THROTTLE_SECONDS = 1800;

    /**
     * Allowed analytics event slugs.
     *
     * @var string[]
     */
    private const ALLOWED_EVENTS = [
        'opened',
        'skipped',
        'completed',
    ];

    /**
     * Local installation identity store.
     *
     * @var InstallationCredentialStore
     */
    private InstallationCredentialStore $credentialStore;

    /**
     * Domain host normalizer shared with package lookups.
     *
     * @var SetupWizardDomainHostNormalizer
     */
    private SetupWizardDomainHostNormalizer $domainNormalizer;

    /**
     * @since 3.0.1
     * @param InstallationCredentialStore      $credentialStore Local site identity.
     * @param SetupWizardDomainHostNormalizer $domainNormalizer Host normalizer.
     */
    public function __construct(
        InstallationCredentialStore $credentialStore,
        SetupWizardDomainHostNormalizer $domainNormalizer
    ) {
        // Keep installation UUID access for stable install analytics.
        $this->credentialStore = $credentialStore;
        // Keep domain normalization aligned with package / gateway hosts.
        $this->domainNormalizer = $domainNormalizer;
    }

    /**
     * Register non-blocking analytics listeners for the Setup Wizard.
     *
     * @since 3.0.1
     * @return void
     */
    public static function register(): void
    {
        // Ping notifal.com when the fullscreen wizard admin screen loads.
        add_action('current_screen', [self::class, 'handleCurrentScreen']);
        // Record skips even when the admin never connects or runs analysis.
        add_action(ActionHooks::SETUP_WIZARD_SKIPPED, [self::class, 'handleSkipped']);
        // Record successful finishes for the plugin wizard funnel.
        add_action(ActionHooks::SETUP_WIZARD_COMPLETED, [self::class, 'handleCompleted']);
    }

    /**
     * Emit an opened event the first time the wizard screen is ready.
     *
     * @since 3.0.1
     * @param \WP_Screen|null $screen Current admin screen.
     * @return void
     */
    public static function handleCurrentScreen($screen): void
    {
        // Ignore non-object screens during early boot.
        if (!is_object($screen) || empty($screen->id)) {
            return;
        }

        // Only track the dedicated Setup Wizard admin page.
        $expected = 'notifal_page_' . MenuController::PAGE_SLUG;
        if ($expected !== (string) $screen->id) {
            return;
        }

        // Resolve the singleton telemetry service from the container.
        $service = notifal_app(self::class);
        // Opened pings are throttled so hard refreshes do not flood analytics.
        $service->trackOpened();
    }

    /**
     * Emit a skipped event after local skip state is persisted.
     *
     * @since 3.0.1
     * @return void
     */
    public static function handleSkipped(): void
    {
        notifal_app(self::class)->track('skipped');
    }

    /**
     * Emit a completed event after local complete state is persisted.
     *
     * @since 3.0.1
     * @return void
     */
    public static function handleCompleted(): void
    {
        notifal_app(self::class)->track('completed');
    }

    /**
     * Track a throttled wizard opened event.
     *
     * @since 3.0.1
     * @return void
     */
    public function trackOpened(): void
    {
        // Skip when this installation already opened the wizard recently.
        if (false !== get_transient(self::OPENED_THROTTLE_KEY)) {
            return;
        }

        // Persist the throttle before the remote call to avoid stampedes.
        set_transient(self::OPENED_THROTTLE_KEY, 1, self::OPENED_THROTTLE_SECONDS);
        // Fire the install / open signal without blocking page paint.
        $this->track('opened');
    }

    /**
     * Queue one analytics event for a non-blocking notifal.com POST.
     *
     * @since 3.0.1
     * @param string $event Event slug (opened|skipped|completed).
     * @return void
     */
    public function track(string $event): void
    {
        // Normalize and validate the event slug against the allowlist.
        $event = sanitize_key($event);
        if (!in_array($event, self::ALLOWED_EVENTS, true)) {
            return;
        }

        // Build the sanitized payload once before scheduling transport.
        $payload = $this->buildPayload($event);
        if (empty($payload['domain'])) {
            return;
        }

        /**
         * Fires before a Setup Wizard analytics event is sent to notifal.com.
         *
         * @since 3.0.1
         * @param string               $event   Event slug.
         * @param array<string, mixed> $payload Sanitized analytics payload.
         */
        do_action(ActionHooks::SETUP_WIZARD_TELEMETRY_QUEUED, $event, $payload);

        // Dispatch after the HTTP response so admin UX stays snappy.
        $endpoint = Urls::API_BASE_NOTIFAL . '/setup-wizard-event';
        $body     = wp_json_encode($payload);
        if (!is_string($body) || '' === $body) {
            return;
        }

        // Capture constants for the shutdown closure without late static binding issues.
        $userAgent = 'Notifal-WordPress/' . (defined('NOTIFAL_VERSION') ? NOTIFAL_VERSION : 'unknown');

        register_shutdown_function(
            static function () use ($endpoint, $body, $userAgent): void {
                // Best-effort flush so the browser receives HTML/JSON first when possible.
                if (function_exists('fastcgi_finish_request')) {
                    // Ignore failures; analytics must never affect the wizard response.
                    @fastcgi_finish_request();
                }

                // Fire-and-forget POST; failures are intentionally ignored.
                wp_remote_post(
                    $endpoint,
                    [
                        'timeout'   => 1,
                        'blocking'  => false,
                        'sslverify' => true,
                        'headers'   => [
                            'Content-Type' => 'application/json',
                            'User-Agent'   => $userAgent,
                        ],
                        'body'      => $body,
                    ]
                );
            }
        );
    }

    /**
     * Build the public analytics payload for notifal.com.
     *
     * @since 3.0.1
     * @param string $event Event slug.
     * @return array<string, mixed>
     */
    private function buildPayload(string $event): array
    {
        // Resolve a stable domain host for funnel grouping.
        $domain = '';
        try {
            $domain = $this->domainNormalizer->normalize((string) home_url('/'));
        } catch (\Throwable $exception) {
            $domain = '';
        }

        // Prefer a persistent site UUID when AI installation metadata exists.
        $siteUuid = '';
        try {
            $siteUuid = sanitize_text_field($this->credentialStore->getOrCreateSiteUuid());
        } catch (\Throwable $exception) {
            $siteUuid = '';
        }

        // Keep admin email optional for recovery / outreach without requiring it.
        $adminEmail = sanitize_email((string) get_option('admin_email'));

        return [
            'event'          => $event,
            'domain'         => $domain,
            'site_uuid'      => $siteUuid,
            'admin_email'    => $adminEmail,
            'plugin_version' => defined('NOTIFAL_VERSION') ? sanitize_text_field((string) NOTIFAL_VERSION) : '',
            'php_version'    => sanitize_text_field(PHP_VERSION),
            'wp_version'     => sanitize_text_field((string) get_bloginfo('version')),
            'is_connected'   => $this->credentialStore->isConnected() ? 1 : 0,
            'source'         => 'plugin',
        ];
    }
}
