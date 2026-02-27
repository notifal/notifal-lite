<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Css_Filter;
use Notifal\Domain\Products\ProductFetcherInterface;
use Notifal\Infrastructure\WordPress\Media\ImageSizeService;
use Notifal\Infrastructure\WordPress\Support\ContentExtractor;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\Templates\Application\Services\FeaturedImageAutoSourceResolver;
use Notifal\Modules\Templates\Application\Services\FeaturedImageResolver;

defined('ABSPATH') || exit;

/**
 * Class ProductImageWidget
 * Elementor widget to display a featured image that adapts to notification context with styling options.
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Widgets
 * @author Hossein <hossein@notifal.com>
 */
class ProductImageWidget extends BaseWidget
{
    use Traits\WidgetContextTrait;

    /**
     * Get widget unique name.
     *
     * @since 2.0.0
     * @return string
     */
    public function get_name(): string {
        return 'notifal-featured-image';
    }

    /**
     * Get widget title.
     *
     * @since 2.0.0
     * @return string
     */
    public function get_title(): string {
        return esc_html__('Featured Image', 'notifal');
    }

    /**
     * Get widget icon.
     *
     * @since 2.0.0
     * @return string
     */
    public function get_icon() {
        return 'eicon-image';
    }


    /**
     * Register widget controls following Elementor standards.
     *
     * This method sets up all the controls for the Product Image widget with proper
     * tabbed interface for normal/hover states, comprehensive styling options,
     * and context-aware preview settings.
     *
     * @since 2.0.0
     * @return void
     */
    protected function register_controls(): void {
        // =====================================================================
        // CONTENT TAB - Image configuration and settings
        // =====================================================================

        /**
         * General Content Section
         * Contains image resolution, lazy loading, and preview source controls
         */
        $this->start_controls_section(
            'section_image_general',
            [
                'label' => __('General', 'notifal'),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        // Lazy loading control - improves page performance by loading images on demand
        $this->add_control(
            'image_lazy_load',
            [
                'label'   => __('Lazy Load', 'notifal'),
                'type'    => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'label_on'  => __('Yes', 'notifal'),
                'label_off' => __('No', 'notifal'),
            ]
        );

        // Image resolution control - selects WordPress image size for optimal loading
        $this->add_control(
            'image_resolution',
            [
                'label'   => __('Image Resolution', 'notifal'),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'large',
                'options' => ImageSizeService::getDropdownOptions(),
            ]
        );

        // Build dynamic options based on WooCommerce availability
        $preview_options = [
            'auto' => __('Auto (Priority Order)', 'notifal'),
            'post' => __('Post', 'notifal'),
            'page' => __('Page', 'notifal'),
        ];
        
        // Only add Product option if WooCommerce is active
        if (PluginDetector::isWooCommerceActive()) {
            $preview_options['product'] = __('Product', 'notifal');
        }
        
        $description = __('Auto detects from tags used in this template: product/order tags → product image, post tags → post image, page tags → page image, comment tags → product image (with WooCommerce) or post image.', 'notifal');

        $this->add_control(
            'preview_image_source',
            [
                'label'   => __('Preview Image Source', 'notifal'),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'auto',
                'options' => $preview_options,
                'description' => $description,
            ]
        );

        $this->end_controls_section();

        // =====================================================================
        // STYLE TAB - Visual customization options
        // =====================================================================

        /**
         * Image Style Section
         * Contains alignment, dimensions, colors, borders, shadows, and effects controls
         */
        $this->start_controls_section(
            'section_style_image',
            [
                'label' => esc_html__('Image', 'notifal'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        // Image alignment control - positions the image within its container
        $this->add_responsive_control('image_alignment', [
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
            ],
            'default' => 'center',
            'selectors' => [
                '{{WRAPPER}} .notifal-featured-image-wrapper' => 'justify-content: {{VALUE}};',
            ],
        ]);

        // Image opacity and effects with tabs for normal/hover states
        $this->start_controls_tabs('tabs_image_style');

        // Normal state tab - default appearance
        $this->start_controls_tab(
            'tab_image_normal',
            ['label' => esc_html__('Normal', 'notifal')]
        );

        // Opacity control for normal state
        $this->add_control('image_opacity', [
            'label' => esc_html__('Opacity', 'notifal'),
            'type' => Controls_Manager::SLIDER,
            'range' => [
                'px' => [
                    'min' => 0,
                    'max' => 1,
                    'step' => 0.1,
                ],
            ],
            'default' => ['size' => 1],
            'selectors' => [
                '{{WRAPPER}} .notifal-featured-image' => 'opacity: {{SIZE}};',
            ],
        ]);

        $this->end_controls_tab();

        // Hover state tab - appearance on mouse hover
        $this->start_controls_tab(
            'tab_image_hover',
            ['label' => esc_html__('Hover', 'notifal')]
        );

        // Opacity control for hover state
        $this->add_control('image_opacity_hover', [
            'label' => esc_html__('Opacity', 'notifal'),
            'type' => Controls_Manager::SLIDER,
            'range' => [
                'px' => [
                    'min' => 0,
                    'max' => 1,
                    'step' => 0.1,
                ],
            ],
            'selectors' => [
                '{{WRAPPER}} .notifal-featured-image:hover' => 'opacity: {{SIZE}};',
            ],
        ]);

        // Hover animation control
        $this->add_control('hover_animation', [
            'label' => esc_html__('Hover Animation', 'notifal'),
            'type' => Controls_Manager::HOVER_ANIMATION,
        ]);

        $this->end_controls_tab();
        $this->end_controls_tabs();

        // Dimensions section separator
        $this->add_control('dimensions_section', [
            'label' => esc_html__('Dimensions', 'notifal'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_responsive_control(
            'width',
            [
                'label' => esc_html__('Width', 'notifal'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%', 'vw', 'em', 'rem'],
                'range' => [
                    'px' => ['min' => 10, 'max' => 1920],
                    '%'  => ['min' => 1, 'max' => 100],
                    'vw' => ['min' => 1, 'max' => 100],
                    'em' => ['min' => 1, 'max' => 50],
                    'rem'=> ['min' => 1, 'max' => 50],
                ],
                'selectors' => [
                    '{{WRAPPER}} img' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'image_height',
            [
                'label' => __('Height', 'notifal'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%', 'vh', 'em', 'rem'],
                'range' => [
                    'px' => ['min' => 10, 'max' => 1920],
                    '%'  => ['min' => 1, 'max' => 100],
                    'vh' => ['min' => 1, 'max' => 100],
                    'em' => ['min' => 1, 'max' => 50],
                    'rem'=> ['min' => 1, 'max' => 50],
                ],
                'selectors' => [
                    '{{WRAPPER}} img' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        // Border section separator
        $this->add_control('border_section', [
            'label' => esc_html__('Border & Shadow', 'notifal'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'image_border',
                'selector' => '{{WRAPPER}} img',
            ]
        );

        $this->add_responsive_control(
            'image_border_radius',
            [
                'label' => esc_html__('Border Radius', 'notifal'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'image_box_shadow',
                'selector' => '{{WRAPPER}} img',
            ]
        );

        // Effects section separator
        $this->add_control('effects_section', [
            'label' => esc_html__('Filters & Effects', 'notifal'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(
            Group_Control_Css_Filter::get_type(),
            [
                'name' => 'css_filters',
                'selector' => '{{WRAPPER}} img',
            ]
        );

        $this->add_control(
            'force_transparent_bg',
            [
                'label'        => __('Force Transparent Background', 'notifal'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'description'  => __('Tries to make the image background transparent even if it has a solid color background.','notifal'),
                'label_on'     => __('Yes', 'notifal'),
                'label_off'    => __('No', 'notifal'),
                'return_value' => 'yes',
                'default'      => '',
            ]
        );

        // Blend mode control - allows images to blend with background
        $this->add_control('image_blend_mode', [
            'label' => esc_html__('Blend Mode', 'notifal'),
            'type' => Controls_Manager::SELECT,
            'options' => [
                '' => esc_html__('Normal', 'notifal'),
                'multiply' => esc_html__('Multiply', 'notifal'),
                'screen' => esc_html__('Screen', 'notifal'),
                'overlay' => esc_html__('Overlay', 'notifal'),
                'darken' => esc_html__('Darken', 'notifal'),
                'lighten' => esc_html__('Lighten', 'notifal'),
                'color-dodge' => esc_html__('Color Dodge', 'notifal'),
                'color-burn' => esc_html__('Color Burn', 'notifal'),
                'hard-light' => esc_html__('Hard Light', 'notifal'),
                'soft-light' => esc_html__('Soft Light', 'notifal'),
                'difference' => esc_html__('Difference', 'notifal'),
                'exclusion' => esc_html__('Exclusion', 'notifal'),
                'hue' => esc_html__('Hue', 'notifal'),
                'saturation' => esc_html__('Saturation', 'notifal'),
                'color' => esc_html__('Color', 'notifal'),
                'luminosity' => esc_html__('Luminosity', 'notifal'),
            ],
            'selectors' => [
                '{{WRAPPER}} .notifal-featured-image' => 'mix-blend-mode: {{VALUE}};',
            ],
        ]);

        // Object fit control - controls how image fits within its container
        $this->add_control(
            'image_object_fit',
            [
                'label' => __('Object Fit', 'notifal'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'cover',
                'options' => [
                    'fill'       => __('Fill', 'notifal'),
                    'contain'    => __('Contain', 'notifal'),
                    'cover'      => __('Cover', 'notifal'),
                    'none'       => __('None', 'notifal'),
                    'scale-down' => __('Scale Down', 'notifal'),
                ],
                'selectors' => [
                    '{{WRAPPER}} img' => 'object-fit: {{VALUE}};',
                ],
            ]
        );



        $this->end_controls_section();
    }

    /**
     * Render widget HTML output following Elementor standards.
     *
     * This method renders the featured image with proper accessibility,
     * responsive alignment, and context-aware image resolution.
     *
     * @since 2.0.0
     * @return void
     */
    protected function render(): void
    {
        // Get widget settings for display
        $settings = $this->get_settings_for_display();

        // Extract settings with defaults
        $lazy_load = $settings['image_lazy_load'] === 'yes' ? 'lazy' : 'eager';
        $resolution = $settings['image_resolution'] ?? 'large';
        $force_trans = $settings['force_transparent_bg'] === 'yes';
        $source = $settings['preview_image_source'] ?? 'auto';

        // When source is auto, resolve effective source from template content (used tags)
        if ($source === 'auto') {
            $template_content = $this->getTemplateContentForAuto();
            $source = FeaturedImageAutoSourceResolver::resolve($template_content);
        }

        // Get widget context for featured image resolution
        $context = $this->getWidgetContext();

        // If no context available, use preview data resolver for better preview experience
        if (!$context) {
            $context = $this->getPreviewContext();
        }

        // Build image CSS classes
        $image_classes = ['notifal-featured-image'];
        if ($force_trans) {
            $image_classes[] = 'notifal-force-transparent';
        }

        // Add hover animation class if specified (exclude 'none' to allow disabling animations)
        if (!empty($settings['hover_animation']) && $settings['hover_animation'] !== 'none') {
            $image_classes[] = 'elementor-animation-' . $settings['hover_animation'];
        }

        // =====================================================================
        // BUILD WRAPPER CLASSES
        // =====================================================================

        // Base wrapper classes for proper alignment and layout
        $wrapper_classes = [
            'notifal-featured-image-wrapper',
            'notifal-flex',
            'notifal-full-width', // Ensures wrapper takes full width for alignment
        ];

        // =====================================================================
        // RENDER HTML OUTPUT
        // =====================================================================

        ?>
        <div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>">
            <div class="notifal-pulse-img">
                <?php
                // Render the featured image with proper attributes
                echo FeaturedImageResolver::getFeaturedImageHtml($context, $resolution, [
                    'loading' => esc_attr($lazy_load),
                    'class' => esc_attr(implode(' ', $image_classes)),
                ], $source);
                ?>
            </div>
        </div>
        <?php
    }


    /**
     * Get template content for auto source resolution (used in editor preview).
     *
     * Returns the current document/post content so Auto can resolve to product/post/page
     * based on which tags are used in the template. For Elementor templates, content is
     * read from _elementor_data (not post_content) so tags like {product_name} are found.
     *
     * @since 2.0.0
     * @return string Template content (may be empty)
     */
    private function getTemplateContentForAuto(): string
    {
        $post_id = $this->getTemplatePostIdForAuto();
        if ($post_id <= 0) {
            return '';
        }
        $post = get_post($post_id);
        if (!$post) {
            return '';
        }
        return ContentExtractor::extractFromElementorTemplate($post);
    }

    /**
     * Get the template (document) post ID for auto source resolution.
     *
     * Tries get_the_ID() first; in Elementor editor preview falls back to current document.
     *
     * @since 2.0.0
     * @return int Post ID or 0
     */
    private function getTemplatePostIdForAuto(): int
    {
        $post_id = get_the_ID();
        if ($post_id > 0) {
            return (int) $post_id;
        }
        if (class_exists(\Elementor\Plugin::class)) {
            $documents = \Elementor\Plugin::$instance->documents;
            if ($documents && method_exists($documents, 'get_current')) {
                $document = $documents->get_current();
                if ($document && method_exists($document, 'get_main_id')) {
                    $post_id = $document->get_main_id();
                    if ($post_id > 0) {
                        return (int) $post_id;
                    }
                }
            }
        }
        return 0;
    }

    /**
     * Get preview context data for Elementor editor.
     *
     * @since 2.0.0
     * @return array Preview context data
     */
    private function getPreviewContext(): array
    {
        $context = [
            'post' => $this->getSamplePost(),
            'page' => $this->getSamplePage(),
            'comment' => $this->getSampleComment(),
        ];

        // Try to get preview data from resolver
        try {
            $previewDataResolver = notifal_app(\Notifal\Modules\Templates\Application\Services\PreviewDataResolver::class);
            $previewData = $previewDataResolver->resolve();

            // Add product data if WooCommerce is active and we have product data
            if (PluginDetector::isWooCommerceActive() && $previewData && $previewData->getProduct()) {
                $context['product'] = $previewData->getProduct();
            }
        } catch (\Exception $e) {
            // Continue without preview data
        }

        // Fallback to random product if WooCommerce is active but no preview data
        if (PluginDetector::isWooCommerceActive() && !isset($context['product'])) {
            try {
                $productFetcher = notifal_app(ProductFetcherInterface::class);
                $product = $productFetcher->getRandom();
                if ($product) {
                    $context['product'] = $product;
                }
            } catch (\Exception $e) {
                // Continue without product data
            }
        }

        return $context;
    }

    /**
     * Get sample post for preview context.
     *
     * @since 2.0.0
     * @return \WP_Post|null Sample post or null if none found.
     */
    private function getSamplePost(): ?\WP_Post
    {
        $posts = get_posts([
            'post_type' => 'post',
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'orderby' => 'rand'
        ]);

        return !empty($posts) ? $posts[0] : null;
    }

    /**
     * Get sample page for preview context.
     *
     * @since 2.0.0
     * @return \WP_Post|null Sample page or null if none found.
     */
    private function getSamplePage(): ?\WP_Post
    {
        $pages = get_posts([
            'post_type' => 'page',
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'orderby' => 'rand'
        ]);

        return !empty($pages) ? $pages[0] : null;
    }

    /**
     * Get sample comment for preview context.
     *
     * @since 2.0.0
     * @return \WP_Comment|null Sample comment or null if none found.
     */
    private function getSampleComment(): ?\WP_Comment
    {
        $comments = get_comments([
            'number' => 1,
            'status' => 'approve',
            'orderby' => 'rand'
        ]);

        return !empty($comments) ? $comments[0] : null;
    }
}
