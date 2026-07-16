<?php

/**
 * Part of Omega - Config Package.
 * php version 8.3
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */

declare(strict_types=1);

namespace Omega\Config\Exceptions;

use RuntimeException;

/**
 * Exception thrown when an XML configuration file is malformed.
 *
 * This exception is triggered when the XML content contains syntax errors or
 * structural inconsistencies that prevent correct parsing.
 *
 *
 * @category   Omega
 * @package    Config
 * @subpackage Excptions
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    2.0.0
 */
class MalformedXmlException extends RuntimeException implements ConfigExceptionInterface
{
}
