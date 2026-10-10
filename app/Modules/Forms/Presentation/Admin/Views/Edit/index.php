<?php

/**
 * Form CPT edit screen (identity, fields, emails, integrations / secrets).
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\Forms\Application\Services\FormEmailTemplateDefaults;
use Notifal\Modules\Forms\Application\Services\FormEmailVariableService;
use Notifal\Modules\Forms\Application\Services\FormFieldSanitizer;
use Notifal\Modules\Forms\Application\Services\FormLicenseGuard;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormFieldsRepository;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormMetaRepository;
use Notifal\Shared\AdminUI\Toast\ToastRenderer;
use Notifal\Shared\Services\NotifalIconService;

defined('ABSPATH') || exit;

$formId = isset($_GET['form_id']) ? absint(wp_unslash($_GET['form_id'])) : 0;
$post = $formId > 0 ? get_post($formId) : null;

if ($formId > 0 && (!$post || $post->post_type !== 'notifal_form')) {
    wp_die(esc_html__('Form not found.', 'notifal'));
}

if ($formId <= 0 && !FormLicenseGuard::canCreate()) {
    wp_die(
        esc_html__('Notifal free includes one form. Upgrade to Notifal Pro to create additional forms.', 'notifal')
    );
}

$metaRepo = new FormMetaRepository();
$fieldsRepo = new FormFieldsRepository(new FormFieldSanitizer());
$meta = $metaRepo->getForAdmin($formId);
$fields = $fieldsRepo->get($formId);
$emailDefaults = (new FormEmailTemplateDefaults())->getForAdminUi();
$emailVariables = (new FormEmailVariableService())->getForAdminUi($fields);
$title = $post ? $post->post_title : '';
$nonce = wp_create_nonce('notifal_form_save');
$isNew = $formId <= 0;
$listUrl = admin_url('admin.php?page=notifal-forms');
?>

<div class="wrap notifal-admin-page notifal-form-edit-page">
    <?php do_action(ActionHooks::ADMIN_PAGE_CONTENT_BEFORE); ?>

    <div class="notifal-form-edit-parent notifal-mt-20">
        <a class="notifal-page-back-link" href="<?php echo esc_url($listUrl); ?>">
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- NotifalIconService::render() escapes icon markup via esc_attr().
            echo NotifalIconService::render('arrow-left-short', 16);
            ?>
            <?php echo esc_html__('Back to Forms', 'notifal'); ?>
        </a>

        <div class="notifal-list-header">
            <h1 class="notifal-page-title">
                <?php echo $isNew
                    ? esc_html__('Create Form', 'notifal')
                    : esc_html__('Edit Form', 'notifal'); ?>
            </h1>
        </div>

        <div class="notifal-form-edit-info" role="note">
            <span class="notifal-form-edit-info__icon" aria-hidden="true">
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- NotifalIconService::render() escapes icon markup via esc_attr().
                echo NotifalIconService::render('question-circle', 18);
                ?>
            </span>
            <p>
                <?php echo esc_html__('Field layout and design are edited in the HTML Builder after you connect this form to a template.', 'notifal'); ?>
            </p>
        </div>

        <form id="notifal-form-edit" class="notifal-notification-form" method="post" novalidate>
            <input type="hidden" name="form_id" value="<?php echo esc_attr((string) $formId); ?>" />
            <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>" />

            <div class="notifal-flex notifal-gap-20 notifal-mt-20">
                <aside class="notifal-card notifal-sidebar notifal-form-edit-sidebar" aria-label="<?php echo esc_attr__('Form sections', 'notifal'); ?>">
                    <nav class="notifal-tabs notifal-flex notifal-flex-column notifal-gap-10" id="notifal-form-edit-tabs">
                        <button type="button" class="notifal-button secondary active" data-tab="basics">
                            <?php echo esc_html__('Basics', 'notifal'); ?>
                        </button>
                        <button type="button" class="notifal-button secondary" data-tab="fields">
                            <?php echo esc_html__('Fields', 'notifal'); ?>
                        </button>
                        <button type="button" class="notifal-button secondary" data-tab="emails">
                            <?php echo esc_html__('Emails', 'notifal'); ?>
                        </button>
                        <button type="button" class="notifal-button secondary" data-tab="integrations">
                            <?php echo esc_html__('Integrations', 'notifal'); ?>
                        </button>
                    </nav>

                    <div class="notifal-form-edit-save notifal-justify-center notifal-flex">
                        <button type="submit" class="notifal-button" id="notifal-save-form">
                            <?php echo esc_html__('Save Form', 'notifal'); ?>
                        </button>
                    </div>
                </aside>

                <section class="notifal-card notifal-content notifal-full-width notifal-form-edit-content">
                    <?php
                    include __DIR__ . '/partials/tab-basics.php';
                    include __DIR__ . '/partials/tab-fields.php';
                    include __DIR__ . '/partials/tab-emails.php';
                    include __DIR__ . '/partials/tab-integrations.php';
                    ?>
                </section>
            </div>
        </form>
    </div>
</div>

<?php
ToastRenderer::renderGlobalContainer();
?>
