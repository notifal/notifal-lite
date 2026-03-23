<?php

namespace Notifal\Modules\Campaign\Presentation\Admin\Menu;

use Notifal\Shared\AdminUI\Lists\BaseListView;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MenuController
 *
 * Registers Campaign admin submenus under Notifal main menu and wires bulk actions.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\Campaign\Presentation\Admin\Menu
 */
class MenuController
{
    /**
     * Register menu hooks.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register(): void
    {
        add_action( 'admin_menu', [ self::class, 'addMenu' ], 23 );
        add_filter( 'submenu_file', [ self::class, 'hideEditPageFromMenu' ] );
    }

    /**
     * Add submenu pages for campaigns.
     *
     * @since 2.0.0
     * @return void
     */
    public static function addMenu(): void
    {
        $hook = add_submenu_page(
            'notifal',
            __( 'Campaigns', 'notifal' ),
            __( 'Campaigns', 'notifal' ),
            'manage_options',
            'notifal-campaigns',
            [ self::class, 'renderList' ],
        );

        // Bulk actions must run before output.
        add_action( "load-{$hook}", [ self::class, 'handleBulkActions' ] );

        // Hidden edit page (accessible via URL).
        add_submenu_page(
            'notifal',
            __( 'Campaign', 'notifal' ),
            __( 'Campaign', 'notifal' ),
            'manage_options',
            'notifal-campaign',
            [ self::class, 'renderEdit' ]
        );
    }

    /**
     * Render Campaign list screen.
     *
     * @since 2.0.0
     * @return void
     */
    public static function renderList(): void
    {
        notifal_view( 'Campaign.Admin.campaign-list' );
    }

    /**
     * Render Campaign edit screen.
     *
     * @since 2.0.0
     * @return void
     */
    public static function renderEdit(): void
    {
        notifal_view( 'Campaign.Admin.Edit.index' );
    }

    /**
     * Handle bulk actions on the campaigns list screen.
     *
     * @since 2.0.0
     * @return void
     */
    public static function handleBulkActions(): void
    {
        BaseListView::handleBulkActionsForPostType( 'notifal_campaign' );
    }

    /**
     * Hide the Campaign edit page from the submenu list.
     *
     * @since 2.0.0
     * @param string|null $submenu_file The current submenu file.
     * @return string|null
     */
    public static function hideEditPageFromMenu( $submenu_file )
    {
        global $submenu;

        if ( isset( $submenu['notifal'] ) ) {
            foreach ( $submenu['notifal'] as $key => $item ) {
                // $item[2] is the submenu slug (same approach as other modules).
                if ( isset( $item[2] ) && $item[2] === 'notifal-campaign' ) {
                    unset( $submenu['notifal'][ $key ] );
                    break;
                }
            }
        }

        return $submenu_file;
    }
}

