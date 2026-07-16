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

namespace Omega\Config\Bootstrapper;

use Omega\Application\Application;
use Omega\Config\ConfigRepository;
use Omega\Container\Exceptions\BindingResolutionException;
use Omega\Container\Exceptions\CircularAliasException;
use Omega\Container\Exceptions\EntryNotFoundException;
use Psr\Container\ContainerExceptionInterface;
use ReflectionException;
use RuntimeException;

use function array_map;
use function array_replace_recursive;
use function date_default_timezone_set;
use function file_exists;
use function gettype;
use function glob;
use function is_array;

use function Omega\Environment\env;
use function Omega\Application\get_path;

/**
 * ConfigProviders is responsible for loading and bootstrapping the application's configuration.
 *
 * It supports both cached configuration (from `config.php` in the application cache)
 * and dynamic configuration loading from PHP files in the configured config directory.
 * After loading, it initializes the application's configuration repository and sets
 * the default timezone.
 *
 * @category  Omega
 * @package   Config
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */
class ConfigBootstrapper
{
    /**
     * Bootstrap the configuration subsystem for the given application instance.
     *
     * This method initializes the application's configuration repository by
     * resolving the full configuration array and binding it to the container.
     *
     * The configuration is resolved through the internal loading strategy:
     * - If a cached configuration file exists, it is loaded and used directly.
     * - Otherwise, all configuration files located in the configured
     *   `path.config` directory are loaded and merged.
     *
     * Once the configuration is loaded, it is injected into the application
     * through a ConfigRepository instance. Finally, the default PHP timezone
     * is set using the `APP_TIMEZONE` environment variable, falling back to
     * `UTC` when no value is defined.
     *
     * @param Application $app The application instance that will receive the
     *                         initialized configuration repository.
     * @return void
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     * @throws RuntimeException If a cached config file or a regular config file does not return an array
     */
    public function bootstrap(Application $app): void
    {
        $config = $this->loadConfiguration($app);

        $app->set('config', fn () => new ConfigRepository($config));

        date_default_timezone_set(env('APP_TIMEZONE') ?? 'UTC');
    }

    /**
     * Resolve the full configuration array for the application.
     *
     * This method determines the configuration loading strategy. It first
     * attempts to load a cached configuration file, which represents a
     * precompiled configuration snapshot intended to improve bootstrap
     * performance.
     *
     * If no valid cached configuration is available, the method falls back
     * to loading individual configuration files from the configured
     * configuration directory.
     *
     * @param Application $app The application instance used to resolve the
     *                         cache location and configuration paths.
     * @return array The fully resolved configuration array that will be
     *               injected into the application's configuration repository.
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     * @throws RuntimeException If a cached config file or a regular config file does not return an array
     */
    private function loadConfiguration(Application $app): array
    {
        if ($cache = $this->loadCachedConfig($app)) {
            return $cache;
        }

        return $this->loadConfigFiles(get_path('path.config'));
    }

    /**
     * Attempt to load the cached configuration file for the application.
     *
     * This method checks whether a compiled configuration cache file exists
     * within the application's cache directory. If present, the file is
     * required and validated to ensure it returns a valid configuration
     * array.
     *
     * When the cache file is missing, the method returns null, signaling
     * that the configuration should be loaded dynamically from individual
     * configuration files.
     *
     * @param Application $app The application instance used to determine the
     *                         location of the configuration cache file.
     * @return array|null The cached configuration array when available,
     *                    or null when the cache file does not exist.
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     * @throws RuntimeException If a cached config file or a regular config file does not return an array
     */
    private function loadCachedConfig(Application $app): ?array
    {
        $file = $app->getApplicationCachePath() . 'config.php';

        if (!file_exists($file)) {
            return null;
        }

        $cached = require $file;

        if (!is_array($cached)) {
            throw new RuntimeException(
                "Invalid config cache file: expected array, got " . gettype($cached)
            );
        }

        return $cached;
    }

    /**
     * Load configuration data from all PHP files within the given directory.
     *
     * This method scans the provided configuration directory for PHP files
     * and requires each one to retrieve its configuration array. All loaded
     * configuration arrays are then merged into a single configuration
     * structure using `array_replace_recursive`, allowing nested values
     * to be combined correctly.
     *
     * Each configuration file is validated through the internal
     * `requireConfig()` method to ensure it returns a valid array.
     *
     * @param string $path The absolute path to the directory containing
     *                     configuration PHP files.
     * @return array The merged configuration array constructed from all
     *               discovered configuration files.
     */
    private function loadConfigFiles(string $path): array
    {
        $files = glob($path . '*.php') ?: [];

        if ($files === []) {
            return [];
        }

        return array_replace_recursive(
            ...array_map([$this, 'requireConfig'], $files)
        );
    }

    /**
     * Require and validate a configuration file.
     *
     * This method loads the specified configuration file and ensures that
     * it returns a valid configuration array. The file is required directly,
     * and its return value is validated to guarantee that the configuration
     * system receives a properly structured array.
     *
     * This method acts as a safeguard against malformed configuration files
     * that may return invalid data types.
     *
     * @param string $file The absolute path to the configuration file to load.
     * @return array The configuration array returned by the required file.
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     * @throws RuntimeException If a cached config file or a regular config file does not return an array
     */
    private function requireConfig(string $file): array
    {
        $config = require $file;

        if (!is_array($config)) {
            throw new RuntimeException(
                "Invalid config file [$file]: expected array, got " . gettype($config)
            );
        }

        return $config;
    }
}
