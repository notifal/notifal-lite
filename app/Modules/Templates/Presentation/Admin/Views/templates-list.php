<?php

/**
 * Templates List Admin Page
 *
 * Renders the admin list page for Notifal Templates with filtering, searching,
 * and bulk actions capabilities. Includes template creation options when no templates exist.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\Templates\Presentation\Admin\Views
 */

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Shared\AdminUI\Lists\BaseListView;
use Notifal\Shared\AdminUI\Toast\ToastRenderer;
use Notifal\Infrastructure\WordPress\Admin\Helpers\AdminStatsService;
use Notifal\Modules\Templates\Application\Services\TemplateUrlService;

if (!defined('ABSPATH')) {
    exit;
}

// Initialize services using dependency injection
$adminStatsService = notifal_app(AdminStatsService::class);
$urlService        = notifal_app(TemplateUrlService::class);

// Get data for rendering
$template_total = $adminStatsService->getTotalPosts('notifal_template');
$status_tabs    = $adminStatsService->getStatusTabs('notifal_template');
$add_new_url    = $urlService->getCreateEditorUrl();

/**
 * Fires before rendering Templates list admin page.
 *
 * Allows developers to add custom content or modify data before the templates list is rendered.
 *
 * @since 2.0.0
 * @param int $template_total Total number of templates
 * @param array $status_tabs Status tab data for filtering
 * @param string $add_new_url URL for creating new templates
 */
do_action(ActionHooks::ADMIN_TEMPLATES_BEFORE, $template_total, $status_tabs, $add_new_url);
?>

<div class="wrap notifal-admin-page">
    <?php do_action(ActionHooks::ADMIN_PAGE_CONTENT_BEFORE); ?>
    <h1></h1>

    <div class="template-list-parent notifal-mt-20">
        <?php if ($template_total > 0): ?>
            <?php
            $listView = new BaseListView([
                'title'              => __('Templates', 'notifal'),
                'post_type'          => 'notifal_template',
                'add_new_url'        => esc_url($add_new_url),
                'search_placeholder' => __('Search templates...', 'notifal'),
                'columns'            => [
                    'title'    => __('Title', 'notifal'),
                    'category' => __('Category', 'notifal'),
                    'builder'  => __('Builder', 'notifal'),
                    'date'     => __('Date', 'notifal'),
                ],
                'per_page'     => 15,
                'bulk_actions' => [
                    'delete' => __('Move to Trash', 'notifal'),
                    'export' => __('Export', 'notifal'),
                ],
                'status_tabs'  => $status_tabs,
                'bulk_actions_handled' => true, // Bulk actions handled by MenuController
            ]);

            // Add custom header actions hook for template creation modal button
            add_action('notifal_list_header_actions', function() {
                echo '<button type="button" class="notifal-button secondary notifal-flex notifal-gap-10" id="notifal-create-template-btn">'
                    . \Notifal\Shared\Services\NotifalIconService::render('plus-circle', 20)
                    . esc_html__('Create New Template', 'notifal')
                    . '</button>';
            });

            $listView->render();
            ?>
        <?php endif; ?>

        <?php if ($template_total === 0): ?>
            <?php
            // Show creation options directly when no templates exist
            include_once __DIR__ . '/new-template.php';
            ?>
        <?php endif; ?>
    </div>
</div>

