<?php

/**
 * Part of Omega - Application Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */

declare(strict_types=1);

namespace Omega\Application\Bootstrapper;

use Omega\Application\Application;
use Omega\Container\Exceptions\BindingResolutionException;
use Omega\Container\Exceptions\CircularAliasException;
use Omega\Container\Exceptions\EntryNotFoundException;
use Omega\Application\ApplicationManifest;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;

/**
 * Handles the registration phase of the application’s service providers.
 *
 * This bootstrapper is responsible for invoking the provider registration
 * mechanism on the Application instance, ensuring that all providers defined
 * by the framework or the user are properly loaded before the application
 * lifecycle proceeds.
 *
 * It acts as an initialization step during the bootstrapping sequence,
 * preparing the container and related bindings required by the framework
 * to operate correctly.
 *
 * @category   Omega
 * @package    Application
 * @subpackage Bootstrapper
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    2.0.0
 */
class RegisterProviders
{
    /**
     * @param Application $app
     * @return void
     * @throws BindingResolutionException
     * @throws CircularAliasException
     * @throws ContainerExceptionInterface
     * @throws EntryNotFoundException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function bootstrap(Application $app): void
    {
        foreach ($this->resolveProviders($app) as $provider) {
            $app->register($provider);
        }
    }

    /**
     * @param Application $app
     * @return array
     * @throws BindingResolutionException
     * @throws CircularAliasException
     * @throws ContainerExceptionInterface
     * @throws EntryNotFoundException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    private function resolveProviders(Application $app): array
    {
        $configProviders = [];

        if ($app->has('config')) {
            $configProviders = $app->get('config')
                ->get('app.providers', []);
        }

        $packageProviders = $app
            ->make(ApplicationManifest::class)
            ->providers() ?? [];

        return array_unique([
            ...$app->getCoreProviders(),
            ...$configProviders,
            ...$packageProviders,
        ]);
    }
}
