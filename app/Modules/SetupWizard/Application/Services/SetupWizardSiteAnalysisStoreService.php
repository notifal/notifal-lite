<?php
namespace Notifal\Modules\SetupWizard\Application\Services;
use Notifal\Modules\Ai\Application\Services\AiIndustryOptions;
use Notifal\Modules\SetupWizard\Domain\Constants\SetupWizardAnalysisLimits;
use Notifal\Modules\SetupWizard\Domain\Constants\SetupWizardOptions;
defined('ABSPATH') || exit;
/**
 * Persists Setup Wizard site scrape and analysis snapshots for reuse.
 *
 * @since 3.0.0
 * @updated 3.0.0 Persist optional suggestion interaction (multi-step hints).
 * @updated 3.0.0 Allow layout inline on stored wizard suggestions (Pro may activate later).
 * @updated 3.0.0 Accept optional product_recommendation_quiz suggestion use_case.
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardSiteAnalysisStoreService
{
    /**
     * Maximum UTF-8 bytes kept for one stored narrative or paragraph field.
     *
     * @var int
     */
    private const MAX_TEXT_FIELD_BYTES = 1200;
    /**
     * Return the stored site analysis snapshot when present.
     *
     * @since 3.0.0
     * @return array<string, mixed>|null Stored snapshot or null.
     */
    public function get(): ?array
    {
        // Read the non-autoloaded analysis option.
        $stored = get_option(SetupWizardOptions::SITE_ANALYSIS, null);
        // Reject malformed stored payloads.
        if (!is_array($stored) || empty($stored['analysis']) || !is_array($stored['analysis'])) {
            return null;
        }
        // Return the normalized stored snapshot.
        return $stored;
    }
    /**
     * Return a browser-safe snapshot for boot config and resume flows.
     *
     * @since 3.0.0
     * @return array<string, mixed>|null Public snapshot or null when unavailable.
     */
    public function getPublicSnapshot(): ?array
    {
        // Read the stored snapshot from the options table.
        $stored = $this->get();
        if (null === $stored) {
            return null;
        }
        // Expose only UI-safe scrape and analysis fields to JavaScript.
        return [
            'cached'    => true,
            'saved_at'  => sanitize_text_field((string) ($stored['saved_at'] ?? '')),
            'scrape'    => is_array($stored['scrape'] ?? null) ? $stored['scrape'] : [],
            'analysis'  => is_array($stored['analysis'] ?? null) ? $stored['analysis'] : [],
        ];
    }
    /**
     * Return true when stored analysis still matches the current scrape fingerprint.
     *
     * @since 3.0.0
     * @param array<string, mixed> $scrape Current scrape payload.
     * @return bool
     */
    public function matchesScrape(array $scrape): bool
    {
        // Require a stored snapshot before comparing fingerprints.
        $stored = $this->get();
        if (null === $stored) {
            return false;
        }
        // Compare the stored hash with the current scrape fingerprint.
        $storedHash = sanitize_key((string) ($stored['scrape_hash'] ?? ''));
        $currentHash = $this->computeScrapeHash($scrape);
        return '' !== $storedHash && hash_equals($storedHash, $currentHash);
    }
    /**
     * Persist one scrape and analysis snapshot for later wizard steps.
     *
     * @since 3.0.0
     * @param array<string, mixed> $scrape Full scrape payload.
     * @param array<string, mixed> $analysis Sanitized analysis payload.
     * @return array<string, mixed> Saved snapshot.
     */
    public function save(array $scrape, array $analysis): array
    {
        // Build the persisted snapshot envelope.
        $snapshot = [
            'scrape_hash' => $this->computeScrapeHash($scrape),
            'saved_at'    => current_time('mysql'),
            'scrape'      => $this->buildSlimScrape($scrape),
            'analysis'    => $this->sanitizeAnalysis($analysis),
        ];
        // Persist without autoload to keep the options table lean.
        update_option(SetupWizardOptions::SITE_ANALYSIS, $snapshot, false);
        // Return the saved snapshot for AJAX consumers.
        return $snapshot;
    }
    /**
     * Remove the stored site analysis snapshot.
     *
     * @since 3.0.0
     * @return void
     */
    public function clear(): void
    {
        // Delete the analysis option entirely.
        delete_option(SetupWizardOptions::SITE_ANALYSIS);
    }
    /**
     * Decode and sanitize one analysis payload submitted by the browser.
     *
     * @since 3.0.0
     * @param mixed $raw Raw POST value.
     * @return array<string, mixed>|null Sanitized analysis or null when invalid.
     */
    public function decodeAnalysisPayload($raw): ?array
    {
        // Reject empty payloads early.
        if (!is_string($raw) || '' === $raw) {
            return null;
        }
        // Reject oversized JSON before decoding.
        if (65536 < strlen($raw)) {
            return null;
        }
        // Decode JSON submitted by the browser boundary.
        $decoded = json_decode(wp_unslash($raw), true);
        if (!is_array($decoded)) {
            return null;
        }
        // Accept either { analysis: {...} } or a direct analysis object.
        $analysis = is_array($decoded['analysis'] ?? null) ? $decoded['analysis'] : $decoded;
        // Return sanitized analysis fields.
        return $this->sanitizeAnalysis($analysis);
    }
    /**
     * Build a stable fingerprint for one scrape payload.
     *
     * @since 3.0.0
     * @param array<string, mixed> $scrape Scrape payload.
     * @return string Scrape fingerprint hash.
     */
    public function computeScrapeHash(array $scrape): string
    {
        // Hash identity fields plus bounded home page content.
        $siteContent = sanitize_textarea_field((string) ($scrape['site_content'] ?? ''));
        $seed = implode(
            '|',
            [
                sanitize_text_field((string) ($scrape['site_title'] ?? '')),
                sanitize_textarea_field((string) ($scrape['tagline'] ?? '')),
                sanitize_text_field((string) ($scrape['site_domain'] ?? '')),
                (string) absint($scrape['site_content_word_count'] ?? 0),
                substr($siteContent, 0, 8192),
            ]
        );
        // Return a short stable hash for option storage.
        return hash('sha256', $seed);
    }
    /**
     * Build a browser-safe scrape snapshot without heavy home page content.
     *
     * @since 3.0.0
     * @param array<string, mixed> $scrape Full scrape payload.
     * @return array<string, mixed> Slim scrape payload.
     */
    private function buildSlimScrape(array $scrape): array
    {
        // Keep only fields needed by intro and later wizard steps in the browser.
        return [
            'logo_url'                => esc_url_raw((string) ($scrape['logo_url'] ?? '')),
            'site_title'              => sanitize_text_field((string) ($scrape['site_title'] ?? '')),
            'tagline'                 => sanitize_textarea_field((string) ($scrape['tagline'] ?? '')),
            'page_title'              => sanitize_text_field((string) ($scrape['page_title'] ?? '')),
            'meta_description'        => sanitize_textarea_field((string) ($scrape['meta_description'] ?? '')),
            'site_domain'             => sanitize_text_field((string) ($scrape['site_domain'] ?? '')),
            'site_locale'             => sanitize_text_field((string) ($scrape['site_locale'] ?? '')),
            'site_content_word_count' => absint($scrape['site_content_word_count'] ?? 0),
            'is_woocommerce'          => !empty($scrape['is_woocommerce']),
            'active_plugins'          => is_array($scrape['active_plugins'] ?? null) ? array_values($scrape['active_plugins']) : [],
            'detected_primary_color'  => sanitize_text_field((string) ($scrape['detected_primary_color'] ?? '')),
            'detected_brand_colors'   => $this->sanitizeDetectedBrandColors($scrape['detected_brand_colors'] ?? []),
        ];
    }
    /**
     * Sanitize one analysis payload before persistence or boot exposure.
     *
     * @since 3.0.0
     * @param array<string, mixed> $analysis Raw analysis payload.
     * @return array<string, mixed> Sanitized analysis payload.
     */
    private function sanitizeAnalysis(array $analysis): array
    {
        // Normalize primary brand color before suggestion sanitization.
        $primaryColor = sanitize_text_field((string) ($analysis['primary_color'] ?? ''));
        if (
            '' !== $primaryColor
            && !preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $primaryColor)
        ) {
            $primaryColor = '';
        }

        // Gateway already coerces AI industry_slug; keep a thin closed-set fallback here.
        $industrySlug = AiIndustryOptions::normalizeSlug((string) ($analysis['industry_slug'] ?? ''));
        $sanitized = [
            'source'                => sanitize_key((string) ($analysis['source'] ?? 'hosted')),
            'industry'              => sanitize_text_field((string) ($analysis['industry'] ?? '')),
            'industry_slug'         => $industrySlug,
            'is_ecommerce'          => !empty($analysis['is_ecommerce']),
            'recommended_use_cases' => $this->sanitizeSlugList($analysis['recommended_use_cases'] ?? [], 5),
            'narrative'             => $this->boundText((string) ($analysis['narrative'] ?? '')),
            'introduction_paragraphs' => $this->sanitizeTextList($analysis['introduction_paragraphs'] ?? [], 6),
            'suggestions'           => $this->sanitizeSuggestions($analysis['suggestions'] ?? [], $primaryColor),
            'closing_line'            => $this->boundText((string) ($analysis['closing_line'] ?? '')),
            'business_description'    => $this->boundText((string) ($analysis['business_description'] ?? '')),
            'primary_color'           => $primaryColor,
        ];
        // Return sanitized analysis payload.
        return $sanitized;
    }
    /**
     * Sanitize one list of short text values.
     *
     * @since 3.0.0
     * @param mixed $values Raw list values.
     * @param int   $limit Maximum number of entries.
     * @return string[] Sanitized text list.
     */
    private function sanitizeTextList($values, int $limit): array
    {
        // Reject non-array list payloads.
        if (!is_array($values)) {
            return [];
        }
        // Normalize each list entry.
        $items = [];
        foreach (array_values($values) as $value) {
            $text = $this->boundText((string) $value);
            if ('' === $text) {
                continue;
            }
            $items[] = $text;
            if (count($items) >= $limit) {
                break;
            }
        }
        // Return sanitized list entries.
        return $items;
    }
    /**
     * Sanitize wizard notification suggestion objects for intro and generation steps.
     *
     * @since 3.0.0
     * @param mixed  $values Raw suggestion list.
     * @param string $fallbackPrimary Primary color used when a suggestion omits appearance.
     * @return array<int, array<string, mixed>> Sanitized suggestion objects.
     */
    private function sanitizeSuggestions($values, string $fallbackPrimary = ''): array
    {
        // Reject non-array suggestion payloads.
        if (!is_array($values)) {
            return [];
        }

        // Normalize fallback brand color for legacy string suggestions.
        $primary = preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $fallbackPrimary)
            ? strtolower($fallbackPrimary)
            : '#7e2bd2';

        $items = [];
        foreach (array_values($values) as $value) {
            // Upgrade legacy plain-text suggestions into structured generation payloads.
            if (is_string($value)) {
                $goal = $this->boundText($value);
                if ('' === $goal) {
                    continue;
                }
                $items[] = [
                    'title'       => $this->boundTitle($goal),
                    'goal'        => $goal,
                    'use_case'    => 'social_proof',
                    'layout'      => 'popup',
                    'interaction' => 'none',
                    'appearance'  => [
                        'primary' => $primary,
                        'colors'  => [$primary],
                    ],
                ];
            } elseif (is_array($value)) {
                $sanitized = $this->sanitizeSuggestionObject($value, $primary);
                if (null !== $sanitized) {
                    $items[] = $sanitized;
                }
            }

            if (count($items) >= SetupWizardAnalysisLimits::MAX_SUGGESTIONS) {
                break;
            }
        }

        return $items;
    }


    /**
     * Sanitize one structured notification suggestion object.
     *
     * @since 3.0.0
     * @updated 3.0.0 Preserve layout inline for guided-setup suggestion cards.
     * @author Hossein <hossein@notifal.com>
     * @param array<string, mixed> $suggestion Raw suggestion object.
     * @param string               $fallbackPrimary Fallback primary color.
     * @return array<string, mixed>|null Sanitized suggestion or null when invalid.
     */
    private function sanitizeSuggestionObject(array $suggestion, string $fallbackPrimary): ?array
    {
        // Read bounded title and generation goal copy.
        $title = $this->boundTitle((string) ($suggestion['title'] ?? ''));
        $goal = $this->boundText((string) ($suggestion['goal'] ?? ''));
        if ('' === $goal) {
            return null;
        }

        if ('' === $title) {
            $title = $this->boundTitle($goal);
        }

        // Prefer explanation; accept description as an alias from older payloads.
        $explanation = $this->boundText(
            (string) ($suggestion['explanation'] ?? $suggestion['description'] ?? '')
        );
        if ('' === $explanation) {
            // Fall back to a short goal summary when card help text is missing.
            $explanation = $this->boundText(substr($goal, 0, 220));
        } elseif (strlen($explanation) > 220) {
            $explanation = substr($explanation, 0, 220);
        }

        // Validate use-case slug against the hosted wizard contract (incl. optional product quiz).
        $useCase = sanitize_key((string) ($suggestion['use_case'] ?? 'social_proof'));
        $allowedUseCases = [
            'social_proof',
            'urgency',
            'lead_capture',
            'announcement',
            'discount',
            'cart_recovery',
            'newsletter',
            'trust_badges',
            'seasonal_promo',
            'welcome_offer',
            'product_recommendation_quiz',
        ];
        // Accept hyphenated builder goal slug from older or mixed payloads.
        if ('product-recommendation-quiz' === $useCase) {
            $useCase = 'product_recommendation_quiz';
        }
        if (!in_array($useCase, $allowedUseCases, true)) {
            $useCase = 'social_proof';
        }

        // Validate optional layout slug (inline is allowed; Pro may activate later in guided setup).
        $layout = sanitize_key((string) ($suggestion['layout'] ?? 'popup'));
        if (!in_array($layout, ['popup', 'toast', 'topbar', 'inline'], true)) {
            $layout = 'popup';
        }

        // Normalize appearance colors for downstream generation intents.
        $appearance = is_array($suggestion['appearance'] ?? null) ? $suggestion['appearance'] : [];
        $primary = $this->sanitizeHexColor((string) ($appearance['primary'] ?? $fallbackPrimary));
        if ('' === $primary) {
            $primary = $fallbackPrimary;
        }

        $colors = [];
        if (is_array($appearance['colors'] ?? null)) {
            foreach ($appearance['colors'] as $color) {
                $hex = $this->sanitizeHexColor((string) $color);
                if ('' !== $hex && !in_array($hex, $colors, true)) {
                    $colors[] = $hex;
                }
                if (count($colors) >= 5) {
                    break;
                }
            }
        }
        if ([] === $colors) {
            $colors = [$primary];
        }

        // Normalize optional multi-step interaction hint for generation intents.
        $interaction = sanitize_key((string) ($suggestion['interaction'] ?? 'none'));
        $allowedInteractions = ['none', 'ui_multi_step', 'form_multi_step'];
        if (!in_array($interaction, $allowedInteractions, true)) {
            $interaction = 'none';
        }

        return [
            'title'       => $title,
            'explanation' => $explanation,
            'goal'        => $goal,
            'use_case'    => $useCase,
            'layout'      => $layout,
            'interaction' => $interaction,
            'appearance'  => [
                'primary' => $primary,
                'colors'  => $colors,
            ],
        ];
    }


    /**
     * Bound one short suggestion title for card UI labels.
     *
     * @since 3.0.0
     * @param string $value Raw title text.
     * @return string Bounded title.
     */
    private function boundTitle(string $value): string
    {
        // Sanitize and cap card titles for marquee readability.
        $value = sanitize_text_field($value);
        if (strlen($value) > 80) {
            $value = substr($value, 0, 80);
        }

        return trim($value);
    }


    /**
     * Sanitize one hex color string.
     *
     * @since 3.0.0
     * @param string $color Raw color value.
     * @return string Normalized hex or empty string.
     */
    private function sanitizeHexColor(string $color): string
    {
        // Trim whitespace before validation.
        $color = trim($color);

        // Accept short and long hex formats only.
        if (!preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color)) {
            return '';
        }

        return strtolower($color);
    }
    /**
     * Sanitize one list of slug values.
     *
     * @since 3.0.0
     * @param mixed $values Raw list values.
     * @param int   $limit Maximum number of entries.
     * @return string[] Sanitized slug list.
     */
    private function sanitizeSlugList($values, int $limit): array
    {
        // Reject non-array list payloads.
        if (!is_array($values)) {
            return [];
        }
        // Normalize each slug entry.
        $items = [];
        foreach (array_values($values) as $value) {
            $slug = sanitize_key((string) $value);
            if ('' === $slug) {
                continue;
            }
            $items[] = $slug;
            if (count($items) >= $limit) {
                break;
            }
        }
        // Return sanitized slug entries.
        return array_values(array_unique($items));
    }
    /**
     * Sanitize detected brand palette colors from scrape metadata.
     *
     * @since 3.0.0
     * @param mixed $colors Raw detected color list.
     * @return string[] Sanitized unique hex colors.
     */
    private function sanitizeDetectedBrandColors($colors): array
    {
        // Accept only array payloads from scrape metadata.
        if (!is_array($colors)) {
            return [];
        }

        $sanitized = [];

        foreach ($colors as $color) {
            // Normalize each candidate hex color before uniqueness checks.
            $hex = strtolower(sanitize_text_field((string) $color));
            if (
                '' === $hex
                || !preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $hex)
                || in_array($hex, $sanitized, true)
            ) {
                continue;
            }

            // Append the next unique palette color.
            $sanitized[] = $hex;
        }

        // Cap palette size to match extractor output limits.
        return array_slice($sanitized, 0, 3);
    }

    /**
     * Bound and sanitize one text field for storage.
     *
     * @since 3.0.0
     * @param string $value Raw text value.
     * @return string Bounded sanitized text.
     */
    private function boundText(string $value): string
    {
        // Sanitize textarea content before applying byte limits.
        $value = sanitize_textarea_field($value);
        if (strlen($value) > self::MAX_TEXT_FIELD_BYTES) {
            $value = substr($value, 0, self::MAX_TEXT_FIELD_BYTES);
            $value = rtrim((string) preg_replace('/[\xC0-\xFF][\x80-\xBF]*$/u', '', $value));
        }
        // Return bounded sanitized text.
        return $value;
    }
}
