<?php

namespace Notifal\Infrastructure\WordPress\Admin\Support;

use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\Ai\Application\Services\InstallationCredentialStore;
use Notifal\Modules\Ai\Application\Services\SiteProfileService;

defined('ABSPATH') || exit;

/**
 * Builds sanitized site context snapshots for backend NAS support turns.
 *
 * @since 3.0.0
 * @updated 3.0.0 Report accurate Pro install, Pro license, and hosted AI connection state.
 * @author Hossein <hossein@notifal.com>
 */
class NasSupportContextService
{
    /**
     * Request-scoped cache for the resolved Notifal Pro status.
     *
     * Prevents duplicate license checks when both summary and mint context
     * are built during the same admin request.
     *
     * @since 3.0.0
     * @var array{installed:bool,active:bool}|null
     */
    private static $proStatusCache = null;

    /**
     * Maximum JSON byte size for one context snapshot payload.
     *
     * @since 3.0.0
     * @var int
     */
    private const MAX_SNAPSHOT_BYTES = 4096;

    /**
     * Build a lightweight summary safe to send to support AI.
     *
     * @return array<string,mixed>
     * @since 3.0.0
     */
    public static function build_summary(): array
    {
        // Count published Notifal templates when the post type exists.
        $templateCount = self::count_posts('notifal_template');

        // Count on-page notifications when the post type exists.
        $notificationCount = self::count_posts('notifal_onpage_notif');

        // Resolve first-name token from the saved AI site profile when available.
        $contactName = self::resolve_contact_first_name();

        // Return only aggregated, non-sensitive fields.
        return array_merge(
            [
                'template_count'     => $templateCount,
                'notification_count' => $notificationCount,
                'woocommerce'        => class_exists('WooCommerce'),
                'contact_name'       => $contactName,
                'notifal_version'    => defined('NOTIFAL_VERSION') ? sanitize_text_field((string) NOTIFAL_VERSION) : '',
                'wp_locale'          => sanitize_text_field(get_locale()),
            ],
            self::build_capability_context()
        );
    }

    /**
     * Build the capability and account-status block shared by every snapshot.
     *
     * Centralizing these flags keeps the summary, analytics, notifications, and
     * templates payloads consistent, so the support AI always receives the same
     * Pro and connection state regardless of which context tier was consented.
     *
     * @return array<string,mixed>
     * @since 3.0.0
     */
    public static function build_capability_context(): array
    {
        // Resolve Pro install, activation, and license state once per request.
        $pro = self::resolve_pro_status();

        // Return sanitized capability flags safe for the support AI.
        return [
            // Legacy flag kept for backward compatibility with existing consumers.
            'notifal_pro'           => $pro['installed'],
            // Whether Pro files exist on disk, even when deactivated.
            'notifal_pro_installed' => $pro['installed'],
            // Whether Pro is active and fully booted for this site.
            'notifal_pro_active'    => $pro['active'],
            // Whether a valid Pro license currently authorizes Pro features.
            'notifal_pro_license'   => $pro['licensed'],
            // Whether the site is linked to a Notifal account for hosted AI.
            'ai_connected'          => self::resolve_connection_status(),
        ];
    }

    /**
     * Build analytics snapshot for consented support turns.
     *
     * @return array<string,mixed>
     * @since 3.0.0
     */
    public static function build_analytics_snapshot(): array
    {
        // Start from the lightweight summary block.
        $snapshot = self::build_summary();

        // Load top notifications with basic metrics when available.
        $snapshot['top_notifications'] = self::top_notifications(5);

        // Enforce payload size cap before returning.
        return self::cap_snapshot($snapshot);
    }

    /**
     * Build notification list snapshot for consented support turns.
     *
     * @return array<string,mixed>
     * @since 3.0.0
     */
    public static function build_notifications_snapshot(): array
    {
        // Include summary counts plus a bounded notification list.
        $snapshot = self::build_summary();
        $snapshot['notifications'] = self::top_notifications(10);

        // Enforce payload size cap before returning.
        return self::cap_snapshot($snapshot);
    }

    /**
     * Build template list snapshot for consented support turns.
     *
     * @return array<string,mixed>
     * @since 3.0.0
     */
    public static function build_templates_snapshot(): array
    {
        // Include summary counts plus a bounded template list.
        $snapshot = self::build_summary();
        $snapshot['templates'] = self::list_templates(10);

        // Enforce payload size cap before returning.
        return self::cap_snapshot($snapshot);
    }

    /**
     * Resolve mint-time site context forwarded to notifal.com.
     *
     * @return array<string,mixed>
     * @since 3.0.0
     */
    public static function build_mint_context(): array
    {
        // Resolve the current admin screen slug when available.
        $page = isset($_GET['page'])
            ? sanitize_key(wp_unslash((string) $_GET['page']))
            : '';

        // Resolve the signed-in administrator email for gateway attribution.
        $user = wp_get_current_user();
        $email = $user instanceof \WP_User ? sanitize_email((string) $user->user_email) : '';

        // Return bounded mint metadata only.
        return array_merge(
            [
                'contact_name'  => self::resolve_contact_first_name(),
                'wp_local_hour' => (int) current_time('G'),
                'admin_email'   => $email,
                'admin_page'    => $page,
                'woocommerce'   => class_exists('WooCommerce'),
            ],
            self::build_capability_context()
        );
    }

