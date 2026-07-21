<?php

declare(strict_types=1);

/**
 *
 * @file Application.php
 * @author Gaspard Kirira
 *
 * Copyright 2026, Gaspard Kirira.
 * All rights reserved.
 * https://github.com/iviphp/framework
 *
 * Use of this source code is governed by an MIT license
 * that can be found in the LICENSE file.
 *
 * IviPHP
 *
 */

namespace Ivi\Framework;

use Ivi\Config\ConfigRepository;
use Ivi\Container\Container;
use Ivi\Framework\Bootstrap\Bootstrapper;
use Ivi\Framework\Contracts\ApplicationInterface;
use Ivi\Framework\Contracts\ServiceProviderInterface;
use Ivi\Framework\Exceptions\FrameworkException;
use Throwable;

/**
 * @class Application
 *
 * @brief Represents the central IviPHP application.
 *
 * Application coordinates the service container, configuration,
 * environment, bootstrap operations and service-provider lifecycle.
 *
 * @since 0.1.0
 */
final class Application implements ApplicationInterface
{
    /**
     * Normalized application base path.
     */
    private string $basePath;

    /**
     * Current application environment.
     */
    private string $environment;

    /**
     * Application service container.
     */
    private Container $container;

    /**
     * Application configuration service.
     */
    private ConfigRepository $config;

    /**
     * Application bootstrap coordinator.
     */
    private Bootstrapper $bootstrapper;

    /**
     * Registered service providers indexed by class name.
     *
     * @var array<class-string<ServiceProviderInterface>, ServiceProviderInterface>
     */
    private array $providers = [];

    /**
     * Service-provider registration order.
     *
     * @var array<int, class-string<ServiceProviderInterface>>
     */
    private array $providerOrder = [];

    /**
     * Whether provider booting is currently active.
     */
    private bool $booting = false;

    /**
     * Whether all registered providers have completed booting.
     */
    private bool $booted = false;

    /**
     * @param string            $basePath
     * @param Container|null    $container
     * @param ConfigRepository|null       $config
     * @param string            $environment
     * @param Bootstrapper|null $bootstrapper
     */
    public function __construct(
        string $basePath,
        ?Container $container = null,
        ?ConfigRepository $config = null,
        string $environment = 'production',
        ?Bootstrapper $bootstrapper = null
    ) {
        $this->basePath = self::normalizeBasePath(
            $basePath
        );

        $this->environment = self::normalizeEnvironment(
            $environment
        );

        $this->container = $container
            ?? new Container();

        $this->config = $config
            ?? new ConfigRepository();

        $this->bootstrapper = $bootstrapper
            ?? new Bootstrapper();

        $this->registerCoreServices();
    }

