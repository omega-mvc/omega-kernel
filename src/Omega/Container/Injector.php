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

use Omega\Container\Attribute\Inject;
use Omega\Container\Exceptions\BindingResolutionException;
use Omega\Container\Exceptions\CircularAliasException;
use Omega\Container\Exceptions\EntryNotFoundException;
use Psr\Container\ContainerExceptionInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

use function array_filter;
use function array_key_exists;
use function array_map;
use function is_array;

/**
 * Injector class responsible for injecting dependencies into objects.
 *
 * This class uses the #[Inject] attribute to inject dependencies into public
 * methods and properties of an object. Dependencies are resolved via the
 * container or the resolver.
 *
 * @category  Omega
 * @package   Container
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */
final class Injector
{
    /** @var Resolver|null Resolver instance used for resolving method parameters and property dependencies. */
    private ?Resolver $resolver;

    /**
     * Create a new Injector instance.
     *
     * @param Container $container The service container used to resolve dependencies.
     * @return void
     */
    public function __construct(private readonly Container $container)
    {
        $this->resolver = new Resolver($container);
    }

    /**
     * Inject dependencies into an existing object instance.
     *
     * This method inspects the object for public methods and properties annotated with #[Inject]
     * and resolves their dependencies using the container or the optional inject configuration.
     *
     * @param object $instance The object instance to perform injection upon
     * @return object The same object instance with all resolved dependencies injected
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function inject(object $instance): object
    {
        $reflector = $this->container->getReflectionClass($instance::class);

        $this->injectMethods($instance, $reflector);
        $this->injectProperties($instance, $reflector);

        return $instance;
    }

    /**
     * Inject dependencies into public methods annotated with #[Inject].
     *
     * @param object $instance The object instance to inject dependencies into
     * @param ReflectionClass<object> $reflector Reflection class of the object
     * @return void
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    private function injectMethods(object $instance, ReflectionClass $reflector): void
    {
        array_map(
            function (ReflectionMethod $method) use ($instance) {
                $attributes = $method->getAttributes(Inject::class);

                if (empty($attributes) || $method->isConstructor() || $method->isStatic()) {
                    return;
                }

                $injectConfig = $attributes[0]->newInstance()->getName();
                $parameters = $method->getParameters();

                if ($this->canInjectMethod($parameters, $injectConfig)) {
                    $this->invokeMethodWithDependencies($instance, $method, $parameters, $injectConfig);
                }
            },
            $reflector->getMethods(ReflectionMethod::IS_PUBLIC)
        );
    }

    /**
     * Inject dependencies into public properties annotated with #[Inject].
     *
     * @param object $instance The object instance to inject dependencies into
     * @param ReflectionClass<object> $reflector Reflection class of the object
     * @return void
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    private function injectProperties(object $instance, ReflectionClass $reflector): void
    {
        array_map(
            fn(ReflectionProperty $property) => $this->injectProperty($instance, $property),
            $reflector->getProperties(ReflectionProperty::IS_PUBLIC)
        );
    }

    /**
     * Determine whether a method can have all its parameters injected.
     *
     * @param ReflectionParameter[] $parameters Array of method parameters
     * @param mixed $injectConfig Optional injection configuration for method parameters
     * @return bool True if all parameters are injectable, false otherwise
     */
    private function canInjectMethod(array $parameters, mixed $injectConfig): bool
    {
        $invalidParams = array_filter(
            $parameters,
            fn(ReflectionParameter $param) => !$this->isParameterInjectable($param, $injectConfig)
        );

        return empty($invalidParams);
    }

    /**
     * Determine whether a single parameter can be injected.
     *
     * @param ReflectionParameter $param The method parameter to check
     * @param mixed $injectConfig Optional injection configuration
     * @return bool True if the parameter can be injected, false otherwise
     */
    private function isParameterInjectable(ReflectionParameter $param, mixed $injectConfig): bool
    {
        if (!$this->hasExplicitInjection($param, $injectConfig)) {
            return $this->isTypeInjectable($param);
        }

        return true;
    }

    /**
     * Determine whether the parameter type is eligible for automatic injection.
     *
     * This method checks if the given parameter has a resolvable type hint that
     * can be used by the container to perform dependency injection.
     *
     * A parameter is considered injectable if:
     * - It has a declared type
     * - The type is a named type (i.e., not a union or intersection type)
     * - The type is not a built-in PHP type (e.g., int, string, bool, array)
     *
     * In practice, this means only class or interface type-hinted parameters
     * are eligible for automatic resolution via the container.
     *
     * This method is used as a fallback when no explicit injection configuration
     * is provided (e.g., via #[Inject] attribute or method-level configuration).
     *
     * @param ReflectionParameter $param The parameter to evaluate
     * @return bool True if the parameter type can be resolved and injected,
     *              false otherwise
     */
    private function isTypeInjectable(ReflectionParameter $param): bool
    {
        $type = $param->getType();

        return $type instanceof ReflectionNamedType && !$type->isBuiltin();
    }

    /**
     * Invoke a method with its dependencies resolved from the container.
     *
     * @param object $instance The object instance
     * @param ReflectionMethod $method The method to invoke
     * @param ReflectionParameter[] $parameters Method parameters
     * @param mixed $injectConfig Optional parameter injection configuration
     * @return void
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    private function invokeMethodWithDependencies(object $instance, ReflectionMethod $method, array $parameters, mixed $injectConfig): void
    {
        try {
            $dependencies = array_map(fn($param) => $this->resolveParam($param, $injectConfig), $parameters);
            $method->invokeArgs($instance, $dependencies);
        } catch (BindingResolutionException) {
            // Fail silently if binding cannot be resolved
        }
    }

    /**
     * Determine whether a parameter has explicit injection defined.
     *
     * @param ReflectionParameter $param The parameter to check
     * @param mixed $injectConfig Optional injection configuration
     * @return bool True if explicit injection is configured, false otherwise
     */
    private function hasExplicitInjection(ReflectionParameter $param, mixed $injectConfig): bool
    {
        $hasParamInject = !empty($param->getAttributes(Inject::class));

        $hasMethodInject = is_array($injectConfig) && array_key_exists($param->getName(), $injectConfig);

        return $hasParamInject || $hasMethodInject;
    }

    /**
     * Resolve a method parameter's dependency using container or injection config.
     *
     * @param ReflectionParameter $param Parameter to resolve
     * @param mixed $injectConfig Optional injection configuration
     * @return mixed The resolved dependency
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    private function resolveParam(ReflectionParameter $param, mixed $injectConfig): mixed
    {
        $paramName = $param->getName();

        $paramAttributes = $param->getAttributes(Inject::class);
        if (!empty($paramAttributes)) {
            $abstract = $paramAttributes[0]->newInstance()->getName();
            return $this->container->get($abstract);
        }

        if (is_array($injectConfig) && array_key_exists($paramName, $injectConfig)) {
            return $injectConfig[$paramName];
        }

        return $this->resolver->resolveParameterDependency($param);
    }

    /**
     * Inject a single public property with a resolved dependency.
     *
     * @param object $instance The object instance
     * @param ReflectionProperty $property The property to inject
     * @return void
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    private function injectProperty(object $instance, ReflectionProperty $property): void
    {
        $attributes = $property->getAttributes(Inject::class);
        if (empty($attributes)) {
            return;
        }

        $abstract = $attributes[0]->newInstance()->getName();

        try {
            if (!is_array($abstract)) {
                $dependency = $this->container->get($abstract);
                $property->setValue($instance, $dependency);
            }
        } catch (EntryNotFoundException) {
            // Fail silently if binding cannot be resolved
        }
    }
}
