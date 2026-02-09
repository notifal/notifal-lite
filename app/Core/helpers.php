<?php
defined('ABSPATH') || exit;
use Notifal\Core\Foundation\Container;
use Notifal\Core\View\ViewRenderer;

if (! function_exists('notifal_app')) {
    /**
     * Resolve a service from Notifal container.
     *
     * @param string $abstract
     * @return mixed
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    function notifal_app(string $abstract)
    {
        return Container::getInstance()->get($abstract);
    }
}


if (!function_exists('notifal_view')) {
    /**
     * Render a Notifal view.
     *
     * @param string $view Dot notation path (e.g., "Templates.Admin.dashboard")
     * @param array $data Data to pass to the view
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    function notifal_view(string $view, array $data = []): void
    {
        notifal_app(ViewRenderer::class)->render($view, $data);
    }
}

if (!function_exists('notifal_is_frontend')) {
    /**
     * Check if the current request is for the frontend (not admin).
     *
     * @return bool True if frontend request, false if admin request
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    function notifal_is_frontend(): bool
    {
        return !is_admin() && !wp_doing_ajax() && !wp_doing_cron();
    }
}

// ===== AJAX SECURITY HELPERS =====

if (!function_exists('notifal_verify_ajax_request')) {
    /**
     * Verify AJAX request with nonce and user capabilities.
     *
     * @param string $nonce_action The nonce action to verify
     * @param string $capability Required user capability (default: 'edit_posts')
     * @param string $nonce_key POST key for nonce (default: 'nonce')
     * @return bool True if verification passes
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    function notifal_verify_ajax_request(string $nonce_action, string $capability = 'edit_posts', string $nonce_key = 'nonce'): bool
    {
        // Verify nonce (wp_unslash required for nonce verification)
        $nonce = isset($_POST[$nonce_key]) ? wp_unslash($_POST[$nonce_key]) : '';
        if (!wp_verify_nonce($nonce, $nonce_action)) {
            wp_send_json_error([
                'message' => __('Security check failed. Please refresh the page and try again.', 'notifal')
            ]);
        }

        // Check user capabilities
        if (!current_user_can($capability)) {
            wp_send_json_error([
                'message' => __('You do not have permission to perform this action.', 'notifal')
            ]);
        }

        return true;
    }
}

if (!function_exists('notifal_verify_get_request')) {
    /**
     * Verify GET request with nonce and user capabilities.
     *
     * @param string $nonce_action The nonce action to verify
     * @param string $capability Required user capability (default: 'edit_posts')
     * @param string $nonce_key GET key for nonce (default: 'nonce')
     * @return bool True if verification passes
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    function notifal_verify_get_request(string $nonce_action, string $capability = 'edit_posts', string $nonce_key = 'nonce'): bool
    {
        // Verify nonce (wp_unslash required for nonce verification)
        $nonce = isset($_GET[$nonce_key]) ? wp_unslash($_GET[$nonce_key]) : '';
        if (!wp_verify_nonce($nonce, $nonce_action)) {
            wp_send_json_error([
                'message' => __('Security check failed. Please refresh the page and try again.', 'notifal')
            ]);
        }

        // Check user capabilities
        if (!current_user_can($capability)) {
            wp_send_json_error([
                'message' => __('You do not have permission to perform this action.', 'notifal')
            ]);
        }

        return true;
    }
}

// ===== ERROR HELPERS =====

if (!function_exists('notifal_wp_error')) {
    /**
     * Create a standardized WP_Error object.
     *
     * @param string $code Error code
     * @param string $message Error message
     * @param array $data Additional error data
     * @return \WP_Error
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    function notifal_wp_error(string $code, string $message, array $data = []): \WP_Error
    {
        return new \WP_Error($code, $message, $data);
    }
}

// ===== RESPONSE HELPERS =====

if (!function_exists('notifal_json_success')) {
    /**
     * Send standardized JSON success response.
     *
     * @param mixed $data Response data
     * @param string $message Success message
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    function notifal_json_success($data = null, string $message = ''): void
    {
        $response = ['success' => true];

        if (!empty($message)) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        wp_send_json($response);
    }
}

if (!function_exists('notifal_json_error')) {
    /**
     * Send standardized JSON error response.
     *
     * @param string $message Error message
     * @param mixed $data Additional error data
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    function notifal_json_error(string $message, $data = null): void
    {
        $response = ['message' => $message];

        if ($data !== null) {
            $response['data'] = $data;
        }

        wp_send_json_error($response);
    }
}

// ===== ASSET HELPERS =====

if (!function_exists('notifal_enqueue_script')) {
    /**
     * Enqueue script with Notifal standards.
     *
     * @param string $handle Script handle
     * @param string $src Script source URL
     * @param array $deps Dependencies
     * @param array $localize Optional localization data
     * @param string|null $objectName Optional custom object name for localize. If null, converts handle to camelCase.
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    function notifal_enqueue_script(string $handle, string $src, array $deps = [], array $localize = [], ?string $objectName = null): void
    {
        wp_enqueue_script(
            $handle,
            $src,
            $deps,
            NOTIFAL_VERSION,
            true
        );

        if (!empty($localize)) {
            // Use custom object name if provided, otherwise convert handle to camelCase
            if ($objectName === null) {
                $objectName = str_replace('-', '', ucwords($handle, '-'));
                $objectName = lcfirst($objectName);
            }
            wp_localize_script($handle, $objectName, $localize);
        }
    }
}

if (!function_exists('notifal_enqueue_style')) {
    /**
     * Enqueue style with Notifal standards.
     *
     * @param string $handle Style handle
     * @param string $src Style source URL
     * @param array $deps Dependencies
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    function notifal_enqueue_style(string $handle, string $src, array $deps = []): void
    {
        wp_enqueue_style(
            $handle,
            $src,
            $deps,
            NOTIFAL_VERSION
        );
    }
}