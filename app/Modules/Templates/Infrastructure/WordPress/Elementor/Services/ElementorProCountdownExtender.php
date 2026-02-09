<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Services;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\Templates\Application\Services\CountdownDateResolver;
use Notifal\Modules\OnPageNotification\Application\Services\Utility\WidgetContextProvider;

defined('ABSPATH') || exit;

/**
 * Class ElementorProCountdownExtender
 *
 * Extends Elementor Pro's Countdown widget to support dynamic product sale dates.
 * Adds context-aware functionality to use WooCommerce product sale end dates.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ElementorProCountdownExtender
{
    /**
     * Register hooks for extending Elementor Pro Countdown widget.
     *
     * @since 2.0.0
     * @return void
     */
    public static function register()
    {
        // Only proceed if Elementor Pro is active
        if (!self::isElementorProActive()) {
            return;
        }

        // Add custom control to Countdown widget (only on notifal_template post type)
        add_action('elementor/element/countdown/section_countdown/before_section_end', [self::class, 'addCustomControls'], 10, 2);
        
        // Modify widget render content to inject dynamic date
        add_filter('elementor/widget/render_content', [self::class, 'modifyCountdownRenderContent'], 10, 2);
    }

    /**
     * Check if Elementor Pro is active.
     *
     * @since 2.0.0
     * @return bool True if Elementor Pro is active
     */
    private static function isElementorProActive()
    {
        return defined('ELEMENTOR_PRO_VERSION') && PluginDetector::isElementorActive();
    }

    /**
     * Add custom controls to Elementor Pro Countdown widget.
     *
     * Injects a new control section with "Use Product Sale Date" option
     * into the existing Countdown widget settings.
     *
     * @since 2.0.0
     * @param Widget_Base $element The widget instance
     * @param array $args Section arguments
     * @return void
     */
    public static function addCustomControls(Widget_Base $element, $args)
    {
        global $post;

        // Only add controls on notifal_template post type
        if (!$post instanceof \WP_Post || $post->post_type !== 'notifal_template') {
            return;
        }

        // Only add controls if WooCommerce is active
        if (!PluginDetector::isWooCommerceActive()) {
            return;
        }

        // Add heading for Notifal section
        $element->add_control(
            'notifal_countdown_heading',
            [
                'label' => esc_html__('Notifal Dynamic Date', 'notifal'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        // Add switcher control for using product sale date
        $element->add_control(
            'notifal_use_product_sale_date',
            [
                'label' => esc_html__('Use Product Sale Date', 'notifal'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'notifal'),
                'label_off' => esc_html__('No', 'notifal'),
                'return_value' => 'yes',
                'default' => '',
                'description' => esc_html__('Automatically use the product sale end date from notification context. Falls back to the due date above if no product or sale date is available.', 'notifal'),
                'condition' => [
                    'countdown_type' => 'due_date',
                ],
            ]
        );

        // Add informational note
        $element->add_control(
            'notifal_countdown_note',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => esc_html__('This feature works with Notifal notifications that have product context. The countdown will automatically display the sale end date of the product in the notification.', 'notifal'),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
                'condition' => [
                    'notifal_use_product_sale_date' => 'yes',
                    'countdown_type' => 'due_date',
                ],
            ]
        );
    }

    /**
     * Modify countdown widget render content.
     *
     * Intercepts the countdown widget render output to inject dynamic product sale date
     * when the "Use Product Sale Date" option is enabled.
     *
     * @since 2.0.0
     * @param string $content Widget HTML content
     * @param Widget_Base $widget The widget instance
     * @return string Modified HTML content
     */
    public static function modifyCountdownRenderContent($content, Widget_Base $widget)
    {
        // Only process countdown widgets
        if ($widget->get_name() !== 'countdown') {
            return $content;
        }

        // Get widget settings
        $settings = $widget->get_settings_for_display();

        // Check if our custom control is enabled
        if (empty($settings['notifal_use_product_sale_date']) || $settings['notifal_use_product_sale_date'] !== 'yes') {
            return $content;
        }

        // Check if countdown type is 'due_date'
        if (empty($settings['countdown_type']) || $settings['countdown_type'] !== 'due_date') {
            return $content;
        }

        // Get widget context at render time to ensure we have the correct product
        $context = self::getWidgetContext();

        // If no context, keep default behavior
        if (!$context) {
            return $content;
        }

        // Get the default due date from settings as fallback
        $defaultDate = isset($settings['due_date']) ? $settings['due_date'] : null;
        
        // Convert default date string to timestamp if needed
        if ($defaultDate && is_string($defaultDate)) {
            try {
                $wpTimezone = new \DateTimeZone(wp_timezone_string());
                $dateTime = new \DateTime($defaultDate, $wpTimezone);
                $defaultDate = $dateTime->getTimestamp();
            } catch (\Exception $e) {
                // Keep original value if parsing fails
                $defaultDate = null;
            }
        }

        // Resolve countdown date from product context
        // This ensures we use the SAME product that's being displayed in the notification
        $resolvedDate = CountdownDateResolver::resolveCountdownDate($context, $defaultDate);

        // If we got a valid timestamp, modify the HTML
        if ($resolvedDate !== null && $resolvedDate !== $defaultDate) {
            // Replace the data-date attribute value with our resolved date
            $content = preg_replace_callback(
                '/data-date=["\'](\d+)["\']/',
                function ($matches) use ($resolvedDate) {
                    return 'data-date="' . esc_attr($resolvedDate) . '"';
                },
                $content
            );

            // Add custom attributes to help with re-initialization after hide/show
            // This helps the countdown reinitialize when notification is re-triggered
            $content = preg_replace_callback(
                '/<div\s+class="elementor-countdown-wrapper"/',
                function ($matches) use ($resolvedDate) {
                    return $matches[0] . ' data-notifal-countdown="1" data-notifal-timestamp="' . esc_attr($resolvedDate) . '"';
                },
                $content
            );
        }

        return $content;
    }

    /**
     * Get the current widget context if available.
     *
     * @since 2.0.0
     * @return array|null Context data or null if not available
     */
    private static function getWidgetContext()
    {
        if (!class_exists(WidgetContextProvider::class)) {
            return null;
        }

        if (!WidgetContextProvider::isActive()) {
            return null;
        }

        return WidgetContextProvider::getContext();
    }

    /**
     * Check if widget context is currently active.
     *
     * @since 2.0.0
     * @return bool True if context is active, false otherwise
     */
    private static function isWidgetContextActive()
    {
        return class_exists(WidgetContextProvider::class) && WidgetContextProvider::isActive();
    }
}
