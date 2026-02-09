<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Utility;

use Notifal\Infrastructure\WordPress\Security\NonceManager;

defined('ABSPATH') || exit;

/**
 * Class UrlService
 *
 * Handles URLs and nonce generation for OnPage Notification module actions.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class UrlService
{
    /**
     * Get URL to create a new OnPage notification.
     *
     * @return string
     * @since 2.0.0
     */
    public function getCreateNotificationUrl(): string
    {
        return admin_url('admin.php?page=notifal-onpage-notification');
    }

    /**
     * Get URL to edit an existing OnPage notification.
     *
     * @param int $notificationId The notification ID
     * @return string
     * @since 2.0.0
     */
    public function getEditNotificationUrl(int $notificationId): string
    {
        return admin_url("admin.php?page=notifal-onpage-notification&action=edit&id={$notificationId}");
    }

    /**
     * Get URL for the OnPage notifications list page.
     *
     * @param string $status Optional status filter for the list URL
     * @return string
     * @since 2.0.0
     */
    public function getListUrl(string $status = ''): string
    {
        $url = admin_url('admin.php?page=notifal-onpage-notifications');
        if (!empty($status)) {
            $url .= '&status=' . urlencode($status);
        }
        return $url;
    }

    /**
     * Get nonce for OnPage notification actions.
     *
     * @param string $action The action name
     * @return string
     * @since 2.0.0
     */
    public function getActionNonce(string $action): string
    {
        return NonceManager::create("notifal_onpage_notification_{$action}");
    }
}
