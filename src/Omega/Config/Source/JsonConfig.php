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

use JsonException;
use Omega\Config\Exceptions\MalformedJsonException;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Configuration source that loads data from a JSON file.
 *
 * This implementation reads a JSON configuration file, decodes its content, and
 * returns it as an associative array. It ensures the file is readable and properly
 * formatted.
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
class JsonConfig extends AbstractSource
{
    /**
     * {@inhertdoc}
     *
     * @throws MalformedJsonException If unable to produce the content.
     */
    public function fetch(): array
    {
        try {
            return (array)json_decode($this->fetchContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new MalformedJsonException('Invalid JSON format in configuration file.');
        }
    }
}
