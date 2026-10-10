<?php
/**
 * What's New Popup Domain Logic
 *
 * Handles the core business logic for what's new popup functionality.
 *
 * @package Notifal\Infrastructure\WordPress\WhatsNewPopup\Domain
 * @since 2.0.0
 * @updated 3.0.0 Keep latest What's New entries including Setup Wizard site readiness.
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\WhatsNewPopup\Domain;

use Notifal\Modules\SetupWizard\Application\Services\SetupWizardEligibilityService;
use Notifal\Modules\SetupWizard\Application\Services\SetupWizardStateService;
use Notifal\Modules\SetupWizard\Domain\Constants\SetupWizardOptions;

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
     * - The site is upgrading (not a fresh install), AND
     * - Legacy upgrade (pre-2.0.0), OR last_shown is empty on an existing site, OR current version > last_shown.
     *
     * Fresh installs seed last_shown during Setup Wizard activation so they never auto-see the install version popup.
     *
     * @return bool True if popup should be shown
     * @since 2.0.0
     */
    public function shouldShowWhatsNewPopup(): bool
    {
        // Defer What's New while the first-run Setup Wizard is still active.
        $eligibilityService = new SetupWizardEligibilityService(new SetupWizardStateService());
        if ($eligibilityService->isFirstRunWizardActive()) {
            return false;
        }

        // Get current version
        $current_version = $this->getCurrentVersion();

        // Get last shown version
        $last_shown_version = $this->getLastShownVersion();

        // Current version must be configured to show a popup
        if (!$this->shouldShowForCurrentVersion()) {
            return false;
        }

        // Show popup for users upgrading from versions below 2.0.0.
        $legacy_version = get_option(self::LEGACY_PLUGIN_VERSION_KEY);
        if (!empty($legacy_version) && empty($last_shown_version)) {
            return true;
        }

        // Fresh installs keep an empty last_shown record until seeded during activation.
        if (empty($last_shown_version)) {
            return $this->shouldShowForExistingSiteWithoutWhatsNewRecord();
        }

        // Show popup once when the plugin was updated to a newer configured version.
        return version_compare($current_version, $last_shown_version, '>');
    }

    /**
     * Return true when an existing site has never recorded a What's New version yet.
     *
     * @since 3.0.0
     * @return bool
     */
    private function shouldShowForExistingSiteWithoutWhatsNewRecord(): bool
    {
        // Legacy pre-2.0 upgrades always qualify for the first What's New popup.
        if (get_option(self::LEGACY_PLUGIN_VERSION_KEY, false)) {
            return true;
        }
        // Existing sites upgrading to 3.0.0 without a prior What's New record.
        $firstInstall = get_option(SetupWizardOptions::FIRST_INSTALL, false);
        $isEligibleForWizard = (bool) get_option(SetupWizardOptions::ELIGIBLE, false);
        return (bool) $firstInstall && !$isEligibleForWizard;
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
        return defined('NOTIFAL_VERSION') ? NOTIFAL_VERSION : '3.0.0';
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
            '3.0.0' => [
                'show_popup' => true,
                'is_important' => false,
                'title' => sprintf(__("What's New in %s", 'notifal'), '3.0.0'),
                'content' => $this->getVersion300Content(),
                'action_buttons' => [],
            ],
            '2.4.5' => [
                'show_popup' => true,
                'is_important' => false,
                'title' => sprintf(__("What's New in %s", 'notifal'), '2.4.5'),
                'content' => $this->getVersion245Content(),
                'action_buttons' => [],
            ],
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
     * Get content for version 3.0.0
     *
     * @return string HTML content for version 3.0.0
     * @since 3.0.0
     * @updated 3.0.0 Include Setup Wizard onboarding and site readiness in the 3.0.0 What's New entry.
     */
    private function getVersion300Content(): string
    {
        ob_start();
        ?>
        <div class="notifal-whatsnew-content">
            <div class="notifal-whatsnew-section">
                <h3><?php echo '✨ ' . esc_html(__("What's New in 3.0.0", 'notifal')); ?></h3>
                <div class="notifal-whatsnew-features">
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🧭</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Setup Wizard with Noti', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Activate and Noti analyzes your site, shows industry-matched suggestion cards, connects your account, and builds notification drafts. Domain package preflight reuses cached analysis when available.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🤖</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Hosted Notifal AI generation', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Generate OnPage notifications and HTML Builder templates with Notifal AI. Connect your account with email verification, then create drafts from a guided wizard inside WordPress.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">✏️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('HTML Builder: create or edit with AI', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Choose to generate a new template or edit the current one. Edit mode shows a before/after preview so you can apply changes only when you are happy.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🧩</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('HTML Builder widgets', 'notifal'); ?></h4>
                            <p><?php esc_html_e('The left panel now has Structure and Widgets tabs. Insert heading, paragraph, image, featured image, button, close button, list, countdown, divider, and spacer with one click or drag them onto the tree (before, inside, or after).', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">↕️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Reorder Structure with drag and drop', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Drag any Structure row to move or nest it. Insertion lines show before/after placement, and an Inside badge highlights when you are nesting into a container. Press Delete or Backspace to remove the selected element.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🎨</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Smarter Style and Content editing', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Background color now reads CSS shorthand and can replace gradients with a solid color. Typography works on text inside layout containers, and paragraphs with nested bold or merge-tag markup stay editable in Content.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">⚙️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Action buttons, spacer, countdown, and flex layout', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Action buttons expose Content settings for Notifal action types. Spacer height, countdown layout, and flex Items controls make layout tweaks faster without editing HTML by hand.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">📝</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Notifal Forms', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Build multi-step lead forms in the HTML Builder with data attributes, captcha, honeypot, submissions inbox, CSV export, and webhook / Mailchimp integrations. Secrets stay in Settings and Form CPT meta, never in template HTML.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🛡️</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('WP Armour compatibility for popup forms', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Forms rendered dynamically inside Notifal popups now receive WP Armour honeypot fields when WP Armour is installed and active, preventing valid submissions from being rejected as spam.', 'notifal'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Get content for version 2.4.5
     *
     * @return string HTML content for version 2.4.5
     * @since 2.4.5
     */
    private function getVersion245Content(): string
    {
        ob_start();
        ?>
        <div class="notifal-whatsnew-content">
            <div class="notifal-whatsnew-section">
                <h3><?php echo '✨ ' . esc_html(__("What's New in 2.4.5", 'notifal')); ?></h3>
                <div class="notifal-whatsnew-features">
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">✨</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Smoother pre-created notifications experience', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Import Pre-Created now scrolls to the library even while templates are still loading, and builder import buttons in the details popup appear only after the template details are fully ready.', 'notifal'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
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
     * @updated 3.0.0 Restore 2.4.3 in the in-plugin last-four What's New list.
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
                        <span class="notifal-feature-icon">📦</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('HTML Builder import for pre-created notifications', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Pre-created notification import now supports HTML Builder templates, and the library checks each template minimum Notifal version before import.', 'notifal'); ?></p>
                        </div>
                    </div>
                    <div class="notifal-feature-item">
                        <span class="notifal-feature-icon">🔗</span>
                        <div class="notifal-feature-content">
                            <h4><?php esc_html_e('Clearer update guidance when a template needs a newer Notifal', 'notifal'); ?></h4>
                            <p><?php esc_html_e('Archive cards and the details popup show when your installed version is too old, with a link to update from Plugins, and import requests are blocked server-side when required.', 'notifal'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
