<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\Traits;

use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Shared\AdminUI\Fields\FieldRenderer;
use Notifal\Domain\Settings\Constants\Urls;

defined('ABSPATH') || exit;

/**
 * Trait RestrictionCardRendererTrait
 *
 * Provides reusable methods for rendering restriction cards in content source settings.
 * Eliminates code duplication by providing a generic interface for different restriction types.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
trait RestrictionCardRendererTrait
{
    /**
     * Render a complete restriction card with all its components.
     *
     * @param array $config Configuration array containing:
     *                     - 'id': Card ID
     *                     - 'title': Card title/label
     *                     - 'tooltip': Tooltip text
     *                     - 'filter_type': Filter type for buttons
     *                     - 'is_pro_only': Whether this is a Pro-only feature
     *                     - 'pro_badge_text': Text for Pro badge (optional)
     *                     - 'empty_message': Message when no filters are configured
     *                     - 'pro_upgrade_text': Text for Pro upgrade messaging (optional)
     * @return void
     *
     * @since 2.0.0
     */
    protected function renderRestrictionCard(array $config): void
    {
        $is_pro_active = PluginDetector::isNotifalProActive();
        $card_classes = 'notifal-restriction-card notifal-mb-20';

        // Add Pro-specific classes if needed
        if ($config['is_pro_only'] ?? false) {
            $card_classes .= ' notifal-pro-feature';
            if (!$is_pro_active) {
                $card_classes .= ' notifal-pro-disabled';
            }
        }

        ?>
        <div class="<?php echo esc_attr($card_classes); ?>" id="<?php echo esc_attr($config['id']); ?>">
            <div class="notifal-card-header">
                <div class="notifal-field-header notifal-flex notifal-flex-row">
                    <label class="notifal-form-label">
                        <?php echo esc_html($config['title']); ?>
                        <?php if ($config['is_pro_only'] ?? false): ?>
                            <span class="notifal-pro-badge notifal-pro-badge-inline"><?php esc_html_e('PRO', 'notifal'); ?></span>
                        <?php endif; ?>
                    </label>
                    <?php FieldRenderer::tooltip(__($config['tooltip'], 'notifal')); ?>
                    <div class="notifal-card-actions">
                        <?php $this->renderCardActionButtons($config, $is_pro_active); ?>
                    </div>
                </div>
            </div>

            <div class="notifal-card-content">
                <div class="notifal-filter-conditions" id="<?php echo esc_attr($config['filter_type']); ?>-filter-conditions">
                    <!-- Filter conditions will be added dynamically -->
                </div>

                <?php $this->renderEmptyFiltersMessage($config, $is_pro_active); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the action buttons for a restriction card.
     *
     * @param array $config Configuration array
     * @param bool $is_pro_active Whether Pro is active
     * @return void
     *
     * @since 2.0.0
     */
    private function renderCardActionButtons(array $config, bool $is_pro_active): void
    {
        $filter_type = $config['filter_type'];

        if (($config['is_pro_only'] ?? false) && !$is_pro_active) {
            echo '<div class="notifal-text-disabled">' . esc_html__('Comment features available in Notifal Pro', 'notifal') . '</div>';
            return;
        }

        ?>
        <button type="button" class="notifal-button secondary small notifal-add-filter-btn" data-filter-type="<?php echo esc_attr($filter_type); ?>">
            <span class="notifal-icon notifal-icon-plus-circle size-16"></span>
            <?php esc_html_e('Add Filter', 'notifal'); ?>
        </button>

        <?php if (!$config['is_pro_only'] && $is_pro_active): ?>
            <div class="notifal-logic-selector notifal-hidden" data-min-conditions="2">
                <label class="notifal-form-label small"><?php esc_html_e('Logic:', 'notifal'); ?></label>
                <select name="<?php echo esc_attr($filter_type); ?>_filters_logic" class="notifal-select small">
                    <option value="AND"><?php esc_html_e('AND (All conditions)', 'notifal'); ?></option>
                    <option value="OR"><?php esc_html_e('OR (Any condition)', 'notifal'); ?></option>
                </select>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Render the empty filters message for a restriction card.
     *
     * @param array $config Configuration array
     * @param bool $is_pro_active Whether Pro is active
     * @return void
     *
     * @since 2.0.0
     */
    private function renderEmptyFiltersMessage(array $config, bool $is_pro_active): void
    {
        $filter_type = $config['filter_type'];
        ?>
        <div class="notifal-empty-filters-message" id="<?php echo esc_attr($filter_type); ?>-empty-message">
            <div class="notifal-empty-icon">🔍</div>
            <p>
                <?php echo esc_html($config['empty_message']); ?>
                <?php if (!$config['is_pro_only'] && !PluginDetector::isNotifalProActive()): ?>
                    <br><small><?php esc_html_e('Free users can add one filter. Upgrade to Pro for unlimited filters.', 'notifal'); ?></small>
                <?php endif; ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render a special information card (like user tags information).
     *
     * @param array $config Configuration array containing:
     *                     - 'id': Card ID
     *                     - 'title': Card title
     *                     - 'content': HTML content to display
     * @return void
     *
     * @since 2.0.0
     */
    protected function renderInformationCard(array $config): void
    {
        ?>
        <div class="notifal-restriction-card notifal-mb-20" id="<?php echo esc_attr($config['id']); ?>">
            <div class="notifal-card-header">
                <div class="notifal-field-header notifal-flex notifal-flex-row">
                    <label class="notifal-form-label"><?php echo esc_html($config['title']); ?></label>
                </div>
            </div>

            <div class="notifal-card-content">
                <?php echo wp_kses_post($config['content']); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render a Pro upgrade box for multi-filter functionality.
     *
     * @param array $config Configuration array containing:
     *                     - 'id': Box ID (optional)
     * @return void
     *
     * @since 2.0.0
     */
    protected function renderProUpgradeBox(array $config = []): void
    {
        $box_id = $config['id'] ?? 'notifal-multi-filter-upgrade';
        ?>
        <div class="notifal-pro-upgrade-cta notifal-hidden" id="<?php echo esc_attr($box_id); ?>">
            <div class="notifal-card notifal-gradient-card">
                <div class="notifal-upgrade-content">
                    <div class="notifal-upgrade-icon">
                        <span class="notifal-icon notifal-icon-filter-circle"></span>
                    </div>
                    <div class="notifal-upgrade-text">
                        <h3><?php esc_html_e('Unlock Multi-Filter Restrictions with Notifal Pro', 'notifal'); ?></h3>
                        <p><?php esc_html_e("You're currently using 1 filter for dynamic content restrictions. Upgrade to Pro to add unlimited filters with AND/OR logic for complex filtering conditions and more precise control over your notifications.", 'notifal'); ?></p>
                        <ul class="notifal-upgrade-features">
                            <li><span class="notifal-icon notifal-icon-check"></span><?php esc_html_e('Add unlimited filters per restriction type', 'notifal'); ?></li>
                            <li><span class="notifal-icon notifal-icon-check"></span><?php esc_html_e('Combine filters with AND/OR logic', 'notifal'); ?></li>
                            <li><span class="notifal-icon notifal-icon-check"></span><?php esc_html_e('Advanced filtering for products, orders, posts, and more', 'notifal'); ?></li>
                            <li><span class="notifal-icon notifal-icon-check"></span><?php esc_html_e('Access to all Pro features and settings', 'notifal'); ?></li>
                        </ul>
                    </div>
                    <div class="notifal-upgrade-action">
                        <?php
                        $domain = parse_url(home_url(), PHP_URL_HOST);
                        $upgrade_url = Urls::withPluginUtm(
                            Urls::PRICING,
                            'wordpress_plugin',
                            'notifal_pro_upgrade'
                        ) . '&utm_medium=content_source_admin_banner&utm_content=content_source_admin_banner&domain=' . urlencode($domain);
                        ?>
                        <a href="<?php echo esc_url($upgrade_url); ?>" class="notifal-button notifal-button-primary notifal-button-large notifal-pro-upgrade-cta" target="_blank" rel="noopener noreferrer">
                            <span class="notifal-icon notifal-icon-crown"></span>
                            <?php esc_html_e('Upgrade to Pro', 'notifal'); ?>
                        </a>
                        <p class="notifal-upgrade-note"><?php esc_html_e('30-day money-back guarantee', 'notifal'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}