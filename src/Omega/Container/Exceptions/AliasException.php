<?php

/**
 * Part of Omega - Container Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */

declare(strict_types=1);

namespace Omega\Container\Exceptions;

use function sprintf;

/**
 * Exception thrown when an alias maps to itself.
 *
 * @category   Omega
 * @package    Container
 * @subpackage Exceptions
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    2.0.0
 */
class AliasException extends BindingResolutionException
{
    /**
     * Constructor.
     *
     * @param string $abstract The abstract class or identifier that is aliased to itself.
     * @return void
     */
    public function __construct(string $abstract)
    {
        parent::__construct(sprintf("%s is aliased to itself.", $abstract));
    }
}
