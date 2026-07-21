<?php

declare(strict_types=1);

/**
 *
 * @file ApplicationInterface.php
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

namespace Ivi\Framework\Contracts;

use Ivi\Config\Config;
use Ivi\Container\Container;

/**
 * @interface ApplicationInterface
 *
 * @brief Defines the central IviPHP application.
 *
 * The application coordinates the service container, configuration,
 * environment, service providers and framework bootstrap lifecycle.
 *
 * @since 0.1.0
 */
interface ApplicationInterface
{
    /**
     * @brief Return the application base path.
     *
     * @return string
     */
    public function basePath(): string;

    /**
     * @brief Resolve a path relative to the application base directory.
     *
     * Passing an empty path returns the base path.
     *
     * @param string $path
     *
     * @return string
     */
    public function path(string $path = ''): string;

    /**
     * @brief Return the current application environment.
     *
     * Examples:
     *
     * ```text
     * development
     * testing
     * production
     * ```
     *
     * @return string
     */
    public function environment(): string;

    /**
     * @brief Determine whether the application uses one of the environments.
     *
     * @param string ...$environments
     *
     * @return bool
     */
    public function isEnvironment(
        string ...$environments
    ): bool;

    /**
     * @brief Return the application service container.
     *
     * @return Container
     */
    public function container(): Container;

    /**
     * @brief Return the application configuration service.
     *
     * @return Config
     */
    public function config(): Config;

    /**
     * @brief Resolve a service from the application container.
     *
     * @param string $id
     *
     * @return mixed
     */
    public function make(string $id): mixed;

    /**
     * @brief Determine whether a service exists in the container.
     *
     * @param string $id
     *
     * @return bool
     */
    public function has(string $id): bool;

    /**
     * @brief Register an application service provider.
     *
     * A provider instance or provider class name may be supplied.
     *
     * @param ServiceProviderInterface|class-string<ServiceProviderInterface> $provider
     *
     * @return ServiceProviderInterface
     */
    public function register(
        ServiceProviderInterface|string $provider
    ): ServiceProviderInterface;

    /**
     * @brief Determine whether a service provider is registered.
     *
     * @param class-string<ServiceProviderInterface>|string $provider
     *
     * @return bool
     */
    public function hasProvider(
        string $provider
    ): bool;

    /**
     * @brief Return all registered service providers.
     *
     * @return array<int, ServiceProviderInterface>
     */
    public function providers(): array;

    /**
     * @brief Bootstrap the application.
     *
     * Bootstrap operations must execute only once.
     *
     * @return void
     */
    public function bootstrap(): void;

    /**
     * @brief Determine whether the application has been bootstrapped.
     *
     * @return bool
     */
    public function isBootstrapped(): bool;

    /**
     * @brief Boot all registered service providers.
     *
     * Providers must be registered before they are booted.
     *
     * @return void
     */
    public function boot(): void;

    /**
     * @brief Determine whether the application has completed booting.
     *
     * @return bool
     */
    public function isBooted(): bool;
}
