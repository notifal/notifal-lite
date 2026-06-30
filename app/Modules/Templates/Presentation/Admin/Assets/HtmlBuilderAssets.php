<?php

namespace Notifal\Modules\Templates\Presentation\Admin\Assets;

use Notifal\Core\Support\Helpers\UrlHelper;
use Notifal\Infrastructure\WordPress\Admin\Localization\LangLoader;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Security\NonceManager;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\Templates\Application\Services\HtmlBuilderAiPromptExamples;
use Notifal\Modules\Templates\Application\Services\HtmlBuilderDisplayLayouts;
use Notifal\Modules\Templates\Application\Services\HtmlBuilderUseCases;
use Notifal\Modules\Templates\Application\Services\TemplateUrlService;
use Notifal\Modules\Templates\Infrastructure\Shared\Traits\TagsPanelTrait;
use Notifal\Modules\Templates\Presentation\Admin\Controllers\HtmlBuilderPageController;
use Notifal\Shared\Config\Paths;

defined('ABSPATH') || exit;

/**
 * Enqueues HTML Builder admin assets on the builder screen only.
 *
 * @since 2.4.0
 * @author Hossein <hossein@notifal.com>
 */
class HtmlBuilderAssets
{
    use TagsPanelTrait;

    /**
     * Register WordPress hooks.
     *
     * @return void
     * @since 2.4.0
     */
    public static function register(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue'], 20);
    }

    /**
     * Enqueue styles and scripts for the HTML Builder page.
     *
     * @return void
     * @since 2.4.0
     */
    public static function enqueue(): void
    {
        if (!HtmlBuilderPageController::isCurrentScreen()) {
            return;
        }

        do_action(ActionHooks::TEMPLATE_HTML_BUILDER_ASSETS_BEFORE);

        self::ensureBaseAssets();
        wp_enqueue_media();

        $cssUrl = Paths::cssAdminBuildUrl();
        $jsUrl = Paths::jsAdminBuildUrl();

        notifal_enqueue_style(
            'notifal-loader-css',
            $cssUrl . 'LoaderAdminStyle.css',
            ['notifal-shared-admin-css']
        );

        notifal_enqueue_style(
            'notifal-html-builder-style',
            $cssUrl . 'HtmlBuilderStyle.css',
            ['notifal-shared-admin-css', 'notifal-icons', 'notifal-global-backend', 'notifal-loader-css']
        );

        notifal_enqueue_style(
            'notifal-html-builder-app-style',
            $cssUrl . 'HtmlBuilderScript.css',
            ['notifal-html-builder-style']
        );

        notifal_enqueue_style(
            'notifal-tags-css',
            $cssUrl . 'TagsAdminStyle.css',
            ['notifal-shared-admin-css']
        );

        $tagsStrings = LangLoader::load('Notifal\\Modules\\Templates');
        wp_localize_script('notifal-tags-js', 'notifalTagsStrings', $tagsStrings);

        $strings = LangLoader::load('Notifal\\Modules\\Templates', 'html-builder.php');
        $strings = self::appendRuntimeConfig($strings);

        notifal_enqueue_script(
            'notifal-html-builder-boot',
            $jsUrl . 'HtmlBuilderBootScript.js',
            [],
            $strings,
            'NotifalHtmlBuilderStrings'
        );

        notifal_enqueue_script(
            'notifal-html-builder-script',
            $jsUrl . 'HtmlBuilderScript.js',
            ['notifal-shared-admin-js', 'notifal-tags-js', 'notifal-html-builder-boot', 'media-upload', 'media-views'],
            [],
            null
        );

        do_action(ActionHooks::TEMPLATE_HTML_BUILDER_ASSETS_AFTER);
    }

