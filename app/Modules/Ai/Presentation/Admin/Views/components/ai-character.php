<?php
/**
 * Shared Notifal AI character (Noti) markup.
 *
 * Used by invite strips, hosted wizards, and other AI surfaces.
 * Expected `$character` array is prepared by AiCharacter::render().
 *
 * @since 3.0.0
 * @package Notifal\Modules\Ai\Presentation\Admin\Views\components
 * @author Hossein <hossein@notifal.com>
 * @var array<string, mixed> $character
 */

use Notifal\Modules\Ai\Config\AiCharacter;

defined('ABSPATH') || exit;

// Build root class list from size, variant, and optional extras.
$root_classes = trim(
    'notifal-ai-character'
    . ' notifal-ai-character--' . $character['size']
    . ' notifal-ai-character--' . $character['variant']
    . ' ' . $character['extra_class']
);
?>
<div
    class="<?php echo esc_attr($root_classes); ?>"
    id="<?php echo esc_attr($character['id']); ?>"
    role="group"
    aria-label="<?php echo esc_attr($character['label']); ?>"
    data-notifal-ai-character="<?php echo esc_attr(AiCharacter::SLUG); ?>"
    data-size="<?php echo esc_attr($character['size']); ?>"
    data-variant="<?php echo esc_attr($character['variant']); ?>"
    data-mode="<?php echo esc_attr($character['mode']); ?>"
    data-storage-key="<?php echo esc_attr($character['storage_key']); ?>"
    data-auto-start="<?php echo $character['auto_start'] ? '1' : '0'; ?>"
    data-click-to-talk="<?php echo $character['click_to_talk'] ? '1' : '0'; ?>"
    data-phrases="<?php echo esc_attr($character['phrases_json']); ?>"
>
    <?php // Clickable avatar cluster with glow, sparkles, and talk bars. ?>
    <button
        type="button"
        class="notifal-ai-character__avatar"
        data-notifal-ai-character-avatar
        aria-pressed="false"
        aria-label="<?php echo esc_attr(sprintf(/* translators: %s: character display name */ __('%s, click to talk', 'notifal'), $character['display_name'])); ?>"
    >
        <span class="notifal-ai-character__glow" aria-hidden="true"></span>
        <span class="notifal-ai-character__face" aria-hidden="true">
            <?php echo $character['icon_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconService returns safe HTML. ?>
        </span>
        <span class="notifal-ai-character__sparkle notifal-ai-character__sparkle--one" aria-hidden="true"></span>
        <span class="notifal-ai-character__sparkle notifal-ai-character__sparkle--two" aria-hidden="true"></span>
        <span class="notifal-ai-character__sparkle notifal-ai-character__sparkle--three" aria-hidden="true"></span>
        <span class="notifal-ai-character__talk-bars" data-notifal-ai-character-bars aria-hidden="true">
            <i></i><i></i><i></i>
        </span>
        <span class="notifal-ai-character__think-dots" data-notifal-ai-character-think aria-hidden="true">
            <i></i><i></i><i></i>
        </span>
    </button>

    <?php if (!empty($character['show_tip'])) : ?>
        <?php // Speech / thought bubble controlled by the shared character JS. ?>
        <div
            class="notifal-ai-character__tip is-hidden"
            data-notifal-ai-character-tip
            role="status"
            aria-live="polite"
            hidden
        >
            <span class="notifal-ai-character__tip-tail" aria-hidden="true"></span>
            <div class="notifal-ai-character__tip-meta">
                <span class="notifal-ai-character__name"><?php echo esc_html($character['display_name']); ?></span>
                <span class="notifal-ai-character__brand"><?php echo esc_html($character['brand_label']); ?></span>
            </div>
            <p class="notifal-ai-character__tip-text">
                <span data-notifal-ai-character-typed></span>
                <span class="notifal-ai-character__tip-caret" aria-hidden="true"></span>
            </p>
            <?php if (!empty($character['tip_dismissible'])) : ?>
                <button
                    type="button"
                    class="notifal-ai-character__tip-dismiss"
                    data-notifal-ai-character-dismiss
                    aria-label="<?php esc_attr_e('Stop talking', 'notifal'); ?>"
                >
                    <?php echo $character['dismiss_icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconService returns safe HTML. ?>
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
