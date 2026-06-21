<?php

namespace Notifal\Modules\Templates\Presentation\Admin\Controllers;

defined('ABSPATH') || exit;

/**
 * Registers the hidden HTML Builder admin page.
 *
 * @since 2.4.0
 * @author Hossein <hossein@notifal.com>
 */
class HtmlBuilderPageController
{
    /**
     * Admin page slug.
     *
     * @since 2.4.0
     */
    public const PAGE_SLUG = 'notifal_html_builder';

    /**
     * Register WordPress hooks.
     *
     * @return void
     * @since 2.4.0
     */
    public static function register(): void
    {
        // Register the hidden admin route used by the HTML Builder workspace.
        add_action('admin_menu', [self::class, 'registerHiddenPage'], 99);
        // Apply fullscreen body class so builder CSS can hide WP admin chrome.
        add_filter('admin_body_class', [self::class, 'filterAdminBodyClass']);
        // Hide the front-end admin bar while editing inside the builder.
        add_filter('show_admin_bar', [self::class, 'filterShowAdminBar']);
    }

    /**
     * Determine whether the current request is the HTML Builder screen.
     *
     * @return bool True when the builder admin page is loading.
     * @since 2.4.0
     */
    public static function isCurrentScreen(): bool
    {
        // Fall back to the page query arg when the screen object is not ready yet.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (!is_admin() || $page !== self::PAGE_SLUG) {
            return false;
        }

        // Prefer the screen API when available for more reliable detection.
        if (!function_exists('get_current_screen')) {
            return true;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return true;
        }

        $expectedBase = 'admin_page_' . self::PAGE_SLUG;

        return $screen->id === $expectedBase || $screen->base === $expectedBase;
    }

    /**
     * Append the fullscreen body class on the HTML Builder screen.
     *
     * @param string $classes Existing admin body classes.
     * @return string Updated body classes.
     * @since 2.4.0
     */
    public static function filterAdminBodyClass(string $classes): string
    {
        // Only alter the body class list on the builder page.
        if (!self::isCurrentScreen()) {
            return $classes;
        }

        return trim($classes . ' notifal-html-builder-fullscreen');
    }

    /**
     * Disable the WP admin bar on the fullscreen builder screen.
     *
     * @param bool $show Whether the admin bar should render.
     * @return bool Filtered visibility flag.
     * @since 2.4.0
     */
    public static function filterShowAdminBar(bool $show): bool
    {
        // Keep the default behavior on every other screen.
        if (!self::isCurrentScreen()) {
            return $show;
        }

        return false;
    }

    /**
     * Register a hidden submenu page for the HTML Builder workspace.
     *
     * @return void
     * @since 2.4.0
     */
    public static function registerHiddenPage(): void
    {
        add_submenu_page(
            null,
            __('HTML Builder', 'notifal'),
            __('HTML Builder', 'notifal'),
            'edit_posts',
            self::PAGE_SLUG,
            [self::class, 'render']
        );
    }

    /**
     * Render the HTML Builder shell view.
     *
     * @return void
     * @since 2.4.0
     */
    public static function render(): void
    {
        $templateId = isset($_GET['template_id']) ? absint($_GET['template_id']) : 0;

        if (!$templateId) {
            wp_die(esc_html__('Template ID is required.', 'notifal'));
        }

        if (!current_user_can('edit_post', $templateId)) {
            wp_die(esc_html__('You are not allowed to edit this template.', 'notifal'));
        }

        $template = get_post($templateId);
        if (!$template || $template->post_type !== 'notifal_template') {
            wp_die(esc_html__('Template not found.', 'notifal'));
        }

        notifal_view('Templates.Admin.html-builder', [
            'template'    => $template,
            'template_id' => $templateId,
        ]);
    }
}
