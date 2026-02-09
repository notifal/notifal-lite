<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Hooks;

use Notifal\Modules\Templates\Infrastructure\Shared\Traits\ExportHooksTrait;
use Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Services\ElementorTagsExportProcessor;

defined('ABSPATH') || exit;

/**
 * Class ExportHooks
 *
 * Registers WordPress hooks for Elementor template export functionality.
 * Connects the export filter to the tags processor using shared trait functionality.
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Hooks
 * @author Hossein <hossein@notifal.com>
 */
class ExportHooks
{
    use ExportHooksTrait;

    /**
     * Elementor tags export processor
     *
     * @var ElementorTagsExportProcessor
     * @since 2.0.0
     */
    private $exportProcessor;

    /**
     * Constructor
     *
     * @param ElementorTagsExportProcessor $exportProcessor Export processor service
     * @since 2.0.0
     */
    public function __construct(ElementorTagsExportProcessor $exportProcessor)
    {
        $this->exportProcessor = $exportProcessor;
    }

    /**
     * Register WordPress hooks for Elementor template export
     *
     * @return void
     * @since 2.0.0
     */
    public function register(): void
    {
        $this->registerExportHooks();
    }
}
