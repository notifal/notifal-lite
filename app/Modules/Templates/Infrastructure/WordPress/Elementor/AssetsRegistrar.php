<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\Elementor;

use Notifal\Modules\Templates\Config\Paths as TemplatePath;
use Notifal\Shared\Config\Paths;
use Notifal\Domain\Tags\Infrastructure\WordPress\Registration\DynamicKeysApiRegistrar;
use Notifal\Infrastructure\WordPress\Admin\Localization\LangLoader;

defined('ABSPATH') || exit;

/**
 * Class AssetsRegistrar
 *
 * Handles registering and enqueuing Elementor widget styles and scripts.
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\Elementor
 * @author Hossein <hossein@notifal.com>
 */
class AssetsRegistrar
{
    /**
     * Register hooks to manage Elementor widget assets.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function register(): void
    {
        add_action('elementor/frontend/after_register_styles', [self::class, 'register_styles']);
        add_action('elementor/editor/after_enqueue_scripts', [self::class, 'register_editor_styles'] );
    }

    /**
     * Register all shared widget styles.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function register_styles(): void
    {
        notifal_enqueue_style(
            'notifal-elementor-widgets-style',
            Paths::cssAdminBuildUrl() . 'TemplatesAdminStyle.css'
        );
    }

    /**
     * Register all editor panel scripts.
     *
     * Only loads tags-specific assets for Elementor panel to keep it lightweight.
     * Does NOT load the full shared bundle which is only for WordPress admin area.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function register_editor_styles(): void
    {
        // Ensure dynamic keys API route is always registered
        DynamicKeysApiRegistrar::register();

        // Load WordPress REST API script to get nonce
        wp_enqueue_script('wp-api');

        // Load tags JavaScript (includes dynamic keys functionality)
        notifal_enqueue_script(
            'notifal-tags-js',
            Paths::jsAdminBuildUrl() . 'TagsAdminScript.js',
            ['jquery', 'elementor-editor', 'wp-api']
        );

        // Localize tags JS strings
        $tagsStrings = LangLoader::load(__NAMESPACE__);
        wp_localize_script('notifal-tags-js', 'notifalTagsStrings', $tagsStrings);

        // Load only tags-specific CSS (includes popup styling)
        notifal_enqueue_style(
            'notifal-tags-css',
            Paths::cssAdminBuildUrl() . 'TagsAdminStyle.css'
        );

        // Load tooltip CSS for tag tooltips
        notifal_enqueue_style(
            'notifal-tooltip-css',
            Paths::cssAdminBuildUrl() . 'TooltipAdminStyle.css'
        );

    }
}
