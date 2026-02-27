<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Frontend\Routes;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\UrlService;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Repositories\NotificationQuery;
use Notifal\Shared\Config\Paths;

defined('ABSPATH') || exit;

/**
 * Class OnPagePreviewRouteController
 *
 * Handles the frontend preview route for OnPage notifications. Renders a minimal page
 * where admins can see how the notification will look and behave (same rendering as live).
 * Preview is admin-only; display rules, frequency, and analytics are ignored.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Presentation\Frontend\Routes
 */
class OnPagePreviewRouteController
{
    /**
     * Register the OnPage preview route (called by container).
     *
     * @since 2.0.0
     * @return void
     */
    public function register(): void
    {
        add_action('wp_loaded', [self::class, 'maybeRenderPreview'], 5);
    }

    /**
     * If preview query param is present, validate and flag preview mode; let the real page load.
     * The full page (e.g. homepage) renders; the notification script gets preview config via localizeScript.
     *
     * @since 2.0.0
     * @return void
     */
    public static function maybeRenderPreview(): void
    {
        $previewId = isset($_GET['notifal_onpage_preview']) ? absint($_GET['notifal_onpage_preview']) : 0;
        if ($previewId <= 0) {
            return;
        }

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'notifal_onpage_preview')) {
            wp_die(
                esc_html__('Security check failed. Please use the Preview button from the admin.', 'notifal'),
                esc_html__('Preview Error', 'notifal'),
                ['response' => 403]
            );
        }

        if (!current_user_can('edit_posts')) {
            wp_die(
                esc_html__('You do not have permission to view this preview.', 'notifal'),
                esc_html__('Preview Error', 'notifal'),
                ['response' => 403]
            );
        }

        $post = NotificationQuery::get($previewId);
        if (!$post || $post->post_type !== 'notifal_onpage_notif') {
            wp_die(
                esc_html__('Notification not found.', 'notifal'),
                esc_html__('Preview Error', 'notifal'),
                ['response' => 404]
            );
        }

        do_action(ActionHooks::ONPAGE_PREVIEW_BEFORE, $post);

        $GLOBALS['notifal_onpage_preview_id'] = $previewId;
        add_action('wp_footer', [self::class, 'renderPreviewBanner'], 5);
        add_action('wp_enqueue_scripts', [self::class, 'enqueuePreviewBannerStyle'], 15);
    }

    /**
     * Enqueue preview banner styles when in preview mode.
     *
     * @since 2.0.0
     * @return void
     */
    public static function enqueuePreviewBannerStyle(): void
    {
        if (empty($GLOBALS['notifal_onpage_preview_id'])) {
            return;
        }

        $previewId = (int) $GLOBALS['notifal_onpage_preview_id'];
        if ($previewId <= 0) {
            return;
        }

        wp_enqueue_style(
            'notifal-onpage-preview-banner',
            Paths::cssFrontendBuildUrl() . 'OnPagePreviewFrontendStyle.css',
            [],
            NOTIFAL_VERSION
        );

        wp_enqueue_script(
            'notifal-onpage-preview-banner',
            Paths::jsAdminBuildUrl() . 'OnPagePreviewFrontendScript.js',
            [],
            NOTIFAL_VERSION,
            true
        );

        wp_localize_script(
            'notifal-onpage-preview-banner',
            'notifalOnPagePreviewConfig',
            [
                'notificationId' => $previewId,
                'nonce'          => wp_create_nonce('notifal_onpage_preview'),
                'homeUrl'        => esc_url_raw(home_url('/')),
                'strings'        => [
                    'preview_url_required' => __('Please enter a path or URL.', 'notifal'),
                    'preview_url_invalid'  => __('Please enter a valid path or URL.', 'notifal'),
                ],
            ]
        );
    }

    /**
     * Render a small preview-mode banner in the footer so the admin knows they are in preview.
     *
     * @since 2.0.0
     * @return void
     */
    public static function renderPreviewBanner(): void
    {
        $previewId = isset($GLOBALS['notifal_onpage_preview_id']) ? (int) $GLOBALS['notifal_onpage_preview_id'] : 0;
        if ($previewId <= 0) {
            return;
        }
        $urlService = notifal_app(UrlService::class);
        $editUrl = $urlService->getEditNotificationUrl($previewId);
        $post = NotificationQuery::get($previewId);
        $title = $post ? $post->post_title : '';
        $isEnabled = $post ? (get_post_meta($post->ID, '_notifal_notif_enabled', true) === '1') : false;
        ?>
        <div class="notifal-onpage-preview-banner" role="status" aria-live="polite">
            <div class="notifal-onpage-preview-banner-inner">
                <div class="notifal-onpage-preview-banner-row-top">
                    <div class="notifal-onpage-preview-banner-meta">
                        <?php if ($title !== '') : ?>
                            <span class="notifal-onpage-preview-banner-title"><?php echo esc_html($title); ?></span>
                            <span class="notifal-onpage-preview-banner-status notifal-onpage-preview-banner-status--<?php echo esc_attr($isEnabled ? 'live' : 'draft'); ?>">
                                <?php echo $isEnabled ? esc_html__('Live & Active', 'notifal') : esc_html__('Draft', 'notifal'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <span class="notifal-onpage-preview-banner-divider" aria-hidden="true"></span>
                    <span class="notifal-onpage-preview-banner-text"><?php esc_html_e('Preview mode — notification only. Display rules, frequency and analytics are not applied.', 'notifal'); ?></span>
                    <div class="notifal-onpage-preview-banner-devices" role="group" aria-label="<?php esc_attr_e('Preview viewport', 'notifal'); ?>">
                        <button type="button" class="notifal-onpage-preview-banner-device notifal-onpage-preview-banner-device--active" data-viewport="desktop" title="<?php esc_attr_e('Desktop', 'notifal'); ?>">
                            <span class="notifal-onpage-preview-banner-device-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></span>
                        </button>
                        <button type="button" class="notifal-onpage-preview-banner-device" data-viewport="tablet" title="<?php esc_attr_e('Tablet', 'notifal'); ?>">
                            <span class="notifal-onpage-preview-banner-device-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="12" y1="18" x2="12" y2="18"/></svg></span>
                        </button>
                        <button type="button" class="notifal-onpage-preview-banner-device" data-viewport="mobile" title="<?php esc_attr_e('Mobile', 'notifal'); ?>">
                            <span class="notifal-onpage-preview-banner-device-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12" y2="18"/></svg></span>
                        </button>
                    </div>
                    <a href="<?php echo esc_url($editUrl); ?>" class="notifal-onpage-preview-banner-link"><?php esc_html_e('Edit notification', 'notifal'); ?></a>
                </div>
                <div class="notifal-onpage-preview-banner-row-bottom">
                    <div class="notifal-onpage-preview-banner-custom-page">
                        <label for="notifal-onpage-preview-url" class="notifal-onpage-preview-banner-label">
                            <?php esc_html_e('Preview on another page', 'notifal'); ?>
                        </label>
                        <div class="notifal-onpage-preview-banner-url-row">
                            <input
                                type="text"
                                id="notifal-onpage-preview-url"
                                class="notifal-onpage-preview-banner-input"
                                placeholder="<?php echo esc_attr(__('e.g. cart or /product-category/cloth', 'notifal')); ?>"
                                autocomplete="off"
                            />
                            <button
                                type="button"
                                id="notifal-onpage-preview-url-btn"
                                class="notifal-onpage-preview-banner-btn"
                            >
                                <?php esc_html_e('Preview on this page', 'notifal'); ?>
                            </button>
                        </div>
                        <p
                            id="notifal-onpage-preview-url-error"
                            class="notifal-onpage-preview-banner-error"
                            role="alert"
                            aria-live="polite"
                            hidden
                        ></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
        if ($post) {
            do_action(ActionHooks::ONPAGE_PREVIEW_AFTER, $post);
        }
    }
}
