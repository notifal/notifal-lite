<?php

namespace Notifal\Domain\Tags;

use Notifal\Core\Foundation\AbstractServiceProvider;
use Notifal\Core\Foundation\Container;
use Notifal\Domain\Tags\Infrastructure\WordPress\Registration\DynamicKeysApiRegistrar;
use Notifal\Domain\Tags\Infrastructure\WordPress\Registration\TagsApiRegistrar;
use Notifal\Domain\Tags\Services\DateFormatterService;
use Notifal\Domain\Tags\Traits\TagResolverTrait;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Domain\Settings\Services\SettingsService;
use Notifal\Shared\Traits\IntegrityVerificationTrait;

defined('ABSPATH') || exit;

/**
 * Class TagsServiceProvider
 *
 * Bootstraps all tag-related services such as API routes and tag registration.
 *
 * @package Notifal\Domain\Tags
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ServiceProvider extends AbstractServiceProvider
{
    use TagResolverTrait;
    use IntegrityVerificationTrait;

    /**
     * List of services to be registered.
     *
     * @var array
     * @since 2.0.0
     */
    protected static array $services = [
        TagsApiRegistrar::class,
        DynamicKeysApiRegistrar::class,
    ];


    public function boot(): void
    {
        Container::getInstance()->singleton(DateFormatterService::class, function () {
            return new DateFormatterService();
        });

        Container::getInstance()->singleton(TagManager::class, function () {
            /** @var SettingsService $settingsService */
            $settingsService = Container::getInstance()->get(SettingsService::class);
            /** @var DateFormatterService $dateFormatterService */
            $dateFormatterService = Container::getInstance()->get(DateFormatterService::class);

            $manager = new TagManager($settingsService);

            // Register default tags
            RegisterTags::register($manager, $dateFormatterService);

            // Load stored generated tags when TagManager is created
            // This ensures all saved custom post type tags are available
            $this->loadStoredGeneratedTags($manager, $settingsService);

            return $manager;
        });

        $this->verify_activation_guard_integrity();
    }

    
    /**
     * Load all stored generated tags into the TagManager
     *
     * This ensures that saved custom post type tags are registered
     * when the TagManager is created.
     *
     * @param TagManager $manager The TagManager instance
     * @param SettingsService $settingsService Settings service instance
     * @return void
     * @since 2.0.0
     */
    private function loadStoredGeneratedTags(TagManager $manager, SettingsService $settingsService): void
    {
        try {
            $storedTags = $settingsService->get('generated_posttype_tags', []);

            foreach ($storedTags as $postTypeName => $tags) {
                if (!empty($tags)) {
                    $this->registerPostTypeTags($manager, $postTypeName, $tags);
                }
            }

        } catch (\Exception $e) {
            // Silently handle errors to avoid breaking tag loading
        }
    }




    /**
     * Hook to filter services if needed.
     *
     * @var string
     * @since 2.0.0
     */
    protected const FILTER_HOOK = FilterHooks::TAGS_SERVICES;
}
