<?php

namespace Notifal\Modules\Templates\Presentation\Admin\ViewComponents;

defined('ABSPATH') || exit;

use Notifal\Modules\Templates\Application\Services\TemplateBuilderDetector;
use Notifal\Modules\Templates\Application\Services\TemplateUrlService;
use Notifal\Modules\Templates\Infrastructure\Shared\Traits\TemplateContentTrait;
use WP_Post;

/**
 * Class TemplateRenderer
 *
 * Renders visual HTML output for a single notifal template selection card.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\Templates\Presentation\Admin\ViewComponents
 */
class TemplateRenderer
{
    use TemplateContentTrait;
    /**
     * Render a radio card with live iframe preview of the given template.
     *
     * @since 2.0.0
     * @param WP_Post   $template   Template post object.
     * @param int|null  $selectedId Currently selected template ID.
     * @return void
     */
    public static function renderPreviewCard(WP_Post $template, ?int $selectedId = null): void
    {
        $id    = $template->ID;
        $title = esc_html(get_the_title($template));

        // Only check if this template matches the selected ID
        $checked = ($selectedId === $id) ? 'checked' : '';

        $previewUrl = esc_url(
            notifal_app(TemplateUrlService::class)->getPreviewUrl($id, $template)
        );
        $editUrl = notifal_app(TemplateUrlService::class)->getEditUrl($template);

        // Enhanced empty template check
        if (!self::templateHasContent($template)) {
            return; // Skip empty templates
        }
        ?>
        <div class="notifal-template-card-parent">
            <input type="radio"
                   id="notifal_template_<?php echo esc_attr($id); ?>"
                   name="notifal_template_id"
                   value="<?php echo esc_attr($id); ?>"
                <?php echo $checked; ?> />

            <label class="notifal-template-card" for="notifal_template_<?php echo esc_attr($id); ?>">
                <div class="template-preview">
                    <div class="notifal-iframe-loading"><?php echo esc_html__('Loading...', 'notifal'); ?></div>
                    <iframe
                        src="<?php echo esc_url($previewUrl); ?>"
                        class="notifal-template-iframe"
                        loading="lazy"
                    ></iframe>
                </div>
                <div class="notifal-flex notifal-justify-between notifal-align-center notifal-p-5">
                    <div class="template-title"><?php echo $title; ?></div>
                    <div class="notifal-flex notifal-gap-10">
                        <a href="<?php echo esc_url($editUrl); ?>"
                           class="notifal-action-button"
                           title="<?php esc_attr_e('Edit', 'notifal'); ?>"
                           target="_blank">
                            <i class="dashicons dashicons-edit"></i>
                        </a>
                        <a href="<?php echo esc_url($previewUrl); ?>"
                           class="notifal-action-button"
                           title="<?php esc_attr_e('View', 'notifal'); ?>"
                           target="_blank">
                            <i class="dashicons dashicons-visibility"></i>
                        </a>
                    </div>
                </div>
            </label>
        </div>
        <?php
    }

    /**
     * Check if template has meaningful content for preview.
     *
     * @since 2.0.0
     * @param WP_Post $template The template post object
     * @return bool True if template has content, false otherwise
     */
    private static function templateHasContent(WP_Post $template): bool
    {
        $builder = TemplateBuilderDetector::getBuilder($template);
        if ($builder === TemplateBuilderDetector::BUILDER_HTML) {
            return self::hasTemplateContent($template, TemplateBuilderDetector::BUILDER_HTML);
        }

        $legacyBuilder = $builder === TemplateBuilderDetector::BUILDER_ELEMENTOR ? 'elementor' : 'block-editor';
        return self::hasTemplateContent($template, $legacyBuilder);
    }
}
