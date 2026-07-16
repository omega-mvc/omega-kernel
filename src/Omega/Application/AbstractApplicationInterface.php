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

namespace Omega\Application;

use Omega\Container\AbstractServiceProvider;

/**
 * Defines the contract for an application instance.
 *
 * The ApplicationInterface represents the core entry point of the framework
 * and coordinates configuration loading, environment detection, service
 * provider registration, bootstrapping, and application lifecycle management.
 *
 * This interface is intentionally decoupled from any specific container
 * implementation or container-related exceptions, allowing developers to
 * provide custom Application implementations or extend the default one
 * without being forced to depend on a particular container behavior.
 *
 * Implementations are expected to manage:
 * - Application configuration and environment
 * - Service provider registration and booting
 * - Application bootstrapping lifecycle
 * - Maintenance and termination handling
 * - Global application state access (singleton-style, if desired)
 *
 * @category  Omega
 * @package   Application
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */
interface AbstractApplicationInterface
{
    /**
     * Get instance Application container.
     *
     * @return Application|null Return instance Application container.
     */
    public static function getInstance(): ?Application;

    /**
     * Bootstrap the application using the given bootstrapper classes.
     *
     * @param array<int, class-string> $bootstrappers List of bootstrapper class names.
     * @return void
     */
    public function bootstrapWith(array $bootstrappers): void;

    /**
     * Boot service provider.
     *
     * @return void
     */
    public function bootProvider(): void;

    /**
     * Call the registered booting callbacks.
     *
     * @param callable[] $bootCallBacks Callbacks executed during the booting phase.
     * @return void
     */
    public function callBootCallbacks(array $bootCallBacks): void;

    /**
     * Register a callback to be executed before the application boot process starts.
     *
     * The given callback will be invoked when the application is about to boot,
     * allowing pre-boot logic to be executed (e.g. preparing state, modifying
     * configuration, or performing early initialization).
     *
     * @param callable $callback A callable that will be executed before the boot
     *                           process begins. The callback may optionally accept
     *                           the Application instance as its first argument.
     *
     * @return void
     */
    public function bootingCallback(callable $callback): void;

    /**
     * Add booted call back, call after boot is called.
     *
     * @param callable $callback Callback executed after the application has booted.
     * @return void
     */
    public function bootedCallback(callable $callback): void;

    /**
     * Flush or reset application (static).
     *
     * @return void
     */
    public function flush(): void;

    /**
     * Register service provider.
     *
     * @param string $provider Class-name service provider
     * @return AbstractServiceProvider The instantiated and registered service provider.
     */
    public function register(string $provider): AbstractServiceProvider;

    /**
     * Registers a callback to be executed when the application terminates.
     *
     * This method allows you to add one or more terminating callbacks that
     * will be called after the application finishes handling a request.
     *
     * @param callable $terminateCallback The callback to execute on termination.
     * @return $this Returns the application instance for method chaining.
     */
    public function registerTerminate(callable $terminateCallback): self;

    /**
     * Terminate the application.
     *
     * @return void
     */
    public function terminate(): void;

    /**
     * Abort application to http exception.
     *
     * @param int                   $code    HTTP status code.
     * @param string                $message Exception message.
     * @param array<string, string> $headers HTTP response headers.
     * @return void
     */
    public function abort(int $code, string $message = '', array $headers = []): void;

    /**
     * Get the list of core providers.
     *
     * @return array Return an array of core providers.
     */
    public function getCoreProviders(): array;

    /**
     * Get the application name.
     *
     * If a name is explicitly provided, it will be returned as-is.
     * Otherwise, the method falls back to the default application name
     * defined by the APP_NAME in .env files.
     *
     * This allows consumers to dynamically override the application name
     * (e.g. during runtime, build, or release phases) without modifying
     * the underlying application state.
     *
     * @return string The resolved application name.
     */
    public function getName(): string;

    /**
     * Get the application version string.
     *
     * If a version string is explicitly provided, it will be returned as-is.
     * Otherwise, the method falls back to the default application version
     * defined by the APP_VERSION in .env file.
     *
     * This allows consumers to dynamically override the application version
     * (e.g. during runtime, build, or release phases) without modifying
     * the underlying application state.
     *
     * @return string The resolved application version.
     */
    public function getVersion(): string;

    /**
     * Get the default application bindings and path definitions.
     *
     * @return array<string, mixed> Key-value pairs defining paths, environment, and core settings.
     */
    public function setDefinitions(): array;

    /**
     * Define and register the configuration directory path for the application.
     *
     * This method is responsible for setting the container binding that represents
     * the base configuration path (typically `path.config`).
     *
     * Concrete application implementations must define where configuration files
     * are located and how the path is resolved.
     *
     * This method is invoked during application initialization and may override
     * the default configuration path resolution.
     *
     * @return void
     */
    public function setConfigPath(): void;

    /**
     * Get application (bootstrapper) cache path.
     *
     * default './boostrap/cache/'.
     *
     * @return string Absolute path to the application bootstrap cache directory.
     */
    public function getApplicationCachePath(): string;

    /**
     * Detect application environment.
     *
     * @return string Current application environment (e.g. "dev", "prod").
     */
    public function getEnvironment(): string;

    /**
     * Detect application debug enable.
     *
     * @return bool True when application debug mode is enabled.
     */
    public function isDebugMode(): bool;

    /**
     * Detect application production mode.
     *
     * @return bool True when the application is running in production environment.
     */
    public function isProduction(): bool;

    /**
     * Detect application development mode.
     *
     * @return bool True when the application is running in development environment.
     */
    public function isDev(): bool;
}
