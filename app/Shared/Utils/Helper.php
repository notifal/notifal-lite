<?php
namespace Notifal\Shared\Utils;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Helper {

    /**
     * Recursively sanitize an array using WordPress sanitization functions.
     *
     * @param array $array Array to sanitize
     * @return array Sanitized array
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function sanitizeArray(array $array): array {
        foreach ($array as $key => $value) {
            $array[$key] = is_array($value)
                ? self::sanitizeArray($value)
                : sanitize_text_field($value);
        }
        return $array;
    }

    /**
     * Format date using WordPress date and time settings.
     *
     * @param string $datetime DateTime string to format
     * @return string Formatted date string
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function formatDate(string $datetime): string {
        return date_i18n(
            get_option('date_format') . ' ' . get_option('time_format'),
            strtotime($datetime)
        );
    }

    /**
     * Log message to WordPress debug log when WP_DEBUG is enabled.
     *
     * @param string $message Message to log
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function log(string $message): void {
        if (WP_DEBUG === true) {
            error_log('[Notifal] ' . $message);
        }
    }

    /**
     * Sanitize various input types with proper validation.
     *
     * @param mixed $input The input to sanitize
     * @param string $type The type of sanitization (text, email, url, int, float, textarea)
     * @return mixed Sanitized input
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function sanitizeInput($input, string $type = 'text')
    {
        switch ($type) {
            case 'email':
                return sanitize_email($input);
            case 'url':
                return esc_url_raw($input);
            case 'int':
                return absint($input);
            case 'float':
                return floatval($input);
            case 'textarea':
                return sanitize_textarea_field($input);
            case 'key':
                return sanitize_key($input);
            case 'html':
                return wp_kses_post($input);
            case 'text':
            default:
                return sanitize_text_field($input);
        }
    }

    /**
     * Sanitize $_POST data with specified types.
     *
     * @param array $fields Array of field_name => type pairs
     * @return array Sanitized data
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function sanitizePostData(array $fields): array
    {
        $sanitized = [];
        foreach ($fields as $field => $type) {
            $value = isset($_POST[$field]) ? wp_unslash($_POST[$field]) : '';
            $sanitized[$field] = self::sanitizeInput($value, $type);
        }
        return $sanitized;
    }

    /**
     * Safely get a post with validation.
     *
     * @param int $post_id Post ID
     * @param string $post_type Expected post type (optional)
     * @return \WP_Post|null Post object or null if invalid
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function getPostSafe(int $post_id, string $post_type = ''): ?\WP_Post
    {
        $post = get_post($post_id);
        if (!$post) {
            return null;
        }

        if (!empty($post_type) && $post->post_type !== $post_type) {
            return null;
        }

        return $post;
    }

    /**
     * Safely update post meta with validation.
     *
     * @param int $post_id Post ID
     * @param string $key Meta key
     * @param mixed $value Meta value
     * @param string $post_type Expected post type (optional)
     * @return bool True on success, false on failure
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function updatePostMetaSafe(int $post_id, string $key, $value, string $post_type = ''): bool
    {
        $post = self::getPostSafe($post_id, $post_type);
        if (!$post) {
            return false;
        }

        return update_post_meta($post_id, $key, $value) !== false;
    }

    /**
     * Log message with Notifal prefix and level when WP_DEBUG is enabled.
     *
     * @param string $message Message to log
     * @param string $level Log level (INFO, WARNING, ERROR, SECURITY)
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function logAdvanced(string $message, string $level = 'INFO'): void
    {
        if (!WP_DEBUG) {
            return;
        }

        $prefix = "[Notifal:{$level}]";
        error_log("{$prefix} {$message}");
    }

    /**
     * Log security-related events.
     *
     * @param string $message Security event description
     * @param array $context Additional context data
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function securityLog(string $message, array $context = []): void
    {
        $user_id = get_current_user_id();
        $user_info = $user_id ? "User ID: {$user_id}" : "Anonymous";
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

        $log_message = sprintf(
            'Security Event: %s | %s | IP: %s | Context: %s',
            $message,
            $user_info,
            $ip,
            json_encode($context)
        );

        self::logAdvanced($log_message, 'SECURITY');
    }

    /**
     * Validate required fields in an array.
     *
     * @param array $data Data array to validate
     * @param array $required_fields Array of required field names
     * @return array Array of missing field names (empty if all required fields are present)
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function validateRequired(array $data, array $required_fields): array
    {
        $missing = [];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing[] = $field;
            }
        }
        return $missing;
    }

    /**
     * Check if value is a positive integer.
     *
     * @param mixed $value Value to check
     * @return bool True if value is a positive integer, false otherwise
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function isPositiveInt($value): bool
    {
        return is_numeric($value) && intval($value) > 0;
    }

    /**
     * Get client IP address with proper validation for local and production environments.
     *
     * Checks multiple headers and validates IP addresses appropriately for different environments.
     *
     * @return string Client IP address
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function getClientIpAddress(): string
    {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];

        $isLocalEnv = defined('WP_DEBUG') && WP_DEBUG ||
                     in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']) ||
                     strpos($_SERVER['HTTP_HOST'] ?? '', '.test') !== false ||
                     strpos($_SERVER['HTTP_HOST'] ?? '', '.local') !== false;

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if ($isLocalEnv) {
                        if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                            return $ip;
                        }
                    } else {
                        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                            return $ip;
                        }
                    }
                }
            }
        }

        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        return $isLocalEnv ? $remoteAddr : ($remoteAddr !== '127.0.0.1' ? $remoteAddr : '0.0.0.0');
    }

    /**
     * Get or generate session ID.
     *
     * Starts session if not already started and returns the session ID.
     *
     * @return string Session ID
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function getSessionId(): string
    {
        if (!session_id()) {
            session_start();
        }

        return session_id() ?: uniqid('notifal_', true);
    }

    /**
     * Execute a callback with temporary post context setup and automatic cleanup.
     *
     * This method safely manages WordPress global post context for operations that
     * require a specific post to be active (e.g., block rendering, widget registration).
     * The original post context is always restored, even if an exception occurs.
     *
     * @param \WP_Post $post The post to set as active context
     * @param callable $callback Function to execute with the post context
     * @param bool $setup_postdata Whether to call setup_postdata (default: true)
     * @return mixed The return value of the callback
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function withPostContext(\WP_Post $post, callable $callback, bool $setup_postdata = true)
    {
        // Store original post context
        $original_post = $GLOBALS['post'] ?? null;

        // Set up new post context
        $GLOBALS['post'] = $post;
        if ($setup_postdata) {
            setup_postdata($post);
        }

        try {
            // Execute callback with post context
            return $callback();
        } finally {
            // Always restore original post context
            $GLOBALS['post'] = $original_post;
            if ($setup_postdata) {
                if ($original_post) {
                    setup_postdata($original_post);
                } else {
                    wp_reset_postdata();
                }
            }
        }
    }

}
