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

use Exception;
use Omega\Config\ConfigRepository;
use Omega\Container\AbstractServiceProvider;
use Omega\Container\Container;
use Omega\Container\Exceptions\BindingResolutionException;
use Omega\Container\Exceptions\CircularAliasException;
use Omega\Container\Exceptions\EntryNotFoundException;
use Omega\Http\Exceptions\HttpException;
use Omega\Http\Request;
use Omega\View\Templator;
use Omega\View\Vite;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;

use function array_filter;
use function array_walk;
use function assert;
use function count;
use function in_array;
use function Omega\Environment\env;
use function str_replace;

use const DIRECTORY_SEPARATOR;

/**
 * Core application container.
 *
 * Manages configuration, service providers, bootstrapping,
 *
 * @category  Omega
 * @package   Application
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */
abstract class AbstractApplication extends Container implements AbstractApplicationInterface
{
    /** @var Application|null Currently active Application runtime instance. */
    protected static ?Application $app = null;

    /** @var string Absolute base path of the application root directory. */
    protected string $basePath;

    /** @var AbstractServiceProvider[] Service providers that have completed the boot phase. */
    protected array $bootedProviders = [];

    /** @var AbstractServiceProvider[] Service providers that have been registered. */
    protected array $loadedProviders = [];

    /** @var bool Indicates whether the application has completed the boot phase. */
    public bool $isBooted = false { // phpcs:ignore
        get {
            return $this->isBooted; // phpcs:ignore
        }
    }

    /** @var bool Indicates whether the application bootstrap process has completed. */
    private bool $isBootstrapped = false;

    /** Indicates whether the application has finished bootstrapping. */
    public bool $bootstrapped { // phpcs:ignore
        get => $this->isBootstrapped; // phpcs:ignore
    }

    /** @var callable[] Callbacks executed when the application is terminating. */
    private array $terminateCallback = [];

    /** @var callable[] Callbacks executed before service providers are booted. */
    protected array $bootingCallbacks = [];

    /** @var callable[] Callbacks executed after all service providers are booted. */
    protected array $bootedCallbacks = [];

    /** @var array<int, class-string<AbstractServiceProvider>> Registered service provider class names. */
    protected array $providers = [];

    /**
     * Application constructor.
     *
     * @param string|null $basePath Base application path.
     * @return void
     * @throws BindingResolutionException
     * @throws CircularAliasException
     * @throws Exception
     */
    public function __construct(?string $basePath = null)
    {
        $this->basePath = str_replace('/', DIRECTORY_SEPARATOR, $basePath);

        $this->set('path.base', $this->basePath . DIRECTORY_SEPARATOR);

        $this->setConfigPath();

        $this->setBaseBinding();

        $this->registerAlias();

        $definitions = $this->setDefinitions();

        array_walk(
            $definitions,
            fn ($value, $key) => $this->set($key, $value)
        );
    }

    /**
     * Get instance Application container.
     *
     * @return Application|null Return instance Application container.
     */
    public static function getInstance(): ?Application
    {
        return Application::$app;
    }

