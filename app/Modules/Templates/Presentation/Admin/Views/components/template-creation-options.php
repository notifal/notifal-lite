<?php

/**
 * Template Creation Options Component
 *
 * Recommended path: HTML Builder + Noti. Fallback builders: Elementor,
 * WordPress Editor, and Import (no duplicate HTML Builder card).
 *
 * @since 2.0.0
 * @updated 3.0.0 Recommend Noti first and modernize alternate builder cards.
 * @updated 3.0.0 Personalized invite title when a Notifal AI contact name is saved.
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\Templates\Presentation\Admin\ViewComponents
 */

use Notifal\Modules\Ai\Application\Services\AiInviteGreeting;
use Notifal\Modules\Ai\Config\AiCharacter;
use Notifal\Shared\Services\NotifalIconService;

if (!defined('ABSPATH')) {
    exit;
}

// Variables should be available from parent context.
// $elementor_installed, $urls (install_elementor, create_editor, create_elementor, create_html_builder, create_html_builder_with_noti, import_nonce)

// Resolve a greeting-safe first name only when Notifal AI already stored one.
$greeting = notifal_app(AiInviteGreeting::class);
$user_first_name = $greeting instanceof AiInviteGreeting ? $greeting->resolveFirstName() : '';

// Personalize the recommended invite title when a connected name exists.
if ($user_first_name !== '') {
    $create_invite_title = sprintf(
        /* translators: 1: user first name, 2: AI character display name (Noti) */
        __('Hey %1$s, I\'m %2$s. Let\'s create your next template in the HTML Builder.', 'notifal'),
        $user_first_name,
        AiCharacter::DISPLAY_NAME
    );
} else {
    $create_invite_title = sprintf(
        /* translators: %s: AI character display name (Noti) */
        __('I\'m %s. Let\'s create your next template in the HTML Builder.', 'notifal'),
        AiCharacter::DISPLAY_NAME
    );
}
?>

