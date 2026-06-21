<?php

/**
 * Template Creation Options Component
 *
 * Renders the available template creation options (Elementor, WordPress Editor, HTML Builder, Import).
 * This component is used both in modal and full-page contexts.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\Templates\Presentation\Admin\ViewComponents
 */

use Notifal\Shared\Services\NotifalIconService;

if (!defined('ABSPATH')) {
    exit;
}

// Variables should be available from parent context.
// $elementor_installed, $urls (install_elementor, create_editor, create_elementor, create_html_builder, import_nonce)

?>

<div class="notifal-builder-options-grid notifal-mt-20">

    <!-- HTML Builder Option -->
    <div class="notifal-card notifal-builder-option">
        <div class="notifal-builder-option__icon">
            <?php echo NotifalIconService::render('file-earmark-code', 32); ?>
        </div>
        <h3 class="notifal-text-base notifal-font-bold">
            <?php esc_html_e('Build with HTML Builder', 'notifal'); ?>
        </h3>
        <p class="notifal-text-sm notifal-text-muted notifal-mt-10">
            <?php esc_html_e('Paste HTML from AI or any source; no Elementor or Gutenberg required.', 'notifal'); ?>
        </p>
        <div class="notifal-mt-20">
            <a href="<?php echo esc_url($urls['create_html_builder']); ?>" class="notifal-button">
                <?php esc_html_e('Open HTML Builder', 'notifal'); ?>
            </a>
        </div>
    </div>

    <!-- Elementor Builder Option -->
    <div class="notifal-card notifal-builder-option">
        <div class="notifal-builder-option__icon">
            <?php echo NotifalIconService::render('elementor', 32); ?>
        </div>
        <h3 class="notifal-text-base notifal-font-bold">
            <?php esc_html_e('Build with Elementor', 'notifal'); ?>
        </h3>
        <p class="notifal-text-sm notifal-text-muted notifal-mt-10">
            <?php
            echo $elementor_installed
                ? esc_html__('Design visually using the Elementor builder.', 'notifal')
                : esc_html__('Elementor plugin is not installed.', 'notifal');
            ?>
        </p>
        <div class="notifal-mt-20">
            <?php if ($elementor_installed) : ?>
                <a href="<?php echo esc_url($urls['create_elementor']); ?>" class="notifal-button">
                    <?php esc_html_e('Create with Elementor', 'notifal'); ?>
                </a>
            <?php else : ?>
                <a href="<?php echo esc_url($urls['install_elementor']); ?>" class="notifal-button secondary">
                    <?php esc_html_e('Install Elementor', 'notifal'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- WordPress Editor Option -->
    <div class="notifal-card notifal-builder-option">
        <div class="notifal-builder-option__icon">
            <?php echo NotifalIconService::render('wordpress', 32); ?>
        </div>
        <h3 class="notifal-text-base notifal-font-bold">
            <?php esc_html_e('Build with WordPress Editor', 'notifal'); ?>
        </h3>
        <p class="notifal-text-sm notifal-text-muted notifal-mt-10">
            <?php esc_html_e('Use the default WordPress editor to create your notification template.', 'notifal'); ?>
        </p>
        <div class="notifal-mt-20">
            <a href="<?php echo esc_url($urls['create_editor']); ?>" class="notifal-button">
                <?php esc_html_e('Create with Editor', 'notifal'); ?>
            </a>
        </div>
    </div>

    <!-- Import Template Option -->
    <div class="notifal-card notifal-builder-option">
        <div class="notifal-builder-option__icon">
            <?php echo NotifalIconService::render('download', 32); ?>
        </div>
        <h3 class="notifal-text-base notifal-font-bold">
            <?php esc_html_e('Import Template', 'notifal'); ?>
        </h3>
        <p class="notifal-text-sm notifal-text-muted notifal-mt-10">
            <?php esc_html_e('Upload a .notifal.json file you have exported or downloaded.', 'notifal'); ?>
        </p>
        <div class="notifal-mt-20">
            <button type="button"
                    id="notifal-import-trigger"
                    class="notifal-button"
                    data-loading-text="<?php esc_attr_e('Importing...', 'notifal'); ?>"
                    data-original-text="<?php esc_attr_e('Import Now', 'notifal'); ?>">
                <?php esc_html_e('Import Now', 'notifal'); ?>
            </button>
            <form id="notifal-import-form" method="post" enctype="multipart/form-data" class="notifal-hidden">
                <input type="file" id="notifal-import-file" name="notifal_import_file" accept=".json,.zip" required>
                <input type="hidden" name="action" value="notifal_import_ajax">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($urls['import_nonce']); ?>">
            </form>
        </div>
    </div>
</div>
