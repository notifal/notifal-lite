<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\HtmlBuilder\Controllers\Ajax;

use Notifal\Modules\Templates\Infrastructure\WordPress\HtmlBuilder\Services\HtmlTemplatePreviewService;

defined('ABSPATH') || exit;

/**
 * AJAX controller for server-side HTML Builder previews.
 *
 * @since 2.4.0
 * @author Hossein <hossein@notifal.com>
 */
class HtmlBuilderPreviewController
{
    /**
     * Register WordPress AJAX hooks.
     *
     * @return void
     * @since 2.4.0
     */
    public static function register(): void
    {
        add_action('wp_ajax_notifal_html_builder_preview', [self::class, 'handle']);
    }

    /**
     * Return rendered preview HTML for iframe srcdoc injection.
     *
     * @return void
     * @since 2.4.0
     */
    public static function handle(): void
    {
        check_ajax_referer('notifal_html_builder_preview', '_wpnonce');

        $templateId = absint($_POST['template_id'] ?? 0);
        $html = isset($_POST['html']) ? wp_unslash($_POST['html']) : null;

        if (!$templateId || !current_user_can('edit_post', $templateId)) {
            wp_send_json_error([
                'message' => __('You are not allowed to preview this template.', 'notifal'),
            ], 403);
        }

        /** @var HtmlTemplatePreviewService $previewService */
        $previewService = notifal_app(HtmlTemplatePreviewService::class);
        $result = $previewService->renderPreview($templateId, $html);

        wp_send_json_success([
            'html'     => $result['html'],
            'warnings' => $result['warnings'],
        ]);
    }
}
