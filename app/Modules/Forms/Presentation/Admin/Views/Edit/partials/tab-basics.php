<?php

/**
 * Form edit: basics tab (identity + presentation synced with HTML Builder).
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 *
 * @var string               $title  Form title.
 * @var int                  $formId Form CPT ID.
 * @var array<string, mixed> $meta   Form meta for admin.
 */

defined('ABSPATH') || exit;

use Notifal\Core\Foundation\Container;
use Notifal\Domain\Settings\Services\SettingsService;
use Notifal\Modules\Forms\Application\Services\FormsSettingsService;
use Notifal\Modules\Forms\Config\AttributeContract;
use Notifal\Shared\Services\NotifalIconService;

// Normalize after-submit behavior for the select control.
$successBehavior = isset($meta['success_behavior'])
    ? sanitize_key((string) $meta['success_behavior'])
    : 'message';
if (!in_array($successBehavior, AttributeContract::SUCCESS_BEHAVIORS, true)) {
    $successBehavior = 'message';
}

// Normalize brand color for the color inputs.
$primaryColor = isset($meta['primary_color'])
    ? (string) $meta['primary_color']
    : '#7e2bd2';
$sanitizedPrimary = sanitize_hex_color($primaryColor);
$primaryColor = is_string($sanitizedPrimary) && $sanitizedPrimary !== ''
    ? $sanitizedPrimary
    : '#7e2bd2';

// Normalize captcha mode for the select control.
$captcha = isset($meta['captcha'])
    ? sanitize_key((string) $meta['captcha'])
    : 'inherit';
if (!in_array($captcha, AttributeContract::CAPTCHA_MODES, true)) {
    $captcha = 'inherit';
}

// Per-form skip UI only when Settings → Forms has a captcha provider.
$formsSettings = new FormsSettingsService(Container::getInstance()->get(SettingsService::class));
$globalCaptchaEnabled = $formsSettings->isGlobalCaptchaEnabled();
// Admin UI only offers inherit vs skip (provider lives in Forms settings).
$captchaUi = ($captcha === 'none') ? 'none' : 'inherit';
?>

