<?php

namespace Notifal\Shared\AdminUI\Toast;

use Notifal\Shared\AdminUI\Toast\ToastManager;
use Notifal\Shared\Utils\Helper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ToastRenderer
 *
 * Handles rendering of both static and dynamic toast notifications.
 * Static toasts use query vars and are injected via PHP.
 * Dynamic toasts are inserted via JavaScript using the global container.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Shared\AdminUI\Toast
 */
class ToastRenderer
{
    /**
     * Render the empty toast container for dynamic JavaScript toasts.
     * This is required for frontend JS to inject toasts at runtime.
     *
     * @return void
     * @since 2.0.0
     */
    public static function renderGlobalContainer(): void
    {
        echo '<div id="notifal-global-messages" class="notifal-global-messages-container"></div>';
    }

    /**
     * Render a toast message based on query string (notifal_toast_message & notifal_toast_type).
     * Useful for redirect-based admin actions.
     *
     * @return void
     * @since 2.0.0
     */
    public static function render(): void
    {
        if (empty($_GET['notifal_toast_message'])) {
            return;
        }

        $message = Helper::sanitizeInput(isset($_GET['notifal_toast_message']) ? wp_unslash($_GET['notifal_toast_message']) : '', 'text');
        $type    = Helper::sanitizeInput(isset($_GET['notifal_toast_type']) ? wp_unslash($_GET['notifal_toast_type']) : ToastManager::TYPE_INFO, 'key');

        $allowedTypes = [
            ToastManager::TYPE_SUCCESS,
            ToastManager::TYPE_ERROR,
            ToastManager::TYPE_INFO,
            ToastManager::TYPE_WARNING
        ];

        if (!in_array($type, $allowedTypes, true)) {
            $type = ToastManager::TYPE_INFO;
        }

        $class = 'notifal-toast notifal-toast-' . esc_attr($type);

        // Determine icon based on type
        $icon = '';
        switch ($type) {
            case ToastManager::TYPE_SUCCESS:
                $icon = '<span class="notifal-icon notifal-icon-check"></span>';
                break;
            case ToastManager::TYPE_ERROR:
                $icon = '<span class="notifal-icon notifal-icon-alert"></span>';
                break;
            case ToastManager::TYPE_WARNING:
                $icon = '<span class="notifal-icon notifal-icon-alert"></span>';
                break;
            case ToastManager::TYPE_INFO:
            default:
                $icon = '<span class="notifal-icon notifal-icon-question-circle"></span>';
                break;
        }

        echo '<div class="' . $class . '" data-toast>';
        echo '<div class="notifal-toast-icon">' . $icon . '</div>';
        echo '<div class="notifal-toast-message">' . esc_html($message) . '</div>';
        echo '<button class="notifal-toast-close"><span class="notifal-icon notifal-icon-x-circle"></span></button>';
        echo '</div>';
    }
}
