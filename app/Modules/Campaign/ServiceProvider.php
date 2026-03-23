<?php

namespace Notifal\Modules\Campaign;

use Notifal\Core\Foundation\AbstractServiceProvider;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Campaign\Application\Services\CampaignScheduleCronService;
use Notifal\Modules\Campaign\Application\Services\CampaignService;
use Notifal\Modules\Campaign\Application\Services\CampaignSettingsService;
use Notifal\Modules\Campaign\Infrastructure\WordPress\Repositories\CampaignQuery as CampaignQueryRepository;
use Notifal\Modules\Campaign\Infrastructure\WordPress\Registration\PostTypeRegistrar;
use Notifal\Modules\Campaign\Presentation\Admin\Assets\CampaignAssets;
use Notifal\Modules\Campaign\Presentation\Admin\ListTable\ColumnsController;
use Notifal\Modules\Campaign\Presentation\Admin\Menu\MenuController;
use Notifal\Modules\Campaign\Presentation\Admin\Routes\AdminRouteController;
use Notifal\Modules\Campaign\Presentation\Admin\Controllers\Ajax\CampaignAjaxController;
use Notifal\Modules\Campaign\Presentation\Admin\Controllers\Ajax\CampaignToggleStatusController;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Campaign module service provider.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ServiceProvider extends AbstractServiceProvider
{
    /**
     * @var array<int, class-string>
     */
    protected static array $services = [
        PostTypeRegistrar::class,
        CampaignService::class,
        CampaignSettingsService::class,
        CampaignQueryRepository::class,
        AdminRouteController::class,
        MenuController::class,
        ColumnsController::class,
        CampaignAssets::class,
    ];

    /**
     * Allow external modification of registered Campaign services.
     *
     * @since 2.0.0
     */
    protected const FILTER_HOOK = FilterHooks::CAMPAIGN_SERVICES;

    /**
     * Boot the service provider.
     *
     * @since 2.0.0
     * @return void
     */
    public function boot(): void
    {
        CampaignScheduleCronService::register();

        // Register AJAX controller actions.
        CampaignAjaxController::register();
        CampaignToggleStatusController::register();

        // Enable notifications list filtering by campaign_id (used by Campaign columns links).
        add_filter( FilterHooks::ADMIN_LIST_QUERY_ARGS, [ self::class, 'filterNotificationsQueryByCampaign' ], 10, 3 );
        add_filter( FilterHooks::ADMIN_LIST_COUNT_QUERY_ARGS, [ self::class, 'filterNotificationsCountByCampaign' ], 10, 3 );
    }

    /**
     * Filter notifications list query by campaign_id when present in URL.
     *
     * @since 2.0.0
     * @param array $args Query arguments.
     * @param string $postType Post type key.
     * @param mixed $instance BaseListView instance.
     * @return array Filtered query arguments.
     */
    public static function filterNotificationsQueryByCampaign( array $args, string $postType, $instance ): array
    {
        if ( $postType !== 'notifal_onpage_notif' ) {
            return $args;
        }

        $campaignId = isset( $_GET['campaign_id'] ) ? absint( wp_unslash( (string) $_GET['campaign_id'] ) ) : 0;
        if ( $campaignId <= 0 ) {
            return $args;
        }

        $metaQuery = [];
        if ( isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ) {
            $metaQuery = $args['meta_query'];
        }

        $metaQuery[] = [
            'key'     => '_notifal_campaign_id',
            'value'   => $campaignId,
            'compare' => '=',
        ];

        $args['meta_query'] = $metaQuery;

        return $args;
    }

    /**
     * Filter notifications list count query by campaign_id when present.
     *
     * @since 2.0.0
     * @param mixed $args Either an int count (from `AdminStatsService`)
     *                     or an array query-args (from `BaseListView` count query).
     * @param string $postType Post type key.
     * @param mixed $instance BaseListView instance.
     * @return mixed Filtered value (keeps the input type: int or array).
     */
    public static function filterNotificationsCountByCampaign( $args, string $postType, $instance = null )
    {
        if ( $postType !== 'notifal_onpage_notif' ) {
            return $args;
        }

        $campaignId = isset( $_GET['campaign_id'] ) ? absint( wp_unslash( (string) $_GET['campaign_id'] ) ) : 0;
        if ( $campaignId <= 0 ) {
            return $args;
        }

        // When $args is array, this is the BaseListView count query-args hook.
        if ( is_array( $args ) ) {
            $metaQuery = [];
            if ( isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ) {
                $metaQuery = $args['meta_query'];
            }

            $metaQuery[] = [
                'key'     => '_notifal_campaign_id',
                'value'   => $campaignId,
                'compare' => '=',
            ];

            $args['meta_query'] = $metaQuery;
            return $args;
        }

        // When $args is int, this is the AdminStatsService total count hook.
        // We compute an accurate total count based on campaign meta_query.
        $count = (int) $args;
        $query = new \WP_Query([
            'post_type'      => $postType,
            'post_status'    => [ 'publish', 'draft', 'trash' ],
            'posts_per_page' => -1,
            'fields'         => 'ids', // Performance: only IDs needed for counting.
            'meta_query'     => [
                [
                    'key'     => '_notifal_campaign_id',
                    'value'   => $campaignId,
                    'compare' => '=',
                ],
            ],
        ]);

        return (int) $query->found_posts;
    }
}

