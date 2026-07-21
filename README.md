# Ivi Framework

Application bootstrapping, service providers, dependency coordination and console integration for the IviPHP ecosystem.

`iviphp/framework` provides the central runtime used to assemble an IviPHP application. It coordinates the service container, configuration, bootstrap operations, service providers and console commands through a small framework-independent API.

## Requirements

- PHP 8.2 or later
- `iviphp/config`
- `iviphp/console`
- `iviphp/container`
- `iviphp/contracts`
- `iviphp/debug`
- `iviphp/http`
- `iviphp/support`

## Installation

```bash
composer require iviphp/framework
```

## Features

- Central application object
- Application base-path management
- Environment detection
- Dependency-container integration
- Configuration integration
- Named bootstrap operations
- Service-provider lifecycle
- Provider service declarations
- Provider registration and boot tracking
- Console command integration
- Closure-backed commands
- Framework service registration
- Safe lifecycle exceptions
- Idempotent application startup
- Framework-independent contracts

## Core components

### `Application`

`Application` represents the running application.

It manages:

- the application base directory;
- the current environment;
- the service container;
- application configuration;
- bootstrap operations;
- service providers;
- the provider boot lifecycle.

### `FrameworkManager`

`FrameworkManager` coordinates the application and console runtime.

It provides the main management API for:

- creating an application;
- registering bootstrap operations;
- registering service providers;
- resolving container services;
- registering console commands;
- starting the framework;
- executing the console runtime.

### `Framework`

`Framework` is the public application-facing API.

It wraps `FrameworkManager` and exposes a compact interface for normal framework usage.

### `Bootstrapper`

`Bootstrapper` stores named application initialization callbacks and executes them in registration order.

### `ServiceProvider`

`ServiceProvider` is the reusable base class for framework service providers.

Concrete providers implement service registration and may optionally perform boot-time initialization.

## Creating a framework application

```php
<?php

declare(strict_types=1);

use Ivi\Framework\Framework;

$framework = Framework::create(
    basePath: dirname(__DIR__),
    environment: 'development',
    consoleName: 'My Application',
    consoleVersion: '1.0.0'
);
```

The base path must reference an existing directory.

## Starting the framework

```php
$framework->start();
```

Starting the framework performs two stages:

1. application bootstrap operations;
2. service-provider booting.

Repeated calls to `start()` do not execute completed lifecycle stages again.

## Application lifecycle

The application lifecycle follows this order:

```text
Create application
Register bootstrap operations
Register service providers
Bootstrap application
Boot service providers
Run application
```

Bootstrap callbacks execute before service providers are booted.

Provider registration happens when the provider is added to the application.

## Application base path

```php
$basePath = $framework->basePath();
```

Resolve a path relative to the application base directory:

```php
$configPath = $framework->path(
    'config/app.php'
);
```

Example result:

```text
/project/config/app.php
```

Passing an empty string returns the application base path:

```php
$basePath = $framework->path();
```

Absolute paths are rejected.

Paths containing parent-directory traversal are also rejected:

```php
$framework->path('../secret.txt');
```

## Application environment

Create an application for a specific environment:

```php
$framework = Framework::create(
    basePath: dirname(__DIR__),
    environment: 'production'
);
```

Retrieve the environment:

```php
$environment = $framework->environment();
```

Check one environment:

```php
if ($framework->isEnvironment('production')) {
    // Production configuration.
}
```

Check several environments:

```php
if (
    $framework->isEnvironment(
        'development',
        'testing'
    )
) {
    // Development or testing behavior.
}
```

Change the environment before bootstrapping:

```php
$framework->setEnvironment('testing');
```

The environment cannot be changed after application bootstrapping begins.

## Service container

Retrieve the application container:

```php
$container = $framework->container();
```

Resolve a service:

```php
$service = $framework->make(
    App\Services\Mailer::class
);
```

Check whether a service exists:

```php
if ($framework->has('mailer')) {
    $mailer = $framework->make('mailer');
}
```

The framework automatically registers several core services.

```php
use Ivi\Config\Config;
use Ivi\Container\Container;
use Ivi\Framework\Application;
use Ivi\Framework\Bootstrap\Bootstrapper;
use Ivi\Framework\Contracts\ApplicationInterface;
use Ivi\Framework\FrameworkManager;

$framework->make('app');
$framework->make(Application::class);
$framework->make(ApplicationInterface::class);

$framework->make('container');
$framework->make(Container::class);

$framework->make('config');
$framework->make(Config::class);

$framework->make('bootstrapper');
$framework->make(Bootstrapper::class);

$framework->make('framework');
$framework->make(FrameworkManager::class);

$framework->make('console');
$framework->make('console.manager');
```

