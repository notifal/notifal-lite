<?php

namespace Notifal\Modules\Forms\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormFieldsRepository;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormMetaRepository;

defined('ABSPATH') || exit;

/**
 * Keep Form CPT field definitions and presentation aligned with HTML Builder markup.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormFieldSyncService
{
    /**
     * @var FormFieldsRepository
     */
    private $fields;

    /**
     * @var HtmlSchemaParser
     */
    private $parser;

    /**
     * @var FormMetaRepository
     */
    private $meta;

    /**
     * @param FormFieldsRepository    $fields Field repository.
     * @param HtmlSchemaParser        $parser HTML schema parser.
     * @param FormMetaRepository|null $meta   Meta repository for presentation sync.
     * @since 3.0.0
     */
    public function __construct(
        FormFieldsRepository $fields,
        HtmlSchemaParser $parser,
        ?FormMetaRepository $meta = null
    ) {
        $this->fields = $fields;
        $this->parser = $parser;
        // Allow older callers without meta; sync presentation when available.
        $this->meta = $meta instanceof FormMetaRepository ? $meta : new FormMetaRepository();
    }

    /**
     * Extract field definitions and presentation from form HTML and persist them.
     *
     * Brand color on the Form CPT is never overwritten from template HTML.
     * Callers can skip presentation writes when connecting (CPT is source of truth).
     *
     * @param int                  $formId  Form CPT ID.
     * @param string               $html    HTML fragment containing the form.
     * @param array<string, mixed> $options {
     *     Optional sync flags.
     *
     *     @type bool $sync_presentation Whether to mirror presentation attrs to CPT. Default true.
     * }
     * @return array{fields: array<int, array<string, mixed>>, presentation: array<string, mixed>}
     * @since 3.0.0
     */
    public function syncFromHtml(int $formId, string $html, array $options = []): array
    {
        if ($formId <= 0) {
            return [
                'fields'       => [],
                'presentation' => FormPresentationSettings::getDefaults(),
            ];
        }

        $schema = $this->parser->parse($html);
        $fields = is_array($schema) && isset($schema['fields']) && is_array($schema['fields'])
            ? $schema['fields']
            : [];

        // Connect loads CPT → HTML; edits sync HTML → CPT. Skip presentation on connect.
        $syncPresentation = !array_key_exists('sync_presentation', $options)
            || (bool) $options['sync_presentation'];

        if ($syncPresentation) {
            $presentation = is_array($schema)
                ? FormPresentationSettings::fromSchema($schema)
                : FormPresentationSettings::getDefaults();

            // Template brand color stays on the HTML widget; keep the CPT brand default.
            $existingPresentation = $this->meta->getPresentation($formId);
            $presentation['primary_color'] = $existingPresentation['primary_color'];

            // Persist presentation mirror without touching email / integration secrets.
            $this->meta->savePresentation($formId, $presentation);
        } else {
            $presentation = $this->meta->getPresentation($formId);
        }

        return [
            'fields'       => $this->persistFields($formId, $fields, 'html'),
            'presentation' => $presentation,
        ];
    }

    /**
     * Persist sanitized field rows from admin or builder payloads.
     *
     * @param int   $formId Form CPT ID.
     * @param mixed $raw    Raw field list.
     * @return array{fields: array<int, array<string, mixed>>, presentation: array<string, mixed>}
     * @since 3.0.0
     */
    public function syncFromPayload(int $formId, $raw): array
    {
        if ($formId <= 0) {
            return [
                'fields'       => [],
                'presentation' => FormPresentationSettings::getDefaults(),
            ];
        }

        return [
            'fields'       => $this->persistFields($formId, $raw, 'payload'),
            'presentation' => $this->meta->getPresentation($formId),
        ];
    }

    /**
     * @param int    $formId Form CPT ID.
     * @param mixed  $raw    Raw field list.
     * @param string $source Sync source slug.
     * @return array<int, array<string, mixed>>
     * @since 3.0.0
     */
    private function persistFields(int $formId, $raw, string $source): array
    {
        $saved = $this->fields->save($formId, $raw);

        /**
         * Fires after form field definitions are synced to the Form CPT.
         *
         * @param int                              $form_id Form CPT ID.
         * @param array<int, array<string, mixed>> $fields  Saved fields.
         * @param string                           $source  Sync source (html|payload).
         * @since 3.0.0
         */
        do_action(ActionHooks::FORMS_FIELDS_SYNCED, $formId, $saved, $source);

        return $saved;
    }
}
