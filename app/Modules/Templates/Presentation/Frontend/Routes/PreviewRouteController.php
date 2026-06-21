<?php

namespace Notifal\Modules\Templates\Presentation\Frontend\Routes;

defined('ABSPATH') || exit;

use Notifal\Infrastructure\WordPress\Elementor\Helpers\ElementorHelper;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\OnPageNotification\Application\Services\Template\FrontendTemplateRenderer;
use Notifal\Modules\Templates\Application\Services\TemplateBuilderDetector;
use Notifal\Modules\Templates\Infrastructure\Shared\Traits\PreviewContextTrait;
use Notifal\Modules\Templates\Config\Paths as ModulePaths;
use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\RegisterBlocks;
use WP_Post;

/**
 * Class PreviewRouteController
 *
 * Handles frontend preview route for notifal_template post.
 * Renders minimal HTML at wp_loaded and exits, so the preview always
 * displays regardless of theme or main query.  Post context is set so
 * wp_head() and content rendering see the correct template post.
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Presentation\Frontend\Routes
 * @author Hossein <hossein@notifal.com>
 */
class PreviewRouteController
{
    use PreviewContextTrait;

    /**
     * Registers the template preview route.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register(): void
    {
        add_action('wp_loaded', [self::class, 'maybeRenderPreview']);
    }

    /**
     * If preview query param is present, render minimal template page and exit.
     *
     * Does not rely on main query or template_include; outputs HTML directly
     * so the preview works on any WordPress setup.
     *
     * @since 2.0.0
     * @since 2.4.0 Updated to allow draft templates for users who can edit them.
     * @return void
     */
    public static function maybeRenderPreview(): void
    {
        if (!self::isTemplatePreviewMode()) {
            return;
        }

        if (!notifal_verify_get_request('notifal_template_preview', 'edit_posts')) {
            return;
        }

        $postId = absint($_GET['notifal_template_preview']);
        $post   = get_post($postId);

        // Allow draft/private templates when the current user can edit them.
        if (
            !$post ||
            $post->post_type !== 'notifal_template' ||
            !current_user_can('edit_post', $postId)
        ) {
            wp_die(esc_html__('Invalid or inaccessible template.', 'notifal'));
        }

        do_action(ActionHooks::TEMPLATE_PREVIEW_BEFORE, $post);

        $GLOBALS['post'] = $post;
        setup_postdata($post);

        if (!ElementorHelper::hasBuilder($post)) {
            self::ensureBlocksRegistered();
        }

        if (ElementorHelper::hasBuilder($post) && PluginDetector::isElementorActive()) {
            add_filter('elementor/widgets/widgets_registered', function () use ($post) {
                $original_post   = $GLOBALS['post'] ?? null;
                $GLOBALS['post'] = $post;
                if (class_exists('\Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Widgets\WidgetsRegistrar')) {
                    \Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Widgets\WidgetsRegistrar::register_widgets();
                }
                $GLOBALS['post'] = $original_post;
            }, 999);
        }

        if (ElementorHelper::hasBuilder($post) && PluginDetector::isElementorActive()) {
            self::initElementorForPreview($post);
        }

        wp_enqueue_style(
            'notifal-template-preview',
            ModulePaths::cssFrontendUrl() . 'notifal-template-preview.css',
            [],
            NOTIFAL_VERSION
        );

        $renderedContent = '';
        add_action('wp_enqueue_scripts', static function () use ($post, &$renderedContent) {
            $renderedContent = self::renderPreviewContent($post);
        }, PHP_INT_MAX);

        status_header(200);
        nocache_headers();
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
        add_filter('show_admin_bar', '__return_false');
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
        remove_all_actions('user_admin_notices');
        remove_all_actions('network_admin_notices');
        remove_all_actions('in_admin_header');
        ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php wp_head(); ?>
</head>
<body <?php body_class('notifal-template-preview-body'); ?>>
<div class="notifal-template-preview-wrapper">
        <?php
        echo apply_filters(FilterHooks::TEMPLATE_PREVIEW_OUTPUT, $renderedContent, $post);
        ?>
</div>
        <?php
        wp_footer();
        do_action(ActionHooks::TEMPLATE_PREVIEW_AFTER, $post);
        wp_reset_postdata();
        ?>
</body>
</html>
        <?php
        exit;
    }