<div class="notifal-tab notifal-tab-panel notifal-form-tab-panel active" id="notifal-form-tab-basics" data-tab-panel="basics">
    <div class="notifal-form-section notifal-form-section--flush">
        <h2 class="notifal-form-section__title"><?php echo esc_html__('Basics', 'notifal'); ?></h2>
        <p class="notifal-form-section__description">
            <?php echo esc_html__('Name your form and set after-submit, brand, and multi-step defaults. These stay synced with the HTML Builder when the form is connected.', 'notifal'); ?>
        </p>
    </div>

    <div class="notifal-form-panels">
        <article class="notifal-form-panel-card" id="notifal-form-basics-identity">
            <header class="notifal-form-panel-card__header notifal-form-panel-card__header--static">
                <span class="notifal-form-panel-card__heading">
                    <span class="notifal-form-panel-card__icon" aria-hidden="true">
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- NotifalIconService::render() escapes icon markup via esc_attr().
                        echo NotifalIconService::render('layers', 20);
                        ?>
                    </span>
                    <span class="notifal-form-panel-card__heading-text">
                        <span class="notifal-form-panel-card__title"><?php echo esc_html__('Form identity', 'notifal'); ?></span>
                        <span class="notifal-form-panel-card__description">
                            <?php echo esc_html__('Name this form so you can find it later and connect it to templates.', 'notifal'); ?>
                        </span>
                    </span>
                </span>
            </header>

            <div class="notifal-form-panel-card__body">
                <div class="notifal-form-field-row">
                    <label class="notifal-form-field-label" for="form_title">
                        <?php echo esc_html__('Title', 'notifal'); ?>
                    </label>
                    <input
                        type="text"
                        id="form_title"
                        name="form_title"
                        class="notifal-form-field-input"
                        value="<?php echo esc_attr($title); ?>"
                        autocomplete="off"
                    />
                    <?php if ($formId > 0) : ?>
                        <p class="notifal-form-field-hint">
                            <?php
                            printf(
                                /* translators: %d: form CPT ID */
                                esc_html__('Connect in templates with data-form-id="%d".', 'notifal'),
                                (int) $formId
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="notifal-form-field-row">
                    <label class="notifal-form-field-label" for="notes">
                        <?php echo esc_html__('Internal notes', 'notifal'); ?>
                    </label>
                    <textarea
                        id="notes"
                        name="notes"
                        class="notifal-form-field-input"
                        rows="3"
                        placeholder="<?php echo esc_attr__('Optional notes for your team (not shown to visitors).', 'notifal'); ?>"
                    ><?php echo esc_textarea((string) $meta['notes']); ?></textarea>
                </div>
            </div>
        </article>

        <article class="notifal-form-panel-card" id="notifal-form-basics-after-submit">
            <header class="notifal-form-panel-card__header notifal-form-panel-card__header--static">
                <span class="notifal-form-panel-card__heading">
                    <span class="notifal-form-panel-card__icon" aria-hidden="true">
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- NotifalIconService::render() escapes icon markup via esc_attr().
                        echo NotifalIconService::render('envelope', 20);
                        ?>
                    </span>
                    <span class="notifal-form-panel-card__heading-text">
                        <span class="notifal-form-panel-card__title"><?php echo esc_html__('After submit', 'notifal'); ?></span>
                        <span class="notifal-form-panel-card__description">
                            <?php echo esc_html__('Choose the confirmation message, redirect, and error text visitors see.', 'notifal'); ?>
                        </span>
                    </span>
                </span>
            </header>

            <div class="notifal-form-panel-card__body">
                <div class="notifal-form-field-row">
                    <label class="notifal-form-field-label" for="success_behavior">
                        <?php echo esc_html__('After success', 'notifal'); ?>
                    </label>
                    <select id="success_behavior" name="success_behavior" class="notifal-form-field-input">
                        <option value="message" <?php selected($successBehavior, 'message'); ?>>
                            <?php echo esc_html__('Show confirmation message', 'notifal'); ?>
                        </option>
                        <option value="redirect" <?php selected($successBehavior, 'redirect'); ?>>
                            <?php echo esc_html__('Redirect to a page', 'notifal'); ?>
                        </option>
                        <option value="both" <?php selected($successBehavior, 'both'); ?>>
                            <?php echo esc_html__('Show message, then redirect', 'notifal'); ?>
                        </option>
                    </select>
                </div>

                <div
                    class="notifal-form-field-row notifal-form-success-message-row<?php echo $successBehavior === 'redirect' ? ' is-hidden' : ''; ?>"
                    data-success-field="message"
                    <?php echo $successBehavior === 'redirect' ? 'hidden' : ''; ?>
                >
                    <label class="notifal-form-field-label" for="success_message">
                        <?php echo esc_html__('Success message', 'notifal'); ?>
                    </label>
                    <input
                        type="text"
                        id="success_message"
                        name="success_message"
                        class="notifal-form-field-input"
                        value="<?php echo esc_attr((string) ($meta['success_message'] ?? '')); ?>"
                        placeholder="<?php echo esc_attr__('Thanks! We will be in touch.', 'notifal'); ?>"
                    />
                </div>

                <div
                    class="notifal-form-field-row notifal-form-success-redirect-row<?php echo $successBehavior === 'message' ? ' is-hidden' : ''; ?>"
                    data-success-field="redirect"
                    <?php echo $successBehavior === 'message' ? 'hidden' : ''; ?>
                >
                    <label class="notifal-form-field-label" for="success_redirect">
                        <?php echo esc_html__('Redirect URL (same site)', 'notifal'); ?>
                    </label>
                    <input
                        type="text"
                        id="success_redirect"
                        name="success_redirect"
                        class="notifal-form-field-input"
                        value="<?php echo esc_attr((string) ($meta['success_redirect'] ?? '')); ?>"
                        placeholder="<?php echo esc_attr__('/thank-you/', 'notifal'); ?>"
                    />
                    <p class="notifal-form-field-hint">
                        <?php echo esc_html__('Only same-site paths or URLs are allowed.', 'notifal'); ?>
                    </p>
                </div>

                <div class="notifal-form-field-row">
                    <label class="notifal-form-field-label" for="error_message">
                        <?php echo esc_html__('Error message', 'notifal'); ?>
                    </label>
                    <input
                        type="text"
                        id="error_message"
                        name="error_message"
                        class="notifal-form-field-input"
                        value="<?php echo esc_attr((string) ($meta['error_message'] ?? '')); ?>"
                        placeholder="<?php echo esc_attr__('Something went wrong. Please try again.', 'notifal'); ?>"
                    />
                </div>
            </div>
        </article>

        <article class="notifal-form-panel-card" id="notifal-form-basics-style">
            <header class="notifal-form-panel-card__header notifal-form-panel-card__header--static">
                <span class="notifal-form-panel-card__heading">
                    <span class="notifal-form-panel-card__icon" aria-hidden="true">
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- NotifalIconService::render() escapes icon markup via esc_attr().
                        echo NotifalIconService::render('sliders2', 20);
                        ?>
                    </span>
                    <span class="notifal-form-panel-card__heading-text">
                        <span class="notifal-form-panel-card__title"><?php echo esc_html__('Style and options', 'notifal'); ?></span>
                        <span class="notifal-form-panel-card__description">
                            <?php
                            echo $globalCaptchaEnabled
                                ? esc_html__('Brand color, multi-step, and captcha applied when this form is connected in the builder.', 'notifal')
                                : esc_html__('Brand color and multi-step defaults applied when this form is connected in the builder.', 'notifal');
                            ?>
                        </span>
                    </span>
                </span>
            </header>

            <div class="notifal-form-panel-card__body">
                <div class="notifal-form-field-row">
                    <label class="notifal-form-field-label" for="primary_color">
                        <?php echo esc_html__('Brand color', 'notifal'); ?>
                    </label>
                    <div class="notifal-form-color-row">
                        <input
                            type="color"
                            id="primary_color_swatch"
                            class="notifal-form-color-swatch"
                            value="<?php echo esc_attr($primaryColor); ?>"
                            aria-label="<?php echo esc_attr__('Pick brand color', 'notifal'); ?>"
                        />
                        <input
                            type="text"
                            id="primary_color"
                            name="primary_color"
                            class="notifal-form-field-input"
                            value="<?php echo esc_attr($primaryColor); ?>"
                            autocomplete="off"
                        />
                    </div>
                    <p class="notifal-form-field-hint">
                        <?php echo esc_html__('Buttons and focus states use this color in templates.', 'notifal'); ?>
                    </p>
                </div>

                <div class="notifal-form-field-row notifal-form-field-row--checkbox">
                    <label for="multi_step">
                        <input
                            type="checkbox"
                            id="multi_step"
                            name="multi_step"
                            value="1"
                            <?php checked(!empty($meta['multi_step'])); ?>
                        />
                        <?php echo esc_html__('Multi-step form', 'notifal'); ?>
                    </label>
                    <div
                        class="notifal-form-multi-step-guide"
                        data-multi-step-guide
                        <?php echo empty($meta['multi_step']) ? ' hidden' : ''; ?>
                    >
                        <p class="notifal-form-field-hint">
                            <?php echo esc_html__('Visitors complete this form one step at a time on the live site.', 'notifal'); ?>
                        </p>
                        <div class="notifal-info-notice notifal-form-multi-step-notice">
                            <div>
                                <div>
                                    <strong><?php echo esc_html__('Set up steps in the HTML Builder', 'notifal'); ?></strong>
                                    <p class="notifal-form-field-hint">
                                        <?php echo esc_html__('You do not need to build the step layout yourself. Open Noti (Notifal AI) in the builder, tell it you need a multi-step form, and Noti will handle the steps, navigation, and markup for you.', 'notifal'); ?>
                                    </p>
                
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($globalCaptchaEnabled) : ?>
                    <div class="notifal-form-field-row">
                        <label class="notifal-form-field-label" for="captcha">
                            <?php echo esc_html__('Captcha', 'notifal'); ?>
                        </label>
                        <select id="captcha" name="captcha" class="notifal-form-field-input">
                            <option value="inherit" <?php selected($captchaUi, 'inherit'); ?>>
                                <?php echo esc_html__('Use Forms settings', 'notifal'); ?>
                            </option>
                            <option value="none" <?php selected($captchaUi, 'none'); ?>>
                                <?php echo esc_html__('Skip captcha for this form', 'notifal'); ?>
                            </option>
                        </select>
                        <p class="notifal-form-field-hint">
                            <?php echo esc_html__('Forms settings captcha applies to every form unless you skip it here.', 'notifal'); ?>
                        </p>
                    </div>
                <?php else : ?>
                    <?php // Keep presentation sync stable when global captcha is off. ?>
                    <input type="hidden" name="captcha" value="inherit" />
                <?php endif; ?>
            </div>
        </article>
    </div>
</div>
