<?php

namespace Notifal\Domain\Settings\Constants;

defined('ABSPATH') || exit;

/**
 * Settings key constants
 * 
 * Centralized definition of all setting keys used throughout Notifal.
 * Prevents typos and provides consistent key naming convention.
 * 
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SettingsKeys
{
    /**
     * Tag category enable/disable settings
     * 
     * These settings control which tag categories are available
     * in tag selectors throughout the plugin interface.
     */
    
    /**
     * Enable/disable user tags
     * 
     * Controls visibility of user-related tags like {user_name}, {user_email}.
     * Always available as part of core WordPress functionality.
     * 
     * @var string
     */
    public const USER_TAGS_ENABLED = 'user_tags_enabled';

    /**
     * Enable/disable post tags
     * 
     * Controls visibility of post-related tags like {post_title}, {post_content}.
     * Always available as part of core WordPress functionality.
     * 
     * @var string
     */
    public const POST_TAGS_ENABLED = 'post_tags_enabled';

    /**
     * Enable/disable page tags
     * 
     * Controls visibility of page-related tags like {page_title}, {page_url}.
     * Always available as part of core WordPress functionality.
     * 
     * @var string
     */
    public const PAGE_TAGS_ENABLED = 'page_tags_enabled';

    /**
     * Enable/disable comment tags - MOVED TO NOTIFAL PRO
     * 
     * Comment tags are now part of Notifal Pro to maintain proper feature separation.
     * This constant is kept for backward compatibility but not used in core.
     * 
     * @var string
     * @deprecated 2.0.0 Moved to Notifal Pro
     */
    public const COMMENT_TAGS_ENABLED = 'comment_tags_enabled';

    /**
     * Enable/disable product tags
     * 
     * Controls visibility of product-related tags like {product_name}, {product_price}.
     * Only available when WooCommerce plugin is active.
     * 
     * @var string
     */
    public const PRODUCT_TAGS_ENABLED = 'product_tags_enabled';

    /**
     * Enable/disable order tags
     * 
     * Controls visibility of order-related tags like {order_total}, {order_status}.
     * Only available when WooCommerce plugin is active.
     * 
     * @var string
     */
    public const ORDER_TAGS_ENABLED = 'order_tags_enabled';

    /**
     * Enable/disable WooCommerce cart tags.
     *
     * Controls visibility of cart-related tags like {cart_total}, {cart_item_count}.
     * Only available when WooCommerce plugin is active.
     *
     * @var string
     * @since 2.3.5
     */
    public const CART_TAGS_ENABLED = 'cart_tags_enabled';

    /**
     * Future settings placeholders
     * 
     * These will be used in upcoming versions for additional features.
     */

    /**
     * Enable/disable custom post type tags
     * 
     * Will control auto-detection and inclusion of custom post types.
     * Planned for future release with universal tag manager.
     * 
     * @var string
     */
    public const CUSTOM_POST_TYPES_ENABLED = 'custom_post_types_enabled';

    /**
     * OnPage notification global settings prefix
     * 
     * All OnPage notification settings will use this prefix.
     * Example: onpage.animation_enabled, onpage.sound_enabled
     * 
     * @var string
     */
    public const ONPAGE_PREFIX = 'onpage.';

    /**
     * Template system settings prefix
     * 
     * All template-related settings will use this prefix.
     * Example: templates.cache_enabled, templates.compression_enabled
     * 
     * @var string
     */
    public const TEMPLATES_PREFIX = 'templates.';

    /**
     * Enable/disable the Forms module (kill switch).
     *
     * When false, Forms ServiceProvider loads nothing.
     *
     * @var string
     * @since 3.0.0
     */
    public const FORMS_ENABLED = 'forms_enabled';

    /**
     * Enable/disable Forms honeypot field.
     *
     * @var string
     * @since 3.0.0
     */
    public const FORMS_HONEYPOT_ENABLED = 'forms_honeypot_enabled';

    /**
     * Max form submissions allowed per rate-limit window.
     *
     * @var string
     * @since 3.0.0
     */
    public const FORMS_RATE_LIMIT_MAX = 'forms_rate_limit_max';

    /**
     * Rate-limit window length in seconds.
     *
     * @var string
     * @since 3.0.0
     */
    public const FORMS_RATE_LIMIT_WINDOW = 'forms_rate_limit_window';

    /**
     * Captcha provider: none|google_recaptcha_v2|google_recaptcha_v3|cloudflare_turnstile.
     *
     * @var string
     * @since 3.0.0
     */
    public const FORMS_CAPTCHA_PROVIDER = 'forms_captcha_provider';

    /**
     * Public captcha site key.
     *
     * @var string
     * @since 3.0.0
     */
    public const FORMS_CAPTCHA_SITE_KEY = 'forms_captcha_site_key';

    /**
     * Captcha secret key (server only).
     *
     * @var string
     * @since 3.0.0
     */
    public const FORMS_CAPTCHA_SECRET_KEY = 'forms_captcha_secret_key';

    /**
     * Default admin email for form submission notifications.
     *
     * @var string
     * @since 3.0.0
     */
    public const FORMS_DEFAULT_ADMIN_EMAIL = 'forms_default_admin_email';

    /**
     * Get all tag-related setting keys
     * 
     * Returns array of all tag category setting keys for bulk operations.
     * Useful for settings page rendering and validation.
     * 
     * @return array Array of tag setting keys
     * @since 2.0.0
     */
    public static function getTagKeys(): array
    {
        return [
            self::USER_TAGS_ENABLED,
            self::POST_TAGS_ENABLED,
            self::PAGE_TAGS_ENABLED,
            // self::COMMENT_TAGS_ENABLED, // Moved to Notifal Pro
            self::PRODUCT_TAGS_ENABLED,
            self::ORDER_TAGS_ENABLED,
            self::CART_TAGS_ENABLED,
        ];
    }

    /**
     * Get all Forms setting keys.
     *
     * @return array<int, string>
     * @since 3.0.0
     */
    public static function getFormsKeys(): array
    {
        return [
            self::FORMS_ENABLED,
            self::FORMS_HONEYPOT_ENABLED,
            self::FORMS_RATE_LIMIT_MAX,
            self::FORMS_RATE_LIMIT_WINDOW,
            self::FORMS_CAPTCHA_PROVIDER,
            self::FORMS_CAPTCHA_SITE_KEY,
            self::FORMS_CAPTCHA_SECRET_KEY,
            self::FORMS_DEFAULT_ADMIN_EMAIL,
        ];
    }

    /**
     * Default Forms settings values.
     *
     * @return array<string, mixed>
     * @since 3.0.0
     */
    public static function getDefaultFormsSettings(): array
    {
        return [
            self::FORMS_ENABLED            => true,
            self::FORMS_HONEYPOT_ENABLED   => true,
            self::FORMS_RATE_LIMIT_MAX     => 5,
            self::FORMS_RATE_LIMIT_WINDOW  => 300,
            self::FORMS_CAPTCHA_PROVIDER   => 'none',
            self::FORMS_CAPTCHA_SITE_KEY   => '',
            self::FORMS_CAPTCHA_SECRET_KEY => '',
            self::FORMS_DEFAULT_ADMIN_EMAIL => '',
        ];
    }

    /**
     * Get WordPress core tag setting keys
     * 
     * Returns array of tag keys that are always available (core WordPress).
     * These don't depend on any plugin being active.
     * 
     * @return array Array of core tag setting keys
     * @since 2.0.0
     */
    public static function getCoreTagKeys(): array
    {
        return [
            self::USER_TAGS_ENABLED,
            self::POST_TAGS_ENABLED,
            self::PAGE_TAGS_ENABLED,
            // self::COMMENT_TAGS_ENABLED, // Moved to Notifal Pro
        ];
    }

    /**
     * Get plugin-dependent tag setting keys
     * 
     * Returns array of tag keys that require specific plugins to be active.
     * Used for conditional settings display and validation.
     * 
     * @return array Array of plugin-dependent tag setting keys
     * @since 2.0.0
     */
    public static function getPluginDependentTagKeys(): array
    {
        return [
            self::PRODUCT_TAGS_ENABLED,
            self::ORDER_TAGS_ENABLED,
            self::CART_TAGS_ENABLED,
        ];
    }

    /**
     * Get category to setting key mapping
     *
     * Centralizes the mapping between tag categories and their corresponding setting keys.
     * Used by TagManager and other components to avoid code duplication.
     *
     * @return array Array mapping category names to setting keys
     * @since 2.0.0
     */
    public static function getCategoryMapping(): array
    {
        return [
            // Core WordPress tags (always available)
            'users' => self::USER_TAGS_ENABLED,
            'posts' => self::POST_TAGS_ENABLED,
            'pages' => self::PAGE_TAGS_ENABLED,
            'comments' => self::COMMENT_TAGS_ENABLED,

            // Plugin-dependent tags (require WooCommerce)
            'products' => self::PRODUCT_TAGS_ENABLED,
            'orders'   => self::ORDER_TAGS_ENABLED,
            'cart'     => self::CART_TAGS_ENABLED,
        ];
    }

    /**
     * Get default tag settings configuration
     *
     * Centralizes the default values and structure for all tag-related settings.
     * Used by SettingsModel and SettingsService to avoid code duplication.
     *
     * @return array Array of tag setting keys with their default values
     * @since 2.0.0
     */
    public static function getDefaultTagSettings(): array
    {
        return [
            self::USER_TAGS_ENABLED => true,
            self::POST_TAGS_ENABLED => true,
            self::PAGE_TAGS_ENABLED => true,
            self::COMMENT_TAGS_ENABLED => self::getDefaultCommentTagsValue(),
            self::PRODUCT_TAGS_ENABLED => true,
            self::ORDER_TAGS_ENABLED   => true,
            self::CART_TAGS_ENABLED    => true,
        ];
    }

    /**
     * Get default value for comment tags setting
     *
     * Comment tags are handled by Notifal Pro plugin.
     * Returns filtered value to allow Pro version override.
     *
     * @return bool Default value for comment tags
     * @since 2.0.0
     */
    public static function getDefaultCommentTagsValue(): bool
    {
        return apply_filters('notifal_pro_comment_tags_allowed', false);
    }

    /**
     * Validate setting key format
     *
     * Checks if provided key matches expected naming convention.
     * Ensures consistency across all setting operations.
     *
     * @param string $key Setting key to validate
     * @return bool True if key format is valid
     * @since 2.0.0
     */
    public static function isValidKey(string $key): bool
    {
        // Key should be non-empty string with allowed characters
        return !empty($key) && preg_match('/^[a-z_][a-z0-9_.]*$/i', $key);
    }
}
