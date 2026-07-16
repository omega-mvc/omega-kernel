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

use Closure;
use Omega\Config\Source\SourceInterface;

use function array_reduce;
use function is_null;

/**
 * Constructs a configuration repository from multiple sources.
 *
 * This class enables the aggregation of different configuration sources, allowing
 * for prioritization and merging strategies. It provides a fluent interface for
 * assembling configurations dynamically.
 *
 * @category  Omega
 * @package   Config
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */
class ConfigBuilder
{
    use ConfigTrait;

    /**
     * List of configuration sources to be used for building the configuration.
     * Each source is stored as an array containing:
     *
     * - The `SourceInterface` instance
     * - An optional section name
     * - A priority value
     *
     * @var array
     */
    private array $sources = [];

    /** @var int Default priority value for configuration sources. */
    private int $priority = 0;

    /**
     * Adds a configuration source to the builder.
     *
     * Each source can be assigned to a specific section and given a priority.
     * Higher priority values ensure that the source is processed earlier.
     *
     * @param SourceInterface $source   The configuration source instance.
     * @param string|null     $section  (Optional) Section to group the source under.
     * @param int             $priority (Optional) The priority of the source (higher means processed first).
     * @return ConfigBuilder The same instance for method chaining.
     */
    public function addConfiguration(SourceInterface $source, ?string $section = null, int $priority = 0): self
    {
        $this->sources[] = [$source, $section, $priority];

        return $this;
    }

    /**
     * Builds a configuration repository from the available sources.
     *
     * The sources are sorted by priority before merging. The merge strategy
     * defines how conflicting values between sources are handled.
     *
     * @param MergeStrategy|null $strategy The merge strategy to apply (default: REPLACE_INDEXED).
     * @return ConfigRepositoryInterface The constructed configuration repository.
     */
    public function build(?MergeStrategy $strategy = null): ConfigRepositoryInterface
    {
        if (is_null($strategy)) {
            $strategy = MergeStrategy::REPLACE_INDEXED;
        }

        // Sort sources by priority (descending order)
        usort($this->sources, fn($a, $b) => $b[2] <=> $a[2]);

        // Reduce the sources into a single configuration object
        return array_reduce(
            $this->sources,
            $this->createConfiguration($strategy),
            new ConfigRepository()
        );
    }

    /**
     * Creates an accumulator function for merging configuration sources.
     *
     * The returned closure takes a `ConfigRepositoryInterface` instance and a
     * configuration source, then merges the source data into the repository.
     *
     * @param MergeStrategy $strategy The merge strategy to apply.
     * @return Closure A closure that merges a configuration source into the repository.
     */
    private function createConfiguration(MergeStrategy $strategy): Closure
    {
        return function (ConfigRepositoryInterface $configuration, array $configurationSource) use ($strategy) {
            list($source, $section) = $configurationSource;

            $configuration->merge(new ConfigRepository($source->fetch()), $section, $strategy);

            return $configuration;
        };
    }
}
