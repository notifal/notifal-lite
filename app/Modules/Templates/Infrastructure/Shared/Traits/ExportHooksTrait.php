<?php

namespace Notifal\Modules\Templates\Infrastructure\Shared\Traits;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Trait ExportHooksTrait
 *
 * Shared functionality for template export hooks across different builders.
 * Provides a standardized way to register export data processing hooks.
 *
 * Classes using this trait must define a protected $exportProcessor property.
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Infrastructure\Shared\Traits
 * @author Hossein <hossein@notifal.com>
 */
trait ExportHooksTrait
{
    /**
     * Register WordPress hooks for template export functionality
     *
     * @return void
     * @since 2.0.0
     */
    protected function registerExportHooks(): void
    {
        add_filter(
            FilterHooks::EXPORT_TEMPLATE_DATA,
            [$this->exportProcessor, 'processExportData'],
            10,
            2
        );
    }
}