    /**
     * Initialise Elementor frontend for preview (register and enqueue base assets).
     *
     * @param WP_Post $post Template post.
     * @return void
     */
    private static function initElementorForPreview(WP_Post $post): void
    {
        try {
            $frontend = \Elementor\Plugin::$instance->frontend;
            $frontend->init();
            $frontend->register_styles();
            $frontend->register_scripts();
            $frontend->enqueue_styles();
            $frontend->enqueue_scripts();
            if (method_exists(\Elementor\Plugin::$instance->widgets_manager, 'enqueue_widgets_styles')) {
                \Elementor\Plugin::$instance->widgets_manager->enqueue_widgets_styles();
            }
            wp_enqueue_style('elementor-icons');
            wp_enqueue_style('elementor-animations');
            wp_enqueue_style('elementor-frontend');
        } catch (\Exception $e) {
            // continue
        }
    }

    /**
     * Render template content for preview (Elementor or block editor).
     *
     * @param WP_Post $post Template post.
     * @return string HTML content.
     * @since 2.4.0 Added HTML Builder rendering branch.
     */
    private static function renderPreviewContent(WP_Post $post): string
    {
        // HTML Builder templates use the full frontend renderer pipeline.
        if (TemplateBuilderDetector::isHtmlBuilder($post)) {
            /** @var FrontendTemplateRenderer $renderer */
            $renderer = notifal_app(FrontendTemplateRenderer::class);
            $result   = $renderer->renderForFrontend($post->ID, ['is_preview' => true]);

            return (string) ($result['html'] ?? '');
        }

        $isElementor = ElementorHelper::hasBuilder($post) && PluginDetector::isElementorActive();

        if ($isElementor) {
            $saved = $GLOBALS['post'] ?? null;
            unset($GLOBALS['post']);
            $content = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($post->ID);
            $GLOBALS['post'] = $saved;
            if ($saved) {
                setup_postdata($saved);
            }
            $content = self::processElementorPreviewTags($content);
        } else {
            $content = $post->post_content;
            if (function_exists('do_blocks')) {
                $content = do_blocks($content);
            } else {
                $content = apply_filters('the_content', $content);
            }
            $content = self::processBlockEditorPreviewTags($content, $post);
        }

        $content = self::processPreviewContent($content, $post);
        return $content;
    }

    /**
     * Process preview content with Notifal tags (wrapper).
     *
     * @param string  $content Rendered HTML.
     * @param WP_Post $post    Template post.
     * @return string Processed content.
     */
    public static function processPreviewContent(string $content, WP_Post $post): string
    {
        $isElementor = ElementorHelper::hasBuilder($post) && PluginDetector::isElementorActive();
        if ($isElementor) {
            return self::processElementorPreviewTags($content);
        }
        return self::processBlockEditorPreviewTags($content, $post);
    }

    private static function processElementorPreviewTags(string $content): string
    {
        try {
            $templateBuilder = notifal_app(
                \Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Services\ElementorTemplateBuilder::class
            );
            return $templateBuilder->buildPreviewContent($content);
        } catch (\Exception $e) {
            // fallback
        }
        try {
            $tagManager          = notifal_app(\Notifal\Domain\Tags\TagManager::class);
            $previewDataResolver = notifal_app(\Notifal\Modules\Templates\Application\Services\PreviewDataResolver::class);
            $previewData         = $previewDataResolver->resolve($content);
            if ($previewData) {
                $orderFetcher = notifal_app(\Notifal\Domain\Orders\OrderFetcherInterface::class);
                $context     = [
                    'product'    => $previewData->getProduct(),
                    'order'      => $orderFetcher->getRandom(),
                    'is_preview' => true,
                ];
                $content = $tagManager->render($content, $context);
            }
        } catch (\Exception $e) {
            // keep content
        }
        return $content;
    }

    private static function processBlockEditorPreviewTags(string $content, WP_Post $post): string
    {
        if (class_exists('\Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\BlockEditorTemplateBuilder')) {
            try {
                $templateBuilder = notifal_app(
                    \Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\BlockEditorTemplateBuilder::class
                );
                return $templateBuilder->buildPreviewContent($content);
            } catch (\Exception $e) {
                // keep content
            }
        }
        return $content;
    }

    public static function getPreviewPost(): ?WP_Post
    {
        return $GLOBALS['post'] ?? null;
    }

    public static function renderPreviewOutput(WP_Post $post): void
    {
        $GLOBALS['post'] = $post;
        setup_postdata($post);
        self::maybeRenderPreview();
    }

    private static function ensureBlocksRegistered(): void
    {
        if (!class_exists('\Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\RegisterBlocks')) {
            return;
        }
        $registry       = \WP_Block_Type_Registry::get_instance();
        $notifal_blocks = ['notifal/action-button', 'notifal/close-icon', 'notifal/featured-image'];
        foreach ($notifal_blocks as $block_name) {
            if (!$registry->is_registered($block_name)) {
                RegisterBlocks::registerAllBlocks();
                break;
            }
        }
    }
}
