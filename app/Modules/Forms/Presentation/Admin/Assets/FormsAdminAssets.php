<?php

namespace Notifal\Modules\Forms\Presentation\Admin\Assets;

use Notifal\Infrastructure\WordPress\Admin\Localization\LangLoader;
use Notifal\Modules\Forms\Application\Services\FormEmailTemplateDefaults;
use Notifal\Modules\Forms\Config\AttributeContract;
use Notifal\Modules\Forms\Application\Services\FormFieldSanitizer;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormFieldsRepository;
use Notifal\Shared\Config\Paths;

defined('ABSPATH') || exit;

/**
 * Enqueue Forms admin scripts on Forms screens.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormsAdminAssets
{
    /**
     * Register hooks.
     *
     * @return void
     * @since 3.0.0
     */
    public static function register(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    /**
     * Enqueue built admin assets on Forms pages only.
     *
     * @param string $hook Current admin hook.
     * @return void
     * @since 3.0.0
     */
    public static function enqueue(string $hook): void
    {
        unset($hook);

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
        $allowed = ['notifal-forms', 'notifal-form', 'notifal-form-submissions', 'notifal-form-settings'];
        if (!in_array($page, $allowed, true)) {
            return;
        }

        $js = Paths::jsAdminBuildUrl() . 'FormsAdminScript.js';
        $css = Paths::cssAdminBuildUrl() . 'FormsAdminStyle.css';

        wp_enqueue_style(
            'notifal-forms-admin',
            $css,
            ['notifal-shared-admin-css'],
            NOTIFAL_VERSION
        );

        wp_enqueue_script(
            'notifal-forms-admin',
            $js,
            ['notifal-shared-admin-js'],
            NOTIFAL_VERSION,
            true
        );

        $formId = isset($_GET['form_id']) ? absint(wp_unslash($_GET['form_id'])) : 0;
        $fieldsRepo = new FormFieldsRepository(new FormFieldSanitizer());
        $fields = $fieldsRepo->get($formId);
        $strings = LangLoader::load('Notifal\\Modules\\Forms', 'admin.php');
        $emailDefaults = (new FormEmailTemplateDefaults())->getForAdminUi();

        wp_localize_script(
            'notifal-forms-admin',
            'NotifalFormsAdmin',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'formId'  => $formId,
                'fields'  => $fields,
                'fieldTypes' => AttributeContract::FIELD_TYPES,
                'conditionalOps' => AttributeContract::CONDITIONAL_OPERATORS,
                'emailDefaults' => $emailDefaults,
                // Shared nonce for submission list / quick view AJAX.
                'submissionsNonce' => wp_create_nonce('notifal_form_submissions'),
                'i18n'    => $strings,
            ]
        );
    }
}