<!-- Template Creation Modal -->
<div class="notifal-modal-backdrop" id="notifal-template-creation-modal" style="display: none;">
    <div class="notifal-modal">
        <div class="notifal-modal-header">
            <h2><?php esc_html_e('Create New Template', 'notifal'); ?></h2>
            <button type="button" class="notifal-modal-close" aria-label="<?php esc_attr_e('Close', 'notifal'); ?>"><span class="notifal-icon notifal-icon-x-circle size-16"></span></button>
        </div>
        <div class="notifal-modal-body">
            <?php
            // Set variables for the component
            $plugin_detector = notifal_app(\Notifal\Infrastructure\WordPress\Support\PluginDetector::class);
            $url_service = notifal_app(\Notifal\Modules\Templates\Application\Services\TemplateUrlService::class);
            $elementor_installed = $plugin_detector->isElementorActive();
            $urls = [
                'install_elementor' => $url_service->getElementorInstallUrl(),
                'create_editor'     => $url_service->getCreateEditorUrl(),
                'create_elementor'  => $url_service->getCreateElementorUrl(),
                'import_nonce'      => $url_service->getImportNonce(),
            ];
            include_once __DIR__ . '/components/template-creation-options.php';
            ?>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="notifal-modal-backdrop" id="notifal-import-modal">
    <div class="notifal-modal">
        <div class="notifal-modal-header">
            <h2><?php esc_html_e('Import Templates', 'notifal'); ?></h2>
            <button type="button" class="notifal-modal-close" aria-label="<?php esc_attr_e('Close', 'notifal'); ?>"><span class="notifal-icon notifal-icon-x-circle size-16"></span></button>
        </div>
        <div class="notifal-modal-body">
            <p class="notifal-text-center notifal-text-muted">
                <?php esc_html_e('Upload a JSON or ZIP file to import templates. Make sure the file was exported from a trusted source.', 'notifal'); ?>
            </p>

            <form id="notifal-import-form" enctype="multipart/form-data" class="notifal-mt-20">
                <?php
                $import_nonce = \Notifal\Infrastructure\WordPress\Security\NonceManager::create('notifal_import_ajax_nonce');
                wp_nonce_field('notifal_import_ajax_nonce', '_wpnonce', false);
                ?>
                <input type="hidden" name="action" value="notifal_import_ajax">

                <div class="notifal-form-group">
                    <div class="notifal-file-upload-area" id="notifal-file-upload-area">
                        <div class="notifal-file-upload-icon">
                            <?php echo \Notifal\Shared\Services\NotifalIconService::render('cloud-arrow-up', 48); ?>
                        </div>
                        <div class="notifal-file-upload-text">
                            <span class="notifal-file-upload-title"><?php esc_html_e('Drop your file here', 'notifal'); ?></span>
                            <span class="notifal-file-upload-subtitle"><?php esc_html_e('or click to browse', 'notifal'); ?></span>
                        </div>
                        <div class="notifal-file-upload-formats">
                            <?php esc_html_e('Supported formats: JSON, ZIP (max 10MB)', 'notifal'); ?>
                        </div>
                        <input type="file"
                               id="notifal_import_file"
                               name="notifal_import_file"
                               accept=".json,.zip"
                               class="notifal-file-input"
                               required>
                    </div>

                    <div class="notifal-file-info notifal-hidden" id="notifal-file-info">
                        <div class="notifal-file-info-content">
                            <span class="notifal-file-info-icon">
                                <?php echo \Notifal\Shared\Services\NotifalIconService::render('file-earmark', 20); ?>
                            </span>
                            <span class="notifal-file-info-name" id="notifal-file-name"></span>
                            <button type="button" class="notifal-file-remove" id="notifal-file-remove" aria-label="<?php esc_attr_e('Remove file', 'notifal'); ?>">
                                <?php echo \Notifal\Shared\Services\NotifalIconService::render('x-circle', 16); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div id="notifal-import-progress" class="notifal-progress notifal-hidden">
                    <div class="notifal-progress-bar">
                        <div class="notifal-progress-fill"></div>
                    </div>
                    <p class="notifal-progress-text"><?php esc_html_e('Importing...', 'notifal'); ?></p>
                </div>

                <div class="notifal-text-center notifal-text-sm notifal-text-muted notifal-mt-20">
                    <p><?php esc_html_e('Note: Imported templates will be published immediately and ready to use.', 'notifal'); ?></p>
                </div>
            </form>
        </div>

        <div class="notifal-modal-footer">
            <button type="submit" class="notifal-button notifal-full-width" id="notifal-import-submit" form="notifal-import-form">
                <?php esc_html_e('Import Templates', 'notifal'); ?>
            </button>
        </div>
    </div>
</div>

<?php
/**
 * Fires after rendering Templates list admin page.
 *
 * Allows developers to add custom content or perform cleanup after the templates list is rendered.
 *
 * @since 2.0.0
 * @param int $template_total Total number of templates
 * @param array $status_tabs Status tab data for filtering
 * @param string $add_new_url URL for creating new templates
 */
do_action(ActionHooks::ADMIN_TEMPLATES_AFTER, $template_total, $status_tabs, $add_new_url);
?>

<?php
// Render global toast container for dynamic notifications
ToastRenderer::renderGlobalContainer();
?>
