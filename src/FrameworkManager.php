<?php

declare(strict_types=1);

/**
 *
 * @file FrameworkManager.php
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
use Ivi\Config\ConfigRepository;
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
use Ivi\Framework\Exceptions\FrameworkException;

/**
 * @class FrameworkManager
 *
 * @brief Coordinates the IviPHP application and console runtime.
 *
 * FrameworkManager provides the main application-facing API for
 * configuring bootstrap operations, registering service providers,
 * registering console commands and starting the framework lifecycle.
 *
 * @since 0.1.0
 */
final class FrameworkManager
{
    /**
     * Framework application.
     */
    private Application $application;

    /**
     * Console runtime manager.
     */
    private ConsoleManager $consoleManager;

    /**
     * Public console API.
     */
    private Console $console;

    /**
     * @param Application        $application
     * @param ConsoleManager|null $consoleManager
     * @param string             $consoleName
     * @param string             $consoleVersion
     */
    public function __construct(
        Application $application,
        ?ConsoleManager $consoleManager = null,
        string $consoleName = 'IviPHP',
        string $consoleVersion = '0.1.0'
    ) {
        $this->application = $application;

        $this->consoleManager = $consoleManager
            ?? new ConsoleManager(
                applicationName: $consoleName,
                applicationVersion: $consoleVersion
            );

        $this->console = new Console(
            $this->consoleManager
        );

        $this->registerFrameworkServices();
    }

    /**
     * @brief Create a framework manager for an application directory.
     *
     * @param string             $basePath
     * @param Container|null     $container
     * @param ConfigRepository|null        $config
     * @param string             $environment
     * @param Bootstrapper|null  $bootstrapper
     * @param ConsoleManager|null $consoleManager
     * @param string             $consoleName
     * @param string             $consoleVersion
     *
     * @return self
     */
    public static function create(
        string $basePath,
        ?Container $container = null,
        ?ConfigRepository $config = null,
        string $environment = 'production',
        ?Bootstrapper $bootstrapper = null,
        ?ConsoleManager $consoleManager = null,
        string $consoleName = 'IviPHP',
        string $consoleVersion = '0.1.0'
    ): self {
        return new self(
            application: new Application(
                basePath: $basePath,
                container: $container,
                config: $config,
                environment: $environment,
                bootstrapper: $bootstrapper
            ),
            consoleManager: $consoleManager,
            consoleName: $consoleName,
            consoleVersion: $consoleVersion
        );
    }

    /**
     * @brief Return the framework application.
     *
     * @return Application
     */
    public function application(): Application
    {
        return $this->application;
    }

    /**
     * @brief Return the application through its contract.
     *
     * @return ApplicationInterface
     */
    public function applicationContract(): ApplicationInterface
    {
        return $this->application;
    }

    /**
     * @brief Return the application service container.
     *
     * @return Container
     */
    public function container(): Container
    {
        return $this->application->container();
    }

    /**
     * @brief Return the application configuration service.
     *
     * @return ConfigRepository
     */
    public function config(): ConfigRepository
    {
        return $this->application->config();
    }

    /**
     * @brief Return the application bootstrap coordinator.
     *
     * @return Bootstrapper
     */
    public function bootstrapper(): Bootstrapper
    {
        return $this->application->bootstrapper();
    }

    /**
     * @brief Return the public console API.
     *
     * @return Console
     */
    public function console(): Console
    {
        return $this->console;
    }

    /**
     * @brief Return the console runtime manager.
     *
     * @return ConsoleManager
     */
    public function consoleManager(): ConsoleManager
    {
        return $this->consoleManager;
    }

    /**
     * @brief Return the application base path.
     *
     * @return string
     */
    public function basePath(): string
    {
        return $this->application->basePath();
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
        return $this->application->path($path);
    }

    /**
     * @brief Return the current application environment.
     *
     * @return string
     */
    public function environment(): string
    {
        return $this->application->environment();
    }

