<?php
defined('ABSPATH') || exit;
/**
 * Fullscreen Setup Wizard admin page shell.
 *
 * Uses a dedicated shell (not the modal `.notifal-ai-wizard-shell`) so the
 * experience can fill the viewport edge to edge.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
?>
<div class="notifal-setup-wizard-wrap">
    <?php // Fixed immersive shell covers WP admin chrome edge to edge. ?>
    <div class="notifal-setup-wizard-shell">
        <?php // Decorative atmosphere layer (gradients / soft glow), non-interactive. ?>
        <div class="notifal-setup-wizard-atmosphere" aria-hidden="true"></div>
        <?php // JS mounts every wizard step into this root. ?>
        <div id="notifal-setup-wizard-root" class="notifal-setup-wizard-root">
            <div class="notifal-setup-wizard-loading">
                <p><?php esc_html_e('Loading Setup Wizard…', 'notifal'); ?></p>
            </div>
        </div>
    </div>
</div>
