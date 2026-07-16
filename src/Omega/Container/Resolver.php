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

use Omega\Container\Exceptions\BindingResolutionException;
use Omega\Container\Exceptions\CircularAliasException;
use Omega\Container\Exceptions\EntryNotFoundException;
use Psr\Container\ContainerExceptionInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_reduce;
use function array_values;
use function implode;
use function is_null;
use function sprintf;

/**
 * Resolver class for resolving class dependencies automatically.
 *
 * This class is responsible for instantiating classes with constructor
 * dependencies, handling circular dependencies, and resolving parameters
 * from the container or defaults.
 *
 * @category  Omega
 * @package   Container
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */
final class Resolver
{
    /** @var array<string, bool> Stack of currently building classes to detect circular dependencies */
    private array $buildStack = [];

    private const string NOT_RESOLVED = '__OMEGA_NOT_RESOLVED__';

    /**
     * Create a new Resolver instance.
     *
     * @param Container $container The container used to resolve dependencies
     */
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * Instantiate a concrete instance of the given class type.
     *
     * @param string $concrete The class name to instantiate
     * @param array<int|string, mixed> $parameters Optional parameters to override constructor arguments
     * @return mixed The instantiated class with resolved dependencies
     * @throws BindingResolutionException If class is not instantiable or a dependency is unresolvable
     * @throws CircularAliasException If a circular dependency is detected
     * @throws ReflectionException If reflection fails
     */
    public function resolveClass(string $concrete, array $parameters = []): mixed
    {
        $reflector = $this->container->getReflectionClass($concrete);
        $this->ensureInstantiable($reflector);

        return $this->withBuildStack($concrete, function() use ($concrete, $parameters, $reflector) {
            $dependencies = $this->container->getConstructorParameters($concrete);

            if (is_null($dependencies)) {
                return new $concrete();
            }

            return $reflector->newInstanceArgs(
                $this->resolveDependencies($dependencies, $parameters)
            );
        });
    }

    /**
     * Resolve an array of constructor dependencies.
     *
     * @param ReflectionParameter[] $dependencies The constructor parameters to resolve
     * @param array<int|string, mixed> $parameters Optional overrides for parameters
     * @return array Resolved dependency instances
     */
    private function resolveDependencies(array $dependencies, array $parameters = []): array
    {
        $lastOverride = $this->container->getLastParameterOverride();

        return array_map(
            fn(ReflectionParameter $dependency) => $this->resolveSingleDependency($dependency, $parameters, $lastOverride),
            $dependencies
        );
    }

    /**
     * Resolve a single constructor or method parameter.
     *
     * @param ReflectionParameter $parameter The parameter to resolve
     * @return mixed The resolved value
     * @throws BindingResolutionException If the parameter cannot be resolved
     * @throws CircularAliasException If a circular dependency is detected
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException If a required container entry is missing
     * @throws ReflectionException If reflection fails
     */
    public function resolveParameterDependency(ReflectionParameter $parameter): mixed
    {
        $result = $this->tryResolveFromType($parameter);
        if ($result !== self::NOT_RESOLVED) {
            return $result;
        }

        $result = $this->tryResolveFromDefault($parameter);
        if ($result !== self::NOT_RESOLVED) {
            return $result;
        }

        return $this->unresolvable($parameter);
    }

    /**
     * Resolve a parameter without type hint.
     *
     * @param ReflectionParameter $parameter The parameter to resolve
     * @return mixed The resolved value or default
     * @throws BindingResolutionException If the parameter cannot be resolved
     */
    private function resolveUnTypedParameter(ReflectionParameter $parameter): mixed
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        return $this->unresolvable($parameter);
    }

    /**
     * Throw exception for an unresolvable parameter.
     *
     * @phpstan-return never
     * @param ReflectionParameter $parameter The parameter that cannot be resolved
     * @param bool $isUnion Whether the parameter is a union type
     * @throws BindingResolutionException Always
     */
    private function unresolvable(ReflectionParameter $parameter, bool $isUnion = false): void
    {
        $class     = $parameter->getDeclaringClass();
        $className = $class ? $class->getName() : 'unknown';
        $message   = $isUnion
            ? 'none of the types in the union are bound in the container'
            : 'the dependency is not bound and cannot be autowired';

        throw new BindingResolutionException(
            sprintf(
                "Unresolvable dependency resolving [%s] in class %s: %s",
                $parameter,
                $className,
                $message
            )
        );
    }

    /**
     * Gestisce l'incapsulamento dello stato dello stack.
     */
    private function withBuildStack(string $concrete, callable $callback): mixed
    {
        if (isset($this->buildStack[$concrete])) {
            $path = implode(' -> ', array_keys($this->buildStack)) . ' -> ' . $concrete;
            throw new BindingResolutionException(
                sprintf("Circular dependency detected while trying to build [%s]. Path: %s.", $concrete, $path)
            );
        }

        $this->buildStack[$concrete] = true;
        try {
            return $callback();
        } finally {
            unset($this->buildStack[$concrete]);
        }
    }

    /**
     * Estrazione della validazione (Guard Clause).
     */
    private function ensureInstantiable(ReflectionClass $reflector): void
    {
        if (!$reflector->isInstantiable()) {
            throw new BindingResolutionException(sprintf("Target [%s] is not instantiable.", $reflector->getName()));
        }
    }

    private function resolveSingleDependency(ReflectionParameter $dependency, array $parameters, array $lastOverride): mixed
    {
        if (array_key_exists($dependency->name, $parameters)) {
            return $parameters[$dependency->name];
        }

        if (array_key_exists($dependency->getPosition(), $parameters)) {
            return $parameters[$dependency->getPosition()];
        }

        if (array_key_exists($dependency->name, $lastOverride)) {
            return $lastOverride[$dependency->name];
        }

        return $this->resolveParameterDependency($dependency);
    }

    private function tryResolveFromType(ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();
        if (!$type) return self::NOT_RESOLVED;

        if ($type instanceof ReflectionIntersectionType) {
            $class = $parameter->getDeclaringClass()?->getName() ?? 'unknown';

            throw new BindingResolutionException(
                sprintf(
                    "Intersection types are not supported for dependency resolution of [%s] in class %s",
                    $parameter,
                    $class
                )
            );
        }

        $isUnion = $type instanceof ReflectionUnionType;
        $types = $isUnion ? $type->getTypes() : [$type];

        // Filtriamo solo le classi (non i tipi built-in)
        $classTypes = array_filter(
            $types,
            fn ($t): bool => $t instanceof ReflectionNamedType && !$t->isBuiltin()
        );

        // Estrarre il primo match dal container (il primo che risulta bound)
        $resolved = array_reduce($classTypes, function ($carry, $classType) {
            if ($carry !== null) return $carry;
            $name = $classType->getName();
            return $this->container->bound($name) ? $this->container->get($name) : null;
        });

        if ($resolved !== null) return $resolved;

        if (!$isUnion && !empty($classTypes)) {
            return $this->container->make(array_values($classTypes)[0]->getName());
        }

        return $type->allowsNull() ? null : self::NOT_RESOLVED;
    }

    private function tryResolveFromDefault(ReflectionParameter $parameter): mixed
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }
        return self::NOT_RESOLVED;
    }
}
