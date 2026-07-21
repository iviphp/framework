<?php

declare(strict_types=1);

/**
 *
 * @file ServiceProviderInterface.php
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

/**
 * @interface ServiceProviderInterface
 *
 * @brief Defines an application service provider.
 *
 * A service provider registers services in the application container and
 * may perform additional initialization after all providers have been
 * registered.
 *
 * The register phase should define bindings and configuration without
 * depending on services registered by providers that may not have run yet.
 *
 * The boot phase may safely resolve services and coordinate application
 * behavior after provider registration is complete.
 *
 * @since 0.1.0
 */
interface ServiceProviderInterface
{
    /**
     * @brief Return the application associated with the provider.
     *
     * @return ApplicationInterface
     */
    public function application(): ApplicationInterface;

    /**
     * @brief Register services in the application.
     *
     * This method is executed once when the provider is registered.
     * Implementations should normally add container bindings, aliases,
     * factories and configuration defaults.
     *
     * @return void
     */
    public function register(): void;

    /**
     * @brief Boot the service provider.
     *
     * This method is executed after all service providers have completed
     * registration.
     *
     * Implementations may resolve services, register event listeners,
     * configure routes or perform other application initialization.
     *
     * @return void
     */
    public function boot(): void;

    /**
     * @brief Determine whether the provider has been registered.
     *
     * @return bool
     */
    public function isRegistered(): bool;

    /**
     * @brief Determine whether the provider has completed booting.
     *
     * @return bool
     */
    public function isBooted(): bool;

    /**
     * @brief Return services provided by this provider.
     *
     * The returned values are container identifiers used for diagnostics,
     * deferred loading or framework introspection.
     *
     * @return array<int, string>
     */
    public function provides(): array;

    /**
     * @brief Determine whether the provider supplies a service.
     *
     * @param string $service
     *
     * @return bool
     */
    public function providesService(
        string $service
    ): bool;
}
