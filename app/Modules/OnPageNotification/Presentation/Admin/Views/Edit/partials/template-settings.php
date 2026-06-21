<?php

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\Templates\Infrastructure\WordPress\Repositories\TemplateQuery;
use Notifal\Modules\Templates\Presentation\Admin\ViewComponents\TemplateRenderer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Template Settings Tab
 *
 * Handles the display and configuration of notification template settings
 * including template selection for different builders and empty states.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Views\Edit\partials
 */

/**
 * Render loading state HTML for template grids.
 *
 * @return void
 * @since 2.0.0
 */
function render_template_loading_state(): void {
    ?>
    <div class="notifal-loading-state">
        <div class="notifal-loading-spinner"></div>
        <p><?php esc_html_e('Loading templates...', 'notifal'); ?></p>
    </div>
    <?php
}

$tab = 'template';

do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_BEFORE, $tab));

// Get selected template ID - check multiple sources
$selected_template = 0;

// First, try global notification data
if (isset($GLOBALS['notifal_notification_data']['template_id']) && is_numeric($GLOBALS['notifal_notification_data']['template_id'])) {
    $selected_template = absint($GLOBALS['notifal_notification_data']['template_id']);
}

// Second, if we're in edit mode, try to get from post meta
$get_action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
$get_id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
if ( $selected_template === 0 && $get_action === 'edit' && $get_id > 0 ) {
    $notification_id = $get_id;
    if ($notification_id > 0) {
        $template_id = get_post_meta($notification_id, '_notifal_template_id', true);
        if ($template_id && is_numeric($template_id)) {
            $selected_template = absint($template_id);
        }
    }
}

// Admin builder slug used in data-builder attributes and AJAX requests.
$html_builder_slug = 'html-builder';

// Fetch templates, prioritizing selected template (HTML Builder first).
$html_builder_templates = TemplateQuery::getByBuilder($html_builder_slug, 6, $selected_template);
$html_builder_count     = TemplateQuery::getByBuilderCount($html_builder_slug);

$elementor_templates = TemplateQuery::getByBuilder('elementor', 6, $selected_template);
$elementor_count     = TemplateQuery::getByBuilderCount('elementor');

$block_templates = TemplateQuery::getByBuilder('block-editor', 6, $selected_template);
$block_count     = TemplateQuery::getByBuilderCount('block-editor');

$no_template_found = empty($html_builder_templates) && empty($elementor_templates) && empty($block_templates);
?>

<div class="notifal-settings-section notifal-<?php echo esc_attr( $tab ); ?>-settings">
    <div class="notifal-template-header">
        <h1><?php esc_html_e('Notification Template', 'notifal'); ?></h1>
        <button type="button" class="notifal-button secondary notifal-template-refresh-btn" id="notifal-template-refresh-btn">
            <span class="notifal-icon notifal-icon-arrow-repeat"></span>
            <?php esc_html_e('Refresh Templates', 'notifal'); ?>
        </button>
    </div>

    <div class="notifal-template-selector">

        <?php if (!empty($html_builder_templates)) : ?>
            <div class="notifal-template-group" data-builder="<?php echo esc_attr($html_builder_slug); ?>">
                <h2 class="notifal-section-title"><?php esc_html_e('HTML Builder Templates', 'notifal'); ?></h2>
                <div class="notifal-template-grid" data-builder="<?php echo esc_attr($html_builder_slug); ?>">
                    <?php render_template_loading_state(); ?>
                </div>
                <?php if ($html_builder_count > 6) : ?>
                    <button class="notifal-load-more notifal-button secondary notifal-m-auto notifal-block notifal-hidden" data-builder="<?php echo esc_attr($html_builder_slug); ?>">
                        <?php esc_html_e('Load More', 'notifal'); ?>
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (PluginDetector::isElementorActive() && !empty($elementor_templates)) : ?>
            <div class="notifal-template-group" data-builder="elementor">
                <h2 class="notifal-section-title"><?php esc_html_e('Elementor Templates', 'notifal'); ?></h2>
                <div class="notifal-template-grid" data-builder="elementor">
                    <?php render_template_loading_state(); ?>
                </div>
                <?php if ($elementor_count > 6): ?>
                    <button class="notifal-load-more notifal-button secondary notifal-m-auto notifal-block notifal-hidden" data-builder="elementor">
                        <?php esc_html_e('Load More', 'notifal'); ?>
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($block_templates)) : ?>
            <div class="notifal-template-group" data-builder="block-editor">
                <h2 class="notifal-section-title"><?php esc_html_e('Block Editor Templates', 'notifal'); ?></h2>
                <div class="notifal-template-grid" data-builder="block-editor">
                    <?php render_template_loading_state(); ?>
                </div>
                <?php if ($block_count > 6): ?>
                    <button class="notifal-load-more notifal-button secondary notifal-m-auto notifal-block notifal-hidden" data-builder="block-editor">
                        <?php esc_html_e('Load More', 'notifal'); ?>
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($no_template_found) : ?>
            <div class="notifal-template-group">
                <p><?php esc_html_e('No templates found. Please create one first.', 'notifal'); ?></p>
                <a class="notifal-button secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=notifal_templates' ) ); ?>">
                    <?php esc_html_e('Go to templates', 'notifal'); ?>
                </a>
            </div>
        <?php endif; ?>

        <!-- No templates found for content type (will be shown/hidden by JavaScript) -->
        <div class="notifal-template-group notifal-no-templates-found notifal-hidden">
            <div class="notifal-empty-state">
                <div class="notifal-empty-icon">📝</div>
                <h3><?php esc_html_e('No Templates Found', 'notifal'); ?></h3>
                <p><?php esc_html_e('No templates found for the selected content type. Try changing the Content Source Type in General Settings or create new templates.', 'notifal'); ?></p>
                <a class="notifal-button secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=notifal_templates' ) ); ?>">
                    <?php esc_html_e('Go to templates', 'notifal'); ?>
                </a>
            </div>
        </div>

    </div>
</div>

<?php
do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_AFTER, $tab));
?>
