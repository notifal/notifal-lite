<?php
/**
 * Centered Notifal AI invite strip for the OnPage notifications list.
 *
 * Uses the shared Noti AI character plus aurora invite layout and CTA.
 *
 * @since 3.0.0
 * @updated 3.0.0 Personalized headline with a safe first-name greeting when available.
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Views\components
 * @author Hossein <hossein@notifal.com>
 */

use Notifal\Modules\Ai\Application\Services\AiInviteGreeting;
use Notifal\Modules\Ai\Config\AiCharacter;
use Notifal\Shared\Services\NotifalIconService;

defined('ABSPATH') || exit;

// Resolve site host for personalized invite thoughts.
$home_url = (string) home_url('/');
$domain = (string) wp_parse_url($home_url, PHP_URL_HOST);
$domain = $domain !== '' ? $domain : __('your site', 'notifal');

// Count existing OnPage notifications for first-popup vs next-popup messaging.
$counts = wp_count_posts('notifal_onpage_notif');
$notification_count = (isset($counts->publish) ? (int) $counts->publish : 0)
    + (isset($counts->draft) ? (int) $counts->draft : 0)
    + (isset($counts->private) ? (int) $counts->private : 0);

// Resolve a greeting-safe first name (empty when no suitable personal name exists).
$greeting = notifal_app(AiInviteGreeting::class);
$user_first_name = $greeting instanceof AiInviteGreeting ? $greeting->resolveFirstName() : '';

// Build looping invite thoughts for Noti.
$tip_phrases = [
    sprintf(
        /* translators: %s: site domain */
        __('I\'m studying how %s can convert more with Notifal popups. I already see gaps where a great popup could help!', 'notifal'),
        $domain
    ),
];

if ($notification_count < 1) {
    $tip_phrases[] = __('You have no OnPage notifications yet. Perfect blank canvas. Try me and I\'ll draft your first one!', 'notifal');
} else {
    $tip_phrases[] = sprintf(
        /* translators: %d: notification count */
        __('You already have %d notification(s). Let\'s craft another one that fills a gap your current set might miss.', 'notifal'),
        $notification_count
    );
}

// Friendly Noti tip without paywall / credit pressure messaging.
$tip_phrases[] = sprintf(
    /* translators: %s: AI character display name (Noti) */
    __('I\'m %s, your Notifal AI companion. Click me anytime to chat, or hit Generate with AI and I\'ll draft a popup for you!', 'notifal'),
    AiCharacter::DISPLAY_NAME
);

// Build the headline: personal when we have a name, otherwise Noti-only invite.
if ($user_first_name !== '') {
    $invite_title = sprintf(
        /* translators: 1: user first name, 2: AI character display name (Noti) */
        __('Hey %1$s, I\'m %2$s. Tell me what you want and I\'ll create a popup for your site.', 'notifal'),
        $user_first_name,
        AiCharacter::DISPLAY_NAME
    );
} else {
    $invite_title = sprintf(
        /* translators: %s: AI character display name (Noti) */
        __('I\'m %s, tell me what you want. I\'ll create a popup for your site.', 'notifal'),
        AiCharacter::DISPLAY_NAME
    );
}
?>
<section
    class="notifal-ai-invite"
    id="notifal-ai-invite"
    aria-label="<?php esc_attr_e('Notifal AI', 'notifal'); ?>"
>
    <?php // Soft animated aurora layers (decorative only, clipped by fx wrapper). ?>
    <div class="notifal-ai-invite__fx" aria-hidden="true">
        <span class="notifal-ai-invite__aurora"></span>
        <span class="notifal-ai-invite__shimmer"></span>
    </div>

    <div class="notifal-ai-invite__inner">
        <?php
        // Shared Noti character: dismiss stops talking, click avatar resumes.
        AiCharacter::render(
            [
                'id'              => 'notifal-ai-invite-character',
                'size'            => 'lg',
                'variant'         => 'default',
                'show_tip'        => true,
                'tip_dismissible' => true,
                'auto_start'      => true,
                'click_to_talk'   => true,
                'storage_key'     => AiCharacter::STORAGE_PREFIX . 'invite',
                'mode'            => 'loop',
                'phrases'         => $tip_phrases,
                'extra_class'     => 'notifal-ai-invite__character',
            ]
        );
        ?>

        <div class="notifal-ai-invite__copy">
            <div class="notifal-ai-invite__eyebrow">
                <?php // Brand eyebrow only; keep the strip focused on Noti, not credits. ?>
                <span class="notifal-ai-invite__brand"><?php echo esc_html(AiCharacter::BRAND_LABEL); ?></span>
            </div>
            <h2 class="notifal-ai-invite__title">
                <?php echo esc_html($invite_title); ?>
            </h2>
            <p class="notifal-ai-invite__lead">
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %s: AI character display name (Noti) */
                        __('Skip blank canvases. Describe your idea and %s drafts a ready-to-review notification.', 'notifal'),
                        AiCharacter::DISPLAY_NAME
                    )
                );
                ?>
            </p>
        </div>

        <?php // Primary CTA keeps the legacy button id so the wizard open handler still finds it. ?>
        <button
            type="button"
            class="notifal-ai-invite__cta"
            id="notifal-generate-ai-button"
        >
            <span class="notifal-ai-invite__cta-shine" aria-hidden="true"></span>
            <span class="notifal-ai-invite__cta-label notifal-flex notifal-gap-10">
                <?php echo NotifalIconService::render('ai', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconService returns safe SVG/HTML. ?>
                <?php esc_html_e('Generate with AI', 'notifal'); ?>
            </span>
        </button>
    </div>
</section>
