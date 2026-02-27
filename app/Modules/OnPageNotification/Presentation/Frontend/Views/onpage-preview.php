<?php

/**
 * OnPage Notification Preview View
 *
 * Minimal layout for admin preview: shows notification title, status, a short note
 * that some settings are ignored, and the same container used on the frontend so the
 * notification renders identically to the live site.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Presentation\Frontend\Views
 */

defined('ABSPATH') || exit;

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body class="notifal-onpage-preview-body">
    <div class="notifal-onpage-preview-wrapper">
        <header class="notifal-onpage-preview-header">
            <div class="notifal-onpage-preview-header-inner">
                <h1 class="notifal-onpage-preview-title"><?php echo esc_html($title); ?></h1>
                <span class="notifal-onpage-preview-status notifal-onpage-preview-status--<?php echo $notif_enabled ? 'live' : 'draft'; ?>">
                    <?php echo $notif_enabled ? esc_html__('Live & Active', 'notifal') : esc_html__('Draft', 'notifal'); ?>
                </span>
                <a href="<?php echo esc_url($edit_url); ?>" class="notifal-onpage-preview-edit-link">
                    <?php esc_html_e('Edit notification', 'notifal'); ?>
                </a>
            </div>
            <p class="notifal-onpage-preview-note">
                <?php esc_html_e('This is a preview only. Display rules, frequency limits, and schedule are not applied. Analytics and clicks are not recorded.', 'notifal'); ?>
            </p>
        </header>
        <main class="notifal-onpage-preview-main">
            <div id="notifal-onpage-container" class="notifal-onpage-container" role="region" aria-label="<?php esc_attr_e('OnPage notification preview', 'notifal'); ?>"></div>
        </main>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
