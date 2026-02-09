<?php

namespace Notifal\Modules\Templates\Presentation\Frontend\Routes;

defined('ABSPATH') || exit;

use Notifal\Infrastructure\WordPress\Elementor\Helpers\ElementorHelper;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\Templates\Infrastructure\Shared\Traits\PreviewContextTrait;
use Notifal\Modules\Templates\Config\Paths as ModulePaths;
use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\RegisterBlocks;
use WP_Post;

/**
 * Class PreviewRouteController
 *
 * Handles frontend preview route for notifal_template post.
 * Renders minimal HTML without theme header/footer for iframe usage.
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
     * Checks for preview query param and renders template if valid.
     *
     * @since 2.0.0
     * @return void
     */
    public static function maybeRenderPreview(): void
    {
        if (!self::isTemplatePreviewMode()) {
            return;
        }

        // Verify nonce for security
        if (!notifal_verify_get_request('notifal_template_preview', 'edit_posts')) {
            return;
        }

        $postId = absint($_GET['notifal_template_preview']);
        $post = get_post($postId);

        if (
            !$post ||
            $post->post_type !== 'notifal_template' ||
            $post->post_status !== 'publish'
        ) {
            wp_die(__('Invalid or inaccessible template.', 'notifal'));
        }

        do_action(ActionHooks::TEMPLATE_PREVIEW_BEFORE, $post);

        $isElementorTemplate = ElementorHelper::hasBuilder($post);
        $isElementorActive = PluginDetector::isElementorActive();

        // Ensure blocks are registered for preview context
        if (!$isElementorTemplate) {
            self::ensureBlocksRegistered();
        }

        // For Elementor templates, trigger widget registration via hook
        if ($isElementorTemplate) {
            add_filter('elementor/widgets/widgets_registered', function() use ($post) {
                $original_post = $GLOBALS['post'] ?? null;
                $GLOBALS['post'] = $post;

                if (class_exists('\Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Widgets\WidgetsRegistrar')) {
                    \Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Widgets\WidgetsRegistrar::register_widgets();
                }

                $GLOBALS['post'] = $original_post;
            }, 999);
        }

        // Enqueue Elementor assets if needed
        if ($isElementorTemplate) {
            \Elementor\Plugin::$instance->frontend->enqueue_styles();
            \Elementor\Plugin::$instance->frontend->enqueue_scripts();
        }

        self::renderMinimalTemplate($post, $isElementorTemplate, $isElementorActive);

        do_action(ActionHooks::TEMPLATE_PREVIEW_AFTER, $post);

        exit;
    }

    /**
     * Renders a minimal HTML page for iframe preview.
     *
     * @param WP_Post $post The template post object
     * @param bool|null $isElementorTemplate Whether the template uses Elementor
     * @param bool|null $isElementorActive Whether Elementor plugin is active
     * @since 2.0.0
     * @return void
     */
    protected static function renderMinimalTemplate(WP_Post $post, ?bool $isElementorTemplate = null, ?bool $isElementorActive = null): void
    {
        $isElementorTemplate = $isElementorTemplate ?? ElementorHelper::hasBuilder($post);
        $isElementorActive = $isElementorActive ?? PluginDetector::isElementorActive();

        status_header(200);
        nocache_headers();
        header('Content-Type: text/html; charset=' . get_option('blog_charset'));

        // Hide PHP errors in preview to prevent breaking the iframe display
        // regardless of WP_DEBUG_DISPLAY setting in wp-config.php
        // This ensures clean preview output for templates
        ini_set('display_errors', '0');
        error_reporting(0);

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
            <?php
            // Enqueue Elementor assets if needed
            if ($isElementorTemplate && $isElementorActive) {
                try {
                    \Elementor\Plugin::$instance->frontend->init();
                    \Elementor\Plugin::$instance->frontend->enqueue_styles();
                    \Elementor\Plugin::$instance->frontend->enqueue_scripts();

                    \Elementor\Plugin::$instance->widgets_manager->enqueue_widgets_styles();

                    wp_enqueue_style('elementor-icons');
                    wp_enqueue_style('elementor-animations');
                    wp_enqueue_style('elementor-frontend');
                } catch (\Exception $e) {
                    // Silently handle Elementor initialization errors to prevent breaking preview
                }
            }
            
            // Enqueue preview-specific styles
            wp_enqueue_style(
                'notifal-template-preview',
                ModulePaths::cssFrontendUrl() . 'notifal-template-preview.css',
                [],
                NOTIFAL_VERSION
            );

            // Always load WP head for proper asset loading
            wp_head();
            ?>
        </head>
        <body>
        <div class="notifal-template-preview-wrapper">
            <?php
            if ($isElementorTemplate) {
                $content = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($post->ID);

                try {
                    $templateBuilder = notifal_app(\Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Services\ElementorTemplateBuilder::class);
                    $content = $templateBuilder->buildPreviewContent($content);
                } catch (\Exception $e) {
                    // Fallback to basic tag processing if service unavailable
                    $tagManager = notifal_app(\Notifal\Domain\Tags\TagManager::class);
                    $previewDataResolver = notifal_app(\Notifal\Modules\Templates\Application\Services\PreviewDataResolver::class);
                    $previewData = $previewDataResolver->resolve($content);

                    if ($previewData) {
                        $orderFetcher = notifal_app(\Notifal\Domain\Orders\OrderFetcherInterface::class);
                        $context = [
                            'product' => $previewData->getProduct(),
                            'order' => $orderFetcher->getRandom(),
                            'is_preview' => true,
                        ];
                        $content = $tagManager->render($content, $context);
                    }
                }
            } else {
                global $wp_query, $wp_the_query;

                $original_post = $GLOBALS['post'] ?? null;
                $original_query = $wp_query;
                $original_the_query = $wp_the_query;

                $GLOBALS['post'] = $post;
                setup_postdata($post);

                $wp_query = new \WP_Query([
                    'p' => $post->ID,
                    'post_type' => 'notifal_template',
                    'posts_per_page' => 1
                ]);
                $wp_the_query = $wp_query;

                if (function_exists('do_blocks')) {
                    $content = do_blocks($post->post_content);
                } else {
                    $content = apply_filters('the_content', $post->post_content);
                }

                if (class_exists('\Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\BlockEditorTemplateBuilder')) {
                    try {
                        $templateBuilder = notifal_app(\Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\BlockEditorTemplateBuilder::class);
                        $content = $templateBuilder->buildPreviewContent($content);
                    } catch (\Exception $e) {
                        // Continue with original content if service unavailable
                    }
                }

                $GLOBALS['post'] = $original_post;
                $wp_query = $original_query;
                $wp_the_query = $original_the_query;
                if ($original_post) {
                    setup_postdata($original_post);
                } else {
                    wp_reset_postdata();
                }

                if (self::isContentMinimal($content)) {
                    $content = self::generateFallbackPreview($post);
                }
            }

            echo apply_filters(FilterHooks::TEMPLATE_PREVIEW_OUTPUT, $content, $post);
            ?>
        </div>
        <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }

    /**
     * Check if content is minimal or empty.
     *
     * @param string $content The processed content
     * @return bool True if content is minimal, false otherwise
     * @since 2.0.0
     */
    private static function isContentMinimal(string $content): bool
    {
        $cleanContent = trim(strip_tags($content));
        return empty($cleanContent) || strlen($cleanContent) < 10;
    }

    /**
     * Generate fallback preview content for minimal templates.
     *
     * @param WP_Post $post The template post
     * @return string Fallback preview HTML
     * @since 2.0.0
     */
    private static function generateFallbackPreview(WP_Post $post): string
    {
        $title = esc_html($post->post_title ?: __('Untitled Template', 'notifal'));

        return sprintf(
            '<div style="padding: 40px; text-align: center; background: #f9f9f9; border: 2px dashed #ddd; border-radius: 8px; margin: 20px;">
                <h3 style="color: #666; margin: 0 0 10px 0; font-family: sans-serif;">%s</h3>
                <p style="color: #999; margin: 0; font-family: sans-serif; font-size: 14px;">%s</p>
                <p style="color: #999; margin: 10px 0 0 0; font-family: sans-serif; font-size: 12px;">%s</p>
            </div>',
            $title,
            esc_html__('This template needs content to display a preview.', 'notifal'),
            esc_html__('Click Edit to add blocks and design your notification.', 'notifal')
        );
    }

    /**
     * Ensure Notifal blocks are registered for preview context.
     *
     * @since 2.0.0
     * @return void
     */
    private static function ensureBlocksRegistered(): void
    {
        if (class_exists('\Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\RegisterBlocks')) {
            // Check if blocks are already registered to avoid duplicate registration errors
            $registry = \WP_Block_Type_Registry::get_instance();
            $notifal_blocks = ['notifal/action-button', 'notifal/close-icon', 'notifal/featured-image'];

            $blocks_registered = true;
            foreach ($notifal_blocks as $block_name) {
                if (!$registry->is_registered($block_name)) {
                    $blocks_registered = false;
                    break;
                }
            }

            // Only register if blocks are not already registered
            if (!$blocks_registered) {
                RegisterBlocks::registerAllBlocks();
            }
        }
    }

}
