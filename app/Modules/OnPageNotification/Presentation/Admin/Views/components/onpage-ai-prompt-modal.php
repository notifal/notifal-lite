<?php

/**
 * OnPage Notification AI Prompt Modal
 *
 * Modern hosted Notifal AI wizard with compact step chrome and Noti companion.
 *
 * @since 2.4.1
 * @updated 3.0.0 Hosted panel UI refresh with separated personalization steps.
 * @updated 3.0.0 Describe-step reference image attach control and credit tip tooltip.
 * @updated 3.0.0 Palette step defaults to AI-decided colors from the prompt (JS-rendered).
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Views\components
 */

use Notifal\Modules\Ai\Config\AiCharacter;
use Notifal\Shared\AdminUI\Fields\FieldRenderer;
use Notifal\Shared\Services\NotifalIconService;

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="notifal-modal-backdrop notifal-hidden" id="notifal-onpage-ai-prompt-modal" aria-hidden="true">
    <div
        class="notifal-modal notifal-onpage-ai-prompt-modal notifal-ai-wizard-shell notifal-onpage-ai-hosted-shell"
        role="dialog"
        aria-modal="true"
        aria-labelledby="notifal-onpage-ai-prompt-title"
    >
    
        <div class="notifal-ai-wizard-shell__sticky-top notifal-onpage-ai-hosted-shell__sticky-top">
            <div class="notifal-onpage-ai-hosted-shell__progress-row">
                <span class="notifal-ai-wizard__step-label" id="notifal-onpage-ai-wizard-step-label"><?php esc_html_e('Step 1 of 6', 'notifal'); ?></span>
                <div class="notifal-onpage-ai-hosted-shell__top-actions">
                    <button
                        type="button"
                        class="notifal-ai-wizard__view-toggle notifal-hidden"
                        id="notifal-onpage-ai-wizard-fullscreen"
                        aria-pressed="false"
                        aria-label="<?php esc_attr_e('Open fullscreen', 'notifal'); ?>"
                        hidden
                    >
                        <span class="notifal-icon notifal-icon-arrows-fullscreen size-16" aria-hidden="true"></span>
                    </button>
                    <button type="button" class="notifal-onpage-ai-hosted-shell__close" id="notifal-onpage-ai-prompt-close" aria-label="<?php esc_attr_e('Close', 'notifal'); ?>">
                        <span class="notifal-icon notifal-icon-x-circle size-16" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
            <div class="notifal-onpage-ai-hosted-shell__segments" id="notifal-onpage-ai-wizard-progress-segments" aria-hidden="true"></div>
            <div class="notifal-ai-wizard__progress-track notifal-hidden" aria-hidden="true">
                <div class="notifal-ai-wizard__progress-fill" id="notifal-onpage-ai-wizard-progress" style="width:20%;"></div>
            </div>

            <div class="notifal-onpage-ai-hosted-shell__noti" id="notifal-onpage-ai-hosted-noti">
                <div class="notifal-onpage-ai-hosted-shell__noti-avatar-wrap notifal-noti-avatar-shell notifal-noti-avatar-shell--md">
                    <span class="notifal-onpage-ai-hosted-shell__noti-avatar notifal-noti-avatar-shell__plate">
                        <span class="notifal-ai-character__face" aria-hidden="true">
                            <?php
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- AiCharacter::renderFaceHtml() returns fixed safe markup.
                            echo AiCharacter::renderFaceHtml('md');
                            ?>
                        </span>
                    </span>
                </div>
                <div class="notifal-onpage-ai-hosted-shell__noti-stack">
                    <div class="notifal-onpage-ai-hosted-shell__noti-meta">
                        <p class="notifal-onpage-ai-hosted-shell__noti-name"><?php echo esc_html(strtoupper(AiCharacter::DISPLAY_NAME)); ?></p>
                        <p class="notifal-onpage-ai-hosted-shell__noti-brand"><?php echo esc_html(AiCharacter::BRAND_LABEL); ?></p>
                    </div>
                    <div class="notifal-onpage-ai-hosted-shell__noti-bubble">
                        <p class="notifal-onpage-ai-hosted-shell__noti-message" id="notifal-onpage-ai-noti-message"></p>
                    </div>
                </div>
                <div class="notifal-ai-wizard__companion notifal-sr-only" aria-hidden="true">
                    <?php
                    AiCharacter::render(
                        [
                            'id'              => 'notifal-onpage-ai-wizard-character',
                            'size'            => 'sm',
                            'variant'         => 'compact',
                            'show_tip'        => true,
                            'tip_dismissible' => false,
                            'auto_start'      => false,
                            'click_to_talk'   => false,
                            'storage_key'     => AiCharacter::STORAGE_PREFIX . 'wizard',
                            'mode'            => 'once',
                            'phrases'         => [],
                            'extra_class'     => 'notifal-ai-wizard__companion-character',
                        ]
                    );
                    ?>
                </div>
            </div>
        </div>

        <div class="notifal-modal-body notifal-ai-wizard-shell__body notifal-onpage-ai-hosted-shell__body">
            <div
                class="notifal-ai-unavailable-host notifal-hidden"
                id="notifal-onpage-ai-unavailable-host"
                hidden
            ></div>
            <div class="notifal-ai-wizard" id="notifal-onpage-ai-hosted-panel">
                <div class="notifal-ai-wizard__body">
                    <span class="notifal-sr-only" id="notifal-onpage-ai-prompt-title"><?php esc_html_e('Generate Notification with Notifal AI', 'notifal'); ?></span>
                    <p class="notifal-onpage-ai-hosted-shell__step-hint" id="notifal-onpage-ai-wizard-lead"><?php esc_html_e('Select your industry to personalize your AI experience', 'notifal'); ?></p>
                    <span class="notifal-sr-only" id="notifal-onpage-ai-wizard-eyebrow"></span>
                    <span class="notifal-sr-only" id="notifal-onpage-ai-wizard-title"></span>

                    <div class="notifal-onpage-ai-wizard-step" data-notifal-ai-step="industry">
                        <?php // Industry cards are rendered like use-case cards (horizontal icon + copy). ?>
                        <div class="notifal-ai-wizard__industry-grid notifal-ai-wizard__option-grid notifal-onpage-ai-hosted-shell__industry-grid" id="notifal-onpage-ai-industry-grid" role="listbox" aria-label="<?php esc_attr_e('Your industry', 'notifal'); ?>"></div>
                    </div>

                    <div class="notifal-onpage-ai-wizard-step notifal-hidden" data-notifal-ai-step="business">
                        <div class="notifal-ai-wizard__field notifal-ai-wizard__field--full">
                            <label class="notifal-sr-only" for="notifal-onpage-ai-business-detail"><?php esc_html_e('Tell us about your business', 'notifal'); ?></label>
                            <textarea
                                id="notifal-onpage-ai-business-detail"
                                class="notifal-ai-wizard__textarea notifal-onpage-ai-hosted-shell__business-textarea"
                                rows="4"
                                maxlength="600"
                                placeholder="<?php esc_attr_e('e.g. We provide sustainable packaging for e-commerce brands, focusing on biodegradable materials and custom branding…', 'notifal'); ?>"
                            ></textarea>
                        </div>
                    </div>

                    <div class="notifal-onpage-ai-wizard-step notifal-hidden" data-notifal-ai-step="goal">
                        <div class="notifal-ai-wizard__option-grid notifal-onpage-ai-hosted-shell__goal-grid" id="notifal-onpage-ai-use-case-grid" role="listbox" aria-label="<?php esc_attr_e('Notification goal', 'notifal'); ?>"></div>
                    </div>

                    <div class="notifal-onpage-ai-wizard-step notifal-hidden" data-notifal-ai-step="layout">
                        <div class="notifal-ai-wizard__layout-grid notifal-onpage-ai-hosted-shell__layout-grid" id="notifal-onpage-ai-layout-grid"></div>
                    </div>

                    <div class="notifal-onpage-ai-wizard-step notifal-hidden" data-notifal-ai-step="palette">
                        <div class="notifal-onpage-ai-hosted-shell__palette-row" id="notifal-onpage-ai-palette-row"></div>
                        <?php // Hidden while "Let AI decide" is selected (toggled in onpage-ai-prompt.js). ?>
                        <div class="notifal-onpage-ai-hosted-shell__palette-custom" id="notifal-onpage-ai-palette-custom">
                            <p class="notifal-onpage-ai-hosted-shell__palette-custom-label"><?php esc_html_e('Custom colors', 'notifal'); ?></p>
                            <div class="notifal-ai-wizard__selected-colors" id="notifal-onpage-ai-selected-colors"></div>
                            <div class="notifal-ai-wizard__color-custom">
                                <label class="notifal-ai-wizard__label notifal-sr-only" for="notifal-onpage-ai-color"><?php esc_html_e('Add a brand color', 'notifal'); ?></label>
                                <input type="color" id="notifal-onpage-ai-color-picker" class="notifal-ai-wizard__color-picker" value="#7e2bd2" aria-label="<?php esc_attr_e('Add a brand color', 'notifal'); ?>">
                                <input type="text" id="notifal-onpage-ai-color" class="notifal-ai-wizard__input notifal-ai-wizard__input--color" value="#7e2bd2" spellcheck="false" autocomplete="off" maxlength="7">
                                <button type="button" class="notifal-ai-wizard__color-add" id="notifal-onpage-ai-color-add">
                                    <?php esc_html_e('Add color', 'notifal'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="notifal-onpage-ai-wizard-step notifal-hidden" data-notifal-ai-step="describe">
                        <div class="notifal-ai-wizard__field notifal-ai-wizard__field--full">
                            <label class="notifal-sr-only" for="notifal-onpage-ai-goal"><?php esc_html_e('Write your prompt', 'notifal'); ?></label>
                            <div class="notifal-onpage-ai-hosted-shell__prompt-compose">
                                <textarea id="notifal-onpage-ai-goal" class="notifal-ai-wizard__textarea notifal-onpage-ai-hosted-shell__prompt-textarea" rows="4" placeholder="<?php esc_attr_e('e.g. Create a sleek exit-intent popup offering a 15% discount for first-time buyers…', 'notifal'); ?>"></textarea>
                                <div class="notifal-onpage-ai-hosted-shell__prompt-toolbar">
                                    <div
                                        class="notifal-onpage-ai-hosted-shell__prompt-attachments"
                                        id="notifal-onpage-ai-prompt-attachments"
                                        aria-live="polite"
                                    ></div>
                                    <div class="notifal-onpage-ai-hosted-shell__prompt-toolbar-actions">
                                        <button
                                            type="button"
                                            class="notifal-onpage-ai-hosted-shell__prompt-attach"
                                            id="notifal-onpage-ai-prompt-attach"
                                            aria-label="<?php esc_attr_e('Attach reference images', 'notifal'); ?>"
                                        >
                                            <?php
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- NotifalIconService::render() escapes via esc_attr().
                                            // Preferred permanent icon name when available: "image". Using cloud-arrow-up until that glyph is added.
                                            echo NotifalIconService::render('cloud-arrow-up', 16);
                                            ?>
                                        </button>
                                        <span class="notifal-onpage-ai-hosted-shell__prompt-credit-tip">
                                            <?php
                                            // Compact credit tip via shared question-circle tooltip pattern.
                                            FieldRenderer::tooltip(
                                                __('Reference images use more credits, and usually match your UI more accurately.', 'notifal')
                                            );
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                <input
                                    type="file"
                                    id="notifal-onpage-ai-prompt-image-input"
                                    class="notifal-hidden"
                                    accept="image/jpeg,image/png,image/webp,image/gif"
                                    multiple
                                    hidden
                                >
                            </div>
                        </div>
                        <div class="notifal-onpage-ai-hosted-shell__tips">
                            <p class="notifal-onpage-ai-hosted-shell__tips-heading"><?php esc_html_e('Quick tips & inspiration', 'notifal'); ?></p>
                            <div class="notifal-onpage-ai-hosted-shell__tips-grid" id="notifal-onpage-ai-prompt-tips"></div>
                        </div>
                    </div>

                    <div class="notifal-onpage-ai-wizard-step notifal-hidden" data-notifal-ai-step="connect">
                        <div class="notifal-onpage-ai-hosted-shell__connect" id="notifal-onpage-ai-connection-panel"></div>
                    </div>

                    <div class="notifal-onpage-ai-wizard-step notifal-hidden" data-notifal-ai-step="generating">
                        <div class="notifal-ai-wizard__generating notifal-onpage-ai-hosted-shell__generating" id="notifal-onpage-ai-generating-host"></div>
                    </div>

                    <div class="notifal-onpage-ai-wizard-step notifal-hidden" data-notifal-ai-step="success">
                        <div class="notifal-ai-wizard__success">
                            <div class="notifal-ai-wizard__success-layout">
                                <div class="notifal-ai-wizard__success-panel notifal-ai-wizard__success-panel--top">
                                    <h3 class="notifal-ai-wizard__title" id="notifal-onpage-ai-success-title"><?php esc_html_e('Draft notification created', 'notifal'); ?></h3>
                                    <p class="notifal-ai-wizard__lead" id="notifal-onpage-ai-success-message"><?php esc_html_e('Your AI notification was imported as a disabled draft. Review settings, then enable it when ready.', 'notifal'); ?></p>
                                </div>
                                <div class="notifal-ai-wizard__success-stage" id="notifal-onpage-ai-success-preview"></div>
                                <div class="notifal-ai-wizard__success-meta-host" id="notifal-onpage-ai-success-meta"></div>
                                <div class="notifal-ai-wizard__success-panel notifal-ai-wizard__success-panel--bottom">
                                    <div class="notifal-ai-wizard__success-noti-tip notifal-hidden" id="notifal-onpage-ai-success-noti-tip" hidden>
                                        <div class="notifal-ai-wizard__success-noti-tip-icon" aria-hidden="true">
                                            <?php
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- NotifalIconService::render() escapes via esc_attr().
                                            echo NotifalIconService::render('ai', 18);
                                            ?>
                                        </div>
                                        <p class="notifal-ai-wizard__success-noti-tip-text" id="notifal-onpage-ai-success-noti-tip-text">
                                            <?php esc_html_e('Want to apply some changes on the template? Open the builder and talk with Noti, Notifal AI, to change anything you need!', 'notifal'); ?>
                                        </p>
                                    </div>
                                    <div class="notifal-ai-wizard__success-feedback notifal-hidden" id="notifal-onpage-ai-success-feedback" hidden>
                                        <p class="notifal-ai-wizard__success-feedback-prompt" id="notifal-onpage-ai-success-feedback-prompt">
                                            <?php esc_html_e('How was this AI result?', 'notifal'); ?>
                                        </p>
                                        <div class="notifal-ai-wizard__success-feedback-actions" id="notifal-onpage-ai-success-feedback-actions">
                                            <button type="button" class="notifal-ai-wizard__feedback-btn" id="notifal-onpage-ai-feedback-helpful" data-notifal-ai-feedback="helpful">
                                                <?php
                                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- NotifalIconService::render() escapes via esc_attr().
                                                echo NotifalIconService::render('emoji-smile', 16);
                                                ?>
                                                <span><?php esc_html_e('Helpful', 'notifal'); ?></span>
                                            </button>
                                            <button type="button" class="notifal-ai-wizard__feedback-btn" id="notifal-onpage-ai-feedback-needs-improvement" data-notifal-ai-feedback="needs_improvement">
                                                <?php
                                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- NotifalIconService::render() escapes via esc_attr().
                                                echo NotifalIconService::render('emoji-frown', 16);
                                                ?>
                                                <span><?php esc_html_e('Needs improvement', 'notifal'); ?></span>
                                            </button>
                                        </div>
                                        <p class="notifal-ai-wizard__success-feedback-thanks notifal-hidden" id="notifal-onpage-ai-success-feedback-thanks" hidden></p>
                                    </div>
                                    <div class="notifal-ai-wizard__nav-actions notifal-ai-wizard__success-actions">
                                        <button type="button" class="notifal-ai-wizard__cta notifal-ai-wizard__cta--secondary" id="notifal-onpage-ai-wizard-open-draft">
                                            <?php
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- NotifalIconService::render() escapes via esc_attr().
                                            echo NotifalIconService::render('pencil-square', 14);
                                            ?>
                                            <span><?php esc_html_e('Open notification settings', 'notifal'); ?></span>
                                        </button>
                                        <button type="button" class="notifal-ai-wizard__cta notifal-hidden" id="notifal-onpage-ai-wizard-open-builder" hidden>
                                            <?php
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- NotifalIconService::render() escapes via esc_attr().
                                            echo NotifalIconService::render('ai', 14);
                                            ?>
                                            <span><?php esc_html_e('Edit template with AI', 'notifal'); ?></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="notifal-ai-wizard-shell__sticky-bottom notifal-onpage-ai-hosted-shell__footer" id="notifal-onpage-ai-wizard-nav">
            <div class="notifal-ai-wizard__error notifal-hidden" id="notifal-onpage-ai-wizard-error" role="alert" aria-live="assertive" hidden>
                <span class="notifal-ai-wizard__error-icon" aria-hidden="true">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- NotifalIconService::render() escapes via esc_attr().
                    echo NotifalIconService::render('exclamation-octagon', 16);
                    ?>
                </span>
                <p class="notifal-ai-wizard__error-text" id="notifal-onpage-ai-wizard-error-text"></p>
            </div>
            <div class="notifal-ai-wizard-shell__nav-row">
                <button type="button" class="notifal-ai-wizard__nav-back notifal-hidden" id="notifal-onpage-ai-wizard-back">
                    <span class="notifal-icon notifal-icon-arrow-left-short size-14" aria-hidden="true"></span>
                    <?php esc_html_e('Back', 'notifal'); ?>
                </button>
                <div class="notifal-ai-wizard__nav-actions">
                    <button type="button" class="notifal-ai-wizard__cta notifal-onpage-ai-hosted-shell__cta" id="notifal-onpage-ai-wizard-next">
                        <span id="notifal-onpage-ai-wizard-next-label"><?php esc_html_e('Continue', 'notifal'); ?></span>
                        <span class="notifal-icon notifal-icon-chevron-right size-14" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
