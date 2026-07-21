<?php

declare(strict_types=1);

/**
 *
 * @file Framework.php
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

use Closure;
use Ivi\Config\Config;
use Ivi\Console\Command;
use Ivi\Console\Console;
use Ivi\Console\ConsoleManager;
use Ivi\Console\Contracts\CommandInterface;
use Ivi\Console\Contracts\InputInterface;
use Ivi\Console\Contracts\OutputInterface;
use Ivi\Container\Container;
use Ivi\Framework\Bootstrap\Bootstrapper;
use Ivi\Framework\Contracts\ApplicationInterface;
use Ivi\Framework\Contracts\ServiceProviderInterface;

/**
 * @class Framework
 *
 * @brief Provides the public API for an IviPHP framework application.
 *
 * Framework is a lightweight facade around FrameworkManager. It exposes
 * application configuration, service-provider registration, bootstrap
 * operations, container resolution and console command execution through
 * a compact application-facing API.
 *
 * @since 0.1.0
 */
final readonly class Framework
{
    /**
     * @param FrameworkManager $manager
     */
    public function __construct(
        private FrameworkManager $manager
    ) {
    }

    /**
     * @brief Create a framework application.
     *
     * @param string              $basePath
     * @param Container|null      $container
     * @param Config|null         $config
     * @param string              $environment
     * @param Bootstrapper|null   $bootstrapper
     * @param ConsoleManager|null $consoleManager
     * @param string              $consoleName
     * @param string              $consoleVersion
     *
     * @return self
     */
    public static function create(
        string $basePath,
        ?Container $container = null,
        ?Config $config = null,
        string $environment = 'production',
        ?Bootstrapper $bootstrapper = null,
        ?ConsoleManager $consoleManager = null,
        string $consoleName = 'IviPHP',
        string $consoleVersion = '0.1.0'
    ): self {
        return new self(
            FrameworkManager::create(
                basePath: $basePath,
                container: $container,
                config: $config,
                environment: $environment,
                bootstrapper: $bootstrapper,
                consoleManager: $consoleManager,
                consoleName: $consoleName,
                consoleVersion: $consoleVersion
            )
        );
    }

    /**
     * @brief Return the underlying framework manager.
     *
     * @return FrameworkManager
     */
    public function manager(): FrameworkManager
    {
        return $this->manager;
    }

    /**
     * @brief Return the framework application.
     *
     * @return Application
     */
    public function application(): Application
    {
        return $this->manager->application();
    }

    /**
     * @brief Return the application through its contract.
     *
     * @return ApplicationInterface
     */
    public function applicationContract(): ApplicationInterface
    {
        return $this->manager
            ->applicationContract();
    }

    /**
     * @brief Return the application service container.
     *
     * @return Container
     */
    public function container(): Container
    {
        return $this->manager->container();
    }

    /**
     * @brief Return the application configuration service.
     *
     * @return Config
     */
    public function config(): Config
    {
        return $this->manager->config();
    }

    /**
     * @brief Return the application bootstrap coordinator.
     *
     * @return Bootstrapper
     */
    public function bootstrapper(): Bootstrapper
    {
        return $this->manager->bootstrapper();
    }

    /**
     * @brief Return the public console API.
     *
     * @return Console
     */
    public function console(): Console
    {
        return $this->manager->console();
    }

    /**
     * @brief Return the console runtime manager.
     *
     * @return ConsoleManager
     */
    public function consoleManager(): ConsoleManager
    {
        return $this->manager
            ->consoleManager();
    }

    /**
     * @brief Return the application base path.
     *
     * @return string
     */
    public function basePath(): string
    {
        return $this->manager->basePath();
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
        return $this->manager->path($path);
    }

    /**
     * @brief Return the current application environment.
     *
     * @return string
     */
    public function environment(): string
    {
        return $this->manager->environment();
    }

    /**
     * @brief Change the current application environment.
     *
     * @param string $environment
     *
     * @return self
     */
    public function setEnvironment(
        string $environment
    ): self {
        $this->manager->setEnvironment(
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
        return $this->manager->isEnvironment(
            ...$environments
        );
    }

    /**
     * @brief Resolve a service from the application container.
     *
     * @param string $service
     *
     * @return mixed
     */
    public function make(string $service): mixed
    {
        return $this->manager->make($service);
    }

    /**
     * @brief Determine whether an application service exists.
     *
     * @param string $service
     *
     * @return bool
     */
    public function has(string $service): bool
    {
        return $this->manager->has($service);
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
    public function bootstrapWith(
        string $name,
        callable $callback,
        bool $replace = false
    ): self {
        $this->manager->addBootstrapper(
            $name,
            $callback,
            $replace
        );

        return $this;
    }

    /**
     * @brief Register an application service provider.
     *
     * @param ServiceProviderInterface|class-string<ServiceProviderInterface> $provider
     *
     * @return ServiceProviderInterface
     */
    public function provider(
        ServiceProviderInterface|string $provider
    ): ServiceProviderInterface {
        return $this->manager
            ->registerProvider($provider);
    }

    /**
     * @brief Register multiple application service providers.
     *
     * @param iterable<ServiceProviderInterface|class-string<ServiceProviderInterface>> $providers
     *
     * @return self
     */
    public function providers(
        iterable $providers
    ): self {
        $this->manager->registerProviders(
            $providers
        );

        return $this;
    }

    /**
     * @brief Determine whether a service provider is registered.
     *
     * @param string $provider
     *
     * @return bool
     */
    public function hasProvider(
        string $provider
    ): bool {
        return $this->manager->hasProvider(
            $provider
        );
    }

    /**
     * @brief Retrieve a registered service provider.
     *
     * @param string $provider
     *
     * @return ServiceProviderInterface
     */
    public function getProvider(
        string $provider
    ): ServiceProviderInterface {
        return $this->manager->provider(
            $provider
        );
    }

    /**
     * @brief Return all registered service providers.
     *
     * @return array<int, ServiceProviderInterface>
     */
    public function registeredProviders(): array
    {
        return $this->manager->providers();
    }

    /**
     * @brief Return providers supplying a service.
     *
     * @param string $service
     *
     * @return array<int, ServiceProviderInterface>
     */
    public function providersFor(
        string $service
    ): array {
        return $this->manager->providersFor(
            $service
        );
    }

    /**
     * @brief Register a console command.
     *
     * @param CommandInterface $command
     * @param bool             $replace
     *
     * @return self
     */
    public function registerCommand(
        CommandInterface $command,
        bool $replace = false
    ): self {
        $this->manager->registerCommand(
            $command,
            $replace
        );

        return $this;
    }

    /**
     * @brief Register multiple console commands.
     *
     * @param iterable<CommandInterface> $commands
     * @param bool                       $replace
     *
     * @return self
     */
    public function registerCommands(
        iterable $commands,
        bool $replace = false
    ): self {
        $this->manager->registerCommands(
            $commands,
            $replace
        );

        return $this;
    }

    /**
     * @brief Register a closure-backed console command.
     *
     * @param string $name
     * @param Closure(InputInterface, OutputInterface): int $handler
     * @param string $description
     * @param iterable<string> $aliases
     * @param string|null $usage
     * @param bool $hidden
     * @param bool $replace
     *
     * @return Command
     */
    public function command(
        string $name,
        Closure $handler,
        string $description = '',
        iterable $aliases = [],
        ?string $usage = null,
        bool $hidden = false,
        bool $replace = false
    ): Command {
        return $this->manager->command(
            name: $name,
            handler: $handler,
            description: $description,
            aliases: $aliases,
            usage: $usage,
            hidden: $hidden,
            replace: $replace
        );
    }

    /**
     * @brief Determine whether a console command exists.
     *
     * @param string $command
     *
     * @return bool
     */
    public function hasCommand(
        string $command
    ): bool {
        return $this->manager->hasCommand(
            $command
        );
    }

    /**
     * @brief Retrieve a registered console command.
     *
     * @param string $command
     *
     * @return CommandInterface
     */
    public function getCommand(
        string $command
    ): CommandInterface {
        return $this->manager->getCommand(
            $command
        );
    }

    /**
     * @brief Return registered console commands.
     *
     * @param bool $includeHidden
     *
     * @return array<int, CommandInterface>
     */
    public function commands(
        bool $includeHidden = true
    ): array {
        return $this->manager->commands(
            $includeHidden
        );
    }

    /**
     * @brief Bootstrap the application.
     *
     * @return self
     */
    public function bootstrap(): self
    {
        $this->manager->bootstrap();

        return $this;
    }

    /**
     * @brief Boot all registered service providers.
     *
     * @return self
     */
    public function boot(): self
    {
        $this->manager->boot();

        return $this;
    }

    /**
     * @brief Bootstrap and boot the application.
     *
     * @return self
     */
    public function start(): self
    {
        $this->manager->start();

        return $this;
    }

    /**
     * @brief Determine whether application bootstrapping is complete.
     *
     * @return bool
     */
    public function isBootstrapped(): bool
    {
        return $this->manager
            ->isBootstrapped();
    }

    /**
     * @brief Determine whether provider booting is active.
     *
     * @return bool
     */
    public function isBooting(): bool
    {
        return $this->manager->isBooting();
    }

    /**
     * @brief Determine whether application booting is complete.
     *
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->manager->isBooted();
    }

    /**
     * @brief Determine whether the framework is fully started.
     *
     * @return bool
     */
    public function isStarted(): bool
    {
        return $this->manager->isStarted();
    }

    /**
     * @brief Start the framework and execute the console application.
     *
     * @param InputInterface|null  $input
     * @param OutputInterface|null $output
     *
     * @return int
     */
    public function run(
        ?InputInterface $input = null,
        ?OutputInterface $output = null
    ): int {
        return $this->manager->runConsole(
            $input,
            $output
        );
    }

    /**
     * @brief Start the framework and execute parsed console input.
     *
     * This method does not use automatic console exception handling.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    public function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        return $this->manager->executeConsole(
            $input,
            $output
        );
    }

    /**
     * @brief Return the number of registered service providers.
     *
     * @return int
     */
    public function providerCount(): int
    {
        return $this->manager
            ->providerCount();
    }

    /**
     * @brief Return the number of registered console commands.
     *
     * @return int
     */
    public function commandCount(): int
    {
        return $this->manager
            ->commandCount();
    }
}
