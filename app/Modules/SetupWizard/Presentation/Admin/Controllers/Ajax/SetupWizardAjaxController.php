<?php
namespace Notifal\Modules\SetupWizard\Presentation\Admin\Controllers\Ajax;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\Ai\Application\Services\AiIndustryOptions;
use Notifal\Modules\Ai\Application\Services\InstallationCredentialStore;
use Notifal\Modules\Ai\Application\Services\SiteProfileService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardDomainPackageService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardGatewayService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardHeuristicAnalysisService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardNotificationImportService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardProInstallService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardProLicenseConnectService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardRecommendationService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardRequestGuard;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardSessionService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardSiteAnalysisStoreService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardSiteScrapeService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardStateService;
use Notifal\Modules\SetupWizard\Domain\Constants\SetupWizardSteps;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;
/**
 * Local AJAX endpoints for Setup Wizard orchestration.
 *
 * @since 3.0.0
 * @updated 3.0.0 Add domain package preflight before local scan / AI spend.
 * @updated 3.0.0 Add force-refresh, package profile sync, and package replace AJAX.
 * @updated 3.0.0 Ignore late save_step requests after the wizard is completed.
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardAjaxController
{
    /**
     * Register AJAX actions.
     *
     * @since 3.0.0
     * @updated 3.0.0 Register domain package force-refresh, profile sync, and replace.
     * @return void
     */
    public static function register(): void
    {
        // Register privileged admin AJAX handlers for the Setup Wizard.
        add_action('wp_ajax_notifal_setup_wizard_skip', [self::class, 'handleSkip']);
        add_action('wp_ajax_notifal_setup_wizard_complete', [self::class, 'handleComplete']);
        add_action('wp_ajax_notifal_setup_wizard_save_step', [self::class, 'handleSaveStep']);
        add_action('wp_ajax_notifal_setup_wizard_scan', [self::class, 'handleScan']);
        add_action('wp_ajax_notifal_setup_wizard_domain_package_preflight', [self::class, 'handleDomainPackagePreflight']);
        add_action('wp_ajax_notifal_setup_wizard_domain_package_force_refresh', [self::class, 'handleDomainPackageForceRefresh']);
        add_action('wp_ajax_notifal_setup_wizard_domain_package_sync_profile', [self::class, 'handleDomainPackageSyncProfile']);
        add_action('wp_ajax_notifal_setup_wizard_domain_package_replace', [self::class, 'handleDomainPackageReplace']);
        add_action('wp_ajax_notifal_setup_wizard_save_analysis', [self::class, 'handleSaveAnalysis']);
        add_action('wp_ajax_notifal_setup_wizard_save_profile', [self::class, 'handleSaveProfile']);
        add_action('wp_ajax_notifal_setup_wizard_recommendations', [self::class, 'handleRecommendations']);
        add_action('wp_ajax_notifal_setup_wizard_import', [self::class, 'handleImport']);
        add_action('wp_ajax_notifal_setup_wizard_trial', [self::class, 'handleTrial']);
        add_action('wp_ajax_notifal_setup_wizard_install_pro', [self::class, 'handleInstallPro']);
        add_action('wp_ajax_notifal_setup_wizard_activate_pro', [self::class, 'handleActivatePro']);
        add_action('wp_ajax_notifal_setup_wizard_report_progress', [self::class, 'handleReportProgress']);
        add_action('wp_ajax_notifal_setup_wizard_feedback', [self::class, 'handleFeedback']);
    }
    /**
     * Mark the wizard as skipped.
     *
     * @since 3.0.0
     * @return void
     */
    public static function handleSkip(): void
    {
        // Verify AJAX request with nonce and administrator capability.
        notifal_verify_ajax_request('notifal_setup_wizard', 'manage_options');
        // Resolve local wizard state service.
        $stateService = notifal_app(SetupWizardStateService::class);
        // Persist skipped terminal state locally.
        $stateService->markSkipped();
        /**
         * Fires after the Setup Wizard is skipped.
         *
         * @since 3.0.0
         */
        do_action(ActionHooks::SETUP_WIZARD_SKIPPED);
        // Return success to the browser without blocking on remote telemetry.
        notifal_json_success(
            [
                'status' => $stateService->getStatus(),
            ],
            __('Setup Wizard skipped.', 'notifal')
        );
    }
    /**
     * Mark the wizard as completed.
     *
     * @since 3.0.0
     * @return void
     */
    public static function handleComplete(): void
    {
        // Verify AJAX request with nonce and administrator capability.
        notifal_verify_ajax_request('notifal_setup_wizard', 'manage_options');
        // Resolve local wizard services.
        $stateService = notifal_app(SetupWizardStateService::class);
        $sessionService = notifal_app(SetupWizardSessionService::class);
        // Persist completed terminal state locally.
        $stateService->markCompleted();
        // Clear stored in-progress session data including trial secrets.
        $sessionService->clear();
        // Remove cached site analysis after the wizard is finished.
        notifal_app(SetupWizardSiteAnalysisStoreService::class)->clear();
        /**
         * Fires after the Setup Wizard is completed.
         *
         * @since 3.0.0
         */
        do_action(ActionHooks::SETUP_WIZARD_COMPLETED);
        // Return success to the browser without blocking on remote telemetry.
        notifal_json_success(
            [
                'status' => $stateService->getStatus(),
            ],
            __('Setup Wizard completed.', 'notifal')
        );
    }
    /**
     * Persist the latest reached step locally.
     *
     * @since 3.0.0
     * @updated 3.0.0 No-op step saves after the wizard is already completed.
     * @return void
     */
    public static function handleSaveStep(): void
    {
        // Verify AJAX request with nonce and administrator capability.
        notifal_verify_ajax_request('notifal_setup_wizard', 'manage_options');
        // Read the submitted step slug.
        $step = Helper::sanitizeInput(isset($_POST['step']) ? wp_unslash($_POST['step']) : '', 'key');
        // Reject unknown step slugs.
        if (!self::isAllowedStep($step)) {
            notifal_json_error(__('Invalid wizard step.', 'notifal'));
            return;
        }
        // Resolve local wizard state service.
        $stateService = notifal_app(SetupWizardStateService::class);
        // When Finish already persisted completed, acknowledge without rewriting state.
        if ($stateService->isCompleted()) {
            notifal_json_success(
                [
                    'status' => $stateService->getStatus(),
                    'step'   => $stateService->getLastStep(),
                ],
                __('Setup Wizard already completed.', 'notifal')
            );
            return;
        }
        // Persist the latest reached step locally.
        $stateService->markStepReached($step);
        /**
         * Fires when the Setup Wizard reaches a new step.
         *
         * @since 3.0.0
         * @param string $step Step slug.
         */
        do_action(ActionHooks::SETUP_WIZARD_STEP_REACHED, $step);
        // Return success to the browser.
        notifal_json_success(
            [
                'step'   => $step,
                'status' => $stateService->getStatus(),
            ]
        );
    }
    /**
     * Preflight the hosted domain analysis package before local scan / AI spend.
     *
     * Soft-misses (missing, expired, unreachable) return success with hit=false so
     * the existing wizard path continues without token waste on cache hits.
     *
     * @since 3.0.0
     * @return void
     */
    public static function handleDomainPackagePreflight(): void
    {
        // Verify AJAX request with nonce and administrator capability.
        notifal_verify_ajax_request('notifal_setup_wizard', 'manage_options');
        // Apply a lightweight rate limit before the outbound gateway lookup.
        notifal_app(SetupWizardRequestGuard::class)->enforceRateLimit('domain_package_preflight');
        // Resolve the current site package without starting analysis or generation.
        $result = notifal_app(SetupWizardDomainPackageService::class)->preflightCurrentSite();
        // Always acknowledge the client; hit/importable flags drive the UI branch.
        notifal_json_success($result);
    }

    /**
     * Grant one plugin force-refresh soft-expire for the current site domain.
     *
     * @since 3.0.0
     * @return void
     */
    public static function handleDomainPackageForceRefresh(): void
    {
        // Verify AJAX request with nonce and administrator capability.
        notifal_verify_ajax_request('notifal_setup_wizard', 'manage_options');
        // Apply a tight rate limit before the outbound force-refresh grant.
        notifal_app(SetupWizardRequestGuard::class)->enforceRateLimit('domain_package_force_refresh');
        // Ask the gateway to soft-expire the fresh package once per lifetime.
        $result = notifal_app(SetupWizardDomainPackageService::class)->forceRefreshCurrentSite();
        if (empty($result['success'])) {
            $error = is_array($result['error'] ?? null) ? $result['error'] : [];
            notifal_json_error(
                sanitize_text_field((string) ($error['message'] ?? __('Force refresh is not available.', 'notifal'))),
                [
                    'code' => sanitize_key((string) ($error['code'] ?? 'force_refresh_unavailable')),
                ]
            );
            return;
        }
        notifal_json_success(is_array($result['data'] ?? null) ? $result['data'] : []);
    }

    /**
     * Dual-sync editable profile fields to the gateway package analysis blob.
     *
     * Local profile save still runs through handleSaveProfile; this endpoint only
     * patches the hosted package so live Analyze Site stays in sync.
     *
     * @since 3.0.0
     * @return void
     */
    public static function handleDomainPackageSyncProfile(): void
    {
        // Verify AJAX request with nonce and administrator capability.
        notifal_verify_ajax_request('notifal_setup_wizard', 'manage_options');
        // Apply a lightweight rate limit before the outbound profile PATCH.
        notifal_app(SetupWizardRequestGuard::class)->enforceRateLimit('domain_package_sync_profile');

        // Read the package UUID and editable profile fields from the browser.
        $packageId = sanitize_text_field(isset($_POST['package_id']) ? wp_unslash($_POST['package_id']) : '');
        $businessDescription = isset($_POST['business_description'])
            ? sanitize_textarea_field(wp_unslash($_POST['business_description']))
            : '';
        $primaryColor = Helper::sanitizeInput(
            isset($_POST['primary_color']) ? wp_unslash($_POST['primary_color']) : '',
            'text'
        );
        // Brand colors may arrive as JSON; do not sanitize_text_field the raw JSON string.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $brandColorsRaw = isset($_POST['brand_colors']) ? wp_unslash($_POST['brand_colors']) : '';
        $brandColorsRaw = is_string($brandColorsRaw) ? $brandColorsRaw : '';
        $brandColors = [];
        if ('' !== trim($brandColorsRaw)) {
            $decoded = json_decode($brandColorsRaw, true);
            $brandColors = is_array($decoded) ? $decoded : [];
        }

        // Patch the hosted package analysis blob for live dual-sync.
        $result = notifal_app(SetupWizardDomainPackageService::class)->syncPackageProfile(
            $packageId,
            [
                'business_description' => $businessDescription,
                'primary_color'        => $primaryColor,
                'brand_colors'         => $brandColors,
            ]
        );
        if (empty($result['success'])) {
            $error = is_array($result['error'] ?? null) ? $result['error'] : [];
            notifal_json_error(
                sanitize_text_field(
                    (string) ($error['message'] ?? __('Could not sync your profile to Notifal.', 'notifal'))
                ),
                [
                    'code' => sanitize_key((string) ($error['code'] ?? 'gateway_error')),
                ]
            );
            return;
        }
        notifal_json_success(is_array($result['data'] ?? null) ? $result['data'] : []);
    }

    /**
     * Overwrite the hosted domain package after a needs-review rebuild.
     *
     * @since 3.0.0
     * @return void
     */
    public static function handleDomainPackageReplace(): void
    {
        // Verify AJAX request with nonce and administrator capability.
        notifal_verify_ajax_request('notifal_setup_wizard', 'manage_options');
        // Apply a tight rate limit before the outbound replace write.
        notifal_app(SetupWizardRequestGuard::class)->enforceRateLimit('domain_package_replace');

        // Decode analysis and notifications JSON payloads from the browser.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $analysisRaw = isset($_POST['analysis']) ? wp_unslash($_POST['analysis']) : '';
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $notificationsRaw = isset($_POST['notifications']) ? wp_unslash($_POST['notifications']) : '';
        $analysisDecoded = is_string($analysisRaw) ? json_decode($analysisRaw, true) : null;
        $notificationsDecoded = is_string($notificationsRaw) ? json_decode($notificationsRaw, true) : null;
        $analysis = is_array($analysisDecoded) ? $analysisDecoded : [];
        $notifications = is_array($notificationsDecoded) ? $notificationsDecoded : [];
        if (empty($analysis) || empty($notifications)) {
            notifal_json_error(__('Invalid package replace payload.', 'notifal'));
            return;
        }

        // Persist the rebuilt package so live Analyze Site shows the new titles.
        $result = notifal_app(SetupWizardDomainPackageService::class)->replaceCurrentSitePackage(
            $analysis,
            $notifications
        );
        if (empty($result['success'])) {
            $error = is_array($result['error'] ?? null) ? $result['error'] : [];
            notifal_json_error(
                sanitize_text_field(
                    (string) ($error['message'] ?? __('Could not update the saved package.', 'notifal'))
                ),
                [
                    'code' => sanitize_key((string) ($error['code'] ?? 'gateway_error')),
                ]
            );
            return;
        }
        notifal_json_success(is_array($result['data'] ?? null) ? $result['data'] : []);
    }

    /**
     * Scrape the local site and return heuristic analysis metadata.
     *
     * @since 3.0.0
     * @updated 3.0.0 Reuse only hosted analysis snapshots as scan cache hits.
     * @return void
     */
    public static function handleScan(): void
    {
        // Verify AJAX request with nonce and administrator capability.
        notifal_verify_ajax_request('notifal_setup_wizard', 'manage_options');
        // Apply a lightweight rate limit to the expensive scan step.
        notifal_app(SetupWizardRequestGuard::class)->enforceRateLimit('scan');
        // Read optional force refresh flag from the browser (Try again bypasses cache).
        $forceRefresh = !empty($_POST['force_refresh']);
        // Scrape public home page content and plugin metadata.
        $scrape = notifal_app(SetupWizardSiteScrapeService::class)->scrape((bool) $forceRefresh);
        // Resolve the persisted analysis store for reuse checks.
        $analysisStore = notifal_app(SetupWizardSiteAnalysisStoreService::class);
        // Reuse only a prior hosted AI snapshot when the scrape fingerprint still matches.
        // Heuristic scrape_only snapshots must not permanently skip hosted generation.
        if (!$forceRefresh && $analysisStore->matchesScrape($scrape)) {
            $stored = $analysisStore->getPublicSnapshot();
            $storedAnalysis = is_array($stored['analysis'] ?? null) ? $stored['analysis'] : null;
            $storedSource = is_array($storedAnalysis)
                ? sanitize_key((string) ($storedAnalysis['source'] ?? ''))
                : '';
            if (null !== $storedAnalysis && 'hosted' === $storedSource) {
                notifal_json_success(
                    [
                        'scrape'   => $scrape,
                        'analysis' => $storedAnalysis,
                        'cached'   => true,
                    ]
                );
                return;
            }
        }
        // Build scrape-only baseline metadata for the browser AI step.
        $analysis = notifal_app(SetupWizardHeuristicAnalysisService::class)->analyze($scrape);
        // Return scrape and analysis payloads for the browser generation step.
        notifal_json_success(
            [
                'scrape'   => $scrape,
                'analysis' => $analysis,
                'cached'   => false,
            ]
        );
    }

    /**
     * Persist the final site analysis snapshot after hosted AI analysis.
     *
     * @since 3.0.0
     * @return void
     */
    public static function handleSaveAnalysis(): void
    {
        // Verify AJAX request with nonce and administrator capability.
        notifal_verify_ajax_request('notifal_setup_wizard', 'manage_options');
        // Resolve analysis store and scrape services.
        $analysisStore = notifal_app(SetupWizardSiteAnalysisStoreService::class);
        // Decode and sanitize the submitted analysis payload.
        $analysis = $analysisStore->decodeAnalysisPayload($_POST['payload'] ?? '');
        if (null === $analysis) {
            notifal_json_error(__('Invalid site analysis payload.', 'notifal'));
            return;
        }
        // Require intro content so empty AI responses are not persisted.
        if (3 > count($analysis['introduction_paragraphs'])) {
            notifal_json_error(__('Site analysis is incomplete.', 'notifal'));
            return;
        }
        // Re-scrape on save so the stored fingerprint matches server-side content.
        $scrape = notifal_app(SetupWizardSiteScrapeService::class)->scrape();
        // Persist the sanitized scrape and analysis snapshot.
        $snapshot = $analysisStore->save($scrape, $analysis);
        // Return the browser-safe snapshot to the client.
        notifal_json_success(
            [
                'siteAnalysis' => [
                    'cached'   => true,
                    'saved_at' => sanitize_text_field((string) ($snapshot['saved_at'] ?? '')),
                    'scrape'   => is_array($snapshot['scrape'] ?? null) ? $snapshot['scrape'] : [],
                    'analysis' => is_array($snapshot['analysis'] ?? null) ? $snapshot['analysis'] : [],
                ],
            ]
        );
    }
    /**
     * Persist confirmed industry and business profile fields.
     *
     * @since 3.0.0
     * @return void
     */
    public static function handleSaveProfile(): void
    {
        // Verify AJAX request with nonce and administrator capability.
        notifal_verify_ajax_request('notifal_setup_wizard', 'manage_options');
        // Read submitted profile fields from the browser.
        $industry = Helper::sanitizeInput(isset($_POST['industry']) ? wp_unslash($_POST['industry']) : '', 'text');
        $industrySlug = Helper::sanitizeInput(isset($_POST['industry_slug']) ? wp_unslash($_POST['industry_slug']) : '', 'key');
        $businessDescription = isset($_POST['business_description']) ? sanitize_textarea_field(wp_unslash($_POST['business_description'])) : '';
        $primaryColor = Helper::sanitizeInput(isset($_POST['primary_color']) ? wp_unslash($_POST['primary_color']) : '', 'text');
        $goal = Helper::sanitizeInput(isset($_POST['goal']) ? wp_unslash($_POST['goal']) : '', 'key');
        // Brand colors may arrive as JSON; do not sanitize_text_field the raw JSON string.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $brandColorsRaw = isset($_POST['brand_colors']) ? wp_unslash($_POST['brand_colors']) : '';
        $brandColorsRaw = is_string($brandColorsRaw) ? $brandColorsRaw : '';
        $brandColors = [];
        if ('' !== trim($brandColorsRaw)) {
            $decoded = json_decode($brandColorsRaw, true);
            $brandColors = is_array($decoded) ? $decoded : [];
        }
        // Reject unknown goal slugs when one is submitted.
        $guard = notifal_app(SetupWizardRequestGuard::class);
        if ('' !== $goal && !$guard->isValidGoalSlug($goal)) {
            notifal_json_error(__('Invalid goal selection.', 'notifal'));
            return;
        }
        // Reject unknown industry slugs when one is submitted.
        if ('' !== $industrySlug && !$guard->isValidIndustrySlug($industrySlug)) {
            notifal_json_error(__('Invalid industry selection.', 'notifal'));
            return;
        }
        // Persist the exact UI card slug (already validated above).
        if ('' !== $industrySlug) {
            $industrySlug = AiIndustryOptions::normalizeSlug($industrySlug);
        }
        // Resolve shared site profile and session services.
        $siteProfile = notifal_app(SiteProfileService::class);
        $sessionService = notifal_app(SetupWizardSessionService::class);
        try {
            // Persist industry when provided by the admin.
            if ('' !== $industry) {
                $siteProfile->saveIndustry($industry, $industrySlug);
            }
            // Persist business description when provided.
            if ('' !== $businessDescription) {
                $siteProfile->saveBusinessDescription($businessDescription);
            }
            // Persist primary brand color when provided.
            if ('' !== $primaryColor || !empty($brandColors)) {
                $resolvedPrimary = '' !== $primaryColor
                    ? $primaryColor
                    : (string) ($brandColors[0] ?? '');
                $siteProfile->savePrimaryColor($resolvedPrimary, $brandColors);
            }
        } catch (\InvalidArgumentException $exception) {
            notifal_json_error(__('Could not save your site profile. Please check your entries and try again.', 'notifal'));
            return;
        }
        // Persist selected goal in the wizard session when supplied.
        if ('' !== $goal) {
            $sessionService->saveGoal($goal);
        }
        // Return the saved profile snapshot without server-only session secrets.
        notifal_json_success(
            [
                'site_profile' => $siteProfile->get(),
                'session'      => $sessionService->getPublicSnapshot(),
            ]
        );
    }
    /**
     * Fetch marketplace recommendations for the selected goal.
     *
     * @since 3.0.0
     * @return void
     */
    public static function handleRecommendations(): void
    {
        // Verify AJAX request with nonce and administrator capability.
        notifal_verify_ajax_request('notifal_setup_wizard', 'manage_options');
        // Apply a lightweight rate limit before calling the marketplace API.
        $guard = notifal_app(SetupWizardRequestGuard::class);
        $guard->enforceRateLimit('recommendations');
        // Read goal and industry slugs from the request.
        $goal = Helper::sanitizeInput(isset($_POST['goal']) ? wp_unslash($_POST['goal']) : '', 'key');
        $industrySlug = Helper::sanitizeInput(isset($_POST['industry_slug']) ? wp_unslash($_POST['industry_slug']) : '', 'key');
        // Reject unknown goal slugs before querying the marketplace.
        if ('' === $goal || !$guard->isValidGoalSlug($goal)) {
            notifal_json_error(__('Invalid goal selection.', 'notifal'));
            return;
        }
        // Persist goal selection in the wizard session.
        notifal_app(SetupWizardSessionService::class)->saveGoal($goal);
        // Query marketplace recommendations through the dedicated service.
        $result = notifal_app(SetupWizardRecommendationService::class)->getRecommendations($goal, $industrySlug);
        // Return structured failure payloads to the browser.
        if (empty($result['success'])) {
            notifal_json_error(sanitize_text_field((string) ($result['error'] ?? __('Could not load recommendations.', 'notifal'))));
            return;
        }
        // Return recommendation cards to the browser.
        notifal_json_success(
            [
                'notifications' => $result['notifications'] ?? [],
            ]
        );
    }
    /**
     * Import one marketplace notification during the wizard.
     *
     * @since 3.0.0
     * @return void
     */
    public static function handleImport(): void
    {
        // Verify AJAX request with nonce and administrator capability.
        notifal_verify_ajax_request('notifal_setup_wizard', 'manage_options');
        // Apply a lightweight rate limit before downloading marketplace packages.
        notifal_app(SetupWizardRequestGuard::class)->enforceRateLimit('import');
        // Read import identifiers from the request.
        $notificationId = isset($_POST['notification_id']) ? absint(wp_unslash($_POST['notification_id'])) : 0;
        $fileType = Helper::sanitizeInput(isset($_POST['file_type']) ? wp_unslash($_POST['file_type']) : '', 'key');
        // Import the selected marketplace notification.
        $result = notifal_app(SetupWizardNotificationImportService::class)->import($notificationId, $fileType);
        // Return structured failure payloads to the browser.
        if (empty($result['success'])) {
            notifal_json_error(sanitize_text_field((string) ($result['error'] ?? __('Import failed.', 'notifal'))));
            return;
        }
        // Persist recommendation metadata in the wizard session.
        notifal_app(SetupWizardSessionService::class)->saveRecommendation('marketplace', $notificationId, $fileType);
        // Return import metadata to the browser.
        notifal_json_success($result);
    }
    /**
     * Provision a free Setup Wizard Pro license through the hosted gateway.
     *
     * @since 3.0.0
     * @return void
     */
    public static function handleTrial(): void
    {
        // Verify AJAX request with nonce and administrator capability.
        notifal_verify_ajax_request('notifal_setup_wizard', 'manage_options');
        // Apply a strict rate limit before creating free Pro licenses.
        notifal_app(SetupWizardRequestGuard::class)->enforceRateLimit('trial');
        // Require a connected Notifal account before Pro license provisioning.
        if (!notifal_app(InstallationCredentialStore::class)->isConnected()) {
            notifal_json_error(
                __('Connect your Notifal account before installing Notifal Pro.', 'notifal'),
                [
                    'code' => 'not_connected',
                ]
            );
            return;
        }
        // Request free Pro license creation from the hosted gateway over HTTPS.
        $result = notifal_app(SetupWizardGatewayService::class)->provisionTrial();
        // Return structured failure payloads with stable codes for the chat UI.
        if (empty($result['success'])) {
            $code = sanitize_key((string) ($result['error']['code'] ?? 'trial_failed'));
            $message = sanitize_text_field(
                (string) ($result['error']['message'] ?? __('Could not create your Notifal Pro license.', 'notifal'))
            );
            notifal_json_error(
                $message,
                [
                    'code' => $code,
                ]
            );
            return;
        }
        // Persist install proof in the server-side wizard session only.
        $trialData = is_array($result['data'] ?? null) ? $result['data'] : [];
        $sessionService = notifal_app(SetupWizardSessionService::class);
        $sessionService->saveTrial($trialData);
        // Return only browser-safe metadata without license secrets.
        notifal_json_success(
            [
                'trial'   => [
                    'expires_in_days' => absint($trialData['expires_in_days'] ?? 0),
                    'pro_version'     => sanitize_text_field((string) ($trialData['pro_version'] ?? '')),
                ],
                'session' => $sessionService->getPublicSnapshot(),
            ]
        );
    }
    /**
     * Download and install Notifal Pro package files from the trial session proof.
     *
     * @since 3.0.0
     * @return void
     */
    public static function handleInstallPro(): void
    {
        // Verify AJAX request with nonce and administrator capability.
        notifal_verify_ajax_request('notifal_setup_wizard', 'manage_options');
        // Apply a strict rate limit before downloading plugin packages.
        notifal_app(SetupWizardRequestGuard::class)->enforceRateLimit('install_pro');
        // Read install proof only from the trusted server-side session.
        $sessionService = notifal_app(SetupWizardSessionService::class);
        if (!$sessionService->hasTrialInstallProof()) {
            notifal_json_error(
                __('Notifal Pro install details are missing. Please accept Pro setup again.', 'notifal'),
                [
                    'code' => 'install_proof_missing',
                ]
            );
            return;
        }
        $proof = $sessionService->getTrialInstallProof();
        // Install package files only; activation and license connect run in a later step.
        $result = notifal_app(SetupWizardProInstallService::class)->installPackage(
            $proof['package_url'],
            $proof['trial_domain']
        );
        // Return structured failure payloads with manual fallback metadata.
        if (empty($result['success'])) {
            notifal_json_error(
                sanitize_text_field((string) ($result['error'] ?? __('Could not install Notifal Pro automatically.', 'notifal'))),
                [
                    'code'                => !empty($result['folder_exists']) ? 'folder_exists' : 'install_failed',
                    'manual_download_url' => esc_url_raw((string) ($result['manual_download_url'] ?? '')),
                ]
            );
            return;
        }
        // Return install success metadata without exposing license secrets.
        unset($result['manual_download_url']);
        notifal_json_success($result);
    }
    /**
     * Activate Notifal Pro and connect the trial license from the session proof.
     *
     * @since 3.0.0
     * @return void
     */
    public static function handleActivatePro(): void
    {
        // Verify AJAX request with nonce and administrator capability.
        notifal_verify_ajax_request('notifal_setup_wizard', 'manage_options');
        // Apply a strict rate limit before plugin activation and license connect.
        notifal_app(SetupWizardRequestGuard::class)->enforceRateLimit('activate_pro');
        // Read install proof only from the trusted server-side session.
        $sessionService = notifal_app(SetupWizardSessionService::class);
        if (!$sessionService->hasTrialInstallProof()) {
            notifal_json_error(
                __('Notifal Pro install details are missing. Please accept Pro setup again.', 'notifal'),
                [
                    'code' => 'install_proof_missing',
                ]
            );
            return;
        }
        $proof = $sessionService->getTrialInstallProof();
        // Activate the plugin and connect the trial license like manual Connect & Activate.
        $result = notifal_app(SetupWizardProLicenseConnectService::class)->activateAndConnect(
            $proof['license_key'],
            $proof['trial_domain']
        );
        // Return structured failure payloads with optional manual package fallback.
        if (empty($result['success'])) {
            notifal_json_error(
                sanitize_text_field(
                    (string) ($result['error'] ?? __('Could not activate Notifal Pro automatically.', 'notifal'))
                ),
                [
                    'manual_download_url' => esc_url_raw((string) ($proof['package_url'] ?? '')),
                    'plugin_activated'    => !empty($result['plugin_activated']),
                ]
            );
            return;
        }
        // Return activation success metadata without exposing license secrets.
        notifal_json_success($result);
    }

    /**
     * Submit Setup Wizard experience feedback to the hosted gateway.
     *
     * Failures are logged and still returned as success so finishing is never blocked.
     *
     * @since 3.0.0
     * @updated 3.0.0 Persist source=plugin and optional package_id for gateway filters.
     * @return void
     */
    public static function handleFeedback(): void
    {
        // Verify AJAX request with nonce and administrator capability.
        notifal_verify_ajax_request('notifal_setup_wizard', 'manage_options');
        // Apply a lightweight rate limit before remote feedback calls.
        $guard = notifal_app(SetupWizardRequestGuard::class);
        $guard->enforceRateLimit('feedback');
        // Read and sanitize rating and optional free-text comment.
        $rating = Helper::sanitizeInput(isset($_POST['rating']) ? wp_unslash($_POST['rating']) : '', 'key');
        $comment = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';
        // Plugin Setup Wizard always records source=plugin on the gateway.
        $source = Helper::sanitizeInput(isset($_POST['source']) ? wp_unslash($_POST['source']) : 'plugin', 'key');
        $packageId = Helper::sanitizeInput(isset($_POST['package_id']) ? wp_unslash($_POST['package_id']) : '', 'text');
        $pluginVersion = defined('NOTIFAL_VERSION') ? (string) NOTIFAL_VERSION : '';
        $payload = $guard->sanitizeFeedbackPayload(
            [
                'rating'         => $rating,
                'comment'        => $comment,
                'plugin_version' => $pluginVersion,
                'source'         => $source,
                'package_id'     => $packageId,
            ]
        );
        if (null === $payload) {
            notifal_json_error(__('Invalid setup feedback.', 'notifal'));
            return;
        }
        /**
         * Fires after Setup Wizard experience feedback is accepted locally.
         *
         * @since 3.0.0
         * @param string               $rating  Rating slug.
         * @param array<string, mixed> $payload Sanitized feedback payload.
         */
        do_action(ActionHooks::SETUP_WIZARD_FEEDBACK_SUBMITTED, $payload['rating'], $payload);
        // Submit feedback to the hosted gateway; never block the finish path on failure.
        $result = notifal_app(SetupWizardGatewayService::class)->submitFeedback($payload);
        if (empty($result['success'])) {
            $code = sanitize_key((string) ($result['error']['code'] ?? 'feedback_failed'));
            $message = sanitize_text_field((string) ($result['error']['message'] ?? ''));
            Helper::logAdvanced(
                sprintf(
                    '[Notifal Wizard Feedback] remote submit failed code=%s message=%s',
                    $code,
                    $message
                ),
                'ERROR'
            );
            // Still acknowledge the client so success CTAs can continue.
            notifal_json_success(
                [
                    'recorded' => false,
                    'code'     => $code,
                ],
                __('Thanks. You can continue setup.', 'notifal')
            );
            return;
        }
        // Return remote storage confirmation to the browser.
        notifal_json_success(
            [
                'recorded' => true,
                'feedback' => $result['data'] ?? [],
            ],
            __('Thanks for your feedback.', 'notifal')
        );
    }

    /**
     * Report Setup Wizard progress to the hosted gateway.
     *
     * @since 3.0.0
     * @return void
     */
    public static function handleReportProgress(): void
    {
        // Verify AJAX request with nonce and administrator capability.
        notifal_verify_ajax_request('notifal_setup_wizard', 'manage_options');
        // Apply a lightweight rate limit before remote telemetry calls.
        $guard = notifal_app(SetupWizardRequestGuard::class);
        $guard->enforceRateLimit('report_progress');
        // Decode and sanitize JSON progress payload from the browser.
        $payload = $guard->decodeProgressPayload($_POST['payload'] ?? '');
        if (null === $payload) {
            notifal_json_error(__('Invalid wizard progress payload.', 'notifal'));
            return;
        }
        // Submit progress to the hosted gateway over HTTPS.
        $result = notifal_app(SetupWizardGatewayService::class)->reportProgress($payload);
        // Return structured failure payloads to the browser.
        if (empty($result['success'])) {
            $message = sanitize_text_field((string) ($result['error']['message'] ?? __('Could not report wizard progress.', 'notifal')));
            notifal_json_error($message);
            return;
        }
        // Return remote progress snapshot to the browser.
        notifal_json_success(
            [
                'progress' => $result['data'] ?? [],
            ]
        );
    }
    /**
     * Return true for supported Setup Wizard step slugs.
     *
     * @since 3.0.0
     * @param string $step Step slug.
     * @return bool
     */
    private static function isAllowedStep(string $step): bool
    {
        return in_array(
            $step,
            [
                SetupWizardSteps::SCAN,
                SetupWizardSteps::INTRO,
                SetupWizardSteps::INDUSTRY,
                SetupWizardSteps::SUGGESTIONS,
                SetupWizardSteps::BUILD,
                SetupWizardSteps::GOAL,
                SetupWizardSteps::RECOMMENDATIONS,
                SetupWizardSteps::CONNECT,
                SetupWizardSteps::PRO_ACCEPT,
                SetupWizardSteps::TRIAL,
                SetupWizardSteps::SUCCESS,
            ],
            true
        );
    }
}
