<?php

namespace Notifal\Modules\Forms\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormFieldsRepository;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormMetaRepository;
use RuntimeException;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Duplicates a Form CPT including field definitions and integration secrets.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormDuplicateService
{
    /**
     * Post meta keys copied explicitly via repositories (skip in generic meta loop).
     *
     * @var string[]
     */
    private const EXPLICIT_META_KEYS = [
        FormFieldsRepository::META_KEY,
        FormMetaRepository::META_KEY,
    ];

    /**
     * Editor-only meta keys that must not carry over to the duplicate.
     *
     * @var string[]
     */
    private const SKIP_META_KEYS = [
        '_edit_lock',
        '_edit_last',
        '_wp_old_slug',
    ];

    /**
     * @var FormMetaRepository
     */
    private $meta;

    /**
     * @var FormFieldsRepository
     */
    private $fields;

    /**
     * @param FormMetaRepository   $meta   Settings repository.
     * @param FormFieldsRepository $fields Fields repository.
     * @since 3.0.0
     */
    public function __construct(FormMetaRepository $meta, FormFieldsRepository $fields)
    {
        // Wire repositories used to copy authoritative form data.
        $this->meta = $meta;
        $this->fields = $fields;
    }

    /**
     * Duplicate a form post with fields, settings, and remaining meta.
     *
     * @param int $sourceFormId Source form CPT ID.
     * @return int New form CPT ID.
     * @throws RuntimeException When source is invalid or license blocks create.
     * @since 3.0.0
     */
    public function duplicate(int $sourceFormId): int
    {
        // Validate the source post type before any writes.
        if ($sourceFormId <= 0 || get_post_type($sourceFormId) !== 'notifal_form') {
            throw new RuntimeException(__('Invalid form.', 'notifal'));
        }

        // Respect the same license guard used when creating forms in admin.
        if (!FormLicenseGuard::canCreate()) {
            throw new RuntimeException(
                __('Notifal free includes one form. Upgrade to Notifal Pro for more.', 'notifal')
            );
        }

        /** @var WP_Post|null $originalPost */
        $originalPost = get_post($sourceFormId);
        if (!$originalPost instanceof WP_Post) {
            throw new RuntimeException(__('Form not found.', 'notifal'));
        }

        // Snapshot authoritative settings + field definitions before insert.
        $settings = $this->meta->get($sourceFormId);
        $fieldDefinitions = $this->fields->get($sourceFormId);

        // Create the duplicate CPT shell (draft, same author as current user).
        $newPostId = wp_insert_post(
            [
                'post_type'      => 'notifal_form',
                'post_title'     => $originalPost->post_title . ' (' . __('Copy', 'notifal') . ')',
                'post_content'   => $originalPost->post_content,
                'post_excerpt'   => $originalPost->post_excerpt,
                'post_status'    => 'draft',
                'post_author'    => get_current_user_id(),
                'post_parent'    => (int) $originalPost->post_parent,
                'menu_order'     => (int) $originalPost->menu_order,
                'comment_status' => $originalPost->comment_status,
                'ping_status'    => $originalPost->ping_status,
            ],
            true
        );

        if (is_wp_error($newPostId) || !$newPostId) {
            throw new RuntimeException(__('Failed to duplicate form. Please try again.', 'notifal'));
        }

        $newFormId = (int) $newPostId;

        // Persist settings (emails, integrations, notes) exactly as stored on the source.
        update_post_meta($newFormId, FormMetaRepository::META_KEY, $settings);

        // Persist field definitions exactly as stored on the source.
        $this->fields->save($newFormId, $fieldDefinitions);

        // Copy any additional meta rows except editor locks and explicit keys above.
        $this->copyRemainingMeta($sourceFormId, $newFormId);

        /**
         * Fires after a Form CPT duplicate is created from the admin list.
         *
         * @param int $sourceFormId Original form ID.
         * @param int $newFormId    Duplicate form ID.
         * @since 3.0.0
         */
        do_action(ActionHooks::FORMS_FORM_DUPLICATED, $sourceFormId, $newFormId);

        return $newFormId;
    }

    /**
     * Copy non-authoritative post meta from source to duplicate.
     *
     * @param int $sourceFormId Source form ID.
     * @param int $newFormId    Duplicate form ID.
     * @return void
     * @since 3.0.0
     */
    private function copyRemainingMeta(int $sourceFormId, int $newFormId): void
    {
        // Read raw meta cache (values are serialized strings in arrays).
        $allMeta = get_post_meta($sourceFormId);
        if (!is_array($allMeta) || $allMeta === []) {
            return;
        }

        foreach ($allMeta as $metaKey => $metaValues) {
            // Skip keys already copied or WordPress editor housekeeping meta.
            if (
                in_array($metaKey, self::EXPLICIT_META_KEYS, true)
                || in_array($metaKey, self::SKIP_META_KEYS, true)
            ) {
                continue;
            }

            if (!is_array($metaValues)) {
                continue;
            }

            foreach ($metaValues as $metaValue) {
                // Unserialize before add_post_meta so arrays/objects persist correctly.
                add_post_meta($newFormId, $metaKey, maybe_unserialize($metaValue));
            }
        }
    }
}
