<?php


namespace Notifal\Modules\Templates\Presentation\Admin\ListTable;

defined('ABSPATH') || exit;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Templates\Application\Services\TemplateBuilderDetector;
use WP_Post;

/**
 * Class ColumnsController
 *
 * Handles custom columns for the admin list table of notifal_template post type.
 * Provides builder type detection and display for template management interface.
 *
 * This controller registers filters to inject custom column content into the
 * WordPress admin list table, specifically identifying which page builder
 * (Elementor or Block Editor) was used to create each template.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\Templates\Presentation\Admin\ListTable
 */
class ColumnsController
{
    /**
     * Register all related filters for custom columns.
     *
     * Hooks into WordPress admin list table filters to provide custom column rendering
     * for the notifal_template post type. Currently handles the 'builder' column.
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        add_filter(FilterHooks::ADMIN_LIST_CUSTOM_COLUMN, [self::class, 'renderBuilderColumn'], 10, 4);
    }

    /**
     * Render the "builder" column for notifal_template post type.
     *
     * Displays the builder type used to create the template (Elementor or Block Editor).
     * This helps users identify which editor was used for each template.
     *
     * @param string $content Default column content.
     * @param string $columnKey Current column key.
     * @param WP_Post $post The post object.
     * @param string $postType The post type.
     * @return string The rendered column content with proper escaping.
     * @since 2.0.0
     */
    public static function renderBuilderColumn(string $content, string $columnKey, WP_Post $post, string $postType): string
    {
        // Only process for notifal_template post type and builder column
        if ($postType !== 'notifal_template' || $columnKey !== 'builder') {
            return $content;
        }

        $builder = TemplateBuilderDetector::getBuilder($post);

        if ($builder === TemplateBuilderDetector::BUILDER_HTML) {
            $builderLabel = __('HTML Builder', 'notifal');
        } elseif ($builder === TemplateBuilderDetector::BUILDER_ELEMENTOR) {
            $builderLabel = __('Elementor', 'notifal');
        } else {
            $builderLabel = __('Block Editor', 'notifal');
        }

        return esc_html($builderLabel);
    }
}
