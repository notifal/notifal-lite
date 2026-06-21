<?php
/**
 * What's New Popup Domain Logic
 *
 * Handles the core business logic for what's new popup functionality.
 *
 * @package Notifal\Infrastructure\WordPress\WhatsNewPopup\Domain
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\WhatsNewPopup\Domain;

defined('ABSPATH') || exit;

/**
 * Class WhatsNewPopup
 */
class WhatsNewPopup
{
    /**
     * Option key for tracking last shown version
     */
    const LAST_SHOWN_VERSION_KEY = 'notifal_whatsnew_last_shown_version';

    /**
     * Option key for tracking last popup shown timestamp
     */
    const LAST_SHOWN_TIME_KEY = 'notifal_whatsnew_last_shown_time';

    /**
     * Legacy option key from versions below 2.0.0
     */
    const LEGACY_PLUGIN_VERSION_KEY = 'notifal_plugin_version';

    /**
     * Check if what's new popup should be shown (auto-show once per version).
     *
     * Intended behavior:
     * - For each version with show_popup = true: auto-show once on first visit to a Notifal page
     *   after update (or on plugins.php if is_important = true). After that, user can still open
     *   "What's New" from the sticky menu.
     * - If is_important = true: popup is also rendered on plugins.php so it can show there after
     *   update; if user visits a Notifal page first without having seen it on plugins.php, it
     *   shows on that first Notifal visit. One dismissal marks the version as shown everywhere.
     *
     * Shows when:
     * - Current version has show_popup = true, AND
     * - Legacy upgrade (pre-2.0.0), OR last_shown is empty, OR current version > last_shown.
     *
     * @return bool True if popup should be shown
     * @since 2.0.0
     */
    public function shouldShowWhatsNewPopup(): bool
    {
        // Get current version
        $current_version = $this->getCurrentVersion();

        // Get last shown version
        $last_shown_version = $this->getLastShownVersion();

        // Current version must be configured to show a popup
        if (!$this->shouldShowForCurrentVersion()) {
            return false;
        }

        // Show popup for users upgrading from versions below 2.0.0
        // These users have 'LEGACY_PLUGIN_VERSION_KEY' option but no 'LAST_SHOWN_VERSION_KEY'
        $legacy_version = get_option(self::LEGACY_PLUGIN_VERSION_KEY);
        if (!empty($legacy_version) && empty($last_shown_version)) {
            return true;
        }

        // Never shown before: show current version popup (covers fresh install and upgrade without prior popup visit)
        if (empty($last_shown_version)) {
            return true;
        }

        // Show popup when current version is newer than last shown version
        if (version_compare($current_version, $last_shown_version, '>')) {
            return true;
        }

        return false;
    }

    /**
     * Check if current version should show the what's new popup
     *
     * @return bool True if popup should be shown for current version
     * @since 2.0.0
     */
    private function shouldShowForCurrentVersion(): bool
    {
        $version_config = $this->getVersionConfig();

        return isset($version_config['show_popup']) && $version_config['show_popup'] === true;
    }

    /**
     * Mark current version as shown
     *
     * @return void
     * @since 2.0.0
     */
    public function markCurrentVersionAsShown(): void
    {
        $current_version = $this->getCurrentVersion();

        update_option(self::LAST_SHOWN_VERSION_KEY, $current_version);
        update_option(self::LAST_SHOWN_TIME_KEY, time());
    }

    /**
     * Get current plugin version
     *
     * @return string Current plugin version
     * @since 2.0.0
     */
    public function getCurrentVersion(): string
    {
        return defined('NOTIFAL_VERSION') ? NOTIFAL_VERSION : '2.4.0';
    }

    /**
     * Get last shown version
     *
     * @return string|null Last shown version or null if never shown
     * @since 2.0.0
     */
    public function getLastShownVersion(): ?string
    {
        $version = get_option(self::LAST_SHOWN_VERSION_KEY);
        return $version ? (string) $version : null;
    }

    /**
     * Get last popup shown timestamp
     *
     * @return int|null Unix timestamp of last popup shown
     * @since 2.0.0
     */
    public function getLastShownTime(): ?int
    {
        $time = get_option(self::LAST_SHOWN_TIME_KEY);
        return $time ? (int) $time : null;
    }

    /**
     * Check if current version is marked as important update
     *
     * Important updates will show popup on plugins.php page in addition to Notifal pages.
     *
     * @return bool True if current version is important update
     * @since 2.0.0
     */
    public function isImportantUpdate(): bool
    {
        $version_config = $this->getVersionConfig();

        return isset($version_config['is_important']) && $version_config['is_important'] === true;
    }

    /**
     * Get version configuration including content and settings
     *
     * @return array Version configuration array
     * @since 2.0.0
     */
    public function getVersionConfig(): array
    {
        $current_version = $this->getCurrentVersion();
        $all = $this->getAllVersionsConfig();

        return $all[$current_version] ?? [
            'show_popup' => false,
            'is_important' => false,
            'title' => sprintf(__("What's New in %s", 'notifal'), $current_version),
            'content' => __('No update information available for this version.', 'notifal'),
            'action_buttons' => [],
        ];
    }

