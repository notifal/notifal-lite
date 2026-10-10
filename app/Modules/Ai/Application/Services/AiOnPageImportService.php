<?php

namespace Notifal\Modules\Ai\Application\Services;

use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Import\Importer;
use Notifal\Modules\Templates\Application\Services\TemplateBuilderDetector;
use Notifal\Modules\Templates\Application\Services\TemplateUrlService;

defined('ABSPATH') || exit;

/**
 * Imports hosted AI OnPage generation output as a disabled draft notification.
 *
 * @since 3.0.0
 * @updated 3.0.0 Reuse Form CPT binds and expose skip_reason when free limit blocks create.
 * @author Hossein <hossein@notifal.com>
 */
class AiOnPageImportService
{
    /**
     * Generated content sanitizer dependency.
     *
     * @var GeneratedContentSanitizer
     */
    private GeneratedContentSanitizer $sanitizer;

    /**
     * Form auto-connect dependency for unbound AI forms.
     *
     * @var AiOnPageFormAutoConnectService
     */
    private AiOnPageFormAutoConnectService $formAutoConnect;

    /**
     * Create the AI OnPage import service.
     *
     * @since 3.0.0
     * @param GeneratedContentSanitizer      $sanitizer       Generated content sanitizer.
     * @param AiOnPageFormAutoConnectService $formAutoConnect Unbound form binder.
     */
    public function __construct(
        GeneratedContentSanitizer $sanitizer,
        AiOnPageFormAutoConnectService $formAutoConnect
    ) {
        // Store sanitizer used for template HTML cleanup.
        $this->sanitizer = $sanitizer;
        // Store binder used to create Form CPTs before import.
        $this->formAutoConnect = $formAutoConnect;
    }

