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
        return defined('NOTIFAL_VERSION') ? NOTIFAL_VERSION : '2.0.0';
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
