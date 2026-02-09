<?php

namespace Notifal\Modules\Templates;

use Notifal\Core\Foundation\AbstractServiceProvider;
use Notifal\Core\Foundation\Container;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\AssetsRegistrar;
use Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Hooks\PreviewRenderer as ElementorPreviewRenderer;
use Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Panel\TagsPanel;
use Notifal\Modules\Templates\Infrastructure\WordPress\Registration\PostTypeRegistrar;
use Notifal\Modules\Templates\Infrastructure\WordPress\Registration\TaxonomyRegistrar;
use Notifal\Modules\Templates\Infrastructure\WordPress\Registration\FeaturedImageApiRegister;
use Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\RegisterBlocks;
use Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Widgets\WidgetsRegistrar;
use Notifal\Modules\Templates\Presentation\Admin\Assets\EditorAssets;
use Notifal\Modules\Templates\Presentation\Admin\Controllers\Ajax\ImportController;
use Notifal\Modules\Templates\Presentation\Admin\Controllers\ExportController;
use Notifal\Modules\Templates\Presentation\Admin\ListTable\ColumnsController;
use Notifal\Modules\Templates\Presentation\Admin\Routes\AdminRouteController;
use Notifal\Modules\Templates\Presentation\Admin\Menu\MenuController;
use Notifal\Modules\Templates\Presentation\Frontend\Routes\PreviewRouteController;
use Notifal\Modules\Templates\Presentation\Frontend\Assets\FrontendAssetsRegistrar;
use Notifal\Modules\Templates\Application\Services\TemplateUrlService;
use Notifal\Modules\Templates\Application\Services\PreviewDataResolver;
use Notifal\Modules\Templates\Contracts\TemplateExporterInterface;
use Notifal\Modules\Templates\Contracts\TemplateBuilderInterface;
use Notifal\Domain\Products\ProductFetcherInterface;
use Notifal\Domain\Orders\OrderFetcherInterface;
use Notifal\Infrastructure\WordPress\Services\UserFetcher;
use Notifal\Infrastructure\WordPress\Admin\Settings\Services\PostTypeDiscoveryService;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\ContentSourceService;
use Notifal\Domain\Tags\TagManager;

defined('ABSPATH') || exit;