    /**
     * Get all version configs (used by getVersionConfig and changelog popup).
     *
     * Only the latest four releases are kept in-plugin; older entries live on notifal.com/changelog/.
     *
     * @return array<string, array> Map of version => config
     * @since 2.0.0
     */
    private function getAllVersionsConfig(): array
    {
        return [
            '2.4.0' => [
                'show_popup' => true,
                'is_important' => false,
                'title' => sprintf(__("What's New in %s", 'notifal'), '2.4.0'),
                'content' => $this->getVersion240Content(),
                'action_buttons' => [],
            ],
            '2.3.12' => [
                'show_popup' => true,
                'is_important' => false,
                'title' => sprintf(__("What's New in %s", 'notifal'), '2.3.12'),
                'content' => $this->getVersion2312Content(),
                'action_buttons' => [],
            ],
            '2.3.11' => [
                'show_popup' => true,
                'is_important' => false,
                'title' => sprintf(__("What's New in %s", 'notifal'), '2.3.11'),
                'content' => $this->getVersion2311Content(),
                'action_buttons' => [],
            ],
            '2.3.10' => [
                'show_popup' => true,
                'is_important' => false,
                'title' => sprintf(__("What's New in %s", 'notifal'), '2.3.10'),
                'content' => $this->getVersion2310Content(),
                'action_buttons' => [],
            ],
        ];
    }

    /**
     * Get list of versions that have changelog content (newest first).
     *
     * Used by the Changelog popup to build the version selector.
     *
     * @return string[] Version strings, e.g. ['2.3.11', '2.3.10']
     * @since 2.0.0
     */
    public function getAvailableChangelogVersions(): array
    {
        $versions = array_keys($this->getAllVersionsConfig());
        usort($versions, 'version_compare');

        return array_reverse($versions);
    }

    /**
     * Get title and content for a specific version (for Changelog popup).
     *
     * @param string $version Version string, e.g. '2.3.11'
     * @return array{title: string, content: string}
     * @since 2.0.0
     */
    public function getChangelogContentForVersion(string $version): array
    {
        $all = $this->getAllVersionsConfig();
        $default_content = __('No update information available for this version.', 'notifal');

        if (!isset($all[$version])) {
            return [
                'title' => sprintf(__("What's New in %s", 'notifal'), $version),
                'content' => $default_content,
            ];
        }

        $config = $all[$version];

        return [
            'title' => $config['title'],
            'content' => $config['content'],
        ];
    }

