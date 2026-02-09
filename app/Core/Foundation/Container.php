<?php

namespace Notifal\Core\Foundation;

defined('ABSPATH') || exit;

/**
 * Class Container
 *
 * A simple dependency injection container with auto-resolution.
 * Provides singleton and transient service bindings with automatic
 * dependency resolution through reflection-based constructor injection.
 *
 * @package Notifal\Core\Foundation
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class Container
{
    /**
     * @var Container|null Singleton instance
     */
    protected static $instance = null;

    /**
     * @var array Service bindings (abstract => concrete)
     */
    protected $bindings = [];

    /**
     * @var array Singleton instances (abstract => instance)
     */
    protected $singletons = [];

    /**
     * Get container singleton instance.
     *
     * Ensures only one container instance exists throughout the application
     * lifecycle, following the singleton pattern for dependency management.
     *
     * @return Container
     */
    public static function getInstance(): Container
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Bind a class or factory to an abstract identifier.
     *
     * Creates a transient binding that resolves a new instance each time
     * the service is requested. Supports both class names and closures.
     *
     * @param string $abstract Service identifier
     * @param \Closure|string $concrete Class name or factory closure
     * @return void
     */
    public function bind($abstract, $concrete)
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Bind a singleton service to an abstract identifier.
     *
     * Creates a singleton binding that resolves the same instance every time
     * the service is requested. The instance is lazily instantiated on first access.
     *
     * @param string $abstract Service identifier
     * @param \Closure|string $concrete Class name or factory closure
     * @return void
     */
    public function singleton($abstract, $concrete)
    {
        $this->bindings[$abstract] = $concrete;
        $this->singletons[$abstract] = null;
    }

    /**
     * Resolve a class or singleton instance by abstract identifier.
     *
     * Returns the appropriate instance based on binding type (singleton vs transient).
     * For singletons, returns the cached instance; for transients, resolves a new instance.
     *
     * @param string $abstract Service identifier to resolve
     * @return mixed Resolved service instance
     */
    public function get($abstract)
    {
        if (isset($this->singletons[$abstract])) {
            if ($this->singletons[$abstract] === null) {
                $this->singletons[$abstract] = $this->resolve($abstract);
            }
            return $this->singletons[$abstract];
        }

        return $this->resolve($abstract);
    }

    /**
     * Resolve a class with automatic dependency injection.
     *
     * Uses reflection to analyze constructor parameters and recursively resolve
     * dependencies. Supports both bound services and concrete classes with
     * type-hinted constructor parameters.
     *
     * @param string $abstract Class name or service identifier to resolve
     * @return mixed Instantiated class with injected dependencies
     * @throws \InvalidArgumentException When binding or class resolution fails
     */
    protected function resolve($abstract)
    {
        if (isset($this->bindings[$abstract])) {
            $concrete = $this->bindings[$abstract];

            if ($concrete instanceof \Closure) {
                return $concrete($this);
            }

            if (is_string($concrete) && class_exists($concrete)) {
                return $this->resolve($concrete);
            }

            throw new \InvalidArgumentException(
                'Invalid binding for ' . sanitize_text_field((string) $abstract)
            );
        }

        if (!class_exists($abstract)) {
            throw new \InvalidArgumentException(
                'Class ' . sanitize_text_field((string) $abstract) . ' does not exist.'
            );
        }

        $reflection = new \ReflectionClass($abstract);

        if (!$reflection->isInstantiable()) {
            throw new \InvalidArgumentException(
                'Class ' . sanitize_text_field((string) $abstract) . ' is not instantiable.'
            );
        }

        $constructor = $reflection->getConstructor();

        if (is_null($constructor)) {
            return new $abstract();
        }

        $params = $constructor->getParameters();
        $dependencies = [];

        foreach ($params as $param) {
            $reflectionType = $param->getType();

            if ($reflectionType === null || !($reflectionType instanceof \ReflectionNamedType)) {
                if ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                } else {
                    throw new \InvalidArgumentException(
                        'Cannot resolve class dependency \'' . sanitize_text_field((string) $param->getName()) . '\' for \'' . sanitize_text_field((string) $abstract) . '\''
                    );
                }
            } else {
                $dependencies[] = $this->get($reflectionType->getName());
            }
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}
