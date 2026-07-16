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
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

use function array_key_exists;
use function array_map;
use function array_shift;
use function call_user_func_array;
use function class_exists;
use function is_array;
use function is_callable;
use function is_object;
use function is_string;
use function method_exists;
use function sprintf;

/**
 * Invoker class responsible for calling callables and injecting dependencies.
 *
 * This class supports closures, class methods, static methods, invokable objects,
 * and class names with an __invoke() method. Dependencies are automatically
 * resolved via the container and method/function reflection.
 *
 * @category  Omega
 * @package   Container
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */
final readonly class Invoker
{
    /**
     * Create a new Invoker instance.
     *
     * @param Container $container The container used to resolve dependencies
     */
    public function __construct(private Container $container)
    {
    }

    /**
     * Call the given callable and inject its dependencies.
     *
     * Supports closures, functions, static and instance methods, invokable classes.
     *
     * @param callable|object|array<string>|string $callable The callable to invoke
     * @param array<int|string, mixed> $parameters Optional parameters to override dependencies
     * @return mixed The result of the callable execution
     * @throws BindingResolutionException If a dependency cannot be resolved
     * @throws CircularAliasException If a circular alias is detected during resolution
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException If a required entry is missing from the container
     * @throws ReflectionException If reflection fails on the callable
     */
    public function call(callable|object|array|string $callable, array $parameters = []): mixed
    {
        // Handle array callable [object, method] or [class, method]
        if (is_array($callable)) {
            return $this->callMethod(instance: $callable[0], method: $callable[1], parameters: $parameters);
        }

        // Handle string ClassName::class (invokable)
        if (is_string($callable) && class_exists($callable)) {
            $reflectionClass = new ReflectionClass($callable);
            if (false === $reflectionClass->hasMethod('__invoke')) {
                throw new BindingResolutionException(
                    sprintf(
                        "Class %s does not have an __invoke() method. Cannot be used as invokable.",
                        $callable
                    )
                );
            }

            $instance     = $this->container->get($callable);
            $invokeMethod = $this->container->getReflectionMethod($callable, '__invoke');
            $dependencies = $this->resolveMethodDependencies($invokeMethod, $instance, $parameters);

            return $invokeMethod->invokeArgs($instance, $dependencies);
        }

        // Handle closure / function
        if (is_callable($callable) && !is_string($callable)) {
            $reflector    = new ReflectionFunction($callable);
            $dependencies = $this->resolveFunctionDependencies($reflector, $parameters);

            return call_user_func_array($callable, $dependencies);
        }

        // Handle object (invokable object)
        if (is_object($callable) && method_exists($callable, '__invoke')) {
            $reflectionMethod = $this->container->getReflectionMethod($callable, '__invoke');
            $dependencies     = $this->resolveMethodDependencies($reflectionMethod, $callable, $parameters);

            return $reflectionMethod->invokeArgs($callable, $dependencies);
        }

        throw new BindingResolutionException(
            'Unable to call the given callable. Unsupported type.'
        );
    }

    /**
     * Call a method on a class or object and inject dependencies.
     *
     * @param object|string $instance The object instance or class name
     * @param string $method The method name to invoke
     * @param array<int|string, mixed> $parameters Optional parameters to override dependencies
     * @return mixed The result of the method invocation
     * @throws BindingResolutionException If a dependency cannot be resolved
     * @throws CircularAliasException If a circular alias is detected
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException If a required entry is missing from the container
     * @throws ReflectionException If method reflection fails
     */
    private function callMethod(object|string $instance, string $method, array $parameters = []): mixed
    {
        // resolve class name
        if (is_string($instance)) {
            $instance = $this->container->get($instance);
        }

        $reflector    = $this->container->getReflectionMethod($instance, $method);
        $dependencies = $this->resolveFunctionDependencies($reflector, $parameters);

        return $reflector->invokeArgs($instance, $dependencies);
    }

    /**
     * Resolve dependencies for a function or closure.
     *
     * @param ReflectionFunctionAbstract $reflection Reflection of the function/closure
     * @param array<int|string, mixed> $parameters Optional parameters to override dependencies
     * @return array The resolved dependencies in order
     * @throws BindingResolutionException If a dependency cannot be resolved
     * @throws CircularAliasException If a circular alias is detected
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException If a required entry is missing from the container
     * @throws ReflectionException If parameter reflection fails
     */
    private function resolveFunctionDependencies(ReflectionFunctionAbstract $reflection, array $parameters = []): array
    {
        $resolved = array_map(
            fn($parameter) => $this->resolveParameter($parameter, $parameters),
            $reflection->getParameters()
        );

        return array_merge($resolved, array_values($parameters));
    }

    /**
     * Resolve dependencies for a method call.
     *
     * @param ReflectionMethod $method The reflection of the method
     * @param object $instance The object instance to invoke the method on
     * @param array<int|string, mixed> $parameters Optional parameters to override dependencies
     * @return array The resolved dependencies in order
     * @throws BindingResolutionException If a dependency cannot be resolved
     * @throws CircularAliasException If a circular alias is detected
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException If a required entry is missing from the container
     * @throws ReflectionException If parameter reflection fails
     */
    private function resolveMethodDependencies(
        ReflectionMethod $method,
        object $instance,
        array $parameters = []
    ): array {
        return array_map(
            fn (ReflectionParameter $parameter) => $this->resolveParameter($parameter, $parameters),
            $method->getParameters()
        );
    }

    private function resolveParameter(ReflectionParameter $parameter, array &$parameters): mixed
    {
        $name = $parameter->getName();
        $pos  = $parameter->getPosition();

        if (array_key_exists($name, $parameters)) {
            $value = $parameters[$name];
            unset($parameters[$name]);
            return $value;
        }

        if (array_key_exists($pos, $parameters)) {
            $value = $parameters[$pos];
            unset($parameters[$pos]);
            return $value;
        }

        $type = $parameter->getType();
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $this->container->get($type->getName());
        }

        if ($name === 'container' && $type === null) {
            return $this->container;
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if (!empty($parameters)) {
            return array_shift($parameters);
        }

        throw new BindingResolutionException(
            sprintf(
                "Unable to resolve dependency [%s] in callable",
                $parameter
            )
        );
    }
}
