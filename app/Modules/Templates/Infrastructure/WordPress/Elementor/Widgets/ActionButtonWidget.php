<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Icons_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

defined('ABSPATH') || exit;

/**
 * Class ActionButtonWidget
 *
 * Renders a customizable action button for notifications with tracking support.
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Widgets
 * @author Hossein <hossein@notifal.com>
 */
class ActionButtonWidget extends BaseWidget
{
    use Traits\WidgetContextTrait;
    /**
     * Get widget unique name.
     *
     * @since 2.0.0
     */
    public function get_name(): string
    {
        return 'notifal-action-button';
    }

    /**
     * Get widget display title.
     *
     * @since 2.0.0
     */
    public function get_title(): string
    {
        return esc_html__('Action Button', 'notifal');
    }

    /**
     * Get widget icon.
     *
     * @since 2.0.0
     */
    public function get_icon(): string
    {
        return 'eicon-button';
    }


    /**
     * Register Elementor controls for Action Button.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    protected function register_controls(): void
    {
        // =====================================================================
        // CONTENT TAB
        // =====================================================================

        /**
         * Button Content Section
         * Contains button text, link settings, alignment, and icon controls
         */
        $this->start_controls_section('section_button', [
            'label' => esc_html__('Button', 'notifal'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        // Button text control
        $this->add_control('button_text', [
            'label' => esc_html__('Text', 'notifal'),
            'type' => Controls_Manager::TEXT,
            'default' => esc_html__('Buy Now', 'notifal'),
            'placeholder' => esc_html__('Enter button text', 'notifal'),
            'dynamic' => ['active' => true],
        ]);

        // Link type selector
        $this->add_control('link_type', [
            'label' => esc_html__('Link Type', 'notifal'),
            'type' => Controls_Manager::SELECT,
            'options' => [
                'product' => esc_html__('Post Link', 'notifal'),
                'copy'    => esc_html__('Copy Text', 'notifal'),
                'custom'  => esc_html__('Custom Link', 'notifal'),
                'close'   => esc_html__('Close Notification', 'notifal'),
                'custom-trigger' => esc_html__('Custom Trigger', 'notifal'),
            ],
            'default' => 'product',
        ]);

        // Copy text field (conditional)
        $this->add_control('copy_text', [
            'label' => esc_html__('Copy Text', 'notifal'),
            'type' => Controls_Manager::TEXT,
            'placeholder' => esc_html__('Enter text to copy', 'notifal'),
            'dynamic' => ['active' => true],
            'condition' => ['link_type' => 'copy'],
        ]);

        // Custom link field (conditional)
        $this->add_control('custom_link', [
            'label' => esc_html__('Custom Link', 'notifal'),
            'type' => Controls_Manager::URL,
            'placeholder' => 'https://example.com',
            'show_external' => true,
            'dynamic' => ['active' => true],
            'condition' => ['link_type' => 'custom'],
        ]);

        // Custom trigger hide elements field (conditional)
        $this->add_control('hide_elements', [
            'label' => esc_html__('Hide Elements', 'notifal'),
            'type' => Controls_Manager::TEXT,
            'placeholder' => esc_html__('e.g. #idname,.className', 'notifal'),
            'description' => esc_html__('Comma-separated CSS selectors to hide when button is clicked (e.g. #test,.className)', 'notifal'),
            'dynamic' => ['active' => true],
            'condition' => ['link_type' => 'custom-trigger'],
        ]);

        // Custom trigger show elements field (conditional)
        $this->add_control('show_elements', [
            'label' => esc_html__('Show Elements', 'notifal'),
            'type' => Controls_Manager::TEXT,
            'placeholder' => esc_html__('e.g. #idname,.className', 'notifal'),
            'description' => esc_html__('Comma-separated CSS selectors to show when button is clicked (e.g. #test,.className)', 'notifal'),
            'dynamic' => ['active' => true],
            'condition' => ['link_type' => 'custom-trigger'],
        ]);

        // Button alignment
        $this->add_responsive_control('align', [
            'label' => esc_html__('Alignment', 'notifal'),
            'type' => Controls_Manager::CHOOSE,
            'options' => [
                'flex-start' => [
                    'title' => esc_html__('Left', 'notifal'),
                    'icon' => 'eicon-text-align-left',
                ],
                'center' => [
                    'title' => esc_html__('Center', 'notifal'),
                    'icon' => 'eicon-text-align-center',
                ],
                'flex-end' => [
                    'title' => esc_html__('Right', 'notifal'),
                    'icon' => 'eicon-text-align-right',
                ],
                'stretch' => [
                    'title' => esc_html__('Justified', 'notifal'),
                    'icon' => 'eicon-text-align-justify',
                ],
            ],
            'default' => 'center',
            'selectors' => [
                '{{WRAPPER}} .notifal-action-button-wrapper' => 'justify-content: {{VALUE}};',
            ],
            'condition' => [
                'button_width!' => 'stretch', // Hide when full width is selected
            ],
        ]);

        // Full width button option
        $this->add_control('button_width', [
            'label' => esc_html__('Width', 'notifal'),
            'type' => Controls_Manager::SELECT,
            'default' => 'inline',
            'options' => [
                'inline' => esc_html__('Inline', 'notifal'),
                'stretch' => esc_html__('Full Width', 'notifal'),
            ],
            'selectors' => [
                '{{WRAPPER}} .notifal-action-button' => '{{VALUE}}',
            ],
            'selectors_dictionary' => [
                'inline' => '',
                'stretch' => 'width: 100%; display: flex; justify-content: center; align-items: center;',
            ],
        ]);

        // Icon section separator
        $this->add_control('icon_section', [
            'label' => esc_html__('Icon', 'notifal'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        // Enable icon switcher
        $this->add_control('need_icon', [
            'label' => esc_html__('Add Icon', 'notifal'),
            'type' => Controls_Manager::SWITCHER,
            'default' => '',
            'return_value' => 'yes',
        ]);

        // Icon picker (conditional)
        $this->add_control('selected_icon', [
            'label' => esc_html__('Icon', 'notifal'),
            'type' => Controls_Manager::ICONS,
            'default' => [
                'value' => 'fas fa-shopping-cart',
                'library' => 'fa-solid',
            ],
            'condition' => ['need_icon' => 'yes'],
        ]);

        // Icon position selector (conditional)
        $this->add_control('icon_position', [
            'label' => esc_html__('Icon Position', 'notifal'),
            'type' => Controls_Manager::CHOOSE,
            'default' => 'left',
            'options' => [
                'left' => [
                    'title' => esc_html__('Left', 'notifal'),
                    'icon' => 'eicon-h-align-left',
                ],
                'right' => [
                    'title' => esc_html__('Right', 'notifal'),
                    'icon' => 'eicon-h-align-right',
                ],
            ],
            'condition' => [
                'need_icon' => 'yes',
                'selected_icon[value]!' => '',
            ],
        ]);

        $this->end_controls_section();

        // =====================================================================
        // STYLE TAB
        // =====================================================================


        /**
         * Button Style Section
         * Contains typography, colors, background, and spacing controls
         */
        $this->start_controls_section('section_style', [
            'label' => esc_html__('Button', 'notifal'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        // Typography group control
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'typography',
            'selector' => '{{WRAPPER}} .notifal-action-button',
        ]);

        // Text color with hover toggle
        $this->start_controls_tabs('tabs_button_colors');

        // Normal state tab
        $this->start_controls_tab(
            'tab_button_normal',
            ['label' => esc_html__('Normal', 'notifal')]
        );

        $this->add_control('text_color', [
            'label' => esc_html__('Text Color', 'notifal'),
            'type' => Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => [
                '{{WRAPPER}} .notifal-action-button' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();

        // Hover state tab
        $this->start_controls_tab(
            'tab_button_hover',
            ['label' => esc_html__('Hover', 'notifal')]
        );

        $this->add_control('hover_text_color', [
            'label' => esc_html__('Text Color', 'notifal'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .notifal-action-button:hover, {{WRAPPER}} .notifal-action-button:focus' => 'color: {{VALUE}};',
            ],
        ]);

        // Hover animation
        $this->add_control('hover_animation', [
            'label' => esc_html__('Hover Animation', 'notifal'),
            'type' => Controls_Manager::HOVER_ANIMATION,
        ]);

        $this->end_controls_tab();
        $this->end_controls_tabs();

        // Background section separator
        $this->add_control('background_section', [
            'label' => esc_html__('Background', 'notifal'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        // Background group control (supports gradients, images, etc.)
        $this->add_group_control(Group_Control_Background::get_type(), [
            'name' => 'button_background',
            'types' => ['classic', 'gradient'],
            'selector' => '{{WRAPPER}} .notifal-action-button',
            'fields_options' => [
                'background' => [
                    'default' => 'classic',
                ],
                'color' => [
                    'default' => '#7e2bd2',
                ],
                'color_b' => [
                    'default' => '#651bb0',
                ],
            ],
        ]);

        // Border section separator
        $this->add_control('border_section', [
            'label' => esc_html__('Border', 'notifal'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        // Border group control
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'border',
            'selector' => '{{WRAPPER}} .notifal-action-button',
        ]);

        // Border radius
        $this->add_responsive_control('border_radius', [
            'label' => esc_html__('Border Radius', 'notifal'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'default' => [
                'top' => 8,
                'right' => 8,
                'bottom' => 8,
                'left' => 8,
                'unit' => 'px',
            ],
            'selectors' => [
                '{{WRAPPER}} .notifal-action-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        // Box shadow
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'box_shadow',
            'selector' => '{{WRAPPER}} .notifal-action-button',
        ]);

        // Spacing section separator
        $this->add_control('spacing_section', [
            'label' => esc_html__('Spacing', 'notifal'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        // Button padding
        $this->add_responsive_control('button_padding', [
            'label' => esc_html__('Padding', 'notifal'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'default' => [
                'top' => 10,
                'right' => 24,
                'bottom' => 10,
                'left' => 24,
                'unit' => 'px',
            ],
            'selectors' => [
                '{{WRAPPER}} .notifal-action-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        // =====================================================================
        // ICON STYLE SECTION (conditional)
        // =====================================================================

        /**
         * Icon Style Section
         * Only shown when icon is enabled
         */
        $this->start_controls_section('section_icon_style', [
            'label' => esc_html__('Icon', 'notifal'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['need_icon' => 'yes'],
        ]);

        // Icon size
        $this->add_responsive_control('icon_size', [
            'label' => esc_html__('Size', 'notifal'),
            'type' => Controls_Manager::SLIDER,
            'range' => [
                'px' => [
                    'min' => 10,
                    'max' => 48,
                ],
            ],
            'default' => [
                'size' => 18,
                'unit' => 'px',
            ],
            'selectors' => [
                '{{WRAPPER}} .notifal-action-button .elementor-icon' => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .notifal-action-button .elementor-icon i' => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .notifal-action-button .elementor-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        // Icon color with hover toggle
        $this->start_controls_tabs('tabs_icon_colors');

        // Normal state
        $this->start_controls_tab(
            'tab_icon_normal',
            ['label' => esc_html__('Normal', 'notifal')]
        );

        $this->add_control('icon_color', [
            'label' => esc_html__('Color', 'notifal'),
            'type' => Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => [
                '{{WRAPPER}} .notifal-action-button .elementor-icon' => 'color: {{VALUE}};',
                '{{WRAPPER}} .notifal-action-button .elementor-icon i' => 'color: {{VALUE}};',
                '{{WRAPPER}} .notifal-action-button .elementor-icon svg' => 'fill: {{VALUE}}; color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();

        // Hover state
        $this->start_controls_tab(
            'tab_icon_hover',
            ['label' => esc_html__('Hover', 'notifal')]
        );

        $this->add_control('hover_icon_color', [
            'label' => esc_html__('Color', 'notifal'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .notifal-action-button:hover .elementor-icon' => 'color: {{VALUE}};',
                '{{WRAPPER}} .notifal-action-button:hover .elementor-icon i' => 'color: {{VALUE}};',
                '{{WRAPPER}} .notifal-action-button:hover .elementor-icon svg' => 'fill: {{VALUE}}; color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();
        $this->end_controls_tabs();

        // Icon spacing
        $this->add_responsive_control('icon_spacing', [
            'label' => esc_html__('Spacing', 'notifal'),
            'type' => Controls_Manager::SLIDER,
            'range' => [
                'px' => [
                    'min' => 0,
                    'max' => 50,
                ],
            ],
            'default' => [
                'size' => 8,
                'unit' => 'px',
            ],
            'selectors' => [
                '{{WRAPPER}} .notifal-action-button .elementor-icon' => 'margin-right: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .notifal-action-button .notifal-action-button-text + .elementor-icon' => 'margin-left: {{SIZE}}{{UNIT}}; margin-right: 0;',
            ],
        ]);

        $this->end_controls_section();
    }


    /**
     * Render widget HTML output following Elementor standards.
     *
     * This method renders the action button with proper accessibility,
     * backward compatibility for existing widget instances, and support
     * for the new full-width button option.
     *
     * @since 2.0.0
     * @return void
     */
    protected function render(): void
    {
        // Get widget settings
        $settings = $this->get_settings_for_display();

        // Get context data using the trait for dynamic content
        $contextData = $this->resolveContextData();

        // =====================================================================
        // BUILD BUTTON CLASSES
        // =====================================================================

        // Base CSS classes for the button element
        $button_classes = [
            'notifal-action-button',
            'notifal-button',
            'notifal-track-click',
            'notifal-flex',
            'notifal-align-center',
        ];

        // Add hover animation class if specified
        if (!empty($settings['hover_animation'])) {
            $button_classes[] = 'elementor-animation-' . $settings['hover_animation'];
        }

        // =====================================================================
        // BUILD BUTTON ATTRIBUTES
        // =====================================================================

        // Base attributes for the button link
        $button_attrs = [
            'class' => implode(' ', $button_classes),
            'aria-label' => esc_attr__('Notification Action Button', 'notifal'),
        ];

        // Handle different link types
        switch ($settings['link_type']) {
            case 'custom':
                // Custom URL link
                if (!empty($settings['custom_link']['url'])) {
                    $button_attrs['href'] = esc_url($settings['custom_link']['url']);

                    // Add target="_blank" for external links
                    if (!empty($settings['custom_link']['is_external'])) {
                        $button_attrs['target'] = '_blank';
                    }

                    // Add rel="nofollow" if specified
                    if (!empty($settings['custom_link']['nofollow'])) {
                        $button_attrs['rel'] = 'nofollow';
                    }
                } else {
                    $button_attrs['href'] = '#';
                }
                break;

            case 'copy':
                // Copy text functionality
                $button_attrs['href'] = '#';
                $button_attrs['data-copy-text'] = esc_attr($settings['copy_text']);
                $button_attrs['data-action'] = 'copy';
                break;

            case 'close':
                // Close notification functionality
                $button_attrs['href'] = '#';
                $button_attrs['data-action'] = 'close';
                break;

            case 'custom-trigger':
                // Custom trigger functionality
                $button_attrs['href'] = '#';
                $button_attrs['data-action'] = 'custom-trigger';

                // Add hide elements data attribute if provided
                if (!empty($settings['hide_elements'])) {
                    $button_attrs['data-hide-elements'] = esc_attr(sanitize_text_field($settings['hide_elements']));
                }

                // Add show elements data attribute if provided
                if (!empty($settings['show_elements'])) {
                    $button_attrs['data-show-elements'] = esc_attr(sanitize_text_field($settings['show_elements']));
                }
                break;

            case 'product':
            default:
                // Post/product link (default)
                $button_attrs['href'] = '#';
                $button_attrs['data-action'] = 'post-link';

                // Add context data for JavaScript handling
                if (!empty($contextData['url'])) {
                    $button_attrs['data-post-url'] = esc_url($contextData['url']);
                }

                // Legacy product data for backward compatibility
                $data = $contextData['data'] ?? null;
                if ($data && method_exists($data, 'getId') && method_exists($data, 'getLink')) {
                    $button_attrs['data-product-id'] = $data->getId();
                    $button_attrs['data-product-url'] = $data->getLink();
                }
                break;
        }

        // =====================================================================
        // BUILD WRAPPER CLASSES
        // =====================================================================

        // Base wrapper classes
        $wrapper_classes = [
            'notifal-action-button-wrapper',
            'notifal-flex',
            'notifal-full-width',
        ];

        // Handle alignment including new stretch option for full-width
        $align = $settings['align'] ?? 'center';

        switch ($align) {
            case 'flex-start':
                $wrapper_classes[] = 'notifal-justify-start';
                break;
            case 'flex-end':
                $wrapper_classes[] = 'notifal-justify-end';
                break;
            case 'stretch':
                // Justified alignment - button is justified within its container
                $wrapper_classes[] = 'notifal-justify-stretch';
                break;
            case 'center':
            default:
                $wrapper_classes[] = 'notifal-justify-center';
                break;
        }

        // Full width button styling is now handled via Elementor selectors_dictionary

        // =====================================================================
        // RENDER HTML OUTPUT
        // =====================================================================

        ?>
        <div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>">
            <a <?php
                // Output all button attributes
                foreach ($button_attrs as $attr => $value) {
                    echo esc_attr($attr) . '="' . esc_attr($value) . '" ';
                }
            ?>>
                <?php
                // Render left icon if enabled
                if (!empty($settings['need_icon']) &&
                    $settings['need_icon'] === 'yes' &&
                    !empty($settings['selected_icon']['value']) &&
                    $settings['icon_position'] !== 'right') {
                    echo '<span class="elementor-icon" style="display:inline-flex;align-items:center;">';
                    \Elementor\Icons_Manager::render_icon($settings['selected_icon'], ['aria-hidden' => 'true']);
                    echo '</span>';
                }
                ?>

                <span class="notifal-action-button-text" style="display:inline-block;vertical-align:middle;">
                    <?php echo esc_html($settings['button_text']); ?>
                </span>

                <?php
                // Render right icon if enabled
                if (!empty($settings['need_icon']) &&
                    $settings['need_icon'] === 'yes' &&
                    !empty($settings['selected_icon']['value']) &&
                    $settings['icon_position'] === 'right') {
                    echo '<span class="elementor-icon" style="display:inline-flex;align-items:center;">';
                    \Elementor\Icons_Manager::render_icon($settings['selected_icon'], ['aria-hidden' => 'true']);
                    echo '</span>';
                }
                ?>
            </a>
        </div>
        <?php
    }
}
