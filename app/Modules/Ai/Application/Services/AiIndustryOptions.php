<?php

namespace Notifal\Modules\Ai\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Selectable industry options for hosted AI personalization.
 *
 * Broad industries can request a short niche detail (for example what an
 * e-commerce store sells) so generation prompts stay specific.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class AiIndustryOptions
{
    /**
     * Return localized industry cards for the AI wizard.
     *
     * @since 3.0.0
     * @return array<int, array<string, mixed>>
     */
    public static function getOptions(): array
    {
        // Prefer existing Notifal icons when a clear match already exists in the font.
        // Add industry-* sources only for industries that have no suitable icon yet.
        $options = array(
            array(
                'slug'   => 'ecommerce',
                'label'  => __('E-commerce / Online Store', 'notifal'),
                'icon'   => 'handbag-fill',
                'detail' => self::detailField(
                    __('What exactly do you sell?', 'notifal'),
                    __('e.g. Home appliances, skincare, pet supplies…', 'notifal')
                ),
            ),
            array(
                'slug'   => 'saas',
                'label'  => __('SaaS / Software', 'notifal'),
                'icon'   => 'laptop',
                'detail' => self::detailField(
                    __('What does your product help with?', 'notifal'),
                    __('e.g. Project management for agencies, invoicing for freelancers…', 'notifal')
                ),
            ),
            array(
                'slug'   => 'education',
                'label'  => __('Education / E-learning', 'notifal'),
                'icon'   => 'mortarboard-fill',
                'detail' => self::detailField(
                    __('What do you teach or offer?', 'notifal'),
                    __('e.g. Online language courses, coding bootcamps…', 'notifal')
                ),
            ),
            array(
                'slug'   => 'agency',
                'label'  => __('Agency / Services', 'notifal'),
                'icon'   => 'industry-agency',
                'detail' => self::detailField(
                    __('What services do you offer?', 'notifal'),
                    __('e.g. Branding, SEO, web design…', 'notifal')
                ),
            ),
            array(
                'slug'   => 'healthcare',
                'label'  => __('Healthcare / Medical', 'notifal'),
                'icon'   => 'industry-healthcare',
                'detail' => self::detailField(
                    __('What care or specialty do you offer?', 'notifal'),
                    __('e.g. Dental clinic, telehealth for parents…', 'notifal')
                ),
            ),
            array(
                'slug'   => 'real-estate',
                'label'  => __('Real Estate', 'notifal'),
                'icon'   => 'industry-real-estate',
                'detail' => self::detailField(
                    __('What do you specialize in?', 'notifal'),
                    __('e.g. Luxury apartments, commercial leasing…', 'notifal')
                ),
            ),
            array(
                'slug'   => 'finance',
                'label'  => __('Finance / Fintech', 'notifal'),
                'icon'   => 'coin',
                'detail' => self::detailField(
                    __('What financial products or services?', 'notifal'),
                    __('e.g. Personal loans, investment coaching…', 'notifal')
                ),
            ),
            array(
                'slug'   => 'travel',
                'label'  => __('Travel / Hospitality', 'notifal'),
                'icon'   => 'industry-travel',
                'detail' => self::detailField(
                    __('What travel or hospitality do you offer?', 'notifal'),
                    __('e.g. Boutique hotels, adventure tours…', 'notifal')
                ),
            ),
            array(
                'slug'   => 'food',
                'label'  => __('Food & Restaurant', 'notifal'),
                'icon'   => 'industry-food',
                'detail' => self::detailField(
                    __('What kind of food or restaurant?', 'notifal'),
                    __('e.g. Vegan cafe, pizza delivery…', 'notifal')
                ),
            ),
            array(
                'slug'   => 'fitness',
                'label'  => __('Fitness / Wellness', 'notifal'),
                'icon'   => 'industry-fitness',
                'detail' => self::detailField(
                    __('What kind of fitness or wellness?', 'notifal'),
                    __('e.g. Yoga studio, online coaching…', 'notifal')
                ),
            ),
            array(
                'slug'   => 'media',
                'label'  => __('Media / Publishing', 'notifal'),
                'icon'   => 'megaphone-fill',
                'detail' => self::detailField(
                    __('What do you publish or cover?', 'notifal'),
                    __('e.g. Tech newsletter, local news…', 'notifal')
                ),
            ),
            array(
                'slug'   => 'nonprofit',
                'label'  => __('Non-profit / Charity', 'notifal'),
                'icon'   => 'industry-nonprofit',
                'detail' => self::detailField(
                    __('What cause do you support?', 'notifal'),
                    __('e.g. Animal rescue, education access…', 'notifal')
                ),
            ),
            array(
                'slug'  => 'other',
                'label' => __('Other', 'notifal'),
                'icon'  => 'tags-fill',
            ),
        );

        /**
         * Filter hosted AI industry options before they are sent to JavaScript.
         *
         * @since 3.0.0
         *
         * @param array<int, array<string, mixed>> $options Industry rows.
         */
        $options = (array) apply_filters(FilterHooks::AI_INDUSTRY_OPTIONS, $options);

        foreach ($options as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            if (!isset($row['summary']) || '' === trim((string) $row['summary'])) {
                $slug = isset($row['slug']) ? sanitize_key((string) $row['slug']) : '';
                $options[$index]['summary'] = self::summaryForSlug($slug);
            }
        }

        return $options;
    }

    /**
     * Return allowed industry slugs for server-side validation.
     *
     * @since 3.0.0
     * @return string[] Sanitized industry slugs.
     */
    public static function getAllowedSlugs(): array
    {
        // Collect slug values from the localized industry card registry.
        $slugs = array();
        foreach (self::getOptions() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $slug = isset($row['slug']) ? sanitize_key((string) $row['slug']) : '';
            if ('' !== $slug) {
                $slugs[] = $slug;
            }
        }
        // Return unique slug values for allowlist checks.
        return array_values(array_unique($slugs));
    }

    /**
     * Default industry card when a value is outside the UI allowlist.
     *
     * @since 3.0.0
     * @var string
     */
    public const DEFAULT_SLUG = AiIndustrySlugNormalizer::DEFAULT_SLUG;

    /**
     * Coerce one industry slug onto the closed UI card allowlist.
     *
     * Exact card matches pass through; otherwise falls back to other.
     * Niche soft-aliases (grocery → food, …) live on the AI gateway only.
     *
     * @since 3.0.0
     * @param string $industrySlug Raw industry slug.
     * @return string Allowlisted slug, or empty string when input is blank.
     */
    public static function normalizeSlug(string $industrySlug): string
    {
        // Thin closed-set coerce for picker / local store defense-in-depth.
        return AiIndustrySlugNormalizer::normalize($industrySlug, self::getAllowedSlugs());
    }

    /**
     * Return true when the slug exactly matches an allowed industry card.
     *
     * @since 3.0.0
     * @param string $industrySlug Industry slug candidate.
     * @return bool
     */
    public static function isAllowedSlug(string $industrySlug): bool
    {
        // Exact card membership for picker AJAX validation.
        return AiIndustrySlugNormalizer::isAllowed($industrySlug, self::getAllowedSlugs());
    }

    /**
     * Return a short card summary for a known industry slug.
     *
     * @since 3.0.0
     * @param string $slug Industry slug.
     * @return string Localized summary line.
     */
    private static function summaryForSlug(string $slug): string
    {
        $map = array(
            'ecommerce'   => __('Optimize sales & customer support', 'notifal'),
            'saas'        => __('Drive growth & user retention', 'notifal'),
            'real-estate' => __('Lead gen & property inquiries', 'notifal'),
            'education'   => __('Student support & course info', 'notifal'),
            'healthcare'  => __('Booking & patient guidance', 'notifal'),
            'agency'      => __('Client onboarding & sales', 'notifal'),
            'finance'     => __('Trust-building & product offers', 'notifal'),
            'travel'      => __('Bookings & guest engagement', 'notifal'),
            'food'        => __('Orders, menus & reservations', 'notifal'),
            'fitness'     => __('Memberships & class signups', 'notifal'),
            'media'       => __('Audience growth & subscriptions', 'notifal'),
            'nonprofit'   => __('Donations & volunteer outreach', 'notifal'),
            'other'       => __('Tell us more on the next step', 'notifal'),
        );

        return isset($map[$slug]) ? $map[$slug] : __('Personalized popups for your niche', 'notifal');
    }

    /**
     * Build a niche-detail field config for broad industry cards.
     *
     * @since 3.0.0
     * @param string $label Field label.
     * @param string $placeholder Field placeholder.
     * @return array{label: string, placeholder: string, required: bool}
     */
    private static function detailField(string $label, string $placeholder): array
    {
        return array(
            'label'       => $label,
            'placeholder' => $placeholder,
            'required'    => true,
        );
    }
}