## Configuration

Retrieve the application configuration service:

```php
$config = $framework->config();
```

The returned object is the `Ivi\Config\Config` instance registered in the application container.

A custom configuration object may be supplied during creation:

```php
<?php

declare(strict_types=1);

use Ivi\Config\Config;
use Ivi\Framework\Framework;

$config = new Config();

$framework = Framework::create(
    basePath: dirname(__DIR__),
    config: $config
);
```

## Custom container

A custom container instance may be supplied:

```php
<?php

declare(strict_types=1);

use Ivi\Container\Container;
use Ivi\Framework\Framework;

$container = new Container();

$framework = Framework::create(
    basePath: dirname(__DIR__),
    container: $container
);
```

The framework registers its core services in the supplied container.

## Bootstrap operations

Register a named bootstrap callback:

```php
use Ivi\Framework\Contracts\ApplicationInterface;

$framework->bootstrapWith(
    'load.configuration',
    static function (
        ApplicationInterface $application
    ): void {
        $configPath = $application->path(
            'config/app.php'
        );

        if (is_file($configPath)) {
            $values = require $configPath;

            if (is_array($values)) {
                $application
                    ->config()
                    ->set($values);
            }
        }
    }
);
```

Register another operation:

```php
$framework->bootstrapWith(
    'prepare.storage',
    static function (
        ApplicationInterface $application
    ): void {
        $storagePath = $application->path(
            'storage'
        );

        if (!is_dir($storagePath)) {
            mkdir(
                $storagePath,
                0775,
                true
            );
        }
    }
);
```

Bootstrap operations execute in registration order.

## Replacing a bootstrap operation

```php
$framework->bootstrapWith(
    'load.configuration',
    static function (
        ApplicationInterface $application
    ): void {
        // Updated initialization.
    },
    replace: true
);
```

The named operation must already exist when calling `Bootstrapper::replace()` directly.

## Accessing the bootstrapper

```php
$bootstrapper = $framework->bootstrapper();
```

List registered operation names:

```php
$names = $bootstrapper->names();
```

List completed operations:

```php
$executed = $bootstrapper->executed();
```

Check whether an operation exists:

```php
if ($bootstrapper->has('load.configuration')) {
    // Operation registered.
}
```

Check whether an operation completed:

```php
if (
    $bootstrapper->hasExecuted(
        'load.configuration'
    )
) {
    // Operation completed.
}
```

Inspect the current operation during bootstrap:

```php
$current = $bootstrapper->current();
```

## Creating a service provider

Extend the base service-provider class:

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Mailer;
use Ivi\Framework\Providers\ServiceProvider;

final class MailServiceProvider extends ServiceProvider
{
    public function __construct(
        \Ivi\Framework\Contracts\ApplicationInterface $application
    ) {
        parent::__construct(
            $application,
            [
                Mailer::class,
                'mailer',
            ]
        );
    }

    protected function registerServices(): void
    {
        $mailer = new Mailer();

        $this->container()->instance(
            Mailer::class,
            $mailer
        );

        $this->container()->instance(
            'mailer',
            $mailer
        );
    }

    protected function bootServices(): void
    {
        $mailer = $this->make(
            Mailer::class
        );

        $mailer->initialize();
    }
}
```

## Registering a provider

Register a provider class:

```php
use App\Providers\MailServiceProvider;

$framework->provider(
    MailServiceProvider::class
);
```

The framework creates the provider by passing the application instance to its constructor.

Register a provider instance:

```php
$provider = new MailServiceProvider(
    $framework->application()
);

$framework->provider($provider);
```

A provider cannot belong to another application instance.

## Registering multiple providers

```php
$framework->providers([
    App\Providers\ConfigServiceProvider::class,
    App\Providers\DatabaseServiceProvider::class,
    App\Providers\MailServiceProvider::class,
]);
```

Providers are registered in the supplied order.

They are also booted in registration order.

## Provider lifecycle

A service provider has two lifecycle stages.

### Registration

```php
protected function registerServices(): void
{
    // Register container bindings.
}
```

Registration should define:

- instances;
- factories;
- aliases;
- configuration defaults;
- framework services.

Registration happens immediately when the provider is added to the application.

### Booting

```php
protected function bootServices(): void
{
    // Resolve services and complete initialization.
}
```

Booting happens after application bootstrap operations complete.

At boot time, providers may safely coordinate services registered by other providers.

## Provider state

Retrieve a provider:

```php
$provider = $framework->getProvider(
    App\Providers\MailServiceProvider::class
);
```

Check its lifecycle state:

```php
if ($provider->isRegistered()) {
    // Provider registration completed.
}

