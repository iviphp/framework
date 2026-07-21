<?php

declare(strict_types=1);

/**
 *
 * @file ServiceProvider.php
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

namespace Ivi\Framework\Providers;

use Ivi\Config\ConfigRepository;
use Ivi\Container\Container;
use Ivi\Framework\Contracts\ApplicationInterface;
use Ivi\Framework\Contracts\ServiceProviderInterface;
use Ivi\Framework\Exceptions\FrameworkException;
use Throwable;

/**
 * @class ServiceProvider
 *
 * @brief Provides a reusable base implementation for service providers.
 *
 * ServiceProvider manages the provider registration and boot lifecycle,
 * exposes the application, container and configuration services, and
 * tracks the container identifiers supplied by the provider.
 *
 * Concrete providers implement registerServices() and may optionally
 * implement bootServices().
 *
 * @since 0.1.0
 */
abstract class ServiceProvider implements ServiceProviderInterface
{
    /**
     * Whether the provider has completed registration.
     */
    private bool $registered = false;

    /**
     * Whether provider registration is currently active.
     */
    private bool $registering = false;

    /**
     * Whether the provider has completed booting.
     */
    private bool $booted = false;

    /**
     * Whether provider booting is currently active.
     */
    private bool $booting = false;

    /**
     * Services supplied by the provider.
     *
     * @var array<int, string>
     */
    private array $providedServices;

    /**
     * @param ApplicationInterface $application
     * @param iterable<string>     $provides
     */
    public function __construct(
        private readonly ApplicationInterface $application,
        iterable $provides = []
    ) {
        $this->providedServices = self::normalizeServices(
            $provides
        );
    }

    /**
     * @brief Return the associated application.
     *
     * @return ApplicationInterface
     */
    final public function application(): ApplicationInterface
    {
        return $this->application;
    }

    /**
     * @brief Return the application service container.
     *
     * @return Container
     */
    final protected function container(): Container
    {
        return $this->application->container();
    }

    /**
     * @brief Return the application configuration service.
     *
     * @return ConfigRepository
     */
    final protected function config(): ConfigRepository
    {
        return $this->application->config();
    }

    /**
     * @brief Resolve a service from the application container.
     *
     * @param string $service
     *
     * @return mixed
     */
    final protected function make(string $service): mixed
    {
        $service = self::normalizeService($service);

        return $this->application->make($service);
    }

    /**
     * @brief Determine whether an application service exists.
     *
     * @param string $service
     *
     * @return bool
     */
    final protected function has(string $service): bool
    {
        $service = self::normalizeService($service);

        return $this->application->has($service);
    }

    /**
     * @brief Register services supplied by this provider.
     *
     * Registration is executed only once. Recursive registration is not
     * allowed.
     *
     * @return void
     */
    final public function register(): void
    {
        if ($this->registered) {
            return;
        }

        if ($this->registering) {
            throw FrameworkException::invalidLifecycle(
                'provider.register',
                sprintf(
                    'Recursive provider registration is not allowed for %s.',
                    static::class
                )
            );
        }

        if ($this->booted || $this->booting) {
            throw FrameworkException::invalidLifecycle(
                'provider.register',
                sprintf(
                    'Provider %s cannot be registered after booting has started.',
                    static::class
                )
            );
        }

        $this->registering = true;

        try {
            $this->registerServices();
            $this->registered = true;
        } catch (FrameworkException $exception) {
            throw FrameworkException::providerRegistrationFailed(
                static::class,
                $exception
            );
        } catch (Throwable $exception) {
            throw FrameworkException::providerRegistrationFailed(
                static::class,
                $exception
            );
        } finally {
            $this->registering = false;
        }
    }

    /**
     * @brief Boot the service provider.
     *
     * A provider must complete registration before it can boot.
     * Booting is executed only once.
     *
     * @return void
     */
    final public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        if (!$this->registered) {
            throw FrameworkException::providerNotRegistered(
                static::class
            );
        }

