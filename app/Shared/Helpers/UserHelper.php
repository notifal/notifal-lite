<?php
namespace Notifal\Shared\Helpers;

if (!defined('ABSPATH')) exit;

/**
 * Helper class for user-related utilities.
 *
 * @since 2.0.0
 */
class UserHelper {

    /**
     * Get current user ID safely.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     * @return int
     */
    public static function getCurrentUserId(): int {
        return get_current_user_id();
    }

    /**
     * Get list of editable user roles.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     * @return array [role_key => label]
     */
    public static function getEditableRoles(): array {
        global $wp_roles;

        if (!isset($wp_roles)) {
            $wp_roles = wp_roles();
        }

        $editable_roles = $wp_roles->roles;
        $roles = [];

        foreach ($editable_roles as $key => $info) {
            $roles[$key] = translate_user_role($info['name']);
        }

        return $roles;
    }
}