    /**
     * @brief Return the application base path.
     *
     * @return string
     */
    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * @brief Resolve a path relative to the application base directory.
     *
     * @param string $path
     *
     * @return string
     */
    public function path(string $path = ''): string
    {
        $path = trim($path);

        if ($path === '') {
            return $this->basePath;
        }

        if (
            str_contains($path, "\0")
            || str_contains($path, "\r")
            || str_contains($path, "\n")
        ) {
            throw FrameworkException::invalidPath(
                $path,
                'The path contains invalid characters.'
            );
        }

        $normalized = str_replace(
            '\\',
            '/',
            $path
        );

        if (
            str_starts_with($normalized, '/')
            || preg_match(
                '/^[A-Za-z]:\//',
                $normalized
            ) === 1
        ) {
            throw FrameworkException::invalidPath(
                $path,
                'The path must be relative to the application base directory.'
            );
        }

        $segments = [];

        foreach (
            explode('/', $normalized)
            as $segment
        ) {
            if (
                $segment === ''
                || $segment === '.'
            ) {
                continue;
            }

            if ($segment === '..') {
                throw FrameworkException::invalidPath(
                    $path,
                    'The path cannot escape the application base directory.'
                );
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            return $this->basePath;
        }

        return $this->basePath
            . DIRECTORY_SEPARATOR
            . implode(
                DIRECTORY_SEPARATOR,
                $segments
            );
    }

    /**
     * @brief Return the current application environment.
     *
     * @return string
     */
    public function environment(): string
    {
        return $this->environment;
    }

    /**
     * @brief Change the application environment.
     *
     * The environment cannot be changed after bootstrapping starts.
     *
     * @param string $environment
     *
     * @return self
     */
    public function setEnvironment(
        string $environment
    ): self {
        if (
            $this->bootstrapper->isBootstrapping()
            || $this->isBootstrapped()
            || $this->booting
            || $this->booted
        ) {
            throw FrameworkException::invalidLifecycle(
                'application.environment',
                'The environment cannot be changed after bootstrapping starts.'
            );
        }

        $this->environment = self::normalizeEnvironment(
            $environment
        );

        return $this;
    }

    /**
     * @brief Determine whether the application uses one of the environments.
     *
     * @param string ...$environments
     *
     * @return bool
     */
    public function isEnvironment(
        string ...$environments
    ): bool {
        foreach ($environments as $environment) {
            if (
                $this->environment
                === self::normalizeEnvironment($environment)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @brief Return the application service container.
     *
     * @return Container
     */
    public function container(): Container
    {
        return $this->container;
    }

    /**
     * @brief Return the application configuration service.
     *
     * @return ConfigRepository
     */
    public function config(): ConfigRepository
    {
        return $this->config;
    }

    /**
     * @brief Return the application bootstrap coordinator.
     *
     * @return Bootstrapper
     */
    public function bootstrapper(): Bootstrapper
    {
        return $this->bootstrapper;
    }

    /**
     * @brief Register an application bootstrap operation.
     *
     * @param string                               $name
     * @param callable(ApplicationInterface): void $callback
     * @param bool                                 $replace
     *
     * @return self
     */
    public function addBootstrapper(
        string $name,
        callable $callback,
        bool $replace = false
    ): self {
        $this->bootstrapper->add(
            $name,
            $callback,
            $replace
        );

        return $this;
    }

    /**
     * @brief Resolve a service from the application container.
     *
     * @param string $id
     *
     * @return mixed
     */
    public function make(string $id): mixed
    {
        $id = self::normalizeServiceId($id);

        try {
            return $this->container->make($id);
        } catch (Throwable $exception) {
            throw FrameworkException::serviceResolutionFailed(
                $id,
                $exception
            );
        }
    }

    /**
     * @brief Determine whether a service exists in the container.
     *
     * @param string $id
     *
     * @return bool
     */
    public function has(string $id): bool
    {
        $id = self::normalizeServiceId($id);

        return $this->container->has($id);
    }

    /**
     * @brief Register an application service provider.
     *
     * @param ServiceProviderInterface|class-string<ServiceProviderInterface> $provider
     *
     * @return ServiceProviderInterface
     */
    public function register(
        ServiceProviderInterface|string $provider
    ): ServiceProviderInterface {
        if ($this->booting || $this->booted) {
            throw FrameworkException::invalidLifecycle(
                'application.register',
                'Service providers cannot be registered after provider booting starts.'
            );
        }

        $instance = is_string($provider)
            ? $this->createProvider($provider)
            : $provider;

        if ($instance->application() !== $this) {
            throw FrameworkException::invalidProvider(
                $instance::class,
                'The provider belongs to another application instance.'
            );
        }

        $class = $instance::class;

        if (isset($this->providers[$class])) {
            throw FrameworkException::duplicateProvider(
                $class
            );
        }

        try {
            $instance->register();
        } catch (FrameworkException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw FrameworkException::providerRegistrationFailed(
                $class,
                $exception
            );
        }

        $this->providers[$class] = $instance;
        $this->providerOrder[] = $class;

        return $instance;
    }

    /**
     * @brief Register multiple application service providers.
     *
     * @param iterable<ServiceProviderInterface|class-string<ServiceProviderInterface>> $providers
     *
     * @return self
     */
    public function registerMany(
        iterable $providers
    ): self {
        foreach ($providers as $provider) {
            if (
                !is_string($provider)
                && !$provider instanceof ServiceProviderInterface
            ) {
                throw FrameworkException::invalidProvider(
                    get_debug_type($provider),
                    'Every provider must be a provider instance or provider class name.'
                );
            }

            $this->register($provider);
        }

        return $this;
    }

    /**
     * @brief Determine whether a service provider is registered.
     *
     * @param class-string<ServiceProviderInterface>|string $provider
     *
     * @return bool
     */
    public function hasProvider(
        string $provider
    ): bool {
        $provider = trim($provider);

        if ($provider === '') {
            return false;
        }

        return isset($this->providers[$provider]);
    }

    /**
     * @brief Retrieve a registered service provider.
     *
     * @param class-string<ServiceProviderInterface>|string $provider
     *
     * @return ServiceProviderInterface
     */
    public function provider(
        string $provider
    ): ServiceProviderInterface {
        $provider = trim($provider);

        if (
            $provider === ''
            || !isset($this->providers[$provider])
        ) {
            throw FrameworkException::providerNotFound(
                $provider
            );
        }

        return $this->providers[$provider];
    }

    /**
     * @brief Return all registered service providers.
     *
     * @return array<int, ServiceProviderInterface>
     */
    public function providers(): array
    {
        $providers = [];

        foreach ($this->providerOrder as $class) {
            if (isset($this->providers[$class])) {
                $providers[] = $this->providers[$class];
            }
        }

        return $providers;
    }

    /**
     * @brief Return providers supplying a container service.
     *
     * @param string $service
     *
     * @return array<int, ServiceProviderInterface>
     */
    public function providersFor(
        string $service
    ): array {
        $service = self::normalizeServiceId(
            $service
        );

        return array_values(
            array_filter(
                $this->providers(),
                static fn(
                    ServiceProviderInterface $provider
                ): bool => $provider->providesService(
                    $service
                )
            )
        );
    }

    /**
     * @brief Bootstrap the application.
     *
     * @return void
     */
    public function bootstrap(): void
    {
        if ($this->isBootstrapped()) {
            return;
        }

        if ($this->booting || $this->booted) {
            throw FrameworkException::invalidLifecycle(
                'application.bootstrap',
                'The application cannot bootstrap after provider booting starts.'
            );
        }

        try {
            $this->bootstrapper->bootstrap($this);
        } catch (FrameworkException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw FrameworkException::applicationBootFailed(
                $exception
            );
        }
    }

    /**
     * @brief Determine whether the application has been bootstrapped.
     *
     * @return bool
     */
    public function isBootstrapped(): bool
    {
        return $this->bootstrapper
            ->isBootstrapped();
    }

    /**
     * @brief Boot all registered service providers.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        if ($this->booting) {
            throw FrameworkException::invalidLifecycle(
                'application.boot',
                'Recursive application booting is not allowed.'
            );
        }

        if (!$this->isBootstrapped()) {
            $this->bootstrap();
        }

        $this->booting = true;

        try {
            foreach ($this->providerOrder as $class) {
                $provider = $this->providers[$class]
                    ?? null;

                if (!$provider instanceof ServiceProviderInterface) {
                    throw FrameworkException::providerNotFound(
                        $class
                    );
                }

                $provider->boot();
            }

            $this->booted = true;
        } catch (FrameworkException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw FrameworkException::applicationBootFailed(
                $exception
            );
        } finally {
            $this->booting = false;
        }
    }

    /**
     * @brief Bootstrap and boot the application.
     *
     * @return self
     */
    public function start(): self
    {
        $this->bootstrap();
        $this->boot();

        return $this;
    }

    /**
     * @brief Determine whether provider booting is active.
     *
     * @return bool
     */
    public function isBooting(): bool
    {
        return $this->booting;
    }

    /**
     * @brief Determine whether the application has completed booting.
     *
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * @brief Return the number of registered service providers.
     *
     * @return int
     */
    public function providerCount(): int
    {
        return count($this->providers);
    }

    /**
     * @brief Register framework services in the container.
     *
     * @return void
     */
    private function registerCoreServices(): void
    {
        $this->container->instance(
            'app',
            $this
        );

        $this->container->instance(
            Application::class,
            $this
        );

        $this->container->instance(
            ApplicationInterface::class,
            $this
        );

        $this->container->instance(
            'container',
            $this->container
        );

        $this->container->instance(
            Container::class,
            $this->container
        );

        $this->container->instance(
            'config',
            $this->config
        );

        $this->container->instance(
            ConfigRepository::class,
            $this->config
        );

        $this->container->instance(
            'bootstrapper',
            $this->bootstrapper
        );

        $this->container->instance(
            Bootstrapper::class,
            $this->bootstrapper
        );
    }

    /**
     * @param class-string<ServiceProviderInterface>|string $provider
     *
     * @return ServiceProviderInterface
     */
    private function createProvider(
        string $provider
    ): ServiceProviderInterface {
        $provider = trim($provider);

        if ($provider === '') {
            throw FrameworkException::invalidProvider(
                $provider,
                'The provider class name cannot be empty.'
            );
        }

        if (!class_exists($provider)) {
            throw FrameworkException::invalidProvider(
                $provider,
                'The provider class does not exist.'
            );
        }

        if (
            !is_subclass_of(
                $provider,
                ServiceProviderInterface::class
            )
        ) {
            throw FrameworkException::invalidProvider(
                $provider,
                'The provider must implement ServiceProviderInterface.'
            );
        }

        try {
            $instance = new $provider($this);
        } catch (Throwable $exception) {
            throw FrameworkException::providerCreationFailed(
                $provider,
                $exception
            );
        }

        if (!$instance instanceof ServiceProviderInterface) {
            throw FrameworkException::invalidProvider(
                $provider,
                'The created provider is invalid.'
            );
        }

        return $instance;
    }

    /**
     * @param string $basePath
     *
     * @return string
     */
    private static function normalizeBasePath(
        string $basePath
    ): string {
        $basePath = trim($basePath);

        if ($basePath === '') {
            throw FrameworkException::invalidBasePath(
                $basePath,
                'The base path cannot be empty.'
            );
        }

        if (
            str_contains($basePath, "\0")
            || str_contains($basePath, "\r")
            || str_contains($basePath, "\n")
        ) {
            throw FrameworkException::invalidBasePath(
                $basePath,
                'The base path contains invalid characters.'
            );
        }

        $resolved = realpath($basePath);

        if ($resolved === false) {
            throw FrameworkException::invalidBasePath(
                $basePath,
                'The directory does not exist.'
            );
        }

        if (!is_dir($resolved)) {
            throw FrameworkException::invalidBasePath(
                $basePath,
                'The path must reference a directory.'
            );
        }

        return rtrim(
            $resolved,
            DIRECTORY_SEPARATOR
        );
    }

    /**
     * @param string $environment
     *
     * @return string
     */
    private static function normalizeEnvironment(
        string $environment
    ): string {
        $environment = strtolower(
            trim($environment)
        );

        if ($environment === '') {
            throw FrameworkException::invalidEnvironment(
                $environment,
                'The environment name cannot be empty.'
            );
        }

        if (strlen($environment) > 120) {
            throw FrameworkException::invalidEnvironment(
                $environment,
                'The environment name cannot exceed 120 bytes.'
            );
        }

        if (
            preg_match(
                '/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/',
                $environment
            ) !== 1
        ) {
            throw FrameworkException::invalidEnvironment(
                $environment,
                'The environment name contains invalid characters.'
            );
        }

        return $environment;
    }

    /**
     * @param string $id
     *
     * @return string
     */
    private static function normalizeServiceId(
        string $id
    ): string {
        $id = trim($id);

        if ($id === '') {
            throw FrameworkException::invalidConfiguration(
                'application',
                'A service identifier cannot be empty.'
            );
        }

        if (
            str_contains($id, "\0")
            || str_contains($id, "\r")
            || str_contains($id, "\n")
        ) {
            throw FrameworkException::invalidConfiguration(
                'application',
                'A service identifier contains invalid characters.'
            );
        }

        return $id;
    }
}