    /**
     * Get content for version 2.4.0
     *
     * @return string HTML content for version 2.4.0
     * @since 2.4.0
     */
    private function getVersion240Content(): string
    {
        ob_start();
        ?>
        <div class="notifal-whatsnew-content">
            <div class="notifal-whatsnew-section">
                <h3><?php echo '✨ ' . esc_html(__("What's New in 2.4.0", 'notifal')); ?></h3>
                <div class="notifal-whatsnew-features">
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🧱</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('HTML Builder', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Create templates by pasting self-contained HTML from AI tools or any source, no Elementor or Gutenberg required. Edit extracted text and colors, insert Notifal tags, and preview with the full frontend pipeline.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">⏱️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('More reliable timed notifications', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Delay, exit intent, scroll, and other timed popups now work reliably on more WordPress sites, including setups where the active theme affects how notification data is loaded.', 'notifal'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Get content for version 2.3.12
     *
     * @return string HTML content for version 2.3.12
     * @since 2.3.12
     */
    private function getVersion2312Content(): string
    {
        ob_start();
        ?>
        <div class="notifal-whatsnew-content">
            <div class="notifal-whatsnew-section">
                <h3><?php echo '✨ ' . esc_html(__("What's New in 2.3.12", 'notifal')); ?></h3>
                <div class="notifal-whatsnew-features">
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🧩</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Class-based template placeholders', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Build templates in Elementor or the Block Editor without Notifal widgets or blocks. Add HTML with special classes and get the same behavior as the official components — featured image, close button, and action buttons.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🖼️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Featured image via class', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Use notifal-post-feature-image on any element to render the context-aware featured image — same resolver as the Product Image widget and block. Optional data-preview-image-source, data-image-resolution, and data-image-lazy-load attributes are supported.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">✕</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Close button via class', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Add notifal-close-button to your own button or link to dismiss the notification and record close analytics — no Close Icon widget required.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🖱️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Action buttons via class', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Use notifal-action-button on custom HTML elements for full action button behavior: post/product links, copy, close, Ajax add to cart, custom URLs, and custom triggers. Set the type with data-notifal-action — tracking and Pro per-button analytics work the same as widgets.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🔒</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Widget and block output unchanged', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Existing Elementor widgets and Block Editor blocks are not modified. Class placeholders are processed only on custom HTML elements, so current templates keep working exactly as before.', 'notifal'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get content for version 2.3.11
     *
     * @return string HTML content for version 2.3.11
     * @since 2.3.11
     */
    private function getVersion2311Content(): string
    {
        ob_start();
        ?>
        <div class="notifal-whatsnew-content">
            <div class="notifal-whatsnew-section">
                <h3><?php echo '✨ ' . esc_html(__("What's New in 2.3.11", 'notifal')); ?></h3>
                <div class="notifal-whatsnew-features">
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🖱️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Per-button click tracking', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Action button clicks now record which button was clicked — including button label, action type (post link, copy, custom, and more), and a stable tracking ID for analytics.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">📊</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Button click breakdown in Pro analytics (Pro)', 'notifal'); ?></h4>
                            <p><?php esc_html_e('In Notifal Pro, open OnPage Analytics and click the Clicks column for a notification to see a breakdown modal — which buttons received clicks, how many, and their action types. Load more rows when templates have many buttons.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🧩</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Stable IDs in Block Editor and Elementor', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Block Editor action buttons persist a trackingId attribute when inserted. Elementor action buttons use a widget-based tracking ID so the same button is counted consistently across page loads.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🗄️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Database support for button stats', 'notifal'); ?></h4>
                            <p><?php esc_html_e('A new analytics table stores per-button click aggregates. The event queue also accepts optional button metadata so Pro can aggregate clicks without extra frontend requests.', 'notifal'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get content for version 2.3.10
     *
     * @return string HTML content for version 2.3.10
     * @since 2.3.10
     */
    private function getVersion2310Content(): string
    {
        ob_start();
        ?>
        <div class="notifal-whatsnew-content">
            <div class="notifal-whatsnew-section">
                <h3><?php echo '✨ ' . esc_html(__("What's New in 2.3.10", 'notifal')); ?></h3>
                <div class="notifal-whatsnew-features">
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🔁</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Smarter return visitor targeting', 'notifal'); ?></h4>
                            <p><?php esc_html_e('The Return visitor visit-history filter now detects when someone comes back after being away, not simply after page two of the same visit. Browsing multiple pages in one active session no longer triggers return-visitor notifications.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">⏱️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Configurable inactivity threshold', 'notifal'); ?></h4>
                            <p><?php esc_html_e('When Return visitor is selected in Users display rules, set Inactivity before return (hours). Default is 3 hours. Use 24 for next-day returns or 48 for two-day returns. Any page view or interaction resets the timer.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">👥</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('All Users login status (Pro)', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Users display rules now include All Users as the default login status, so notifications can target both guests and logged-in visitors. Optional visit-history filters (new visitor, return visitor, first session) still apply alongside login targeting.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">⚡</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Cache-safe client-side evaluation', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Return visitor checks still run entirely in the browser using activity timestamps, so full-page cache stays safe while re-engagement campaigns target visitors who were genuinely inactive.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🏷️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Better variable product sale alerts', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Notifications now pick up variable products when any variation is on sale, including Sale Products Only filters and product-page targeting.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🛍️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Clearer variable product details in notifications', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Show the on-sale variation name, price, and link in your message. Total sales still reflect the whole product. Post Link and Add to Cart take shoppers straight to that variation.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">📍</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('More reliable page targeting on exit intent', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Page targeting display rules are now re-checked in the browser before a notification shows, including exit-intent triggers. A cart-based popup limited to Cart and Checkout no longer appears on Shop, Home, or other pages when the cart still has items.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">📚</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Z-Index setting works as expected', 'notifal'); ?></h4>
                            <p><?php esc_html_e('The Z-Index value you set in Appearance is now respected. When two notifications overlap, the one with the higher number appears on top.', 'notifal'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get content for version 2.3.9
     *
     * @return string HTML content for version 2.3.9
     * @since 2.3.9
     */
    private function getVersion239Content(): string
    {
        ob_start();
        ?>
        <div class="notifal-whatsnew-content">
            <div class="notifal-whatsnew-section">
                <h3><?php echo '✨ ' . esc_html(__("What's New in 2.3.9", 'notifal')); ?></h3>
                <div class="notifal-whatsnew-features">
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🛒</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Cart Products content source', 'notifal'); ?></h4>
                            <p><?php esc_html_e('A new Cart Products filter in Product Restrictions (WooCommerce only) lets dynamic notifications pull product content from the visitor\'s live cart instead of a static catalog pool.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🔗</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Cart, related, upsell, and cross-sell sources', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Choose which cart-derived products feed your notification: cart line items, related products, upsells, or cross-sells. Toggles inside one Cart Products filter combine with OR logic and still respect your existing Product Restriction AND/OR rules.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">⚡</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Live cart updates on the storefront', 'notifal'); ?></h4>
                            <p><?php esc_html_e('When visitors add or remove items via Ajax, the cart product pool refreshes automatically so on-page notifications can show updated cart-based products without a full page reload.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">💰</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Clearer clicked and influenced revenue', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Post Link and Ajax Add to Cart now attribute clicked revenue from the matched product line subtotal while influenced revenue reflects the full order total on the same conversion. Post Link redirects store attribution before navigation, variable products match parent clicks, and the attribution window is 24 hours.', 'notifal'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

}
