<?php

/**
 * Templates list AI invite strip.
 *
 * Mirrors the OnPage notifications list invite and routes "Generate with AI"
 * into a new HTML Builder template with Noti opened by default.
 *
 * @since 3.0.0
 * @updated 3.0.0 Personalized headline with a safe first-name greeting when available.
 * @package Notifal\Modules\Templates\Presentation\Admin\Views\components
 * @author Hossein <hossein@notifal.com>
 */

use Notifal\Modules\Ai\Application\Services\AiInviteGreeting;
use Notifal\Modules\Ai\Config\AiCharacter;
use Notifal\Modules\Templates\Application\Services\TemplateUrlService;
use Notifal\Shared\Services\NotifalIconService;

defined('ABSPATH') || exit;

// Resolve create URL that opens HTML Builder with Noti expanded.
$urlService = notifal_app(TemplateUrlService::class);
$generateWithAiUrl = $urlService->getCreateHtmlBuilderUrl(true);

// Resolve a greeting-safe first name only when Notifal AI already stored one.
// Empty when not connected yet, so the Noti-only headline is used.
$greeting = notifal_app(AiInviteGreeting::class);
$user_first_name = $greeting instanceof AiInviteGreeting ? $greeting->resolveFirstName() : '';

// Build the headline: personal when a saved AI name exists, otherwise Noti-only invite.
if ($user_first_name !== '') {
    $invite_title = sprintf(
        /* translators: 1: user first name, 2: AI character display name (Noti) */
        __('Hey %1$s, I\'m %2$s. Let\'s create your next template in the HTML Builder.', 'notifal'),
        $user_first_name,
        AiCharacter::DISPLAY_NAME
    );
} else {
    $invite_title = sprintf(
        /* translators: %s: AI character display name (Noti) */
        __('I\'m %s. Let\'s create your next template in the HTML Builder.', 'notifal'),
        AiCharacter::DISPLAY_NAME
    );
}
?>
<section
    class="notifal-ai-invite"
    id="notifal-ai-invite"
    aria-label="<?php esc_attr_e('Notifal AI', 'notifal'); ?>"
>
    <div class="notifal-ai-invite__fx" aria-hidden="true">
        <span class="notifal-ai-invite__aurora"></span>
        <span class="notifal-ai-invite__shimmer"></span>
    </div>

    <div class="notifal-ai-invite__inner">
        <?php
        // Shared Noti character for the templates list invite.
        AiCharacter::render(
            [
                'id'              => 'notifal-ai-invite-character',
                'size'            => 'lg',
                'variant'         => 'default',
                'show_tip'        => true,
                'tip_dismissible' => true,
                'auto_start'      => true,
                'click_to_talk'   => true,
                'storage_key'     => AiCharacter::STORAGE_PREFIX . 'templates_invite',
                'mode'            => 'loop',
                'phrases'         => [
                    sprintf(
                        /* translators: %s: AI character display name (Noti) */
                        __('I\'m %s. Open the HTML Builder with me and I\'ll design a full template from a simple prompt.', 'notifal'),
                        AiCharacter::DISPLAY_NAME
                    ),
                    __('Recommended: create templates with the Notifal HTML Builder so I can help you design the whole layout.', 'notifal'),
                ],
                'extra_class'     => 'notifal-ai-invite__character',
            ]
        );
        ?>

        <div class="notifal-ai-invite__copy">
            <div class="notifal-ai-invite__eyebrow">
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
                        __('Describe what you want and %s designs the template for you inside Notifal HTML Builder.', 'notifal'),
                        AiCharacter::DISPLAY_NAME
                    )
                );
                ?>
            </p>
        </div>

        <a
            href="<?php echo esc_url($generateWithAiUrl); ?>"
            class="notifal-ai-invite__cta"
            id="notifal-generate-ai-button"
        >
            <span class="notifal-ai-invite__cta-shine" aria-hidden="true"></span>
            <span class="notifal-ai-invite__cta-label notifal-flex notifal-gap-10">
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconService returns safe SVG/HTML.
                echo NotifalIconService::render('ai', 18);
                ?>
                <?php esc_html_e('Generate with AI', 'notifal'); ?>
            </span>
        </a>
    </div>
</section>
