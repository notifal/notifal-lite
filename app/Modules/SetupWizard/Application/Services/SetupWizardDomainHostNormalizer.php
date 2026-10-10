<?php
namespace Notifal\Modules\SetupWizard\Application\Services;

use InvalidArgumentException;

defined('ABSPATH') || exit;

/**
 * Normalizes URLs and hosts into a stable domain package lookup key.
 *
 * Matching is scheme, www, path, query, and trailing-slash insensitive.
 * Aligns with the gateway DomainHostNormalizer contract for S2S lookups.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardDomainHostNormalizer
{
    /**
     * Maximum accepted raw input length before rejection.
     *
     * @var int
     */
    private const MAX_INPUT_LENGTH = 500;

    /**
     * Normalize a URL or bare host into a package domain_host key.
     *
     * @since 3.0.0
     * @param string $value URL or host from home_url or gateway responses.
     * @return string Lowercased host without leading www.
     *
     * @throws InvalidArgumentException When the value cannot be a public host.
     */
    public function normalize(string $value): string
    {
        // Trim surrounding whitespace before any parsing.
        $value = trim($value);

        // Reject empty or oversized inputs early.
        if ('' === $value || self::MAX_INPUT_LENGTH < strlen($value)) {
            throw new InvalidArgumentException('invalid_domain');
        }

        // Treat bare hosts as HTTPS origins so wp_parse_url can extract host.
        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . ltrim($value, '/');
        }

        // Parse the candidate URL into components.
        $parts = wp_parse_url($value);

        // Require a parseable host component.
        if (!is_array($parts) || empty($parts['host'])) {
            throw new InvalidArgumentException('invalid_domain');
        }

        // Lowercase the host for stable comparisons.
        $host = strtolower((string) $parts['host']);

        // Strip a single leading www. label for domain-only matching.
        if (0 === strpos($host, 'www.')) {
            $host = substr($host, 4);
        }

        // Reject empty hosts after www stripping.
        if ('' === $host) {
            throw new InvalidArgumentException('invalid_domain');
        }

        // Reject credentials embedded in the URL.
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('invalid_domain');
        }

        // Reject hosts that are not valid DNS or IP literals.
        if (!$this->isPlausibleHost($host)) {
            throw new InvalidArgumentException('invalid_domain');
        }

        // Bound the persisted host length to the gateway schema column.
        return substr($host, 0, 255);
    }

    /**
     * Normalize the current site home URL into a package domain key.
     *
     * @since 3.0.0
     * @return string Normalized domain host.
     *
     * @throws InvalidArgumentException When home_url cannot be normalized.
     */
    public function normalizeHomeUrl(): string
    {
        // Prefer the public homepage URL so package keys match public Analyze Site.
        return $this->normalize((string) home_url('/'));
    }

    /**
     * Determine whether a host string is a plausible DNS name or IP.
     *
     * @since 3.0.0
     * @param string $host Lowercased host candidate.
     * @return bool True when the host is acceptable as a package key.
     */
    private function isPlausibleHost(string $host): bool
    {
        // Accept literal IPv4 or IPv6 addresses.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        // Allow localhost for local client development sites.
        if ('localhost' === $host) {
            return true;
        }

        // Validate hostname labels against a conservative DNS pattern.
        return (bool) preg_match(
            '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/',
            $host
        );
    }
}