/**
 * Service provider for Templates module.
 *
 * Registers all services, controllers, assets, and infrastructure components
 * required for the template functionality including post types, admin interfaces,
 * frontend assets, and builder integrations (Elementor and Block Editor).
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ServiceProvider extends AbstractServiceProvider
{

    /**
     * List of services to register for the Templates module.
     *
     * Includes core services, conditional services for Elementor and Block Editor support,
     * and admin/frontend components required for template management.
     *
     * @var array
     * @since 2.0.0
     */
    protected static array $services = [
        // Core registration services
        PostTypeRegistrar::class,
        TaxonomyRegistrar::class,
        FeaturedImageApiRegister::class,

        // Admin interface services
        EditorAssets::class,
        AdminRouteController::class,
        ExportController::class,
        MenuController::class,
        ColumnsController::class,

        // Frontend services
        PreviewRouteController::class,
        FrontendAssetsRegistrar::class,

        // Block Editor services (conditional)
        RegisterBlocks::class,

        // Elementor-specific services (conditional on Elementor being active)
        [
            'condition' => [PluginDetector::class, 'isElementorActive'],
            'class'     => [
                WidgetsRegistrar::class,
                AssetsRegistrar::class,
                TagsPanel::class,
                ElementorPreviewRenderer::class,
                \Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Services\ElementorTagsExportProcessor::class,
                \Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Hooks\ExportHooks::class,
                \Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Services\ElementorProCountdownExtender::class,
            ],
        ],

        // Block Editor-specific services (conditional on block editor functions)
        [
            'condition' => ['function_exists', 'register_block_type'],
            'class'     => [
                \Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\AssetsRegistrar::class,
                \Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\BlockEditorTemplateBuilder::class,
                \Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Hooks\PreviewRenderer::class,
                \Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Hooks\FrontendContentProcessor::class,
                \Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Panel\TagsPanel::class,
                \Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\BlockEditorTagsExportProcessor::class,
                \Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Hooks\ExportHooks::class,
            ],
        ],
    ];

    /**
     * Filter hook constant for external service filtering.
     *
     * Allows external code to modify the list of registered services
     * for the Templates module.
     *
     * @var string
     * @since 2.0.0
     */
    protected const FILTER_HOOK = FilterHooks::TEMPLATE_SERVICES;

    /**
     * Boot the service provider with custom service bindings.
     *
     * Registers singleton services with their dependencies and binds interfaces
     * to appropriate implementations based on available plugins (Elementor/Block Editor).
     * Also registers conditional services that depend on plugin availability.
     *
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public function boot(): void
    {
        $container = Container::getInstance();

        // Register core singleton services
        $this->registerCoreServices($container);

        // Register builder-specific services based on plugin availability
        $this->registerBuilderServices($container);

        // Bind interfaces to appropriate implementations
        $this->bindInterfaces($container);

        // Register AJAX handlers for import functionality
        ImportController::register();
    }

    /**
     * Register core singleton services required by all template functionality.
     *
     * @param Container $container Dependency injection container
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function registerCoreServices(Container $container): void
    {
        // Register PreviewDataResolver for template data resolution
        $container->singleton(PreviewDataResolver::class, function () use ($container) {
            return new PreviewDataResolver(
                $container->get(ProductFetcherInterface::class),
                $container->get(OrderFetcherInterface::class),
                $container->get(UserFetcher::class),
                $container->get(PostTypeDiscoveryService::class)
            );
        });

        // Register TemplateUrlService for URL generation
        $container->singleton(TemplateUrlService::class, function () {
            return new TemplateUrlService();
        });
    }

    /**
     * Register builder-specific services based on available plugins.
     *
     * Conditionally registers services for Elementor and Block Editor support
     * to avoid loading unnecessary code when plugins are not active.
     *
     * @param Container $container Dependency injection container
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function registerBuilderServices(Container $container): void
    {
        // Elementor-specific services (only load if Elementor is active)
        if (PluginDetector::isElementorActive()) {
            $this->registerElementorServices($container);
        }

        // Block Editor-specific services (only load if block editor functions exist)
        if (function_exists('register_block_type')) {
            $this->registerBlockEditorServices($container);
        }
    }

    /**
     * Register Elementor-specific services and their dependencies.
     *
     * @param Container $container Dependency injection container
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function registerElementorServices(Container $container): void
    {
        // Register Elementor Template Builder
        $container->singleton(
            \Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Services\ElementorTemplateBuilder::class,
            function () use ($container) {
                return new \Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Services\ElementorTemplateBuilder(
                    $container->get(PreviewDataResolver::class),
                    $container->get(OrderFetcherInterface::class),
                    $container->get(UserFetcher::class),
                    $container->get(ContentSourceService::class),
                    $container->get(PostTypeDiscoveryService::class)
                );
            }
        );

        // Register Elementor Tags Export Processor
        $container->singleton(
            \Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Services\ElementorTagsExportProcessor::class,
            function () use ($container) {
                return new \Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Services\ElementorTagsExportProcessor(
                    $container->get(TagManager::class)
                );
            }
        );
    }

    /**
     * Register Block Editor-specific services and their dependencies.
     *
     * @param Container $container Dependency injection container
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function registerBlockEditorServices(Container $container): void
    {
        // Register Block Editor Template Builder
        $container->singleton(
            \Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\BlockEditorTemplateBuilder::class,
            function () use ($container) {
                return new \Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\BlockEditorTemplateBuilder(
                    $container->get(PreviewDataResolver::class),
                    $container->get(OrderFetcherInterface::class),
                    $container->get(UserFetcher::class)
                );
            }
        );

        // Register Block Editor Tags Export Processor
        $container->singleton(
            \Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\BlockEditorTagsExportProcessor::class,
            function () use ($container) {
                return new \Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\BlockEditorTagsExportProcessor(
                    $container->get(TagManager::class),
                    $container->get(PreviewDataResolver::class)
                );
            }
        );
    }

    /**
     * Bind template interfaces to appropriate implementations based on plugin availability.
     *
     * Dynamically selects Elementor or Block Editor implementations
     * depending on which plugin is active.
     *
     * @param Container $container Dependency injection container
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function bindInterfaces(Container $container): void
    {
        if (PluginDetector::isElementorActive()) {
            // Bind to Elementor implementations when Elementor is active
            $container->bind(
                TemplateExporterInterface::class,
                \Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Services\ElementorTemplateExporter::class
            );
            $container->bind(
                TemplateBuilderInterface::class,
                \Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Services\ElementorTemplateBuilder::class
            );
        } else {
            // Bind to Block Editor implementations as fallback
            $container->bind(
                TemplateExporterInterface::class,
                \Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\BlockEditorTemplateExporter::class
            );
            $container->bind(
                TemplateBuilderInterface::class,
                \Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\BlockEditorTemplateBuilder::class
            );
        }
    }

    /**
     * Get the services provided by this service provider.
     *
     * Returns an array of service class names that this provider registers,
     * including both core services and conditional services.
     *
     * @return array Array of service class names
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function provides(): array
    {
        return [
            // Core services always provided
            PreviewDataResolver::class,
            TemplateUrlService::class,

            // Interface bindings (implementation varies by builder)
            TemplateExporterInterface::class,
            TemplateBuilderInterface::class,

            // Builder-specific services (registered conditionally)
            \Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\BlockEditorTemplateBuilder::class,
            \Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Services\ElementorTemplateBuilder::class,
            \Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services\BlockEditorTagsExportProcessor::class,
            \Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Services\ElementorTagsExportProcessor::class,
        ];
    }

}
