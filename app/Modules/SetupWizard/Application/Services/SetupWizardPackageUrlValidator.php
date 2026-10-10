<?php
namespace Notifal\Modules\SetupWizard\Application\Services;
use Notifal\Domain\Settings\Constants\Urls;
defined('ABSPATH') || exit;
/**
 * Validates signed Notifal Pro package URLs before Plugin_Upgrader downloads.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardPackageUrlValidator
{
    /**
     * Required REST path fragment for initial Pro install packages.
     *
     * @var string
     */
    private const UPDATE_DOWNLOAD_PATH = '/wp-json/notifal/v1/update-download/';
    /**
     * Return true when the URL is an HTTPS notifal.com update-download endpoint.
     *
     * @since 3.0.0
     * @param string $packageUrl Candidate package URL.
     * @return bool
     */
    public function isAllowed(string $packageUrl): bool
    {
        // Sanitize the incoming URL before validation.
        $packageUrl = esc_url_raw(trim($packageUrl));
        if ('' === $packageUrl) {
            return false;
        }
        // Require HTTPS and a notifal.com host prefix.
        if (0 !== strpos($packageUrl, 'https://')) {
            return false;
        }
        if (!Urls::isNotifalUrl($packageUrl)) {
            return false;
        }
        // Parse host and path for strict endpoint matching.
        $parts = wp_parse_url($packageUrl);
        if (!is_array($parts)) {
            return false;
        }
        // Allow only the notifal.com hostname (including www when configured on CDN).
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($host, ['notifal.com', 'www.notifal.com'], true)) {
            return false;
        }
        // Require the signed update-download REST route shape.
        $path = (string) ($parts['path'] ?? '');
        if (false === strpos($path, self::UPDATE_DOWNLOAD_PATH)) {
            return false;
        }
        // Require license proof query args supplied by the license manager.
        parse_str((string) ($parts['query'] ?? ''), $query);
        $licenseKey = isset($query['license_key']) ? sanitize_text_field((string) $query['license_key']) : '';
        $siteUrl = isset($query['site_url']) ? esc_url_raw((string) $query['site_url']) : '';
        return '' !== $licenseKey && '' !== $siteUrl;
    }
}
