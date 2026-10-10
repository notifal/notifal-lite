<?php
namespace Notifal\Modules\SetupWizard\Application\Services;

use InvalidArgumentException;

defined('ABSPATH') || exit;

/**
 * Looks up hosted domain analysis packages before Setup Wizard token spend.
 *
 * Only package_complete with importable notifications enters the reuse UI.
 * analysis_ready (incomplete live packages) is treated as a soft miss so the
 * plugin always runs a fresh local analysis.
 *
 * @since 3.0.0
 * @updated 3.0.0 Miss on analysis_only; expose force-refresh and profile dual-sync fields.
 * @updated 3.0.0 Add human-readable expires_at_label for Setup Wizard copy.
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardDomainPackageService
{
    /**
     * Maximum notification documents returned to the browser for import.
     *
     * @var int
     */
    private const MAX_NOTIFICATIONS = 20;

    /**
     * Gateway bridge dependency.
     *
     * @var SetupWizardGatewayService
     */
    private SetupWizardGatewayService $gatewayService;

    /**
     * Domain host normalizer dependency.
     *
     * @var SetupWizardDomainHostNormalizer
     */
    private SetupWizardDomainHostNormalizer $domainNormalizer;

    /**
     * Create the domain package preflight service.
     *
     * @since 3.0.0
     * @param SetupWizardGatewayService      $gatewayService   Gateway bridge.
     * @param SetupWizardDomainHostNormalizer $domainNormalizer Domain host normalizer.
     */
    public function __construct(
        SetupWizardGatewayService $gatewayService,
        SetupWizardDomainHostNormalizer $domainNormalizer
    ) {
        // Store collaborators used by the preflight AJAX boundary.
        $this->gatewayService = $gatewayService;
        $this->domainNormalizer = $domainNormalizer;
    }

    /**
     * Preflight the current site domain against the hosted package store.
     *
     * @since 3.0.0
     * @updated 3.0.0 Treat analysis_ready without complete status as a miss.
     * @return array{
     *     hit: bool,
     *     importable: bool,
     *     reason: string,
     *     domain: string,
     *     package: array<string, mixed>|null
     * }
     */
    public function preflightCurrentSite(): array
    {
        // Normalize the local home URL into the shared package domain key.
        try {
            $domain = $this->domainNormalizer->normalizeHomeUrl();
        } catch (InvalidArgumentException $exception) {
            unset($exception);
            return $this->missEnvelope('', 'invalid_domain');
        }

        // Ask the gateway for a fresh package with full import bodies.
        $result = $this->gatewayService->lookupDomainPackage($domain, true);

        // Soft-miss on transport or missing/expired packages so the wizard continues.
        if (empty($result['success'])) {
            $code = sanitize_key((string) ($result['error']['code'] ?? 'package_not_found'));
            $reason = $this->mapFailureReason($code);
            return $this->missEnvelope($domain, $reason);
        }

        // Sanitize the public package contract for browser consumers.
        $package = $this->sanitizePackage(
            is_array($result['data'] ?? null) ? $result['data'] : []
        );
        if (null === $package) {
            return $this->missEnvelope($domain, 'invalid_package');
        }

        // Incomplete live packages must not enter the reuse / import UI.
        if (!$this->isImportable($package)) {
            return $this->missEnvelope($domain, 'analysis_only');
        }

        return [
            'hit'         => true,
            'importable'  => true,
            'reason'      => 'ready',
            'domain'      => $domain,
            'package'     => $package,
        ];
    }

    /**
     * Grant a one-time force refresh for the current site domain.
     *
     * @since 3.0.0
     * @return array{success: bool, data?: array<string, mixed>, error?: array<string, mixed>}
     */
    public function forceRefreshCurrentSite(): array
    {
        // Normalize the local home URL into the shared package domain key.
        try {
            $domain = $this->domainNormalizer->normalizeHomeUrl();
        } catch (InvalidArgumentException $exception) {
            unset($exception);
            return [
                'success' => false,
                'error'   => [
                    'code'    => 'invalid_domain',
                    'message' => __('Could not resolve this site domain.', 'notifal'),
                ],
            ];
        }

        // Delegate the S2S force-refresh grant to the gateway bridge.
        return $this->gatewayService->forceRefreshDomainPackage($domain);
    }

    /**
     * Dual-sync editable profile fields to the gateway package analysis blob.
     *
     * @since 3.0.0
     * @param string               $packageId Package UUID.
     * @param array<string, mixed> $fields    Editable profile fields.
     * @return array{success: bool, data?: array<string, mixed>, error?: array<string, mixed>}
     */
    public function syncPackageProfile(string $packageId, array $fields): array
    {
        // Require a canonical package UUID before the outbound call.
        $packageId = strtolower(sanitize_text_field($packageId));
        if (!wp_is_uuid($packageId, 4)) {
            return [
                'success' => false,
                'error'   => [
                    'code'    => 'invalid_request',
                    'message' => __('Invalid package.', 'notifal'),
                ],
            ];
        }

        // Delegate the S2S profile merge to the gateway bridge.
        return $this->gatewayService->updateDomainPackageProfile($packageId, $fields);
    }

    /**
     * Overwrite the gateway package after a needs-review rebuild.
     *
     * @since 3.0.0
     * @param array<string, mixed> $analysis      Local analysis snapshot.
     * @param array<int, mixed>    $notifications Importable OnPage documents.
     * @return array{success: bool, data?: array<string, mixed>, error?: array<string, mixed>}
     */
    public function replaceCurrentSitePackage(array $analysis, array $notifications): array
    {
        // Normalize the local home URL into the shared package domain key.
        try {
            $domain = $this->domainNormalizer->normalizeHomeUrl();
        } catch (InvalidArgumentException $exception) {
            unset($exception);
            return [
                'success' => false,
                'error'   => [
                    'code'    => 'invalid_domain',
                    'message' => __('Could not resolve this site domain.', 'notifal'),
                ],
            ];
        }

        // Delegate the no-AI replace to the gateway bridge.
        return $this->gatewayService->replaceDomainPackage($domain, $analysis, $notifications);
    }

    /**
     * Return true when the package can skip analysis and regenerate.
     *
     * @since 3.0.0
     * @updated 3.0.0 Require package_complete; ignore analysis_ready alone.
     * @param array<string, mixed> $package Public package envelope.
     * @return bool
     */
    public function isImportable(array $package): bool
    {
        // Reject expired status even if notifications are present.
        $status = sanitize_key((string) ($package['status'] ?? ''));
        if ('package_complete' !== $status) {
            return false;
        }

        // Require at least one importable OnPage notification document.
        $notifications = is_array($package['notifications'] ?? null)
            ? $package['notifications']
            : [];
        return !empty($notifications);
    }

    /**
     * Sanitize one public package envelope for AJAX / browser use.
     *
     * @since 3.0.0
     * @updated 3.0.0 Pass through force-refresh flags for needs-review UX.
     * @param array<string, mixed> $package Raw gateway package data.
     * @return array<string, mixed>|null Sanitized package or null when unusable.
     */
    public function sanitizePackage(array $package): ?array
    {
        // Require a recognizable contract version and domain.
        $version = sanitize_text_field((string) ($package['version'] ?? ''));
        $domain = sanitize_text_field((string) ($package['domain'] ?? ''));
        $status = sanitize_key((string) ($package['status'] ?? ''));
        if ('' === $domain || '' === $status) {
            return null;
        }

        // Bound and sanitize notification import documents.
        $notifications = [];
        $rawNotifications = is_array($package['notifications'] ?? null)
            ? $package['notifications']
            : [];
        foreach ($rawNotifications as $row) {
            if (!is_array($row) || count($notifications) >= self::MAX_NOTIFICATIONS) {
                continue;
            }
            $document = $this->sanitizeNotificationDocument($row);
            if (null !== $document) {
                $notifications[] = $document;
            }
        }

        // Pass through analysis as an associative array when present.
        $analysis = is_array($package['analysis'] ?? null) ? $package['analysis'] : null;
        // Keep the raw ISO stamp for logic and a WP-formatted label for chat copy.
        $expiresAt = sanitize_text_field((string) ($package['expires_at'] ?? ''));

        return [
            'version'                        => '' !== $version ? $version : '1',
            'id'                             => sanitize_text_field((string) ($package['id'] ?? '')),
            'domain'                         => $domain,
            'status'                         => $status,
            'expires_at'                     => $expiresAt,
            'expires_at_label'               => $this->formatExpiresAtLabel($expiresAt),
            'analysis'                       => $analysis,
            'notifications'                  => $notifications,
            'analysis_request_uuid'          => sanitize_text_field(
                (string) ($package['analysis_request_uuid'] ?? '')
            ),
            'cache_hit'                      => !empty($package['cache_hit']),
            'ai_invoked'                     => !empty($package['ai_invoked']),
            'client_force_refresh_available' => !empty($package['client_force_refresh_available']),
            'client_force_refreshed_at'      => sanitize_text_field(
                (string) ($package['client_force_refreshed_at'] ?? '')
            ),
        ];
    }

    /**
     * Format a package expiry stamp with WordPress date/time settings.
     *
     * @since 3.0.0
     * @param string $expiresAt ISO-8601 or MySQL UTC datetime.
     * @return string Human-readable local datetime, or empty string when invalid.
     */
    private function formatExpiresAtLabel(string $expiresAt): string
    {
        // Reject empty stamps before parsing.
        $expiresAt = trim($expiresAt);
        if ('' === $expiresAt) {
            return '';
        }

        // Parse ISO / MySQL stamps into a Unix timestamp.
        $timestamp = strtotime($expiresAt);
        if (false === $timestamp) {
            return '';
        }

        // Prefer the site date + time formats so admin copy matches WP settings.
        $format = trim((string) get_option('date_format') . ' ' . (string) get_option('time_format'));
        if ('' === $format) {
            $format = 'F j, Y g:i A';
        }

        // wp_date applies the site timezone and locale.
        return (string) wp_date($format, $timestamp);
    }

    /**
     * Sanitize one OnPage import document from the package notifications list.
     *
     * @since 3.0.0
     * @param array<string, mixed> $document Raw notification document.
     * @return array<string, mixed>|null Sanitized document or null when invalid.
     */
    private function sanitizeNotificationDocument(array $document): ?array
    {
        // Unwrap nested output envelopes used by some generation payloads.
        if (!isset($document['notification']) && isset($document['output']) && is_array($document['output'])) {
            $document = $document['output'];
        }

        // Require the same shape AiOnPageImportService already accepts.
        $type = sanitize_key((string) ($document['type'] ?? ''));
        $version = sanitize_text_field((string) ($document['version'] ?? ''));
        $notification = is_array($document['notification'] ?? null)
            ? $document['notification']
            : null;
        if (
            '' === $version
            || null === $notification
            || !in_array($type, ['notifal_onpage_notif', 'notifal_onpage_notification'], true)
        ) {
            return null;
        }

        // Keep the full document for the existing import_onpage AJAX boundary.
        return [
            'version'      => $version,
            'type'         => $type,
            'notification' => $notification,
        ];
    }

    /**
     * Map gateway failure codes to stable preflight miss reasons.
     *
     * @since 3.0.0
     * @param string $code Gateway error code.
     * @return string Miss reason slug.
     */
    private function mapFailureReason(string $code): string
    {
        // Preserve expired as a distinct UX reason for future messaging.
        if ('package_expired' === $code) {
            return 'expired';
        }
        // Transport failures should not block the existing wizard path.
        if (in_array($code, ['gateway_unreachable', 'invalid_gateway_response', 'gateway_error'], true)) {
            return 'unreachable';
        }
        return 'missing';
    }

    /**
     * Build a soft-miss preflight envelope.
     *
     * @since 3.0.0
     * @param string $domain Normalized domain or empty string.
     * @param string $reason Miss reason slug.
     * @return array{
     *     hit: false,
     *     importable: false,
     *     reason: string,
     *     domain: string,
     *     package: null
     * }
     */
    private function missEnvelope(string $domain, string $reason): array
    {
        return [
            'hit'        => false,
            'importable' => false,
            'reason'     => sanitize_key($reason),
            'domain'     => sanitize_text_field($domain),
            'package'    => null,
        ];
    }
}
