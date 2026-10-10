<?php

namespace Notifal\Modules\Forms\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormFieldsRepository;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormMetaRepository;
use RuntimeException;

defined('ABSPATH') || exit;

/**
 * CRUD for Form CPT identity and secrets meta.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormService
{
    /**
     * @var FormMetaRepository
     */
    private $meta;

    /**
     * @var FormFieldsRepository
     */
    private $fields;

    /**
     * @param FormMetaRepository   $meta   Meta repository.
     * @param FormFieldsRepository $fields Field repository.
     * @since 3.0.0
     */
    public function __construct(FormMetaRepository $meta, FormFieldsRepository $fields)
    {
        $this->meta = $meta;
        $this->fields = $fields;
    }

    /**
     * Create or update a form CPT.
     *
     * @param array<string, mixed> $data Request data.
     * @return int Form post ID.
     * @throws RuntimeException On validation or license failure.
     * @since 3.0.0
     */
    public function save(array $data): int
    {
        $nonce = isset($data['nonce']) ? (string) $data['nonce'] : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'notifal_form_save')) {
            throw new RuntimeException(
                __('Security check failed. Please refresh the page and try again.', 'notifal')
            );
        }

        $formId = isset($data['form_id']) ? absint($data['form_id']) : 0;
        $title = isset($data['form_title'])
            ? sanitize_text_field(wp_unslash((string) $data['form_title']))
            : '';

        if ($title === '') {
            throw new RuntimeException(__('Form title is required.', 'notifal'));
        }

        if ($formId <= 0 && !FormLicenseGuard::canCreate()) {
            throw new RuntimeException(
                __('Notifal free includes one form. Upgrade to Notifal Pro for more.', 'notifal')
            );
        }

        // Admin / builder saves must include at least one valid field definition.
        $sanitizedFields = null;
        if (array_key_exists('form_fields', $data)) {
            $sanitizedFields = $this->fields->sanitizeList($data['form_fields']);
            if ($sanitizedFields === []) {
                throw new RuntimeException(
                    __('Add at least one field so submissions can be validated.', 'notifal')
                );
            }
        } elseif ($formId <= 0) {
            // New forms cannot be created without a field payload.
            throw new RuntimeException(
                __('Add at least one field so submissions can be validated.', 'notifal')
            );
        }

        // Resolve field names used to validate enabled integrations before save.
        $fieldsForValidation = $sanitizedFields ?? $this->fields->get($formId);
        $fieldNames = [];
        foreach ($fieldsForValidation as $fieldRow) {
            if (!is_array($fieldRow)) {
                continue;
            }
            $name = isset($fieldRow['name']) ? sanitize_key((string) $fieldRow['name']) : '';
            if ($name !== '') {
                $fieldNames[] = $name;
            }
        }

        // Block save when an integration is enabled without its required settings.
        (new FormIntegrationValidator($this->meta))->validate($data, $formId, $fieldNames);

        $postArgs = [
            'post_type'   => 'notifal_form',
            'post_title'  => $title,
            'post_status' => 'publish',
        ];

        if ($formId > 0) {
            $postArgs['ID'] = $formId;
        }

        $postId = wp_insert_post($postArgs, true);
        if (is_wp_error($postId)) {
            throw new RuntimeException($postId->get_error_message());
        }

        $this->meta->save((int) $postId, $data);

        // Persist field definitions when provided by admin UI or builder sync.
        if ($sanitizedFields !== null) {
            $this->fields->save((int) $postId, $sanitizedFields);
        }

        /**
         * Fires after a Form CPT is saved.
         *
         * @param int   $postId Form ID.
         * @param array $data   Request data.
         * @since 3.0.0
         */
        do_action(ActionHooks::FORMS_FORM_SAVED, (int) $postId, $data);

        return (int) $postId;
    }
}
