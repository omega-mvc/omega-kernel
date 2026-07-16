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

use Omega\Config\ConfigTrait;
use Omega\Config\Exceptions\InvalidArrayConfigException;

/**
 * Configuration source that loads data from an array.
 *
 * This implementation allows using a predefined associative array as a
 * configuration source. It ensures the array is properly structured before
 * returning it.
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
readonly class ArrayConfig implements SourceInterface
{
    use ConfigTrait;

    /**
     * Creates a new configuration source instance.
     *
     * @param array $content The configuration source content.
     * @return void
     */
    public function __construct(private array $content)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArrayCOnfigException if file is empty or not an associative array.
     */
    public function fetch(): array
    {
        if (empty($this->content)) {
            throw new InvalidArrayConfigException("Configuration array cannot be empty.");
        }

        if (!$this->isAssociative($this->content)) {
            throw new InvalidArrayConfigException("Configuration must be an associative array.");
        }

        return $this->content;
    }
}
