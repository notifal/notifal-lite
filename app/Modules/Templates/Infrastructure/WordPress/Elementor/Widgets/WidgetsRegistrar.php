<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Widgets;

use Elementor\Elements_Manager;
use Elementor\Plugin;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WidgetsRegistrar
 * Registers the Notifal widget category and individual widgets in Elementor.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class WidgetsRegistrar {

    /**
     * Hook into Elementor.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register(): void {
        add_action('elementor/elements/categories_registered', [self::class, 'register_category']);
        add_action('elementor/widgets/widgets_registered', [self::class, 'register_widgets']);
    }

    /**
     * Register the Notifal widget category in Elementor.
     *
     * @since 2.0.0
     * @param Elements_Manager $elements_manager Elementor elements manager instance.
     * @return void
     */
    public static function register_category(Elements_Manager $elements_manager): void {
        global $post;

        if (! $post instanceof \WP_Post || $post->post_type !== 'notifal_template') {
            return;
        }

        $categories = $elements_manager->get_categories();

        // Avoid duplicate
        if (isset($categories['notifal'])) {
            return;
        }

        // Inject Notifal category at beginning
        $categories = ['notifal' => [
                'title' => __('Notifal', 'notifal'),
                'icon'  => 'fa fa-bell',
            ]] + $categories;

        // Overwrite categories via reflection (private prop!)
        $refObject = new \ReflectionObject($elements_manager);
        if ($refObject->hasProperty('categories')) {
            $prop = $refObject->getProperty('categories');
            $prop->setAccessible(true);
            $prop->setValue($elements_manager, $categories);
        }
    }

    /**
     * Register individual Notifal widgets.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register_widgets(): void
    {
        global $post;

        if (! $post instanceof \WP_Post || $post->post_type !== 'notifal_template') {
            return;
        }
        foreach (self::get_widgets() as $widget_class) {
            if (class_exists($widget_class)) {
                Plugin::instance()->widgets_manager->register(new $widget_class());
            }
        }

        /**
         * Fires after all Notifal Elementor widgets are registered.
         *
         * @since 2.0.0
         */
        do_action(ActionHooks::ELEMENTOR_WIDGETS_REGISTERED);
    }


    /**
     * Get the list of Notifal Elementor widget classes.
     *
     * @since 2.0.0
     * @return string[]
     */
    private static function get_widgets(): array
    {
        return [
            ProductImageWidget::class,
            CloseIconWidget::class,
            ActionButtonWidget::class, // Added ActionButtonWidget registration
        ];
    }
}