    /**
     * Resolve the Notifal Pro install, activation, and license state.
     *
     * The result is memoized per request because license checks read options
     * and load helper functions, and the context may be built several times
     * within a single admin request.
     *
     * @return array{installed:bool,active:bool,licensed:bool}
     * @since 3.0.0
     */
    private static function resolve_pro_status(): array
    {
        // Reuse the resolved status when the same request already computed it.
        if (null !== self::$proStatusCache) {
            return self::$proStatusCache;
        }

        // Detect Pro files first, since active and licensed states depend on it.
        $installed = PluginDetector::isNotifalProInstalled();

        // Only an installed plugin can be active or licensed.
        $active = $installed && PluginDetector::isNotifalProActive();

        // Resolve the license state through the shared Pro license checker when available.
        $licensed = $active && self::is_pro_license_active();

        // Memoize the resolved tuple for the remainder of the request.
        self::$proStatusCache = [
            'installed' => $installed,
            'active'    => $active,
            'licensed'  => $licensed,
        ];

        return self::$proStatusCache;
    }

    /**
     * Determine whether an active Notifal Pro license currently authorizes features.
     *
     * The centralized Pro helper is the source of truth; when it is unavailable
     * (for example Pro is deactivated) the license is treated as inactive.
     *
     * @return bool
     * @since 3.0.0
     */
    private static function is_pro_license_active(): bool
    {
        // Require the Pro-provided license checker before trusting any license state.
        if (!function_exists('is_notifal_pro_active')) {
            return false;
        }

        // Cast to bool so a stray truthy value cannot leak into the payload.
        return (bool) is_notifal_pro_active();
    }

    /**
     * Determine whether the site is linked to a Notifal account for hosted AI.
     *
     * Reads the local credential store, which only reports whether encrypted
     * refresh credentials exist. No secret material is ever returned.
     *
     * @return bool
     * @since 3.0.0
     */
    private static function resolve_connection_status(): bool
    {
        // Guard against early calls before the container is bootstrapped.
        if (!function_exists('notifal_app')) {
            return false;
        }

        // Resolve the credential store and report its connection state.
        return (bool) notifal_app(InstallationCredentialStore::class)->isConnected();
    }

    /**
     * Resolve the first-name token used in greetings.
     *
     * @return string
     * @since 3.0.0
     */
    private static function resolve_contact_first_name(): string
    {
        // Read the saved AI site profile when the service is registered.
        if (!function_exists('notifal_app')) {
            return '';
        }

        $profile = notifal_app(SiteProfileService::class)->get();
        $raw = sanitize_text_field((string) ($profile['contact_name'] ?? ''));
        if ('' === $raw) {
            return '';
        }

        // Use only the first token for natural greetings.
        $parts = preg_split('/\s+/', $raw);

        return sanitize_text_field((string) ($parts[0] ?? ''));
    }

    /**
     * Count published posts for one post type when registered.
     *
     * @param string $postType Post type slug.
     * @return int
     * @since 3.0.0
     */
    private static function count_posts(string $postType): int
    {
        // Skip unknown post types to avoid unnecessary queries.
        if (!post_type_exists($postType)) {
            return 0;
        }

        // Count published items only.
        $counts = wp_count_posts($postType);

        return isset($counts->publish) ? (int) $counts->publish : 0;
    }

    /**
     * Return top notifications with title and status only.
     *
     * @param int $limit Maximum rows.
     * @return array<int,array<string,mixed>>
     * @since 3.0.0
     */
    private static function top_notifications(int $limit): array
    {
        // Abort when the notification post type is unavailable.
        if (!post_type_exists('notifal_onpage_notif')) {
            return [];
        }

        // Load recent published notifications.
        $posts = get_posts([
            'post_type'      => 'notifal_onpage_notif',
            'post_status'    => 'publish',
            'posts_per_page' => max(1, min(10, $limit)),
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ]);

        // Map to lightweight rows without post content.
        $rows = [];
        foreach ($posts as $postId) {
            $rows[] = [
                'id'     => (int) $postId,
                'title'  => sanitize_text_field(get_the_title((int) $postId)),
                'status' => sanitize_key(get_post_status((int) $postId) ?: 'publish'),
            ];
        }

        return $rows;
    }

    /**
     * Return recent templates for consented context turns.
     *
     * @param int $limit Maximum rows.
     * @return array<int,array<string,mixed>>
     * @since 3.0.0
     */
    private static function list_templates(int $limit): array
    {
        // Abort when the template post type is unavailable.
        if (!post_type_exists('notifal_template')) {
            return [];
        }

        // Load recent published templates.
        $posts = get_posts([
            'post_type'      => 'notifal_template',
            'post_status'    => 'publish',
            'posts_per_page' => max(1, min(10, $limit)),
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ]);

        // Map to lightweight rows without HTML content.
        $rows = [];
        foreach ($posts as $postId) {
            $rows[] = [
                'id'    => (int) $postId,
                'title' => sanitize_text_field(get_the_title((int) $postId)),
            ];
        }

        return $rows;
    }

    /**
     * Trim one snapshot to the configured byte budget.
     *
     * @param array<string,mixed> $snapshot Snapshot payload.
     * @return array<string,mixed>
     * @since 3.0.0
     */
    private static function cap_snapshot(array $snapshot): array
    {
        // Encode once to measure payload size.
        $encoded = (string) wp_json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($encoded) <= self::MAX_SNAPSHOT_BYTES) {
            return $snapshot;
        }

        // Drop list payloads first when the snapshot is too large.
        unset($snapshot['notifications'], $snapshot['templates'], $snapshot['top_notifications']);

        return $snapshot;
    }
}
