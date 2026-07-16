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

use ArrayAccess;
use Closure;
use Omega\Container\Exceptions\AliasException;
use Omega\Container\Exceptions\BindingResolutionException;
use Omega\Container\Exceptions\CircularAliasException;
use Omega\Container\Exceptions\EntryNotFoundException;
use Psr\Container\ContainerExceptionInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReturnTypeWillChange;

use function array_pop;
use function class_exists;
use function compact;
use function end;
use function interface_exists;
use function is_object;
use function sprintf;

use const ARRAY_FILTER_USE_BOTH;

/**
 * A dependency injection container that manages bindings, singletons, aliases, and resolves dependencies.
 *
 * This container allows registering services, resolving classes with constructor injection,
 * calling callables with automatic dependency injection, and managing shared instances.
 *
 * @category  Omega
 * @package   Container
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */
class Container implements ArrayAccess, ContainerInterface
{
    #region Property
    /** @var array<string, array{concrete: Closure, shared: bool}> Container's bindings for abstract types
     * @noinspection PhpGetterAndSetterCanBeReplacedWithPropertyHooksInspection
     */
    protected array $bindings = [];

    /** @var array<string, mixed> Container's shared instances (singleton cache) */
    protected array $instances = [];

    /** @var array<string, string> Registered aliases for abstract types */
    protected array $aliases = [];

    /** @var ?Resolver Dependency resolver instance for class instantiation */
    private ?Resolver $resolver = null;

    /** @var ?Invoker Callable invoker instance for automatic dependency injection */
    private ?Invoker $invoker = null;

    /** @var ?ReflectionCache Reflection cache instance to optimize repeated reflection calls */
    private ?ReflectionCache $reflectionCache = null;

    /** @var ?Injector Dependency injector instance to inject existing objects */
    private ?Injector $injector = null;

    /** @var list<array> Stack of parameter overrides for resolving dependencies */
    protected array $with = [];
    #endregion

    #region Public Method
    /**
     * {@inheritdoc}
     */
    public function bind(string $abstract, Closure|string|null $concrete = null, bool $shared = false): void
    {
        $abstract = $this->getAlias($abstract);

        $concrete ??= $abstract;

        // If the concrete is not a Closure, we will make it one.
        if (false === $concrete instanceof Closure) {
            $concrete = $this->getClosure($abstract, $concrete);
        }

        $this->bindings[$abstract] = compact('concrete', 'shared');
    }

