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

use function array_key_exists;
use function count;
use function explode;
use function is_array;
use function reset;

/**
 * Abstract implementation of the configuration repository.
 *
 * This class provides a base implementation for managing configuration settings,
 * handling operations such as retrieval, modification, and merging. Concrete
 * implementations should extend this class to provide specific behavior.
 *
 * @category  Omega
 * @package   Config
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */
abstract class AbstractConfigRepository implements ConfigRepositoryInterface
{
    use ConfigTrait;

    /** @var array The store holding all configuration variables. */
    protected array $config = [];

    /**
     * Create new config using array.
     *
     * @param array<string, mixed> $config The store holding all configuration variables.
     * @return void
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function getAll(): array
    {
        return $this->config;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        $config = $this->config;

        foreach (explode('.', $key) as $section) {
            if (!is_array($config) || !array_key_exists($section, $config)) {
                return false;
            }

            $config = $config[$section];
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $config = $this->config;

        foreach (explode('.', $key) as $section) {
            if (!is_array($config) || ! array_key_exists($section, $config)) {
                return $default;
            }

            $config = $config[$section];
        }

        return $config;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value): void
    {
        $config = &$this->config;

        foreach (explode('.', $key) as $section) {
            if (!$this->isAssociative($config)) {
                $config = [];
            }

            $config = &$config[$section];
        }

        $config = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $key): void
    {
        $config        = &$this->config;
        $sections      = explode('.', $key);
        $section_count = count($sections);
        $section       = reset($sections);

        for ($i = 0; $i < $section_count; $section = $sections[++$i]) {
            if (!array_key_exists($section, $config)) {
                break;
            }

            if (count($sections) === $i + 1) {
                unset($config[$section]);

                break;
            }

            $config = &$config[$section];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->config = [];
    }

    /**
     * {@inheritdoc}
     */
    public function push(string $key, mixed $value): void
    {
        if (!isset($this->config[$key]) || !is_array($this->config[$key])) {
            $this->config[$key] = [];
        }

        $this->config[$key][] = $value;
    }
}
