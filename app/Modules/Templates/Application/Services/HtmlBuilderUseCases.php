<?php

namespace Notifal\Modules\Templates\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Use case options for the HTML Builder AI prompt helper.
 *
 * @since 2.4.1
 * @author Hossein <hossein@notifal.com>
 */
class HtmlBuilderUseCases
{
    /**
     * Return localized use case options for the builder UI.
     *
     * @return array<int, array{slug: string, label: string, description: string}>
     * @since 2.4.1
     */
    public static function getOptions(): array
    {
        // Build the list of goals users can pick when generating AI prompts.
        $options = array(
            array(
                'slug'        => 'increase-sales',
                'label'       => __('Increase Sales', 'notifal'),
                'description' => __('Recover lost sales with exit-intent coupons, flash discounts, and cross-sell offers powered by live WooCommerce cart data.', 'notifal'),
            ),
            array(
                'slug'        => 'grow-email-list',
                'label'       => __('Grow Email List', 'notifal'),
                'description' => __('Turn visitors into subscribers with newsletter popups, scroll-based forms, and exit-intent lead capture.', 'notifal'),
            ),
            array(
                'slug'        => 'boost-engagement',
                'label'       => __('Boost Engagement', 'notifal'),
                'description' => __('Spotlight new posts, products, and reviews the moment they go live with timely on-page notifications.', 'notifal'),
            ),
            array(
                'slug'        => 'build-trust-and-social-proof',
                'label'       => __('Build Trust and Social Proof', 'notifal'),
                'description' => __('Show real-time sales, Google and Trustpilot ratings, and testimonials that turn visitors into confident buyers.', 'notifal'),
            ),
            array(
                'slug'        => 'communicate-with-visitors',
                'label'       => __('Communicate with Visitors', 'notifal'),
                'description' => __('Add WhatsApp chat, click-to-call, and live support widgets so visitors can reach you instantly.', 'notifal'),
            ),
            array(
                'slug'        => 'recover-or-retain-visitors',
                'label'       => __('Recover or Retain Visitors', 'notifal'),
                'description' => __('Win back idle visitors and abandoning carts with perfectly timed exit-intent and inactivity prompts.', 'notifal'),
            ),
            array(
                'slug'        => 'promote-social-channels',
                'label'       => __('Promote Social Channels', 'notifal'),
                'description' => __('Grow your YouTube and social following with on-page promotion that appears at the right moment.', 'notifal'),
            ),
            array(
                'slug'        => 'content-blocking',
                'label'       => __('Content Blocking', 'notifal'),
                'description' => __('Gate content with age verification or registration walls, fully customizable for your site rules.', 'notifal'),
            ),
            array(
                'slug'        => 'grow-aov',
                'label'       => __('Grow AOV (Average Order Value)', 'notifal'),
                'description' => __('Lift average order value with free shipping progress bars, upsells, and cross-sell recommendations.', 'notifal'),
            ),
            array(
                'slug'        => 'prevent-cart-abandonment',
                'label'       => __('Prevent Cart Abandonment', 'notifal'),
                'description' => __('Catch shoppers right before they leave checkout with a dedicated cart recovery notification.', 'notifal'),
            ),
        );

        /**
         * Filter HTML Builder use case options before they are sent to JavaScript.
         *
         * @since 2.4.1
         *
         * @param array<int, array{slug: string, label: string, description: string}> $options Use case rows.
         */
        return (array) apply_filters(FilterHooks::TEMPLATE_HTML_BUILDER_USE_CASES, $options);
    }
}
