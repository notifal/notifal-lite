<?php

namespace Notifal\Modules\Forms\Presentation\Admin\ListTable;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\SubmissionRepository;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Forms list columns and edit links for the hidden Form CPT.
 *
 * Points edit URLs at the Notifal Form screen and renders an Inbox
 * column with submission counts linking to filtered submissions.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ColumnsController
{
    /**
     * Cached form_id => submission count for the current request.
     *
     * @var array<int, int>|null
     */
    private static $inboxCounts = null;

    /**
     * Register list filters.
     *
     * @return void
     * @since 3.0.0
     */
    public static function register(): void
    {
        // Custom Inbox column content on the Forms list.
        add_filter(FilterHooks::ADMIN_LIST_CUSTOM_COLUMN, [self::class, 'renderInboxColumn'], 10, 4);
        // Remove public View action; inbox is available via the Inbox column.
        add_filter(FilterHooks::ADMIN_LIST_ROW_ACTIONS, [self::class, 'modifyRowActions'], 10, 3);
        // CPT has show_ui false, so core edit links must be rewritten.
        add_filter('get_edit_post_link', [self::class, 'modifyEditPostLink'], 10, 3);
    }

    /**
     * Render the Inbox column (submission count + link).
     *
     * @param string  $content   Default column HTML.
     * @param string  $columnKey Column key from BaseListView.
     * @param WP_Post $post      Form post.
     * @param string  $postType  List post type.
     * @return string
     * @since 3.0.0
     */
    public static function renderInboxColumn(string $content, string $columnKey, WP_Post $post, string $postType): string
    {
        // Only handle the Forms list Inbox column.
        if ($postType !== 'notifal_form' || $columnKey !== 'inbox') {
            return $content;
        }

        $formId = (int) $post->ID;
        $count = self::getInboxCount($formId);
        $url = self::getInboxUrl($formId);

        // Linked count opens submissions filtered to this form.
        return sprintf(
            '<a href="%s" class="notifal-list-link notifal-forms-inbox-link" data-form-id="%d">%s</a>',
            esc_url($url),
            $formId,
            esc_html(number_format_i18n($count))
        );
    }

    /**
     * Drop public View row action on the Forms list.
     *
     * @param array<string, string> $actions  Row action map.
     * @param WP_Post               $post     Current form.
     * @param string                $postType List post type.
     * @return array<string, string>
     * @since 3.0.0
     */
    public static function modifyRowActions(array $actions, WP_Post $post, string $postType): array
    {
        // Only adjust row actions for the Forms list.
        if ($postType !== 'notifal_form') {
            return $actions;
        }

        // Forms are not public posts; inbox access lives in the Inbox column.
        unset($actions['view']);

        return $actions;
    }

    /**
     * Return the Notifal admin URL for editing a form CPT.
     *
     * @param string     $link    Default URL.
     * @param int|string $post_id Post ID.
     * @param string     $context Context.
     * @return string
     * @since 3.0.0
     */
    public static function modifyEditPostLink(string $link, $post_id, $context = 'display'): string
    {
        unset($context);

        $post = get_post((int) $post_id);
        if (!$post instanceof WP_Post || $post->post_type !== 'notifal_form') {
            return $link;
        }

        return add_query_arg(
            [
                'page'    => 'notifal-form',
                'form_id' => (int) $post_id,
            ],
            admin_url('admin.php')
        );
    }

    /**
     * Build submissions list URL filtered by form ID.
     *
     * @param int $formId Form CPT ID.
     * @return string
     * @since 3.0.0
     */
    private static function getInboxUrl(int $formId): string
    {
        return add_query_arg(
            [
                'page'    => 'notifal-form-submissions',
                'form_id' => $formId,
            ],
            admin_url('admin.php')
        );
    }

    /**
     * Resolve submission count for a form using a request-level cache.
     *
     * @param int $formId Form CPT ID.
     * @return int
     * @since 3.0.0
     */
    private static function getInboxCount(int $formId): int
    {
        // Load all form counts once per request for the list table.
        if (self::$inboxCounts === null) {
            $repo = new SubmissionRepository();
            self::$inboxCounts = $repo->getCountsGroupedByForm();
        }

        return isset(self::$inboxCounts[$formId]) ? (int) self::$inboxCounts[$formId] : 0;
    }
}
