<?php

namespace Notifal\Modules\Ai;

use Notifal\Core\Foundation\AbstractServiceProvider;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Ai\Application\Services\AccessTokenManager;
use Notifal\Modules\Ai\Application\Services\AccountConnectionService;
use Notifal\Modules\Ai\Application\Services\AiInviteGreeting;
use Notifal\Modules\Ai\Application\Services\AiOnPageFormAutoConnectService;
use Notifal\Modules\Ai\Application\Services\AiOnPageImportService;
use Notifal\Modules\Ai\Application\Services\AiPublicConfigService;
use Notifal\Modules\Ai\Application\Services\GatewayReadinessService;
use Notifal\Modules\Ai\Application\Services\GatewayUrlResolver;
use Notifal\Modules\Ai\Application\Services\GeneratedContentSanitizer;
use Notifal\Modules\Ai\Application\Services\GenerationBridgeService;
use Notifal\Modules\Ai\Application\Services\InstallationCredentialStore;
use Notifal\Modules\Ai\Application\Services\LicenseConnectionProvisioner;
use Notifal\Modules\Ai\Application\Services\SecureOptionStorage;
use Notifal\Modules\Ai\Application\Services\SiteProfileService;
use Notifal\Modules\Ai\Domain\SiteIdentityBuilder;
use Notifal\Modules\Ai\Infrastructure\Gateway\AiGatewayClient;
use Notifal\Modules\Ai\Presentation\Admin\Assets\AiAssetsRegistrar;
use Notifal\Modules\Ai\Presentation\Admin\Controllers\Ajax\AiConnectionAjaxController;
use Notifal\Modules\Ai\Presentation\Admin\Controllers\Ajax\AiContentAjaxController;
use Notifal\Modules\Ai\Presentation\Admin\Controllers\Ajax\AiGenerationAjaxController;
use Notifal\Modules\Ai\Presentation\Admin\Controllers\Ajax\AiSiteProfileAjaxController;

defined('ABSPATH') || exit;

/**
 * Service provider for the shared hosted AI client infrastructure.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ServiceProvider extends AbstractServiceProvider
{
    /**
     * Services registered by the AI module.
     *
     * @var array<int, string>
     */
    protected static array $services = [
        GatewayUrlResolver::class,
        SecureOptionStorage::class,
        InstallationCredentialStore::class,
        AiGatewayClient::class,
        AccessTokenManager::class,
        SiteIdentityBuilder::class,
        AccountConnectionService::class,
        LicenseConnectionProvisioner::class,
        GatewayReadinessService::class,
        GenerationBridgeService::class,
        SiteProfileService::class,
        AiInviteGreeting::class,
        AiPublicConfigService::class,
        GeneratedContentSanitizer::class,
        AiOnPageFormAutoConnectService::class,
        AiOnPageImportService::class,
        AiAssetsRegistrar::class,
    ];

    /**
     * Filter hook allowing external modification of AI services.
     *
     * @var string
     */
    protected const FILTER_HOOK = FilterHooks::AI_SERVICES;

    /**
     * Boot AJAX controllers for the AI module.
     *
     * @since 3.0.0
     * @return void
     */
    public function boot(): void
    {
        // Register local AJAX boundaries for account, generation, and profile flows.
        AiConnectionAjaxController::register();
        AiGenerationAjaxController::register();
        AiSiteProfileAjaxController::register();
        AiContentAjaxController::register();
    }
}
