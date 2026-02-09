<?php

namespace Notifal\Shared\Services;

defined('ABSPATH') || exit;

/**
 * Unified Notifal Icon Renderer
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class NotifalIconService
{
    /**
     * Render a Notifal icon as a span with the correct classes.
     *
     * @param string $name Icon name (e.g. 'elementor', 'logo', 'wordpress', etc.)
     * @param int $size Font size in px (default 20)
     * @param array $attrs Extra HTML attributes (associative array)
     * @return string
     * @since 2.0.0
     */
    public static function render(string $name, int $size = 20, array $attrs = []): string
    {
        $size_class = 'size-' . intval($size);
        $class = 'notifal-icon notifal-icon-' . esc_attr($name) . ' ' . $size_class;
        if (!empty($attrs['class'])) {
            $class .= ' ' . esc_attr($attrs['class']);
            unset($attrs['class']);
        }
        $attr_str = '';
        foreach ($attrs as $k => $v) {
            $attr_str .= ' ' . esc_attr($k) . '="' . esc_attr($v) . '"';
        }
        return '<span class="' . $class . '"' . $attr_str . '></span>';
    }
} 
