<?php

namespace Notifal\Shared\AdminUI\Lists;

use Notifal\Core\Support\Helpers\UrlHelper;
use Notifal\Infrastructure\WordPress\Elementor\Helpers\ElementorHelper;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Shared\AdminUI\Toast\ToastManager;
use Notifal\Modules\Templates\Application\Services\TemplateUrlService;
use Notifal\Shared\Services\NotifalIconService;
use Notifal\Shared\Utils\Helper;
use WP_Post;
use WP_Query;

if (!defined('ABSPATH')) exit;

/**
 * BaseListView provides a reusable table list renderer for WordPress post types in Notifal admin UI.
 *
 * Features status-based filtering, search, bulk actions, pagination, and custom columns
 * with proper security measures and extensibility through WordPress hooks.
 *
 * @since 2.0.0
 * @package Notifal\Shared\AdminUI\Lists
 */
class BaseListView
{
    /**
     * The post type to display in the list.
     *
     * @since 2.0.0
     * @var string
     */
    protected string $postType;

    /**
     * Column definitions for the table.
     * Format: ['column_key' => 'Column Label']
     *
     * @since 2.0.0
     * @var array<string, string>
     */
    protected array $columns = [];

    /**
     * Status tab definitions with labels and counts.
     * Format: ['status_key' => ['label' => 'Status Label', 'count' => 5]]
     *
     * @since 2.0.0
     * @var array<string, array{label: string, count: int}>
     */
    protected array $statusTabs = [];


    /**
     * Optional taxonomy name used for the "category" column.
     * Defaults to {$postType}_category if not set.
     *
     * @since 2.0.0
     * @var string|null
     */
    protected ?string $categoryTaxonomy = null;

    /**
     * Available bulk actions for the list.
     * Format: ['action_key' => 'Action Label']
     *
     * @since 2.0.0
     * @var array<string, string>
     */
    protected array $bulkActions = [];

    /**
     * Page title displayed in the header.
     *
     * @since 2.0.0
     * @var string
     */
    protected string $title = '';

    /**
     * URL for the "Add New" button.
     *
     * @since 2.0.0
     * @var string
     */
    protected string $addNewUrl = '';

    /**
     * Number of items to display per page.
     *
     * @since 2.0.0
     * @var int
     */
    protected int $perPage = 10;

    /**
     * Cached total count from the main query.
     *
     * @since 2.0.0
     * @var int|null
     */
    protected ?int $queryTotal = null;

    /**
     * Current page number for pagination.
     *
     * @since 2.0.0
     * @var int
     */
    protected int $currentPage = 1;

    /**
     * Current search query string.
     *
     * @since 2.0.0
     * @var string
     */
    protected string $searchQuery = '';

    /**
     * Placeholder text for the search input.
     *
     * @since 2.0.0
     * @var string
     */
    protected string $searchPlaceholder = '';

    /**
     * Current status filter.
     *
     * @since 2.0.0
     * @var string
     */
    protected string $currentStatus = 'all';

    /**
     * Flag to track if bulk actions have been handled to prevent double processing.
     *
     * @since 2.0.0
     * @var bool
     */
    protected bool $bulkActionsHandled = false;

    /**
     * Handle bulk actions for any post type before rendering.
     *
     * This static method can be called from controllers or hooks to handle bulk actions
     *
     * @since 2.0.0
     * @param string $postType The post type to handle bulk actions for
     * @return bool True if bulk actions were processed, false otherwise
     */
    public static function handleBulkActionsForPostType(string $postType): bool
    {
        // Only process POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return false;
        }

        // Verify nonce first (WordPress security guideline: check capability/nonce before using input)
        $nonce = isset( $_POST['notifal_bulk_nonce'] ) ? wp_unslash( $_POST['notifal_bulk_nonce'] ) : '';
        if ( $nonce === '' || ! wp_verify_nonce( $nonce, 'notifal_bulk_action' ) ) {
            return false;
        }

        $action = isset( $_POST['bulk_action'] ) ? wp_unslash( $_POST['bulk_action'] ) : '';
        $ids    = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? $_POST['ids'] : [];

        if ( empty( $action ) || empty( $ids ) ) {
            return false;
        }

        // Sanitize inputs after nonce verification
        $ids           = array_map( 'absint', array_filter( array_map( 'absint', $ids ) ) );
        $action        = Helper::sanitizeInput( $action, 'key' );
        $currentStatus = Helper::sanitizeInput( isset( $_GET['status'] ) ? wp_unslash( $_GET['status'] ) : 'all', 'key' );

        // Process actions
        switch ($action) {
            case 'restore':
                self::handleBulkRestoreStatic($ids);
                break;

            case 'delete':
                self::handleBulkDeleteStatic($ids, $postType, $currentStatus);
                break;

            case 'duplicate':
                self::handleBulkDuplicateStatic($ids, $postType);
                break;

            default:
                do_action(ActionHooks::ADMIN_LIST_HANDLE_BULK_ACTION, $action, $ids, $postType);
                break;
        }

