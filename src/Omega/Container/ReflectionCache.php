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
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

use function array_key_exists;

/**
 * ReflectionCache class for caching reflection data.
 *
 * This class caches reflection classes, methods, and constructor parameters
 * to avoid repeated reflection lookups, improving performance in dependency
 * resolution and method/property inspection.
 *
 * @category  Omega
 * @package   Container
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */
final class ReflectionCache
{
    /** @var array<string, ReflectionClass<object>> Cache for reflection class objects */
    private array $classCache = [];

    /** @var array<string, array<string, ReflectionMethod>> Cache for reflection methods */
    private array $methodCache = [];

    /** @var array<string, array<ReflectionParameter>|null> Cache for constructor parameters */
    private array $constructorCache = [];

    /**
     * Retrieve a cached reflection class or create and cache it.
     *
     * @param string $class Class name to reflect
     * @param Closure():ReflectionClass<object> $creator Closure to create ReflectionClass if not cached
     * @return ReflectionClass<object> The cached or newly created ReflectionClass instance
     */
    public function getReflectionClass(string $class, Closure $creator): ReflectionClass
    {
        if (isset($this->classCache[$class])) {
            return $this->classCache[$class];
        }

        return $this->classCache[$class] = $creator();
    }

    /**
     * Retrieve a cached reflection method or create and cache it.
     *
     * @param string $className The class name that owns the method
     * @param string $method The method name
     * @param Closure():ReflectionMethod $creator Closure to create ReflectionMethod if not cached
     * @return ReflectionMethod The cached or newly created ReflectionMethod instance
     */
    public function getReflectionMethod(string $className, string $method, Closure $creator): ReflectionMethod
    {
        if (isset($this->methodCache[$className][$method])) {
            return $this->methodCache[$className][$method];
        }

        return $this->methodCache[$className][$method] = $creator();
    }

    /**
     * Retrieve cached constructor parameters or create and cache them.
     *
     * @param string $class The class name to inspect
     * @param Closure():(?array<ReflectionParameter>) $creator Closure to create constructor parameters array
     *                                                         if not cached
     * @return array<ReflectionParameter>|null The cached or newly created constructor parameters, or null if none
     */
    public function getConstructorParameters(string $class, Closure $creator): ?array
    {
        if (array_key_exists($class, $this->constructorCache)) {
            return $this->constructorCache[$class];
        }

        return $this->constructorCache[$class] = $creator();
    }

    /**
     * Clear all cached reflection data.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->classCache       = [];
        $this->methodCache      = [];
        $this->constructorCache = [];
    }
}
