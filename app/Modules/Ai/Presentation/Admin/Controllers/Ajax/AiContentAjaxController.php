<?php

namespace Notifal\Modules\Ai\Presentation\Admin\Controllers\Ajax;

use Notifal\Modules\Ai\Application\Services\AiOnPageImportService;
use Notifal\Modules\Ai\Application\Services\GeneratedContentSanitizer;
use Notifal\Modules\Ai\Config\Constants;

defined('ABSPATH') || exit;

/**
 * AJAX endpoints for applying hosted AI generated content safely.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class AiContentAjaxController
{
    /**
     * Register WordPress AJAX handlers.
     *
     * @since 3.0.0
     * @return void
     */
    public static function register(): void
    {
        // Register HTML sanitization for builder apply flows.
        add_action('wp_ajax_notifal_ai_sanitize_html', [self::class, 'sanitizeHtml']);

        // Register OnPage import for completed hosted generation output.
        add_action('wp_ajax_notifal_ai_import_onpage', [self::class, 'importOnPage']);
    }

    /**
     * Sanitize hosted AI HTML before loading it into the HTML Builder.
     *
     * @since 3.0.0
     * @return void
     */
    public static function sanitizeHtml(): void
    {
        try {
            // Verify nonce and editor capability before handling HTML payloads.
            notifal_verify_ajax_request(Constants::AJAX_NONCE_ACTION, 'edit_posts');

            // Read raw HTML from POST without sanitize_text_field to preserve markup.
            $html = isset($_POST['html']) ? (string) wp_unslash($_POST['html']) : '';

            // Reject empty HTML submissions.
            if ('' === trim($html)) {
                notifal_json_error(__('Generated HTML was empty.', 'notifal'));
            }

            // Sanitize using the strict generated-content policy.
            $result = notifal_app(GeneratedContentSanitizer::class)->sanitizeHtml($html);

            // Return sanitized HTML and any warnings to the builder client.
            notifal_json_success([
                'html'     => $result['content'],
                'warnings' => $result['warnings'],
            ]);
        } catch (\Throwable $exception) {
            notifal_json_error(__('An unexpected error occurred. Please try again.', 'notifal'));
        }
    }

    /**
     * Import one hosted AI OnPage notification as a disabled draft.
     *
     * @since 3.0.0
     * @return void
     */
    public static function importOnPage(): void
    {
        try {
            // Verify nonce and editor capability before importing generated content.
            notifal_verify_ajax_request(Constants::AJAX_NONCE_ACTION, 'edit_posts');

            // Decode JSON document posted by the wizard client.
            $raw = isset($_POST['document']) ? wp_unslash($_POST['document']) : '';
            $document = is_string($raw) ? json_decode($raw, true) : [];

            // Reject malformed JSON payloads.
            if (!is_array($document)) {
                notifal_json_error(__('Invalid generation document.', 'notifal'));
            }

            // Delegate import to the dedicated AI OnPage import service.
            $result = notifal_app(AiOnPageImportService::class)->import($document);

            // Return structured failure payloads from the importer.
            if (empty($result['success'])) {
                notifal_json_error((string) ($result['error'] ?? __('Import failed.', 'notifal')));
            }

            // Return success metadata including edit URL for redirect UX.
            notifal_json_success($result);
        } catch (\Throwable $exception) {
            notifal_json_error(__('An unexpected error occurred. Please try again.', 'notifal'));
        }
    }
}