<div class="notifal-template-create">
    <?php // Recommended path: HTML Builder + Noti AI. ?>
    <section
        class="notifal-ai-invite notifal-template-create__ai"
        id="notifal-template-ai-invite"
        aria-label="<?php esc_attr_e('Create with Notifal HTML Builder and AI', 'notifal'); ?>"
    >
        <div class="notifal-ai-invite__fx" aria-hidden="true">
            <span class="notifal-ai-invite__aurora"></span>
            <span class="notifal-ai-invite__shimmer"></span>
        </div>

        <div class="notifal-ai-invite__inner">
            <?php
            // Shared Noti character for the create-template invite.
            AiCharacter::render(
                [
                    'id'              => 'notifal-template-ai-invite-character',
                    'size'            => 'lg',
                    'variant'         => 'default',
                    'show_tip'        => false,
                    'tip_dismissible' => false,
                    'auto_start'      => false,
                    'click_to_talk'   => true,
                    'storage_key'     => AiCharacter::STORAGE_PREFIX . 'template_create',
                    'mode'            => 'loop',
                    'phrases'         => [
                        sprintf(
                            /* translators: %s: AI character display name (Noti) */
                            __('I\'m %s. Describe the template you want and I\'ll design it in the HTML Builder.', 'notifal'),
                            AiCharacter::DISPLAY_NAME
                        ),
                        __('Skip blank canvases. Open the HTML Builder with me and turn a simple prompt into a ready template.', 'notifal'),
                    ],
                    'extra_class'     => 'notifal-ai-invite__character',
                ]
            );
            ?>

            <div class="notifal-ai-invite__copy">
                <div class="notifal-ai-invite__eyebrow">
                    <span class="notifal-ai-invite__brand"><?php echo esc_html(AiCharacter::BRAND_LABEL); ?></span>
                    <span class="notifal-template-create__recommended"><?php esc_html_e('Recommended', 'notifal'); ?></span>
                </div>
                <h3 class="notifal-ai-invite__title">
                    <?php echo esc_html($create_invite_title); ?>
                </h3>
                <p class="notifal-ai-invite__lead">
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: %s: AI character display name (Noti) */
                            __('Open the Notifal HTML Builder and let %s design your full template from a simple prompt. Best experience, no Elementor or Gutenberg required.', 'notifal'),
                            AiCharacter::DISPLAY_NAME
                        )
                    );
                    ?>
                </p>
            </div>

            <a
                href="<?php echo esc_url($urls['create_html_builder_with_noti'] ?? $urls['create_html_builder']); ?>"
                class="notifal-ai-invite__cta"
                id="notifal-template-generate-ai-button"
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

    <p class="notifal-template-create__or">
        <span class="notifal-template-create__or-line" aria-hidden="true"></span>
        <span class="notifal-template-create__or-text"><?php esc_html_e('Or choose another way', 'notifal'); ?></span>
        <span class="notifal-template-create__or-line" aria-hidden="true"></span>
    </p>

    <div class="notifal-builder-options-grid" role="list">
        <?php // Elementor builder option. ?>
        <div class="notifal-builder-option" role="listitem" data-builder="elementor">
            <div class="notifal-builder-option__icon" aria-hidden="true">
                <?php echo NotifalIconService::render('elementor', 28); ?>
            </div>
            <div class="notifal-builder-option__body">
                <h3 class="notifal-builder-option__title">
                    <?php esc_html_e('Elementor', 'notifal'); ?>
                </h3>
                <p class="notifal-builder-option__desc">
                    <?php
                    echo $elementor_installed
                        ? esc_html__('Build the template visually with Elementor.', 'notifal')
                        : esc_html__('Install Elementor to use this builder.', 'notifal');
                    ?>
                </p>
            </div>
            <div class="notifal-builder-option__action">
                <?php if ($elementor_installed) : ?>
                    <a href="<?php echo esc_url($urls['create_elementor']); ?>" class="notifal-builder-option__cta">
                        <?php esc_html_e('Continue', 'notifal'); ?>
                        <span class="notifal-builder-option__cta-arrow" aria-hidden="true">&rarr;</span>
                    </a>
                <?php else : ?>
                    <a href="<?php echo esc_url($urls['install_elementor']); ?>" class="notifal-builder-option__cta notifal-builder-option__cta--secondary">
                        <?php esc_html_e('Install', 'notifal'); ?>
                        <span class="notifal-builder-option__cta-arrow" aria-hidden="true">&rarr;</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php // WordPress block editor option. ?>
        <div class="notifal-builder-option" role="listitem" data-builder="block-editor">
            <div class="notifal-builder-option__icon" aria-hidden="true">
                <?php echo NotifalIconService::render('wordpress', 28); ?>
            </div>
            <div class="notifal-builder-option__body">
                <h3 class="notifal-builder-option__title">
                    <?php esc_html_e('Block Editor', 'notifal'); ?>
                </h3>
                <p class="notifal-builder-option__desc">
                    <?php esc_html_e('Create the template with the WordPress block editor.', 'notifal'); ?>
                </p>
            </div>
            <div class="notifal-builder-option__action">
                <a href="<?php echo esc_url($urls['create_editor']); ?>" class="notifal-builder-option__cta">
                    <?php esc_html_e('Continue', 'notifal'); ?>
                    <span class="notifal-builder-option__cta-arrow" aria-hidden="true">&rarr;</span>
                </a>
            </div>
        </div>

        <?php // Import existing template file. ?>
        <div class="notifal-builder-option" role="listitem" data-builder="import">
            <div class="notifal-builder-option__icon" aria-hidden="true">
                <?php echo NotifalIconService::render('download', 28); ?>
            </div>
            <div class="notifal-builder-option__body">
                <h3 class="notifal-builder-option__title">
                    <?php esc_html_e('Import', 'notifal'); ?>
                </h3>
                <p class="notifal-builder-option__desc">
                    <?php esc_html_e('Upload a Notifal JSON or ZIP export file.', 'notifal'); ?>
                </p>
            </div>
            <div class="notifal-builder-option__action">
                <button type="button"
                        id="notifal-import-trigger"
                        class="notifal-builder-option__cta"
                        data-loading-text="<?php esc_attr_e('Importing...', 'notifal'); ?>"
                        data-original-text="<?php esc_attr_e('Import', 'notifal'); ?>">
                    <?php esc_html_e('Import', 'notifal'); ?>
                    <span class="notifal-builder-option__cta-arrow" aria-hidden="true">&rarr;</span>
                </button>
                <form id="notifal-import-form" method="post" enctype="multipart/form-data" class="notifal-hidden">
                    <input type="file" id="notifal-import-file" name="notifal_import_file" accept=".json,.zip" required>
                    <input type="hidden" name="action" value="notifal_import_ajax">
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($urls['import_nonce']); ?>">
                </form>
            </div>
        </div>
    </div>
</div>
