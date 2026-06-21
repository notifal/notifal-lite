<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\HtmlBuilder\Controllers\Ajax;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\Templates\Application\Services\TemplateBuilderDetector;
use Notifal\Modules\Templates\Application\Services\TemplateUrlService;
use Notifal\Modules\Templates\Infrastructure\WordPress\HtmlBuilder\Services\HtmlTemplateSanitizer;

defined('ABSPATH') || exit;

/**
 * AJAX controller for saving HTML Builder templates.
 *
 * @since 2.4.0
 * @author Hossein <hossein@notifal.com>
 */
class HtmlBuilderSaveController
{
    /**
     * Register WordPress AJAX hooks.
     *
     * @return void
     * @since 2.4.0
     */
    public static function register(): void
    {
        add_action('wp_ajax_notifal_save_html_template', [self::class, 'handle']);
    }

    /**
     * Handle save request from the HTML Builder screen.
     *
     * @return void
     * @since 2.4.0
     */
    public static function handle(): void
    {
        check_ajax_referer('notifal_save_html_template', '_wpnonce');

        $templateId = absint($_POST['template_id'] ?? 0);
        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        $html = isset($_POST['html']) ? wp_unslash($_POST['html']) : '';

        if (!$templateId || !current_user_can('edit_post', $templateId)) {
            wp_send_json_error([
                'message' => __('You are not allowed to edit this template.', 'notifal'),
            ], 403);
        }

        $post = get_post($templateId);
        if (!$post || $post->post_type !== 'notifal_template') {
            wp_send_json_error([
                'message' => __('Invalid template.', 'notifal'),
            ], 400);
        }

        if (get_post_meta($templateId, '_notifal_builder', true) !== TemplateBuilderDetector::BUILDER_HTML) {
            wp_send_json_error([
                'message' => __('This template is not an HTML Builder template.', 'notifal'),
            ], 400);
        }

        do_action(ActionHooks::TEMPLATE_HTML_BUILDER_SAVE_BEFORE, $templateId, $title, $html);

        $sanitized = HtmlTemplateSanitizer::sanitize((string) $html);

        $shouldPublish = isset($_POST['publish']) && sanitize_text_field(wp_unslash($_POST['publish'])) === '1';

        $updateData = [
            'ID'           => $templateId,
            'post_title'   => $title !== '' ? $title : $post->post_title,
            'post_content' => $sanitized['content'],
        ];

        if ($shouldPublish && current_user_can('publish_post', $templateId)) {
            $updateData['post_status'] = 'publish';
        }

        $updated = wp_update_post($updateData, true);

        if (is_wp_error($updated)) {
            wp_send_json_error([
                'message' => __('Failed to save template. Please try again.', 'notifal'),
            ], 500);
        }

        update_post_meta($templateId, '_notifal_content_hash', md5($sanitized['content']));

        do_action(ActionHooks::TEMPLATE_HTML_BUILDER_SAVED, $templateId);

        /** @var TemplateUrlService $urlService */
        $urlService = notifal_app(TemplateUrlService::class);
        $savedPost = get_post($templateId);
        $savedStatus = $savedPost instanceof \WP_Post ? $savedPost->post_status : $post->post_status;
        $wasPublished = $shouldPublish && $savedStatus === 'publish';

        $message = $wasPublished
            ? __('Template published successfully.', 'notifal')
            : __('Template saved successfully.', 'notifal');

        wp_send_json_success([
            'message'     => $message,
            'warnings'    => $sanitized['warnings'],
            'preview_url' => $urlService->getPreviewUrl($templateId, $savedPost instanceof \WP_Post ? $savedPost : null),
            'post_status' => $savedStatus,
        ]);
    }
}
