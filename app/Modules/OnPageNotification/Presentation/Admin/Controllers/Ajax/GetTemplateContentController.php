<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\Controllers\Ajax;

use Notifal\Infrastructure\WordPress\Elementor\Helpers\ElementorHelper;
use Notifal\Infrastructure\WordPress\Support\ContentExtractor;
use Notifal\Modules\OnPageNotification\Application\Services\Tag\TagCategoryDetector;
use Notifal\Shared\Utils\Helper;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * AJAX Controller for retrieving template content for analysis.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class GetTemplateContentController
{
    /**
     * Register AJAX handlers.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register(): void
    {
        add_action('wp_ajax_notifal_get_template_content', [self::class, 'handle']);
    }

    /**
     * Handle the AJAX request to get template content.
     *
     * @since 2.0.0
     * @return void
     */
    public static function handle(): void
    {
        try {
            // Verify nonce and user capabilities
            notifal_verify_ajax_request('notifal_get_template_content', 'edit_posts');

            // Validate and sanitize template ID
            $templateId = absint($_POST['template_id'] ?? 0);
            if (!$templateId) {
                wp_send_json_error([
                    'message' => __('Invalid template ID.', 'notifal')
                ]);
            }

            // Get template post safely
            $template = Helper::getPostSafe($templateId, 'notifal_template');
            if (!$template) {
                wp_send_json_error([
                    'message' => __('Template not found.', 'notifal')
                ]);
            }

            // Get template content using centralized ContentExtractor
            $content = self::getTemplateContent($template);

            if (empty($content)) {
                wp_send_json_error([
                    'message' => __('Template has no content.', 'notifal')
                ]);
            }

            // Detect tag categories and hidden restrictions
            $categoryDetector = new TagCategoryDetector();
            $detectedCategories = $categoryDetector->detectCategories($content);
            $hiddenRestrictions = $categoryDetector->getHiddenRestrictions($detectedCategories);

            wp_send_json_success([
                'content' => $content,
                'template_id' => $templateId,
                'template_title' => get_the_title($template),
                'detected_categories' => $detectedCategories,
                'hidden_restrictions' => $hiddenRestrictions
            ]);

        } catch (\Exception $e) {
            error_log('Notifal GetTemplateContent Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An unexpected error occurred. Please try again.', 'notifal')
            ]);
        }
    }

    /**
     * Extract content from template using centralized ContentExtractor.
     *
     * @since 2.0.0
     * @param WP_Post $template Template post object.
     * @return string Template content.
     */
    private static function getTemplateContent(WP_Post $template): string
    {
        $isElementor = ElementorHelper::hasBuilder($template);

        if ($isElementor) {
            return ContentExtractor::extractFromElementorTemplate($template);
        } else {
            return ContentExtractor::extractFromBlockTemplate($template);
        }
    }
} 
