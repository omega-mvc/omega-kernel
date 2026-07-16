<?php

/**
 * Part of Omega - Config Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */

declare(strict_types=1);

namespace Omega\Config;

/**
 * Defines the contract for a configuration repository.
 *
 * This interface provides methods to manage configuration settings, including
 * retrieval, modification, deletion, and merging. Implementations of this interface
 * act as a central storage for application configuration data.
 *
 * @category  Omega
 * @package   Config
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */
interface ConfigRepositoryInterface
{
    /**
     * Retrieves all configuration settings.
     *
     * @return array The entire configuration data as an associative array.
     */
    public function getAll(): array;

    /**
     * Checks if a specific configuration key exists.
     *
     * @param string $key The configuration key to check.
     * @return bool True if the key exists, false otherwise.
     */
    public function has(string $key): bool;

    /**
     * Retrieves the value associated with a given key.
     *
     * @param string $key     The configuration key to retrieve.
     * @param mixed  $default An optional default value if the key does not exist.
     * @return mixed The configuration value or the default if not found.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Sets a configuration key to a given value.
     *
     * @param string $key   The configuration key to set.
     * @param mixed  $value The value to assign to the key.
     * @return void
     */
    public function set(string $key, mixed $value): void;

    /**
     * Removes a specific configuration key.
     *
     * @param string $key The configuration key to remove.
     * @return void
     */
    public function remove(string $key): void;

    /**
     * Clears all stored configuration settings.
     *
     * @return void
     */
    public function clear(): void;

    /**
     * Appends a value to an existing configuration key.
     *
     * If the key does not exist, an array is initialized before appending the value.
     *
     * @param string $key   The configuration key to append to.
     * @param mixed  $value The value to append.
     * @return void
     */
    public function push(string $key, mixed $value): void;

    /**
     * Merges another configuration repository into the current one.
     *
     * The merge can be applied to the entire configuration or
     * a specific key using a given merge strategy.
     *
     * @param ConfigRepositoryInterface     $configuration The configuration to merge.
     * @param string|null                   $key          The key under which to merge the data (optional).
     * @param MergeStrategy|string|int|null $strategy     The merging strategy (optional).
     * @return void
     */
    public function merge(
        ConfigRepositoryInterface $configuration,
        ?string $key = null,
        MergeStrategy|string|int|null $strategy = null
    ): void;
}
