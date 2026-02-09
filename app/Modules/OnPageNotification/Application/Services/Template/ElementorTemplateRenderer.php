<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Template;

use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Widgets\WidgetsRegistrar;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Handles Elementor-specific template rendering for frontend notifications.
 *
 * This renderer is responsible for rendering Elementor templates with proper
 * widget registration and asset loading for frontend notification display.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ElementorTemplateRenderer extends AbstractTemplateRenderer
{
    /**
     * Expected Notifal Elementor widget names that should be registered.
     *
     * @var array
     */
    private const NOTIFAL_WIDGETS = [
        'notifal-close-icon',
        'notifal-action-button',
        'notifal-product-image'
    ];

    /**
     * Render the actual Elementor template content.
     *
     * Prepares Elementor frontend system and renders the template content.
     * Elementor's dynamic widget loading system handles initialization automatically.
     *
     * @param \WP_Post $template Template post
     * @param array $frontendContext Frontend context
     * @return string Rendered content
     * @since 2.0.0
     */
    protected function renderContent(\WP_Post $template, array $frontendContext): string
    {
        if (!PluginDetector::isElementorActive()) {
            throw new \Exception('Elementor plugin is not active or available');
        }

        $this->ensureElementorWidgetsRegistered($template);
        $this->initializeElementorFrontend($template);

        return \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($template->ID);
    }

    /**
     * Get Elementor-specific assets for the template.
     *
     * @param \WP_Post $template Template post
     * @return array Asset URLs or inline content
     * @since 2.0.0
     */
    protected function getAssets(\WP_Post $template): array
    {
        return $this->getElementorAssets($template->ID);
    }

    /**
     * Get the builder type identifier.
     *
     * @return string Builder type string
     * @since 2.0.0
     */
    protected function getBuilderType(): string
    {
        return 'elementor';
    }

    /**
     * Get Elementor assets for a template.
     *
     * Loads template-specific CSS and JS. Catches Throwable so Elementor CSS
     * generation errors on some sites do not fatal; falls back to base Elementor CSS.
     *
     * @param int $templateId Template ID
     * @return array Asset URLs
     * @since 2.0.0
     */
    private function getElementorAssets(int $templateId): array
    {
        $assets = [];

        try {
            if (class_exists('\Elementor\Core\Files\CSS\Post')) {
                $cssFile = new \Elementor\Core\Files\CSS\Post($templateId);
                $cssContent = $cssFile->get_content();

                if (!empty($cssContent)) {
                    $assets['css'] = [$cssContent];
                }
            }

            // Fallback to basic Elementor CSS if no template-specific CSS
            if (empty($assets['css'])) {
                $elementorCssUrl = plugins_url('assets/css/frontend.min.css', ELEMENTOR__FILE__);
                if (file_exists(ELEMENTOR_PATH . 'assets/css/frontend.min.css')) {
                    $assets['css'] = [$elementorCssUrl];
                }
            }

            // Load Elementor JavaScript assets for dynamic widgets
            $elementorJsAssets = $this->getElementorJavaScriptAssets($templateId);
            if (!empty($elementorJsAssets)) {
                $assets['js'] = $elementorJsAssets;
            }

        } catch (\Throwable $e) {
            $elementorCssUrl = plugins_url('assets/css/frontend.min.css', ELEMENTOR__FILE__);
            $assets['css'] = [$elementorCssUrl];
            Helper::log('Elementor assets failed for template ' . $templateId . ': ' . $e->getMessage());
        }

        return $assets;
    }

    /**
     * Get Elementor JavaScript assets required for dynamic widgets.
     *
     * Loads essential Elementor JavaScript files for frontend functionality,
     * including the dynamic widget handler system.
     *
     * @param int $templateId Template ID
     * @return array JavaScript asset URLs
     * @since 2.0.0
     */
    private function getElementorJavaScriptAssets(int $templateId): array
    {
        $jsAssets = [];

        try {
            // Load Elementor frontend JavaScript
            $elementorJsUrl = plugins_url('assets/js/frontend.min.js', ELEMENTOR__FILE__);
            if (file_exists(ELEMENTOR_PATH . 'assets/js/frontend.min.js')) {
                $jsAssets[] = $elementorJsUrl;
            }

            // Also load Elementor webpack runtime and modules for dynamic imports
            $webpackRuntimeUrl = plugins_url('assets/js/webpack.runtime.min.js', ELEMENTOR__FILE__);
            if (file_exists(ELEMENTOR_PATH . 'assets/js/webpack.runtime.min.js')) {
                $jsAssets[] = $webpackRuntimeUrl;
            }

            $modulesUrl = plugins_url('assets/js/frontend-modules.min.js', ELEMENTOR__FILE__);
            if (file_exists(ELEMENTOR_PATH . 'assets/js/frontend-modules.min.js')) {
                $jsAssets[] = $modulesUrl;
            }

            // Load Elementor elements handlers for dynamic widget loading
            $handlersUrl = plugins_url('assets/js/elements-handlers.min.js', ELEMENTOR__FILE__);
            if (file_exists(ELEMENTOR_PATH . 'assets/js/elements-handlers.min.js')) {
                $jsAssets[] = $handlersUrl;
            }

            // Load Elementor frontend modules if available (Elementor Pro features)
            if (defined('ELEMENTOR_PRO_VERSION') && defined('ELEMENTOR_PRO_PATH')) {
                $elementorModulesUrl = plugins_url('assets/js/frontend-modules.min.js', ELEMENTOR_PRO__FILE__);
                if (file_exists(ELEMENTOR_PRO_PATH . 'assets/js/frontend-modules.min.js')) {
                    $jsAssets[] = $elementorModulesUrl;
                }
            }

        } catch (\Exception $e) {
            Helper::log('Elementor JS assets failed for template ' . $templateId . ': ' . $e->getMessage());
        }

        return $jsAssets;
    }


    /**
     * Ensure Notifal Elementor widgets are registered for frontend rendering.
     *
     * This method checks if required Notifal widgets are registered and registers
     * them if missing. This ensures widgets are available during frontend rendering.
     *
     * @param \WP_Post $template Template post being rendered
     * @return void
     * @since 2.0.0
     */
    private function ensureElementorWidgetsRegistered(\WP_Post $template): void
    {
        if ($template->post_type !== 'notifal_template') {
            return;
        }

        try {
            $widgets_manager = \Elementor\Plugin::instance()->widgets_manager;
            $registered_widgets = $widgets_manager->get_widget_types();

            $missing_widgets = array_filter(
                self::NOTIFAL_WIDGETS,
                fn($widget_name) => !isset($registered_widgets[$widget_name])
            );

            if (empty($missing_widgets)) {
                return;
            }

            // Set temporary post context for widget registration (without setup_postdata)
            Helper::withPostContext($template, function() {
                // Register missing Notifal widgets
                if (class_exists(WidgetsRegistrar::class)) {
                    WidgetsRegistrar::register_widgets();
                }
            }, false);

        } catch (\Exception $e) {
            // Log widget registration failure but continue rendering
            Helper::log('Elementor widget registration failed for template ' . $template->ID . ': ' . $e->getMessage());
        }
    }

    /**
     * Initialize Elementor frontend system for proper widget functionality.
     *
     * Ensures Elementor recognizes there's Elementor content on the page and loads
     * the necessary frontend scripts and handlers.
     *
     * @param \WP_Post $template Template post being rendered
     * @return void
     * @since 2.0.0
     */
    private function initializeElementorFrontend(\WP_Post $template): void
    {
        try {
            $elementor_frontend = \Elementor\Plugin::instance()->frontend;

            // Force Elementor to recognize that there's Elementor content on this page
            // This ensures Elementor loads its frontend scripts and handlers
            if (!has_action('wp_enqueue_scripts', [$elementor_frontend, 'enqueue_scripts'])) {
                add_action('wp_enqueue_scripts', [$elementor_frontend, 'enqueue_scripts'], 5);
            }

        } catch (\Exception $e) {
            Helper::log('Elementor frontend init failed for template ' . $template->ID . ': ' . $e->getMessage());
        }
    }


}
