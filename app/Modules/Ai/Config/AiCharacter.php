<?php

namespace Notifal\Modules\Ai\Config;

use Notifal\Shared\Services\NotifalIconService;

defined('ABSPATH') || exit;

/**
 * Shared Notifal AI character identity and renderer.
 *
 * Noti is the reusable talking companion used across Notifal AI surfaces
 * (list invite strip, hosted wizard, and future AI screens).
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class AiCharacter
{
    /**
     * Internal character slug used for CSS/JS hooks.
     *
     * @var string
     */
    public const SLUG = 'noti';

    /**
     * Legacy icon slug kept for backwards-compatible references.
     *
     * The live Noti face is the animated markup from renderFaceHtml().
     *
     * @var string
     */
    public const FACE_ICON = 'robot';

    /**
     * Friendly display name shown beside the avatar.
     *
     * @var string
     */
    public const DISPLAY_NAME = 'Noti';

    /**
     * Product brand label paired with the character.
     *
     * @var string
     */
    public const BRAND_LABEL = 'Notifal AI';

    /**
     * Default localStorage key prefix for autoplay mute state.
     *
     * @var string
     */
    public const STORAGE_PREFIX = 'notifal_ai_character_autoplay_';

    /**
     * Allowed Noti face size tokens.
     *
     * @var string[]
     */
    private const FACE_SIZES = ['xs', 'sm', 'md', 'lg'];

    /**
     * Build the animated Noti neutral face markup.
     *
     * Call sites keep using AiCharacter helpers; the face itself is CSS-animated.
     *
     * @since 3.0.0
     * @param string $size Face size token: xs|sm|md|lg.
     * @return string Safe HTML for the animated face.
     */
    public static function renderFaceHtml(string $size = 'md'): string
    {
        // Clamp to the known size tokens used by notifal-noti-face.css.
        $face_size = in_array($size, self::FACE_SIZES, true) ? $size : 'md';

        // Static markup only; motion lives entirely in CSS keyframes.
        return sprintf(
            '<span class="notifal-noti-face notifal-noti-face--%1$s" aria-hidden="true">'
            . '<span class="notifal-noti-face__stage">'
            . '<span class="notifal-noti-face__frame"></span>'
            . '<span class="notifal-noti-face__body"></span>'
            . '<span class="notifal-noti-face__eye-group">'
            . '<span class="notifal-noti-face__eye notifal-noti-face__eye--left">'
            . '<span class="notifal-noti-face__eye-visual"></span>'
            . '</span>'
            . '<span class="notifal-noti-face__eye notifal-noti-face__eye--right">'
            . '<span class="notifal-noti-face__eye-visual"></span>'
            . '</span>'
            . '</span>'
            . '</span>'
            . '</span>',
            esc_attr($face_size)
        );
    }

    /**
     * Map a character avatar size to a Noti face size token.
     *
     * @since 3.0.0
     * @param string $avatar_size Character size: sm|md|lg.
     * @return string Face size token.
     */
    public static function mapAvatarSizeToFaceSize(string $avatar_size): string
    {
        // Keep face proportions aligned with the existing avatar size variants.
        if ($avatar_size === 'sm') {
            return 'sm';
        }

        if ($avatar_size === 'lg') {
            return 'lg';
        }

        return 'md';
    }

    /**
     * Public metadata exposed to JavaScript.
     *
     * @since 3.0.0
     * @return array<string, string>
     */
    public static function getPublicMeta(): array
    {
        return [
            'slug'         => self::SLUG,
            'display_name' => self::DISPLAY_NAME,
            'brand_label'  => self::BRAND_LABEL,
            // Legacy key for older JS that still reads face_icon.
            'face_icon'    => self::FACE_ICON,
            // Default animated face used by JS-built Noti avatars.
            'face_html'    => self::renderFaceHtml('md'),
            'face_html_xs' => self::renderFaceHtml('xs'),
            'face_html_sm' => self::renderFaceHtml('sm'),
            'face_html_lg' => self::renderFaceHtml('lg'),
        ];
    }

    /**
     * Render the shared AI character markup.
     *
     * @since 3.0.0
     * @param array<string, mixed> $args {
     *     Optional render arguments.
     *
     *     @type string   $id               Root element id.
     *     @type string   $size             Avatar size: sm|md|lg.
     *     @type string   $variant          Layout variant: default|companion|compact.
     *     @type bool     $show_tip         Whether the speech bubble exists in DOM.
     *     @type bool     $tip_dismissible  Whether the tip has a dismiss control.
     *     @type bool     $auto_start       Whether talking starts automatically.
     *     @type bool     $click_to_talk    Whether clicking the avatar toggles talking.
     *     @type string   $storage_key      localStorage key for muted autoplay.
     *     @type string   $mode             Tip playback: loop|once.
     *     @type string[] $phrases         Initial tip phrases.
     *     @type string   $extra_class      Extra CSS classes on the root.
     *     @type string   $label           Accessible name override.
     * }
     * @return void
     */
    public static function render(array $args = []): void
    {
        // Merge caller overrides with safe defaults.
        $args = wp_parse_args(
            $args,
            [
                'id'              => 'notifal-ai-character-' . self::SLUG,
                'size'            => 'md',
                'variant'         => 'default',
                'show_tip'        => true,
                'tip_dismissible' => true,
                'auto_start'      => true,
                'click_to_talk'   => true,
                'storage_key'     => self::STORAGE_PREFIX . self::SLUG,
                'mode'            => 'loop',
                'phrases'         => [],
                'extra_class'     => '',
                'label'           => self::DISPLAY_NAME . ' · ' . self::BRAND_LABEL,
            ]
        );

        // Sanitize identifiers and enums for attribute output.
        $id = sanitize_html_class((string) $args['id']);
        $size = in_array($args['size'], ['sm', 'md', 'lg'], true) ? (string) $args['size'] : 'md';
        $variant = in_array($args['variant'], ['default', 'companion', 'compact'], true)
            ? (string) $args['variant']
            : 'default';
        $mode = in_array($args['mode'], ['loop', 'once'], true) ? (string) $args['mode'] : 'loop';
        $storage_key = sanitize_key((string) $args['storage_key']);
        $extra_class = sanitize_html_class((string) $args['extra_class']);
        $phrases = is_array($args['phrases']) ? array_values($args['phrases']) : [];
        $phrases_json = wp_json_encode($phrases);

        // Resolve the shared partial path.
        $view = dirname(__DIR__) . '/Presentation/Admin/Views/components/ai-character.php';

        // Bail when the partial is missing.
        if (!is_readable($view)) {
            return;
        }

        // Expose sanitized values to the included view.
        $character = [
            'id'              => $id,
            'size'            => $size,
            'variant'         => $variant,
            'mode'            => $mode,
            'storage_key'     => $storage_key,
            'extra_class'     => $extra_class,
            'show_tip'        => (bool) $args['show_tip'],
            'tip_dismissible' => (bool) $args['tip_dismissible'],
            'auto_start'      => (bool) $args['auto_start'],
            'click_to_talk'   => (bool) $args['click_to_talk'],
            'phrases_json'    => is_string($phrases_json) ? $phrases_json : '[]',
            'label'           => (string) $args['label'],
            'display_name'    => self::DISPLAY_NAME,
            'brand_label'     => self::BRAND_LABEL,
            // Animated Noti face replaces the old icon-font glyph in the avatar.
            'icon_html'       => self::renderFaceHtml(self::mapAvatarSizeToFaceSize($size)),
            'dismiss_icon'    => NotifalIconService::render('x-circle', 14),
        ];

        // Render the shared character partial.
        include $view;
    }
}
