<?php

/**
 * Part of Omega - Container Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */

declare(strict_types=1);

namespace Omega\Container;

use Closure;
use Omega\Container\Exceptions\AliasException;
use Omega\Container\Exceptions\BindingResolutionException;
use Omega\Container\Exceptions\CircularAliasException;
use Omega\Container\Exceptions\EntryNotFoundException;
use Psr\Container\ContainerInterface as PSRContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Container Interface for Omega.
 *
 * This interface defines the contract for a dependency injection container in Omega.
 * It extends PSR-11's ContainerInterface, adding methods for binding, resolving,
 * calling, and injecting dependencies, as well as handling aliases and reflection caching.
 *
 * Implementations must provide mechanisms for:
 *   - Registering abstract-to-concrete bindings (`bind`, `set`),
 *   - Resolving instances (`make`, `build`, `get`),
 *   - Managing singleton/shared instances,
 *   - Handling aliases (`alias`, `getAlias`),
 *   - Injecting dependencies into existing objects (`injectOn`, `call`),
 *   - Reflection caching for performance (`getReflectionClass`, `getReflectionMethod`, `getConstructorParameters`),
 *   - Clearing container state (`clearCache`, `flush`) and checking for bound types (`bound`),
 *   - Retrieving internal metadata (`getBindings`, `getLastParameterOverride`).
 *
 * By extending PSR-11, this interface guarantees compatibility with standard container consumers
 * while providing a rich feature set for Omega's advanced dependency injection system.
 *
 * @category  Omega
 * @package   Container
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL V3.0+
 * @version   2.0.0
 */
interface ContainerInterface extends PSRContainerInterface
{
    /**
     * Register an alias for an abstract type.
     *
     * The alias will be resolved to the given abstract when requested.
     *
     * @param string $abstract The original abstract identifier.
     * @param string $alias The alias name.
     * @return void
     * @throws AliasException Thrown when an alias maps to itself.
     */
    public function alias(string $abstract, string $alias): void;

    /**
     * Register a binding in the container.
     *
     * Binds an abstract type to a concrete implementation. If no concrete
     * is provided, the abstract is assumed to be concrete itself.
     *
     * @param string $abstract The abstract identifier or class name.
     * @param Closure|string|null $concrete The concrete implementation or factory.
     * @param bool $shared Whether the binding should be shared (singleton).
     * @return void
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     */
    public function bind(string $abstract, Closure|string|null $concrete = null, bool $shared = false): void;

    /**
     * Build a concrete instance.
     *
     * If the concrete is a Closure, it is executed directly.
     * Otherwise, the class is instantiated through the resolver.
     *
     * @param string|Closure $concrete The concrete class name or factory Closure.
     * @param array<int|string, mixed> $parameters Parameters to override dependency resolution.
     * @return mixed The instantiated object.
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function build(string|Closure $concrete, array $parameters = []): mixed;

    /**
     * Determine whether the given abstract type is bound in the container.
     *
     * This method checks bindings, shared instances, and registered aliases
     * after resolving the abstract through the alias map.
     *
     * @param string $abstract The abstract type or identifier to check.
     * @return bool True if the type is bound or aliased, false otherwise.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     */
    public function bound(string $abstract): bool;

    /**
     * Call a callable and automatically inject its dependencies.
     *
     * @param callable|object|array|string $callable The callable to invoke.
     * @param array<int|string, mixed> $parameters Optional parameters to override injection.
     * @return mixed The result returned by the callable.
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function call(callable|object|array|string $callable, array $parameters = []): mixed;

    /**
     * Clear the internal reflection cache.
     *
     * This forces all cached reflection metadata (classes, methods,
     * constructors) to be rebuilt on the next resolution.
     *
     * @return $this The container instance for method chaining.
     */
    public function clearCache(): ContainerInterface;

    /**
     * Get a cached ReflectionClass instance for the given class or interface.
     *
     * @param string $class Fully-qualified class or interface name.
     * @return ReflectionClass<object> Reflection metadata for the given class.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function getReflectionClass(string $class): ReflectionClass;

    /**
     * Get a cached ReflectionMethod instance for the given class and method.
     *
     * @param string|object $class Class name or object instance.
     * @param string $method Method name to reflect.
     * @return ReflectionMethod Reflection metadata for the requested method.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function getReflectionMethod(string|object $class, string $method): ReflectionMethod;

    /**
     * Get the constructor parameters for the given class.
     *
     * @param string $class Fully-qualified class name.
     * @return ReflectionParameter[]|null List of constructor parameters,
     *                                   or null if the class has no constructor.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function getConstructorParameters(string $class): ?array;

    /**
     * Get the most recent parameter override set during resolution.
     *
     * @return array<int|string, mixed> Parameter overrides for the current resolution scope.
     */
    public function getLastParameterOverride(): array;

    /**
     * Retrieve all registered container bindings.
     *
     * @return array<string, array{concrete: Closure, shared: bool}> An array of bindings indexed by abstract type.
     */
    public function getBindings(): array;

    /**
     * Resolve a new instance from the container.
     *
     * Unlike get(), this method always returns a new instance and bypasses
     * the shared instance cache.
     *
     * @param string|class-string $name The entry identifier or class name.
     * @param array<int|string, mixed> $parameters Parameters to override dependency resolution.
     * @return mixed The newly resolved instance.
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function make(string $name, array $parameters = []): mixed;

    /**
     * Inject dependencies into an existing object instance.
     *
     * @param object $instance The object on which dependencies should be injected.
     * @return object The same instance, after dependency injection.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function injectOn(object $instance): object;

    /**
     * Define a value or object in the container.
     *
     * If a Closure is provided, it will be treated as a factory for a shared
     * instance. Otherwise, the value is stored as a resolved singleton.
     *
     * @param string $name The entry identifier.
     * @param mixed $value The value or factory to store.
     * @return void
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     */
    public function set(string $name, mixed $value): void;

    /**
     * Remove all bindings, instances, aliases, and cached services.
     *
     * This resets the container to a clean state.
     *
     * @return void
     */
    public function flush(): void;

    /**
     * Resolve and return the final alias for an abstract type.
     *
     * If the abstract is aliased multiple times, all aliases are resolved
     * recursively until the original abstract is reached.
     *
     * @param string $abstract The abstract identifier.
     * @return string The resolved abstract identifier.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     */
    public function getAlias(string $abstract): string;
}
