<?php

/**
 * Addons admin page view.
 *
 * Displays available and coming soon addons with AJAX enable/disable toggles.
 * Sticky menu is appended via ADMIN_PAGE_CONTENT_BEFORE inside .wrap, matching
 * other Notifal admin screens (notifications list, forms list, settings, etc.).
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 *
 * @var string               $page_slug     Admin page slug.
 * @var array<string, mixed> $modules       Addon catalog.
 * @var array<string, bool>  $module_states Enabled state keyed by addon slug.
 */

defined('ABSPATH') || exit;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;

?>

<div class="wrap notifal-admin-page notifal-addons-page">
    <?php
    // Render sticky menu and other shared admin chrome inside the page wrap.
    do_action(ActionHooks::ADMIN_PAGE_CONTENT_BEFORE);
    ?>
    <!-- Empty h1 keeps WordPress admin notices below the sticky menu. -->
    <h1></h1>

    <div class="notifal-addons-dashboard">
        <!-- Page header -->
        <header class="notifal-addons-hero">
            <div class="notifal-addons-hero__copy">
                <p class="notifal-addons-hero__eyebrow">
                    <?php echo esc_html__('Notifal', 'notifal'); ?>
                </p>
                <h1 class="notifal-addons-hero__title">
                    <?php echo esc_html__('Addons', 'notifal'); ?>
                </h1>
                <p class="notifal-addons-hero__subtitle">
                    <?php echo esc_html__('Turn built-in features on or off, and preview upcoming addons.', 'notifal'); ?>
                </p>
            </div>
        </header>

        <div class="notifal-addons-grid"
             id="notifal-addons-grid"
             data-label-active="<?php echo esc_attr__('Active', 'notifal'); ?>"
             data-label-inactive="<?php echo esc_attr__('Inactive', 'notifal'); ?>">
            <?php foreach ($modules as $module_key => $module) : ?>
                <?php
                // Normalize addon metadata for safe output.
                $module_slug = isset($module['slug']) ? sanitize_key((string) $module['slug']) : sanitize_key((string) $module_key);
                $module_title = isset($module['title']) ? (string) $module['title'] : '';
                $module_description = isset($module['description']) ? (string) $module['description'] : '';
                $module_icon = isset($module['icon']) ? sanitize_html_class((string) $module['icon']) : 'admin-generic';
                $module_status = isset($module['status']) ? sanitize_key((string) $module['status']) : 'coming_soon';
                $settings_key = isset($module['settings_key']) ? sanitize_key((string) $module['settings_key']) : '';
                $manage_url = isset($module['manage_url']) ? (string) $module['manage_url'] : '';
                $is_coming_soon = ($module_status === 'coming_soon');
                $is_enabled = !empty($module_states[$module_key]);
                $card_class = 'notifal-addon-card';

                if ($is_coming_soon) {
                    $card_class .= ' notifal-addon-card--coming-soon';
                } elseif ($is_enabled) {
                    $card_class .= ' notifal-addon-card--enabled';
                } else {
                    $card_class .= ' notifal-addon-card--disabled';
                }
                ?>

                <article class="<?php echo esc_attr($card_class); ?>" data-addon="<?php echo esc_attr($module_slug); ?>">
                    <div class="notifal-addon-card__top">
                        <span class="notifal-addon-card__icon dashicons dashicons-<?php echo esc_attr($module_icon); ?>" aria-hidden="true"></span>
                        <?php if ($is_coming_soon) : ?>
                            <span class="notifal-addon-card__badge">
                                <?php echo esc_html__('Coming Soon', 'notifal'); ?>
                            </span>
                        <?php elseif ($is_enabled) : ?>
                            <span class="notifal-addon-card__badge notifal-addon-card__badge--active">
                                <?php echo esc_html__('Active', 'notifal'); ?>
                            </span>
                        <?php else : ?>
                            <span class="notifal-addon-card__badge notifal-addon-card__badge--inactive">
                                <?php echo esc_html__('Inactive', 'notifal'); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <h2 class="notifal-addon-card__title">
                        <?php echo esc_html($module_title); ?>
                    </h2>

                    <p class="notifal-addon-card__description">
                        <?php echo esc_html($module_description); ?>
                    </p>

                    <div class="notifal-addon-card__footer">
                        <?php if (!$is_coming_soon && $settings_key !== '') : ?>
                            <label class="notifal-addon-toggle" for="<?php echo esc_attr($settings_key); ?>">
                                <input type="checkbox"
                                       class="notifal-addon-toggle__input"
                                       id="<?php echo esc_attr($settings_key); ?>"
                                       data-settings-key="<?php echo esc_attr($settings_key); ?>"
                                       value="1"
                                       <?php checked($is_enabled); ?> />
                                <span class="notifal-addon-toggle__track" aria-hidden="true"></span>
                                <span class="notifal-addon-toggle__label">
                                    <?php echo esc_html__('Enable addon', 'notifal'); ?>
                                </span>
                            </label>

                            <?php if ($manage_url !== '' && $is_enabled) : ?>
                                <a class="notifal-addon-card__manage"
                                   href="<?php echo esc_url($manage_url); ?>">
                                    <?php echo esc_html__('Manage', 'notifal'); ?>
                                </a>
                            <?php endif; ?>
                        <?php else : ?>
                            <span class="notifal-addon-card__soon-note">
                                <?php echo esc_html__('Available in a future release.', 'notifal'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</div>
