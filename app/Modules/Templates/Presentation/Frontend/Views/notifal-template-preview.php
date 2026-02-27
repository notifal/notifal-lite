<?php
/**
 * Minimal template for Notifal template preview (notifal_template_preview).
 *
 * Loaded via template_include after PreviewRouteController overrides the
 * main WP_Query with pre_get_posts.  Because WordPress's full lifecycle
 * runs (parse_request → query_posts → template_redirect → template_include),
 * this template is rendered in the EXACT same context as the standard
 * /notifal-template/slug/ view.  All plugins (Elementor, WoodMart, etc.)
 * fire their wp_enqueue_scripts callbacks normally, so every widget
 * asset loads identically to the standard post view.
 *
 * @package Notifal\Modules\Templates\Presentation\Frontend\Views
 * @since 2.0.0
 */

defined('ABSPATH') || exit;

use Notifal\Infrastructure\WordPress\Elementor\Helpers\ElementorHelper;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\Templates\Presentation\Frontend\Routes\PreviewRouteController;

$GLOBALS['notifal_using_minimal_preview_template'] = true;

remove_all_actions('admin_notices');
remove_all_actions('all_admin_notices');
remove_all_actions('user_admin_notices');
remove_all_actions('network_admin_notices');

status_header(200);
nocache_headers();

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
    if (have_posts()) {
        while (have_posts()) {
            the_post();

            $post        = get_post();
            $isElementor = ElementorHelper::hasBuilder($post) && PluginDetector::isElementorActive();

            if ($isElementor) {
                $content = \Elementor\Plugin::instance()
                    ->frontend
                    ->get_builder_content_for_display(get_the_ID());
            } else {
                $content = apply_filters('the_content', get_the_content());
            }

            $content = PreviewRouteController::processPreviewContent($content, $post);

            echo apply_filters(FilterHooks::TEMPLATE_PREVIEW_OUTPUT, $content, $post);
        }
    }
    ?>
</div>
<?php
wp_footer();

$previewPost = PreviewRouteController::getPreviewPost();
if ($previewPost) {
    do_action(ActionHooks::TEMPLATE_PREVIEW_AFTER, $previewPost);
}
?>
</body>
</html>
