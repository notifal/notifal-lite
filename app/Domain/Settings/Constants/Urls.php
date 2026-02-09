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

    /**
     * Main website URLs
     */
    public const WEBSITE_HOME = 'https://notifal.com';

    /**
     * Support and help URLs
     */
    public const SUPPORT_PAGE = 'https://notifal.com/my-account/support';
    public const KNOWLEDGE_BASE = 'https://notifal.com/knowledge-base/';
    public const COMMUNITY = 'https://notifal.com/community/';

    /**
     * Learning and onboarding URLs
     */
    public const ONBOARDING_COURSE = 'https://notifal.com/community/course/onboarding/lessons';

    /**
     * Business URLs
     */
    public const PRICING = 'https://notifal.com/pricing/';

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
     * Generate URL with UTM parameters for activation popup
     *
     * @param string $url Base URL to add UTM parameters to
     * @param string $campaign UTM campaign parameter
     * @return string URL with UTM parameters
     */
    public static function withActivationPopupUtm(string $url, string $campaign): string
    {
        return add_query_arg([
            'utm_source' => 'activation_popup',
            'utm_medium' => 'plugin',
            'utm_campaign' => $campaign,
        ], $url);
    }

    /**
     * Generate URL with UTM parameters for plugin context
     *
     * @param string $url Base URL to add UTM parameters to
     * @param string $source UTM source parameter
     * @param string $campaign UTM campaign parameter (optional)
     * @return string URL with UTM parameters
     */
    public static function withPluginUtm(string $url, string $source = 'wordpress_plugin', string $campaign = ''): string
    {
        $args = [
            'utm_source' => $source,
            'utm_medium' => 'plugin',
        ];

        if (!empty($campaign)) {
            $args['utm_campaign'] = $campaign;
        }

        return add_query_arg($args, $url);
    }

    /**
     * Generate URL with UTM parameters for upgrade context
     *
     * @param string $url Base URL to add UTM parameters to
     * @param string $source UTM source parameter
     * @return string URL with UTM parameters
     */
    public static function withUpgradeUtm(string $url, string $source = ''): string
    {
        $args = [
            'utm_source' => $source,
            'utm_medium' => 'plugin',
            'utm_campaign' => 'upgrade',
        ];

        return add_query_arg($args, $url);
    }

    /**
     * Get onboarding URL with activation popup UTM parameters
     *
     * @return string
     */
    public static function getOnboardingUrl(): string
    {
        return self::withActivationPopupUtm(self::ONBOARDING_COURSE, 'onboarding');
    }

    /**
     * Get knowledge base URL with activation popup UTM parameters
     *
     * @return string
     */
    public static function getKnowledgeBaseUrl(): string
    {
        return self::withActivationPopupUtm(self::KNOWLEDGE_BASE, 'tutorials');
    }

    /**
     * Get community URL with activation popup UTM parameters
     *
     * @return string
     */
    public static function getCommunityUrl(): string
    {
        return self::withActivationPopupUtm(self::COMMUNITY, 'community');
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
