<?php

namespace Notifal\Modules\Forms\Infrastructure\WordPress\Repositories;

use Notifal\Modules\Forms\Application\Services\FormFieldSanitizer;

defined('ABSPATH') || exit;

/**
 * Persist form field definitions on the Form CPT (structure only, not visual layout).
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormFieldsRepository
{
    /**
     * Post meta key for field definitions.
     *
     * @var string
     */
    public const META_KEY = '_notifal_form_fields';

    /**
     * @var FormFieldSanitizer
     */
    private $sanitizer;

    /**
     * @param FormFieldSanitizer $sanitizer Field sanitizer.
     * @since 3.0.0
     */
    public function __construct(FormFieldSanitizer $sanitizer)
    {
        $this->sanitizer = $sanitizer;
    }

    /**
     * Get stored field definitions for a form.
     *
     * @param int $formId Form CPT ID.
     * @return array<int, array<string, mixed>>
     * @since 3.0.0
     */
    public function get(int $formId): array
    {
        if ($formId <= 0) {
            return [];
        }

        $stored = get_post_meta($formId, self::META_KEY, true);
        if (!is_array($stored)) {
            return [];
        }

        return $this->sanitizer->sanitizeList($stored);
    }

    /**
     * Sanitize a raw field list without persisting.
     *
     * @param mixed $raw Raw field list (array or JSON string).
     * @return array<int, array<string, mixed>>
     * @since 3.0.0
     */
    public function sanitizeList($raw): array
    {
        // Delegate to the shared sanitizer used by admin and builder sync.
        return $this->sanitizer->sanitizeList($raw);
    }

    /**
     * Save field definitions for a form.
     *
     * @param int   $formId Form CPT ID.
     * @param mixed $raw    Raw field list.
     * @return array<int, array<string, mixed>>
     * @since 3.0.0
     */
    public function save(int $formId, $raw): array
    {
        if ($formId <= 0) {
            return [];
        }

        $fields = $this->sanitizeList($raw);
        update_post_meta($formId, self::META_KEY, $fields);

        return $fields;
    }
}
