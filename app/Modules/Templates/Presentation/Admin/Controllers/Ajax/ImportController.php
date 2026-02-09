<?php

namespace Notifal\Modules\Templates\Presentation\Admin\Controllers\Ajax;

defined('ABSPATH') || exit;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Templates\Infrastructure\WordPress\Import\Importer;
use Notifal\Shared\Controllers\Ajax\BaseImportController;

/**
 * Class ImportController
 *
 * Handles AJAX import logic for notifal_template files (JSON/ZIP).
 * Provides secure file upload validation and template import processing.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ImportController extends BaseImportController
{
    /**
     * Get the AJAX action name for registration.
     *
     * @since 2.0.0
     * @return string The AJAX action name
     */
    protected static function getAjaxAction(): string
    {
        return 'notifal_import_ajax';
    }

    /**
     * Get the nonce action name for verification.
     *
     * @since 2.0.0
     * @return string The nonce action name
     */
    protected static function getNonceAction(): string
    {
        return 'notifal_import_ajax_nonce';
    }

    /**
     * Get whether this import controller requires file upload validation.
     *
     * @since 2.0.0
     * @return bool True if file validation is required, false otherwise
     */
    protected static function requiresFileValidation(): bool
    {
        return true;
    }

    /**
     * Get the filter hook name for import results.
     *
     * @since 2.0.0
     * @return string The filter hook name
     */
    protected static function getImportResultFilter(): string
    {
        return FilterHooks::TEMPLATE_IMPORT_RESULT;
    }

    /**
     * Get the title text for ZIP imports.
     *
     * @since 2.0.0
     * @return string The title text
     */
    protected static function getZipImportTitle(): string
    {
        return __('Templates', 'notifal');
    }

    /**
     * Perform the actual import using the template importer.
     *
     * @since 2.0.0
     * @param string|null $filePath The path to the uploaded file
     * @return array The import result
     */
    protected static function performImport(?string $filePath): array
    {
        return Importer::importFromFile($filePath);
    }
}
