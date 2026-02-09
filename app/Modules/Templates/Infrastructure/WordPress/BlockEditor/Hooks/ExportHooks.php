<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Hooks;

use Notifal\Modules\Templates\Infrastructure\Shared\Traits\ExportHooksTrait;
use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\BlockEditorTagsExportProcessor;

defined('ABSPATH') || exit;

/**
 * Class ExportHooks
 *
 * Registers WordPress hooks for block editor template export functionality.
 * Connects the export filter to the tags processor using shared trait functionality.
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Hooks
 * @author Hossein <hossein@notifal.com>
 */
class ExportHooks
{
    use ExportHooksTrait;

    /**
     * Block editor tags export processor
     *
     * @var BlockEditorTagsExportProcessor
     * @since 2.0.0
     */
    private $exportProcessor;

    /**
     * Constructor
     *
     * @param BlockEditorTagsExportProcessor $exportProcessor Export processor service
     * @since 2.0.0
     */
    public function __construct(BlockEditorTagsExportProcessor $exportProcessor)
    {
        $this->exportProcessor = $exportProcessor;
    }

    /**
     * Register WordPress hooks for block editor template export
     *
     * @return void
     * @since 2.0.0
     */
    public function register(): void
    {
        $this->registerExportHooks();
    }
} 
