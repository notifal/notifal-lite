<?php
namespace Notifal\Shared\Helpers;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AdminScreenDetector {

    /**
     * Check if current admin screen belongs to Notifal
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     * @return bool
     */
    public static function isNotifalPage(): bool {
        if ( ! function_exists( 'get_current_screen' ) ) {
            return false;
        }
        $screen = get_current_screen();

        if (!$screen) {
            return false;
        }

        // Main Notifal menu (top-level) - WordPress uses toplevel_page_{slug},
        if ($screen->id === 'toplevel_page_notifal' || $screen->base === 'toplevel_page_notifal') {
            return true;
        }

        // Check for notifal_page in screen ID (submenu pages under Notifal)
        if (strpos($screen->id, 'notifal_page') !== false) {
            return true;
        }

        // Check for notifal_templates page specifically
        if ($screen->base === 'notifal_page_notifal_templates') {
            return true;
        }

        // Check for notifal settings page
        if (strpos($screen->id, 'notifal-settings') !== false) {
            return true;
        }

        // Check for Notifal Pro license page
        if (strpos($screen->id, 'notifal-pro-license') !== false) {
            return true;
        }

        // Check for notifal post types (sanitize input before use)
        $post_type = isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : '';
        if ($post_type !== '' && strpos($post_type, 'notifal_') === 0) {
            return true;
        }

        return false;
    }
}