    /**
     * @brief Change the application environment.
     *
     * @param string $environment
     *
     * @return self
     */
    public function setEnvironment(
        string $environment
    ): self {
        $this->application->setEnvironment(
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
        return $this->application->isEnvironment(
            ...$environments
        );
    }

    /**
     * @brief Resolve an application service.
     *
     * @param string $service
     *
     * @return mixed
     */
    public function make(string $service): mixed
    {
        return $this->application->make(
            $service
        );
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
        return $this->application->has(
            $service
        );
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
        $this->application->addBootstrapper(
            $name,
            $callback,
            $replace
        );

        return $this;
    }

    /**
     * @brief Register a service provider.
     *
     * @param ServiceProviderInterface|class-string<ServiceProviderInterface> $provider
     *
     * @return ServiceProviderInterface
     */
    public function registerProvider(
        ServiceProviderInterface|string $provider
    ): ServiceProviderInterface {
        return $this->application->register(
            $provider
        );
    }

    /**
     * @brief Register multiple service providers.
     *
     * @param iterable<ServiceProviderInterface|class-string<ServiceProviderInterface>> $providers
     *
     * @return self
     */
    public function registerProviders(
        iterable $providers
    ): self {
        $this->application->registerMany(
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
        return $this->application->hasProvider(
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
    public function provider(
        string $provider
    ): ServiceProviderInterface {
        return $this->application->provider(
            $provider
        );
    }

    /**
     * @brief Return all registered service providers.
     *
     * @return array<int, ServiceProviderInterface>
     */
    public function providers(): array
    {
        return $this->application->providers();
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
        return $this->application->providersFor(
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
        $this->console->register(
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
        $this->console->registerMany(
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
        return $this->console->command(
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
        return $this->console->has(
            $command
        );
    }

    /**
     * @brief Retrieve a console command.
     *
     * @param string $command
     *
     * @return CommandInterface
     */
    public function getCommand(
        string $command
    ): CommandInterface {
        return $this->console->get(
            $command
        );
    }

    /**
     * @brief Return all registered console commands.
     *
     * @param bool $includeHidden
     *
     * @return array<int, CommandInterface>
     */
    public function commands(
        bool $includeHidden = true
    ): array {
        return $this->console->commands(
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
        $this->application->bootstrap();

        return $this;
    }

    /**
     * @brief Boot all registered service providers.
     *
     * @return self
     */
    public function boot(): self
    {
        $this->application->boot();

        return $this;
    }

    /**
     * @brief Bootstrap and boot the application.
     *
     * @return self
     */
    public function start(): self
    {
        $this->application->start();

        return $this;
    }

    /**
     * @brief Determine whether application bootstrapping is complete.
     *
     * @return bool
     */
    public function isBootstrapped(): bool
    {
        return $this->application
            ->isBootstrapped();
    }

    /**
     * @brief Determine whether provider booting is active.
     *
     * @return bool
     */
    public function isBooting(): bool
    {
        return $this->application->isBooting();
    }

    /**
     * @brief Determine whether application booting is complete.
     *
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->application->isBooted();
    }

    /**
     * @brief Determine whether the framework is fully started.
     *
     * @return bool
     */
    public function isStarted(): bool
    {
        return $this->isBootstrapped()
            && $this->isBooted();
    }

    /**
     * @brief Start the framework and execute the console application.
     *
     * @param InputInterface|null  $input
     * @param OutputInterface|null $output
     *
     * @return int
     */
    public function runConsole(
        ?InputInterface $input = null,
        ?OutputInterface $output = null
    ): int {
        $this->start();

        return $this->console->run(
            $input,
            $output
        );
    }

    /**
     * @brief Execute console input without automatic exception handling.
     *
     * The framework application is started before the command executes.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    public function executeConsole(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $this->start();

        return $this->console->execute(
            $input,
            $output
        );
    }

    /**
     * @brief Return the number of registered providers.
     *
     * @return int
     */
    public function providerCount(): int
    {
        return $this->application
            ->providerCount();
    }

    /**
     * @brief Return the number of registered console commands.
     *
     * @return int
     */
    public function commandCount(): int
    {
        return $this->console->count();
    }

    /**
     * @brief Register framework manager services in the container.
     *
     * @return void
     */
    private function registerFrameworkServices(): void
    {
        $container = $this->application
            ->container();

        $services = [
            'framework' => $this,
            FrameworkManager::class => $this,
            'console' => $this->console,
            Console::class => $this->console,
            'console.manager' => $this->consoleManager,
            ConsoleManager::class => $this->consoleManager,
        ];

        foreach ($services as $id => $service) {
            if ($container->has($id)) {
                throw FrameworkException::invalidConfiguration(
                    'framework_manager',
                    "The container service {$id} is already registered."
                );
            }

            $container->instance(
                $id,
                $service
            );
        }
    }
}
