<?php

namespace Notifal\Modules\Templates\Application\Services;


use Notifal\Infrastructure\WordPress\Security\NonceManager;

defined('ABSPATH') || exit;

/**
 * Class TemplateUrlService
 *
 * Handles URLs and nonce generation for Templates module actions.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class TemplateUrlService
{
    /**
     * Get URL to create a new template with WordPress Editor.
     *
     * @return string
     * @since 2.0.0
     */
    public function getCreateEditorUrl(): string
    {
        return admin_url('post-new.php?post_type=notifal_template');
    }

    /**
     * Get URL to create a new template with Elementor.
     *
     * @return string
     * @since 2.0.0
     */
    public function getCreateElementorUrl(): string
    {
        $nonce = NonceManager::create('notifal_create_elementor_template');
        return add_query_arg([
            'action'   => 'notifal_create_elementor_template',
            '_wpnonce' => $nonce,
        ], admin_url('admin.php'));
    }

    /**
     * Get URL to install Elementor plugin.
     *
     * @return string
     * @since 2.0.0
     */
    public function getElementorInstallUrl(): string
    {
        return admin_url('plugin-install.php?s=elementor&tab=search&type=term');
    }

    /**
     * Get nonce for template import.
     *
     * @return string
     * @since 2.0.0
     */
    public function getImportNonce(): string
    {
        return NonceManager::create('notifal_import_ajax_nonce');
    }
}
