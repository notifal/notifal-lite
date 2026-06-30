<?php

namespace Notifal\Modules\Templates\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Pattern examples for the HTML Builder AI prompt form.
 *
 * Shows users how to fill each field well — not niche one-off templates.
 *
 * @since 2.4.1
 * @author Hossein <hossein@notifal.com>
 */
class HtmlBuilderAiPromptExamples
{
    /**
     * Return localized field-filling patterns users can load into the AI prompt form.
     *
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     layout_slug: string,
     *     use_case_slug: string,
     *     industry: string,
     *     user_goal: string,
     *     primary_color: string
     * }>
     * @since 2.4.1
     */
    public static function getExamples(): array
    {
        // Each row teaches how to write industry + goal for a layout/use-case pair.
        $examples = array(
            array(
                'id'            => 'pattern-popup-recover',
                'title'         => __('Popup — recover or retain visitors', 'notifal'),
                'layout_slug'   => 'popup',
                'use_case_slug' => 'recover-or-retain-visitors',
                'industry'      => __(
                    'Describe your niche: what you offer and who you help. Examples: "Online course platform for career changers", "WooCommerce pet supplies store", "Local accounting firm for small businesses".',
                    'notifal'
                ),
                'user_goal'     => __(
                    'Explain when it shows, what you offer, and how it should look. Example structure: Show on [exit intent / after 30–60s idle / before leaving checkout]. Offer [free trial / discount code / helpful resource]. Include a strong headline, up to 3 short benefits, one primary CTA button, and a subtle dismiss link. Tone: [premium and calm / friendly / urgent but not spammy]. Replace every bracket with your real details.',
                    'notifal'
                ),
                'primary_color' => '#2563EB',
            ),
            array(
                'id'            => 'pattern-toast-social-proof',
                'title'         => __('Floating side box — social proof', 'notifal'),
                'layout_slug'   => 'toast',
                'use_case_slug' => 'build-trust-and-social-proof',
                'industry'      => __(
                    'Describe your business type and what visitors buy or sign up for. Examples: "Physical product store on WooCommerce", "Membership site for photographers", "Booking site for home services".',
                    'notifal'
                ),
                'user_goal'     => __(
                    'Describe the social-proof moment and copy style. Example structure: Show a recent [purchase / signup / review] notification. Mention [buyer name, city, product or action] using merge tags where possible. Keep it short and believable — like a real update, not an ad. Add a small CTA to [product page / reviews / shop]. Tone: warm and trustworthy.',
                    'notifal'
                ),
                'primary_color' => '#7e2bd2',
            ),
            array(
                'id'            => 'pattern-topbar-announcement',
                'title'         => __('Floating bar — announcement or offer', 'notifal'),
                'layout_slug'   => 'topbar',
                'use_case_slug' => 'grow-email-list',
                'industry'      => __(
                    'Describe your site and audience. Examples: "News blog with a weekly newsletter", "B2B software marketing site", "Restaurant with online ordering". Add one detail that shapes tone (professional, playful, local, premium).',
                    'notifal'
                ),
                'user_goal'     => __(
                    'Describe what the bar promotes and how it should read. Example structure: Full-width bar for [free shipping threshold / new feature / limited promo / newsletter signup]. One bold headline, one short supporting line, CTA button on the right, close control on the left or right. Keep height compact. Tone: clear and scannable — visitors should get the message in 2 seconds.',
                    'notifal'
                ),
                'primary_color' => '#E11D48',
            ),
            array(
                'id'            => 'pattern-popup-sales',
                'title'         => __('Popup — discount or conversion offer', 'notifal'),
                'layout_slug'   => 'popup',
                'use_case_slug' => 'increase-sales',
                'industry'      => __(
                    'Describe what you sell and your brand feel. Examples: "Affordable fashion boutique", "Premium B2B analytics dashboard", "Digital templates marketplace". Mention price range or customer type if it affects design (budget vs luxury).',
                    'notifal'
                ),
                'user_goal'     => __(
                    'Describe the offer, trigger, and popup structure. Example structure: Show on [exit intent / first visit / cart page]. Offer [X% off / free gift / bundle deal] with [optional light urgency]. Headline + 1–2 lines of value + primary CTA + close button. If lead capture fits, note it (email field or "claim offer" CTA). Match visuals to brand: [minimal / bold / elegant].',
                    'notifal'
                ),
                'primary_color' => '#2D6A4F',
            ),
            array(
                'id'            => 'pattern-toast-contact',
                'title'         => __('Floating side box — contact or support', 'notifal'),
                'layout_slug'   => 'toast',
                'use_case_slug' => 'communicate-with-visitors',
                'industry'      => __(
                    'Describe your service and how customers reach you. Examples: "Dental clinic accepting new patients", "Freelance agency with global clients", "SaaS product with live chat support". Include region or language only if it matters.',
                    'notifal'
                ),
                'user_goal'     => __(
                    'Describe the contact action and tone. Example structure: Small side box inviting visitors to [WhatsApp / call / book a call / open chat]. One friendly sentence, clear CTA button, close control. When to show: [always on key pages / after 20s / on pricing page]. Tone: helpful and professional — low pressure, high trust.',
                    'notifal'
                ),
                'primary_color' => '#0D9488',
            ),
        );

        /**
         * Filter HTML Builder AI prompt field-filling patterns before they are sent to JavaScript.
         *
         * @since 2.4.1
         *
         * @param array<int, array<string, string>> $examples Pattern rows.
         */
        return (array) apply_filters(FilterHooks::TEMPLATE_HTML_BUILDER_AI_PROMPT_EXAMPLES, $examples);
    }
}
