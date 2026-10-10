<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\ListTable;

defined('ABSPATH') || exit;

use Notifal\Core\Support\Helpers\UrlHelper;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\UrlService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\AppearanceSettingsService;
use Notifal\Modules\OnPageNotification\Helpers\NotificationHelper;
use Notifal\Shared\Services\NotifalIconService;
use WP_Post;

/**
 * Class ColumnsController
 *
 * Handles custom columns for the admin list table of notifal_onpage_notif post type.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ColumnsController
{
    /**
     * Register all related filters for custom columns.
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        add_filter(FilterHooks::ADMIN_LIST_CUSTOM_COLUMN, [self::class, 'renderStatusColumn'], 10, 4);
        add_filter(FilterHooks::ADMIN_LIST_CUSTOM_COLUMN, [self::class, 'renderEnabledColumn'], 10, 4);
        add_filter(FilterHooks::ADMIN_LIST_CUSTOM_COLUMN, [self::class, 'renderDisplayTypeColumn'], 10, 4);
        add_filter(FilterHooks::ADMIN_LIST_ROW_ACTIONS, [self::class, 'modifyRowActions'], 10, 3);
        add_filter('get_edit_post_link', [self::class, 'modifyEditPostLink'], 10, 2);
    }

    /**
     * Render the "status" column for notifal_onpage_notif post type.
     *
     * @param string $content Default column content.
     * @param string $columnKey Current column key.
     * @param WP_Post $post The post object.
     * @param string $postType The post type.
     * @return string
     * @since 2.0.0
     */
    public static function renderStatusColumn(string $content, string $columnKey, WP_Post $post, string $postType): string
    {
        if ($postType !== 'notifal_onpage_notif' || $columnKey !== 'status') {
            return $content;
        }

        $status = get_post_status($post);
        $statusObj = get_post_status_object($status);
        
        if (!$statusObj) {
            return $content;
        }

        $statusClass = 'notifal-status-' . $status;
        $statusLabel = $statusObj->label;

        return sprintf(
            '<span class="notifal-status-badge %s">%s</span>',
            esc_attr($statusClass),
            esc_html($statusLabel)
        );
    }

    /**
     * Render the "enabled" column for notifal_onpage_notif post type.
     *
     * @param string $content Default column content.
     * @param string $columnKey Current column key.
     * @param WP_Post $post The post object.
     * @param string $postType The post type.
     * @return string
     * @since 2.0.0
     */
    public static function renderEnabledColumn(string $content, string $columnKey, WP_Post $post, string $postType): string
    {
        if ($postType !== 'notifal_onpage_notif' || $columnKey !== 'enabled') {
            return $content;
        }

        // CRITICAL: Enabled status is determined by meta key, NOT post_status
        // This is essential for proper licensing enforcement
        $isEnabled = NotificationHelper::isNotificationEnabled($post);
        $statusClass = NotificationHelper::getNotificationStatusClass($post);
        $statusLabel = NotificationHelper::getNotificationStatusLabel($post);
        $icon = $isEnabled ? 'check' : 'x-circle';

        return sprintf(
            '<button type="button" class="notifal-status-badge notifal-status-toggle %s" data-notification-id="%d" data-current-enabled="%s" title="%s" aria-label="%s">
                %s %s
            </button>',
            esc_attr($statusClass),
            esc_attr($post->ID),
            esc_attr($isEnabled ? '1' : '0'),
            esc_attr(sprintf(__('Click to %s this notification', 'notifal'), $isEnabled ? __('disable', 'notifal') : __('enable', 'notifal'))),
            esc_attr(sprintf(__('Toggle notification status. Currently %s.', 'notifal'), $statusLabel)),
            NotifalIconService::render($icon, 16),
            esc_html($statusLabel)
        );
    }

    /**
     * Render the "display_type" column for notifal_onpage_notif post type.
     *
     * @param string  $content    Default column content.
     * @param string  $columnKey  Current column key.
     * @param WP_Post $post       The post object.
     * @param string  $postType   The post type.
     * @return string
     * @since 3.0.0
     */
    public static function renderDisplayTypeColumn(string $content, string $columnKey, WP_Post $post, string $postType): string
    {
        // Only handle the display_type column for OnPage notifications.
        if ($postType !== 'notifal_onpage_notif' || $columnKey !== 'display_type') {
            return $content;
        }

        // Resolve the stored display type slug (same source as row actions).
        $displayType = self::resolveNotificationDisplayTypeSlug($post);

        // Map display type slugs to human-readable labels.
        $labelMap = [];
        foreach (AppearanceSettingsService::getDisplayTypeOptions() as $option) {
            if (!isset($option['value'], $option['label'])) {
                continue;
            }
            $labelMap[(string) $option['value']] = (string) $option['label'];
        }

        // Fallback labels for types added by extensions but not yet in the select options.
        $fallbackLabels = [
            'toast'    => __('Floating Side Box', 'notifal'),
            'popup'    => __('Popup (Modal)', 'notifal'),
            'topbar'   => __('Floating Bar', 'notifal'),
            'inline'   => __('Inline', 'notifal'),
            'floating' => __('Floating', 'notifal'),
        ];

        // Pick the best label for the current display type.
        $label = $labelMap[$displayType] ?? ($fallbackLabels[$displayType] ?? ucfirst($displayType));

        // Pro may replace this markup with shortcode tooltip UI for inline rows.
        return sprintf(
            '<span class="notifal-display-type-badge notifal-display-type-%1$s" data-display-type="%1$s">%2$s</span>',
            esc_attr($displayType),
            esc_html($label)
        );
    }

    /**
     * Render the "labels" column for notifal_onpage_notif post type.
     *
     * @deprecated 3.0.0 Replaced by display_type column. Kept to avoid fatal errors if referenced externally.
     * @param string $content Default column content.
     * @param string $columnKey Current column key.
     * @param WP_Post $post The post object.
     * @param string $postType The post type.
     * @return string
     * @since 2.0.0
     */
    public static function renderLabelsColumn(string $content, string $columnKey, WP_Post $post, string $postType): string
    {
        if ($postType !== 'notifal_onpage_notif' || $columnKey !== 'labels') {
            return $content;
        }

        // Get labels as taxonomy terms
        $labels = wp_get_object_terms($post->ID, 'notifal_label', ['fields' => 'names']);
        
        if (empty($labels) || is_wp_error($labels)) {
            return '<span class="notifal-no-labels">' . __('No labels', 'notifal') . '</span>';
        }

        if (!is_array($labels)) {
            $labels = [$labels];
        }

        $labelHtml = [];
        foreach ($labels as $labelName) {
            $labelHtml[] = sprintf(
                '<span class="notifal-label-badge">%s</span>',
                esc_html($labelName)
            );
        }

        return implode(' ', $labelHtml);
    }

    /**
     * Modify row actions for OnPage notifications to use custom edit URLs.
     *
     * @param array $actions Current actions array
     * @param WP_Post $post The post object
     * @param string $postType The post type
     * @return array Modified actions array
     * @since 2.0.0
     * @updated 3.0.0 Inline layout preview control is muted and shows an info toast on click.
     */
    public static function modifyRowActions(array $actions, WP_Post $post, string $postType): array
    {
        // Leave non–OnPage post types untouched.
        if ($postType !== 'notifal_onpage_notif') {
            return $actions;
        }

        // Remove view action (notifications are not public CPT pages).
        unset($actions['view']);

        // Replace edit action with custom URL.
        if (isset($actions['edit'])) {
            // Resolve the module URL helper for edit links.
            $urlService = notifal_app(UrlService::class);
            // Build the custom OnPage editor URL for this notification.
            $editUrl = $urlService->getEditNotificationUrl($post->ID);
            // Render the icon-only edit control to match other list actions.
            $actions['edit'] = sprintf(
                '<a href="%s" class="notifal-button secondary" title="%s" aria-label="%s">
                    %s
                </a>',
                esc_url($editUrl),
                esc_attr__('Edit', 'notifal'),
                esc_attr(sprintf(__('Edit %s', 'notifal'), $post->post_title)),
                NotifalIconService::render('pencil-square', 20)
            );
        }

        // Add Preview row action for users who can edit posts.
        if (current_user_can('edit_posts')) {
            // Read layout from appearance settings (canonical slug, e.g. inline).
            $displayTypeSlug = self::resolveNotificationDisplayTypeSlug($post);
            // Inline notifications use in-content placement; list preview is not offered yet.
            $isInlineLayout = ($displayTypeSlug === 'inline');
            // Short hint on hover; full guidance is shown via admin toast on click.
            $previewUnavailableTitle = __('Preview not available for inline (click for details)', 'notifal');
            // Include the notification title in the accessible name for context.
            $previewUnavailableAria = sprintf(
                /* translators: %s: notification title */
                __('Preview not available for inline notification %s. Activate for more information.', 'notifal'),
                $post->post_title
            );

            if ($isInlineLayout) {
                // Muted eye control stays clickable so we can show an informational toast.
                $actions['preview'] = sprintf(
                    '<button type="button" class="notifal-button secondary notifal-inline-preview-unavailable" title="%s" aria-label="%s">%s</button>',
                    esc_attr($previewUnavailableTitle),
                    esc_attr($previewUnavailableAria),
                    NotifalIconService::render('eye', 20)
                );
            } else {
                // Standard preview opens the front-end preview route in a new tab.
                $urlService = notifal_app(UrlService::class);
                // Build the authenticated preview URL for this notification.
                $previewUrl = $urlService->getPreviewUrl($post->ID);
                // Render the active preview link with eye icon.
                $actions['preview'] = sprintf(
                    '<a href="%s" class="notifal-button secondary" title="%s" target="_blank" rel="noopener noreferrer" aria-label="%s">%s</a>',
                    esc_url($previewUrl),
                    esc_attr__('Preview', 'notifal'),
                    esc_attr(sprintf(__('Preview %s', 'notifal'), $post->post_title)),
                    NotifalIconService::render('eye', 20)
                );
            }
        }

        return $actions;
    }

    /**
     * Resolve the saved notification display type slug for a list row.
     *
     * Reads `_notifal_appearance_settings.notification_display_type` and falls back to toast.
     *
     * @param WP_Post $post OnPage notification post.
     * @return string Sanitized display type slug.
     * @since 3.0.0
     */
    private static function resolveNotificationDisplayTypeSlug(WP_Post $post): string
    {
        // Load appearance settings from post meta.
        $appearanceSettings = get_post_meta($post->ID, '_notifal_appearance_settings', true);
        // Treat missing or invalid meta as an empty settings array.
        if (!is_array($appearanceSettings)) {
            $appearanceSettings = [];
        }
        // Default display type when the key was never saved.
        if (!isset($appearanceSettings['notification_display_type'])) {
            return 'toast';
        }
        // Return a canonical slug for comparisons and column badges.
        return sanitize_key((string) $appearanceSettings['notification_display_type']);
    }

    /**
     * Modify the edit post link for OnPage notifications to use custom URL.
     *
     * @param string $link The edit post link
     * @param int $postId The post ID
     * @return string Modified edit link
     * @since 2.0.0
     */
    public static function modifyEditPostLink(string $link, int $postId): string
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== 'notifal_onpage_notif') {
            return $link;
        }

        $urlService = notifal_app(UrlService::class);
        return $urlService->getEditNotificationUrl($postId);
    }
} 
