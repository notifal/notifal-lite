<?php

namespace Notifal\Modules\Forms\Presentation\Admin\Menu;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Forms\Presentation\Admin\Lists\SubmissionListView;
use Notifal\Shared\AdminUI\Lists\BaseListView;

defined('ABSPATH') || exit;

/**
 * Forms menus under Notifal with a hover flyout for child screens.
 *
 * Child screens stay registered under Notifal for capability checks. CSS hides
 * them on non-Forms pages. On Forms screens a body class reveals the native
 * WordPress submenu rows. Elsewhere JS renders a hover flyout on Forms.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class MenuController
{
    /**
     * Visible Forms parent slug under Notifal.
     *
     * @var string
     */
    private const MENU_SLUG = 'notifal-forms';

    /**
     * Child page slugs that stay registered but are CSS-hidden by default.
     *
     * @var string[]
     */
    private const CHILD_SLUGS = [
        'notifal-form',
        'notifal-form-submissions',
        'notifal-form-settings',
    ];

    /**
     * Register menu hooks.
     *
     * @return void
     * @since 3.0.0
     */
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'addMenu'], 25);
        add_action('admin_menu', [self::class, 'renameFormsItemOnSection'], 999);
        add_filter('submenu_file', [self::class, 'highlightFormsParent']);
        add_filter('parent_file', [self::class, 'highlightNotifalParent']);
        add_filter('admin_body_class', [self::class, 'addFormsSectionBodyClass']);
        add_filter(FilterHooks::ADMIN_GLOBAL_BACKEND_DATA, [self::class, 'addGlobalBackendData']);
    }

    /**
     * On Forms screens, rename the Forms row to "All Forms" so the open section
     * matches the flyout list (All Forms, Add New, Submissions, Settings).
     *
     * @return void
     * @since 3.0.0
     */
    public static function renameFormsItemOnSection(): void
    {
        if (!self::isFormsSectionScreen()) {
            return;
        }

        global $submenu;

        if (!isset($submenu['notifal']) || !is_array($submenu['notifal'])) {
            return;
        }

        foreach ($submenu['notifal'] as $key => $item) {
            $slug = isset($item[2]) ? (string) $item[2] : '';

            if ($slug !== self::MENU_SLUG) {
                continue;
            }

            // Menu title is index 0 in WordPress submenu rows.
            $submenu['notifal'][$key][0] = esc_html__('All Forms', 'notifal');
            break;
        }
    }

    /**
     * Whether the current admin request is inside the Forms section.
     *
     * @return bool
     * @since 3.0.0
     */
    public static function isFormsSectionScreen(): bool
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';

        if ($page === self::MENU_SLUG || in_array($page, self::CHILD_SLUGS, true)) {
            return true;
        }

        // Form Settings redirects to Settings → Forms.
        if ($page === 'notifal-settings') {
            $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash((string) $_GET['tab'])) : '';
            return $tab === 'forms';
        }

        return false;
    }

    /**
     * Mark Forms-section screens so CSS can reveal native child menu rows.
     *
     * @param string $classes Existing admin body classes.
     * @return string
     * @since 3.0.0
     */
    public static function addFormsSectionBodyClass($classes)
    {
        if (self::isFormsSectionScreen()) {
            $classes .= ' notifal-forms-section';
        }

        return $classes;
    }

    /**
     * Append Forms menu flyout data for the global backend script.
     *
     * @param array<string, mixed> $data Existing global backend data.
     * @return array<string, mixed>
     * @since 3.0.0
     */
    public static function addGlobalBackendData(array $data): array
    {
        $data['formsMenu'] = [
            // When true, native WP submenu rows are visible and JS skips injection.
            'sectionActive' => self::isFormsSectionScreen(),
            'items'         => self::getFlyoutItems(),
        ];

        return $data;
    }

    /**
     * Register Forms and child screens under Notifal.
     *
     * @return void
     * @since 3.0.0
     */
    public static function addMenu(): void
    {
        // Visible Forms item inside the Notifal menu (list screen).
        // Label stays "Forms" for the hover flyout; renamed to "All Forms" on Forms screens.
        $hook = add_submenu_page(
            'notifal',
            __('All Forms', 'notifal'),
            __('Forms', 'notifal'),
            'manage_options',
            self::MENU_SLUG,
            [self::class, 'renderList']
        );

        // Bulk actions must run before list output starts.
        add_action("load-{$hook}", [self::class, 'handleBulkActions']);

        // Keep children registered so WordPress capability checks always pass.
        // On Forms screens CSS reveals these under Forms; elsewhere the hover flyout lists them.
        add_submenu_page(
            'notifal',
            __('Add New Form', 'notifal'),
            __('Add New Form', 'notifal'),
            'manage_options',
            'notifal-form',
            [self::class, 'renderEdit']
        );

        // Capture the hook so bulk delete can run before list output.
        $submissionsHook = add_submenu_page(
            'notifal',
            __('Form Submissions', 'notifal'),
            __('Form Submissions', 'notifal'),
            'manage_options',
            'notifal-form-submissions',
            [self::class, 'renderSubmissions']
        );
        if (is_string($submissionsHook) && $submissionsHook !== '') {
            add_action("load-{$submissionsHook}", [self::class, 'handleSubmissionBulkActions']);
        }

        add_submenu_page(
            'notifal',
            __('Form Settings', 'notifal'),
            __('Form Settings', 'notifal'),
            'manage_options',
            'notifal-form-settings',
            [self::class, 'renderFormSettingsRedirect']
        );
    }

    /**
     * Keep Forms highlighted for child Form screens.
     *
     * @param string|null $submenu_file Current submenu file.
     * @return string|null
     * @since 3.0.0
     */
    public static function highlightFormsParent($submenu_file)
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash((string) $_GET['tab'])) : '';

        if (in_array($page, self::CHILD_SLUGS, true)) {
            return self::MENU_SLUG;
        }

        if ($page === 'notifal-settings' && $tab === 'forms') {
            return self::MENU_SLUG;
        }

        return $submenu_file;
    }

    /**
     * Keep the Notifal top-level menu open on Forms screens.
     *
     * @param string $parent_file Current parent file.
     * @return string
     * @since 3.0.0
     */
    public static function highlightNotifalParent($parent_file)
    {
        if (self::isFormsSectionScreen()) {
            return 'notifal';
        }

        return $parent_file;
    }

    /**
     * Nested flyout links shown when hovering the Forms item.
     *
     * @return array<int, array{label: string, url: string, slug: string}>
     * @since 3.0.0
     */
    public static function getFlyoutItems(): array
    {
        return [
            [
                'label' => __('All Forms', 'notifal'),
                'url'   => admin_url('admin.php?page=notifal-forms'),
                'slug'  => 'notifal-forms',
            ],
            [
                'label' => __('Add New Form', 'notifal'),
                'url'   => admin_url('admin.php?page=notifal-form'),
                'slug'  => 'notifal-form',
            ],
            [
                'label' => __('Form Submissions', 'notifal'),
                'url'   => admin_url('admin.php?page=notifal-form-submissions'),
                'slug'  => 'notifal-form-submissions',
            ],
            [
                'label' => __('Form Settings', 'notifal'),
                'url'   => admin_url('admin.php?page=notifal-settings&tab=forms'),
                'slug'  => 'notifal-form-settings',
            ],
        ];
    }

    /**
     * Render the forms list screen.
     *
     * @return void
     * @since 3.0.0
     */
    public static function renderList(): void
    {
        notifal_view('Forms.Admin.form-list');
    }

    /**
     * Render the form editor screen.
     *
     * @return void
     * @since 3.0.0
     */
    public static function renderEdit(): void
    {
        notifal_view('Forms.Admin.Edit.index');
    }

    /**
     * Render submissions list or single submission detail.
     *
     * @return void
     * @since 3.0.0
     */
    public static function renderSubmissions(): void
    {
        $submissionId = isset($_GET['submission_id']) ? absint(wp_unslash($_GET['submission_id'])) : 0;

        if ($submissionId > 0) {
            notifal_view('Forms.Admin.Submissions.detail');
            return;
        }

        notifal_view('Forms.Admin.Submissions.index');
    }

    /**
     * Redirect Form Settings submenu to the Forms tab in Notifal Settings.
     *
     * @return void
     * @since 3.0.0
     */
    public static function renderFormSettingsRedirect(): void
    {
        wp_safe_redirect(admin_url('admin.php?page=notifal-settings&tab=forms'));
        exit;
    }

    /**
     * Handle list-table bulk actions for the Form CPT.
     *
     * @return void
     * @since 3.0.0
     */
    public static function handleBulkActions(): void
    {
        BaseListView::handleBulkActionsForPostType('notifal_form');
    }

    /**
     * Handle bulk delete on the form submissions list screen.
     *
     * @return void
     * @since 3.0.0
     */
    public static function handleSubmissionBulkActions(): void
    {
        SubmissionListView::handleBulkActions();
    }
}
