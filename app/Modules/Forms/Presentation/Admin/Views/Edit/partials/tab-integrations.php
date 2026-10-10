<?php

/**
 * Form edit: integrations tab (webhook + Mailchimp panel cards).
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 *
 * @var array<string, mixed> $meta Form meta for admin.
 */

defined('ABSPATH') || exit;

use Notifal\Shared\Services\NotifalIconService;

// Resolve enable states for panel chrome.
$webhookEnabled = !empty($meta['webhook_enabled']);
$mailchimpEnabled = !empty($meta['mailchimp_enabled']);
?>

<div class="notifal-tab notifal-tab-panel notifal-form-tab-panel" id="notifal-form-tab-integrations" data-tab-panel="integrations">
    <div class="notifal-form-section notifal-form-section--flush">
        <h2 class="notifal-form-section__title"><?php echo esc_html__('Integrations', 'notifal'); ?></h2>
        <p class="notifal-form-section__description">
            <?php echo esc_html__('Connect this form to external tools. Enable an integration, then fill in its settings below.', 'notifal'); ?>
        </p>
    </div>

    <div class="notifal-form-panels">
        <?php
        // Webhook integration card.
        $panelId = 'notifal-form-integration-webhook';
        $panelEnabled = $webhookEnabled;
        $panelIcon = 'plug';
        $panelTitle = __('Webhook', 'notifal');
        $panelDesc = __('Send each submission as JSON to an HTTPS endpoint.', 'notifal');
        $enableFieldName = 'webhook_enabled';
        $cardClasses = 'notifal-form-panel-card';
        if (!$panelEnabled) {
            // Disabled integrations start collapsed so unused settings stay hidden.
            $cardClasses .= ' notifal-form-panel-card--disabled notifal-form-panel-card--collapsed';
        }
        ?>
        <article
            class="<?php echo esc_attr($cardClasses); ?>"
            id="<?php echo esc_attr($panelId); ?>"
            data-integration-panel="webhook"
            data-webhook-auth-set="<?php echo !empty($meta['webhook_auth_header_set']) ? '1' : '0'; ?>"
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
                            echo NotifalIconService::render($panelIcon, 20);
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
                <?php echo $panelEnabled ? '' : 'hidden'; ?>
            >
                <div class="notifal-form-field-row">
                    <label class="notifal-form-field-label" for="webhook_url">
                        <?php echo esc_html__('Webhook URL', 'notifal'); ?>
                    </label>
                    <input
                        type="url"
                        id="webhook_url"
                        name="webhook_url"
                        class="notifal-form-field-input"
                        value="<?php echo esc_attr((string) $meta['webhook_url']); ?>"
                        placeholder="<?php echo esc_attr__('https://example.com/hooks/notifal', 'notifal'); ?>"
                        autocomplete="off"
                    />
                    <p class="notifal-form-field-hint">
                        <?php echo esc_html__('Must use HTTPS. Submissions are posted as JSON.', 'notifal'); ?>
                    </p>
                </div>

                <div class="notifal-form-field-row">
                    <label class="notifal-form-field-label" for="webhook_auth_header">
                        <?php echo esc_html__('Authorization header', 'notifal'); ?>
                    </label>
                    <input
                        type="password"
                        id="webhook_auth_header"
                        name="webhook_auth_header"
                        class="notifal-form-field-input"
                        value=""
                        autocomplete="new-password"
                        placeholder="<?php echo !empty($meta['webhook_auth_header_set']) ? esc_attr__('Saved (leave blank to keep)', 'notifal') : esc_attr__('Bearer token or custom value', 'notifal'); ?>"
                    />
                    <p class="notifal-form-field-hint">
                        <?php echo esc_html__('Optional secret sent with each request. Leave blank to keep the saved value.', 'notifal'); ?>
                    </p>
                </div>

                <div class="notifal-form-field-row notifal-form-field-row--checkbox">
                    <label class="notifal-form-checkbox-label notifal-form-checkbox-label--muted">
                        <input type="checkbox" name="webhook_auth_header_clear" value="1" />
                        <span><?php echo esc_html__('Clear saved authorization header', 'notifal'); ?></span>
                    </label>
                </div>
            </div>
        </article>

        <?php
        // Mailchimp integration card.
        $panelId = 'notifal-form-integration-mailchimp';
        $panelEnabled = $mailchimpEnabled;
        $panelIcon = 'mailchimpicon';
        $panelTitle = __('Mailchimp', 'notifal');
        $panelDesc = __('Add submitters to a Mailchimp audience when the form is completed.', 'notifal');
        $enableFieldName = 'mailchimp_enabled';
        $cardClasses = 'notifal-form-panel-card';
        if (!$panelEnabled) {
            // Disabled integrations start collapsed so unused settings stay hidden.
            $cardClasses .= ' notifal-form-panel-card--disabled notifal-form-panel-card--collapsed';
        }
        ?>
        <article
            class="<?php echo esc_attr($cardClasses); ?>"
            id="<?php echo esc_attr($panelId); ?>"
            data-integration-panel="mailchimp"
            data-mailchimp-api-key-set="<?php echo !empty($meta['mailchimp_api_key_set']) ? '1' : '0'; ?>"
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
                            echo NotifalIconService::render($panelIcon, 20);
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
                <?php echo $panelEnabled ? '' : 'hidden'; ?>
            >
                <div class="notifal-form-field-row">
                    <label class="notifal-form-field-label" for="mailchimp_api_key">
                        <?php echo esc_html__('API key', 'notifal'); ?>
                    </label>
                    <input
                        type="password"
                        id="mailchimp_api_key"
                        name="mailchimp_api_key"
                        class="notifal-form-field-input"
                        value=""
                        autocomplete="new-password"
                        placeholder="<?php echo !empty($meta['mailchimp_api_key_set']) ? esc_attr__('Saved (leave blank to keep)', 'notifal') : esc_attr__('Paste your Mailchimp API key', 'notifal'); ?>"
                    />
                    <p class="notifal-form-field-hint">
                        <?php echo esc_html__('Stored securely. Leave blank to keep the saved key.', 'notifal'); ?>
                    </p>
                </div>

                <div class="notifal-form-field-row notifal-form-field-row--checkbox">
                    <label class="notifal-form-checkbox-label notifal-form-checkbox-label--muted">
                        <input type="checkbox" name="mailchimp_api_key_clear" value="1" />
                        <span><?php echo esc_html__('Clear saved API key', 'notifal'); ?></span>
                    </label>
                </div>

                <div class="notifal-form-field-row">
                    <label class="notifal-form-field-label" for="mailchimp_audience_id">
                        <?php echo esc_html__('Audience / list ID', 'notifal'); ?>
                    </label>
                    <input
                        type="text"
                        id="mailchimp_audience_id"
                        name="mailchimp_audience_id"
                        class="notifal-form-field-input"
                        value="<?php echo esc_attr((string) $meta['mailchimp_audience_id']); ?>"
                        autocomplete="off"
                    />
                    <p class="notifal-form-field-hint">
                        <?php echo esc_html__('Find this in Mailchimp under Audience settings.', 'notifal'); ?>
                    </p>
                </div>

                <div class="notifal-form-field-row">
                    <label class="notifal-form-field-label" for="mailchimp_email_field">
                        <?php echo esc_html__('Email field name', 'notifal'); ?>
                    </label>
                    <input
                        type="text"
                        id="mailchimp_email_field"
                        name="mailchimp_email_field"
                        class="notifal-form-field-input"
                        value="<?php echo esc_attr((string) $meta['mailchimp_email_field']); ?>"
                        placeholder="<?php echo esc_attr__('email', 'notifal'); ?>"
                        autocomplete="off"
                    />
                    <p class="notifal-form-field-hint">
                        <?php echo esc_html__('Must match a field name from the Fields tab (usually "email").', 'notifal'); ?>
                    </p>
                </div>
            </div>
        </article>
    </div>
</div>
