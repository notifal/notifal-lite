<?php

namespace Notifal\Modules\Templates\Application\Services;


use Notifal\Infrastructure\WordPress\Elementor\Helpers\ElementorHelper;
use Notifal\Infrastructure\WordPress\Security\NonceManager;
use Notifal\Modules\Templates\Application\Services\TemplateBuilderDetector;
use Notifal\Modules\Templates\Presentation\Admin\Controllers\HtmlBuilderPageController;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Class TemplateUrlService
 *
 * Handles URLs and nonce generation for Templates module actions.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class TemplateUrlService
{
    /**
     * Get URL to create a new template with WordPress Editor.
     *
     * @return string
     * @since 2.0.0
     */
    public function getCreateEditorUrl(): string
    {
        return admin_url('post-new.php?post_type=notifal_template');
    }

    /**
     * Get URL to create a new template with Elementor.
     *
     * @return string
     * @since 2.0.0
     */
    public function getCreateElementorUrl(): string
    {
        $nonce = NonceManager::create('notifal_create_elementor_template');
        return add_query_arg([
            'action'   => 'notifal_create_elementor_template',
            '_wpnonce' => $nonce,
        ], admin_url('admin.php'));
    }

    /**
     * Get URL to create a new template with the HTML Builder.
     *
     * @return string
     * @since 2.4.0
     */
    public function getCreateHtmlBuilderUrl(): string
    {
        $nonce = NonceManager::create('notifal_create_notifal_html_builder');

        return add_query_arg([
            'action'   => 'notifal_create_notifal_html_builder',
            '_wpnonce' => $nonce,
        ], admin_url('admin.php'));
    }

    /**
     * Get URL to edit a template in the HTML Builder.
     *
     * @param int $templateId Template post ID.
     * @return string
     * @since 2.4.0
     */
    public function getEditHtmlBuilderUrl(int $templateId): string
    {
        return add_query_arg(
            [
                'page'        => HtmlBuilderPageController::PAGE_SLUG,
                'template_id' => $templateId,
            ],
            admin_url('admin.php')
        );
    }

    /**
     * Resolve the correct edit URL for a template based on its builder.
     *
     * @param WP_Post $template Template post object.
     * @return string Edit URL.
     * @since 2.4.0
     */
    public function getEditUrl(WP_Post $template): string
    {
        $builder = TemplateBuilderDetector::getBuilder($template);

        if ($builder === TemplateBuilderDetector::BUILDER_HTML) {
            return $this->getEditHtmlBuilderUrl($template->ID);
        }

        if ($builder === TemplateBuilderDetector::BUILDER_ELEMENTOR) {
            return ElementorHelper::getEditUrl($template->ID);
        }

        $editLink = get_edit_post_link($template->ID, 'raw');

        return $editLink ? $editLink : admin_url('post.php?post=' . $template->ID . '&action=edit');
    }

    /**
     * Get URL to install Elementor plugin.
     *
     * @return string
     * @since 2.0.0
     */
    public function getElementorInstallUrl(): string
    {
        return admin_url('plugin-install.php?s=elementor&tab=search&type=term');
    }

    /**
     * Get nonce for template import.
     *
     * @return string
     * @since 2.0.0
     */
    public function getImportNonce(): string
    {
        return NonceManager::create('notifal_import_ajax_nonce');
    }

    /**
     * Get the authenticated frontend preview URL for a template post.
     *
     * Templates are internal (non-public) posts; preview uses a query argument
     * handled by PreviewRouteController instead of pretty permalinks.
     *
     * @param int          $templateId Template post ID.
     * @param WP_Post|null $template   Optional template post (avoids extra query).
     * @return string Preview URL for admins/editors.
     * @since 2.2.5
     */
    public function getPreviewUrl(int $templateId, ?WP_Post $template = null): string
    {
        // Resolve the template post when the caller did not pass it.
        if (!$template instanceof WP_Post) {
            $template = get_post($templateId);
        }

        // Cache-bust iframe previews when the template was updated.
        $version = ($template instanceof WP_Post && $template->post_modified_gmt)
            ? strtotime($template->post_modified_gmt)
            : time();

        return add_query_arg(
            [
                'notifal_template_preview' => $templateId,
                'nonce'                    => wp_create_nonce('notifal_template_preview'),
                'v'                        => $version,
            ],
            home_url('/')
        );
    }
}