        return true;
    }

    /**
     * Handle bulk restore action (static version).
     *
     * @since 2.0.0
     * @param array $ids Post IDs to restore
     */
    protected static function handleBulkRestoreStatic(array $ids): void
    {
        foreach ($ids as $id) {
            if (get_post_status($id) === 'trash') {
                wp_untrash_post($id);
            }
        }

        $redirectUrl = add_query_arg([
            'page' => Helper::sanitizeInput( isset( $_GET['page'] ) ? wp_unslash( $_GET['page'] ) : '', 'key' ),
            'status' => 'all'
        ], admin_url('admin.php'));

        ToastManager::success(__('Items restored successfully.', 'notifal'), $redirectUrl);
    }

    /**
     * Handle bulk delete action (static version).
     *
     * @since 2.0.0
     * @param array $ids Post IDs to delete
     * @param string $postType The post type
     * @param string $currentStatus Current status filter
     */
    protected static function handleBulkDeleteStatic(array $ids, string $postType, string $currentStatus): void
    {
        $deleted = 0;

        foreach ($ids as $id) {
            if (get_post_type($id) === $postType) {
                if ($currentStatus === 'trash') {
                    wp_delete_post($id, true);
                } else {
                    wp_trash_post($id);
                }
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $message = $currentStatus === 'trash'
                ? __('Items permanently deleted.', 'notifal')
                : __('Items moved to trash.', 'notifal');

            $redirectUrl = add_query_arg([
                'page' => Helper::sanitizeInput( isset( $_GET['page'] ) ? wp_unslash( $_GET['page'] ) : '', 'key' ),
                'status' => 'all'
            ], admin_url('admin.php'));

            ToastManager::success($message, $redirectUrl);
        }
    }

    /**
     * Handle bulk duplicate action (static version).
     *
     * @since 2.0.0
     * @param array $ids Post IDs to duplicate
     * @param string $postType The post type
     */
    protected static function handleBulkDuplicateStatic(array $ids, string $postType): void
    {
        $duplicatedCount = 0;

        foreach ($ids as $id) {
            if (get_post_type($id) === $postType && self::duplicatePostStatic($id)) {
                $duplicatedCount++;
            }
        }

        if ($duplicatedCount > 0) {
            $message = sprintf(
                _n(
                    '%d item duplicated successfully.',
                    '%d items duplicated successfully.',
                    $duplicatedCount,
                    'notifal'
                ),
                $duplicatedCount
            );

            $redirectUrl = add_query_arg([
                'page' => Helper::sanitizeInput( isset( $_GET['page'] ) ? wp_unslash( $_GET['page'] ) : '', 'key' ),
                'status' => 'all'
            ], admin_url('admin.php'));

            ToastManager::success($message, $redirectUrl);
        } else {
            $redirectUrl = add_query_arg([
                'page' => Helper::sanitizeInput( isset( $_GET['page'] ) ? wp_unslash( $_GET['page'] ) : '', 'key' ),
                'status' => 'all'
            ], admin_url('admin.php'));

            ToastManager::error(__('No items were duplicated. Please try again.', 'notifal'), $redirectUrl);
        }
    }

    /**
     * Duplicate a post with all its metadata (static version).
     *
     * @since 2.0.0
     * @param int $postId The ID of the post to duplicate
     * @return int|false The new post ID on success, false on failure
     */
    protected static function duplicatePostStatic(int $postId)
    {
        // Get the original post
        $originalPost = Helper::getPostSafe($postId);
        if (!$originalPost) {
            return false;
        }

        // Check if user can edit this post type
        if (!current_user_can('edit_post', $postId)) {
            return false;
        }

        // Prepare the new post data
        $newPostData = [
            // Translators: Adds (Copy) to duplicated post titles
            'post_title'   => $originalPost->post_title . ' (' . __('Copy', 'notifal') . ')',
            'post_content' => $originalPost->post_content,
            'post_excerpt' => $originalPost->post_excerpt,
            'post_status'  => 'draft',
            'post_type'    => $originalPost->post_type,
            'post_author'  => get_current_user_id(),
            'post_parent'  => $originalPost->post_parent,
            'menu_order'   => $originalPost->menu_order,
            'comment_status' => $originalPost->comment_status,
            'ping_status'    => $originalPost->ping_status,
        ];

        // Insert the new post
        $newPostId = wp_insert_post($newPostData);

        if (is_wp_error($newPostId)) {
            return false;
        }

        // Copy all post meta
        $postMeta = get_post_meta($postId);
        foreach ($postMeta as $metaKey => $metaValues) {
            foreach ($metaValues as $metaValue) {
                $metaValue = maybe_unserialize($metaValue);
                add_post_meta($newPostId, $metaKey, $metaValue);
            }
        }

        // Copy terms (taxonomies)
        $taxonomies = get_object_taxonomies($originalPost->post_type);
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($postId, $taxonomy, ['fields' => 'ids']);
            if (!is_wp_error($terms) && !empty($terms)) {
                wp_set_object_terms($newPostId, $terms, $taxonomy);
            }
        }

        /**
         * Fires after a post has been duplicated.
         *
         * @since 2.0.0
         * @param int $newPostId The ID of the newly created post
         * @param int $originalPostId The ID of the original post
         * @param string $postType The post type
         */
        do_action(ActionHooks::POST_DUPLICATED, $newPostId, $postId, $originalPost->post_type);

        return $newPostId;
    }

    /**
     * Initialize the BaseListView with configuration options.
     *
     * @since 2.0.0
     * @param array<string, mixed> $args {
     *     Configuration arguments for the list view.
     *
     *     @type string   $post_type           Post type to display
     *     @type array    $columns             Column definitions ['key' => 'Label']
     *     @type array    $status_tabs         Status tab definitions
     *     @type array    $bulk_actions        Bulk action definitions
     *     @type string   $title               Page title
     *     @type bool     $bulk_actions_handled Whether bulk actions have been handled externally
     *     @type string   $add_new_url         URL for add new button
     *     @type int      $per_page            Items per page (default: 10)
     *     @type string   $search_placeholder  Search input placeholder
     * }
     */
    public function __construct(array $args = [])
    {
        $this->postType = $args['post_type'] ?? 'post';
        $this->columns = $args['columns'] ?? [];
        $this->currentStatus = Helper::sanitizeInput( isset( $_GET['status'] ) ? wp_unslash( $_GET['status'] ) : 'all', 'key' );
        $this->statusTabs = $args['status_tabs'] ?? [];
        $this->bulkActions = $this->getBulkActions(
            $args['bulk_actions'] ?? [],
            $this->currentStatus,
            $this->postType
        );
        $this->bulkActionsHandled = $args['bulk_actions_handled'] ?? false;
        $this->title = $args['title'] ?? ucfirst($this->postType);
        $this->addNewUrl = $args['add_new_url'] ?? '';
        $this->perPage = max(1, (int) ($args['per_page'] ?? 10));
        $paged_raw = isset( $_GET['paged'] ) ? wp_unslash( $_GET['paged'] ) : '1';
        $this->currentPage = Helper::isPositiveInt( $paged_raw ) ? (int) Helper::sanitizeInput( $paged_raw, 'int' ) : 1;
        $this->searchQuery = Helper::sanitizeInput( isset( $_GET['s'] ) ? wp_unslash( $_GET['s'] ) : '', 'text' );
        $this->searchPlaceholder = $args['search_placeholder'] ?? __('Search...', 'notifal');
    }

    /**
     * Modifies the given bulk actions based on current post status view.
     *
     * - In "trash" view: Adds "restore" action and renames "delete" to "Delete permanently"
     * - In other views: Adds "duplicate" action and leaves other actions unchanged
     *
     * @since 2.0.0
     * @param array  $actions  Original list of bulk actions
     * @param string $status   Current status tab ("all", "trash", ...)
     * @param string $postType Current post type
     * @return array Modified list of bulk actions
     */
    protected function getBulkActions(array $actions, string $status, string $postType): array
    {
        if ($status === 'trash') {
            // Rename 'delete' to 'Delete permanently'
            if (isset($actions['delete'])) {
                $actions['delete'] = __('Delete permanently', 'notifal');
            }

            // Add 'restore' action if not already present
            if (!isset($actions['restore'])) {
                $actions = ['restore' => __('Restore', 'notifal')] + $actions;
            }
        } else {
            // Add duplicate action for non-trash views
            if (!isset($actions['duplicate'])) {
                $actions['duplicate'] = __('Duplicate', 'notifal');
            }
        }

        /**
         * Filters the available bulk actions in the list view.
         *
         * @since 2.0.0
         * @param array  $actions
         * @param string $status
         * @param string $postType
         */
        return apply_filters(FilterHooks::ADMIN_LIST_BULK_ACTIONS, $actions, $status, $postType);
    }

    /**
     * Render the complete list view with all components.
     *
     * Includes header, status tabs, search form, table, and pagination.
     * Fires appropriate WordPress actions before and after rendering.
     *
     * @since 2.0.0
     * @return void
     */
    public function render(): void
    {
        /**
         * Fires before rendering the admin list.
         *
         * @since 2.0.0
         * @param string $post_type The current post type being rendered
         * @param BaseListView $instance The current list view instance
         */
        do_action(ActionHooks::ADMIN_LIST_BEFORE_RENDER, $this->postType, $this);

        // Handle bulk actions before rendering (only if not already handled)
        if (!$this->bulkActionsHandled) {
            $this->handleBulkActions();
        }

        echo '<div class="notifal-admin-list">';
        $this->renderHeader();
        $this->renderStatusTabs();
        $this->renderSearchForm();
        $this->renderTable();
        $this->renderPagination();
        echo '</div>';

        /**
         * Fires after rendering the admin list.
         *
         * @since 2.0.0
         * @param string $post_type The current post type being rendered
         * @param BaseListView $instance The current list view instance
         */
        do_action(ActionHooks::ADMIN_LIST_AFTER_RENDER, $this->postType, $this);
    }

    /**
     * Render the page header with title and "Add New" button.
     *
     * Shows different buttons based on current status (trash vs normal view).
     * Includes conditional buttons for specific post types (e.g., import buttons for notifications).
     *
     * @since 2.0.0
     * @return void
     */
    protected function renderHeader(): void
    {
        echo '<div class="notifal-list-header">';
        echo '<h1 class="notifal-page-title">' . esc_html($this->title) . '</h1>';

        if ($this->currentStatus === 'trash') {
            $emptyTrashUrl = add_query_arg([
                'action' => "notifal_empty_trash_{$this->postType}",
                '_wpnonce' => wp_create_nonce("empty_trash_{$this->postType}")
            ], UrlHelper::admin('admin.php'));

            echo '<a href="' . esc_url($emptyTrashUrl) . '" class="notifal-button danger">'
                . esc_html__('Empty Trash', 'notifal') . '</a>';
        } elseif ($this->addNewUrl) {
            // Use Notifal icon for Add New
            echo '<a href="' . esc_url($this->addNewUrl) . '" class="notifal-button notifal-flex notifal-gap-10 add-new-' . esc_attr($this->postType) . '">'
                . NotifalIconService::render('plus-circle', 20) . esc_html__('Add New', 'notifal') . '</a>';
            
            // Add buttons for OnPage Notifications
            if ($this->postType === 'notifal_onpage_notif') {
                // Import Pre-Created Notifications button (scrolls to precreated section)
                echo '<button type="button" class="notifal-button secondary notifal-flex notifal-gap-10" id="notifal-import-precreated-button">'
                    . NotifalIconService::render('search', 20) . esc_html__('Import Pre-Created Notifications', 'notifal') . '</button>';
                
                // Generate with AI button (full notification JSON prompt)
                echo '<button type="button" class="notifal-button secondary notifal-flex notifal-gap-10" id="notifal-generate-ai-button">'
                    . NotifalIconService::render('ai', 20) . esc_html__('Generate with AI', 'notifal') . '</button>';

                // Import Manually button (existing import functionality)
                echo '<button type="button" class="notifal-button secondary notifal-flex notifal-gap-10" id="notifal-import-button">'
                    . NotifalIconService::render('cloud-download', 20) . esc_html__('Import Manually', 'notifal') . '</button>';
            }
        }

        echo '</div>';
    }

    /**
     * Render status filter tabs with proper URL generation and accessibility.
     *
     * @since 2.0.0
     * @return void
     */
    protected function renderStatusTabs(): void
    {
        if (empty($this->statusTabs)) {
            return;
        }

        $currentPage = Helper::sanitizeInput( isset( $_GET['page'] ) ? wp_unslash( $_GET['page'] ) : '', 'key' );

        if (empty($currentPage)) {
            return; // Cannot generate proper URLs without page context
        }

        echo '<ul class="notifal-status-tabs" role="tablist">';

        foreach ($this->statusTabs as $key => $tab) {
            $isActive = $this->currentStatus === $key;
            $activeClass = $isActive ? 'active' : '';

            // Build URL with proper parameter preservation
            $url = add_query_arg([
                'page' => $currentPage,
                'status' => $key
            ], UrlHelper::admin('admin.php'));

            // Remove search and pagination when switching status to avoid confusion
            $url = remove_query_arg(['s', 'paged'], $url);

            printf(
                '<li class="%s" role="presentation">
                    <a href="%s" role="tab" aria-selected="%s" aria-controls="notifal-list-content">
                        %s <span class="count">(%d)</span>
                    </a>
                </li>',
                esc_attr($activeClass),
                esc_url($url),
                $isActive ? 'true' : 'false',
                esc_html($tab['label']),
                (int) $tab['count']
            );
        }

        echo '</ul>';
    }

    /**
     * Render the search form with proper parameter preservation.
     *
     * @since 2.0.0
     * @return void
     */
    protected function renderSearchForm(): void
    {
        $currentPage = Helper::sanitizeInput( isset( $_GET['page'] ) ? wp_unslash( $_GET['page'] ) : '', 'key' );

        echo '<form method="get" class="notifal-search-form" role="search">';

        // Preserve essential parameters
        if ($currentPage) {
            printf('<input type="hidden" name="page" value="%s" />', esc_attr($currentPage));
        }

        if ($this->currentStatus !== 'all') {
            printf('<input type="hidden" name="status" value="%s" />', esc_attr($this->currentStatus));
        }

        printf(
            '<label class="screen-reader-text" for="search-input">%s</label>
            <input type="search" id="search-input" name="s" value="%s" placeholder="%s" />
            <button type="submit" class="notifal-search-button">
                %s
                <span class="screen-reader-text">%s</span>
                %s
            </button>',
            esc_html($this->searchPlaceholder),
            esc_attr($this->searchQuery),
            esc_attr($this->searchPlaceholder),
            NotifalIconService::render('search', 16),
            esc_html__('Search', 'notifal'),
            esc_html__('Search', 'notifal')
        );

        echo '</form>';
    }

    /**
     * Render the main data table with bulk actions and row content.
     *
     * @since 2.0.0
     * @return void
     */
    protected function renderTable(): void
    {
        $posts = $this->getQueryResults();
        $totalColumns = count($this->columns) + 2; // +2 for checkbox and actions columns

        echo '<form method="post" id="notifal-list-form" class="notifal-list-form">';

        // Add nonce for security
        wp_nonce_field('notifal_bulk_action', 'notifal_bulk_nonce');

        // Render bulk actions if available
        if (!empty($this->bulkActions)) {
            $this->renderBulkActions();
        }

        echo '<table class="notifal-table" role="table">';
        $this->renderTableHeader();

        echo '<tbody>';
        if (empty($posts)) {
            printf(
                '<tr><td colspan="%d" class="no-items">%s</td></tr>',
                $totalColumns,
                esc_html__('No items found.', 'notifal')
            );
        } else {
            foreach ($posts as $post) {
                $this->renderTableRow($post);
            }
        }
        echo '</tbody>';

        echo '</table>';
        echo '</form>';
    }

    /**
     * Render bulk actions dropdown and apply button.
     *
     * @since 2.0.0
     * @return void
     */
    protected function renderBulkActions(): void
    {
        echo '<div class="notifal-bulk-actions">';
        echo '<select name="bulk_action" class="notifal-bulk-select" aria-label="' . esc_attr__('Select bulk action', 'notifal') . '">';
        echo '<option value="">' . esc_html__('Bulk Actions', 'notifal') . '</option>';

        foreach ($this->bulkActions as $key => $label) {
            printf('<option value="%s">%s</option>', esc_attr($key), esc_html($label));
        }

        echo '</select>';
        printf(
            '<button type="submit" class="notifal-button secondary">%s</button>',
            esc_html__('Apply', 'notifal')
        );
        echo '</div>';
    }

    /**
     * Render table header with column titles and master checkbox.
     *
     * @since 2.0.0
     * @return void
     */
    protected function renderTableHeader(): void
    {
        echo '<thead><tr role="row">';
        printf(
            '<th scope="col" class="check-column">
                <input type="checkbox" class="notifal-master-checkbox" aria-label="%s" />
            </th>',
            esc_attr__('Select all items', 'notifal')
        );

        foreach ($this->columns as $key => $label) {
            printf('<th scope="col">%s</th>', esc_html($label));
        }

        printf('<th scope="col">%s</th>', esc_html__('Actions', 'notifal'));
        echo '</tr></thead>';
    }

    /**
     * Render a single table row for a post.
     *
     * @since 2.0.0
     * @param WP_Post $post The post object to render
     * @return void
     */
    protected function renderTableRow(WP_Post $post): void
    {
        echo '<tr>';

        // Checkbox column
        printf(
            '<td class="check-column">
                <input type="checkbox" name="ids[]" value="%d" class="notifal-row-checkbox" aria-label="%s" />
            </td>',
            (int) $post->ID,
            esc_attr(sprintf(__('Select %s', 'notifal'), $post->post_title))
        );

        // Data columns
        foreach (array_keys($this->columns) as $colKey) {
            echo '<td>';
            $this->renderColumnContent($colKey, $post);
            echo '</td>';
        }

        // Actions column
        echo '<td class="notifal-list-actions">';
        $this->renderRowActions($post);
        echo '</td>';

        echo '</tr>';
    }

    /**
     * Allowlist for {@see wp_kses()} when list cells include interactive markup (e.g. status toggle buttons).
     *
     * {@see wp_kses_post()} strips `class` and `data-*` from `<button>` and `class` from `<span>` because default
     * post HTML rules only allow a small attribute set, which breaks admin list scripts and badges.
     *
     * @since 2.2.0
     * @return array<string, array<string, bool>> KSES allowlist.
     */
    protected function getKsesAllowedForListInteractiveHtml(): array
    {
        $allowed = wp_kses_allowed_html('post');

        if (!isset($allowed['button']) || !is_array($allowed['button'])) {
            $allowed['button'] = [];
        }

        $allowed['button'] = array_merge(
            $allowed['button'],
            [
                'class' => true,
                'type' => true,
                'title' => true,
                'aria-label' => true,
                'disabled' => true,
                'data-campaign-id' => true,
                'data-current-active' => true,
                'data-toggle-bound' => true,
                'data-*' => true,
            ]
        );

        if (!isset($allowed['span']) || !is_array($allowed['span'])) {
            $allowed['span'] = [];
        }

        $allowed['span'] = array_merge(
            $allowed['span'],
            [
                'class' => true,
                'role' => true,
                'aria-label' => true,
                'data-*' => true,
            ]
        );

        return $allowed;
    }

    /**
     * Render content for a specific column.
     *
     * @since 2.0.0
     * @param string $columnKey The column key
     * @param WP_Post $post The post object
     * @return void
     */
    protected function renderColumnContent(string $columnKey, WP_Post $post): void
    {
        switch ($columnKey) {
            case 'title':
                $editLink = get_edit_post_link($post);
                $title    = $post->post_title ?: '(' . __('no title', 'notifal') . ')';

                if ($editLink && current_user_can('edit_post', $post->ID)) {
                    printf(
                        '<a href="%s" class="row-title" aria-label="%s">%s</a>',
                        esc_url($editLink),
                        esc_attr(sprintf(__('Edit %s', 'notifal'), $title)),
                        esc_html($title)
                    );
                } else {
                    echo esc_html($title);
                }

                // Show post status if it's not "publish"
                if (in_array($post->post_status, ['draft', 'pending', 'future'], true)) {
                    printf(
                        ' <span class="post-state">— %s</span>',
                        esc_html(ucfirst($post->post_status))
                    );
                }
                break;

            case 'date':
                printf(
                    '<time datetime="%s">%s</time>',
                    esc_attr(get_the_date('c', $post)),
                    esc_html(get_the_date('', $post))
                );
                break;

            case 'status':
                $customStatus = apply_filters(
                    FilterHooks::ADMIN_LIST_CUSTOM_COLUMN,
                    '',
                    'status',
                    $post,
                    $this->postType
                );

                if (!empty($customStatus)) {
                    echo wp_kses($customStatus, $this->getKsesAllowedForListInteractiveHtml());
                    break;
                }

                $status = get_post_status($post);
                $statusObj = get_post_status_object($status);
                echo esc_html($statusObj ? $statusObj->label : $status);
                break;

            case 'category':
                $taxonomy = $this->categoryTaxonomy ?? "{$this->postType}_category";
                $terms = get_the_terms($post, $taxonomy);
                if (!empty($terms) && !is_wp_error($terms)) {
                    echo esc_html(implode(', ', wp_list_pluck($terms, 'name')));
                } else {
                    echo '&mdash;';
                }
                break;

            default:
                /**
                 * Filter to render custom column content.
                 *
                 * @since 2.0.0
                 * @param string $content Default content (empty string)
                 * @param string $column_key The column key
                 * @param WP_Post $post The current post object
                 * @param string $post_type The current post type
                 */
                $content = apply_filters(FilterHooks::ADMIN_LIST_CUSTOM_COLUMN, '', $columnKey, $post, $this->postType);
                echo wp_kses_post($content ?: '&mdash;');
                break;
        }
    }

    /**
     * Render action buttons for a table row.
     *
     * @since 2.0.0
     * @param WP_Post $post The post object
     * @return void
     */
    protected function renderRowActions(WP_Post $post): void
    {
        /**
         * Fires before rendering row actions.
         *
         * @since 2.0.0
         * @param WP_Post $post The current post
         * @param string $post_type The post type
         */
        do_action(ActionHooks::ADMIN_LIST_ACTIONS_BEFORE, $post, $this->postType);

        $actions = [];
        $isTrash = $this->currentStatus === 'trash';

        // Edit action
        if (!$isTrash && current_user_can('edit_post', $post->ID)) {
            if ($this->postType === 'notifal_template') {
                /** @var TemplateUrlService $urlService */
                $urlService = notifal_app(TemplateUrlService::class);
                $editLink = $urlService->getEditUrl($post);
            } else {
                $isElementor = ElementorHelper::hasBuilder($post);
                $editLink = $isElementor
                    ? ElementorHelper::getEditUrl($post->ID)
                    : get_edit_post_link($post);
            }

            if ($editLink) {
                $actions['edit'] = sprintf(
                    '<a href="%s" class="notifal-button secondary" title="%s" aria-label="%s">
                        %s
                    </a>',
                    esc_url($editLink),
                    esc_attr__('Edit', 'notifal'),
                    esc_attr(sprintf(__('Edit %s', 'notifal'), $post->post_title)),
                    NotifalIconService::render('pencil-square', 20)
                );
            }
        }

        // View action (only for published posts)
        if (!$isTrash && $post->post_status === 'publish') {
            // Internal template CPT uses query-arg preview, not public permalinks.
            if ($post->post_type === 'notifal_template') {
                $permalink = notifal_app(TemplateUrlService::class)->getPreviewUrl((int) $post->ID, $post);
            } else {
                $permalink = get_permalink($post);
            }

            if ($permalink) {
                $actions['view'] = sprintf(
                    '<a href="%s" class="notifal-button secondary" title="%s" target="_blank" rel="noopener" aria-label="%s">
                        %s
                    </a>',
                    esc_url($permalink),
                    esc_attr__('View', 'notifal'),
                    esc_attr(sprintf(__('View %s', 'notifal'), $post->post_title)),
                    NotifalIconService::render('eye', 20)
                );
            }
        }

        // Duplicate action (only for non-trash posts)
        if (!$isTrash && current_user_can('edit_post', $post->ID)) {
            $duplicateUrl = $this->getDuplicateUrl($post);
            $actions['duplicate'] = sprintf(
                '<a href="%s" class="notifal-button secondary" title="%s" aria-label="%s">
                    %s
                </a>',
                esc_url($duplicateUrl),
                esc_attr__('Duplicate', 'notifal'),
                esc_attr(sprintf(__('Duplicate %s', 'notifal'), $post->post_title)),
                NotifalIconService::render('copy', 20)
            );
        }

        // Delete action
        if (current_user_can('delete_post', $post->ID)) {
            $deleteUrl = $this->getDeleteUrl($post);
            $confirmMessage = $isTrash
                ? esc_js(sprintf(__('Are you sure you want to permanently delete “%s”?', 'notifal'), $post->post_title))
                : esc_js(sprintf(__('Are you sure you want to move “%s” to trash?', 'notifal'), $post->post_title));

            $actions['delete'] = sprintf(
                '<a href="%s" class="notifal-button secondary delete-link" title="%s" 
                   data-confirm-message="%s" aria-label="%s">
                    %s
                </a>',
                esc_url($deleteUrl),
                esc_attr__('Delete', 'notifal'),
                $confirmMessage,
                esc_attr(sprintf(__('Delete %s', 'notifal'), $post->post_title)),
                NotifalIconService::render('trash', 20)
            );
        }

        /**
         * Filter the row actions for a post.
         *
         * @since 2.0.0
         * @param array<string, string> $actions Associative array of action key => HTML
         * @param WP_Post $post The current post
         * @param string $post_type The post type
         */
        $actions = apply_filters(FilterHooks::ADMIN_LIST_ROW_ACTIONS, $actions, $post, $this->postType);

        echo implode(' ', $actions);

        /**
         * Fires after rendering row actions.
         *
         * @since 2.0.0
         * @param WP_Post $post The current post
         * @param string $post_type The post type
         */
        do_action(ActionHooks::ADMIN_LIST_ACTIONS_AFTER, $post, $this->postType);
    }

    /**
     * Render pagination controls with smart URL handling.
     *
     * @since 2.0.0
     * @return void
     */
    protected function renderPagination(): void
    {
        $total = $this->getQueryTotal();
        $totalPages = (int) ceil($total / $this->perPage);

        if ($totalPages <= 1) {
            return;
        }

        $range = 2;
        $start = max(1, $this->currentPage - $range);
        $end = min($totalPages, $this->currentPage + $range);
        $baseUrl = remove_query_arg(['notifal_message', 'notifal_message_type', 'paged']);

        echo '<div class="notifal-pagination-wrapper">';
        echo '<div class="notifal-pagination-info">';
        printf(
            esc_html__('Showing %1$d to %2$d of %3$d items', 'notifal'),
            (($this->currentPage - 1) * $this->perPage) + 1,
            min($this->currentPage * $this->perPage, $total),
            $total
        );
        echo '</div>';

        echo '<div class="notifal-pagination">';

        // Previous button
        if ($this->currentPage > 1) {
            echo '<a class="page-number" href="' . esc_url(add_query_arg('paged', $this->currentPage - 1, $baseUrl)) . '">&laquo;</a>';
        }

        // First page and ellipsis
        if ($start > 1) {
            echo '<a class="page-number" href="' . esc_url(add_query_arg('paged', 1, $baseUrl)) . '">1</a>';
            if ($start > 2) {
                echo '<span class="page-dots">...</span>';
            }
        }

        // Page numbers
        for ($i = $start; $i <= $end; $i++) {
            $active = ($i === $this->currentPage) ? ' active' : '';
            echo '<a class="page-number' . esc_attr($active) . '" href="' . esc_url(add_query_arg('paged', $i, $baseUrl)) . '">' . $i . '</a>';
        }

        // Last page and ellipsis
        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                echo '<span class="page-dots">...</span>';
            }
            echo '<a class="page-number" href="' . esc_url(add_query_arg('paged', $totalPages, $baseUrl)) . '">' . $totalPages . '</a>';
        }

        // Next button
        if ($this->currentPage < $totalPages) {
            echo '<a class="page-number" href="' . esc_url(add_query_arg('paged', $this->currentPage + 1, $baseUrl)) . '">&raquo;</a>';
        }

        echo '</div></div>';
    }

    /**
     * Get posts for the current query with proper filtering and pagination.
     *
     * @since 2.0.0
     * @return WP_Post[] Array of post objects
     */
    protected function getQueryResults(): array
    {
        $args = [
            'post_type'      => $this->postType,
            'post_status'    => $this->getPostStatusForQuery(),
            'posts_per_page' => $this->perPage,
            'paged'          => $this->currentPage,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        // Add search query if present
        if (!empty($this->searchQuery)) {
            $args['s'] = $this->searchQuery;
        }

        /**
         * Filter the query arguments before executing.
         *
         * @since 2.0.0
         * @param array $args Query arguments
         * @param string $post_type Current post type
         * @param BaseListView $instance Current instance
         */
        $args = apply_filters(FilterHooks::ADMIN_LIST_QUERY_ARGS, $args, $this->postType, $this);

        $query = new WP_Query($args);

        // Cache the total count to avoid duplicate queries
        $this->queryTotal = $query->found_posts;

        return $query->posts ?: [];
    }

    /**
     * Get total count of posts matching current filters.
     *
     * Uses cached count from main query when available to avoid duplicate database queries.
     *
     * @since 2.0.0
     * @return int Total number of posts
     */
    protected function getQueryTotal(): int
    {
        // Return cached total if available from main query
        if ($this->queryTotal !== null) {
            return (int) $this->queryTotal;
        }

        // Fallback: Run separate count query with optimized arguments
        $args = [
            'post_type'      => $this->postType,
            'post_status'    => $this->getPostStatusForQuery(),
            'posts_per_page' => -1,
            'fields'         => 'ids', // Only get IDs for better performance
        ];

        // Add search query if present
        if (!empty($this->searchQuery)) {
            $args['s'] = $this->searchQuery;
        }

        /**
         * Filter the count query arguments.
         *
         * @since 2.0.0
         * @param array $args Query arguments
         * @param string $post_type Current post type
         * @param BaseListView $instance Current instance
         */
        $args = apply_filters(FilterHooks::ADMIN_LIST_COUNT_QUERY_ARGS, $args, $this->postType, $this);

        $query = new WP_Query($args);
        return (int) $query->found_posts;
    }

    /**
     * Get the appropriate post status for the current query.
     *
     * @since 2.0.0
     * @return string|array Post status or array of statuses
     */
    /**
     * Get the appropriate post status for the current query.
     *
     * @since 2.0.0
     * @return string|array Post status or array of statuses
     */
    protected function getPostStatusForQuery()
    {
        if ($this->currentStatus === 'all') {
            return 'any';
        }

        // Validate that the status is a real post status
        $availableStatuses = get_post_stati();
        if (isset($availableStatuses[$this->currentStatus])) {
            return $this->currentStatus;
        }

        return 'any'; // Fallback to 'any' if invalid status
    }

    /**
     * Generate the delete URL for a specific post.
     *
     * Creates a route-style URL compatible with custom route handlers that listen
     * for actions like `notifal_delete_{post_type}`.
     *
     * @since 2.0.0
     * @param WP_Post $post The post object to generate delete URL for
     * @return string The admin URL that triggers the delete action
     */
    protected function getDeleteUrl(WP_Post $post): string
    {
        $args = [
            'action' => "notifal_delete_{$this->postType}",
            'id'     => $post->ID,
            '_wpnonce' => wp_create_nonce("delete_{$this->postType}_{$post->ID}")
        ];

        // Preserve current status filter if present
        if ($this->currentStatus !== 'all') {
            $args['status'] = $this->currentStatus;
        }

        return add_query_arg($args, UrlHelper::admin('admin.php'));
    }

    /**
     * Generate the duplicate URL for a specific post.
     *
     * Creates a route-style URL compatible with custom route handlers that listen
     * for actions like `notifal_duplicate_{post_type}`.
     *
     * @since 2.0.0
     * @param WP_Post $post The post object to generate duplicate URL for
     * @return string The admin URL that triggers the duplicate action
     */
    protected function getDuplicateUrl(WP_Post $post): string
    {
        $args = [
            'action' => "notifal_duplicate_{$this->postType}",
            'id'     => $post->ID,
            '_wpnonce' => wp_create_nonce("duplicate_{$this->postType}_{$post->ID}")
        ];

        // Preserve current status filter if present
        if ($this->currentStatus !== 'all') {
            $args['status'] = $this->currentStatus;
        }

        return add_query_arg($args, UrlHelper::admin('admin.php'));
    }

    /**
     * Duplicate a post with all its metadata, content, and taxonomies.
     *
     * Creates a copy of the original post with "(Copy)" appended to the title
     * and sets the status to draft. Copies all post meta, content, and taxonomy terms.
     *
     * @since 2.0.0
     * @param int $postId The ID of the post to duplicate
     * @return int|false The new post ID on success, false on failure
     */
    protected function duplicatePost(int $postId)
    {
        // Get the original post
        $originalPost = Helper::getPostSafe($postId, $this->postType);
        if (!$originalPost) {
            return false;
        }

        // Check if user can edit this post type
        if (!current_user_can('edit_post', $postId)) {
            return false;
        }

        // Prepare the new post data
        $newPostData = [
            // Translators: Adds (Copy) to duplicated post titles
            'post_title'   => $originalPost->post_title . ' (' . __('Copy', 'notifal') . ')',
            'post_content' => $originalPost->post_content,
            'post_excerpt' => $originalPost->post_excerpt,
            'post_status'  => 'draft',
            'post_type'    => $this->postType,
            'post_author'  => get_current_user_id(),
            'post_parent'  => $originalPost->post_parent,
            'menu_order'   => $originalPost->menu_order,
            'comment_status' => $originalPost->comment_status,
            'ping_status'    => $originalPost->ping_status,
        ];

        // Insert the new post
        $newPostId = wp_insert_post($newPostData);

        if (is_wp_error($newPostId)) {
            return false;
        }

        // Copy all post meta
        $postMeta = get_post_meta($postId);
        foreach ($postMeta as $metaKey => $metaValues) {
            foreach ($metaValues as $metaValue) {
                $metaValue = maybe_unserialize($metaValue);
                add_post_meta($newPostId, $metaKey, $metaValue);
            }
        }

        // Copy terms (taxonomies)
        $taxonomies = get_object_taxonomies($this->postType);
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($postId, $taxonomy, ['fields' => 'ids']);
            if (!is_wp_error($terms) && !empty($terms)) {
                wp_set_object_terms($newPostId, $terms, $taxonomy);
            }
        }

        /**
         * Fires after a post has been duplicated.
         *
         * @since 2.0.0
         * @param int $newPostId The ID of the newly created post
         * @param int $originalPostId The ID of the original post
         * @param string $postType The post type
         */
        do_action(ActionHooks::POST_DUPLICATED, $newPostId, $postId, $this->postType);

        return $newPostId;
    }

    /**
     * Handle single duplicate action triggered via URL parameters.
     *
     * Processes GET requests for duplicating individual posts with proper
     * security validation and user capability checks.
     *
     * @since 2.0.0
     * @return void
     */
    protected function handleSingleDuplicateAction(): void
    {
        $action = Helper::sanitizeInput( isset( $_GET['action'] ) ? wp_unslash( $_GET['action'] ) : '', 'key' );
        $post_id_raw = isset( $_GET['id'] ) ? wp_unslash( $_GET['id'] ) : '0';
        $postId = Helper::isPositiveInt( $post_id_raw ) ? (int) Helper::sanitizeInput( $post_id_raw, 'int' ) : 0;
        $nonce = Helper::sanitizeInput( isset( $_GET['_wpnonce'] ) ? wp_unslash( $_GET['_wpnonce'] ) : '', 'key' );

        if ($action !== "notifal_duplicate_{$this->postType}" || $postId <= 0) {
            return;
        }

        if (!wp_verify_nonce($nonce, "duplicate_{$this->postType}_{$postId}")) {
            ToastManager::error(__('Security check failed. Please try again.', 'notifal'));
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            ToastManager::error(__('You do not have permission to duplicate this item.', 'notifal'));
            return;
        }

        $newPostId = $this->duplicatePost($postId);
        $newPostId = $this->duplicatePost($postId);
        if ($newPostId) {
            ToastManager::success(__('Item duplicated successfully.', 'notifal'));

            // Redirect to edit the new post
            $editUrl = get_edit_post_link($newPostId);
            if ($editUrl) {
                wp_safe_redirect($editUrl);
                exit;
            }
        } else {
            ToastManager::error(__('Failed to duplicate item. Please try again.', 'notifal'));
        }
    }


    /**
     * Processes the selected bulk action (e.g., delete) for the current post type.
     *
     * Validates the POST request and executes the appropriate bulk action logic.
     *
     * @since 2.0.0
     * @return void
     */
    protected function handleBulkActions(): void
    {
        // Handle single duplicate action from URL first
        $this->handleSingleDuplicateAction();

        // Only process POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        // Verify nonce first (WordPress security guideline; pass raw value for wp_verify_nonce)
        $nonce = isset( $_POST['notifal_bulk_nonce'] ) ? wp_unslash( $_POST['notifal_bulk_nonce'] ) : '';
        if ( $nonce === '' || ! wp_verify_nonce( $nonce, 'notifal_bulk_action' ) ) {
            ToastManager::error(__('Security check failed. Please try again.', 'notifal'));
            return;
        }

        $action = isset( $_POST['bulk_action'] ) ? wp_unslash( $_POST['bulk_action'] ) : '';
        $ids    = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? $_POST['ids'] : [];

        if ( empty( $action ) ) {
            ToastManager::error(__('Please select a bulk action first.', 'notifal'));
            return;
        }

        if ( empty( $ids ) ) {
            ToastManager::error(__('Please select at least one item to perform this action on.', 'notifal'));
            return;
        }

        // Sanitize inputs after nonce verification
        $ids    = array_map( 'absint', array_filter( array_map( 'absint', $ids ) ) );
        $action = Helper::sanitizeInput( $action, 'key' );

        // Process actions with early returns for better performance
        switch ($action) {
            case 'restore':
                $this->handleBulkRestore($ids);
                break;

            case 'delete':
                $this->handleBulkDelete($ids);
                break;

            case 'duplicate':
                $this->handleBulkDuplicate($ids);
                break;

            default:
                do_action(ActionHooks::ADMIN_LIST_HANDLE_BULK_ACTION, $action, $ids, $this->postType);
                break;
        }
    }

    /**
     * Handle bulk restore action.
     *
     * @since 2.0.0
     * @param array $ids Post IDs to restore
     */
    protected function handleBulkRestore(array $ids): void
    {
        foreach ($ids as $id) {
            if (get_post_status($id) === 'trash') {
                wp_untrash_post($id);
            }
        }
        ToastManager::success(__('Items restored successfully.', 'notifal'));
    }

    /**
     * Handle bulk delete action.
     *
     * @since 2.0.0
     * @param array $ids Post IDs to delete
     */
    protected function handleBulkDelete(array $ids): void
    {
        $deleted = 0;
        foreach ($ids as $id) {
            if (get_post_type($id) === $this->postType) {
                if ($this->currentStatus === 'trash') {
                    wp_delete_post($id, true);
                } else {
                    wp_trash_post($id);
                }
                $deleted++;
            }
        }

        if ($deleted > 0) {
            ToastManager::success(
                $this->currentStatus === 'trash'
                    ? __('Items permanently deleted.', 'notifal')
                    : __('Items moved to trash.', 'notifal')
            );
        }
    }

    /**
     * Handle bulk duplicate action.
     *
     * @since 2.0.0
     * @param array $ids Post IDs to duplicate
     */
    protected function handleBulkDuplicate(array $ids): void
    {
        $duplicatedCount = 0;
        foreach ($ids as $id) {
            if (get_post_type($id) === $this->postType && $this->duplicatePost($id)) {
                $duplicatedCount++;
            }
        }

        if ($duplicatedCount > 0) {
            ToastManager::success(
                sprintf(
                    _n(
                        '%d item duplicated successfully.',
                        '%d items duplicated successfully.',
                        $duplicatedCount,
                        'notifal'
                    ),
                    $duplicatedCount
                )
            );
        } else {
            ToastManager::error(__('No items were duplicated. Please try again.', 'notifal'));
        }
    }




}