    /**
     * Import one hosted AI OnPage notification document.
     *
     * @since 3.0.0
     * @param array<string, mixed> $payload Decoded generation output document.
     * @return array<string, mixed> Import result with edit URL metadata.
     */
    public function import(array $payload): array
    {
        // Normalize gateway envelope shapes into a flat import document.
        $document = $this->extractImportDocument($payload);

        // Reject invalid or incomplete import documents early.
        if (!$this->isValidOnPageDocument($document)) {
            return [
                'success' => false,
                'error'   => __('The generated notification did not match the expected import format.', 'notifal'),
            ];
        }

        // Track how many unbound AI forms were bound during this import.
        $formsConnected = 0;
        $formsReused = 0;
        $formsSkipped = 0;
        $formsFailed = 0;
        $formsInjected = false;
        $formsSkipReason = '';
        $connectedFormIds = [];

        // Sanitize embedded template HTML when present.
        if (
            isset($document['notification']['template']['content'])
            && is_string($document['notification']['template']['content'])
        ) {
            $sanitized = $this->sanitizer->sanitizeHtml($document['notification']['template']['content']);
            $templateHtml = $sanitized['content'];

            // Create Form CPTs for data-form-id="0" roots before the importer stores HTML.
            $notificationTitle = sanitize_text_field(
                (string) ($document['notification']['title'] ?? '')
            );
            $formBind = $this->formAutoConnect->connectUnboundForms(
                $templateHtml,
                $notificationTitle
            );
            $templateHtml = $formBind['html'];
            $formsConnected = (int) $formBind['connected'];
            $formsReused = (int) ($formBind['reused'] ?? 0);
            $formsSkipped = (int) $formBind['skipped'];
            $formsFailed = (int) $formBind['failed'];
            $formsInjected = !empty($formBind['injected']);
            $formsSkipReason = sanitize_key((string) ($formBind['skip_reason'] ?? ''));
            $connectedFormIds = is_array($formBind['form_ids'] ?? null)
                ? array_map('absint', $formBind['form_ids'])
                : [];

            $document['notification']['template']['content'] = $templateHtml;
        }

        // Delegate to the existing OnPage importer pipeline.
        $result = Importer::importFromJsonArray($document);

        // Return structured failure when import did not succeed.
        if (empty($result['success'])) {
            $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
            $message = !empty($errors[0]) ? (string) $errors[0] : __('Import failed.', 'notifal');

            return [
                'success' => false,
                'error'   => $message,
            ];
        }

        // Resolve edit URL for success UX when notification ID is available.
        $notificationId = absint($result['notification_id'] ?? 0);
        $editUrl = $notificationId > 0 ? (string) get_edit_post_link($notificationId, 'raw') : '';

        // Prefer importer template ID, then fall back to notification meta.
        $templateId = absint($result['template_id'] ?? 0);
        if ($templateId <= 0 && $notificationId > 0) {
            $templateId = absint(get_post_meta($notificationId, '_notifal_template_id', true));
        }

        // Publish the template so it appears in the OnPage template picker (publish-only queries).
        $this->ensureTemplatePublished($templateId);

        // Keep the OnPage notification as a disabled draft for review before go-live.
        $this->ensureOnPageDraftDisabled($notificationId);

        // Resolve HTML Builder edit URL when the imported template supports it.
        $builderUrl = $this->resolveHtmlBuilderUrl($templateId);

        // Resolve the authenticated frontend preview URL for the saved template.
        $previewUrl = $this->resolveTemplatePreviewUrl($templateId);

        // Read useful preview fields from the imported notification document.
        $notification = is_array($document['notification'] ?? null) ? $document['notification'] : [];
        $settings = is_array($notification['settings'] ?? null) ? $notification['settings'] : [];
        $appearance = is_array($settings['appearance'] ?? null) ? $settings['appearance'] : [];
        $template = is_array($notification['template'] ?? null) ? $notification['template'] : [];
        $layout = sanitize_key((string) ($appearance['notification_display_type'] ?? ''));
        $builder = sanitize_key((string) ($template['builder'] ?? ''));
        $templateHtml = (
            !empty($template['content']) && is_string($template['content'])
        ) ? (string) $template['content'] : '';
        $hasTemplate = '' !== trim($templateHtml);

        // Desktop corner/edge position drives floating sidebox placement in the success preview.
        $previewPosition = sanitize_key((string) ($appearance['desktop_position'] ?? 'bottom-left'));
        if ($notificationId > 0) {
            $savedAppearance = get_post_meta($notificationId, '_notifal_appearance_settings', true);
            if (is_array($savedAppearance) && !empty($savedAppearance['desktop_position'])) {
                $previewPosition = sanitize_key((string) $savedAppearance['desktop_position']);
            }
        }

        // Return success metadata for the admin wizard preview.
        return [
            'success'         => true,
            'notification_id' => $notificationId,
            'template_id'     => $templateId,
            'title'           => sanitize_text_field((string) ($result['first_title'] ?? ($notification['title'] ?? ''))),
            'edit_url'        => esc_url_raw($editUrl),
            'builder_url'     => esc_url_raw($builderUrl),
            'preview_url'     => esc_url_raw($previewUrl),
            'layout'          => $layout,
            'preview_position' => $previewPosition,
            'builder'         => $builder,
            'has_template'    => $hasTemplate,
            // Already sanitized HTML for the non-interactive success preview.
            'template_html'   => $templateHtml,
            'status'          => 'draft',
            'forms_connected' => $formsConnected,
            'forms_reused'    => $formsReused,
            'forms_skipped'   => $formsSkipped,
            'forms_failed'    => $formsFailed,
            'forms_injected'  => $formsInjected,
            'forms_skip_reason' => $formsSkipReason,
            'form_ids'        => $connectedFormIds,
        ];
    }

    /**
     * Ensure the imported template CPT is published for picker visibility.
     *
     * @since 3.0.0
     * @param int $templateId Template post ID.
     * @return void
     */
    private function ensureTemplatePublished(int $templateId): void
    {
        // Skip invalid IDs early.
        if ($templateId <= 0) {
            return;
        }

        // Load the template post for status verification.
        $template = get_post($templateId);
        if (!$template instanceof \WP_Post || 'notifal_template' !== $template->post_type) {
            return;
        }

        // Already published templates need no update.
        if ('publish' === $template->post_status) {
            return;
        }

        // Promote the template so TemplateQuery publish-only lists can include it.
        wp_update_post(
            [
                'ID'          => $templateId,
                'post_status' => 'publish',
            ]
        );
    }

