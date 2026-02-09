<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\Routes;

defined('ABSPATH') || exit;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\UrlService;
use Notifal\Shared\AdminUI\Traits\AdminOperationsTrait;
use Notifal\Shared\Utils\Helper;

/**
 * Class AdminRouteController
 *
 * Handles admin-side routes/actions for on-page notifications dispatched via `admin_init`.
 * Provides CRUD operations including delete, duplicate, and empty trash functionality
 * with proper security validation and user feedback.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class AdminRouteController
{
    use AdminOperationsTrait;

    /**
     * Hook prefix for action hooks.
     */
    protected const HOOK_PREFIX = 'Notifal\\Infrastructure\\WordPress\\Hooks\\ActionHooks::';

    /**
     * Post type constant for on-page notifications.
     */
    protected const POST_TYPE = 'notifal_onpage_notif';

    /**
     * Registers the admin route dispatcher.
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        add_action('admin_init', [self::class, 'dispatch']);
    }

    /**
     * Dispatch the route based on `$_GET['action']`.
     *
     * @return void
     * @since 2.0.0
     */
    public static function dispatch(): void
    {
        if (!is_admin()) {
            return;
        }

        $action = Helper::sanitizeInput($_GET['action'] ?? '', 'text');

        switch ($action) {
            case 'notifal_delete_notifal_onpage_notif':
                self::handleDeleteNotification();
                break;

            case 'notifal_duplicate_notifal_onpage_notif':
                self::handleDuplicateNotification();
                break;

            case 'notifal_empty_trash_notifal_onpage_notif':
                self::handleEmptyTrashAction();
                break;
        }
    }

    /**
     * Handles deleting a single notification.
     *
     * @return void
     * @since 2.0.0
     */
    protected static function handleDeleteNotification(): void
    {
        $postId = absint($_GET['id'] ?? 0);
        $urlService = notifal_app(UrlService::class);

        self::handleDeletePost(
            $postId,
            "delete_notifal_onpage_notif_$postId",
            self::POST_TYPE,
            function ($status) use ($urlService) {
                return $urlService->getListUrl($status);
            },
            [
                'deleted' => 'ONPAGE_NOTIFICATION_DELETED',
                'trashed' => 'ONPAGE_NOTIFICATION_TRASHED',
            ]
        );
    }

    /**
     * Handles duplicating a single notification.
     *
     * @return void
     * @since 2.0.0
     */
    protected static function handleDuplicateNotification(): void
    {
        $postId = absint($_GET['id'] ?? 0);
        $urlService = notifal_app(UrlService::class);

        self::handleDuplicatePost(
            $postId,
            "duplicate_notifal_onpage_notif_$postId",
            self::POST_TYPE,
            function ($status) use ($urlService) {
                return $urlService->getListUrl($status);
            },
            'ONPAGE_NOTIFICATION_DUPLICATED'
        );
    }

    /**
     * Empties the trash for on-page notifications.
     *
     * @return void
     * @since 2.0.0
     */
    protected static function handleEmptyTrashAction(): void
    {
        $urlService = notifal_app(UrlService::class);

        self::handleEmptyTrash(
            self::POST_TYPE,
            'empty_trash_notifal_onpage_notif',
            function ($status = '') use ($urlService) {
                return $status === 'trash' ? $urlService->getListUrl('trash') : $urlService->getListUrl();
            },
            'ONPAGE_NOTIFICATIONS_TRASH_EMPTIED'
        );
    }
} 
