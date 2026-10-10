<?php

namespace Notifal\Modules\Templates\Application\Services;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Widget definitions for the HTML Builder insert palette and AI prompts.
 *
 * Widgets are HTML shortcuts. They write known markup (with data-notifal-widget)
 * so the builder can detect them and expose widget-specific settings.
 * Developers can add or modify widgets via FilterHooks::TEMPLATE_HTML_BUILDER_WIDGETS.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class HtmlBuilderWidgets
{
    /**
     * Return localized widget definitions for the builder UI and AI prompts.
     *
     * @return array<int, array{
     *     id: string,
     *     label: string,
     *     category: string,
     *     category_label: string,
     *     icon: string,
     *     description: string,
     *     default_html: string,
     *     ai_snippet: string
     * }>
     * @since 3.0.0
     */
    public static function getWidgets(): array
    {
        // Build the core palette used for click/drag insert and AI structure guidance.
        $widgets = array(
            self::headingWidget(),
            self::paragraphWidget(),
            self::imageWidget(),
            self::featuredImageWidget(),
            self::buttonWidget(),
            self::closeButtonWidget(),
            self::listWidget(),
            self::countdownWidget(),
            self::dividerWidget(),
            self::spacerWidget(),
        );

        /**
         * Filter HTML Builder widgets before they are sent to JavaScript.
         *
         * @since 3.0.0
         *
         * @param array<int, array<string, string>> $widgets Widget definition rows.
         */
        $filtered = (array) apply_filters(FilterHooks::TEMPLATE_HTML_BUILDER_WIDGETS, $widgets);

        // Keep only well-formed widget rows so the React palette stays stable.
        return array_values(array_filter($filtered, array(self::class, 'isValidWidget')));
    }

    /**
     * Validate a widget definition row.
     *
     * @param mixed $widget Candidate widget row.
     * @return bool True when required keys are present and non-empty.
     * @since 3.0.0
     */
    public static function isValidWidget($widget): bool
    {
        if (!is_array($widget)) {
            return false;
        }

        $required = array('id', 'label', 'category', 'category_label', 'icon', 'description', 'default_html', 'ai_snippet');

        foreach ($required as $key) {
            if (!isset($widget[$key]) || !is_string($widget[$key]) || $widget[$key] === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Heading widget definition.
     *
     * @return array<string, string>
     * @since 3.0.0
     */
    private static function headingWidget(): array
    {
        $html = '<h2 data-notifal-widget="heading" class="notifal-hb-heading">Your headline</h2>';

        return array(
            'id'             => 'heading',
            'label'          => __('Heading', 'notifal'),
            'category'       => 'basic',
            'category_label' => __('Basic', 'notifal'),
            'icon'           => 'pencil-square',
            'description'    => __('Add a headline (h2).', 'notifal'),
            'default_html'   => $html,
            'ai_snippet'     => $html,
        );
    }

    /**
     * Paragraph widget definition.
     *
     * @return array<string, string>
     * @since 3.0.0
     */
    private static function paragraphWidget(): array
    {
        $html = '<p data-notifal-widget="paragraph" class="notifal-hb-paragraph">Add your message here.</p>';

        return array(
            'id'             => 'paragraph',
            'label'          => __('Paragraph', 'notifal'),
            'category'       => 'basic',
            'category_label' => __('Basic', 'notifal'),
            'icon'           => 'file-earmark-code',
            'description'    => __('Add a text paragraph.', 'notifal'),
            'default_html'   => $html,
            'ai_snippet'     => $html,
        );
    }

    /**
     * Static image widget definition.
     *
     * @return array<string, string>
     * @since 3.0.0
     */
    private static function imageWidget(): array
    {
        $html = '<img data-notifal-widget="image" class="notifal-hb-image" src="https://loremflickr.com/120/120/product?lock=42" alt="" width="120" height="120" />';

        return array(
            'id'             => 'image',
            'label'          => __('Image', 'notifal'),
            'category'       => 'media',
            'category_label' => __('Media', 'notifal'),
            'icon'           => 'eye',
            'description'    => __('Add a static image. Replace the src in Content or Media Library.', 'notifal'),
            'default_html'   => $html,
            'ai_snippet'     => $html,
        );
    }

    /**
     * Featured image placeholder widget definition.
     *
     * @return array<string, string>
     * @since 3.0.0
     */
    private static function featuredImageWidget(): array
    {
        $html = '<div data-notifal-widget="featured-image" class="notifal-post-feature-image"></div>';

        return array(
            'id'             => 'featured-image',
            'label'          => __('Featured Image', 'notifal'),
            'category'       => 'media',
            'category_label' => __('Media', 'notifal'),
            'icon'           => 'layers',
            'description'    => __('Dynamic product/post featured image placeholder.', 'notifal'),
            'default_html'   => $html,
            'ai_snippet'     => $html,
        );
    }

    /**
     * Action button widget definition.
     *
     * @return array<string, string>
     * @since 3.0.0
     */
    private static function buttonWidget(): array
    {
        $html = '<a href="#" data-notifal-widget="button" class="notifal-action-button notifal-hb-button" style="display:inline-flex;align-items:center;justify-content:center;padding:12px 20px;background:#7e2bd2;color:#ffffff;font-size:14px;font-weight:600;line-height:1.2;text-decoration:none;border-radius:8px;">Shop now</a>';

        return array(
            'id'             => 'button',
            'label'          => __('Button', 'notifal'),
            'category'       => 'actions',
            'category_label' => __('Actions', 'notifal'),
            'icon'           => 'cursor',
            'description'    => __('Add a Notifal action button CTA.', 'notifal'),
            'default_html'   => $html,
            'ai_snippet'     => $html,
        );
    }

    /**
     * Close button widget definition.
     *
     * @return array<string, string>
     * @since 3.0.0
     */
    private static function closeButtonWidget(): array
    {
        $html = '<button type="button" data-notifal-widget="close-button" class="notifal-close-button" aria-label="Close notification">&times;</button>';

        return array(
            'id'             => 'close-button',
            'label'          => __('Close Button', 'notifal'),
            'category'       => 'actions',
            'category_label' => __('Actions', 'notifal'),
            'icon'           => 'x-circle',
            'description'    => __('Add a dismiss (close) control.', 'notifal'),
            'default_html'   => $html,
            'ai_snippet'     => $html,
        );
    }

    /**
     * List widget definition.
     *
     * @return array<string, string>
     * @since 3.0.0
     */
    private static function listWidget(): array
    {
        $html = '<ul data-notifal-widget="list" class="notifal-hb-list"><li>Benefit one</li><li>Benefit two</li><li>Benefit three</li></ul>';

        return array(
            'id'             => 'list',
            'label'          => __('List', 'notifal'),
            'category'       => 'basic',
            'category_label' => __('Basic', 'notifal'),
            'icon'           => 'layers-fill',
            'description'    => __('Add a bullet list of benefits or steps.', 'notifal'),
            'default_html'   => $html,
            'ai_snippet'     => $html,
        );
    }

    /**
     * Countdown timer widget definition (attribute-based settings).
     *
     * @return array<string, string>
     * @since 3.0.0
     */
    private static function countdownWidget(): array
    {
        // Shared markup keeps insert HTML and AI snippets identical.
        $html = HtmlBuilderCountdownMarkup::defaultHtml();

        return array(
            'id'             => 'countdown',
            'label'          => __('Countdown', 'notifal'),
            'category'       => 'actions',
            'category_label' => __('Actions', 'notifal'),
            'icon'           => 'arrow-repeat',
            'description'    => __('Add a Notifal urgency countdown timer.', 'notifal'),
            'default_html'   => $html,
            'ai_snippet'     => $html,
        );
    }

    /**
     * Divider widget definition.
     *
     * @return array<string, string>
     * @since 3.0.0
     */
    private static function dividerWidget(): array
    {
        $html = '<hr data-notifal-widget="divider" class="notifal-hb-divider" />';

        return array(
            'id'             => 'divider',
            'label'          => __('Divider', 'notifal'),
            'category'       => 'layout',
            'category_label' => __('Layout', 'notifal'),
            'icon'           => 'sliders2',
            'description'    => __('Add a horizontal divider line.', 'notifal'),
            'default_html'   => $html,
            'ai_snippet'     => $html,
        );
    }

    /**
     * Spacer widget definition.
     *
     * @return array<string, string>
     * @since 3.0.0
     */
    private static function spacerWidget(): array
    {
        $html = '<div data-notifal-widget="spacer" class="notifal-hb-spacer" style="height: 16px;" aria-hidden="true"></div>';

        return array(
            'id'             => 'spacer',
            'label'          => __('Spacer', 'notifal'),
            'category'       => 'layout',
            'category_label' => __('Layout', 'notifal'),
            'icon'           => 'desktop',
            'description'    => __('Add vertical spacing between elements.', 'notifal'),
            'default_html'   => $html,
            'ai_snippet'     => $html,
        );
    }
}
