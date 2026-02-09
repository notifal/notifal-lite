<?php

namespace Notifal\Core\Foundation;

use Notifal\Core\Foundation\Container;

defined('ABSPATH') || exit;

/**
 * Class AbstractServiceProvider
 *
 * Base class for all module ServiceProviders to handle
 * conditional registration of services with dependency injection.
 *
 * Supports single and multiple service registration
 * with optional conditions for activation.
 *
 * Example usage in child ServiceProvider:
 * protected static array $services = [
 *     MyService::class,
 *     [
 *         'condition' => [PluginDetector::class, 'isElementorActive'],
 *         'class'     => [
 *             ElementorWidgetA::class,
 *             ElementorWidgetB::class,
 *         ],
 *     ],
 * ];
 *
 * @package Notifal\Core\Foundation
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
abstract class AbstractServiceProvider
{
    /**
     * List of services to register.
     * Can be class names or arrays with 'class' and 'condition'.
     *
     * @var array
     * @since 2.0.0
     */
    protected static array $services = [];

    /**
     * Hook to allow external filtering of services.
     * Modules should define their own unique filter hook.
     *
     * @var string
     * @since 2.0.0
     */
    protected const FILTER_HOOK = '';

    /**
     * Boot the service provider.
     * Optional method for child classes to override with custom initialization logic.
     *
     * @return void
     * @since 2.0.0
     */
    public function boot(): void
    {
        // Optional method for child classes to override
    }

    /**
     * Register all services defined in $services.
     *
     * Resolves dependencies via the Container,
     * applies optional conditions, and calls `register()` and `boot()` methods
     * on each service if they exist.
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        $container = Container::getInstance();
        $services  = static::$services;

        // Apply filter hook for external modification of services
        if (static::FILTER_HOOK) {
            $services = apply_filters(static::FILTER_HOOK, $services);
        }

        // Call boot() on the ServiceProvider itself first (higher priority)
        if (method_exists(static::class, 'boot')) {
            (new static())->boot();
        }

        foreach ($services as $service) {
            $classes = [];

            if (is_string($service)) {
                $classes[] = $service;
            } elseif (isset($service['class'])) {
                $classes = is_array($service['class']) ? $service['class'] : [$service['class']];
            }

            $should_register = true;
            if (isset($service['condition']) && is_callable($service['condition'])) {
                $should_register = call_user_func($service['condition']);
            }

            if (! $should_register) {
                continue;
            }

            foreach ($classes as $class) {
                if (class_exists($class)) {
                    $instance = $container->get($class);

                    if (method_exists($instance, 'register')) {
                        $instance->register();
                    }

                    if (method_exists($instance, 'boot')) {
                        $instance->boot();
                    }
                }
            }
        }
    }
}
