<?php
namespace Notifal\Modules\SetupWizard\Application\Services;
defined('ABSPATH') || exit;
/**
 * Builds scrape-only baseline metadata when hosted AI has not run yet.
 *
 * Industry labels and narratives are never guessed locally. Hosted AI reads
 * public site content in any language and returns those fields instead.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardHeuristicAnalysisService
{
    /**
     * Build a wizard analysis snapshot from scraped site data.
     *
     * @since 3.0.0
     * @param array<string, mixed> $scrape Scrape payload from SetupWizardSiteScrapeService.
     * @return array<string, mixed> Analysis fields for the wizard UI.
     */
    public function analyze(array $scrape): array
    {
        // Read WooCommerce activation from the scrape payload only.
        $isWooCommerce = !empty($scrape['is_woocommerce']);
        // Recommend starter use-case slugs from WooCommerce context when available.
        $recommendedUseCases = $this->buildRecommendedUseCases($isWooCommerce);
        // Use the public page title as a neutral business description fallback.
        $businessDescription = sanitize_text_field((string) ($scrape['page_title'] ?? get_bloginfo('name')));
        // Resolve site identity fields used in localized fallback intro copy.
        $siteTitle = sanitize_text_field((string) ($scrape['site_title'] ?? get_bloginfo('name')));
        $tagline = sanitize_textarea_field((string) ($scrape['tagline'] ?? ''));
        $metaDescription = sanitize_textarea_field((string) ($scrape['meta_description'] ?? ''));
        // Read detected brand palette from scrape metadata when available.
        $brandColors = $this->sanitizeDetectedBrandColors($scrape['detected_brand_colors'] ?? []);
        $primaryColor = sanitize_text_field((string) ($scrape['detected_primary_color'] ?? ''));
        if (
            '' !== $primaryColor
            && !preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $primaryColor)
        ) {
            $primaryColor = '';
        }
        if ('' === $primaryColor && [] !== $brandColors) {
            $primaryColor = $brandColors[0];
        }
        // Build localized fallback welcome copy when hosted AI is unavailable.
        $fallbackIntro = $this->buildFallbackIntro($siteTitle, $isWooCommerce, $tagline, $metaDescription, $primaryColor, $brandColors);
        // Return scrape-only metadata compatible with the hosted AI output shape.
        return [
            'source'                  => 'scrape_only',
            'industry'                => '',
            'industry_slug'           => $isWooCommerce ? 'ecommerce' : '',
            'is_ecommerce'            => $isWooCommerce,
            'recommended_use_cases'   => $recommendedUseCases,
            'narrative'               => '',
            'introduction_paragraphs' => $fallbackIntro['introduction_paragraphs'],
            'suggestions'             => $fallbackIntro['suggestions'],
            'closing_line'            => $fallbackIntro['closing_line'],
            'business_description'    => $businessDescription,
            'primary_color'           => $primaryColor,
        ];
    }
    /**
     * Build localized fallback intro copy for the welcome step.
     *
     * @since 3.0.0
     * @param string $siteTitle Public site title.
     * @param bool   $isEcommerce Whether WooCommerce is active.
     * @param string $tagline Site tagline or meta description fallback.
     * @param string $metaDescription Meta description from the home page.
     * @param string   $primaryColor Detected brand color hex.
     * @param string[] $brandColors Detected brand palette colors.
     * @return array{introduction_paragraphs: string[], suggestions: array<int, array<string, mixed>>, closing_line: string}
     */
    private function buildFallbackIntro(
        string $siteTitle,
        bool $isEcommerce,
        string $tagline = '',
        string $metaDescription = '',
        string $primaryColor = '',
        array $brandColors = []
    ): array {
        // Prefer the tagline, then meta description, as a short niche hint.
        $nicheHint = '' !== $tagline ? $tagline : $metaDescription;
        // Cap the hint so fallback paragraphs stay readable in the bubble.
        if (strlen($nicheHint) > 120) {
            $nicheHint = rtrim(substr($nicheHint, 0, 117)) . '...';
        }
        // Build site-aware paragraphs that prove Noti reviewed this website.
        $introductionParagraphs = $isEcommerce
            ? $this->buildEcommerceParagraphs($siteTitle, $nicheHint)
            : $this->buildGeneralParagraphs($siteTitle, $nicheHint);
        // Suggest six structured notification ideas aligned with hosted generation intents.
        $suggestions = $this->buildStructuredSuggestions($isEcommerce, $primaryColor, $brandColors);
        // Return first-person bridge copy shown before the suggestion marquee.
        return [
            'introduction_paragraphs' => $introductionParagraphs,
            'suggestions'             => $suggestions,
            'closing_line'            => __('Here are a few ideas to get you started:', 'notifal'),
        ];
    }
    /**
     * Build structured notification suggestion objects for offline fallback mode.
     *
     * @since 3.0.0
     * @param bool   $isEcommerce Whether WooCommerce is active.
     * @param string   $primaryColor Detected primary brand color.
     * @param string[] $brandColors Detected brand palette colors.
     * @return array<int, array<string, mixed>> Structured suggestions.
     */
    private function buildStructuredSuggestions(bool $isEcommerce, string $primaryColor, array $brandColors = []): array
    {
        // Normalize fallback appearance colors for each suggestion card.
        $palette = $this->sanitizeBrandPalette($brandColors, $primaryColor);
        $primary = $palette[0];
        $appearance = [
            'primary' => $primary,
            'colors'  => $palette,
        ];

        if ($isEcommerce) {
            return [
                $this->buildSuggestionItem(
                    __('Recent purchases', 'notifal'),
                    __('Highlight recent purchases on product pages so shoppers feel confident before they buy.', 'notifal'),
                    'social_proof',
                    'popup',
                    $appearance
                ),
                $this->buildSuggestionItem(
                    __('Cart recovery', 'notifal'),
                    __('Recover abandoned carts with a gentle exit-intent reminder tied to items still waiting.', 'notifal'),
                    'cart_recovery',
                    'popup',
                    $appearance
                ),
                $this->buildSuggestionItem(
                    __('Seasonal offer', 'notifal'),
                    __('Promote seasonal or limited stock offers without slowing down your storefront.', 'notifal'),
                    'seasonal_promo',
                    'toast',
                    $appearance
                ),
                $this->buildSuggestionItem(
                    __('Returning customers', 'notifal'),
                    __('Welcome returning customers with a short thank-you offer on category pages.', 'notifal'),
                    'welcome_offer',
                    'toast',
                    $appearance
                ),
                $this->buildSuggestionItem(
                    __('Checkout trust', 'notifal'),
                    __('Surface trust signals near checkout so hesitant buyers finish their order.', 'notifal'),
                    'trust_badges',
                    'topbar',
                    $appearance
                ),
                $this->buildSuggestionItem(
                    __('Shipping threshold', 'notifal'),
                    __('Announce free shipping thresholds or bundle deals while shoppers browse.', 'notifal'),
                    'discount',
                    'topbar',
                    $appearance
                ),
            ];
        }

        return [
            $this->buildSuggestionItem(
                __('Lead capture', 'notifal'),
                __('Capture more leads with a focused signup prompt on your most visited pages.', 'notifal'),
                'lead_capture',
                'popup',
                $appearance
            ),
            $this->buildSuggestionItem(
                __('Site updates', 'notifal'),
                __('Announce updates and offers with lightweight on-page messages that stay on brand.', 'notifal'),
                'announcement',
                'toast',
                $appearance
            ),
            $this->buildSuggestionItem(
                __('Welcome visitors', 'notifal'),
                __('Build trust with a welcome note that explains who you help and what to do next.', 'notifal'),
                'welcome_offer',
                'popup',
                $appearance
            ),
            $this->buildSuggestionItem(
                __('Newsletter invite', 'notifal'),
                __('Invite newsletter signups after someone reads a key article or service page.', 'notifal'),
                'newsletter',
                'toast',
                $appearance
            ),
            $this->buildSuggestionItem(
                __('Proof moment', 'notifal'),
                __('Share a short proof moment, such as a client win or review, near your call to action.', 'notifal'),
                'social_proof',
                'topbar',
                $appearance
            ),
            $this->buildSuggestionItem(
                __('Next step CTA', 'notifal'),
                __('Guide visitors toward booking, contact, or demo pages with a clear next step.', 'notifal'),
                'urgency',
                'popup',
                $appearance
            ),
        ];
    }
    /**
     * Build one structured notification suggestion object.
     *
     * @since 3.0.0
     * @param string               $title Card title label.
     * @param string               $goal Generation prompt text.
     * @param string               $useCase Hosted use-case slug.
     * @param string               $layout Layout slug.
     * @param array<string, mixed> $appearance Brand color appearance payload.
     * @return array<string, mixed> Structured suggestion object.
     */
    private function buildSuggestionItem(
        string $title,
        string $goal,
        string $useCase,
        string $layout,
        array $appearance
    ): array {
        // Reuse the operator-facing help text as both explanation and generation goal offline.
        $explanation = sanitize_textarea_field($goal);
        if (strlen($explanation) > 220) {
            $explanation = substr($explanation, 0, 220);
        }

        // Return one suggestion object compatible with hosted generation intents.
        return [
            'title'       => $title,
            'explanation' => $explanation,
            'goal'        => $goal,
            'use_case'    => $useCase,
            'layout'      => $layout,
            'appearance'  => $appearance,
        ];
    }
    /**
     * Build WooCommerce-aware intro paragraphs for offline fallback mode.
     *
     * @since 3.0.0
     * @param string $siteTitle Public site title.
     * @param string $nicheHint Tagline or meta description hint.
     * @return string[] Introduction paragraphs.
     */
    private function buildEcommerceParagraphs(string $siteTitle, string $nicheHint): array
    {
        // Open with store recognition so the operator feels understood.
        $first = '' !== $nicheHint
            ? sprintf(
                /* translators: 1: website title, 2: tagline or meta description */
                __('Hi, I am Noti, the Notifal AI assistant. I reviewed %1$s and I can see you run an online store. From what I found, your shop is oriented around %2$s.', 'notifal'),
                $siteTitle,
                $nicheHint
            )
            : sprintf(
                /* translators: %s: website title */
                __('Hi, I am Noti, the Notifal AI assistant. I reviewed %s and I can see you run an online store with products ready for shoppers.', 'notifal'),
                $siteTitle
            );
        // Explain how Notifal helps this store without generic product fluff alone.
        return [
            $first,
            __('For an ecommerce site like yours, I can help you launch purchase social proof, cart recovery prompts, and offer messages that stay fast on the storefront.', 'notifal'),
            __('Let me guide you through templates, AI generation, display rules, and the HTML builder so you get notifications that match how your store sells.', 'notifal'),
        ];
    }
    /**
     * Build general-site intro paragraphs for offline fallback mode.
     *
     * @since 3.0.0
     * @param string $siteTitle Public site title.
     * @param string $nicheHint Tagline or meta description hint.
     * @return string[] Introduction paragraphs.
     */
    private function buildGeneralParagraphs(string $siteTitle, string $nicheHint): array
    {
        // Open with niche recognition from tagline or meta description when present.
        $first = '' !== $nicheHint
            ? sprintf(
                /* translators: 1: website title, 2: tagline or meta description */
                __('Hi, I am Noti, the Notifal AI assistant. I reviewed %1$s and it looks like your site focuses on %2$s.', 'notifal'),
                $siteTitle,
                $nicheHint
            )
            : sprintf(
                /* translators: %s: website title */
                __('Hi, I am Noti, the Notifal AI assistant. I reviewed %s and I am ready to help you turn more visitors into leads and customers.', 'notifal'),
                $siteTitle
            );
        // Keep later paragraphs site-aware and first-person without Noti-only filler.
        return [
            $first,
            __('Based on what I can see on your public pages, I can help you launch on-page notifications, lead capture prompts, and conversion-focused messages that fit this site.', 'notifal'),
            __('Let me guide you through templates, AI generation, display rules, and the HTML builder so you can choose what works for your audience.', 'notifal'),
        ];
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
     * Normalize detected palette colors with primary color and Notifal fallback.
     *
     * @since 3.0.0
     * @param string[] $brandColors Detected palette colors.
     * @param string   $primaryColor Detected primary color fallback.
     * @return string[] Sanitized palette with at least one color.
     */
    private function sanitizeBrandPalette(array $brandColors, string $primaryColor): array
    {
        // Start from detected palette entries when scrape returned multiple colors.
        $palette = [];

        foreach ($brandColors as $color) {
            $hex = strtolower(sanitize_text_field((string) $color));
            if (
                '' === $hex
                || !preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $hex)
                || in_array($hex, $palette, true)
            ) {
                continue;
            }

            $palette[] = $hex;
        }

        // Fall back to primary color when palette extraction returned nothing.
        $normalizedPrimary = strtolower(sanitize_text_field($primaryColor));
        if (
            [] === $palette
            && '' !== $normalizedPrimary
            && preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $normalizedPrimary)
        ) {
            $palette[] = $normalizedPrimary;
        }

        // Use Notifal purple when no brand colors were detected on the site.
        if ([] === $palette) {
            $palette[] = '#7e2bd2';
        }

        return array_slice($palette, 0, 3);
    }

    /**
     * Build recommended gateway use-case slugs from WooCommerce context.
     *
     * @since 3.0.0
     * @param bool $isEcommerce Whether WooCommerce is active on the site.
     * @return string[] Recommended use-case slugs.
     */
    private function buildRecommendedUseCases(bool $isEcommerce): array
    {
        // Prefer ecommerce-oriented slugs when WooCommerce is active.
        if ($isEcommerce) {
            return ['social_proof', 'cart_recovery', 'urgency', 'discount'];
        }
        // Default to lead and trust oriented slugs for general sites.
        return ['lead_capture', 'social_proof', 'announcement', 'newsletter'];
    }
}