        if ($this->booting) {
            throw FrameworkException::invalidLifecycle(
                'provider.boot',
                sprintf(
                    'Recursive provider booting is not allowed for %s.',
                    static::class
                )
            );
        }

        $this->booting = true;

        try {
            $this->bootServices();
            $this->booted = true;
        } catch (FrameworkException $exception) {
            throw FrameworkException::providerBootFailed(
                static::class,
                $exception
            );
        } catch (Throwable $exception) {
            throw FrameworkException::providerBootFailed(
                static::class,
                $exception
            );
        } finally {
            $this->booting = false;
        }
    }

    /**
     * @brief Determine whether the provider has been registered.
     *
     * @return bool
     */
    final public function isRegistered(): bool
    {
        return $this->registered;
    }

    /**
     * @brief Determine whether provider registration is active.
     *
     * @return bool
     */
    final public function isRegistering(): bool
    {
        return $this->registering;
    }

    /**
     * @brief Determine whether the provider has completed booting.
     *
     * @return bool
     */
    final public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * @brief Determine whether provider booting is active.
     *
     * @return bool
     */
    final public function isBooting(): bool
    {
        return $this->booting;
    }

    /**
     * @brief Return services supplied by this provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return $this->providedServices;
    }

    /**
     * @brief Determine whether the provider supplies a service.
     *
     * @param string $service
     *
     * @return bool
     */
    final public function providesService(
        string $service
    ): bool {
        $service = self::normalizeService($service);

        return in_array(
            $service,
            $this->provides(),
            true
        );
    }

    /**
     * @brief Add a service to the provider declaration.
     *
     * Service declarations cannot be changed after registration begins.
     *
     * @param string $service
     *
     * @return void
     */
    final protected function addProvidedService(
        string $service
    ): void {
        if (
            $this->registering
            || $this->registered
            || $this->booting
            || $this->booted
        ) {
            throw FrameworkException::invalidLifecycle(
                'provider.provides',
                'Provided services cannot be changed after registration starts.'
            );
        }

        $service = self::normalizeService($service);

        if (
            !in_array(
                $service,
                $this->providedServices,
                true
            )
        ) {
            $this->providedServices[] = $service;
        }
    }

    /**
     * @brief Register container services.
     *
     * Concrete providers must implement this method instead of overriding
     * the public register() lifecycle method.
     *
     * @return void
     */
    abstract protected function registerServices(): void;

    /**
     * @brief Perform provider initialization after registration.
     *
     * Concrete providers may override this method when boot-time work is
     * required.
     *
     * @return void
     */
    protected function bootServices(): void
    {
    }

    /**
     * @param iterable<string> $services
     *
     * @return array<int, string>
     */
    private static function normalizeServices(
        iterable $services
    ): array {
        $normalized = [];

        foreach ($services as $service) {
            if (!is_string($service)) {
                throw FrameworkException::invalidProvider(
                    static::class,
                    'Every provided service identifier must be a string.'
                );
            }

            $service = self::normalizeService(
                $service
            );

            if (
                !in_array(
                    $service,
                    $normalized,
                    true
                )
            ) {
                $normalized[] = $service;
            }
        }

        return $normalized;
    }

    /**
     * @param string $service
     *
     * @return string
     */
    private static function normalizeService(
        string $service
    ): string {
        $service = trim($service);

        if ($service === '') {
            throw FrameworkException::invalidProvider(
                static::class,
                'A provided service identifier cannot be empty.'
            );
        }

        if (strlen($service) > 255) {
            throw FrameworkException::invalidProvider(
                static::class,
                'A provided service identifier cannot exceed 255 bytes.'
            );
        }

        if (
            str_contains($service, "\0")
            || str_contains($service, "\r")
            || str_contains($service, "\n")
        ) {
            throw FrameworkException::invalidProvider(
                static::class,
                'A provided service identifier contains invalid characters.'
            );
        }

        return $service;
    }
}
