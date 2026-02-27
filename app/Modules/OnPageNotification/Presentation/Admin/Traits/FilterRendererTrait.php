<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\Traits;

use Notifal\Shared\Services\NotifalIconService;

defined('ABSPATH') || exit;

/**
 * Trait FilterRendererTrait
 *
 * Provides common functionality for rendering filter components in admin views.
 * Handles taxonomy filters, license type filters, and related UI elements.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
trait FilterRendererTrait
{
    /**
     * Render taxonomy filter section.
     *
     * Renders a complete filter section for a specific taxonomy including
     * checkboxes and collapse/expand toggle. All terms are shown by default (no Show More/Less).
     *
     * @param string $taxonomy_slug The taxonomy slug (e.g., 'use_cases', 'events')
     * @param string $taxonomy_label The human-readable label for the taxonomy
     * @param array $terms Array of taxonomy terms
     * @param array $current_filters Currently selected filter values
     * @param string $text_domain Text domain for translations
     * @param string $id_prefix Optional prefix for input IDs (useful for modal contexts)
     * @return void
     *
     * @since 2.0.0
     */
    public function renderTaxonomyFilter(
        string $taxonomy_slug,
        string $taxonomy_label,
        array $terms,
        array $current_filters,
        string $text_domain = 'notifal',
        string $id_prefix = ''
    ): void {
        if (empty($terms)) {
            return;
        }

        $input_name = $taxonomy_slug . '[]';
        $data_taxonomy = $taxonomy_slug;

        // Map singular taxonomy slugs to API/JS plural keys (data-taxonomy and input name)
        if ($taxonomy_slug === 'plugin') {
            $input_name = 'used_plugins[]';
            $data_taxonomy = 'used_plugins';
        } elseif ($taxonomy_slug === 'use_case') {
            $input_name = 'use_cases[]';
            $data_taxonomy = 'use_cases';
        } elseif ($taxonomy_slug === 'event') {
            $input_name = 'events[]';
            $data_taxonomy = 'events';
        } elseif ($taxonomy_slug === 'industry') {
            $input_name = 'industries[]';
            $data_taxonomy = 'industries';
        } elseif ($taxonomy_slug === 'layout') {
            $input_name = 'layouts[]';
            $data_taxonomy = 'layouts';
        }

        $wrapper_id = 'notifal-filter-options-' . $data_taxonomy . ( $id_prefix ? '-' . sanitize_key( $id_prefix ) : '' );
        $collapse_label = __( 'Collapse', $text_domain );
        ?>
        <div class="filter-section filter-section--taxonomy" data-taxonomy="<?php echo esc_attr( $data_taxonomy ); ?>">
            <div class="filter-section-header">
                <h3><?php echo esc_html( $taxonomy_label ); ?></h3>
                <button type="button" class="filter-section-toggle" aria-expanded="true" aria-controls="<?php echo esc_attr( $wrapper_id ); ?>" aria-label="<?php echo esc_attr( $collapse_label . ' ' . $taxonomy_label ); ?>">
                    <span class="filter-section-toggle-icon">
                        <?php echo NotifalIconService::render( 'arrow-up-short', 16 ); ?>
                    </span>
                </button>
            </div>
            <div class="filter-options-wrapper" id="<?php echo esc_attr( $wrapper_id ); ?>">
                <?php
                foreach ($terms as $index => $term) :
                    $term_slug = $term['slug'] ?? '';
                    $term_name = $term['name'] ?? '';
                    $term_count = $term['count'] ?? 0;
                    $input_id = $id_prefix . $taxonomy_slug . '-' . $term_slug;
                    $current_values = $current_filters[$taxonomy_slug] ?? [];
                ?>
                    <div class="filter-option" data-index="<?php echo esc_attr( $index ); ?>">
                        <input
                            type="checkbox"
                            id="<?php echo esc_attr( $input_id ); ?>"
                            class="filter-checkbox"
                            name="<?php echo esc_attr( $input_name ); ?>"
                            value="<?php echo esc_attr( $term_slug ); ?>"
                            data-taxonomy="<?php echo esc_attr( $data_taxonomy ); ?>"
                            <?php checked( in_array( $term_slug, $current_values ) ); ?>
                        />
                        <label for="<?php echo esc_attr( $input_id ); ?>">
                            <?php echo esc_html( $term_name ); ?>
                            <span class="taxonomy-filter-count">(<?php echo esc_html( $term_count ); ?>)</span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render license type filter section.
     *
     * Renders the license type filter with radio buttons for All/Free/Pro options.
     *
     * @param string $current_value Currently selected license filter value
     * @param string $text_domain Text domain for translations
     * @param string $name_prefix Optional prefix for input names (useful for modal contexts)
     * @return void
     *
     * @since 2.0.0
     */
    public function renderLicenseFilter(
        string $current_value,
        string $text_domain = 'notifal',
        string $name_prefix = ''
    ): void {
        $input_name = $name_prefix . 'license-type';
        ?>
        <div class="filter-section">
            <h3><?php esc_html_e('License Type', $text_domain); ?></h3>
            <div class="filter-option">
                <input
                    type="radio"
                    id="<?php echo esc_attr($name_prefix); ?>license-all"
                    name="<?php echo esc_attr($input_name); ?>"
                    value=""
                    <?php checked($current_value, ''); ?>
                />
                <label for="<?php echo esc_attr($name_prefix); ?>license-all">
                    <?php esc_html_e('All Notifications', $text_domain); ?>
                </label>
            </div>
            <div class="filter-option">
                <input
                    type="radio"
                    id="<?php echo esc_attr($name_prefix); ?>license-free"
                    name="<?php echo esc_attr($input_name); ?>"
                    value="0"
                    <?php checked($current_value, '0'); ?>
                />
                <label for="<?php echo esc_attr($name_prefix); ?>license-free">
                    <?php esc_html_e('Free Only', $text_domain); ?>
                </label>
            </div>
            <div class="filter-option">
                <input
                    type="radio"
                    id="<?php echo esc_attr($name_prefix); ?>license-pro"
                    name="<?php echo esc_attr($input_name); ?>"
                    value="1"
                    <?php checked($current_value, '1'); ?>
                />
                <label for="<?php echo esc_attr($name_prefix); ?>license-pro">
                    <?php esc_html_e('Pro Features', $text_domain); ?>
                </label>
            </div>
        </div>
        <?php
    }

    /**
     * Render clear filters button.
     *
     * @param string $text_domain Text domain for translations
     * @param string $button_id Optional custom ID for the button
     * @return void
     *
     * @since 2.0.0
     */
    public function renderClearFiltersButton(
        string $text_domain = 'notifal',
        string $button_id = 'clear-all-filters'
    ): void {
        ?>
        <div class="filter-section">
            <button id="<?php echo esc_attr($button_id); ?>" class="clear-filters-btn">
                <?php esc_html_e('Clear All Filters', $text_domain); ?>
            </button>
        </div>
        <?php
    }
}
