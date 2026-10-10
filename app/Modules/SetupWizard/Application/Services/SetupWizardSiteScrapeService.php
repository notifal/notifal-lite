<?php
namespace Notifal\Modules\SetupWizard\Application\Services;

use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\SetupWizard\Domain\Constants\SetupWizardOptions;

defined('ABSPATH') || exit;
/**
 * Scrapes public home page content for Setup Wizard site analysis.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardSiteScrapeService
{
    /**
     * Minimum visible words targeted before capping outbound site content.
     *
     * @var int
     */
    private const MIN_SITE_CONTENT_WORDS = 1000;
    /**
     * Maximum UTF-8 bytes kept from scraped visible text.
     *
     * @var int
     */
    private const MAX_SITE_CONTENT_BYTES = 65536;
    /**
     * Maximum HTML response bytes read from the home page request.
     *
     * @var int
     */
    private const MAX_HTML_RESPONSE_BYTES = 500000;
    /**
     * HTTP timeout for the public home page request in seconds.
     *
     * @var int
     */
    private const HTTP_TIMEOUT = 8;
    /**
     * Cache lifetime for scrape results in seconds.
     *
     * @var int
     */
    private const CACHE_TTL = 900;
    /**
     * Scrape the local site home page and active plugin metadata.
     *
     * @since 3.0.0
     * @param bool $forceRefresh When true, bypass the cached scrape snapshot.
     * @return array<string, mixed> Scrape payload for wizard analysis.
     */
    public function scrape(bool $forceRefresh = false): array
    {
        // Reuse a recent scrape snapshot to keep the scan step fast.
        $cacheKey = $this->cacheKey();
        if (!$forceRefresh) {
            $cached = get_transient($cacheKey);
            if (is_array($cached) && !empty($cached['site_content'])) {
                return $cached;
            }
        }
        // Resolve the public home URL for this installation.
        $homeUrl = home_url('/');
        // Fetch the rendered home page HTML over HTTP with a short timeout.
        $response = wp_remote_get(
            $homeUrl,
            [
                'timeout'     => self::HTTP_TIMEOUT,
                'redirection' => 2,
                'sslverify'   => apply_filters('https_local_ssl_verify', false),
                'user-agent'  => 'Notifal-Setup-Wizard/' . (defined('NOTIFAL_VERSION') ? NOTIFAL_VERSION : '3.0.0'),
            ]
        );
        // Start with empty HTML when the request fails.
        $html = '';
        if (!is_wp_error($response)) {
            $html = (string) wp_remote_retrieve_body($response);
            if (strlen($html) > self::MAX_HTML_RESPONSE_BYTES) {
                $html = substr($html, 0, self::MAX_HTML_RESPONSE_BYTES);
            }
        }
        // Resolve WordPress site identity fields used by the intro step.
        $siteTitle = sanitize_text_field((string) get_bloginfo('name'));
        $tagline = sanitize_textarea_field((string) get_bloginfo('description'));
        // Extract bounded metadata and visible text from the HTML body.
        $pageTitle = $this->extractTitle($html, $siteTitle);
        $metaDescription = $this->extractMetaDescription($html);
        $resolvedTagline = '' !== $tagline ? $tagline : $metaDescription;
        $siteContent = $this->extractVisibleText($html, $siteTitle, $resolvedTagline, $pageTitle, $metaDescription);
        $logoUrl = $this->resolveLogoUrl();
        // Detect likely brand palette colors from theme metadata and inline styles.
        $brandColorExtractor = notifal_app(SetupWizardBrandColorExtractor::class);
        $detectedBrandColors = $brandColorExtractor->extractPaletteColors($html);
        $detectedPrimaryColor = $detectedBrandColors[0] ?? '';
        // Resolve the public site host for gateway prompts.
        $siteDomain = (string) parse_url($homeUrl, PHP_URL_HOST);
        // Collect active plugin slugs and WooCommerce availability.
        $activePlugins = PluginDetector::getActivePluginSlugs();
        $isWooCommerce = PluginDetector::isWooCommerceActive();
        // Resolve the WordPress timezone string for seasonal prompt context.
        $siteTimezone = function_exists('wp_timezone_string')
            ? sanitize_text_field((string) wp_timezone_string())
            : '';
        // Build scrape payload consumed by analysis and generation intent.
        $payload = [
            'site_content'             => $siteContent,
            'site_title'               => $siteTitle,
            'tagline'                  => $resolvedTagline,
            'page_title'               => $pageTitle,
            'meta_description'         => $metaDescription,
            'logo_url'                 => $logoUrl,
            'detected_primary_color'   => $detectedPrimaryColor,
            'detected_brand_colors'    => $detectedBrandColors,
            'site_domain'              => $siteDomain,
            'site_locale'              => sanitize_text_field((string) get_locale()),
            'site_timezone'            => $siteTimezone,
            'site_content_word_count'  => $this->countWords($siteContent),
            'active_plugins'           => $activePlugins,
            'is_woocommerce'           => $isWooCommerce,
            'html_builder_available'   => true,
            'elementor_available'      => PluginDetector::isElementorActive(),
            'is_pro_active'            => PluginDetector::isNotifalProActive(),
            'is_pro_installed'         => PluginDetector::isNotifalProInstalled(),
        ];
        // Cache successful scrapes that contain useful content.
        if (strlen($siteContent) >= 20) {
            set_transient($cacheKey, $payload, self::CACHE_TTL);
        }
        return $payload;
    }
    /**
     * Build the transient cache key for one site scrape snapshot.
     *
     * @since 3.0.0
     * @return string Transient key.
     */
    private function cacheKey(): string
    {
        // Scope cache entries per site host to avoid cross-site leakage on multisite.
        $host = sanitize_key((string) parse_url(home_url('/'), PHP_URL_HOST));
        return SetupWizardOptions::SCRAPE_CACHE_TRANSIENT . $host;
    }
    /**
     * Extract the document title from HTML markup.
     *
     * @since 3.0.0
     * @param string $html Raw HTML body.
     * @param string $fallbackTitle WordPress site title fallback.
     * @return string Sanitized page title.
     */
    private function extractTitle(string $html, string $fallbackTitle): string
    {
        // Match the first title tag when present.
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return sanitize_text_field(html_entity_decode(wp_strip_all_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
        }
        // Fall back to the WordPress site name when HTML lacks a title tag.
        return $fallbackTitle;
    }
    /**
     * Extract the meta description tag when available.
     *
     * @since 3.0.0
     * @param string $html Raw HTML body.
     * @return string Sanitized meta description.
     */
    private function extractMetaDescription(string $html): string
    {
        // Match a standard meta description tag.
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            return sanitize_textarea_field(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
        }
        return '';
    }
    /**
     * Build bounded visible text from HTML for AI analysis.
     *
     * @since 3.0.0
     * @param string $html Raw HTML body.
     * @param string $siteTitle WordPress site title.
     * @param string $tagline Site tagline or meta description.
     * @param string $pageTitle Home page document title.
     * @param string $metaDescription Meta description fallback text.
     * @return string Bounded plain-text site content.
     */
    private function extractVisibleText(
        string $html,
        string $siteTitle,
        string $tagline,
        string $pageTitle,
        string $metaDescription
    ): string {
        // Strip script and style blocks before converting to plain text.
        $cleanHtml = preg_replace('/<(script|style|noscript)[^>]*>.*?<\/\\1>/is', ' ', $html);
        $cleanHtml = is_string($cleanHtml) ? $cleanHtml : $html;
        // Convert remaining markup to plain text.
        $visibleText = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($cleanHtml)));
        // Prepend identity fields so short home pages still analyze well.
        $parts = array_filter([$siteTitle, $tagline, $pageTitle, $metaDescription, $visibleText]);
        $content = trim(implode("\n\n", $parts));
        // Cap UTF-8 byte length to match the gateway contract.
        if (strlen($content) > self::MAX_SITE_CONTENT_BYTES) {
            $content = $this->truncateToWordTarget($content, self::MIN_SITE_CONTENT_WORDS, self::MAX_SITE_CONTENT_BYTES);
        }
        return sanitize_textarea_field($content);
    }
    /**
     * Truncate content near a word target without exceeding a byte cap.
     *
     * @since 3.0.0
     * @param string $content Plain-text content.
     * @param int    $targetWords Preferred minimum word count before truncation.
     * @param int    $maxBytes Maximum UTF-8 byte length.
     * @return string Truncated plain-text content.
     */
    private function truncateToWordTarget(string $content, int $targetWords, int $maxBytes): string
    {
        // Return early when the payload is already within the byte cap.
        if (strlen($content) <= $maxBytes) {
            return $content;
        }
        // Split on whitespace so we can preserve at least the target word count.
        $words = preg_split('/\s+/u', $content, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($words) || [] === $words) {
            return substr($content, 0, $maxBytes);
        }
        // Prefer keeping the first targetWords words when that fits the byte cap.
        $preferredWords = array_slice($words, 0, max(1, $targetWords));
        $preferredText = implode(' ', $preferredWords);
        if (strlen($preferredText) <= $maxBytes) {
            return rtrim((string) preg_replace('/[\xC0-\xFF][\x80-\xBF]*$/u', '', $preferredText));
        }
        // Fall back to a hard byte cap when the word target exceeds the limit.
        $truncated = substr($content, 0, $maxBytes);
        return rtrim((string) preg_replace('/[\xC0-\xFF][\x80-\xBF]*$/u', '', $truncated));
    }
    /**
     * Count words in one plain-text payload.
     *
     * @since 3.0.0
     * @param string $text Plain-text content.
     * @return int Word count.
     */
    private function countWords(string $text): int
    {
        // Normalize whitespace before counting words.
        $normalized = trim(preg_replace('/\s+/u', ' ', $text));
        if ('' === $normalized) {
            return 0;
        }
        // Split on whitespace for a locale-aware word count approximation.
        $words = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($words) ? count($words) : 0;
    }
    /**
     * Resolve a public logo URL from theme custom logo or site icon.
     *
     * @since 3.0.0
     * @return string Escaped logo URL or empty string.
     */
    private function resolveLogoUrl(): string
    {
        // Prefer the theme custom logo attachment when configured.
        $customLogoId = absint(get_theme_mod('custom_logo'));
        if ($customLogoId > 0) {
            $logoUrl = wp_get_attachment_image_url($customLogoId, 'full');
            if (is_string($logoUrl) && '' !== $logoUrl) {
                return esc_url_raw($logoUrl);
            }
        }
        // Fall back to the site icon when no custom logo exists.
        $siteIconId = absint(get_option('site_icon'));
        if ($siteIconId > 0) {
            $iconUrl = wp_get_attachment_image_url($siteIconId, 'full');
            if (is_string($iconUrl) && '' !== $iconUrl) {
                return esc_url_raw($iconUrl);
            }
        }
        return '';
    }
}