    /**
     * Register the base application bindings and finalize application identity.
     *
     * This method is the **single point of truth** where the Application instance
     * becomes globally available and fully integrated with the container.
     *
     * Responsibilities:
     * - Defines this instance as the active Application runtime.
     * - Registers core container bindings (app, Application::class, Container::class).
     * - Initializes framework-level services that depend on a stable Application instance.
     *
     * Contract:
     * - MUST be called exactly once during application construction.
     * - MUST be executed before any service providers, helpers, or container lookups
     *   that rely on the Application instance.
     * - After this method executes, the Application instance is considered
     *   globally addressable and safe to use.
     *
     * Rationale:
     * The framework relies on a globally accessible Application reference for
     * container resolution, helpers, service providers, and testing utilities.
     * Assigning the instance here guarantees a deterministic initialization order.
     *
     * @return void
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     */
    protected function setBaseBinding(): void
    {
        assert(
            Application::$app === null,
            'Application::$app must be null before base bindings are registered.'
        );

        // The Application instance must be globally available before any container
        // bindings, helpers, or service providers are resolved.
        Application::$app = $this;

        $this->set('app', $this);
        $this->set(Application::class, $this);
        $this->set(Container::class, $this);

        $this->set(
            ApplicationManifest::class,
            fn () => new ApplicationManifest(
                $this->basePath,
                $this->getApplicationCachePath()
            )
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function bootstrapWith(array $bootstrappers): void
    {
        $this->isBootstrapped = true;

        array_walk($bootstrappers, fn($b) => $this->make($b)->bootstrap($this));
    }

    /**
     * {@inheritdoc}
     *
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function bootProvider(): void
    {
        if ($this->isBooted) {
            return;
        }

        $this->callBootCallbacks($this->bootingCallbacks);

        $providers = array_filter(
            $this->getCoreProviders(),
            fn ($provider) => ! in_array($provider, $this->bootedProviders, true)
        );

        array_walk($providers, function ($provider) {
            $this->call([$provider, 'boot']);
            $this->bootedProviders[] = $provider;
        });

        $this->callBootCallbacks($this->bootedCallbacks);

        $this->isBooted = true;
    }

    /**
     * {@inheritdoc}
     *
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function callBootCallbacks(array $bootCallBacks): void
    {
        $index = 0;

        while ($index < count($bootCallBacks)) {
            $this->call($bootCallBacks[$index]);

            $index++;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function bootingCallback(callable $callback): void
    {
        $this->bootingCallbacks[] = $callback;
    }

    /**
     * {@inheritdoc}
     *
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function bootedCallback(callable $callback): void
    {
        $this->bootedCallbacks[] = $callback;

        if ($this->isBooted) {
            $this->call($callback);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): void
    {
        Application::$app = null;

        $this->providers         = [];
        $this->loadedProviders   = [];
        $this->bootedProviders   = [];
        $this->terminateCallback = [];
        $this->bootingCallbacks  = [];
        $this->bootedCallbacks   = [];

        parent::flush();
    }

    /**
     * {@inheritdoc}
     */
    public function register(string $provider): AbstractServiceProvider
    {
        if (in_array($provider, $this->loadedProviders, true)) {
            return new $provider($this);
        }

        $instance = new $provider($this);

        $instance->register();

        $this->loadedProviders[] = $provider;

        if ($this->isBooted) {
            $instance->boot();
            $this->bootedProviders[] = $provider;
        }

        return $instance;
    }

    /**
     * Registers a callback to be executed when the application terminates.
     *
     * This method allows you to add one or more terminating callbacks that
     * will be called after the application finishes handling a request.
     *
     * @param callable $terminateCallback The callback to execute on termination.
     * @return $this Returns the application instance for method chaining.
     */
    public function registerTerminate(callable $terminateCallback): self
    {
        $this->terminateCallback[] = $terminateCallback;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function terminate(): void
    {
        $index = 0;

        while ($index < count($this->terminateCallback)) {
            $this->call($this->terminateCallback[$index]);

            $index++;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function abort(int $code, string $message = '', array $headers = []): void
    {
        throw new HttpException($code, $message, null, $headers);
    }

    /**
     * Register aliases to container.
     *
     * @return void
     * @throws Exception Thrown when alias registration fails.
     */
    protected function registerAlias(): void
    {
        $aliases = [
            'request'       => [Request::class],
            'view.instance' => [Templator::class],
            'vite.gets'     => [Vite::class],
            'config'        => [ConfigRepository::class],
        ];

        array_walk(
            $aliases,
            function (array $list, string $abstract): void {
                array_walk(
                    $list,
                    fn (string $alias) => $this->alias($abstract, $alias)
                );
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getCoreProviders(): array
    {
        return $this->providers;
    }

    /**
     * {@inheritdoc}
     *
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function getName(): string
    {
        return $this->get('app.name');
    }

    /**
     * {@inheritdoc}
     *
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function getVersion(): string
    {
        return $this->get('app.version');
    }

    /**
     * {@inheritdoc}
     */
    public function setDefinitions(): array
    {
        return [
            'boot.cache'              => $this->basePath . set_path('bootstrap.cache'),
            'path.app'                => $this->basePath . set_path('app'),
            'path.cache'              => $this->basePath . set_path('storage.app.cache'),
            'path.command'            => $this->basePath . set_path('app.Console.Commands'),
            'path.component'          => $this->basePath . set_path('resources.components'),
            'path.controller'         => $this->basePath . set_path('app.Http.Controllers'),
            'path.exception'          => $this->basePath . set_path('app.Exceptions'),
            'path.model'              => $this->basePath . set_path('app.Models'),
            'path.middleware'         => $this->basePath . set_path('app.Middlewares'),
            'path.provider'           => $this->basePath . set_path('app.Providers'),
            'path.view'               => $this->basePath . set_path('resources.views'),
            'path.storage'            => $this->basePath . set_path('storage'),
            'path.logs'               => $this->basePath . set_path('storage.logs'),
            'path.public'             => $this->basePath . set_path('public'),
            'path.migration'          => $this->basePath . set_path('database.migration'),
            'path.seeder'             => $this->basePath . set_path('database.seeders'),
            'path.compiled_view_path' => $this->basePath . set_path('storage.app.view'),
            'path.database'           => $this->basePath . set_path('database'),
            'paths.view'              => array_map(
                fn ($p) => $this->basePath . $p,
                [set_path('resources.views')]
            ),
            'environment'             => env('APP_ENV'),
            'app.debug'               => env('APP_DEBUG'),
            'app.name'                => env('APP_NAME'),
            'app.version'             => env('APP_VERSION')
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     */
    public function setConfigPath(): void
    {
        $this->set('path.config', $this->basePath . set_path('config'));
    }

    /**
     * {@inheritdoc}
     *
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function getApplicationCachePath(): string
    {
        $base = rtrim(get_path('path.base'), "/\\");

        return $base . set_path('bootstrap.cache');
    }

    /**
     * {@inheritdoc}
     *
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function getEnvironment(): string
    {
        return $this->get('environment');
    }

    /**
     * {@inheritdoc}
     *
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function isDebugMode(): bool
    {
        return $this->get('app.debug');
    }

    /**
     * {@inheritdoc}
     *
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function isProduction(): bool
    {
        return $this->getEnvironment() === 'prod';
    }

    /**
     * {@inheritdoc}
     *
     * @throws BindingResolutionException Thrown when resolving a binding fails.
     * @throws CircularAliasException Thrown when alias resolution loops recursively.
     * @throws ContainerExceptionInterface Thrown on general container errors, e.g., service not retrievable.
     * @throws EntryNotFoundException Thrown when no entry exists for the identifier.
     * @throws ReflectionException Thrown when the requested class or interface cannot be reflected.
     */
    public function isDev(): bool
    {
        return $this->getEnvironment() === 'dev';
    }
}