if ($provider->isBooted()) {
    // Provider boot completed.
}
```

The base `ServiceProvider` class also exposes:

```php
$provider->isRegistering();
$provider->isBooting();
```

## Provider service declarations

Providers may declare the services they supply:

```php
public function __construct(
    ApplicationInterface $application
) {
    parent::__construct(
        $application,
        [
            App\Services\Mailer::class,
            'mailer',
        ]
    );
}
```

Retrieve declared services:

```php
$services = $provider->provides();
```

Check one service:

```php
if ($provider->providesService('mailer')) {
    // Provider declares the mailer service.
}
```

Find all providers declaring a service:

```php
$providers = $framework->providersFor(
    'mailer'
);
```

Service declarations are intended for diagnostics and framework introspection.

They do not automatically register container bindings.

## Checking registered providers

```php
use App\Providers\MailServiceProvider;

if (
    $framework->hasProvider(
        MailServiceProvider::class
    )
) {
    $provider = $framework->getProvider(
        MailServiceProvider::class
    );
}
```

Return all registered providers:

```php
$providers = $framework
    ->registeredProviders();
```

Return the provider count:

```php
$count = $framework->providerCount();
```

## Manual bootstrap and boot

Bootstrap without booting providers:

```php
$framework->bootstrap();
```

Boot providers:

```php
$framework->boot();
```

Calling `boot()` before `bootstrap()` automatically bootstraps the application first.

Check lifecycle state:

```php
if ($framework->isBootstrapped()) {
    // Bootstrap operations completed.
}

if ($framework->isBooting()) {
    // Provider booting is active.
}

if ($framework->isBooted()) {
    // All providers completed booting.
}

if ($framework->isStarted()) {
    // Application is fully initialized.
}
```

## Console integration

The framework integrates `iviphp/console`.

Retrieve the console API:

```php
$console = $framework->console();
```

Retrieve the console manager:

```php
$consoleManager = $framework
    ->consoleManager();
```

## Registering a console command

```php
<?php

declare(strict_types=1);

use Ivi\Console\Contracts\InputInterface;
use Ivi\Console\Contracts\OutputInterface;

$framework->command(
    name: 'app:status',
    handler: static function (
        InputInterface $input,
        OutputInterface $output
    ): int {
        $output->success(
            'The application is running.'
        );

        return 0;
    },
    description: 'Display application status.',
    aliases: [
        'status',
    ],
    usage: 'app:status',
    hidden: false
);
```

## Registering command objects

```php
$framework->registerCommand(
    new App\Console\Commands\CacheClearCommand()
);
```

Register several commands:

```php
$framework->registerCommands([
    new App\Console\Commands\CacheClearCommand(),
    new App\Console\Commands\DatabaseMigrateCommand(),
]);
```

Replace commands with matching names:

```php
$framework->registerCommands(
    $commands,
    replace: true
);
```

## Inspecting console commands

Check whether a command exists:

```php
if ($framework->hasCommand('app:status')) {
    $command = $framework->getCommand(
        'app:status'
    );
}
```

Return all commands:

```php
$commands = $framework->commands();
```

Exclude hidden commands:

```php
$commands = $framework->commands(
    includeHidden: false
);
```

Return the number of registered commands:

```php
$count = $framework->commandCount();
```

## Running the console application

```php
$exitCode = $framework->run();

exit($exitCode);
```

The framework is started before console execution.

A minimal `bin/console` file may look like this:

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__)
    . '/vendor/autoload.php';

use Ivi\Console\Contracts\InputInterface;
use Ivi\Console\Contracts\OutputInterface;
use Ivi\Framework\Framework;

$framework = Framework::create(
    basePath: dirname(__DIR__),
    environment: getenv('APP_ENV')
        ?: 'production',
    consoleName: 'My Application',
    consoleVersion: '1.0.0'
);

$framework->command(
    name: 'hello',
    handler: static function (
        InputInterface $input,
        OutputInterface $output
    ): int {
        $name = $input->argument(
            0,
            'Developer'
        );

        $output->success(
            "Hello, {$name}."
        );

        return 0;
    },
    description: 'Display a greeting.',
    usage: 'hello [name]'
);

exit($framework->run());
```

Make the file executable:

```bash
chmod +x bin/console
```

Run it:

```bash
./bin/console hello Gaspard
```

## Executing custom console input

```php
<?php

declare(strict_types=1);

use Ivi\Console\Input\ArgvInput;
use Ivi\Console\Output\ConsoleOutput;

$input = ArgvInput::fromTokens([
    'hello',
    'Gaspard',
]);

$output = new ConsoleOutput();

$exitCode = $framework->execute(
    $input,
    $output
);
```

`execute()` starts the framework and executes the supplied input without using the console manager's automatic exception handling.