    /**
     * Ensure shared Notifal admin assets are registered before builder assets.
     *
     * @return void
     * @since 2.4.0
     */
    private static function ensureBaseAssets(): void
    {
        $cssUrl = Paths::cssAdminBuildUrl();
        $jsUrl = Paths::jsAdminBuildUrl();

        if (!wp_style_is('notifal-global-backend', 'enqueued') && !wp_style_is('notifal-global-backend', 'done')) {
            notifal_enqueue_style(
                'notifal-global-backend',
                $cssUrl . 'GlobalBackendStyle.css',
                []
            );
        }

        if (!wp_style_is('notifal-icons', 'enqueued') && !wp_style_is('notifal-icons', 'done')) {
            notifal_enqueue_style(
                'notifal-icons',
                $cssUrl . 'IconsAdminStyle.css',
                ['notifal-global-backend']
            );
        }

        if (!wp_style_is('notifal-shared-admin-css', 'enqueued') && !wp_style_is('notifal-shared-admin-css', 'done')) {
            notifal_enqueue_style(
                'notifal-shared-admin-css',
                $cssUrl . 'SharedAdminStyle.css',
                []
            );
        }

        if (!wp_script_is('notifal-tags-js', 'enqueued') && !wp_script_is('notifal-tags-js', 'done')) {
            notifal_enqueue_script(
                'notifal-tags-js',
                $jsUrl . 'TagsAdminScript.js',
                []
            );
        }

        if (!wp_script_is('notifal-shared-admin-js', 'enqueued') && !wp_script_is('notifal-shared-admin-js', 'done')) {
            notifal_enqueue_script(
                'notifal-shared-admin-js',
                $jsUrl . 'SharedAdminScript.js',
                ['notifal-tags-js'],
                [
                    'ajax_url' => UrlHelper::baseAjax(),
                    'rtl'      => is_rtl(),
                ]
            );
        }
    }

    /**
     * Append runtime configuration used by the builder JavaScript.
     *
     * @param array $strings Localized translation strings.
     * @return array Merged configuration array.
     * @since 2.4.0
     */
    private static function appendRuntimeConfig(array $strings): array
    {
        $templateId = isset($_GET['template_id']) ? absint($_GET['template_id']) : 0;
        $template = $templateId ? get_post($templateId) : null;
        /** @var TemplateUrlService $urlService */
        $urlService = notifal_app(TemplateUrlService::class);

        $strings['config'] = [
            'template_id'        => $templateId,
            'title'              => $template instanceof \WP_Post ? $template->post_title : '',
            'initial_html'       => $template instanceof \WP_Post ? (string) $template->post_content : '',
            'post_status'        => $template instanceof \WP_Post ? $template->post_status : 'draft',
            'can_publish'        => $templateId > 0
                ? current_user_can('publish_post', $templateId)
                : current_user_can('publish_posts'),
            'ajax_url'           => UrlHelper::baseAjax(),
            'templates_list_url' => admin_url('admin.php?page=notifal_templates'),
            'preview_url'        => $templateId ? $urlService->getPreviewUrl($templateId, $template) : '',
            'logo_url'           => Paths::buildImagesUrl() . 'notifal-builder-logo.jpg',
            'nonce'              => [
                'save'    => NonceManager::create('notifal_save_html_template'),
                'preview' => NonceManager::create('notifal_html_builder_preview'),
            ],
            'user_id'            => get_current_user_id(),
            'rtl'                => is_rtl(),
            'primary_color'      => self::resolvePrimaryColor(),
            'active_plugins'     => PluginDetector::getActivePluginNames(),
            'use_cases'          => HtmlBuilderUseCases::getOptions(),
            'display_layouts'    => HtmlBuilderDisplayLayouts::getOptions(),
            'ai_prompt_examples' => HtmlBuilderAiPromptExamples::getExamples(),
        ];

        return $strings;
    }

    /**
     * Resolve the Notifal brand primary color for the AI prompt default.
     *
     * @return string Hex color value.
     * @since 2.4.0
     */
    private static function resolvePrimaryColor(): string
    {
        // Allow extensions to override the default brand color used in prompts.
        $color = (string) apply_filters(FilterHooks::TEMPLATE_HTML_BUILDER_PRIMARY_COLOR, '#7e2bd2');

        // Validate the resolved value as a 3 or 6 digit hex color.
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            return $color;
        }

        return '#7e2bd2';
    }

    /**
     * Render tags panel markup for the Insert Tag modal.
     *
     * @return string Rendered tags HTML.
     * @since 2.4.0
     */
    public static function renderTagsPanel(): string
    {
        $tags = self::getFilteredTags();

        if (empty($tags)) {
            return '<p class="notifal-text-muted">' . esc_html__('No tags available.', 'notifal') . '</p>';
        }

        return self::renderTags($tags, [
            'container_class' => 'notifal-tags-container notifal-html-builder-tags-panel',
            'show_info'       => false,
            'show_warning'    => false,
        ]);
    }
}
