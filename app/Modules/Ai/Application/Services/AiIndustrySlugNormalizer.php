<?php

namespace Notifal\Modules\Ai\Application\Services;

defined('ABSPATH') || exit;

/**
 * Thin closed-set check for industry picker card slugs.
 *
 * The hosted AI gateway remains the source of truth for model output:
 * WizardPromptBuilder::normalize_industry_slug() coerces invented niches
 * (grocery → food, dental-clinic → healthcare, …) before the client stores
 * analysis. This class only keeps UI picker / local store values on the same
 * card allowlist that AiIndustryOptions exposes, with a safe other fallback.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class AiIndustrySlugNormalizer
{
    /**
     * Default industry card when a value is outside the UI allowlist.
     *
     * @var string
     */
    public const DEFAULT_SLUG = 'other';

    /**
     * Maximum bytes accepted for an industry slug after sanitization.
     *
     * @var int
     */
    public const MAX_SLUG_BYTES = 64;

    /**
     * Coerce one industry slug onto the closed UI card allowlist.
     *
     * Exact card matches pass through. Anything else maps to other when that
     * card exists. Soft niche aliases belong on the gateway only.
     *
     * @since 3.0.0
     * @param string   $industrySlug Raw industry slug.
     * @param string[] $allowed      Allowed card slugs from AiIndustryOptions.
     * @return string Allowlisted slug, or empty string when input is blank.
     */
    public static function normalize(string $industrySlug, array $allowed): string
    {
        // Sanitize once for stable allowlist lookups.
        $slug = sanitize_key($industrySlug);
        if ('' === $slug) {
            return '';
        }

        // Bound length before allowlist checks.
        if (self::MAX_SLUG_BYTES < strlen($slug)) {
            $slug = substr($slug, 0, self::MAX_SLUG_BYTES);
            $slug = rtrim($slug, '-_');
        }
        if ('' === $slug) {
            return '';
        }

        // Prefer exact card matches without remapping.
        if (in_array($slug, $allowed, true)) {
            return $slug;
        }

        // Prefer the Other card when present; otherwise return empty for callers to reject.
        if (in_array(self::DEFAULT_SLUG, $allowed, true)) {
            return self::DEFAULT_SLUG;
        }

        return '';
    }

    /**
     * Return true when the slug exactly matches an allowed industry card.
     *
     * Used for picker AJAX validation. Does not soft-alias niches; gateway
     * already normalizes AI-returned industry_slug values.
     *
     * @since 3.0.0
     * @param string   $industrySlug Industry slug candidate.
     * @param string[] $allowed      Allowed card slugs.
     * @return bool
     */
    public static function isAllowed(string $industrySlug, array $allowed): bool
    {
        // Sanitize once so membership checks match stored card slugs.
        $slug = sanitize_key($industrySlug);
        if ('' === $slug) {
            return false;
        }

        // Bound length the same way normalize() does before membership checks.
        if (self::MAX_SLUG_BYTES < strlen($slug)) {
            $slug = substr($slug, 0, self::MAX_SLUG_BYTES);
            $slug = rtrim($slug, '-_');
        }
        if ('' === $slug) {
            return false;
        }

        // Exact card membership only (no client-side niche alias maps).
        return in_array($slug, $allowed, true);
    }
}
