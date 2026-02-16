<?php
/**
 * Changelog Popup Controller
 *
 * Handles changelog popup rendering and AJAX request for version content.
 * Separate from What's New popup; used for the sticky menu "Changelog" button.
 *
 * @package Notifal\Infrastructure\WordPress\ChangelogPopup\Infrastructure
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\ChangelogPopup\Infrastructure;

use Notifal\Infrastructure\WordPress\ChangelogPopup\Domain\ChangelogPopup;
use Notifal\Shared\Helpers\AdminScreenDetector;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class ChangelogController
 */
class ChangelogController
{
    /**
     * ChangelogPopup domain instance
     *
     * @var ChangelogPopup
     */
    private ChangelogPopup $changelog_popup;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->changelog_popup = new ChangelogPopup();
    }

    /**
     * Register hooks and actions
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        $instance = new self();

        add_action('wp_ajax_notifal_get_changelog_version_content', [$instance, 'getChangelogVersionContent']);
        add_action('admin_footer', [$instance, 'maybeRenderChangelogPopup']);
    }

    /**
     * Render changelog popup HTML on Notifal admin pages only
     *
     * @return void
     * @since 2.0.0
     */
    public function maybeRenderChangelogPopup(): void
    {
        if (wp_doing_ajax() || !AdminScreenDetector::isNotifalPage()) {
            return;
        }

        $this->renderChangelogPopup();
    }

    /**
     * AJAX handler: get title and content for a specific version
     *
     * @return void
     * @since 2.0.0
     */
    public function getChangelogVersionContent(): void
    {
        try {
            notifal_verify_ajax_request('notifal_changelog_popup', 'manage_options');

            $version = isset($_POST['version']) ? sanitize_text_field(wp_unslash($_POST['version'])) : '';
            if ($version === '') {
                notifal_json_error(__('Invalid version.', 'notifal'));
                return;
            }

            $data = $this->changelog_popup->getChangelogContentForVersion($version);

            notifal_json_success($data);
        } catch (\Exception $e) {
            Helper::logAdvanced('Changelog popup AJAX error: ' . $e->getMessage(), 'ERROR');
            notifal_json_error(__('An error occurred while loading changelog.', 'notifal'));
        }
    }

    /**
     * Output changelog popup markup
     *
     * @return void
     * @since 2.0.0
     */
    public function renderChangelogPopup(): void
    {
        $versions = $this->changelog_popup->getAvailableChangelogVersions();
        $first_version = !empty($versions) ? $versions[0] : '';
        $first_content = $first_version ? $this->changelog_popup->getChangelogContentForVersion($first_version) : ['title' => '', 'content' => ''];
        ?>
        <div id="notifal-changelog-popup" class="notifal-changelog-popup-overlay notifal-hidden" aria-hidden="true">
            <div class="notifal-changelog-popup-content">
                <div class="notifal-changelog-popup-header">
                    <h2 class="notifal-changelog-popup-title"><?php esc_html_e('Changelog', 'notifal'); ?></h2>
                    <button type="button" class="notifal-changelog-popup-close" aria-label="<?php esc_attr_e('Close', 'notifal'); ?>">
                        <span class="notifal-icon notifal-icon-x-circle" aria-hidden="true"></span>
                    </button>
                </div>
                <div class="notifal-changelog-popup-body">
                    <div class="notifal-changelog-popup-version-select-wrap">
                        <label for="notifal-changelog-version-select" class="notifal-changelog-version-label"><?php esc_html_e('Select version', 'notifal'); ?></label>
                        <select id="notifal-changelog-version-select" class="notifal-changelog-version-select" data-first-version="<?php echo esc_attr($first_version); ?>">
                            <?php foreach ($versions as $ver): ?>
                                <option value="<?php echo esc_attr($ver); ?>" <?php selected($ver, $first_version); ?>><?php echo esc_html($ver); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="notifal-changelog-popup-loader" class="notifal-changelog-popup-loader notifal-hidden" aria-live="polite" aria-busy="false">
                        <span class="notifal-changelog-popup-loader-spinner" aria-hidden="true"></span>
                        <span class="notifal-changelog-popup-loader-text"></span>
                    </div>
                    <div class="notifal-changelog-popup-version-content">
                        <div class="notifal-changelog-popup-version-title" id="notifal-changelog-version-title"><?php echo esc_html($first_content['title']); ?></div>
                        <div class="notifal-changelog-popup-version-body" id="notifal-changelog-version-body">
                            <?php echo wp_kses_post($first_content['content']); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
