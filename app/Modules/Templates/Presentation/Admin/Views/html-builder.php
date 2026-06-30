<?php

/**
 * HTML Builder admin shell view.
 *
 * Renders the loader, React mount point, and PHP tags modal.
 *
 * @since 2.4.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\Templates\Presentation\Admin\Views
 *
 * @var \WP_Post $template
 * @var int      $template_id
 */

use Notifal\Modules\Templates\Presentation\Admin\Assets\HtmlBuilderAssets;
use Notifal\Shared\AdminUI\Loader\LoaderRenderer;
use Notifal\Shared\AdminUI\Toast\ToastRenderer;

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="notifal-html-builder-shell" id="notifal-html-builder-shell">
    <?php LoaderRenderer::render(LoaderRenderer::HTML_BUILDER_LOADER_ID, false, true); ?>

    <div id="notifal-html-builder-root" class="notifal-html-builder-root"></div>

    <?php ToastRenderer::renderGlobalContainer(); ?>

    <div class="notifal-modal-backdrop" id="notifal-html-builder-tags-modal" role="dialog" aria-modal="true" aria-labelledby="notifal-html-builder-tags-modal-title">
        <div class="notifal-modal notifal-html-builder-tags-modal">
            <div class="notifal-modal-header">
                <h2 id="notifal-html-builder-tags-modal-title"><?php esc_html_e('Insert Notifal Tag', 'notifal'); ?></h2>
                <button type="button" class="notifal-modal-close" id="notifal-html-builder-tags-close" aria-label="<?php esc_attr_e('Close', 'notifal'); ?>">
                    <span class="notifal-icon notifal-icon-x-circle size-16"></span>
                </button>
            </div>
            <div class="notifal-modal-body">
                <?php echo HtmlBuilderAssets::renderTagsPanel(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in TagsRenderer ?>
            </div>
        </div>
    </div>
</div>
