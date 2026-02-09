<?php

use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Shared\Services\NotifalIconService;

if (!defined('ABSPATH')) exit;

/**
 * Notification Detail Popup Component
 *
 * Shared component for rendering notification detail popup/modal content.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

/**
 * Render notification detail popup content.
 *
 * @param string $context Context identifier for element IDs (e.g., 'modal', 'archive')
 * @return void
 *
 * @since 2.0.0
 */
function render_notification_detail_popup(string $context = 'archive'): void {
    $id_prefix = $context === 'modal' ? 'notifalModal' : 'notifal';
    $use_preview_layout = ( $context === 'archive' );

    ?>
    <div class="notifal-popup-body">
        <!-- Preview Image with Switcher -->
        <div class="notifal-popup-preview">
            <?php if ( $use_preview_layout ) : ?>
                <!-- Archive: same preview-area UI as marketplace (bricks + layout-specific styles) -->
                <div class="notifal-popup-preview-area preview-area">
                    <div class="image-holder notifal-layout--popup" id="notifalPopupPreviewWrapper" data-device="desktop">
                        <div class="notifal-layout-holder">
                            <div class="notifal-layout-site">
                                <div class="notifal-layout-cover-wrapper">
                                    <div class="notifal-layout-cover-site-header">
                                        <span class="notifal-layout-cover-logo"></span>
                                        <span class="notifal-layout-cover-nav-links"></span>
                                    </div>
                                    <div class="notifal-layout-cover-hero"></div>
                                    <div class="notifal-layout-cover-main">
                                        <div class="notifal-layout-cover-content">
                                            <div class="notifal-layout-cover-heading"></div>
                                            <span class="notifal-layout-cover-line"></span>
                                            <span class="notifal-layout-cover-line notifal-layout-cover-line--short"></span>
                                            <div class="notifal-layout-cover-body"></div>
                                            <div class="notifal-layout-cover-media"></div>
                                            <span class="notifal-layout-cover-line"></span>
                                            <span class="notifal-layout-cover-line notifal-layout-cover-line--short"></span>
                                            <div class="notifal-layout-cover-cta"></div>
                                        </div>
                                        <div class="notifal-layout-cover-sidebar">
                                            <div class="notifal-layout-cover-card"></div>
                                            <div class="notifal-layout-cover-card"></div>
                                        </div>
                                    </div>
                                    <div class="notifal-layout-cover-footer"></div>
                                </div>
                                <div class="notifal-layout-feature" id="<?php echo esc_attr( $id_prefix ); ?>PopupImageHolder">
                                    <div class="notifal-popup-image-placeholder">
                                        <span><?php esc_html_e( 'No preview available', 'notifal' ); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="notifal-popup-toolbar toolbar">
                        <button type="button" class="notifal-popup-device-btn icon-btn active" id="<?php echo esc_attr( $id_prefix ); ?>PopupDesktopBtn" data-device="desktop" aria-label="<?php esc_attr_e( 'Desktop Preview', 'notifal' ); ?>">
                            <?php echo NotifalIconService::render( 'laptop', 16 ); ?>
                        </button>
                        <button type="button" class="notifal-popup-device-btn icon-btn" id="<?php echo esc_attr( $id_prefix ); ?>PopupMobileBtn" data-device="mobile" aria-label="<?php esc_attr_e( 'Mobile Preview', 'notifal' ); ?>">
                            <?php echo NotifalIconService::render( 'phone', 16 ); ?>
                        </button>
                    </div>
                </div>
            <?php else : ?>
                <!-- Modal: simple image holder -->
                <div class="notifal-popup-image-holder" id="<?php echo esc_attr( $id_prefix ); ?>PopupImageHolder">
                    <div class="notifal-popup-image-placeholder">
                        <span><?php esc_html_e( 'No preview available', 'notifal' ); ?></span>
                    </div>
                </div>
                <div class="notifal-popup-toolbar">
                    <button type="button" class="notifal-popup-device-btn active" id="<?php echo esc_attr( $id_prefix ); ?>PopupDesktopBtn" data-device="desktop" aria-label="<?php esc_attr_e( 'Desktop Preview', 'notifal' ); ?>">
                        <?php echo NotifalIconService::render( 'laptop', 16 ); ?>
                    </button>
                    <button type="button" class="notifal-popup-device-btn" id="<?php echo esc_attr( $id_prefix ); ?>PopupMobileBtn" data-device="mobile" aria-label="<?php esc_attr_e( 'Mobile Preview', 'notifal' ); ?>">
                        <?php echo NotifalIconService::render( 'phone', 16 ); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Notification Title -->
        <h2 class="notifal-popup-notification-title"></h2>

        <!-- Taxonomy Badges -->
        <div class="notifal-popup-badges"></div>

        <!-- Description -->
        <div class="notifal-popup-description"></div>
    </div>

    <!-- Loading State -->
    <div class="notifal-popup-loading notifal-popup-hidden">
        <div class="notifal-loading-spinner"></div>
        <p><?php esc_html_e('Loading notification details...', 'notifal'); ?></p>
    </div>

    <!-- Error State -->
    <div class="notifal-popup-error notifal-popup-hidden">
        <p class="notifal-popup-error-message"></p>
    </div>
    <?php
}

/**
 * Render notification detail popup footer.
 *
 * @param string $context Context identifier for element IDs (e.g., 'modal', 'archive')
 * @return void
 *
 * @since 2.0.0
 */
function render_notification_detail_popup_footer(string $context = 'archive'): void {
    $id_prefix = $context === 'modal' ? 'notifalModal' : 'notifal';
    $class_prefix = $context === 'modal' ? 'notifal-modal-detail-footer' : 'notifal-popup-footer';

    ?>
    <div class="notifal-popup-import-buttons">
        <?php
        // Only show Import Elementor when Elementor plugin is installed and active on the site.
        if (PluginDetector::isElementorActive()) :
            ?>
            <button type="button" class="notifal-popup-import-btn notifal-popup-import-elementor" id="<?php echo esc_attr($id_prefix); ?>PopupImportElementorBtn" data-file-type="elementor">
                <?php echo NotifalIconService::render('cloud-download', 16); ?>
                <?php esc_html_e('Import Elementor', 'notifal'); ?>
            </button>
            <?php
        endif;
        ?>
        <button type="button" class="notifal-popup-import-btn notifal-popup-import-block-editor" id="<?php echo esc_attr($id_prefix); ?>PopupImportBlockEditorBtn" data-file-type="block-editor">
            <?php echo NotifalIconService::render('cloud-download', 16); ?>
            <?php esc_html_e('Import Block Editor', 'notifal'); ?>
        </button>
    </div>
    <?php
}