<?php
/**
 * Changelog Popup Assets Registrar
 *
 * Localizes the shared admin script with changelog popup config (ajaxUrl, nonce, i18n).
 * Changelog popup CSS and JS are bundled with the shared admin bundle via notifal-sticky-menu.
 *
 * @package Notifal\Infrastructure\WordPress\ChangelogPopup\Presentation\Admin
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\ChangelogPopup\Presentation\Admin;

use Notifal\Core\Support\Helpers\UrlHelper;
use Notifal\Infrastructure\WordPress\Security\NonceManager;
use Notifal\Shared\Helpers\AdminScreenDetector;

defined('ABSPATH') || exit;

/**
 * Class ChangelogAssetsRegistrar
 */
class ChangelogAssetsRegistrar
{
    /**
     * Handle of the shared admin script (changelog popup JS is bundled there)
     */
    const SHARED_ADMIN_SCRIPT_HANDLE = 'notifal-shared-admin-js';

    /**
     * Register hooks to localize shared admin script with changelog config
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        $instance = new self();
        add_action('admin_enqueue_scripts', [$instance, 'localizeChangelogConfig'], 20);
    }

    /**
     * Add notifalChangelogPopup to the shared admin script on Notifal pages
     *
     * @param string $hook Current admin page hook
     * @return void
     * @since 2.0.0
     */
    public function localizeChangelogConfig(string $hook): void
    {
        if (!AdminScreenDetector::isNotifalPage()) {
            return;
        }

        wp_localize_script(
            self::SHARED_ADMIN_SCRIPT_HANDLE,
            'notifalChangelogPopup',
            [
                'ajaxUrl' => UrlHelper::baseAjax(),
                'nonce' => NonceManager::create('notifal_changelog_popup'),
                'i18n' => [
                    'changelog' => __('Changelog', 'notifal'),
                    'selectVersion' => __('Select version', 'notifal'),
                    'close' => __('Close', 'notifal'),
                    'errorLoading' => __('Failed to load changelog for this version.', 'notifal'),
                    'loadingChangesOf' => __('Loading changes of %s', 'notifal'),
                ],
            ]
        );
    }
}
