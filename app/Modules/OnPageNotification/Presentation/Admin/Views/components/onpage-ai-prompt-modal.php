<?php

/**
 * OnPage Notification AI Prompt Modal
 *
 * Renders the AI prompt generator modal for full notification JSON export.
 *
 * @since 2.4.1
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Views\components
 */

use Notifal\Shared\Services\NotifalIconService;

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="notifal-modal-backdrop notifal-hidden" id="notifal-onpage-ai-prompt-modal" aria-hidden="true">
    <div class="notifal-modal notifal-onpage-ai-prompt-modal" role="dialog" aria-modal="true" aria-labelledby="notifal-onpage-ai-prompt-title">
        <div class="notifal-modal-header">
            <div class="notifal-onpage-ai-prompt-header-text">
                <h2 id="notifal-onpage-ai-prompt-title"><?php esc_html_e('Generate Notification with AI', 'notifal'); ?></h2>
                <p class="notifal-text-muted notifal-onpage-ai-prompt-subtitle">
                    <?php esc_html_e('Copy this prompt into ChatGPT, Claude, or any AI assistant. Paste the JSON response into Import → Paste JSON to create a ready-to-review notification with template and settings.', 'notifal'); ?>
                </p>
            </div>
            <button type="button" class="notifal-modal-close" id="notifal-onpage-ai-prompt-close" aria-label="<?php esc_attr_e('Close', 'notifal'); ?>">
                <span class="notifal-icon notifal-icon-x-circle size-16"></span>
            </button>
        </div>

        <div class="notifal-modal-body">
            <div class="notifal-onpage-ai-prompt-steps notifal-alert notifal-info" role="note">
                <p class="notifal-onpage-ai-prompt-steps-title"><?php esc_html_e('How it works', 'notifal'); ?></p>
                <ol class="notifal-onpage-ai-prompt-steps-list">
                    <li><?php esc_html_e('Copy the prompt below and paste it into your AI tool.', 'notifal'); ?></li>
                    <li><?php esc_html_e('Ask the AI to return only the Notifal import JSON (no extra text).', 'notifal'); ?></li>
                    <li><?php esc_html_e('Click "Open Import" and paste the JSON into the import modal.', 'notifal'); ?></li>
                </ol>
            </div>

            <div class="notifal-onpage-ai-prompt-examples-toggle-row notifal-hidden" id="notifal-onpage-ai-examples-toggle-row">
                <button type="button" class="notifal-onpage-ai-examples-toggle" id="notifal-onpage-ai-examples-toggle" aria-expanded="false" aria-controls="notifal-onpage-ai-examples-panel">
                    <span class="notifal-icon notifal-icon-chevron-down1 notifal-onpage-ai-examples-chevron" aria-hidden="true"></span>
                    <span id="notifal-onpage-ai-examples-toggle-label"><?php esc_html_e('See filling examples', 'notifal'); ?></span>
                    <span class="notifal-onpage-ai-examples-active-label notifal-hidden" id="notifal-onpage-ai-examples-active-label"></span>
                </button>
            </div>

            <div class="notifal-onpage-ai-examples-panel notifal-hidden" id="notifal-onpage-ai-examples-panel"></div>

            <div class="notifal-onpage-ai-prompt-controls">
                <div class="notifal-onpage-ai-prompt-controls-row">
                    <div class="notifal-form-group">
                        <label class="notifal-label" for="notifal-onpage-ai-layout"><?php esc_html_e('Display layout', 'notifal'); ?></label>
                        <select id="notifal-onpage-ai-layout" class="notifal-input">
                            <option value=""><?php esc_html_e('Select a layout (optional)', 'notifal'); ?></option>
                        </select>
                    </div>
                    <div class="notifal-form-group">
                        <label class="notifal-label" for="notifal-onpage-ai-use-case"><?php esc_html_e('Notification goal', 'notifal'); ?></label>
                        <select id="notifal-onpage-ai-use-case" class="notifal-input">
                            <option value=""><?php esc_html_e('Select a goal (optional)', 'notifal'); ?></option>
                        </select>
                    </div>
                </div>

                <p class="notifal-help-text notifal-hidden" id="notifal-onpage-ai-layout-guide" role="note"></p>
                <p class="notifal-help-text notifal-hidden" id="notifal-onpage-ai-use-case-guide" role="note"></p>

                <div class="notifal-form-group">
                    <label class="notifal-label" for="notifal-onpage-ai-industry"><?php esc_html_e('Your industry or niche', 'notifal'); ?></label>
                    <input type="text" id="notifal-onpage-ai-industry" class="notifal-input" autocomplete="off" placeholder="<?php esc_attr_e('e.g. Specialty coffee roastery, WooCommerce fashion store…', 'notifal'); ?>">
                    <span class="notifal-help-text"><?php esc_html_e('Be specific so the AI can match tone, copy, and settings to your audience.', 'notifal'); ?></span>
                </div>

                <div class="notifal-onpage-ai-prompt-controls-row">
                    <div class="notifal-form-group">
                        <label class="notifal-label" for="notifal-onpage-ai-goal"><?php esc_html_e('Your notification goal', 'notifal'); ?></label>
                        <textarea id="notifal-onpage-ai-goal" class="notifal-input notifal-onpage-ai-goal" rows="3" placeholder="<?php esc_attr_e('e.g. Exit-intent cart recovery popup with 10% off…', 'notifal'); ?>"></textarea>
                    </div>
                    <div class="notifal-form-group">
                        <label class="notifal-label" for="notifal-onpage-ai-color"><?php esc_html_e('Primary color', 'notifal'); ?></label>
                        <input type="text" id="notifal-onpage-ai-color" class="notifal-input" value="#7e2bd2" spellcheck="false" autocomplete="off">
                    </div>
                </div>
            </div>

            <div class="notifal-form-group notifal-mt-20">
                <label class="notifal-label" for="notifal-onpage-ai-prompt-output"><?php esc_html_e('AI prompt (read-only)', 'notifal'); ?></label>
                <textarea id="notifal-onpage-ai-prompt-output" class="notifal-input notifal-onpage-ai-prompt-output" rows="14" readonly spellcheck="false"></textarea>
            </div>
        </div>

        <div class="notifal-modal-footer notifal-onpage-ai-prompt-footer">
            <p class="notifal-onpage-ai-prompt-footer-hint">
                <?php esc_html_e('Paste the prompt into', 'notifal'); ?>
                <a href="https://claude.ai" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Claude (recommended)', 'notifal'); ?></a>
                /
                <a href="https://chatgpt.com" target="_blank" rel="noopener noreferrer"><?php esc_html_e('ChatGPT', 'notifal'); ?></a>
            </p>
            <div class="notifal-onpage-ai-prompt-footer-actions">
                <button type="button" class="notifal-button secondary notifal-flex notifal-gap-10" id="notifal-onpage-ai-prompt-copy">
                    <?php echo NotifalIconService::render('copy', 16); ?>
                    <span id="notifal-onpage-ai-prompt-copy-label"><?php esc_html_e('Copy Prompt', 'notifal'); ?></span>
                </button>
                <button type="button" class="notifal-button notifal-flex notifal-gap-10" id="notifal-onpage-ai-open-import">
                    <?php echo NotifalIconService::render('cloud-download', 16); ?>
                    <span><?php esc_html_e('Open Import to Paste JSON', 'notifal'); ?></span>
                </button>
            </div>
        </div>
    </div>
</div>
