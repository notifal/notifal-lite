<?php

use Notifal\Modules\OnPageNotification\Application\Services\Utility\UrlService;
use Notifal\Modules\OnPageNotification\Presentation\Admin\Components\NotificationDetailPopupComponent;
use Notifal\Shared\Services\NotifalIconService;

if (!defined('ABSPATH')) exit;

/**
 * Pre-created Notifications Modal Component
 *
 * Modal popup version of pre-created notifications archive for "Add New" functionality.
 * Displays the archive view in a modal with navigation to details within the same modal.
 * Uses shared components to avoid code duplication.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

// Include required components
require_once __DIR__ . '/../../Components/NotificationDetailPopupComponent.php';

// Get required service instances
$urlService = notifal_app(UrlService::class);

?>

<!-- Pre-created Notifications Modal -->
<div class="notifal-modal-backdrop" id="notifal-precreated-modal">
    <div class="notifal-modal notifal-precreated-modal">
        <!-- Modal Header -->
        <div class="notifal-modal-header">
            <div class="notifal-modal-header-content">
                <h3 class="notifal-modal-title" id="notifal-modal-title">
                    <?php esc_html_e('Explore Pre-created Notifications', 'notifal'); ?>
                </h3>
                <button type="button" class="notifal-modal-back-btn notifal-hidden" id="notifal-modal-back-btn">
                    <?php echo NotifalIconService::render('arrow-left', 20); ?>
                    <?php esc_html_e('Back to List', 'notifal'); ?>
                </button>
            </div>
            <div class="notifal-modal-header-actions">
                <a href="<?php echo esc_url($urlService->getCreateNotificationUrl()); ?>"
                   class="notifal-button notifal-flex notifal-gap-10 notifal-create-from-scratch-btn">
                    <?php echo NotifalIconService::render('plus-circle', 16); ?>
                    <?php esc_html_e('Create From Scratch', 'notifal'); ?>
                </a>
                <button type="button" class="notifal-modal-close" aria-label="<?php esc_attr_e('Close', 'notifal'); ?>">
                    <?php echo NotifalIconService::render('x-circle', 24); ?>
                </button>
            </div>
        </div>

        <!-- Modal Content -->
        <div class="notifal-modal-content">
            <!-- Archive View (Default) -->
            <div class="notifal-modal-view notifal-modal-archive-view" id="notifal-modal-archive-view">
                <div class="notifal-marketplace-archive" data-component="precreated-notifications-modal-archive">
                    <div
                        id="notifal-modal-precreated-archive-container"
                        class="notifal-modal-archive-host"
                        data-notifal-archive-async="1"
                        data-archive-lazy="1"
                    >
                        <div
                            class="notifal-precreated-archive-loading"
                            aria-live="polite"
                            role="status"
                            aria-label="<?php esc_attr_e('Loading pre-created notifications...', 'notifal'); ?>"
                        >
                            <div class="notifal-precreated-archive-loading-spinner" aria-hidden="true"></div>
                            <p class="notifal-precreated-archive-loading-text">
                                <?php esc_html_e('Loading pre-created notifications...', 'notifal'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detail View (Hidden by default) -->
            <div class="notifal-modal-view notifal-modal-detail-view notifal-hidden" id="notifal-modal-detail-view">
                <?php render_notification_detail_popup('modal'); ?>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="notifal-modal-footer notifal-modal-detail-footer notifal-hidden" id="notifal-modal-detail-footer">
            <?php render_notification_detail_popup_footer('modal'); ?>
        </div>
        <?php // Template request note disabled for now. render_notification_detail_popup_request_note('modal'); ?>
    </div>
</div>

<?php
