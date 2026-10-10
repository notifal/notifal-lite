<?php

namespace Notifal\Modules\Forms\Application\Services;

use Notifal\Domain\Settings\Services\SettingsService;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormFieldsRepository;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormMetaRepository;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Export and import Form CPTs connected to templates via data-form-id.
 *
 * Used by Template and OnPage notification export/import so connected forms
 * travel with the template and are recreated + reassigned on the destination.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ConnectedFormPortabilityService
{
    /**
     * @var ConnectedFormContentScanner
     */
    private $scanner;

    /**
     * @var FormMetaRepository
     */
    private $meta;

    /**
     * @var FormFieldsRepository
     */
    private $fields;

    /**
     * @param ConnectedFormContentScanner $scanner Content scanner.
     * @param FormMetaRepository          $meta    Form settings repository.
     * @param FormFieldsRepository        $fields  Form fields repository.
     * @since 3.0.0
     */
    public function __construct(
        ConnectedFormContentScanner $scanner,
        FormMetaRepository $meta,
        FormFieldsRepository $fields
    ) {
        // Wire dependencies used for scan, export payload, and CPT create.
        $this->scanner = $scanner;
        $this->meta = $meta;
        $this->fields = $fields;
    }

    /**
     * Build the default service with shared repositories.
     *
     * @since 3.0.0
     * @return self
     */
    public static function createDefault(): self
    {
        // Match admin AJAX wiring so sanitization rules stay identical.
        $sanitizer = new FormFieldSanitizer();
        $fields = new FormFieldsRepository($sanitizer);

        return new self(
            new ConnectedFormContentScanner(),
            new FormMetaRepository(),
            $fields
        );
    }

    /**
     * Prepare connected form payloads for a template export.
     *
     * @since 3.0.0
     * @param mixed $content Template content that may reference forms.
     * @return array<int, array<string, mixed>> Exportable form payloads keyed numerically.
     */
    public function prepareFormsForExport($content): array
    {
        // Resolve every positive data-form-id referenced by the template.
        $formIds = $this->scanner->extractFormIds($content);
        if ($formIds === []) {
            return [];
        }

        $payloads = [];
        foreach ($formIds as $formId) {
            $payload = $this->prepareSingleFormExport($formId);
            if ($payload !== null) {
                $payloads[] = $payload;
            }
        }

        return $payloads;
    }

    /**
     * Import connected forms and remap template content to new Form CPT ids.
     *
     * When the free-tier limit blocks create, that form is left unbound (0)
     * and a warning is returned so the merchant can upgrade and try again.
     *
     * @since 3.0.0
     * @param array<int, array<string, mixed>> $forms   Exported form payloads.
     * @param mixed                            $content Template content to remap.
     * @return array{
     *     content: mixed,
     *     id_map: array<int, int>,
     *     created: int[],
     *     warnings: string[]
     * }
     */
    public function importFormsAndRemapContent(array $forms, $content): array
    {
        $result = [
            'content'  => $content,
            'id_map'   => [],
            'created'  => [],
            'warnings' => [],
        ];

        // Nothing to do when the export did not include connected forms.
        if ($forms === []) {
            return $result;
        }

        // Forms kill switch: keep markup unbound and explain why.
        if (!$this->isFormsModuleEnabled()) {
            $result['warnings'][] = __(
                'Connected forms were not imported because the Forms addon is disabled. Enable Forms under Notifal → Addons, then import again or reconnect forms manually.',
                'notifal'
            );
            $result['content'] = $this->scanner->remapFormIds($content, []);

            return $result;
        }

        // Create each exported form (or record a recoverable warning).
        foreach ($forms as $formPayload) {
            if (!is_array($formPayload)) {
                continue;
            }

            $oldId = isset($formPayload['id']) ? absint($formPayload['id']) : 0;
            if ($oldId <= 0) {
                continue;
            }

            $created = $this->createFormFromExport($formPayload);
            if ($created['success'] && !empty($created['form_id'])) {
                $newId = absint($created['form_id']);
                $result['id_map'][$oldId] = $newId;
                $result['created'][] = $newId;
                continue;
            }

            // Leave this form unbound and surface a try-again message.
            $result['id_map'][$oldId] = 0;
            if (!empty($created['error'])) {
                $result['warnings'][] = (string) $created['error'];
            }
        }

        // Rewrite every data-form-id in the template to the destination CPT ids.
        $result['content'] = $this->scanner->remapFormIds($content, $result['id_map']);

        return $result;
    }

    /**
     * Build export payload for one Form CPT.
     *
     * @since 3.0.0
     * @param int $formId Form post ID.
     * @return array<string, mixed>|null Payload or null when the post is missing.
     */
    private function prepareSingleFormExport(int $formId): ?array
    {
        // Only export live Form CPT posts (skip trash / wrong type).
        $post = get_post($formId);
        if (!$post instanceof WP_Post || $post->post_type !== 'notifal_form') {
            return null;
        }

        if ($post->post_status === 'trash') {
            return null;
        }

        // Snapshot fields + settings used to recreate the CPT on import.
        // Mask integration secrets so portable JSON never ships API keys / auth headers.
        $settings = $this->meta->getForAdmin($formId);
        unset($settings['webhook_auth_header_set'], $settings['mailchimp_api_key_set']);
        $fieldDefinitions = $this->fields->get($formId);

        return [
            'id'       => $formId,
            'title'    => (string) $post->post_title,
            'status'   => (string) $post->post_status,
            'fields'   => $fieldDefinitions,
            'settings' => $settings,
        ];
    }

    /**
     * Create a Form CPT from an export payload.
     *
     * @since 3.0.0
     * @param array<string, mixed> $payload Exported form data.
     * @return array{success: bool, form_id?: int, error?: string}
     */
    private function createFormFromExport(array $payload): array
    {
        // Respect the same free-tier create guard as admin / duplicate flows.
        if (!FormLicenseGuard::canCreate()) {
            return [
                'success' => false,
                'error'   => __(
                    'A connected form could not be created because the free form limit was reached. Upgrade to Notifal Pro or delete an existing form, then import again to reconnect it.',
                    'notifal'
                ),
            ];
        }

        $title = isset($payload['title'])
            ? sanitize_text_field((string) $payload['title'])
            : '';
        if ($title === '') {
            $title = __('Imported Form', 'notifal');
        }

        // Prefer publish so frontend hydration can resolve the CPT immediately.
        $status = isset($payload['status'])
            ? sanitize_key((string) $payload['status'])
            : 'publish';
        if (!in_array($status, ['publish', 'draft', 'private'], true)) {
            $status = 'publish';
        }

        $postId = wp_insert_post(
            [
                'post_type'   => 'notifal_form',
                'post_title'  => $title,
                'post_status' => $status,
            ],
            true
        );

        // License guard may force an empty auto-draft; treat that as failure.
        if (is_wp_error($postId) || !$postId) {
            return [
                'success' => false,
                'error'   => __(
                    'Failed to create a connected form. Please try importing again.',
                    'notifal'
                ),
            ];
        }

        $formId = (int) $postId;
        $createdPost = get_post($formId);
        if (
            !$createdPost instanceof WP_Post
            || $createdPost->post_type !== 'notifal_form'
            || $createdPost->post_status === 'auto-draft'
            || $createdPost->post_title === ''
        ) {
            // Clean up the rejected shell so the free limit stays accurate.
            wp_delete_post($formId, true);

            return [
                'success' => false,
                'error'   => __(
                    'A connected form could not be created because the free form limit was reached. Upgrade to Notifal Pro or delete an existing form, then import again to reconnect it.',
                    'notifal'
                ),
            ];
        }

        // Persist settings through the repository so sanitization stays authoritative.
        $settingsInput = isset($payload['settings']) && is_array($payload['settings'])
            ? $payload['settings']
            : [];
        $this->meta->save($formId, $settingsInput);

        // Persist field definitions when the export included them.
        $fieldsInput = $payload['fields'] ?? [];
        if (is_array($fieldsInput) && $fieldsInput !== []) {
            $this->fields->save($formId, $fieldsInput);
        }

        return [
            'success' => true,
            'form_id' => $formId,
        ];
    }

    /**
     * Whether the Forms addon kill switch is currently enabled.
     *
     * @since 3.0.0
     * @return bool
     */
    private function isFormsModuleEnabled(): bool
    {
        // Fail open when the container is unavailable so portability still runs.
        if (!function_exists('notifal_app')) {
            return true;
        }

        try {
            $settings = notifal_app(SettingsService::class);
            $formsSettings = new FormsSettingsService($settings);

            return $formsSettings->isEnabled();
        } catch (\Throwable $exception) {
            return true;
        }
    }
}
