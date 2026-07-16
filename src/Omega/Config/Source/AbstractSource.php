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

namespace Omega\Config\Source;

use Omega\Config\Exceptions\FileReadException;

use function file_get_contents;
use function sprintf;

/**
 * Base class for file-based configuration sources.
 *
 * This abstract class provides a foundation for configuration sources that load data
 * from files, such as JSON or XML. It includes logic to ensure the file can be read
 * correctly.
 *
 * @category   Omega
 * @package    Config
 * @subpackage Source
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    2.0.0
 */
abstract class AbstractSource implements SourceInterface
{
    /**
     * Creates a new file-based configuration source.
     *
     * @param string $file The file path to load configuration from.
     * @return void
     */
    public function __construct(private readonly string $file)
    {
    }

    /**
     * Reads the content of the configuration file.
     *
     * @return string The file content as a string.
     * @throws FileReadException If the file cannot be read.
     */
    protected function fetchContent(): string
    {
        // phpcs:ignore WordPress
        $fileContent = @file_get_contents($this->file);

        if (false === $fileContent) {
            throw new FileReadException(
                sprintf(
                    "Failed to read configuration file: %s",
                    $this->file
                )
            );
        }

        return $fileContent;
    }
}
