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
 * @updated 3.0.0 compact preview toolbar
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
        // Validate preview query args before the template renders.
        add_action('wp_loaded', [self::class, 'maybeRenderPreview'], 5);

        // Always register enqueue; the callback no-ops when not in preview mode.
        add_action('wp_enqueue_scripts', [self::class, 'enqueuePreviewBannerStyle'], 20);
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
        // Read and sanitize the preview notification ID from the query string.
        $previewId = isset($_GET['notifal_onpage_preview']) ? absint($_GET['notifal_onpage_preview']) : 0;
        if ($previewId <= 0) {
            return;
        }

        // Verify the preview nonce before continuing.
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'notifal_onpage_preview')) {
            wp_die(
                esc_html__('Security check failed. Please use the Preview button from the admin.', 'notifal'),
                esc_html__('Preview Error', 'notifal'),
                ['response' => 403]
            );
        }

        // Only users who can edit posts may open preview mode.
        if (!current_user_can('edit_posts')) {
            wp_die(
                esc_html__('You do not have permission to view this preview.', 'notifal'),
                esc_html__('Preview Error', 'notifal'),
                ['response' => 403]
            );
        }

        // Ensure the preview target is a valid on-page notification post.
        $post = NotificationQuery::get($previewId);
        if (!$post || $post->post_type !== 'notifal_onpage_notif') {
            wp_die(
                esc_html__('Notification not found.', 'notifal'),
                esc_html__('Preview Error', 'notifal'),
                ['response' => 404]
            );
        }

        // Allow other modules to react before preview chrome is attached.
        do_action(ActionHooks::ONPAGE_PREVIEW_BEFORE, $post);

        // Store preview ID for footer banner and script localization.
        $GLOBALS['notifal_onpage_preview_id'] = $previewId;

        // Render compact preview chrome late in the footer (before scripts print).
        add_action('wp_footer', [self::class, 'renderPreviewBanner'], 5);
    }

    /**
     * Resolve a Vite build file URL and a cache-busting version from filemtime.
     *
     * @since 3.0.0
     * @param string $directoryUrl Trailing-slash build directory URL from Paths helpers.
     * @param string $fileName Build file name (e.g. OnPagePreviewFrontendScript.js).
     * @param string $relativePath Path under public/build for filesystem checks.
     * @return array{url:string,version:string,exists:bool} Asset URL, version, and existence flag.
     */
    private static function resolveBuildAsset(string $directoryUrl, string $fileName, string $relativePath): array
    {
        // Absolute filesystem path for existence and mtime checks.
        $absolutePath = trailingslashit(NOTIFAL_PATH) . 'public/build/' . ltrim($relativePath, '/');
        $exists = is_readable($absolutePath);

        // Public URL built from the shared Paths helper so enqueue matches other modules.
        $url = trailingslashit($directoryUrl) . ltrim($fileName, '/');

        // Prefer filemtime so rebuilt assets bust browser cache without a plugin version bump.
        $version = $exists ? (string) filemtime($absolutePath) : (string) NOTIFAL_VERSION;

        return [
            'url'     => $url,
            'version' => $version,
            'exists'  => $exists,
        ];
    }

    /**
     * Enqueue preview banner styles and scripts when in preview mode.
     *
     * @since 2.0.0
     * @update 3.0.0
     * @return void
     */
    public static function enqueuePreviewBannerStyle(): void
    {
        // Skip when preview mode was not activated.
        if (empty($GLOBALS['notifal_onpage_preview_id'])) {
            return;
        }

        // Cast the stored preview ID for localization.
        $previewId = (int) $GLOBALS['notifal_onpage_preview_id'];
        if ($previewId <= 0) {
            return;
        }

        // Frontend CSS build for the compact preview toolbar.
        $styleAsset = self::resolveBuildAsset(
            Paths::cssFrontendBuildUrl(),
            'OnPagePreviewFrontendStyle.css',
            'css/frontend/OnPagePreviewFrontendStyle.css'
        );

        // Frontend JS build (js/frontend, never js/admin).
        $scriptAsset = self::resolveBuildAsset(
            Paths::jsFrontendBuildUrl(),
            'OnPagePreviewFrontendScript.js',
            'js/frontend/OnPagePreviewFrontendScript.js'
        );

        // Load built Vite CSS for the compact preview toolbar.
        wp_enqueue_style(
            'notifal-onpage-preview-banner',
            $styleAsset['url'],
            [],
            $styleAsset['version']
        );

        // Load built Vite JS for dismiss, expand, devices, and URL redirect.
        wp_enqueue_script(
            'notifal-onpage-preview-banner',
            $scriptAsset['url'],
            [],
            $scriptAsset['version'],
            true
        );

        // Pass preview context and UI strings to the banner script.
        wp_localize_script(
            'notifal-onpage-preview-banner',
            'notifalOnPagePreviewConfig',
            [
                'notificationId' => $previewId,
                'nonce'          => wp_create_nonce('notifal_onpage_preview'),
                'homeUrl'        => esc_url_raw(home_url('/')),
                'storageKey'     => 'notifal_onpage_preview_banner_dismissed_' . $previewId,
                'assetUrl'       => $scriptAsset['url'],
                'assetVersion'   => $scriptAsset['version'],
                'strings'        => [
                    'preview_url_required' => __('Please enter a path or URL.', 'notifal'),
                    'preview_url_invalid'  => __('Please enter a valid path or URL.', 'notifal'),
                    'close_banner'         => __('Close preview toolbar', 'notifal'),
                    'expand_details'       => __('Show preview tools', 'notifal'),
                    'collapse_details'     => __('Hide preview tools', 'notifal'),
                    'reopen_banner'        => __('Show preview toolbar', 'notifal'),
                ],
            ]
        );
    }

    /**
     * Render a compact, dismissible preview toolbar so admins know they are in preview.
     *
     * @since 2.0.0
     * @return void
     */
    public static function renderPreviewBanner(): void
    {
        // Resolve the active preview notification ID.
        $previewId = isset($GLOBALS['notifal_onpage_preview_id']) ? (int) $GLOBALS['notifal_onpage_preview_id'] : 0;
        if ($previewId <= 0) {
            return;
        }

        // Build the edit-screen URL for the current notification.
        $urlService = notifal_app(UrlService::class);
        $editUrl = $urlService->getEditNotificationUrl($previewId);

        // Load title and enabled state for the compact meta row.
        $post = NotificationQuery::get($previewId);
        $title = $post ? $post->post_title : '';
        $isEnabled = $post ? (get_post_meta($post->ID, '_notifal_notif_enabled', true) === '1') : false;
        ?>
        <div
            id="notifal-onpage-preview-banner"
            class="notifal-onpage-preview-banner"
            role="region"
            aria-label="<?php esc_attr_e('Notification preview toolbar', 'notifal'); ?>"
            data-notification-id="<?php echo esc_attr((string) $previewId); ?>"
        >
            <div class="notifal-onpage-preview-banner-inner">
                <div class="notifal-onpage-preview-banner-toolbar">
                    <span class="notifal-onpage-preview-banner-badge"><?php esc_html_e('Preview', 'notifal'); ?></span>

                    <div class="notifal-onpage-preview-banner-meta">
                        <?php if ($title !== '') : ?>
                            <span class="notifal-onpage-preview-banner-title"><?php echo esc_html($title); ?></span>
                            <span class="notifal-onpage-preview-banner-status notifal-onpage-preview-banner-status--<?php echo esc_attr($isEnabled ? 'live' : 'draft'); ?>">
                                <?php echo $isEnabled ? esc_html__('Live', 'notifal') : esc_html__('Draft', 'notifal'); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="notifal-onpage-preview-banner-devices" role="group" aria-label="<?php esc_attr_e('Preview viewport', 'notifal'); ?>">
                        <button type="button" class="notifal-onpage-preview-banner-device notifal-onpage-preview-banner-device--active" data-viewport="desktop" title="<?php esc_attr_e('Desktop', 'notifal'); ?>">
                            <span class="notifal-onpage-preview-banner-device-icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></span>
                        </button>
                        <button type="button" class="notifal-onpage-preview-banner-device" data-viewport="tablet" title="<?php esc_attr_e('Tablet', 'notifal'); ?>">
                            <span class="notifal-onpage-preview-banner-device-icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="12" y1="18" x2="12" y2="18"/></svg></span>
                        </button>
                        <button type="button" class="notifal-onpage-preview-banner-device" data-viewport="mobile" title="<?php esc_attr_e('Mobile', 'notifal'); ?>">
                            <span class="notifal-onpage-preview-banner-device-icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12" y2="18"/></svg></span>
                        </button>
                    </div>

                    <button
                        type="button"
                        id="notifal-onpage-preview-banner-toggle"
                        class="notifal-onpage-preview-banner-toggle"
                        aria-expanded="false"
                        aria-controls="notifal-onpage-preview-banner-details"
                        title="<?php esc_attr_e('Show preview tools', 'notifal'); ?>"
                    >
                        <span class="notifal-onpage-preview-banner-toggle-label"><?php esc_html_e('Tools', 'notifal'); ?></span>
                        <span class="notifal-onpage-preview-banner-toggle-icon" aria-hidden="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </span>
                    </button>

                    <a href="<?php echo esc_url($editUrl); ?>" class="notifal-onpage-preview-banner-link"><?php esc_html_e('Edit', 'notifal'); ?></a>

                    <button
                        type="button"
                        id="notifal-onpage-preview-banner-close"
                        class="notifal-onpage-preview-banner-close"
                        title="<?php esc_attr_e('Close preview toolbar', 'notifal'); ?>"
                        aria-label="<?php esc_attr_e('Close preview toolbar', 'notifal'); ?>"
                    >
                        <span aria-hidden="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </span>
                    </button>
                </div>

                <div
                    id="notifal-onpage-preview-banner-details"
                    class="notifal-onpage-preview-banner-details"
                    hidden
                >
                    <p class="notifal-onpage-preview-banner-text">
                        <?php esc_html_e('Preview mode only. Display rules, frequency and analytics are not applied.', 'notifal'); ?>
                    </p>

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
                                <?php esc_html_e('Go', 'notifal'); ?>
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

        <button
            type="button"
            id="notifal-onpage-preview-banner-reopen"
            class="notifal-onpage-preview-banner-reopen"
            hidden
            title="<?php esc_attr_e('Show preview toolbar', 'notifal'); ?>"
            aria-label="<?php esc_attr_e('Show preview toolbar', 'notifal'); ?>"
        >
            <?php esc_html_e('Preview', 'notifal'); ?>
        </button>
        <?php
        // Allow other modules to run after preview chrome is rendered.
        if ($post) {
            do_action(ActionHooks::ONPAGE_PREVIEW_AFTER, $post);
        }
    }
}
