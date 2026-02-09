<?php

namespace Notifal\Modules\Templates\Contracts;

defined('ABSPATH') || exit;

/**
 * Interface TemplateBuilderInterface
 *
 * Contract for rendering, importing, exporting, and building previews of templates.
 * Supports multiple implementations (e.g. Elementor, Gutenberg).
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Contracts
 * @author Hossein <hossein@notifal.com>
 */
interface TemplateBuilderInterface
{
    /**
     * Render a given template for preview or display.
     *
     * @param int $templateId
     * @return string Rendered HTML content
     * @since 2.0.0
     */
    public function render(int $templateId): string;

    /**
     * Build a preview of a given template with resolved tag data.
     *
     * @param int $templateId
     * @return string Rendered HTML preview
     * @since 2.0.0
     */
    public function buildPreview(int $templateId): string;

    /**
     * Export a template's structure and content.
     *
     * @param int $templateId
     * @return array Exported template data
     * @since 2.0.0
     */
    public function export(int $templateId): array;

    /**
     * Import a template from exported data.
     *
     * @param array $data Template data to import
     * @return int Created template post ID
     * @since 2.0.0
     */
    public function import(array $data): int;
}
