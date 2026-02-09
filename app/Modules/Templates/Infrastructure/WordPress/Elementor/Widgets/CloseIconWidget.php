<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Icons_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

defined('ABSPATH') || exit;

/**
 * Class CloseIconWidget
 *
 * Renders a close icon for notifications with customizable style.
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Widgets
 * @author Hossein <hossein@notifal.com>
 */
class CloseIconWidget extends BaseWidget
{
    /**
     * Get widget unique name.
     *
     * @since 2.0.0
     * @return string
     */
    public function get_name(): string
    {
        return 'notifal-close-icon';
    }

    /**
     * Get widget title.
     *
     * @since 2.0.0
     * @return string
     */
    public function get_title(): string
    {
        return esc_html__('Close Icon', 'notifal');
    }

    /**
     * Get widget icon.
     *
     * @since 2.0.0
     * @return string
     */
    public function get_icon(): string
    {
        return 'eicon-close';
    }


    /**
     * Register widget controls.
     *
     * @since 2.0.0
     * @return void
     */
    protected function register_controls(): void
    {
        // =====================================================================
        // CONTENT TAB - Basic icon configuration
        // =====================================================================

        /**
         * Icon Content Section
         * Contains icon selection and alignment controls
         */
        $this->start_controls_section('section_icon', [
            'label' => esc_html__('Icon', 'notifal'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        // Icon selection control - allows users to choose from Elementor's icon library
        $this->add_control('selected_icon', [
            'label'   => esc_html__('Icon', 'notifal'),
            'type'    => Controls_Manager::ICONS,
            'default' => [
                'value'   => 'eicon-close',
                'library' => 'eicons',
            ],
        ]);

        // Icon alignment control - positions the icon within its container
        $this->add_responsive_control('align', [
            'label' => esc_html__('Alignment', 'notifal'),
            'type'  => Controls_Manager::CHOOSE,
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
            ],
            'default' => 'flex-end',
            'selectors' => [
                '{{WRAPPER}} .notifal-close-icon-wrapper' => 'justify-content: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();

        // =====================================================================
        // STYLE TAB - Visual customization options
        // =====================================================================

        /**
         * Icon Style Section
         * Contains typography, colors, background, and spacing controls
         */
        $this->start_controls_section('section_style_icon', [
            'label' => esc_html__('Icon', 'notifal'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        // Icon size control
        $this->add_responsive_control('size', [
            'label' => esc_html__('Size', 'notifal'),
            'type' => Controls_Manager::SLIDER,
            'range' => [
                'px' => [
                    'min' => 10,
                    'max' => 100,
                ],
            ],
            'default' => [
                'size' => 30,
                'unit' => 'px',
            ],
            'selectors' => [
                '{{WRAPPER}} .notifal-close' => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .notifal-close svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        // Icon colors with tabs for normal/hover states
        $this->start_controls_tabs('tabs_icon_colors');

        // Normal state tab
        $this->start_controls_tab(
            'tab_icon_normal',
            ['label' => esc_html__('Normal', 'notifal')]
        );

        // Primary color (backward compatibility: maps to old 'primary_color')
        $this->add_control('primary_color', [
            'label' => esc_html__('Color', 'notifal'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .notifal-close' => 'color: {{VALUE}};',
                '{{WRAPPER}} .notifal-close svg' => 'fill: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();

        // Hover state tab
        $this->start_controls_tab(
            'tab_icon_hover',
            ['label' => esc_html__('Hover', 'notifal')]
        );

        // Hover color (backward compatibility: maps to old 'hover_color')
        $this->add_control('hover_color', [
            'label' => esc_html__('Color', 'notifal'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .notifal-close:hover, {{WRAPPER}} .notifal-close:focus' => 'color: {{VALUE}};',
                '{{WRAPPER}} .notifal-close:hover svg, {{WRAPPER}} .notifal-close:focus svg' => 'fill: {{VALUE}};',
            ],
        ]);

        // Hover animation control
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

        // Background group control
        $this->add_group_control(Group_Control_Background::get_type(), [
            'name' => 'background',
            'types' => ['classic', 'gradient'],
            'selector' => '{{WRAPPER}} .notifal-close',
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
            'selector' => '{{WRAPPER}} .notifal-close',
        ]);

        // Border radius control
        $this->add_responsive_control('border_radius', [
            'label' => esc_html__('Border Radius', 'notifal'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors' => [
                '{{WRAPPER}} .notifal-close' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        // Box shadow group control
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'box_shadow',
            'selector' => '{{WRAPPER}} .notifal-close',
        ]);

        // Spacing section separator
        $this->add_control('spacing_section', [
            'label' => esc_html__('Spacing', 'notifal'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        // Icon padding control
        $this->add_responsive_control('icon_padding', [
            'label' => esc_html__('Padding', 'notifal'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors' => [
                '{{WRAPPER}} .notifal-close' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    /**
     * Render widget output on the frontend.
     *
     * @since 2.0.0
     * @return void
     */
    protected function render(): void
    {
        // Get widget settings for display
        $settings = $this->get_settings_for_display();

        // =====================================================================
        // BUILD ICON CLASSES
        // =====================================================================

        // Base CSS classes for the icon element
        $icon_classes = [
            'notifal-close',
        ];

        // Add hover animation class if specified (exclude 'none' to allow disabling animations)
        if (!empty($settings['hover_animation']) && $settings['hover_animation'] !== 'none') {
            $icon_classes[] = 'elementor-animation-' . $settings['hover_animation'];
        }

        // =====================================================================
        // BUILD WRAPPER CLASSES
        // =====================================================================

        // Base wrapper classes for proper alignment and layout
        $wrapper_classes = [
            'notifal-close-icon-wrapper',
            'notifal-flex',
            'notifal-full-width', // Ensures wrapper takes full width for alignment
        ];

        // Handle alignment classes based on setting
        $align = $settings['align'] ?? 'flex-end';
        switch ($align) {
            case 'flex-start':
                $wrapper_classes[] = 'notifal-justify-start';
                break;
            case 'center':
                $wrapper_classes[] = 'notifal-justify-center';
                break;
            case 'flex-end':
            default:
                $wrapper_classes[] = 'notifal-justify-end';
                break;
        }

        // =====================================================================
        // RENDER HTML OUTPUT
        // =====================================================================

        ?>
        <div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>">
            <span class="<?php echo esc_attr(implode(' ', $icon_classes)); ?>"
                  role="button"
                  tabindex="0"
                  aria-label="<?php echo esc_attr__('Close Notification', 'notifal'); ?>">
                <?php
                // Render the selected icon with proper attributes
                Icons_Manager::render_icon($settings['selected_icon'], ['aria-hidden' => 'true']);
                ?>
            </span>
        </div>
        <?php
    }
}
