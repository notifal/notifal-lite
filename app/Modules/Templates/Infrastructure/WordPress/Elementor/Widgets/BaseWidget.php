<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Widgets;

use Elementor\Widget_Base;

defined('ABSPATH') || exit;

/**
 * Base class for Notifal Elementor widgets.
 *
 * Provides common functionality and standardized patterns for all Notifal widgets.
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Widgets
 * @author Hossein <hossein@notifal.com>
 */
class BaseWidget extends Widget_Base
{
    /**
     * Get widget name.
     *
     * Elementor requires each widget to implement get_name().
     *
     * @since 2.0.0
     * @return string
     */
    public function get_name(): string
    {
        return 'notifal-base-widget';
    }

    /**
     * Get widget categories.
     *
     * @since 2.0.0
     * @return array
     */
    public function get_categories(): array
    {
        return ['notifal'];
    }

    /**
     * Get widget style dependencies.
     *
     * @since 2.0.0
     * @return array
     */
    public function get_style_depends(): array
    {
        return ['elementor-icons', 'notifal-elementor-widgets-style'];
    }
}