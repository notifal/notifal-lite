<?php

namespace Notifal\Domain\Settings\Constants;

defined('ABSPATH') || exit;

/**
 * URL constants for external notifal.com resources
 *
 * Centralized definition of all external URLs used throughout Notifal plugins.
 * Provides single source of truth for URL management and easy maintenance.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class Urls
{
    /**
     * Base domain for all notifal.com URLs
     *
     * @var string
     */
    public const NOTIFAL_BASE_DOMAIN = 'https://notifal.com';

    /**
     * API base URLs
     */
    public const API_BASE_NOTIFAL = 'https://notifal.com/wp-json/notifal/v1';
    public const API_BASE_MARKETPLACE = 'https://notifal.com/wp-json/notifal-mp/v1';
    public const API_BASE_LICENSE = 'https://notifal.com/wp-json/nlm/v1';
    public const API_BASE_AI_GATEWAY = 'https://notifal.com/wp-json/notifal-ai/v1';
    public const API_BASE_AI_SUPPORT = 'https://notifal.com/wp-json/nas/v1';

    /**
     * Main website URLs
     */
    public const WEBSITE_HOME = 'https://notifal.com';

    /**
     * Support and help URLs
     */
    public const SUPPORT_PAGE = 'https://notifal.com/my-account/support';
    public const KNOWLEDGE_BASE = 'https://notifal.com/knowledge-base/';
    /**
     * Product documentation hub on notifal.com.
     *
     * Prefer this over the knowledge base for plugin list and similar help CTAs.
     *
     * @var string
     * @since 3.0.0
     */
    public const DOCUMENTATION = 'https://notifal.com/documentation/';
    public const COMMUNITY = 'https://notifal.com/community/';

    /**
     * Privacy policy page URL.
     *
     * Linked from AI disclosure copy and related legal notices.
     *
     * @var string
     * @since 3.0.0
     */
    public const PRIVACY_POLICY = 'https://notifal.com/privacy-policy/';

    /**
     * Learning and onboarding URLs
     */
    public const ONBOARDING_COURSE = 'https://notifal.com/community/course/onboarding/lessons';

    /**
     * Business URLs
     */
    public const PRICING = 'https://notifal.com/pricing/';

    /**
     * Free configuration landing page URL.
     *
     * Used by admin menu, plugin row meta, and deactivation popup CTAs.
     *
     * @var string
     * @since 2.3.5
     */
    public const FREE_CONFIGURATION = 'https://notifal.com/same-traffic-more-sales/';

    /**
     * Account management URLs
     */
    public const LICENSE_MANAGER = 'https://notifal.com/my-account/license-manager/';

    /**
     * Product information URLs
     */
    public const CHANGELOG = 'https://notifal.com/changelog/';

    /**
     * Blog and content URLs
     */
    public const BLOG_FAKE_SALES = 'https://notifal.com/how-fake-sales-hurt-brands-conversions-and-customer-loyalty/';

    /**
     * Template library URL
     */
    public const TEMPLATES_LIBRARY = 'https://notifal.com/templates/';

    /**
     * Generate URL with UTM parameters for plugin context.
     *
     * @param string $url Base URL to add UTM parameters to.
     * @param string $source UTM source parameter.
     * @param string $campaign UTM campaign parameter (optional).
     * @return string URL with UTM parameters.
     */
    public static function withPluginUtm(string $url, string $source = 'wordpress_plugin', string $campaign = ''): string
    {
        // Build the shared plugin UTM query arguments.
        $args = [
            'utm_source' => $source,
            'utm_medium' => 'plugin',
        ];
        // Append the campaign only when one is provided.
        if ('' !== $campaign) {
            $args['utm_campaign'] = $campaign;
        }
        // Return the final tracked URL.
        return add_query_arg($args, $url);
    }

    /**
     * Generate URL with UTM parameters for upgrade context.
     *
     * @param string $url Base URL to add UTM parameters to.
     * @param string $source UTM source parameter.
     * @return string URL with UTM parameters.
     */
    public static function withUpgradeUtm(string $url, string $source = ''): string
    {
        // Build the shared upgrade UTM query arguments.
        $args = [
            'utm_source' => $source,
            'utm_medium' => 'plugin',
            'utm_campaign' => 'upgrade',
        ];
        // Return the final tracked URL.
        return add_query_arg($args, $url);
    }

    /**
     * Get onboarding URL with plugin UTM parameters.
     *
     * @return string
     */
    public static function getOnboardingUrl(): string
    {
        return self::withPluginUtm(self::ONBOARDING_COURSE, 'wordpress_plugin', 'onboarding');
    }

    /**
     * Get knowledge base URL with plugin UTM parameters.
     *
     * @return string
     */
    public static function getKnowledgeBaseUrl(): string
    {
        return self::withPluginUtm(self::KNOWLEDGE_BASE, 'wordpress_plugin', 'tutorials');
    }

    /**
     * Get community URL with plugin UTM parameters.
     *
     * @return string
     */
    public static function getCommunityUrl(): string
    {
        return self::withPluginUtm(self::COMMUNITY, 'wordpress_plugin', 'community');
    }

    /**
     * Get pricing URL with domain parameter
     *
     * @param string $domain Current site domain for context
     * @return string
     */
    public static function getPricingUrl(string $domain = ''): string
    {
        $url = self::PRICING;
        if (!empty($domain)) {
            $url = add_query_arg('domain', urlencode($domain), $url);
        }
        return $url;
    }

    /**
     * Get upgrade URL for specific feature
     *
     * @param string $source Feature or context for upgrade
     * @return string
     */
    public static function getUpgradeUrl(string $source = ''): string
    {
        return self::withUpgradeUtm(self::PRICING, $source);
    }

    /**
     * Generate URL with custom UTM parameters for plugin context
     *
     * @param string $url Base URL to add UTM parameters to
     * @param array $utmParams Associative array of UTM parameters (source, medium, campaign, content, term)
     * @return string URL with UTM parameters
     */
    public static function withCustomUtm(string $url, array $utmParams = []): string
    {
        $defaultParams = [
            'utm_source' => 'wordpress_plugin',
            'utm_medium' => 'plugin',
        ];

        $args = array_merge($defaultParams, array_filter($utmParams));

        return add_query_arg($args, $url);
    }

    /**
     * Validate URL belongs to notifal.com domain
     *
     * @param string $url URL to validate
     * @return bool True if URL belongs to notifal.com
     */
    public static function isNotifalUrl(string $url): bool
    {
        return strpos($url, self::NOTIFAL_BASE_DOMAIN) === 0;
    }
}
