<?php

namespace Notifal\Modules\Forms;

use Notifal\Core\Foundation\AbstractServiceProvider;
use Notifal\Core\Foundation\Container;
use Notifal\Domain\Settings\Services\SettingsService;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Forms\Application\Services\FormLicenseGuard;
use Notifal\Modules\Forms\Application\Services\FormsSettingsService;
use Notifal\Modules\Forms\Application\Services\HtmlBuilder\FormsHtmlBuilderIntegration;
use Notifal\Modules\Forms\Infrastructure\WordPress\Ajax\FormAdminAjaxController;
use Notifal\Modules\Forms\Infrastructure\WordPress\Ajax\FormSubmitAjaxController;
use Notifal\Modules\Forms\Infrastructure\WordPress\Database\MigrationService;
use Notifal\Modules\Forms\Infrastructure\WordPress\Registration\PostTypeRegistrar;
use Notifal\Modules\Forms\Presentation\Admin\Assets\FormsAdminAssets;
use Notifal\Modules\Forms\Presentation\Admin\ListTable\ColumnsController;
use Notifal\Modules\Forms\Presentation\Admin\Menu\MenuController;
use Notifal\Modules\Forms\Presentation\Admin\Routes\AdminRouteController;
use Notifal\Modules\Forms\Presentation\Frontend\Assets\FormsFrontendAssets;

defined('ABSPATH') || exit;

/**
 * Forms module service provider with Settings kill switch.
 *
 * When forms_enabled is false, no Forms services, assets, AJAX, or widgets load.
 * Re-enable Forms from Notifal → Addons.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ServiceProvider extends AbstractServiceProvider
{
    /**
     * Services registered when Forms is enabled.
     *
     * @var array<int, class-string>
     */
    protected static array $services = [
        PostTypeRegistrar::class,
        MenuController::class,
        AdminRouteController::class,
        ColumnsController::class,
        FormsAdminAssets::class,
        FormsFrontendAssets::class,
        FormsHtmlBuilderIntegration::class,
    ];

    /**
     * Allow external modification of Forms services.
     *
     * @since 3.0.0
     */
    protected const FILTER_HOOK = FilterHooks::FORMS_SERVICES;

    /**
     * Register the module only when the kill switch is on.
     *
     * @return void
     * @since 3.0.0
     */
    public static function register(): void
    {
        // Resolve settings without requiring Forms services (kill switch check).
        $container = Container::getInstance();
        $settingsService = $container->get(SettingsService::class);
        $formsSettings = new FormsSettingsService($settingsService);

        // Early exit: module disabled, load nothing (assets/AJAX/CPT/widgets).
        if (!$formsSettings->isEnabled()) {
            return;
        }

        parent::register();
    }

    /**
     * Boot Forms hooks that are not Container services.
     *
     * @return void
     * @since 3.0.0
     */
    public function boot(): void
    {
        // Database migrations for submission tables.
        MigrationService::init();

        // Public submit + admin AJAX endpoints.
        FormSubmitAjaxController::register();
        FormAdminAjaxController::register();

        // License guard on create/publish of Form CPT.
        FormLicenseGuard::register();
    }
}
