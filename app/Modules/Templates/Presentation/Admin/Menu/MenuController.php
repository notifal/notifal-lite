<?php

namespace Notifal\Modules\Templates\Presentation\Admin\Menu;

use Notifal\Shared\AdminUI\Lists\BaseListView;

defined('ABSPATH') || exit;

/**
 * Class MenuController
 *
 * Registers the Templates submenu page under the main Notifal menu in wp-admin.
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Presentation\Admin\Menu
 */
class MenuController
{
    /**
     * Register hooks for Templates admin menu.
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'overrideTemplateMenu'], 21);
        add_action('admin_init', [self::class, 'maybeRedirectFromDefaultList']);
    }

    /**
     * Remove default CPT menu and add our custom Templates menu.
     *
     * @return void
     * @since 2.0.0
     */
    public static function overrideTemplateMenu(): void
    {
        remove_submenu_page('notifal', 'edit.php?post_type=notifal_template');

        $hook = add_submenu_page(
            'notifal',
            __('Templates', 'notifal'),
            __('Templates', 'notifal'),
            'manage_options',
            'notifal_templates',
            [self::class, 'render']
        );

        // Handle bulk actions on this specific page load
        add_action("load-{$hook}", [self::class, 'handleBulkActions']);
    }

    /**
     * Redirect from default WP template list to our custom page.
     *
     * @return void
     * @since 2.0.0
     */
    public static function maybeRedirectFromDefaultList(): void
    {
        global $pagenow;

        $get_post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
        $get_page      = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if (
            $pagenow === 'edit.php' &&
            $get_post_type === 'notifal_template' &&
            $get_page === ''
        ) {
            wp_safe_redirect(admin_url('admin.php?page=notifal_templates'));
            exit;
        }
    }

    /**
     * Handle bulk actions for Templates before rendering.
     *
     * This method processes POST requests for bulk actions and redirects with appropriate messages.
     * Must be called before any output is sent to avoid "headers already sent" errors.
     *
     * @return void
     * @since 2.0.0
     */
    public static function handleBulkActions(): void
    {
        // Use the generic BaseListView method for templates
        BaseListView::handleBulkActionsForPostType('notifal_template');
    }

    /**
     * Render the Templates admin view.
     *
     * @return void
     * @since 2.0.0
     */
    public static function render(): void
    {
        notifal_view('Templates.Admin.templates-list');
    }
}
