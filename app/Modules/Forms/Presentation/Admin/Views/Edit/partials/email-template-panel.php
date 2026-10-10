<?php

/**
 * Reusable email template panel for admin or visitor notifications.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 *
 * @var string               $panelId          Unique DOM id prefix.
 * @var string               $panelRole        admin|visitor.
 * @var string               $panelTitle       Card heading.
 * @var string               $panelDesc        Card description.
 * @var bool                 $panelEnabled     Whether this notification is enabled.
 * @var string               $enableFieldName  Checkbox name for the enable toggle.
 * @var array<string, mixed> $meta             Form meta.
 * @var array<string, mixed> $emailDefaults    Default templates bundle.
 * @var array<string, mixed> $emailVariables   Merge tags grouped for UI.
 * @var array<int, array<string, mixed>> $fields Form field definitions.
 */

defined('ABSPATH') || exit;

use Notifal\Modules\Forms\Application\Services\FormEmailTemplateDefaults;
use Notifal\Shared\Services\NotifalIconService;

// Resolve meta keys based on panel role.
$formatKey = $panelRole === 'admin' ? 'admin_email_format' : 'confirmation_email_format';
$subjectKey = $panelRole === 'admin' ? 'admin_email_subject' : 'confirmation_email_subject';
$bodyKey = $panelRole === 'admin' ? 'admin_email_body' : 'confirmation_email_body';
$defaultsKey = $panelRole === 'admin' ? 'admin' : 'visitor';
$storedFormat = (new FormEmailTemplateDefaults())->normalizeFormat((string) ($meta[$formatKey] ?? 'plain'));
$defaultSubject = (string) ($emailDefaults[$defaultsKey]['subject'] ?? '');
$defaultPlain = (string) ($emailDefaults[$defaultsKey]['plain'] ?? '');
$defaultHtml = (string) ($emailDefaults[$defaultsKey]['html'] ?? '');
$defaultBodyForFormat = $storedFormat === 'html' ? $defaultHtml : $defaultPlain;

// Prefill empty stored values with the active format defaults so the editor holds the template.
$storedSubject = (string) ($meta[$subjectKey] ?? '');
$storedBody = (string) ($meta[$bodyKey] ?? '');
$displaySubject = $storedSubject !== '' ? $storedSubject : $defaultSubject;
$displayBody = $storedBody !== '' ? $storedBody : $defaultBodyForFormat;

// Collect email-type fields for visitor recipient select.
$emailFieldOptions = [];
foreach ($fields as $fieldRow) {
    $fieldName = isset($fieldRow['name']) ? sanitize_key((string) $fieldRow['name']) : '';
    if ($fieldName === '') {
        continue;
    }
    $fieldType = isset($fieldRow['type']) ? sanitize_key((string) $fieldRow['type']) : 'text';
    if ($fieldType === 'email' || $fieldName === 'email') {
        $fieldLabel = isset($fieldRow['label']) ? sanitize_text_field((string) $fieldRow['label']) : $fieldName;
        $emailFieldOptions[$fieldName] = $fieldLabel;
    }
}

$cardClasses = 'notifal-form-panel-card';
if (!$panelEnabled) {
    // Disabled panels start collapsed so unused settings stay out of the way.
    $cardClasses .= ' notifal-form-panel-card--disabled notifal-form-panel-card--collapsed';
}
?>

<article
    class="<?php echo esc_attr($cardClasses); ?>"
    id="<?php echo esc_attr($panelId); ?>"
    data-email-panel="<?php echo esc_attr($panelRole); ?>"
