<?php
/**
 * Sticky Menu Domain Logic
 *
 * Handles the business logic for sticky menu functionality including
 * determining which menu items to display based on user status.
 *
 * @package Notifal\Infrastructure\WordPress\StickyMenu\Domain
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\StickyMenu\Domain;

use Notifal\Core\Support\Helpers\UrlHelper;
use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Infrastructure\WordPress\WhatsNewPopup\Domain\WhatsNewPopup;
use Notifal\Shared\Services\NotifalLogoService;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\UrlService;

defined('ABSPATH') || exit;

/**
 * Class StickyMenu
 */
class StickyMenu
{
    /**
     * Check if Notifal Pro is installed and active
     *
     * @return bool True if pro is active
     * @since 2.0.0
     */
    public function isProActive(): bool
    {
        return PluginDetector::isNotifalProActive();
    }

    /**
     * Check if Notifal Pro is installed (but not necessarily active)
     *
     * @return bool True if pro is installed
     * @since 2.0.0
     */
    public function isProInstalled(): bool
    {
        return PluginDetector::isPluginInstalled('notifal-pro/notifal-pro.php');
    }

    /**
     * Check if the user is connected/authenticated with Notifal services
     *
     * Checks if Notifal Pro is installed and has a valid, active license.
     *
     * @return bool True if connected and license is valid
     * @since 2.0.0
     */
    public function isConnected(): bool
    {
        // Check if Notifal Pro is installed
        if (!$this->isProInstalled()) {
            return false;
        }

        // Check if Notifal Pro license validation classes exist
        if (!class_exists('NotifalPro\Domain\License\Services\LicenseStatusChecker')) {
            return false;
        }

        // Use Notifal Pro's license status checker to validate connection
        try {
            return \NotifalPro\Domain\License\Services\LicenseStatusChecker::validate_feature_access('sticky_menu_connection_check');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the current plugin version
     *
     * @return string Current version
     * @since 2.0.0
     */
    public function getCurrentVersion(): string
    {
        return defined('NOTIFAL_VERSION') ? NOTIFAL_VERSION : '2.0.0';
    }

    /**
     * Check if a plugin update is available from the WordPress.org repository
     *
     * Uses the update_plugins transient populated by WordPress core.
     *
     * @return bool True if an update is available for the Notifal plugin
     * @since 2.0.0
     */
    public function hasPluginUpdateAvailable(): bool
    {
        if (!defined('NOTIFAL_BASENAME')) {
            return false;
        }

        $updates = get_site_transient('update_plugins');
        if (!is_object($updates) || !isset($updates->response) || !is_array($updates->response)) {
            return false;
        }

        return isset($updates->response[NOTIFAL_BASENAME]);
    }

    /**
     * Get the URL to the Plugins admin page for updating the plugin
     *
     * @return string Admin URL to plugins.php
     * @since 2.0.0
     */
    public function getPluginsPageUrl(): string
    {
        return UrlHelper::admin('plugins.php');
    }

    /**
     * Get menu configuration for the first row
     *
     * @return array First row menu items
     * @since 2.0.0
     */
    public function getFirstRowMenuItems(): array
    {
        $items = [
            [
                'type' => 'logo',
                'svg' => $this->getLogoSvg(),
                'alt' => __('Notifal', 'notifal'),
                'version' => $this->getCurrentVersion(),
                'update_available' => $this->hasPluginUpdateAvailable(),
                'update_url' => $this->getPluginsPageUrl(),
            ],
            [
                'type' => 'link',
                'text' => __('Onboarding', 'notifal'),
                'url' => Urls::withPluginUtm(Urls::ONBOARDING_COURSE, 'sticky_menu'),
                'external' => true,
                'icon' => 'rocket',
            ],
            [
                'type' => 'link',
                'text' => __('Community', 'notifal'),
                'url' => Urls::withPluginUtm(Urls::COMMUNITY, 'sticky_menu'),
                'external' => true,
                'icon' => 'people',
            ],
            [
                'type' => 'link',
                'text' => __('Templates Library', 'notifal'),
                'url' => Urls::withPluginUtm(Urls::TEMPLATES_LIBRARY, 'sticky_menu'),
                'external' => true,
                'icon' => 'layers',
            ],
            [
                'type' => 'link',
                'text' => __('Support', 'notifal'),
                'url' => Urls::withPluginUtm(Urls::SUPPORT_PAGE, 'sticky_menu'),
                'external' => true,
                'icon' => 'chat-left-dots',
            ],
        ];

        // Add upgrade/connect button based on status
        if (!$this->isProInstalled()) {
            $items[] = [
                'type' => 'button',
                'text' => __('Upgrade', 'notifal'),
                'url' => Urls::withPluginUtm(Urls::getPricingUrl(parse_url(get_site_url(), PHP_URL_HOST)), 'sticky_menu', 'upgrade'),
                'external' => true,
                'primary' => true,
                'icon' => 'rocket',
            ];
        } elseif (!$this->isConnected()) {
            $items[] = [
                'type' => 'button',
                'text' => __('Connect & Activate', 'notifal'),
                'url' => UrlHelper::admin('admin.php?page=notifal-pro-connect'),
                'external' => true,
                'primary' => true,
                'icon' => 'key',
            ];
        }
        // If connected, no button is shown

        return $items;
    }

    /**
     * Get menu configuration for the second row
     *
     * @return array Second row menu items
     * @since 2.0.0
     */
    public function getSecondRowMenuItems(): array
    {
        $urlService = new UrlService();

        $items = [
            [
                'type' => 'link',
                'icon' => 'megaphone',
                'text' => __('Onpage Notifications List', 'notifal'),
                'url' => $urlService->getListUrl(),
                'title' => __('View all on-page notifications', 'notifal'),
            ],
            [
                'type' => 'link',
                'text' => __('Analytics', 'notifal'),
                'url' => UrlHelper::admin('admin.php?page=notifal-onpage-analytics'),
                'icon' => 'exchange-rate',
            ],
            [
                'type' => 'link',
                'text' => __('Templates', 'notifal'),
                'url' => UrlHelper::admin('admin.php?page=notifal_templates'),
                'icon' => 'layers',
            ],
            [
                'type' => 'link',
                'text' => __('Settings', 'notifal'),
                'url' => UrlHelper::admin('admin.php?page=notifal-settings'),
                'icon' => 'sliders2',
            ],
        ];

        // Only show What's New button when current version has update content to display
        if ($this->hasWhatsNewContentForCurrentVersion()) {
            $items[] = [
                'type' => 'button',
                'text' => sprintf(__("What's New %s", 'notifal'), $this->getCurrentVersion()),
                'action' => 'show_whats_new',
                'icon' => 'question-circle',
                'primary' => false,
                'title' => __("View what's new in this version", 'notifal'),
            ];
        }

        // Changelog button: opens changelog popup (separate from What's New popup)
        $items[] = [
            'type' => 'button',
            'text' => __('Changelog', 'notifal'),
            'action' => 'show_changelog',
            'icon' => 'clock-history',
            'primary' => false,
            'title' => __('View plugin changelog by version', 'notifal'),
        ];

        return $items;
    }

    /**
     * Check if the current plugin version has what's new content to show
     *
     * When there is no update/changelog popup for the current version, the sticky
     * header should not display the What's New button.
     *
     * @return bool True if current version has what's new popup content
     * @since 2.0.0
     */
    private function hasWhatsNewContentForCurrentVersion(): bool
    {
        $whatsnew = new WhatsNewPopup();
        $config = $whatsnew->getVersionConfig();

        return isset($config['show_popup']) && $config['show_popup'] === true;
    }

    /**
     * Check if a menu item should be marked as active (current page)
     *
     * @param array $item Menu item configuration
     * @return bool True if item should be active
     * @since 2.0.0
     */
    public function isMenuItemActive(array $item): bool
    {
        // Skip if item is external or not a link
        if (isset($item['external']) && $item['external']) {
            return false;
        }

        if (!isset($item['url']) || !isset($item['type'])) {
            return false;
        }

        // Get current page
        $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $current_post_type = isset($_GET['post_type']) ? sanitize_text_field(wp_unslash($_GET['post_type'])) : '';

        // Check based on item type and URL
        switch ($item['type']) {
            case 'icon_link':
                // For icon links, check post type in URL
                if (strpos($item['url'], 'post_type=') !== false) {
                    preg_match('/post_type=([^&]+)/', $item['url'], $matches);
                    if (!empty($matches[1]) && $matches[1] === $current_post_type) {
                        return true;
                    }
                }
                break;

            case 'link':
                // For regular links, check page parameter in URL
                if (strpos($item['url'], 'page=') !== false) {
                    preg_match('/page=([^&]+)/', $item['url'], $matches);
                    if (!empty($matches[1]) && $matches[1] === $current_page) {
                        return true;
                    }
                }
                break;
        }

        return false;
    }

    /**
     * Get the Notifal logo SVG
     *
     * @return string SVG markup
     * @since 2.0.0
     */
    private function getLogoSvg(): string
    {
        return NotifalLogoService::getCompactLogo();
    }
}
