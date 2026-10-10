<?php

namespace Notifal\Modules\Ai\Application\Services;

defined('ABSPATH') || exit;

/**
 * Resolves a safe first-name greeting for Notifal AI invite surfaces.
 *
 * Uses only names stored by Notifal AI connect (contact_name / account first name).
 * When the site is not connected yet, returns empty so invite copy stays Noti-only.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class AiInviteGreeting
{
    /**
     * Generic or technical labels that should not appear in a friendly greeting.
     *
     * @var string[]
     */
    private const BLOCKED_NAMES = [
        'admin',
        'administrator',
        'root',
        'user',
        'test',
        'wordpress',
        'null',
        'undefined',
        'guest',
        'owner',
        'webmaster',
    ];

    /**
     * Site profile reader for hosted AI contact_name.
     *
     * @var SiteProfileService
     */
    private $siteProfile;

    /**
     * Installation store for connected account_first_name.
     *
     * @var InstallationCredentialStore
     */
    private $credentials;

    /**
     * @since 3.0.0
     * @param SiteProfileService          $siteProfile Hosted AI site profile service.
     * @param InstallationCredentialStore $credentials Installation credential store.
     */
    public function __construct(SiteProfileService $siteProfile, InstallationCredentialStore $credentials)
    {
        // Keep profile and connection stores for candidate resolution.
        $this->siteProfile = $siteProfile;
        $this->credentials = $credentials;
    }

    /**
     * Resolve a greeting-safe first name stored by Notifal AI connect.
     *
     * Returns an empty string when the site is not connected yet, or when no
     * suitable personal name was saved during connect.
     *
     * @since 3.0.0
     * @return string Sanitized first name, or empty string.
     */
    public function resolveFirstName(): string
    {
        // Without an AI connection there is no stored invite name yet.
        if (!$this->credentials->isConnected()) {
            return '';
        }

        // Collect only AI-stored name candidates (never WordPress profile names).
        $candidates = [];

        // 1) Hosted AI contact name saved during connect / profile flows.
        $profile = $this->siteProfile->get();
        $candidates[] = (string) ($profile['contact_name'] ?? '');

        // 2) Connected Notifal account first name from installation state.
        $publicState = $this->credentials->getPublicState();
        $candidates[] = (string) ($publicState['account_first_name'] ?? '');

        // Return the first candidate that passes greeting-safe normalization.
        foreach ($candidates as $candidate) {
            $firstName = $this->normalizeGreetingName($candidate);
            if ($firstName !== '') {
                return $firstName;
            }
        }

        // Connected but no usable personal name; use the non-personalized copy.
        return '';
    }

    /**
     * Normalize a raw label into a single greeting-safe first name.
     *
     * @since 3.0.0
     * @param string $raw Raw name candidate.
     * @return string Normalized first name, or empty string when unsuitable.
     */
    private function normalizeGreetingName(string $raw): string
    {
        // Strip tags and WordPress-sanitize the raw value.
        $raw = sanitize_text_field(wp_strip_all_tags(trim($raw)));
        if ($raw === '') {
            return '';
        }

        // Reject emails and email-like labels immediately.
        if (false !== strpos($raw, '@') || is_email($raw)) {
            return '';
        }

        // Use only the first whitespace-separated token as the first name.
        $parts = preg_split('/\s+/u', $raw);
        $first = is_array($parts) && isset($parts[0]) ? (string) $parts[0] : '';
        $first = trim($first, " \t\n\r\0\x0B.,;:!?\"'()[]{}");

        // Bail on empty or extreme lengths after tokenizing.
        $length = function_exists('mb_strlen') ? mb_strlen($first) : strlen($first);
        if ($first === '' || $length < 2 || $length > 32) {
            return '';
        }

        // Reject purely numeric tokens.
        if (preg_match('/^\d+$/', $first)) {
            return '';
        }

        // Reject username-style tokens such as user123 or admin01.
        if (preg_match('/^[a-z]+\\d+$/i', $first)) {
            return '';
        }

        // Reject known generic / technical labels.
        if (in_array(strtolower($first), self::BLOCKED_NAMES, true)) {
            return '';
        }

        // Allow letters (including unicode), apostrophes, and hyphens only.
        if (!preg_match("/^[\\p{L}][\\p{L}'\\-]*$/u", $first)) {
            return '';
        }

        return $first;
    }
}
