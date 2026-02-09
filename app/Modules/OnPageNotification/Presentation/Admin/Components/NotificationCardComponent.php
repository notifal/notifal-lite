<?php

use Notifal\Shared\Services\NotifalIconService;

if (!defined('ABSPATH')) exit;

/**
 * Notification Card Component
 *
 * Renders a single notification card for the pre-created notifications archive.
 * Handles featured image, title, badges, and action buttons consistently.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

/**
 * Get normalized layout CSS class for a notification.
 *
 * @param array $notification_taxonomies Notification taxonomies.
 * @return string Normalized layout class name.
 */
function notifal_get_notification_layout_class(array $notification_taxonomies): string {
    $layout_type = '';

    if (!empty($notification_taxonomies['layouts']) && is_array($notification_taxonomies['layouts'])) {
        foreach ($notification_taxonomies['layouts'] as $layout_term) {
            $raw_value = '';

            if (is_array($layout_term)) {
                $raw_value = (string) ($layout_term['slug'] ?? $layout_term['name'] ?? '');
            } elseif (is_object($layout_term)) {
                $raw_value = (string) ($layout_term->slug ?? $layout_term->name ?? '');
            } else {
                $raw_value = (string) $layout_term;
            }

            $normalized_value = strtolower(trim($raw_value));

            if ('' === $normalized_value) {
                continue;
            }

            if (false !== strpos($normalized_value, 'floating') && false !== strpos($normalized_value, 'bar')) {
                $layout_type = 'floating-bar';
                break;
            }

            if (false !== strpos($normalized_value, 'floating') && (false !== strpos($normalized_value, 'box') || false !== strpos($normalized_value, 'widget'))) {
                $layout_type = 'floating-box';
                break;
            }

            if (false !== strpos($normalized_value, 'popup') || false !== strpos($normalized_value, 'modal')) {
                $layout_type = 'popup';
                break;
            }
        }
    }

    if ('' === $layout_type) {
        $layout_type = 'popup';
    }

    return 'notifal-layout--' . $layout_type;
}

/**
 * Render a notification card.
 *
 * @param array $notification Notification data array
 * @param int $max_badges Maximum number of badges to display (default: 5)
 * @param string $view_details_text Text for the view details button (default: 'View details & import')
 * @return void
 *
 * @since 2.0.0
 */
function render_notification_card(array $notification, int $max_badges = 5, string $view_details_text = ''): void {
    // Extract notification data with defaults
    $id = $notification['id'] ?? 0;
    $title = $notification['title'] ?? '';
    $permalink = $notification['permalink'] ?? '';
    $featured_image = $notification['featured_image'] ?? array();
    $notification_taxonomies = $notification['taxonomies'] ?? array();

    if (empty($view_details_text)) {
        $view_details_text = __('View details & import', 'notifal');
    }

    // Collect all taxonomy terms for badges
    $all_terms = [];
    foreach ($notification_taxonomies as $taxonomy => $terms) {
        if (!empty($terms) && is_array($terms)) {
            foreach ($terms as $term) {
                $all_terms[] = [
                    'name' => $term['name'] ?? '',
                    'taxonomy' => $taxonomy
                ];
            }
        }
    }

    // Limit to maximum badges
    $all_terms = array_slice($all_terms, 0, $max_badges);

    $layout_class = notifal_get_notification_layout_class($notification_taxonomies);

    ?>
    <div class="notification-card">
        <div class="notification-card-content">
            <!-- Featured Image -->
            <div class="featured-image <?php echo esc_attr($layout_class); ?>">
                <div class="notifal-layout-holder">
                    <div class="notifal-layout-site">
                        <div class="notifal-layout-cover-wrapper">
                            <div class="notifal-layout-cover-row notifal-layout-cover-row--small"></div>
                            <div class="notifal-layout-cover-row"></div>
                            <div class="notifal-layout-cover-row notifal-layout-cover-row--small"></div>
                            <div class="notifal-layout-cover-columns">
                                <div class="notifal-layout-cover-col"></div>
                                <div class="notifal-layout-cover-col"></div>
                                <div class="notifal-layout-cover-col"></div>
                            </div>
                        </div>
                        <div class="notifal-layout-feature">
                            <?php if (!empty($featured_image['url'])) : ?>
                                <img src="<?php echo esc_url($featured_image['url']); ?>"
                                    alt="<?php echo esc_attr($featured_image['alt'] ?? $title); ?>"
                                    loading="lazy">
                            <?php else : ?>
                                <div class="image-placeholder"></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Title -->
            <h3 class="notification-title">
                <?php echo esc_html(wp_trim_words($title, 8, '...')); ?>
            </h3>

            <!-- Badges Container -->
            <div class="badges-container">
                <?php foreach ($all_terms as $term) : ?>
                    <span class="badge">
                        <?php echo esc_html($term['name']); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- View Details Link -->
        <a href="#" class="view-details" data-notification-id="<?php echo esc_attr($id); ?>">
            <?php echo esc_html($view_details_text); ?>
            <span class="view-details-icon">
                <?php
                // Render icon with inline font-size for exact 25px size
                $icon_html = NotifalIconService::render('arrow-up-short', 25, ['style' => 'font-size: 25px;']);
                echo $icon_html;
                ?>
            </span>
        </a>
    </div>
    <?php
}
