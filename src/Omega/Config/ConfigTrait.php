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

use function array_filter;
use function array_key_exists;
use function array_keys;
use function count;
use function is_array;

/**
 * Provides configuration merging functionalities.
 *
 * This trait offers utility methods for merging configurations using
 * different strategies and detecting associative arrays.
 *
 * @category  Omega
 * @package   Config
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */
trait ConfigTrait
{
    /**
     * Merges two given arrays according to the selected merge strategy.
     *
     * - `REPLACE_INDEXED`: Replaces indexed arrays completely.
     * - `MERGE_INDEXED`: Merges indexed arrays and removes duplicates.
     * - `MERGE_ADD_NEW`: Adds new elements without modifying existing ones.
     *
     * @param array         $a        The first array.
     * @param array         $b        The second array.
     * @param MergeStrategy $strategy The merge strategy to apply.
     * @return array The result of merging the two arrays.
     */
    protected function mergeArrays(array $a, array $b, MergeStrategy $strategy): array
    {
        foreach ($b as $key => $value) {
            // If the key doesn't exist in the first array, add it
            if (!array_key_exists($key, $a)) {
                $a[$key] = $value;
                continue;
            }

            // If both values are associative arrays, recursively merge them
            if ($this->isAssociative($a[$key]) && $this->isAssociative($value)) {
                $a[$key] = $this->mergeArrays($a[$key], $value, $strategy);
                continue;
            }

            // If the strategy is MERGE_INDEXED and both values are arrays, merge them with unique values
            if (
                $strategy === MergeStrategy::MERGE_INDEXED &&
                is_array($a[$key]) &&
                is_array($value)
            ) {
                $a[$key] = array_values(array_unique(array_merge($a[$key], $value)));
                continue;
            }

            // If the strategy is MERGE_ADD_NEW, add new keys from $b that don't exist in $a
            if ($strategy === MergeStrategy::MERGE_ADD_NEW) {
                foreach ($b as $k => $v) {
                    if (!array_key_exists($k, $a)) {
                        $a[$k] = $v;
                    }
                }
            }

            // Default behavior: replace the value in the first array with the value from the second array
            $a[$key] = $value;
        }

        return $a;
    }

    /**
     * Determines whether the given value is an associative array.
     *
     * An associative array has at least one string key.
     *
     * @param mixed $value The value to check.
     * @return bool True if the value is an associative array, false otherwise.
     */
    protected function isAssociative(mixed $value): bool
    {
        return is_array($value) && count(array_filter(array_keys($value), 'is_string')) > 0;
    }
}
