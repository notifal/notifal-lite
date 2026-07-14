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
        return defined('NOTIFAL_VERSION') ? NOTIFAL_VERSION : '2.4.4';
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
            '2.4.4' => [
                'show_popup' => true,
                'is_important' => false,
                'title' => sprintf(__("What's New in %s", 'notifal'), '2.4.4'),
                'content' => $this->getVersion244Content(),
                'action_buttons' => [],
            ],
            '2.4.3' => [
                'show_popup' => true,
                'is_important' => false,
                'title' => sprintf(__("What's New in %s", 'notifal'), '2.4.3'),
                'content' => $this->getVersion243Content(),
                'action_buttons' => [],
            ],
            '2.4.2' => [
                'show_popup' => true,
                'is_important' => false,
                'title' => sprintf(__("What's New in %s", 'notifal'), '2.4.2'),
                'content' => $this->getVersion242Content(),
                'action_buttons' => [],
            ],
            '2.4.1' => [
                'show_popup' => true,
                'is_important' => false,
                'title' => sprintf(__("What's New in %s", 'notifal'), '2.4.1'),
                'content' => $this->getVersion241Content(),
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
     * Get content for version 2.4.4
     *
     * @return string HTML content for version 2.4.4
     * @since 2.4.4
     */
    private function getVersion244Content(): string
    {
        ob_start();
        ?>
        <div class="notifal-whatsnew-content">
            <div class="notifal-whatsnew-section">
                <h3><?php echo '✨ ' . esc_html(__("What's New in 2.4.4", 'notifal')); ?></h3>
                <div class="notifal-whatsnew-features">
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🧩</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('HTML Builder style attribute updates', 'notifal'); ?></h4>
                            <p><?php esc_html_e('When you edit an element in the HTML tab and remove CSS from the style attribute, Update HTML now keeps that change. Styles from class or style-tag rules stay in the stylesheet instead of being copied back as inline styles.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">📄</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Readable HTML Source modal', 'notifal'); ?></h4>
                            <p><?php esc_html_e('The HTML Source popup now opens with indented HTML and formatted CSS in style tags, so you can read and edit the markup like in a code editor instead of one long minified line.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🔍</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Search in HTML Source', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Find text in the HTML Source modal with highlighted matches, automatic scroll to the first result, and previous/next arrows to move between matches when you need to tweak the markup.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">✏️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Readable Properties HTML tab', 'notifal'); ?></h4>
                            <p><?php esc_html_e('The Properties panel HTML tab now shows indented element markup and includes the same search with previous/next navigation, so editing a selected element is easier for non-technical users.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">⏱️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Notifal countdown timers', 'notifal'); ?></h4>
                            <p><?php esc_html_e('AI prompts for HTML Builder and Generate with AI now ask for the notifal-countdown structure when a timer is needed. Select the timer in the builder to set minutes, seconds, and what happens when it ends (keep 00:00, hide the timer, or close the notification). Notifal runs the countdown when the notification is shown.', 'notifal'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Get content for version 2.4.3
     *
     * @return string HTML content for version 2.4.3
     * @since 2.4.3
     */
    private function getVersion243Content(): string
    {
        ob_start();
        ?>
        <div class="notifal-whatsnew-content">
            <div class="notifal-whatsnew-section">
                <h3><?php echo '✨ ' . esc_html(__("What's New in 2.4.3", 'notifal')); ?></h3>
                <div class="notifal-whatsnew-features">
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">📥</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('HTML Builder pre-created templates', 'notifal'); ?></h4>
                            <p><?php esc_html_e('The pre-created notification library now supports HTML Builder. When a marketplace template includes an HTML Builder export, you can import it directly from the notification details popup alongside Elementor and Block Editor.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">📌</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Minimum Notifal version checks for pre-created templates', 'notifal'); ?></h4>
                            <p><?php esc_html_e('When a marketplace template requires a newer Notifal version, the archive card and details popup now tell you before import. Import buttons stay disabled until you update Notifal from Plugins.', 'notifal'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Get content for version 2.4.2
     *
     * @return string HTML content for version 2.4.2
     * @since 2.4.2
     */
    private function getVersion242Content(): string
    {
        ob_start();
        ?>
        <div class="notifal-whatsnew-content">
            <div class="notifal-whatsnew-section">
                <h3><?php echo '✨ ' . esc_html(__("What's New in 2.4.2", 'notifal')); ?></h3>
                <div class="notifal-whatsnew-features">
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🤖</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Smarter AI prompts for action buttons', 'notifal'); ?></h4>
                            <p><?php esc_html_e('HTML Builder and Generate with AI on the OnPage list now include full class-placeholder documentation. AI-generated templates should output working copy buttons (data-notifal-action="copy" plus data-copy-text) and Ajax add to cart buttons (data-notifal-action="ajax-add-to-cart" with quantity, redirect, and product attributes) instead of plain notifal-action-button markup.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">📋</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Clearer CTA behavior rules for AI', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Prompts now tell external AI tools when to use copy coupon, add to cart in place, or visit product page CTAs, so coupon popups, cart nudges, and shop buttons match the right Notifal action type.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">📖</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Expanded class-placeholder reference', 'notifal'); ?></h4>
                            <p><?php esc_html_e('TEMPLATE_CLASS_PLACEHOLDERS.md now lists required and optional data attributes for featured image, close button, copy, Ajax add to cart, custom URL, and custom trigger placeholders.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">📱</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Custom CSS @media queries work again', 'notifal'); ?></h4>
                            <p><?php esc_html_e('OnPage Custom CSS validation now accepts @media blocks when inner rules use the notification ID or class prefix, so responsive styles like mobile width no longer show false validation errors.', 'notifal'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Get content for version 2.4.1
     *
     * @return string HTML content for version 2.4.1
     * @since 2.4.1
     */
    private function getVersion241Content(): string
    {
        ob_start();
        ?>
        <div class="notifal-whatsnew-content">
            <div class="notifal-whatsnew-section">
                <h3><?php echo '✨ ' . esc_html(__("What's New in 2.4.1", 'notifal')); ?></h3>
                <div class="notifal-whatsnew-features">
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🧩</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Generate full notifications with AI', 'notifal'); ?></h4>
                            <p><?php esc_html_e('On the OnPage Notifications list, use Generate with AI to copy a prompt that asks external AI tools for a complete Notifal import JSON, HTML template plus appearance, timing, behavior, and display rules. Open Import to paste the JSON and create a ready-to-review draft in one step.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">📋</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Paste JSON when importing notifications', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Import OnPage Notifications from a file or by pasting exported JSON directly. A security notice and trusted-source confirmation help keep imports safe; pasted JSON is validated server-side and saved as disabled drafts.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">💰</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Accurate clicked revenue on orders', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Order attribution now shows Total clicked revenue as the sum of each clicked product line subtotal (price × quantity) when clicked products are listed on the order.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🛍️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Better variable product revenue matching', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Clicked revenue backfill and pending checkout snapshots now resolve parent and variation product IDs against WooCommerce order line items, so revenue is counted correctly for variable products.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">⏳</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Delayed payment attribution', 'notifal'); ?></h4>
                            <p><?php esc_html_e('When payment completes after on-hold or pending status, clicked revenue is still recorded if product clicks were validated at checkout, even when live click lookup no longer matches.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🛠️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('HTML Builder improvements', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Smaller boot preloader, a clearer new-template welcome flow (paste HTML or generate with AI), link URL editing for anchors, and richer AI prompt fields with field-filling patterns.', 'notifal'); ?></p>
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

}
