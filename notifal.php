<?php
/**
 * Plugin Name: Notifal
 * Plugin URI: https://notifal.com/?utm_source=repo-notifal-lite&utm_campaign=plugin-uri&utm_medium=plugin-page-main-link
 * Description: Notifal helps boost engagement on your website by showing sales popups, stock alerts, offers, and custom messages to build trust, create urgency, and add social proof.
 * Version: 2.3.5
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * Author: Notifal.com
 * Requires at least: 6.0
 * Tested up to: 7.0
 * Author URI: https://notifal.com
 * Text Domain: notifal
 * Domain Path: /languages
 */

use Notifal\Bootstrap\Plugin;

defined('ABSPATH') || exit;

// Plugin Constants
if (!defined('NOTIFAL_VERSION')) {
    define('NOTIFAL_VERSION', '2.3.5');
}
if (!defined('NOTIFAL_FILE')) {
    define('NOTIFAL_FILE', __FILE__);
}
if (!defined('NOTIFAL_BASENAME')) {
    define('NOTIFAL_BASENAME', plugin_basename(__FILE__));
}
if (!defined('NOTIFAL_PATH')) {
    define('NOTIFAL_PATH', plugin_dir_path(__FILE__));
}
if (!defined('NOTIFAL_URL')) {
    define('NOTIFAL_URL', plugin_dir_url(__FILE__));
}
if (!defined('NOTIFAL_APP_PATH')) {
    define('NOTIFAL_APP_PATH', NOTIFAL_PATH.'app/');
}
if (!defined('NOTIFAL_APP_URL')) {
    define('NOTIFAL_APP_URL', NOTIFAL_URL.'app/');
}
if (!defined('NOTIFAL_MODULES_PATH')) {
    define('NOTIFAL_MODULES_PATH', NOTIFAL_APP_PATH . 'Modules/');
}
if (!defined('NOTIFAL_MODULES_URL')) {
    define('NOTIFAL_MODULES_URL', NOTIFAL_APP_URL . 'Modules/');
}

// PSR-4 Autoloader for Notifal classes
spl_autoload_register(function ($class) {
    // Notifal namespace prefix
    $prefix = 'Notifal\\';

    // Base directory for the namespace prefix
    $base_dir = __DIR__ . '/app/';

    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No, move to the next registered autoloader
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Helpers
require_once NOTIFAL_PATH . 'app/Core/helpers.php';

// Bootstrap
require_once NOTIFAL_PATH . 'app/Bootstrap/Plugin.php';

Plugin::register();