<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor;

use WP_Block_Editor_Context;
use WP_Block_Type_Registry;
use Notifal\Core\Support\Helpers\UrlHelper;
use Notifal\Shared\Config\Paths;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Renderers\FeaturedImageRenderer;
use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Renderers\ActionButtonRenderer;
use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Renderers\CloseIconRenderer;
use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Renderers\IconRenderer;
use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\AdvancedBlockStyleInjector;

defined('ABSPATH') || exit;

/**
 * Class RegisterBlocks
 *
 * Handles registration and visibility control of custom Notifal Gutenberg blocks.
 * Provides block category management and server-side rendering integration.
 * Follows Notifal Laravel-like architecture with proper separation of concerns.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class RegisterBlocks {
    /**
     * Filesystem path to Gutenberg Blocks directory.
     *
     * @var string
     * @since 2.0.0
     */
    private const BLOCKS_DIR = __DIR__ . '/Blocks/';

    /**
     * Cache for Notifal block names to avoid repeated registry queries.
     *
     * @var string[]
     * @since 2.0.0
     */
    private static array $notifal_blocks = [];

    /**
     * Register all necessary WordPress hooks for Gutenberg integration.
     *
     * Uses Notifal hook constants for consistency and maintainability.
     * High priority ensures our filters run after other plugins.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function register(): void {
        // Register block category for notifal_template post type only
        add_filter(
            FilterHooks::WP_BLOCK_CATEGORIES_ALL,
            [self::class, 'addCategory'],
            5,
            2
        );

        // Register all Notifal blocks from build directory
        add_action(
            'init',
            [self::class, 'registerAllBlocks'],
            20
        );

        // Control block visibility per post type - high priority ensures proper filtering
        add_filter(
            FilterHooks::WP_ALLOWED_BLOCK_TYPES_ALL,
            [self::class, 'filterAllowedBlocks'],
            PHP_INT_MAX,
            2
        );
        
        // Enqueue editor scripts for dynamic WooCommerce-aware features
        add_action('enqueue_block_editor_assets', [self::class, 'enqueueEditorScripts']);

        // Register advanced block style injector for frontend
        AdvancedBlockStyleInjector::register();
    }

    /**
     * Add 'notifal' block category only for notifal_template post type.
     *
     * Ensures Notifal blocks appear in a dedicated category only where needed.
     * Prevents category pollution in other post types.
     *
     * @param array $categories List of existing block categories.
     * @param WP_Block_Editor_Context $context Editor context containing post data.
     * @return array Modified categories with Notifal category if applicable.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function addCategory(
        array $categories,
        WP_Block_Editor_Context $context
    ): array {
        // Validate context has post data
        $post = $context->post;
        if (!$post) {
            return $categories;
        }
        
        // Only add category for notifal_template post type
        if ($post->post_type !== 'notifal_template') {
            return $categories;
        }

        // Check if category already exists to avoid duplicates
        foreach ($categories as $category) {
            if (isset($category['slug']) && $category['slug'] === 'notifal') {
                return $categories;
            }
        }

        // Create Notifal category with proper localization
        $notifal_category = [
            'slug'  => 'notifal',
            'title' => __('Notifal', 'notifal'),
            'icon'  => null,
        ];

        // Add at beginning for better UX (first position)
        return array_merge([$notifal_category], $categories);
    }

    /**
     * Register all Notifal blocks located in the Blocks build directory.
     *
     * Scans for block.json files and registers blocks with WordPress.
     * Adds server-side rendering for blocks that require it.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function registerAllBlocks(): void {
        // Get blocks from build directory (compiled by wp-scripts)
        $blocks_path = self::BLOCKS_DIR . 'build/';
        $block_dirs = glob($blocks_path . '*', GLOB_ONLYDIR);

        // Validate directory exists and contains blocks
        if (empty($block_dirs) || !is_array($block_dirs)) {
            return;
        }

        // Register each block found in build directory
        foreach ($block_dirs as $block_dir) {
            $metadata_file = $block_dir . '/block.json';
            
            // Ensure block.json exists before attempting registration
            if (!file_exists($metadata_file)) {
                continue;
            }

            // Register block using WordPress metadata registration
            $block = register_block_type_from_metadata($metadata_file);
            
            // Cache block name and add custom rendering if needed
            if ($block) {
                // Cache for later filtering operations
                self::$notifal_blocks[] = $block->name;
                
                // Add server-side rendering for dynamic blocks
                if ($block->name === 'notifal/featured-image') {
                    // Override render callback for dynamic content
                    $block->render_callback = [self::class, 'renderFeaturedImageBlock'];
                } elseif ($block->name === 'notifal/action-button') {
                    // Override render callback for dynamic content
                    $block->render_callback = [self::class, 'renderActionButtonBlock'];
                } elseif ($block->name === 'notifal/close-icon') {
                    // Override render callback for dynamic content
                    $block->render_callback = [self::class, 'renderCloseIconBlock'];
                } elseif ($block->name === 'notifal/icon') {
                    // Override render callback for dynamic content
                    $block->render_callback = [self::class, 'renderIconBlock'];
                }
            }
        }
    }

    /**
     * Filter allowed Gutenberg blocks per post type.
     *
     * Controls block visibility to maintain clean editing experience:
     * - notifal_template: Allow all blocks including Notifal blocks
     * - Other post types: Exclude Notifal blocks to prevent confusion
     *
     * @param bool|array $allowed_blocks Current allowed blocks configuration.
     * @param WP_Block_Editor_Context $context Editor context with post data.
     * @return bool|array Filtered blocks configuration.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function filterAllowedBlocks(
        $allowed_blocks,
        WP_Block_Editor_Context $context
    ) {
        // Validate context contains post data
        $post = $context->post;
        if (!$post) {
            return $allowed_blocks;
        }

        // Get cached or fresh list of Notifal blocks
        $notifal_blocks = self::getNotifalBlockNames();
        if (empty($notifal_blocks)) {
            return $allowed_blocks;
        }

        // For notifal_template post type, allow all blocks (including Notifal blocks)
        if ($post->post_type === 'notifal_template') {
            return $allowed_blocks;
        }

        // For other post types, exclude Notifal blocks
        if (!is_array($allowed_blocks)) {
            // If $allowed_blocks is true (all blocks allowed), convert to array of all blocks
            if ($allowed_blocks === true) {
                $all_registered_blocks = array_keys(WP_Block_Type_Registry::get_instance()->get_all_registered());
                $allowed_blocks = $all_registered_blocks;
            } else {
                // If it's false or some other non-array value, return as-is
                return $allowed_blocks;
            }
        }

        // Remove Notifal blocks from allowed list
        $filtered_blocks = array_diff($allowed_blocks, $notifal_blocks);

        // Re-index array to maintain proper structure
        return array_values($filtered_blocks);
    }

    /**
     * Get all registered Notifal block names with caching.
     *
     * Efficiently retrieves Notifal blocks using cached data or registry scan.
     * Caches results to avoid repeated expensive registry operations.
     *
     * @return string[] List of Notifal block names (e.g., ['notifal/template-container']).
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function getNotifalBlockNames(): array {
        // Return cached blocks if available
        if (!empty(self::$notifal_blocks)) {
            return self::$notifal_blocks;
        }

        // Scan registry for Notifal blocks
        $all_blocks = WP_Block_Type_Registry::get_instance()->get_all_registered();
        $notifal_blocks = [];

        // Filter blocks by notifal/ namespace
        foreach ($all_blocks as $block_name => $block_type) {
            if (strpos($block_name, 'notifal/') === 0) {
                $notifal_blocks[] = $block_name;
            }
        }

        // Cache results for future calls
        self::$notifal_blocks = $notifal_blocks;

        return $notifal_blocks;
    }

    /**
     * Server-side rendering callback for Featured Image block.
     *
     * Renders dynamic featured image with attributes from Gutenberg editor.
     * Delegates to FeaturedImageRenderer for separation of concerns.
     *
     * @param array $attributes Block attributes from Gutenberg editor.
     * @param string $content Block content (usually empty for dynamic blocks).
     * @param mixed $block Block instance.
     * @return string Rendered HTML output.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function renderFeaturedImageBlock(array $attributes = [], string $content = '', $block = null): string {
        // Delegate to dedicated renderer for maintainability
        return FeaturedImageRenderer::render($attributes, $content, $block);
    }

    /**
     * Server-side rendering callback for Action Button block.
     *
     * Renders dynamic action button with attributes from Gutenberg editor.
     * Delegates to ActionButtonRenderer for separation of concerns.
     *
     * @param array $attributes Block attributes from Gutenberg editor.
     * @param string $content Block content (usually empty for dynamic blocks).
     * @param mixed $block Block instance.
     * @return string Rendered HTML output.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function renderActionButtonBlock(array $attributes = [], string $content = '', $block = null): string {
        // Delegate to dedicated renderer for maintainability
        return ActionButtonRenderer::render($attributes, $content, $block);
    }

    /**
     * Server-side rendering callback for Close Icon block.
     *
     * Renders dynamic close icon with attributes from Gutenberg editor.
     * Delegates to CloseIconRenderer for separation of concerns.
     *
     * @param array $attributes Block attributes from Gutenberg editor.
     * @param string $content Block content (usually empty for dynamic blocks).
     * @param mixed $block Block instance.
     * @return string Rendered HTML output.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function renderCloseIconBlock(array $attributes = [], string $content = '', $block = null): string {
        // Delegate to dedicated renderer for maintainability
        return CloseIconRenderer::render($attributes, $content, $block);
    }

    /**
     * Server-side rendering callback for Icon block.
     *
     * Renders dynamic icon with attributes from Gutenberg editor.
     * Delegates to IconRenderer for separation of concerns.
     *
     * @param array $attributes Block attributes from Gutenberg editor.
     * @param string $content Block content (usually empty for dynamic blocks).
     * @param mixed $block Block instance.
     * @return string Rendered HTML output.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function renderIconBlock(array $attributes = [], string $content = '', $block = null): string {
        // Delegate to dedicated renderer for maintainability
        return IconRenderer::render($attributes, $content, $block);
    }

    /**
     * Enqueue block editor scripts for WooCommerce-aware functionality.
     *
     * Adds JavaScript to dynamically modify block controls based on WooCommerce availability.
     * This ensures the editor interface adapts without requiring JavaScript compilation.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function enqueueEditorScripts(): void {
        // Only enqueue for post types that can contain Notifal blocks
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'notifal_template') {
            return;
        }

        // Enqueue WooCommerce-aware blocks script with localization
        notifal_enqueue_script(
            'notifal-woocommerce-aware-blocks',
            plugins_url('Assets/js/woocommerce-aware-blocks.js', __FILE__),
            ['wp-blocks', 'wp-element', 'wp-compose', 'wp-components', 'wp-block-editor'],
            [
                'wooCommerceActive' => PluginDetector::isWooCommerceActive(),
                'ajaxUrl' => UrlHelper::baseAjax(),
                'nonce' => wp_create_nonce('notifal_editor_nonce')
            ],
            'notifalSettings'
        );

        // Enqueue advanced block settings script for all blocks
        notifal_enqueue_script(
            'notifal-block-advanced-settings',
            Paths::jsAdminBuildUrl() . 'BlockAdvancedSettingsScript.js',
            [
                'wp-blocks',
                'wp-element',
                'wp-compose',
                'wp-components',
                'wp-block-editor',
                'wp-data',
                'wp-hooks',
                'wp-i18n'
            ],
            [
                'isNotifalProActive' => PluginDetector::isNotifalProActive()
            ],
            'notifalAdvancedSettings'
        );
    }
}