>
    <header class="notifal-form-panel-card__header">
        <button
            type="button"
            class="notifal-form-panel-card__collapse"
            data-panel-collapse-toggle="1"
            aria-expanded="<?php echo $panelEnabled ? 'true' : 'false'; ?>"
            aria-controls="<?php echo esc_attr($panelId); ?>-body"
        >
            <span class="notifal-form-panel-card__heading">
                <span class="notifal-form-panel-card__icon" aria-hidden="true">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- NotifalIconService::render() escapes icon markup via esc_attr().
                    echo NotifalIconService::render($panelRole === 'admin' ? 'megaphone' : 'envelope', 20);
                    ?>
                </span>
                <span class="notifal-form-panel-card__heading-text">
                    <span class="notifal-form-panel-card__title"><?php echo esc_html($panelTitle); ?></span>
                    <span class="notifal-form-panel-card__description"><?php echo esc_html($panelDesc); ?></span>
                </span>
            </span>
            <span class="notifal-form-panel-card__chevron" aria-hidden="true">
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- NotifalIconService::render() escapes icon markup via esc_attr().
                echo NotifalIconService::render('chevron-up1', 18);
                ?>
            </span>
        </button>

        <label class="notifal-form-panel-card__toggle" for="<?php echo esc_attr($panelId); ?>-enabled">
            <span class="notifal-form-panel-card__toggle-label"><?php echo esc_html__('Enabled', 'notifal'); ?></span>
            <span class="notifal-toggle-container">
                <input
                    type="checkbox"
                    class="notifal-toggle-input"
                    id="<?php echo esc_attr($panelId); ?>-enabled"
                    name="<?php echo esc_attr($enableFieldName); ?>"
                    value="1"
                    data-panel-enable-toggle="1"
                    <?php checked($panelEnabled); ?>
                />
                <span class="notifal-toggle-slider"></span>
            </span>
        </label>
    </header>

    <div
        class="notifal-form-panel-card__body"
        id="<?php echo esc_attr($panelId); ?>-body"
        data-panel-body="1"
        data-email-panel-body="1"
        <?php echo $panelEnabled ? '' : 'hidden'; ?>
    >
        <?php if ($panelRole === 'admin') : ?>
            <div class="notifal-form-field-row">
                <label class="notifal-form-field-label" for="<?php echo esc_attr($panelId); ?>-recipient">
                    <?php echo esc_html__('Recipient email', 'notifal'); ?>
                </label>
                <input
                    type="text"
                    id="<?php echo esc_attr($panelId); ?>-recipient"
                    name="admin_email"
                    class="notifal-form-field-input"
                    value="<?php echo esc_attr((string) ($meta['admin_email'] ?? '')); ?>"
                    autocomplete="email"
                    placeholder="<?php echo esc_attr__('admin@example.com, team@example.com', 'notifal'); ?>"
                />
                <p class="notifal-form-field-hint">
                    <?php echo esc_html__('Add one or more addresses separated by a comma (,). Leave empty to use the default from Forms settings.', 'notifal'); ?>
                </p>
            </div>
        <?php else : ?>
            <div class="notifal-form-field-row">
                <label class="notifal-form-field-label" for="<?php echo esc_attr($panelId); ?>-submitter-field">
                    <?php echo esc_html__('Visitor email field', 'notifal'); ?>
                </label>
                <?php if ($emailFieldOptions !== []) : ?>
                    <select
                        id="<?php echo esc_attr($panelId); ?>-submitter-field"
                        name="submitter_email_field"
                        class="notifal-form-field-input"
                        data-email-submitter-field="1"
                    >
                        <?php foreach ($emailFieldOptions as $optionName => $optionLabel) : ?>
                            <option value="<?php echo esc_attr($optionName); ?>" <?php selected((string) ($meta['submitter_email_field'] ?? 'email'), $optionName); ?>>
                                <?php echo esc_html($optionLabel . ' (' . $optionName . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else : ?>
                    <input
                        type="text"
                        id="<?php echo esc_attr($panelId); ?>-submitter-field"
                        name="submitter_email_field"
                        class="notifal-form-field-input"
                        data-email-submitter-field="1"
                        value="<?php echo esc_attr((string) ($meta['submitter_email_field'] ?? 'email')); ?>"
                    />
                <?php endif; ?>
                <p class="notifal-form-field-hint">
                    <?php echo esc_html__('Which form field contains the visitor email address.', 'notifal'); ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="notifal-form-field-row">
            <label class="notifal-form-field-label" for="<?php echo esc_attr($panelId); ?>-subject">
                <?php echo esc_html__('Email subject', 'notifal'); ?>
            </label>
            <input
                type="text"
                id="<?php echo esc_attr($panelId); ?>-subject"
                name="<?php echo esc_attr($subjectKey); ?>"
                class="notifal-form-field-input"
                value="<?php echo esc_attr($displaySubject); ?>"
                data-email-subject="1"
            />
            <p class="notifal-form-field-hint">
                <?php echo esc_html__('Edit the subject or insert variables below.', 'notifal'); ?>
            </p>
        </div>

        <div class="notifal-form-field-row">
            <span class="notifal-form-field-label"><?php echo esc_html__('Message format', 'notifal'); ?></span>
            <div class="notifal-form-email-format" role="radiogroup" aria-label="<?php echo esc_attr__('Message format', 'notifal'); ?>">
                <label class="notifal-form-email-format__option">
                    <input
                        type="radio"
                        name="<?php echo esc_attr($formatKey); ?>"
                        value="plain"
                        <?php checked($storedFormat, 'plain'); ?>
                        data-email-format="plain"
                    />
                    <span><?php echo esc_html__('Plain text', 'notifal'); ?></span>
                </label>
                <label class="notifal-form-email-format__option">
                    <input
                        type="radio"
                        name="<?php echo esc_attr($formatKey); ?>"
                        value="html"
                        <?php checked($storedFormat, 'html'); ?>
                        data-email-format="html"
                    />
                    <span><?php echo esc_html__('HTML', 'notifal'); ?></span>
                </label>
            </div>
        </div>

        <div class="notifal-form-field-row">
            <label class="notifal-form-field-label" for="<?php echo esc_attr($panelId); ?>-body">
                <?php echo esc_html__('Email message', 'notifal'); ?>
            </label>
            <textarea
                id="<?php echo esc_attr($panelId); ?>-body"
                name="<?php echo esc_attr($bodyKey); ?>"
                class="notifal-form-field-input notifal-form-email-body"
                rows="10"
                data-email-body="1"
            ><?php echo esc_textarea($displayBody); ?></textarea>
            <p class="notifal-form-field-hint">
                <?php echo esc_html__('The default template for the selected format is loaded here. Edit it freely and use variables below.', 'notifal'); ?>
            </p>
        </div>

        <div
            class="notifal-form-email-preview"
            data-email-html-preview-wrap="1"
            <?php echo $storedFormat !== 'html' ? 'hidden' : ''; ?>
        >
            <div class="notifal-form-email-preview__header">
                <h4 class="notifal-form-email-preview__title"><?php echo esc_html__('HTML preview', 'notifal'); ?></h4>
                <p class="notifal-form-email-preview__hint">
                    <?php echo esc_html__('Live preview of your HTML message. Merge tags stay as placeholders until an email is sent.', 'notifal'); ?>
                </p>
            </div>
            <iframe
                class="notifal-form-email-preview__frame"
                data-email-html-preview="1"
                title="<?php echo esc_attr__('HTML email preview', 'notifal'); ?>"
                sandbox="allow-same-origin"
            ></iframe>
        </div>

        <div class="notifal-form-email-variables">
            <div class="notifal-form-email-variables__header">
                <h4 class="notifal-form-email-variables__title"><?php echo esc_html__('Insert variables', 'notifal'); ?></h4>
                <p class="notifal-form-email-variables__hint">
                    <?php echo esc_html__('Click a tag to insert it into the subject or message field.', 'notifal'); ?>
                </p>
            </div>

            <div class="notifal-form-email-variables__group">
                <span class="notifal-form-email-variables__label"><?php echo esc_html__('Site & submission', 'notifal'); ?></span>
                <div class="notifal-form-email-variables__tags">
                    <?php foreach (($emailVariables['system'] ?? []) as $variable) : ?>
                        <?php
                        $tagKey = isset($variable['key']) ? sanitize_key((string) $variable['key']) : '';
                        if ($tagKey === '') {
                            continue;
                        }
                        $tagLabel = isset($variable['label']) ? (string) $variable['label'] : $tagKey;
                        ?>
                        <button
                            type="button"
                            class="notifal-tag notifal-form-email-variable"
                            data-email-variable="<?php echo esc_attr('{' . $tagKey . '}'); ?>"
                            data-email-panel="<?php echo esc_attr($panelId); ?>"
                            title="<?php echo esc_attr(isset($variable['description']) ? (string) $variable['description'] : $tagLabel); ?>"
                        >
                            <span class="notifal-tag-text"><?php echo esc_html('{' . $tagKey . '}'); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php
            // Always render the Form fields group so JS can refresh tags live from the Fields tab.
            $hasFieldVariables = !empty($emailVariables['fields']);
            ?>
            <div class="notifal-form-email-variables__group" data-email-field-variables-group="1">
                <span class="notifal-form-email-variables__label"><?php echo esc_html__('Form fields', 'notifal'); ?></span>
                <div
                    class="notifal-form-email-variables__tags"
                    data-email-field-variables="1"
                    <?php echo !$hasFieldVariables ? 'hidden' : ''; ?>
                >
                    <?php if ($hasFieldVariables) : ?>
                        <?php foreach ($emailVariables['fields'] as $variable) : ?>
                            <?php
                            $tagKey = isset($variable['key']) ? sanitize_key((string) $variable['key']) : '';
                            if ($tagKey === '') {
                                continue;
                            }
                            $tagLabel = isset($variable['label']) ? (string) $variable['label'] : $tagKey;
                            ?>
                            <button
                                type="button"
                                class="notifal-tag notifal-form-email-variable"
                                data-email-variable="<?php echo esc_attr('{' . $tagKey . '}'); ?>"
                                data-email-panel="<?php echo esc_attr($panelId); ?>"
                                title="<?php echo esc_attr(isset($variable['description']) ? (string) $variable['description'] : $tagLabel); ?>"
                            >
                                <span class="notifal-tag-text"><?php echo esc_html('{' . $tagKey . '}'); ?></span>
                            </button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <p
                    class="notifal-form-field-hint notifal-form-email-variables__empty"
                    data-email-field-variables-empty="1"
                    <?php echo $hasFieldVariables ? 'hidden' : ''; ?>
                >
                    <?php echo esc_html__('Add fields on the Fields tab to use field variables in emails.', 'notifal'); ?>
                </p>
            </div>
        </div>
    </div>
</article>
