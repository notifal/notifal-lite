<?php

/**
 * Form edit: fields tab (structure definitions).
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 *
 * @var array<int, array<string, mixed>> $fields Stored field definitions.
 */

use Notifal\Shared\Services\NotifalIconService;

defined('ABSPATH') || exit;
?>

<div class="notifal-tab notifal-tab-panel notifal-form-tab-panel" id="notifal-form-tab-fields" data-tab-panel="fields">
    <div class="notifal-form-section notifal-form-section--flush">
        <h2 class="notifal-form-section__title"><?php echo esc_html__('Fields', 'notifal'); ?></h2>
        <p class="notifal-form-section__description">
            <?php echo esc_html__('Define which fields this form collects. Field names and validation stay here; step layout and visual design are configured in the HTML Builder when this form is used in a template.', 'notifal'); ?>
        </p>
    </div>

    <div class="notifal-form-panels">
        <article class="notifal-form-panel-card" id="notifal-form-fields-panel">
            <header class="notifal-form-panel-card__header notifal-form-panel-card__header--static">
                <span class="notifal-form-panel-card__heading">
                    <span class="notifal-form-panel-card__icon" aria-hidden="true">
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- NotifalIconService::render() escapes icon markup via esc_attr().
                        echo NotifalIconService::render('sliders2', 20);
                        ?>
                    </span>
                    <span class="notifal-form-panel-card__heading-text">
                        <span class="notifal-form-panel-card__title"><?php echo esc_html__('Form fields', 'notifal'); ?></span>
                        <span class="notifal-form-panel-card__description">
                            <?php echo esc_html__('Configure labels, types, validation, and optional advanced rules for each field.', 'notifal'); ?>
                        </span>
                    </span>
                </span>
            </header>

            <div class="notifal-form-panel-card__body">
                <div
                    id="notifal-form-fields-root"
                    class="notifal-form-fields-root"
                    data-initial-fields="<?php echo esc_attr(wp_json_encode($fields)); ?>"
                ></div>

                <button type="button" class="notifal-form-add-field" id="notifal-form-add-field">
                    <span class="notifal-form-add-field__icon" aria-hidden="true">
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- NotifalIconService::render() escapes icon markup via esc_attr().
                        echo NotifalIconService::render('plus-circle', 18);
                        ?>
                    </span>
                    <?php echo esc_html__('Add field', 'notifal'); ?>
                </button>

                <input type="hidden" name="form_fields" id="notifal-form-fields-input" value="" />
            </div>
        </article>
    </div>
</div>
