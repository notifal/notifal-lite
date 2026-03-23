<?php

namespace Notifal\Modules\Campaign\Presentation\Admin\Assets;

use Notifal\Core\Support\Helpers\UrlHelper;
use Notifal\Infrastructure\WordPress\Admin\Localization\LangLoader;
use Notifal\Infrastructure\WordPress\Security\NonceManager;
use Notifal\Shared\Config\Paths;

defined('ABSPATH') || exit;

/**
 * Class CampaignAssets
 *
 * Enqueues admin assets for the Campaign list (status toggle) and edit screens,
 * and localizes translations plus AJAX configuration.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\Campaign\Presentation\Admin\Assets
 */
class CampaignAssets
{
    /**
     * Register WordPress hooks to enqueue assets.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register(): void
    {
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
    }

    /**
     * Enqueue scripts/styles for the campaign list and edit screens.
     *
     * @since 2.0.0
     * @return void
     */
    public static function enqueue(): void
    {
        $screen = get_current_screen();
        $validScreens = [
            'notifal_page_notifal-campaign',
            'notifal_page_notifal-campaigns',
            'toplevel_page_notifal-campaign',
            'toplevel_page_notifal-campaigns',
        ];

        $currentPage = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';

        $isValidScreenId = $screen && in_array( $screen->id, $validScreens, true );
        $isValidPageParam = in_array( $currentPage, [ 'notifal-campaign', 'notifal-campaigns' ], true );

        if ( ! $isValidScreenId && ! $isValidPageParam ) {
            return;
        }

        $isListScreen = ( $currentPage === 'notifal-campaigns' );
        $isEditScreen = ( $currentPage === 'notifal-campaign' );

        $jsUrl = Paths::jsAdminBuildUrl();
        $cssUrl = Paths::cssAdminBuildUrl();

        if ( $isEditScreen ) {
            notifal_enqueue_style(
                'notifal-campaign-edit-style',
                $cssUrl . 'CampaignAdminStyle.css',
                [ 'notifal-shared-admin-css' ]
            );

            $translations = LangLoader::load( __NAMESPACE__ );

            notifal_enqueue_script(
                'notifal-campaign-edit-script',
                $jsUrl . 'CampaignAdminScript.js',
                [ 'notifal-shared-admin-js' ],
                $translations,
                'NotifalCampaignStrings'
            );

            wp_localize_script(
                'notifal-campaign-edit-script',
                'NotifalCampaignAjax',
                self::getAjaxConfig()
            );
        }

        if ( $isListScreen ) {
            notifal_enqueue_style(
                'notifal-shared-admin-css',
                $cssUrl . 'SharedAdminStyle.css',
                []
            );

            $listTranslations = LangLoader::load( __NAMESPACE__ );

            notifal_enqueue_script(
                'notifal-campaign-list-admin',
                $jsUrl . 'CampaignListScript.js',
                [ 'notifal-shared-admin-js' ],
                $listTranslations,
                'NotifalCampaignStrings'
            );

            wp_localize_script(
                'notifal-campaign-list-admin',
                'NotifalCampaignToggleAjax',
                self::getToggleAjaxConfig()
            );
        }

    }

    /**
     * Get AJAX configuration data for Campaign edit JS.
     *
     * @since 2.0.0
     * @return array<string, mixed>
     */
    private static function getAjaxConfig(): array
    {
        $campaign_nonce = NonceManager::create( 'notifal_campaign_save' );
        $toggle_nonce   = NonceManager::create( 'notifal_toggle_campaign_status' );

        return [
            'nonce' => [
                'save_campaign' => $campaign_nonce,
                'get_campaign_data' => $campaign_nonce,
                'search_onpage_for_campaign' => $campaign_nonce,
                'toggle_campaign_status' => $toggle_nonce,
            ],
            'ajax_url' => UrlHelper::baseAjax(),
            'ajax_actions' => [
                'search_onpage_for_campaign' => 'notifal_search_onpage_notifications_for_campaign',
                'toggle_campaign_status' => 'notifal_toggle_campaign_status',
            ],
            'onpage_edit_url_base' => admin_url( 'admin.php?page=notifal-onpage-notification&action=edit&id=' ),
            'scheduleDisplayTimezone' => (string) get_option( 'timezone_string', '' ),
            'scheduleGmtOffsetHours' => (float) get_option( 'gmt_offset', 0.0 ),
        ];
    }

    /**
     * AJAX configuration for the campaign list status toggle script.
     *
     * @since 2.2.0
     * @return array<string, mixed>
     */
    private static function getToggleAjaxConfig(): array
    {
        return [
            'ajax_url' => UrlHelper::baseAjax(),
            'nonce' => [
                'toggle_campaign_status' => NonceManager::create( 'notifal_toggle_campaign_status' ),
            ],
            'ajax_actions' => [
                'toggle_campaign_status' => 'notifal_toggle_campaign_status',
            ],
        ];
    }
}

