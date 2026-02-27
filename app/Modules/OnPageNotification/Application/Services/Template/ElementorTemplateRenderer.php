<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Template;

use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Widgets\WidgetsRegistrar;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Handles Elementor-specific template rendering for frontend notifications.
 *
 * Extends AbstractTemplateRenderer to add Elementor-specific initialisation
 * (frontend subsystem, widget registration, Post-CSS, base JS) while
 * leveraging the base class for generic asset-queue snapshotting so that
 * every CSS/JS handle enqueued by ANY widget or third-party plugin during
 * content rendering is captured automatically.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ElementorTemplateRenderer extends AbstractTemplateRenderer
{
    /**
     * Expected Notifal Elementor widget names that should be registered.
     *
     * @var string[]
     */
    private const NOTIFAL_WIDGETS = [
        'notifal-close-icon',
        'notifal-action-button',
        'notifal-product-image',
    ];

    /**
     * Initialise Elementor subsystems before rendering.
     *
     * Registers Notifal widgets, initialises the Elementor frontend
     * (register + enqueue styles/scripts with proper post context),
     * so that widget handles resolve during content rendering.
     *
     * @param \WP_Post $template Template post being rendered.
     * @return void
     * @since 2.0.0
     */
    protected function initializeForRendering(\WP_Post $template): void
    {
        if (!PluginDetector::isElementorActive()) {
            return;
        }

        $this->ensureElementorWidgetsRegistered($template);
        $this->initializeElementorFrontend($template);
    }

    /**
     * Render the actual Elementor template content.
     *
     * Asset-queue snapshotting is handled by the base class around
     * this call, so any CSS/JS enqueued by Elementor widgets during
     * get_builder_content_for_display() is captured automatically.
     *
     * @param \WP_Post $template Template post.
     * @param array    $frontendContext Frontend context.
     * @return string Rendered HTML content.
     * @throws \Exception If Elementor is not active.
     * @since 2.0.0
     */
    protected function renderContent(\WP_Post $template, array $frontendContext): string
    {
        if (!PluginDetector::isElementorActive()) {
            throw new \Exception('Elementor plugin is not active or available');
        }

        return \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($template->ID);
    }

    /**
     * Get Elementor-specific assets for the template.
     *
     * Returns only Elementor-specific assets:
     *   - Post-CSS (generated stylesheet with per-widget styles)
     *   - Base Elementor JS files (webpack runtime, frontend modules, etc.)
     *
     * Generic widget/third-party assets captured via queue snapshotting
     * are merged automatically by the base class.
     *
     * @param \WP_Post $template Template post.
     * @return array{css?: string[], js?: string[]} Asset URLs or inline content.
     * @since 2.0.0
     */
    protected function getAssets(\WP_Post $template): array
    {
        $assets = $this->getElementorPostCss($template->ID);

        // Ensure base Elementor JS is always included
        $assets['js'] = $this->ensureBaseElementorJs($assets['js'] ?? []);

        return $assets;
    }

    /**
     * Get the builder type identifier.
     *
     * @return string Builder type string.
     * @since 2.0.0
     */
    protected function getBuilderType(): string
    {
        return 'elementor';
    }

    /**
     * Get the Elementor Post-CSS for a template.
     *
     * The Post-CSS file is a single generated stylesheet that contains
     * all widget-specific CSS rules used in the template.
     *
     * @param int $templateId Template ID.
     * @return array Asset array (may contain css key).
     * @since 2.0.0
     */
    private function getElementorPostCss(int $templateId): array
    {
        $assets = [];

        try {
            if (class_exists('\Elementor\Core\Files\CSS\Post')) {
                $cssFile    = new \Elementor\Core\Files\CSS\Post($templateId);
                $cssContent = $cssFile->get_content();

                if (!empty($cssContent)) {
                    $assets['css'] = [$cssContent];
                }
            }

            if (empty($assets['css'])) {
                $elementorCssUrl = plugins_url('assets/css/frontend.min.css', ELEMENTOR__FILE__);
                if (file_exists(ELEMENTOR_PATH . 'assets/css/frontend.min.css')) {
                    $assets['css'] = [$elementorCssUrl];
                }
            }
        } catch (\Throwable $e) {
            $elementorCssUrl = plugins_url('assets/css/frontend.min.css', ELEMENTOR__FILE__);
            $assets['css']   = [$elementorCssUrl];
            Helper::log('Elementor Post-CSS failed for template ' . $templateId . ': ' . $e->getMessage());
        }

        return $assets;
    }

    /**
     * Ensure base Elementor JS files are always present in the JS assets.
     *
     * @param string[] $jsAssets Current JS asset list.
     * @return string[] Updated JS asset list.
     * @since 2.0.0
     */
    private function ensureBaseElementorJs(array $jsAssets): array
    {
        $requiredFiles = [
            'assets/js/webpack.runtime.min.js',
            'assets/js/frontend-modules.min.js',
            'assets/js/frontend.min.js',
            'assets/js/elements-handlers.min.js',
        ];

        foreach ($requiredFiles as $file) {
            $fullPath = ELEMENTOR_PATH . $file;
            if (!file_exists($fullPath)) {
                continue;
            }
            $url = plugins_url($file, ELEMENTOR__FILE__);
            if (!in_array($url, $jsAssets, true)) {
                $jsAssets[] = $url;
            }
        }

        // Elementor Pro frontend modules
        if (defined('ELEMENTOR_PRO_VERSION') && defined('ELEMENTOR_PRO_PATH') && defined('ELEMENTOR_PRO__FILE__')) {
            $proFile = 'assets/js/frontend-modules.min.js';
            if (file_exists(ELEMENTOR_PRO_PATH . $proFile)) {
                $url = plugins_url($proFile, ELEMENTOR_PRO__FILE__);
                if (!in_array($url, $jsAssets, true)) {
                    $jsAssets[] = $url;
                }
            }
        }

        return $jsAssets;
    }

    /**
     * Ensure Notifal Elementor widgets are registered for frontend rendering.
     *
     * @param \WP_Post $template Template post being rendered.
     * @return void
     * @since 2.0.0
     */
    private function ensureElementorWidgetsRegistered(\WP_Post $template): void
    {
        if ($template->post_type !== 'notifal_template') {
            return;
        }

        try {
            $widgets_manager    = \Elementor\Plugin::instance()->widgets_manager;
            $registered_widgets = $widgets_manager->get_widget_types();

            $missing_widgets = array_filter(
                self::NOTIFAL_WIDGETS,
                function ($widget_name) use ($registered_widgets) {
                    return !isset($registered_widgets[$widget_name]);
                }
            );

            if (empty($missing_widgets)) {
                return;
            }

            Helper::withPostContext($template, function () {
                if (class_exists(WidgetsRegistrar::class)) {
                    WidgetsRegistrar::register_widgets();
                }
            }, false);

        } catch (\Exception $e) {
            Helper::log('Elementor widget registration failed for template ' . $template->ID . ': ' . $e->getMessage());
        }
    }

    /**
     * Initialise the Elementor frontend subsystem for rendering.
     *
     * Registers base styles/scripts, then calls enqueue_styles() and
     * enqueue_scripts() with the template post context.  This makes
     * Elementor read the template's _elementor_data meta, determine
     * which widgets are used, and enqueue their individual CSS/JS
     * handles (e.g. widget-countdown.min.css).  Without this, only
     * the Post-CSS (user customisations) would load and widget base
     * styles would be missing.
     *
     * @param \WP_Post $template Template post being rendered.
     * @return void
     * @since 2.0.0
     */
    private function initializeElementorFrontend(\WP_Post $template): void
    {
        try {
            $frontend = \Elementor\Plugin::instance()->frontend;

            // Register base Elementor style/script handles
            if (method_exists($frontend, 'register_styles')) {
                $frontend->register_styles();
            }
            if (method_exists($frontend, 'register_scripts')) {
                $frontend->register_scripts();
            }

            // Enqueue widget-specific CSS/JS for this template.
            // Must run with post context so Elementor reads the template's
            // _elementor_data meta and enqueues each widget's stylesheet.
            Helper::withPostContext($template, function () use ($frontend) {
                if (method_exists($frontend, 'enqueue_styles')) {
                    $frontend->enqueue_styles();
                }
                if (method_exists($frontend, 'enqueue_scripts')) {
                    $frontend->enqueue_scripts();
                }
            }, false);

            // Also enqueue widget-level styles via the widgets manager
            $widgets_manager = \Elementor\Plugin::instance()->widgets_manager;
            if (method_exists($widgets_manager, 'enqueue_widgets_styles')) {
                $widgets_manager->enqueue_widgets_styles();
            }

        } catch (\Exception $e) {
            Helper::log('Elementor frontend init failed for template ' . $template->ID . ': ' . $e->getMessage());
        }
    }
}
