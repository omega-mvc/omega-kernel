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
 * Defines merge strategies for configuration data.
 *
 * This enum-like class extends `AbstractEnum` and provides constants that determine
 * how conflicting indexed arrays should be merged within the configuration system.
 * Available strategies:
 *
 * - `REPLACE_INDEXED`: Replaces the existing indexed array with the new one.
 *
 * - `MERGE_INDEXED`: Merges the new indexed array into the existing one.
 * - `MERGE_ADD_NEW`: Merges the new indexed array but only adds new elements, preserving existing ones.
 *
 * @category  Omega
 * @package   Config
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */

enum MergeStrategy: int
{
    case REPLACE_INDEXED = 0;
    case MERGE_INDEXED = 1;
    case MERGE_ADD_NEW = 2;
}
