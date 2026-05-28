<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\Registration;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Templates\Infrastructure\WordPress\Registration\TaxonomyRegistrar;


defined('ABSPATH') || exit;

/**
 * Class PostTypeRegistrar
 *
 * Registers the custom post type for Notifal Templates and associated components.
 * This includes the notifal_template post type, taxonomy registration, and editor configuration.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\Registration
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class PostTypeRegistrar {

    /**
     * Register the notifal_template post type and associated components.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register(): void {
        add_action( 'init', [ self::class, 'registerTemplatePostType' ] );
        add_action( 'init', [ TaxonomyRegistrar::class, 'register' ] );
        add_filter( 'wp_sitemaps_post_types', [ self::class, 'excludeFromCoreSitemaps' ] );
        add_filter( 'rank_math/sitemap/exclude_post_type', [ self::class, 'excludeFromRankMathSitemaps' ], 10, 2 );
        add_filter( 'rank_math/sitemap/html_sitemap_post_types', [ self::class, 'excludeFromRankMathHtmlSitemaps' ] );
        add_filter( 'wpseo_sitemap_exclude_post_type', [ self::class, 'excludeFromYoastSitemaps' ], 10, 2 );
        add_filter( 'wp_robots', [ self::class, 'setCoreNoindexForTemplate' ] );
        add_filter( 'rank_math/frontend/robots', [ self::class, 'setRankMathNoindexForTemplate' ] );
        add_filter( 'wpseo_robots', [ self::class, 'setYoastNoindexForTemplate' ] );
        add_action( 'send_headers', [ self::class, 'sendNoindexHeaderForTemplate' ] );

        // Enforce Gutenberg editor for notifal_template with highest priority to override any other plugins
        add_filter( 'use_block_editor_for_post_type', [ self::class, 'forceBlockEditor' ], PHP_INT_MAX, 2 );
        
        // Prevent Classic Editor plugin from interfering with notifal_template post type
        add_filter( 'classic_editor_enabled_editors_for_post_type', [ self::class, 'disableClassicEditorForTemplate' ], PHP_INT_MAX, 2 );
        
        // Modify edit post link to include classic-editor__forget parameter
        add_filter( 'get_edit_post_link', [ self::class, 'forceBlockEditorInEditLink' ], 10, 2 );
        
        // Force block editor for new post screen
        add_action( 'load-post-new.php', [ self::class, 'forceBlockEditorOnNewPost' ] );
        
        // Force block editor for edit post screen
        add_action( 'load-post.php', [ self::class, 'forceBlockEditorOnEditPost' ] );
        
        // Modify submenu "Add New" link to include classic-editor__forget parameter
        add_action( 'admin_menu', [ self::class, 'modifyAddNewSubmenuLink' ], 999 );
    }

    /**
     * Register the notifal_template custom post type.
     *
     * Creates a custom post type specifically for Notifal notification templates
     * with appropriate labels, capabilities, and editor support.
     *
     * @since 2.0.0
     * @return void
     */
    public static function registerTemplatePostType(): void {

        $labels = [
            'name'               => __( 'Notifal Templates', 'notifal' ),
            'singular_name'      => __( 'Template', 'notifal' ),
            'menu_name'          => __( 'Templates', 'notifal' ),
            'name_admin_bar'     => __( 'Template', 'notifal' ),
            'add_new'            => __( 'Add New', 'notifal' ),
            'add_new_item'       => __( 'Add New Template', 'notifal' ),
            'edit_item'          => __( 'Edit Template', 'notifal' ),
            'new_item'           => __( 'New Template', 'notifal' ),
            'view_item'          => __( 'View Template', 'notifal' ),
            'search_items'       => __( 'Search Templates', 'notifal' ),
            'not_found'          => __( 'No templates found.', 'notifal' ),
            'not_found_in_trash' => __( 'No templates found in Trash.', 'notifal' ),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => true,
            'exclude_from_search'=> true,
            'show_ui'            => true,
            'show_in_menu'       => 'notifal',
            'query_var'          => true,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 3,
            'show_in_nav_menus'  => false,
            'supports'           => [ 'title', 'editor', 'custom-fields','elementor' ],
            'show_in_rest'       => true,
            'template'           => [
                ['notifal/template-container', []]
            ],
            'template_lock'      => 'insert', // Lock template to prevent accidental changes
        ];

        $args = apply_filters( FilterHooks::TEMPLATE_TYPE_ARGS, $args );

        register_post_type( 'notifal_template', $args );
        add_post_type_support('notifal_template', 'elementor');
    }

    /**
     * Exclude internal template post type from WordPress core XML sitemaps.
     *
     * @since 2.3.0
     * @param array $post_types Registered post types included in core sitemap output.
     * @return array
     */
    public static function excludeFromCoreSitemaps( array $post_types ): array {
        // Remove internal template post type from core sitemap providers.
        unset( $post_types['notifal_template'] );

        return $post_types;
    }

    /**
     * Exclude internal template post type from Rank Math sitemaps.
     *
     * @since 2.2.5
     * @param bool   $exclude   Current exclusion state.
     * @param string $post_type Post type key currently evaluated by Rank Math.
     * @return bool
     */
    public static function excludeFromRankMathSitemaps( $exclude, $post_type ) {
        // Force exclusion for internal template post type only.
        if ( $post_type === 'notifal_template' ) {
            return true;
        }

        return (bool) $exclude;
    }

    /**
     * Exclude internal template post type from Rank Math HTML sitemap output.
     *
     * @since 2.2.5
     * @param array $post_types Post types included in Rank Math HTML sitemap.
     * @return array
     */
    public static function excludeFromRankMathHtmlSitemaps( array $post_types ): array {
        // Remove internal template post type from HTML sitemap providers.
        $filtered = array_filter(
            $post_types,
            static function ( $post_type ) {
                return $post_type !== 'notifal_template';
            }
        );

        return array_values( $filtered );
    }

    /**
     * Exclude internal template post type from Yoast SEO sitemaps.
     *
     * @since 2.2.5
     * @param bool   $exclude   Current exclusion state.
     * @param string $post_type Post type key currently evaluated by Yoast SEO.
     * @return bool
     */
    public static function excludeFromYoastSitemaps( $exclude, $post_type ) {
        // Force exclusion for internal template post type only.
        if ( $post_type === 'notifal_template' ) {
            return true;
        }

        return (bool) $exclude;
    }

    /**
     * Enforce noindex robots directives for template singular pages using core robots API.
     *
     * @since 2.2.5
     * @param array $robots Core robots directives.
     * @return array
     */
    public static function setCoreNoindexForTemplate( array $robots ): array {
        // Apply noindex directives only on template singular requests.
        if ( ! self::isTemplateSingularRequest() ) {
            return $robots;
        }

        // Remove conflicting directive and force noindex directives.
        unset( $robots['index'] );
        $robots['noindex']   = true;
        $robots['nofollow']  = false;
        $robots['noarchive'] = true;

        return $robots;
    }

    /**
     * Enforce noindex robots directives for template singular pages in Rank Math output.
     *
     * @since 2.2.5
     * @param array $robots Rank Math robots directives.
     * @return array
     */
    public static function setRankMathNoindexForTemplate( array $robots ): array {
        // Apply noindex directives only on template singular requests.
        if ( ! self::isTemplateSingularRequest() ) {
            return $robots;
        }

        // Remove conflicting directive and force noindex directives.
        unset( $robots['index'] );
        $robots['noindex']   = 'noindex';
        $robots['follow']    = 'follow';
        $robots['noarchive'] = 'noarchive';

        return $robots;
    }

    /**
     * Enforce noindex robots directives for template singular pages in Yoast output.
     *
     * @since 2.2.5
     * @param string $robots Yoast robots string.
     * @return string
     */
    public static function setYoastNoindexForTemplate( $robots ): string {
        // Apply noindex directives only on template singular requests.
        if ( ! self::isTemplateSingularRequest() ) {
            return (string) $robots;
        }

        return 'noindex,follow,noarchive';
    }

    /**
     * Send a robots response header as an extra noindex safety layer.
     *
     * @since 2.2.5
     * @return void
     */
    public static function sendNoindexHeaderForTemplate(): void {
        // Apply noindex header only on template singular requests.
        if ( ! self::isTemplateSingularRequest() ) {
            return;
        }

        header( 'X-Robots-Tag: noindex, follow, noarchive', true );
    }

    /**
     * Check whether the current frontend request is a template singular page.
     *
     * @since 2.2.5
     * @return bool
     */
    private static function isTemplateSingularRequest(): bool {
        // Skip if query functions are unavailable in current execution context.
        if ( ! function_exists( 'is_singular' ) ) {
            return false;
        }

        return is_singular( 'notifal_template' );
    }

    /**
     * Force block editor for notifal_template post type.
     *
     * This filter ensures that the block editor is always used for notifal_template,
     * overriding any other plugin settings including Classic Editor plugin.
     *
     * @since 2.0.0
     * @param bool   $use_block_editor Whether to use the block editor.
     * @param string $post_type        The post type being checked.
     * @return bool True if post type is notifal_template, otherwise original value.
     */
    public static function forceBlockEditor( $use_block_editor, $post_type ) {
        // Force block editor for notifal_template post type
        if ( $post_type === 'notifal_template' ) {
            return true;
        }
        return $use_block_editor;
    }

    /**
     * Disable Classic Editor for notifal_template post type.
     *
     * This filter prevents the Classic Editor plugin from showing any editor options
     * for the notifal_template post type, forcing block editor only.
     *
     * @since 2.0.0
     * @param array  $editors   Array of enabled editors for the post type.
     * @param string $post_type The post type being checked.
     * @return array Modified array of editors (empty for notifal_template to force block editor).
     */
    public static function disableClassicEditorForTemplate( $editors, $post_type ) {
        // Return block editor only for notifal_template
        if ( $post_type === 'notifal_template' ) {
            return [ 'block_editor' => true ];
        }
        return $editors;
    }

    /**
     * Force block editor in edit post links for notifal_template.
     *
     * Adds the classic-editor__forget parameter to edit post links
     * to ensure the block editor is used instead of classic editor.
     *
     * @since 2.0.0
     * @param string $url     The edit post URL.
     * @param int    $post_id The post ID.
     * @return string Modified URL with classic-editor__forget parameter if applicable.
     */
    public static function forceBlockEditorInEditLink( $url, $post_id ) {
        // Get post object to check post type
        $post = get_post( $post_id );
        
        // Only modify URL for notifal_template post type
        if ( $post && $post->post_type === 'notifal_template' ) {
            // Add classic-editor__forget parameter to force block editor
            $url = add_query_arg( 'classic-editor__forget', '', $url );
        }
        
        return $url;
    }

    /**
     * Force block editor on new post screen for notifal_template.
     *
     * This action redirects to the block editor if classic editor is being loaded
     * for a new notifal_template post.
     *
     * @since 2.0.0
     * @return void
     */
    public static function forceBlockEditorOnNewPost() {
        // Check if we're creating a notifal_template (sanitized per WordPress guidelines)
        $get_post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
        if ( $get_post_type === 'notifal_template' ) {
            // If classic-editor parameter is present, redirect to block editor
            if ( isset( $_GET['classic-editor'] ) ) {
                // Build URL without classic-editor parameter
                $redirect_url = admin_url( 'post-new.php' );
                $redirect_url = add_query_arg( 'post_type', 'notifal_template', $redirect_url );
                $redirect_url = add_query_arg( 'classic-editor__forget', '', $redirect_url );
                
                // Redirect to block editor
                wp_safe_redirect( $redirect_url );
                exit;
            }
        }
    }

    /**
     * Force block editor on edit post screen for notifal_template.
     *
     * This action redirects to the block editor if classic editor is being loaded
     * for an existing notifal_template post.
     *
     * @since 2.0.0
     * @return void
     */
    public static function forceBlockEditorOnEditPost() {
        // Get the post being edited (sanitized per WordPress guidelines)
        $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
        
        if ( ! $post_id ) {
            return;
        }
        
        $post = get_post( $post_id );
        
        // Only handle notifal_template post type
        if ( ! $post || $post->post_type !== 'notifal_template' ) {
            return;
        }
        
        // If classic-editor parameter is present, redirect to block editor
        if ( isset( $_GET['classic-editor'] ) ) {
            // Build URL without classic-editor parameter
            $redirect_url = admin_url( 'post.php' );
            $redirect_url = add_query_arg( 'post', $post_id, $redirect_url );
            $redirect_url = add_query_arg( 'action', 'edit', $redirect_url );
            $redirect_url = add_query_arg( 'classic-editor__forget', '', $redirect_url );
            
            // Redirect to block editor
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }

    /**
     * Modify the "Add New" submenu link to include classic-editor__forget parameter.
     *
     * This ensures that clicking "Add New" from the admin menu always opens
     * the block editor for notifal_template post type.
     *
     * @since 2.0.0
     * @return void
     */
    public static function modifyAddNewSubmenuLink() {
        global $submenu;
        
        // Check if notifal submenu exists
        if ( ! isset( $submenu['notifal'] ) ) {
            return;
        }
        
        // Loop through submenu items to find the "Add New" link for templates
        foreach ( $submenu['notifal'] as $key => $item ) {
            // Check if this is the add new template link
            // The URL will be in format: post-new.php?post_type=notifal_template
            if ( isset( $item[2] ) && strpos( $item[2], 'post-new.php?post_type=notifal_template' ) !== false ) {
                // Add classic-editor__forget parameter to force block editor
                $submenu['notifal'][ $key ][2] = add_query_arg( 
                    'classic-editor__forget', 
                    '', 
                    $item[2] 
                );
            }
        }
    }
}
