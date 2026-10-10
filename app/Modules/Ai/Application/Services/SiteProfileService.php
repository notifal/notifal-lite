<?php

namespace Notifal\Modules\Ai\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\Ai\Config\Constants;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Stores the hosted AI site profile used across generation flows.
 *
 * Industry is captured once. Brand colors can be refreshed after each generation.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SiteProfileService
{
    /**
     * Maximum number of brand colors stored for later generations.
     *
     * @since 3.0.0
     * @var int
     */
    private const MAX_BRAND_COLORS = 5;

    /**
     * Return the saved site profile or an empty array.
     *
     * @since 3.0.0
     * @return array{industry: string, industry_slug: string, primary_color: string, brand_colors: string[]}
     */
    public function get(): array
    {
        // Read the non-autoloaded site profile option.
        $profile = get_option(Constants::OPTION_SITE_PROFILE, []);

        // Normalize malformed stored values.
        if (!is_array($profile)) {
            return [
                'industry'             => '',
                'industry_slug'        => '',
                'business_description' => '',
                'primary_color'        => '',
                'brand_colors'         => [],
                'contact_name'         => '',
            ];
        }

        // Normalize brand colors and keep primary as the first palette entry when present.
        $brandColors = $this->sanitizeBrandColors($profile['brand_colors'] ?? []);
        $primaryColor = $this->sanitizeHexColor((string) ($profile['primary_color'] ?? ''));

        if ($primaryColor !== '' && !in_array($primaryColor, $brandColors, true)) {
            array_unshift($brandColors, $primaryColor);
            $brandColors = array_slice($brandColors, 0, self::MAX_BRAND_COLORS);
        }

        if ($primaryColor === '' && !empty($brandColors[0])) {
            $primaryColor = $brandColors[0];
        }

        // Return sanitized profile fields only.
        return [
            'industry'             => sanitize_text_field((string) ($profile['industry'] ?? '')),
            'industry_slug'        => sanitize_key((string) ($profile['industry_slug'] ?? '')),
            'business_description' => sanitize_textarea_field((string) ($profile['business_description'] ?? '')),
            'primary_color'        => $primaryColor,
            'brand_colors'         => $brandColors,
            'contact_name'         => sanitize_text_field((string) ($profile['contact_name'] ?? '')),
        ];
    }

    /**
     * Persist the connected account display name for Noti personalization.
     *
     * @since 3.0.0
     * @param string $contactName Preferred first name or full name label.
     * @return array<string, mixed> Saved profile snapshot.
     */
    public function saveContactName(string $contactName): array
    {
        // Sanitize and cap the stored greeting name.
        $contactName = sanitize_text_field(trim($contactName));

        // Reject empty submissions.
        if ('' === $contactName) {
            throw new \InvalidArgumentException('invalid_contact_name');
        }

        if (strlen($contactName) > 120) {
            $contactName = substr($contactName, 0, 120);
        }

        // Merge into the existing profile without dropping industry or colors.
        $profile = $this->get();
        $profile['contact_name'] = $contactName;

        // Persist the updated profile without autoload.
        update_option(Constants::OPTION_SITE_PROFILE, $profile, false);

        /**
         * Fires after the hosted AI contact name preference is saved.
         *
         * @since 3.0.0
         * @param array<string, mixed> $profile Saved profile snapshot.
         */
        do_action(ActionHooks::AI_SITE_PROFILE_SAVED, $profile);

        return $profile;
    }

    /**
     * Whether the one-time industry profile has already been saved.
     *
     * @since 3.0.0
     * @return bool True when industry is present.
     */
    public function hasIndustry(): bool
    {
        // Treat a non-empty industry label as a completed profile step.
        $profile = $this->get();

        return '' !== $profile['industry'];
    }

    /**
     * Persist the site profile industry for later generations.
     *
     * @since 3.0.0
     * @param string $industry Industry label.
     * @param string $industrySlug Optional industry slug.
     * @return array{industry: string, industry_slug: string, primary_color: string, brand_colors: string[]}
     */
    public function saveIndustry(string $industry, string $industrySlug = ''): array
    {
        // Sanitize the submitted industry using shared helper conventions.
        $industry = Helper::sanitizeInput($industry, 'text');

        // Reject empty industry submissions.
        if ($industry === '') {
            throw new \InvalidArgumentException('invalid_industry');
        }

        // Cap industry length to keep gateway payloads bounded.
        if (strlen($industry) > 180) {
            $industry = substr($industry, 0, 180);
        }

        // Sanitize optional slug for selectable industry cards.
        $industrySlug = sanitize_key($industrySlug);

        // Preserve previously saved color preferences while updating industry.
        $existing = $this->get();
        $profile  = [
            'industry'             => $industry,
            'industry_slug'        => $industrySlug,
            'business_description' => $existing['business_description'] ?? '',
            'primary_color'        => $existing['primary_color'],
            'brand_colors'         => $existing['brand_colors'],
            'contact_name'         => $existing['contact_name'] ?? '',
        ];

        // Persist the profile in a non-autoloaded option.
        update_option(Constants::OPTION_SITE_PROFILE, $profile, false);

        /**
         * Fires after the hosted AI industry profile is saved.
         *
         * @since 3.0.0
         * @param array<string, mixed> $profile Saved profile snapshot.
         */
        do_action(ActionHooks::AI_SITE_PROFILE_SAVED, $profile);

        // Return the saved profile snapshot.
        return $profile;
    }

    /**
     * Persist a short business description for hosted AI personalization.
     *
     * @since 3.0.0
     * @param string $businessDescription Business summary from the wizard.
     * @return array<string, mixed> Saved profile snapshot.
     */
    public function saveBusinessDescription(string $businessDescription): array
    {
        // Sanitize multiline business copy from the wizard step.
        $businessDescription = sanitize_textarea_field(trim($businessDescription));

        // Reject empty submissions.
        if ('' === $businessDescription) {
            throw new \InvalidArgumentException('invalid_business_description');
        }

        // Cap length so gateway intent payloads stay bounded.
        if (strlen($businessDescription) > 600) {
            $businessDescription = substr($businessDescription, 0, 600);
        }

        // Merge into the existing profile without dropping other fields.
        $profile = $this->get();
        $profile['business_description'] = $businessDescription;

        // Persist the updated profile without autoload.
        update_option(Constants::OPTION_SITE_PROFILE, $profile, false);

        /**
         * Fires after the hosted AI business description is saved.
         *
         * @since 3.0.0
         * @param array<string, mixed> $profile Saved profile snapshot.
         */
        do_action(ActionHooks::AI_SITE_PROFILE_SAVED, $profile);

        return $profile;
    }

    /**
     * Persist the latest brand color palette for the next wizard run.
     *
     * @since 3.0.0
     * @param string $primaryColor Primary hex color value.
     * @param array<int, string> $brandColors Optional full palette list.
     * @return array{industry: string, industry_slug: string, primary_color: string, brand_colors: string[]}
     */
    public function savePrimaryColor(string $primaryColor, array $brandColors = []): array
    {
        // Sanitize the submitted brand color.
        $primaryColor = $this->sanitizeHexColor($primaryColor);

        // Reject empty or invalid primary colors.
        if ($primaryColor === '') {
            throw new \InvalidArgumentException('invalid_primary_color');
        }

        // Prefer an explicit palette when provided, otherwise keep primary only.
        $palette = !empty($brandColors)
            ? $this->sanitizeBrandColors($brandColors)
            : [$primaryColor];

        // Ensure the primary color stays first in the saved palette.
        $palette = array_values(array_unique(array_merge([$primaryColor], $palette)));
        $palette = array_slice($palette, 0, self::MAX_BRAND_COLORS);

        // Merge into the existing profile so industry is preserved.
        $profile = $this->get();
        $profile['primary_color'] = $primaryColor;
        $profile['brand_colors'] = $palette;

        // Persist the updated profile without autoload.
        update_option(Constants::OPTION_SITE_PROFILE, $profile, false);

        /**
         * Fires after the hosted AI brand color preference is saved.
         *
         * @since 3.0.0
         * @param array<string, mixed> $profile Saved profile snapshot.
         */
        do_action(ActionHooks::AI_SITE_PROFILE_SAVED, $profile);

        // Return the saved profile snapshot.
        return $profile;
    }

    /**
     * Sanitize one hex color string.
     *
     * @since 3.0.0
     * @param string $color Raw color.
     * @return string Normalized hex or empty string.
     */
    private function sanitizeHexColor(string $color): string
    {
        // Trim whitespace before validation.
        $color = trim($color);

        // Accept short and long hex formats only.
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            return strtolower($color);
        }

        return '';
    }

    /**
     * Sanitize a list of brand colors.
     *
     * @since 3.0.0
     * @param mixed $colors Raw color list.
     * @return string[] Normalized unique hex colors.
     */
    private function sanitizeBrandColors($colors): array
    {
        // Accept JSON strings and arrays from storage or AJAX.
        if (is_string($colors)) {
            $decoded = json_decode($colors, true);
            $colors = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($colors)) {
            return [];
        }

        $sanitized = [];

        foreach ($colors as $color) {
            $hex = $this->sanitizeHexColor((string) $color);
            if ($hex === '' || in_array($hex, $sanitized, true)) {
                continue;
            }

            $sanitized[] = $hex;

            if (count($sanitized) >= self::MAX_BRAND_COLORS) {
                break;
            }
        }

        return $sanitized;
    }
}
