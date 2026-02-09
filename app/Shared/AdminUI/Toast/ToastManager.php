<?php

namespace Notifal\Shared\AdminUI\Toast;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ToastManager
 *
 * Manages admin toast notifications across Notifal admin pages.
 * Adds a query variable to the URL and renders the message in the footer.
 * JavaScript picks up the message and shows a styled toast using Notifal UI.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Shared\AdminUI\Toast
 */
class ToastManager
{
    public const TYPE_SUCCESS = 'success';
    public const TYPE_ERROR   = 'error';
    public const TYPE_INFO    = 'info';
    public const TYPE_WARNING = 'warning';

    /**
     * Redirects to a given URL with a success message.
     *
     * @param string $message
     * @param string|null $redirectUrl
     * @return void
     * @since 2.0.0
     */
    public static function success(string $message, ?string $redirectUrl = null): void
    {
        self::redirectWithMessage($message, self::TYPE_SUCCESS, $redirectUrl);
    }

    /**
     * Redirects to a given URL with an error message.
     *
     * @param string $message
     * @param string|null $redirectUrl
     * @return void
     * @since 2.0.0
     */
    public static function error(string $message, ?string $redirectUrl = null): void
    {
        self::redirectWithMessage($message, self::TYPE_ERROR, $redirectUrl);
    }

    /**
     * Redirects to a given URL with an info message.
     *
     * @param string $message
     * @param string|null $redirectUrl
     * @return void
     * @since 2.0.0
     */
    public static function info(string $message, ?string $redirectUrl = null): void
    {
        self::redirectWithMessage($message, self::TYPE_INFO, $redirectUrl);
    }

    /**
     * Redirects to a given URL with a warning message.
     *
     * @param string $message
     * @param string|null $redirectUrl
     * @return void
     * @since 2.0.0
     */
    public static function warning(string $message, ?string $redirectUrl = null): void
    {
        self::redirectWithMessage($message, self::TYPE_WARNING, $redirectUrl);
    }

    /**
     * Internal method to redirect with message + type as query args.
     *
     * @param string $message
     * @param string $type
     * @param string|null $redirectUrl
     * @return void
     * @since 2.0.0
     */
    protected static function redirectWithMessage(string $message, string $type, ?string $redirectUrl = null): void
    {
        $type    = apply_filters(FilterHooks::TOAST_TYPE, $type, $message);
        $message = apply_filters(FilterHooks::TOAST_MESSAGE, $message, $type);

        do_action(ActionHooks::TOAST_DISPATCHED, $message, $type, $redirectUrl);

        $url = $redirectUrl ?? remove_query_arg(['notifal_toast_message', 'notifal_toast_type']);

        $url = add_query_arg([
            'notifal_toast_message' => rawurlencode($message),
            'notifal_toast_type'    => $type,
        ], $url);

        wp_redirect($url);
        exit;
    }
}
