<?php

namespace Notifal\Modules\Forms\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use WP_Query;

defined('ABSPATH') || exit;

/**
 * Server-side license guard: max 1 Form CPT without Pro.
 *
 * @since 3.0.0
 * @updated 3.0.0 Expose reusable Form CPT id when free create limit is reached.
 * @author Hossein <hossein@notifal.com>
 */
class FormLicenseGuard
{
    /**
     * Register create/publish guards.
     *
     * @return void
     * @since 3.0.0
     */
    public static function register(): void
    {
        add_filter('wp_insert_post_data', [self::class, 'guardInsert'], 10, 2);
        add_action('admin_notices', [self::class, 'maybeShowLimitNotice']);
    }

    /**
     * Whether multiple forms are allowed (Pro filter, default false).
     *
     * @return bool
     * @since 3.0.0
     */
    public static function allowsMultiple(): bool
    {
        return (bool) apply_filters(FilterHooks::FORMS_MULTIPLE_ALLOWED, false);
    }

    /**
     * Count non-trash Form CPT posts.
     *
     * @return int
     * @since 3.0.0
     */
    public static function countForms(): int
    {
        $query = new WP_Query([
            'post_type'      => 'notifal_form',
            'post_status'    => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => false,
        ]);

        return (int) $query->found_posts;
    }

    /**
     * Whether a new form may be created.
     *
     * @return bool
     * @since 3.0.0
     */
    public static function canCreate(): bool
    {
        if (self::allowsMultiple()) {
            return true;
        }

        return self::countForms() < 1;
    }

    /**
     * Oldest Form CPT id suitable for reuse when create is blocked by the free limit.
     *
     * Used by AI OnPage / Setup Wizard auto-connect so unbound markup still gets
     * data-form-id > 0 instead of staying disconnected in the builder.
     *
     * @return int Form post ID, or 0 when no reusable Form exists.
     * @since 3.0.0
     */
    public static function getReusableFormId(): int
    {
        // Prefer the oldest published/draft Form so reuse stays stable across imports.
        $query = new WP_Query([
            'post_type'              => 'notifal_form',
            'post_status'            => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'orderby'                => 'ID',
            'order'                  => 'ASC',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        // Empty installs have nothing to reuse until the first Form is created.
        if (empty($query->posts[0])) {
            return 0;
        }

        return absint($query->posts[0]);
    }

    /**
     * Block insert when over free limit (filter default false).
     *
     * @param array<string, mixed> $data    Post data.
     * @param array<string, mixed> $postarr Original postarr.
     * @return array<string, mixed>
     * @since 3.0.0
     */
    public static function guardInsert(array $data, array $postarr): array
    {
        if (!isset($data['post_type']) || $data['post_type'] !== 'notifal_form') {
            return $data;
        }

        $postId = isset($postarr['ID']) ? absint($postarr['ID']) : 0;
        if ($postId > 0) {
            return $data;
        }

        if (self::canCreate()) {
            return $data;
        }

        // Force draft rejection path by emptying title and using auto-draft status.
        $data['post_status'] = 'auto-draft';
        $data['post_title'] = '';

        return $data;
    }

    /**
     * Admin notice when free limit is reached.
     *
     * @return void
     * @since 3.0.0
     */
    public static function maybeShowLimitNotice(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
        if ($page !== 'notifal-forms' && $page !== 'notifal-form') {
            return;
        }

        if (self::allowsMultiple() || self::canCreate()) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__(
            'Notifal free includes one form. Upgrade to Notifal Pro to create additional forms.',
            'notifal'
        );
        echo '</p></div>';
    }
}