    /**
     * {@inheritdoc}
     *
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function get(string $id): mixed
    {
        if (false === $this->has($id)) {
            throw new EntryNotFoundException($id);
        }

        return $this->resolve($id);
    }

    /**
     * {@inheritdoc}
     *
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function make(string $name, array $parameters = []): mixed
    {
        return $this->resolve($name, $parameters, false);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $name, mixed $value): void
    {
        // If the value is a Closure,
        // it's a factory for a shared instance.
        if ($value instanceof Closure) {
            $this->bind($name, $value, true);

            return;
        }

        $name = $this->getAlias($name);

        // Store the value directly as a resolved.
        $this->instances[$name] = $value;
        $this->bindings[$name]  = [
            'concrete' => fn () => $this->instances[$name],
            'shared'   => true,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     */
    public function has(string $id): bool
    {
        if ($this->bound($id)) {
            return true;
        }

        if (class_exists($id)) {
            $ref = new ReflectionClass($id);
            return $ref->isInstantiable();
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function alias(string $abstract, string $alias): void
    {
        if ($abstract === $alias) {
            throw new AliasException($abstract);
        }

        $this->aliases[$alias] = $abstract;
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias(string $abstract): string
    {
        return $this->resolveAlias($abstract, []);
    }

    /**
     * {@inheritdoc}
     *
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function build(string|Closure $concrete, array $parameters = []): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        return $this->getResolver()->resolveClass($concrete, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function getReflectionClass(string $class): ReflectionClass
    {
        return $this->getReflectionCache()->getReflectionClass($class, function () use ($class) {
            if (false === class_exists($class) && false === interface_exists($class)) {
                throw new ReflectionException(
                    sprintf(
                        "Class %s does not exist",
                        $class
                    )
                );
            }

            return new ReflectionClass($class);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getReflectionMethod(string|object $class, string $method): ReflectionMethod
    {
        $className = is_object($class) ? $class::class : $class;

        return $this->getReflectionCache()->getReflectionMethod(
            $className,
            $method,
            fn () => new ReflectionMethod($class, $method)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getConstructorParameters(string $class): ?array
    {
        return $this->getReflectionCache()->getConstructorParameters($class, function () use ($class) {
            $reflector   = $this->getReflectionClass($class);
            $constructor = $reflector->getConstructor();

            return $constructor?->getParameters();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getLastParameterOverride(): array
    {
        return $this->with ? end($this->with) : [];
    }

    /**
     * {@inheritdoc}
     *
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function call(callable|object|array|string $callable, array $parameters = []): mixed
    {
        return $this->getInvoker()->call($callable, $parameters);
    }

    /**
     * {@inheritdoc}
     *
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function injectOn(object $instance): object
    {
        return $this->getInjector()->inject($instance);
    }

    /**
     * {@inheritdoc}
     */
    public function bound(string $abstract): bool
    {
        $abstract = $this->getAlias($abstract);

        return isset($this->bindings[$abstract])
            || isset($this->instances[$abstract]);
    }

    /**
     * {@inheritdoc}
     */
    public function clearCache(): self
    {
        $this->getReflectionCache()->clear();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): void
    {
        $this->bindings        = [];
        $this->instances       = [];
        $this->aliases         = [];
        $this->with            = [];
        $this->resolver        = null;
        $this->invoker         = null;
        $this->reflectionCache = null;
    }

    /**
     * {@inheritdoc}
     *
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * Retrieve an entry from the container (ArrayAccess).
     *
     * This is equivalent to calling {@see make()} on the given offset.
     *
     * @param string|class-string<mixed> $offset Entry name or class name.
     * @return mixed The resolved value.
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    #[ReturnTypeWillChange]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->make($offset);
    }

    /**
     * Register a value or factory in the container (ArrayAccess).
     *
     * Non-closure values are automatically wrapped in a factory closure.
     *
     * @param mixed $offset The entry identifier.
     * @param mixed $value  The value or factory to bind.
     * @return void
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->bind($offset, $value instanceof Closure ? $value : fn () => $value);
    }

    /**
     * Remove a binding or instance from the container (ArrayAccess).
     *
     * This also removes any aliases pointing to the same abstract.
     *
     * @param mixed $offset The entry identifier to remove.
     * @return void
     * @throws CircularAliasException If a circular alias reference is detected.
     */
    public function offsetUnset(mixed $offset): void
    {
        $offset = $this->getAlias((string)$offset);

        unset($this->instances[$offset], $this->bindings[$offset]);

        $this->aliases = array_filter(
            $this->aliases,
            fn (string $abstract, string $alias): bool => $abstract !== $offset && $alias !== $offset,
            ARRAY_FILTER_USE_BOTH
        );
    }
    #endregion

    #region Protected Method
    /**
     * Create the Closure used to resolve a concrete implementation.
     *
     * The returned Closure is responsible for building or resolving the concrete
     * type when the abstract is requested from the container.
     *
     * @param string $abstract The abstract type being bound.
     * @param string $concrete The concrete implementation.
     * @return Closure(self, array<int|string, mixed>): mixed
     */
    protected function getClosure(string $abstract, string $concrete): Closure
    {
        return function ($container, $parameters = []) use ($abstract, $concrete) {
            if ($abstract == $concrete) {
                return $container->build($concrete, $parameters);
            }

            return $container->resolve($concrete, $parameters, false);
        };
    }

    /**
     * Resolve the given abstract type from the container.
     *
     * This method handles alias resolution, shared instances, parameter overrides,
     * and delegates object creation to closures or the resolver.
     *
     * @param string $abstract The abstract type to resolve.
     * @param array<int|string, mixed> $parameters Parameters to override dependency resolution.
     * @param bool $useCache Whether to use cached shared instances.
     * @return mixed The resolved object or value.
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    protected function resolve(string $abstract, array $parameters = [], bool $useCache = true): mixed
    {
        $abstract = $this->getAlias($abstract);

        if ($useCache && isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        return $this->withParameterOverride($parameters, function () use ($abstract, $useCache) {

            $concrete = $this->getConcrete($abstract);

            $object = $concrete instanceof Closure
                ? $this->call($concrete, $this->getLastParameterOverride())
                : $this->build($concrete, $this->getLastParameterOverride());

            if ($useCache || $this->isShared($abstract)) {
                $this->instances[$abstract] = $object;
            }

            return $object;
        });
    }

    /**
     * Execute a callback within the scope of a temporary parameter override.
     *
     * This method pushes the given parameter overrides onto the internal stack,
     * making them available during dependency resolution. The stack entry is
     * automatically removed after the callback completes, even if an exception
     * is thrown, ensuring the container state remains consistent.
     *
     * @param array<int|string, mixed> $parameters Parameter overrides to apply
     *                                             during the callback execution.
     * @param callable(): mixed $callback The callback executed while the
     *                                    parameter override is active.
     * @return mixed The value returned by the callback.
     */
    private function withParameterOverride(array $parameters, callable $callback): mixed
    {
        $this->with[] = $parameters;

        try {
            return $callback();
        } finally {
            array_pop($this->with);
        }
    }

    /**
     * Determine whether the given abstract type is registered as shared.
     *
     * @param string $abstract The abstract type to check.
     * @return bool True if the binding is shared, false otherwise.
     */
    protected function isShared(string $abstract): bool
    {
        return isset($this->bindings[$abstract]['shared'])
            && true === $this->bindings[$abstract]['shared'];
    }

    /**
     * Get the concrete implementation for a given abstract type.
     *
     * If no binding exists, the abstract itself is returned.
     *
     * @param string $abstract The abstract type.
     * @return mixed The concrete implementation or the abstract itself.
     */
    protected function getConcrete(string $abstract): mixed
    {
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    #endregion

    #region Private Method
    /**
     * Lazily create and return the callable invoker.
     *
     * @return Invoker The invoker responsible for calling callables with injection.
     */
    private function getInvoker(): Invoker
    {
        if (null === $this->invoker) {
            $this->invoker = new Invoker($this);
        }

        return $this->invoker;
    }

    /**
     * Lazily instantiate and return the injector instance.
     *
     * @return Injector The dependency injector.
     */
    private function getInjector(): Injector
    {
        if (null === $this->injector) {
            $this->injector = new Injector($this);
        }

        return $this->injector;
    }

    /**
     * Resolve an alias to its final abstract name, following the alias chain.
     *
     * @param string $abstract  The abstract type or alias to resolve.
     * @param array<string, true> $resolving Map of aliases currently being resolved to detect cycles.
     * @return string The resolved abstract type.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     */
    private function resolveAlias(string $abstract, array $resolving): string
    {
        if (false === isset($this->aliases[$abstract])) {
            return $abstract;
        }

        if (isset($resolving[$abstract])) {
            throw new CircularAliasException($abstract);
        }

        $resolving[$abstract] = true;

        return $this->resolveAlias($this->aliases[$abstract], $resolving);
    }

    /**
     * Lazily create and return the reflection cache instance.
     *
     * @return ReflectionCache The reflection cache used by the container.
     */
    private function getReflectionCache(): ReflectionCache
    {
        if (null === $this->reflectionCache) {
            $this->reflectionCache = new ReflectionCache();
        }

        return $this->reflectionCache;
    }

    /**
     * Lazily create and return the dependency resolver.
     *
     * @return Resolver The resolver instance used to build concrete classes.
     */
    private function getResolver(): Resolver
    {
        if (null === $this->resolver) {
            $this->resolver = new Resolver($this);
        }

        return $this->resolver;
    }
    #endregion
}

