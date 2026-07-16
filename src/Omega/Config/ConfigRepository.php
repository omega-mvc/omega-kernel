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

use ArrayAccess;
use ArrayIterator;
use Countable;
use Exception;
use IteratorAggregate;
use Traversable;

use function count;
use function is_array;
use function is_null;

/**
 * Configuration repository with advanced access and merging capabilities.
 *
 * This class extends `AbstractConfigRepository` and implements `ArrayAccess`, `Countable`,
 * and `IteratorAggregate` to provide a flexible interface for managing configuration settings.
 *
 * - Supports direct array access for retrieving and setting configuration values.
 * - Allows merging configurations using different strategies.
 * - Enables iteration over stored configuration values.
 * - Implements a countable mechanism for determining the number of stored settings.
 *
 * @category  Omega
 * @package   Config
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */
class ConfigRepository extends AbstractConfigRepository implements ArrayAccess, Countable, IteratorAggregate
{
    use ConfigTrait;

    /**
     * Creates a new configuration repository from an array.
     *
     * @param array<string, mixed> $config The initial configuration values.
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }

    /**
     * {@inheritdoc}
     */
    public function merge(
        ConfigRepositoryInterface $configuration,
        ?string $key = null,
        MergeStrategy|string|int|null $strategy = null
    ): void {
        $config = !is_null($key) ? $this->get($key) : $this->getAll();

        if (!is_array($config)) {
            $config = [];
        }

        if (!$strategy instanceof MergeStrategy) {
            $strategy = MergeStrategy::from($strategy ?? MergeStrategy::REPLACE_INDEXED);
        }

        $mergedStore = $this->mergeArrays($config, $configuration->getAll(), $strategy);

        if (!is_null($key)) {
            $this->set($key, $mergedStore);

            return;
        }

        $this->config = $mergedStore;
    }

    /**
     * Checks whether a given offset exists in the configuration.
     *
     * This method enables the usage of `isset($config[$key])`.
     *
     * @param mixed $offset The configuration key.
     * @return bool True if the key exists, false otherwise.
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * Retrieves a configuration value using array access.
     *
     * This method enables the usage of `$value = $config[$key]`.
     *
     * @param mixed $offset The configuration key.
     * @return mixed The configuration value.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * Sets a configuration value using array access.
     *
     * This method enables the usage of `$config[$key] = $value`.
     *
     * @param mixed $offset The configuration key.
     * @param mixed $value  The value to set.
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set($offset, $value);
    }

    /**
     * Removes a configuration key using array access.
     *
     * This method enables the usage of `unset($config[$key])`.
     *
     * @param mixed $offset The configuration key.
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }

    /**
     * Retrieves an iterator for iterating over the configuration values.
     *
     * This method allows the configuration object to be used in `foreach` loops.
     *
     * @return Traversable An iterator for the configuration values.
     * @throws Exception If an iterator cannot be created.
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->config);
    }

    /**
     * Counts the number of stored configuration items.
     *
     * This method enables the usage of `count($config)`.
     *
     * @return int The number of configuration items.
     */
    public function count(): int
    {
        return count($this->config);
    }
}
