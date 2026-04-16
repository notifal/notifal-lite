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

use Notifal\Domain\Settings\Constants\Urls;

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
        return defined('NOTIFAL_VERSION') ? NOTIFAL_VERSION : '2.2.3';
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
     * @return array<string, array> Map of version => config
     * @since 2.0.0
     */
    private function getAllVersionsConfig(): array
    {
        $current_version = $this->getCurrentVersion();

        return [
            '2.2.3' => [
                'show_popup' => true,
                'is_important' => false,
                'title' => sprintf(__("What's New in %s", 'notifal'), '2.2.3'),
                'content' => $this->getVersion223Content(),
                'action_buttons' => [],
            ],
            '2.2.2' => [
                'show_popup' => false,
                'is_important' => false,
                'title' => sprintf(__("What's New in %s", 'notifal'), '2.2.2'),
                'content' => $this->getVersion222Content(),
                'action_buttons' => [],
            ],
            '2.2.1' => [
                'show_popup' => false,
                'is_important' => false,
                'title' => sprintf(__("What's New in %s", 'notifal'), '2.2.1'),
                'content' => $this->getVersion221Content(),
                'action_buttons' => [],
            ],
            '2.2.0' => [
                'show_popup' => true,
                'is_important' => false,
                'title' => sprintf(__("What's New in %s", 'notifal'), '2.2.0'),
                'content' => $this->getVersion220Content(),
                'action_buttons' => [],
            ],
            '2.1.5' => [
                'show_popup' => true,
                'is_important' => false,
                'title' => sprintf(__("What's New in %s", 'notifal'), '2.1.5'),
                'content' => $this->getVersion215Content(),
                'action_buttons' => [],
            ],
            '2.1.1' => [
                'show_popup' => true,
                'is_important' => false,
                'title' => sprintf(__("What's New in %s", 'notifal'), '2.1.1'),
                'content' => $this->getVersion211Content(),
                'action_buttons' => [],
            ],
            '2.1.0' => [
                'show_popup' => true,
                'is_important' => false,
                'title' => sprintf(__("What's New in %s", 'notifal'), '2.1.0'),
                'content' => $this->getVersion210Content(),
                'action_buttons' => [],
            ],
            '2.0.2' => [
                'show_popup' => true,
                'is_important' => false,
                'title' => sprintf(__("What's New in %s", 'notifal'), '2.0.2'),
                'content' => $this->getVersion202Content(),
                'action_buttons' => [],
            ],
            '2.0.1' => [
                'show_popup' => true,
                'is_important' => false,
                'title' => sprintf(__("What's New in %s", 'notifal'), '2.0.1'),
                'content' => $this->getVersion201Content(),
                'action_buttons' => [],
            ],
            '2.0.0' => [
                'show_popup' => true,
                'is_important' => true,
                'title' => '🎉 ' . __('Notifal 2.0.0 - Complete Transformation!', 'notifal'),
                'content' => $this->getVersion200Content(),
                'action_buttons' => [
                    [
                        'id' => 'learn-fake-sales',
                        'text' => __('Why Fake Sales Hurt Brands', 'notifal'),
                        'url' => Urls::BLOG_FAKE_SALES,
                        'icon' => 'dashicons-external',
                        'external' => true,
                        'primary' => true,
                    ],
                    [
                        'id' => 'view-changelog',
                        'text' => __('View Changelog', 'notifal'),
                        'url' => Urls::withPluginUtm(Urls::CHANGELOG, 'whatsnew_popup', 'changelog'),
                        'icon' => 'dashicons-list-view',
                        'external' => true,
                    ],
                    [
                        'id' => 'got-it',
                        'text' => __('Got it', 'notifal'),
                        'url' => '#',
                        'icon' => 'dashicons-yes',
                        'close' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * Get list of versions that have changelog content (newest first).
     *
     * Used by the Changelog popup to build the version selector.
     *
     * @return string[] Version strings, e.g. ['2.0.1', '2.0.0']
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
     * @param string $version Version string, e.g. '2.0.0'
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
     * Get content for version 2.0.0
     *
     * @return string HTML content for version 2.0.0
     * @since 2.0.0
     */
    private function getVersion200Content(): string
    {
        ob_start();
        ?>
        <div class="notifal-whatsnew-content">
            <div class="notifal-whatsnew-intro">
                <p class="notifal-whatsnew-lead">
                    <?php esc_html_e('Welcome to Notifal 2.0.0 - A Complete Transformation!', 'notifal'); ?>
                </p>
                <p>
                    <?php esc_html_e("We've completely rebuilt Notifal from the ground up with enhanced on-page notifications, advanced analytics, powerful template builder, and an improved tags system. This version brings you a more professional and effective way to engage your visitors.", 'notifal'); ?>
                </p>
            </div>

            <div class="notifal-whatsnew-section notifal-whatsnew-highlight">
                <h3><?php echo '🚫 ' . esc_html( __( 'Important: Fake Sales Notifications Removed', 'notifal' ) ); ?></h3>
                <p><?php esc_html_e("We've removed the fake sales notification feature to focus on genuine, trust-building notifications that actually help your business grow. While many users found this feature useful, fake notifications can damage your brand credibility and hurt long-term customer relationships.", 'notifal'); ?></p>

                <div class="notifal-whatsnew-callout">
                    <p><strong><?php esc_html_e("Don't worry!", 'notifal'); ?></strong> <?php esc_html_e('Notifal now offers powerful alternatives that deliver real results:', 'notifal'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Real-time social proof from actual customer activities', 'notifal'); ?></li>
                        <li><?php esc_html_e('Engagement notifications that encourage interaction', 'notifal'); ?></li>
                        <li><?php esc_html_e('Conversion-focused alerts that drive sales', 'notifal'); ?></li>
                        <li><?php esc_html_e('Custom notifications tailored to your business needs', 'notifal'); ?></li>
                    </ul>
                </div>

                <div class="notifal-whatsnew-callout notifal-whatsnew-support">
                    <h4><?php echo '🛟 ' . esc_html( __( 'Need Help Transitioning?', 'notifal' ) ); ?></h4>
                    <p><?php esc_html_e('If you want to replace your fake sales notifications with effective alternatives, our expert team is here to help you!', 'notifal'); ?></p>
                    <div class="notifal-support-benefits">
                        <p><strong><?php esc_html_e('Get FREE support:', 'notifal'); ?></strong></p>
                        <ul>
                            <li><?php esc_html_e('Personal website review and analysis', 'notifal'); ?></li>
                            <li><?php esc_html_e('Custom notification recommendations for your business', 'notifal'); ?></li>
                            <li><?php esc_html_e('Free setup and configuration of new notifications', 'notifal'); ?></li>
                        </ul>
                    </div>
                    <p>
                        <a target="_blank" href="<?php echo esc_url(Urls::SUPPORT_PAGE); ?>"
                           class="notifal-whatsnew-link notifal-support-button">
                            <?php esc_html_e('Open Support Ticket →', 'notifal'); ?>
                        </a>
                    </p>
                </div>

                <p>
                    <a href="<?php echo esc_url(Urls::BLOG_FAKE_SALES); ?>"
                       target="_blank"
                       class="notifal-whatsnew-link notifal-whatsnew-external-link">
                        <?php esc_html_e('Learn why fake notifications hurt brands →', 'notifal'); ?>
                    </a>
                </p>
            </div>

            <div class="notifal-whatsnew-section">
                <h3><?php echo '✨ ' . esc_html( __( "What's New in 2.0.0", 'notifal' ) ); ?></h3>
                <div class="notifal-whatsnew-features">
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">📢</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Enhanced On-Page Notifications', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Advanced customization options and smart targeting to maximize engagement.', 'notifal'); ?></p>
                        </div>
                    </div>

                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">📊</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Powerful Analytics', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Deep insights into notification performance and visitor behavior.', 'notifal'); ?></p>
                        </div>
                    </div>

                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🎨</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Advanced Template Builder', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Create stunning notifications with our drag-and-drop builder.', 'notifal'); ?></p>
                        </div>
                    </div>

                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🏷️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Enhanced Tags System', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Better organization and smarter notification targeting.', 'notifal'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="notifal-whatsnew-section notifal-whatsnew-ready">
                <h3><?php echo '🎯 ' . esc_html( __( 'Ready to Build Trust and Boost Conversions?', 'notifal' ) ); ?></h3>
                <p><?php esc_html_e("Explore your new Notifal dashboard and discover how real, authentic notifications can transform your website's performance. Your customers will thank you for the genuine engagement!", 'notifal'); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get content for version 2.1.0
     *
     * @return string HTML content for version 2.1.0
     * @since 2.1.0
     */
    private function getVersion210Content(): string
    {
        ob_start();
        ?>
        <div class="notifal-whatsnew-content">
            <div class="notifal-whatsnew-section">
                <h3><?php echo '✨ ' . esc_html(__("What's New in 2.1.0", 'notifal')); ?></h3>
                <div class="notifal-whatsnew-features">
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">👁️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Preview on-page notifications before publishing', 'notifal'); ?></h4>
                            <p><?php esc_html_e('See how your notification will look on your live site before you publish it. Use the Preview button on the edit page or from the notifications list to open a preview on your site, so you can fine-tune design and placement with confidence.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🔍</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Search for notifications', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Search has been added to Explore Pre-created Notifications so you can find templates quickly.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">✨</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Appearance improvements', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Explore Pre-created Notifications has been updated with appearance improvements for a better experience.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🖼️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Smarter featured images', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Featured Image Auto source now follows your template tags consistently in both the Block Editor and Elementor.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🎯</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('More precise URL targeting [PRO]', 'notifal'); ?></h4>
                            <p><?php esc_html_e('URL targeting options in Pro now make it easier to show different campaigns and offers to visitors based on how they arrived on your site.', 'notifal'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get content for version 2.1.5
     *
     * @return string HTML content for version 2.1.5
     * @since 2.1.5
     */
    private function getVersion215Content(): string
    {
        ob_start();
        ?>
        <div class="notifal-whatsnew-content">
            <div class="notifal-whatsnew-section">
                <h3><?php echo '✨ ' . esc_html(__("What's New in 2.1.5", 'notifal')); ?></h3>
                <div class="notifal-whatsnew-features">
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🛒</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Ajax Add to Cart (WooCommerce)', 'notifal'); ?></h4>
                            <p><?php esc_html_e('When WooCommerce is active, the Action Button now supports an "Ajax Add to Cart" link type. It adds the notification\'s product to the cart with a smoother, more modern experience.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">⏳</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Friendlier loading state for Action Button', 'notifal'); ?></h4>
                            <p><?php esc_html_e('For Post Link and Custom Link actions, you can choose the text shown on the button while the page is changing, or keep it quiet and let the redirect happen after a short moment.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">⚡</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Smoother behavior on cached sites', 'notifal'); ?></h4>
                            <p><?php esc_html_e('On-page notifications have been adjusted to work more comfortably alongside page caching, so visitors still see your messages without affecting site performance.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">📱</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Previews that match the live site', 'notifal'); ?></h4>
                            <p><?php esc_html_e('On-page notification previews now respect device-specific placement for mobile and tablet, helping you trust that what you see in preview is what visitors will see.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🔁</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('More natural repeats when notifications appear again', 'notifal'); ?></h4>
                            <p><?php esc_html_e('When a notification is allowed to show again after being closed, its content is now refreshed more reliably so visitors are less likely to see the same item over and over, even on sites that use caching or offline support.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🧹</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Automatic cache clearing for on-page notifications', 'notifal'); ?></h4>
                            <p><?php esc_html_e('On-page notification caches are now cleared automatically when you save a notification, change its status from the list, or activate/update the plugin, so your latest changes appear on the frontend without extra manual refresh steps.', 'notifal'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get content for version 2.2.0
     *
     * @return string HTML content for version 2.2.0
     * @since 2.2.0
     */
    private function getVersion220Content(): string
    {
        ob_start();
        ?>
        <div class="notifal-whatsnew-content">
            <div class="notifal-whatsnew-section">
                <h3><?php echo '✨ ' . esc_html(__("What's New in 2.2.0", 'notifal')); ?></h3>
                <div class="notifal-whatsnew-features">
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">📅</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Campaign Manager', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Create campaigns with their own start and end dates, assign on-page notifications to a campaign, and let one schedule keep every related message aligned — no need to duplicate dates on each notification.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">📊</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Campaign Analytics Filters (Pro)', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Available in Notifal Pro v2.1.0: see analytics (impressions, clicks, close rate, conversions, users) per campaign using event attribution for campaigns.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">⏱️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Notification scheduling (start/end time)', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Control when a notification becomes active and when it stops showing by using Start and End date/time fields. When a notification is not assigned to a campaign, these settings drive visibility.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🖱️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Close after an action button', 'notifal'); ?></h4>
                            <p><?php esc_html_e('In Behavior settings you can close the notification when someone clicks a template action button, and set how many seconds to wait before it closes.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">📊</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Clearer close numbers (Pro)', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Analytics now counts a close only when a visitor actually dismisses the notification — not when it hides on its own, after a form, or from an automatic close.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🛒</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('WooCommerce', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Fixed an issue with Ajax Add to Cart in notifications.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🧩</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Sticky header compatibility', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Improved “Above header” floating bar placement on sticky header themes so it no longer leaves gaps or overlaps after scrolling back to the top.', 'notifal'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get content for version 2.2.1
     *
     * @return string HTML content for version 2.2.1
     * @since 2.2.1
     */
    private function getVersion221Content(): string
    {
        ob_start();
        ?>
        <div class="notifal-whatsnew-content">
            <div class="notifal-whatsnew-section">
                <h3><?php echo '✨ ' . esc_html(__("What's New in 2.2.1", 'notifal')); ?></h3>
                <div class="notifal-whatsnew-features">
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">💬</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Online chat system update', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Improved online chat system in plugin backend.', 'notifal'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get content for version 2.2.2
     *
     * @return string HTML content for version 2.2.2
     * @since 2.2.2
     */
    private function getVersion222Content(): string
    {
        ob_start();
        ?>
        <div class="notifal-whatsnew-content">
            <div class="notifal-whatsnew-section">
                <h3><?php echo '✨ ' . esc_html(__("What's New in 2.2.2", 'notifal')); ?></h3>
                <div class="notifal-whatsnew-features">
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🛠️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Reported issues improvements', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Some reported issues were fixed to improve overall stability and performance.', 'notifal'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get content for version 2.2.3
     *
     * @return string HTML content for version 2.2.3
     * @since 2.2.3
     */
    private function getVersion223Content(): string
    {
        ob_start();
        ?>
        <div class="notifal-whatsnew-content">
            <div class="notifal-whatsnew-section">
                <h3><?php echo '✨ ' . esc_html(__("What's New in 2.2.3", 'notifal')); ?></h3>
                <div class="notifal-whatsnew-features">
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🛠️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Frontend template rendering fix', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Elementor template widgets now render correctly on frontend on-page notifications.', 'notifal'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get content for version 2.1.1
     *
     * @return string HTML content for version 2.1.1
     * @since 2.1.1
     */
    private function getVersion211Content(): string
    {
        ob_start();
        ?>
        <div class="notifal-whatsnew-content">
            <div class="notifal-whatsnew-section">
                <h3><?php echo '✨ ' . esc_html(__("What's New in 2.1.1", 'notifal')); ?></h3>
                <div class="notifal-whatsnew-features">
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">📱</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('More accurate on-page notification preview', 'notifal'); ?></h4>
                            <p><?php esc_html_e('On-page notification preview now correctly respects device-specific position settings for mobile and tablet viewports so your preview matches the live frontend.', 'notifal'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get content for version 2.0.2
     *
     * @return string HTML content for version 2.0.2
     * @since 2.0.2
     */
    private function getVersion202Content(): string
    {
        ob_start();
        ?>
        <div class="notifal-whatsnew-content">
            <div class="notifal-whatsnew-section">
                <h3><?php echo '✨ ' . esc_html(__("What's New in 2.0.2", 'notifal')); ?></h3>
                <div class="notifal-whatsnew-features">
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🔊</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Audio Settings', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Default notification sounds in Appearance settings now work correctly.', 'notifal'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get content for version 2.0.1
     *
     * @return string HTML content for version 2.0.1
     * @since 2.0.1
     */
    private function getVersion201Content(): string
    {
        ob_start();
        ?>
        <div class="notifal-whatsnew-content">
            <div class="notifal-whatsnew-section">
                <h3><?php echo '✨ ' . esc_html(__("What's New in 2.0.1", 'notifal')); ?></h3>
                <div class="notifal-whatsnew-features">
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">📋</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Request Your Preferred Builder for Any Template', 'notifal'); ?></h4>
                            <p><?php esc_html_e('In Explore Pre-created Notifications, you can request an Elementor or Block Editor version for any template that does not yet support your preferred builder. Submit your request from the template details; we will create it and notify you when it is ready.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">⏱️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Cache Refresh Countdown', 'notifal'); ?></h4>
                            <p><?php esc_html_e('The pre-created notifications list now shows how long until the list refreshes, so you know when new or updated templates will appear.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">⚡</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Faster OnPage List Page', 'notifal'); ?></h4>
                            <p><?php esc_html_e('The pre-created notifications section now loads in the background so the page is not blocked; improved loading state and timeout handling.', 'notifal'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }


}
