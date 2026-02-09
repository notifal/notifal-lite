<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\Components;

use Notifal\Modules\OnPageNotification\Presentation\Admin\Traits\FilterRendererTrait;

defined('ABSPATH') || exit;

/**
 * Class FilterRenderer
 *
 * Provides filter rendering functionality for admin views.
 * Handles taxonomy filters, license type filters, and related UI elements.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FilterRenderer
{
    use FilterRendererTrait;

    /**
     * Render all taxonomy filters for the archive.
     *
     * @param array $taxonomies Taxonomy data from API
     * @param array $current_filters Currently selected filter values
     * @param string $text_domain Text domain for translations
     * @return void
     *
     * @since 2.0.0
     */
    public function renderArchiveFilters(array $taxonomies, array $current_filters, string $text_domain = 'notifal'): void
    {
        // Render taxonomy filters using the trait
        $this->renderTaxonomyFilter('use_case', __('Use Cases', $text_domain), $taxonomies['use_cases'] ?? [], $current_filters, $text_domain);
        $this->renderTaxonomyFilter('event', __('Events', $text_domain), $taxonomies['events'] ?? [], $current_filters, $text_domain);
        $this->renderTaxonomyFilter('industry', __('Industries', $text_domain), $taxonomies['industries'] ?? [], $current_filters, $text_domain);
        $this->renderTaxonomyFilter('layout', __('Layouts', $text_domain), $taxonomies['layouts'] ?? [], $current_filters, $text_domain);
        $this->renderTaxonomyFilter('plugin', __('Used Plugins', $text_domain), $taxonomies['used_plugins'] ?? [], $current_filters, $text_domain);

        // Render license filter and clear filters button
        $this->renderLicenseFilter($current_filters['is_pro'] ?? '', $text_domain);
        $this->renderClearFiltersButton($text_domain);
    }
}
