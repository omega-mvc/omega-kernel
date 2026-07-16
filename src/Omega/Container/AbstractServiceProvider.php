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

namespace Omega\Container;

use Omega\Application\Application;

/**
 * Abstract base class for service providers.
 *
 * Service providers are responsible for registering services and booting logic
 * into the application container.
 *
 * @category   Omega
 * @package    Container
 * @subpackage Provider
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    2.0.0
 */
abstract class AbstractServiceProvider
{
    use AppServiceProviderTrait;

    /** @var array<int|string, class-string> Classes to register in the container */
    protected array $register = [];

    /**
     * Create a new service provider instance.
     *
     * @param Application $app The application instance
     */
    public function __construct(protected Application $app)
    {
    }

    /**
     * Boot the service provider.
     *
     * This method is called after all providers are registered.
     */
    public function boot(): void
    {
    }

    /**
     * Register services into the application container.
     *
     * This method should be called before boot.
     */
    public function register(): void
    {
    }
}
