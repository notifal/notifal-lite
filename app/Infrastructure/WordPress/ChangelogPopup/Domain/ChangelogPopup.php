<?php
/**
 * Changelog Popup Domain
 *
 * Provides changelog data for the Changelog popup (sticky menu).
 * Delegates to WhatsNewPopup for version list and content so a single
 * source of truth is used for both What's New and Changelog.
 *
 * @package Notifal\Infrastructure\WordPress\ChangelogPopup\Domain
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\ChangelogPopup\Domain;

use Notifal\Infrastructure\WordPress\WhatsNewPopup\Domain\WhatsNewPopup;

defined('ABSPATH') || exit;

/**
 * Class ChangelogPopup
 */
class ChangelogPopup
{
    /**
     * WhatsNewPopup instance for version/config data
     *
     * @var WhatsNewPopup
     */
    private WhatsNewPopup $whatsnew_popup;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->whatsnew_popup = new WhatsNewPopup();
    }

    /**
     * Get list of versions available in the changelog (newest first)
     *
     * @return string[]
     * @since 2.0.0
     */
    public function getAvailableChangelogVersions(): array
    {
        return $this->whatsnew_popup->getAvailableChangelogVersions();
    }

    /**
     * Get title and content for a specific version (for Changelog popup).
     *
     * Uses "Version X.X.X" as the title in the changelog popup so it is distinct
     * from the What's New popup, which uses "What's New in X.X.X".
     *
     * @param string $version Version string, e.g. '2.0.0'
     * @return array{title: string, content: string}
     * @since 2.0.0
     */
    public function getChangelogContentForVersion(string $version): array
    {
        $data = $this->whatsnew_popup->getChangelogContentForVersion($version);
        $data['title'] = sprintf(__('Version %s', 'notifal'), $version);

        return $data;
    }
}
