<?php

/**
 * New Template Creation View Component
 *
 * Renders the template creation options UI. Can be displayed as a modal or full-page layout
 * depending on the context (when no templates exist vs. adding new template).
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\Templates\Presentation\Admin\Views
 */

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\Templates\Application\Services\TemplateUrlService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fires before rendering the new template creation view.
 *
 * @since 2.0.0
 * @param bool $is_modal Whether the view is rendered as a modal
 */
do_action(ActionHooks::TEMPLATE_CREATION_BEFORE, $is_modal ?? false);

// Initialize services using dependency injection
$plugin_detector = notifal_app(PluginDetector::class);
$url_service     = notifal_app(TemplateUrlService::class);

// Get configuration data
$elementor_installed = $plugin_detector->isElementorActive();
$urls = [
    'install_elementor'             => $url_service->getElementorInstallUrl(),
    'create_editor'                 => $url_service->getCreateEditorUrl(),
    'create_elementor'              => $url_service->getCreateElementorUrl(),
    'create_html_builder'           => $url_service->getCreateHtmlBuilderUrl(),
    'create_html_builder_with_noti' => $url_service->getCreateHtmlBuilderUrl(true),
    'import_nonce'                  => $url_service->getImportNonce(),
];

$is_modal = isset($as_modal) && $as_modal === true;

?>

<?php if ($is_modal): ?>
    <div class="notifal-modal-backdrop" id="notifal-template-modal">
        <div class="notifal-modal notifal-template-create-modal">
            <div class="notifal-modal-header">
                <h2><?php esc_html_e('Create New Template', 'notifal'); ?></h2>
                <button type="button" class="notifal-modal-close" aria-label="<?php esc_attr_e('Close', 'notifal'); ?>"><span class="notifal-icon notifal-icon-x-circle size-16"></span></button>
            </div>

            <div class="notifal-modal-body">
                <?php include __DIR__ . '/components/template-creation-options.php'; ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php
    // Empty-state layout: show Noti invite and builder options only (no intro heading).
    ?>
    <div class="notifal-no-templates notifal-card notifal-flex notifal-direction-column notifal-align-center notifal-justify-center notifal-mt-20 notifal-text-center">
        <?php include __DIR__ . '/components/template-creation-options.php'; ?>
    </div>
<?php endif; ?>

<?php
/**
 * Fires after rendering the new template creation view.
 *
 * @since 2.0.0
 * @param bool $is_modal Whether the view was rendered as a modal
 */
do_action(ActionHooks::TEMPLATE_CREATION_AFTER, $is_modal ?? false);
?>