## Accessing the application

```php
$application = $framework->application();
```

Access it through the public contract:

```php
$application = $framework
    ->applicationContract();
```

The application contract exposes:

```php
public function basePath(): string;

public function path(
    string $path = ''
): string;

public function environment(): string;

public function isEnvironment(
    string ...$environments
): bool;

public function container(): Container;

public function config(): Config;

public function make(string $id): mixed;

public function has(string $id): bool;

public function register(
    ServiceProviderInterface|string $provider
): ServiceProviderInterface;

public function hasProvider(
    string $provider
): bool;

public function providers(): array;

public function bootstrap(): void;

public function isBootstrapped(): bool;

public function boot(): void;

public function isBooted(): bool;
```

## Using `Application` directly

The higher-level `Framework` API is recommended, but `Application` may also be used directly.

```php
<?php

declare(strict_types=1);

use Ivi\Framework\Application;

$application = new Application(
    basePath: dirname(__DIR__),
    environment: 'development'
);

$application->register(
    App\Providers\AppServiceProvider::class
);

$application->start();
```

## Using `FrameworkManager` directly

```php
<?php

declare(strict_types=1);

use Ivi\Framework\FrameworkManager;

$manager = FrameworkManager::create(
    basePath: dirname(__DIR__),
    environment: 'development',
    consoleName: 'My Application',
    consoleVersion: '1.0.0'
);

$manager->registerProviders([
    App\Providers\AppServiceProvider::class,
]);

$manager->start();
```

## Exceptions

Framework lifecycle failures are represented by:

```php
Ivi\Framework\Exceptions\FrameworkException
```

Examples include:

- invalid application base paths;
- invalid relative paths;
- invalid environment names;
- invalid service providers;
- duplicate provider registration;
- provider creation failures;
- provider registration failures;
- provider boot failures;
- invalid bootstrap operations;
- bootstrap failures;
- unavailable services;
- service-resolution failures;
- invalid framework configuration;
- invalid lifecycle operations.

```php
<?php

declare(strict_types=1);

use Ivi\Framework\Exceptions\FrameworkException;

try {
    $framework->start();
} catch (FrameworkException $exception) {
    echo $exception->getMessage();

    $context = $exception->context();
}
```

Exception context contains safe diagnostic metadata such as:

- provider class;
- bootstrap operation name;
- service identifier;
- application path;
- lifecycle operation.

It should not contain service values, configuration secrets or request data.

## Lifecycle restrictions

To keep framework state predictable:

- environments cannot change after bootstrap begins;
- bootstrap operations cannot change while bootstrapping;
- completed bootstrappers cannot be modified;
- service providers cannot be registered after provider booting starts;
- providers cannot boot before registration;
- providers cannot register after booting begins;
- recursive bootstrap, provider registration and provider boot operations are rejected.

## Complete example

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__)
    . '/vendor/autoload.php';

use App\Providers\AppServiceProvider;
use App\Providers\DatabaseServiceProvider;
use Ivi\Console\Contracts\InputInterface;
use Ivi\Console\Contracts\OutputInterface;
use Ivi\Framework\Contracts\ApplicationInterface;
use Ivi\Framework\Framework;

$framework = Framework::create(
    basePath: dirname(__DIR__),
    environment: getenv('APP_ENV')
        ?: 'production',
    consoleName: 'Example Application',
    consoleVersion: '1.0.0'
);

$framework->bootstrapWith(
    'prepare.storage',
    static function (
        ApplicationInterface $application
    ): void {
        $storage = $application->path(
            'storage'
        );

        if (!is_dir($storage)) {
            mkdir(
                $storage,
                0775,
                true
            );
        }
    }
);

$framework->providers([
    AppServiceProvider::class,
    DatabaseServiceProvider::class,
]);

$framework->command(
    name: 'app:environment',
    handler: static function (
        InputInterface $input,
        OutputInterface $output
    ) use ($framework): int {
        $output->info(
            'Environment: '
            . $framework->environment()
        );

        return 0;
    },
    description: 'Display the application environment.',
    aliases: [
        'env',
    ]
);

exit($framework->run());
```

## Design principles

`iviphp/framework` follows these principles:

- explicit application lifecycle;
- framework-independent contracts;
- dependency-container coordination;
- ordered bootstrap operations;
- predictable provider registration;
- idempotent application startup;
- separation between registration and booting;
- safe diagnostic context;
- small public application API;
- modular integration with the IviPHP ecosystem.

## License

Ivi Framework is open-source software released under the MIT License.

## Maintainer

Maintained by [Gaspard Kirira](https://github.com/GaspardKirira) and [Softadastra](https://softadastra.com).
