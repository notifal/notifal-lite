<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Traits;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Trait BlockRendererTrait
 *
 * Provides common functionality for Gutenberg block renderers.
 * Handles standardized hook firing for before/after rendering operations.
 * Reduces code duplication across different block renderers.
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Traits
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
trait BlockRendererTrait
{
    /**
     * Fire before render hook for a block.
     *
     * Standardizes the hook firing pattern across all block renderers.
     *
     * @param string $block_type Block type identifier (e.g., 'action_button', 'close_icon').
     * @param array $attributes Block attributes.
     * @param string $content Block content.
     * @param mixed $block Block instance.
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    protected static function fireBeforeRenderHook(string $block_type, array $attributes, string $content, $block): void
    {
        /**
         * Fires before rendering block content.
         *
         * @since 2.0.0
         * @param array $attributes Block attributes.
         * @param string $content Block content.
         * @param mixed $block Block instance.
         */
        do_action(ActionHooks::BLOCK_RENDER_BEFORE . '_' . $block_type, $attributes, $content, $block);
    }

    /**
     * Fire after render hook for a block.
     *
     * Standardizes the hook firing pattern across all block renderers.
     *
     * @param string $block_type Block type identifier (e.g., 'action_button', 'close_icon').
     * @param string $html Rendered HTML output.
     * @param array $attributes Block attributes.
     * @param string $content Block content.
     * @param mixed $block Block instance.
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    protected static function fireAfterRenderHook(string $block_type, string $html, array $attributes, string $content, $block): void
    {
        /**
         * Fires after rendering block content.
         *
         * @since 2.0.0
         * @param string $html Rendered HTML.
         * @param array $attributes Block attributes.
         * @param string $content Block content.
         * @param mixed $block Block instance.
         */
        do_action(ActionHooks::BLOCK_RENDER_AFTER . '_' . $block_type, $html, $attributes, $content, $block);
    }

    /**
     * Sanitize color attribute with validation.
     *
     * @param mixed $value Raw color value.
     * @param string $default Default color value.
     * @return string Sanitized hex color.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    protected static function sanitizeColor($value, string $default = ''): string
    {
        return sanitize_hex_color($value) ?: $default;
    }

    /**
     * Sanitize text attribute with validation.
     *
     * @param mixed $value Raw text value.
     * @param string $default Default text value.
     * @return string Sanitized text.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    protected static function sanitizeText($value, string $default = ''): string
    {
        return Helper::sanitizeInput($value ?: $default, 'text');
    }

    /**
     * Sanitize URL attribute with validation.
     *
     * @param mixed $value Raw URL value.
     * @return string Sanitized URL.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    protected static function sanitizeUrl($value): string
    {
        return Helper::sanitizeInput($value, 'url');
    }

    /**
     * Sanitize integer attribute with validation.
     *
     * @param mixed $value Raw integer value.
     * @param int $default Default integer value.
     * @param int $min Minimum allowed value.
     * @return int Sanitized integer.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    protected static function sanitizeInt($value, int $default = 0, int $min = 0): int
    {
        $int = Helper::sanitizeInput($value, 'int');
        return $int >= $min ? $int : $default;
    }

    /**
     * Sanitize float attribute with validation.
     *
     * @param mixed $value Raw float value.
     * @param float $default Default float value.
     * @return float Sanitized float.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    protected static function sanitizeFloat($value, float $default = 0.0): float
    {
        return Helper::sanitizeInput($value, 'float') ?: $default;
    }

    /**
     * Validate and sanitize attribute from allowed values.
     *
     * @param mixed $value Raw value to validate.
     * @param array $allowed Allowed values array.
     * @param mixed $default Default value if validation fails.
     * @return mixed Sanitized value from allowed list.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    protected static function sanitizeFromAllowed($value, array $allowed, $default)
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * Sanitize boolean attribute.
     *
     * @param mixed $value Raw boolean value.
     * @return bool Sanitized boolean.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    protected static function sanitizeBool($value): bool
    {
        return (bool) $value;
    }
}