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

namespace Omega\Container\Attribute;

use Attribute;

/**
 * Attribute used to indicate that a dependency should be injected into a property, method, or parameter.
 *
 * This attribute is processed by the container's Injector to automatically resolve and assign
 * dependencies based on the type-hint or a custom name provided.
 *
 * Can be applied to:
 * - Methods: for setter injection
 * - Properties: for property injection
 * - Parameters: for constructor or method parameter injection
 *
 * Example usage:
 *
 * ```php
 * class MyService
 * {
 *     #[Inject(SomeDependency::class)]
 *     public SomeDependency $dependency;
 *
 *     #[Inject(['logger' => Logger::class])]
 *     public function setLogger(Logger $logger): void
 *     {
 *         $this->logger = $logger;
 *     }
 *
 *     public function __construct(#[Inject(Database::class)] Database $db)
 *     {
 *         $this->db = $db;
 *     }
 * }
 * ```
 *
 * @category   Omega
 * @package    Container
 * @subpackage Attribute
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    2.0.0
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class Inject
{
    /**
     * Constructor.
     *
     * @param string|array<string, string> $name Optional. The name of the dependency to inject, or
     *                                             an associative array mapping parameter/property names
     *                                             to dependency class names.
     */
    public function __construct(
        private string|array $name = [],
    ) {
    }

    /**
     * Returns the name of the dependency to inject.
     *
     * @return string|array<string, string> The dependency class name, or an associative array
     *                                      mapping parameter/property names to class names.
     */
    public function getName(): string|array
    {
        return $this->name;
    }
}