    /**
     * Keep the imported OnPage notification as a disabled draft for review.
     *
     * @since 3.0.0
     * @param int $notificationId OnPage notification post ID.
     * @return void
     */
    private function ensureOnPageDraftDisabled(int $notificationId): void
    {
        // Skip invalid IDs early.
        if ($notificationId <= 0) {
            return;
        }

        // Load the notification post for status verification.
        $notification = get_post($notificationId);
        if (!$notification instanceof \WP_Post || 'notifal_onpage_notif' !== $notification->post_type) {
            return;
        }

        // Force draft so the merchant reviews before publishing live.
        if ('draft' !== $notification->post_status) {
            wp_update_post(
                [
                    'ID'          => $notificationId,
                    'post_status' => 'draft',
                ]
            );
        }

        // Keep the notification disabled until the merchant enables it.
        update_post_meta($notificationId, '_notifal_enabled', '0');
    }

    /**
     * Build the HTML Builder edit URL for one imported template.
     *
     * @since 3.0.0
     * @param int $templateId Template post ID.
     * @return string Escaped-ready builder URL or empty string.
     */
    private function resolveHtmlBuilderUrl(int $templateId): string
    {
        // Require a valid template post before building a builder deep-link.
        if ($templateId <= 0) {
            return '';
        }

        $template = get_post($templateId);
        if (!$template instanceof \WP_Post) {
            return '';
        }

        // Only deep-link into HTML Builder for HTML Builder templates.
        if (!TemplateBuilderDetector::isHtmlBuilder($template)) {
            return '';
        }

        // Reuse the shared URL service so slug and query args stay consistent.
        $urlService = new TemplateUrlService();

        return $urlService->getEditHtmlBuilderUrl($templateId);
    }

    /**
     * Build the frontend preview URL for one imported template.
     *
     * @since 3.0.0
     * @param int $templateId Template post ID.
     * @return string Escaped-ready preview URL or empty string.
     */
    private function resolveTemplatePreviewUrl(int $templateId): string
    {
        // Require a valid template post before building a preview URL.
        if ($templateId <= 0) {
            return '';
        }

        $template = get_post($templateId);
        if (!$template instanceof \WP_Post) {
            return '';
        }

        // Reuse the shared URL service so preview auth and cache-busting stay aligned.
        $urlService = new TemplateUrlService();

        return $urlService->getPreviewUrl($templateId, $template);
    }

    /**
     * Extract a flat import document from gateway output envelopes.
     *
     * @since 3.0.0
     * @param array<string, mixed> $payload Raw generation output.
     * @return array<string, mixed> Normalized import document.
     */
    private function extractImportDocument(array $payload): array
    {
        // Accept direct import documents from the gateway output field.
        if (isset($payload['notification']) && is_array($payload['notification'])) {
            return $payload;
        }

        // Accept nested output wrappers returned by polling endpoints.
        if (isset($payload['output']) && is_array($payload['output'])) {
            $output = $payload['output'];

            if (isset($output['notification']) && is_array($output['notification'])) {
                return $output;
            }
        }

        // Return empty array when no recognizable document exists.
        return [];
    }

    /**
     * Validate the normalized OnPage import document structure.
     *
     * @since 3.0.0
     * @param array<string, mixed> $document Import document candidate.
     * @return bool True when structure is importable.
     */
    private function isValidOnPageDocument(array $document): bool
    {
        // Require version, type, and notification keys.
        if (
            empty($document['version'])
            || empty($document['type'])
            || empty($document['notification'])
            || !is_array($document['notification'])
        ) {
            return false;
        }

        // Accept supported OnPage export type slugs.
        $type = sanitize_key((string) $document['type']);

        return in_array($type, ['notifal_onpage_notif', 'notifal_onpage_notification'], true);
    }
}
