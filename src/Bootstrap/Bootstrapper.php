<?php

declare(strict_types=1);

/**
 *
 * @file Bootstrapper.php
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

namespace Ivi\Framework\Bootstrap;

use Closure;
use Ivi\Framework\Contracts\ApplicationInterface;
use Ivi\Framework\Exceptions\FrameworkException;
use Throwable;

/**
 * @class Bootstrapper
 *
 * @brief Coordinates ordered application bootstrap operations.
 *
 * Bootstrapper stores named callbacks and executes them once in
 * registration order. Each callback receives the application instance.
 *
 * Bootstrap operations may load configuration, prepare directories,
 * register framework services or perform other initialization required
 * before service providers are booted.
 *
 * @since 0.1.0
 */
final class Bootstrapper
{
    /**
     * Registered bootstrap callbacks indexed by name.
     *
     * @var array<string, Closure(ApplicationInterface): void>
     */
    private array $callbacks = [];

    /**
     * Bootstrap operation execution order.
     *
     * @var array<int, string>
     */
    private array $order = [];

    /**
     * Successfully executed bootstrap operations.
     *
     * @var array<string, true>
     */
    private array $executed = [];

    /**
     * Whether all registered operations have completed.
     */
    private bool $bootstrapped = false;

    /**
     * Whether bootstrap execution is currently active.
     */
    private bool $bootstrapping = false;

    /**
     * Name of the operation currently being executed.
     */
    private ?string $current = null;

    /**
     * @param iterable<string, callable(ApplicationInterface): void> $callbacks
     */
    public function __construct(
        iterable $callbacks = []
    ) {
        foreach ($callbacks as $name => $callback) {
            if (!is_string($name)) {
                throw FrameworkException::invalidBootstrapper(
                    get_debug_type($name),
                    'Bootstrap callback names must be strings.'
                );
            }

            if (!is_callable($callback)) {
                throw FrameworkException::invalidBootstrapper(
                    $name,
                    'The bootstrap operation must be callable.'
                );
            }

            $this->add(
                $name,
                $callback
            );
        }
    }

    /**
     * @brief Register a bootstrap operation.
     *
     * Bootstrap operation names must be unique unless replacement is
     * explicitly enabled.
     *
     * New operations cannot be added after bootstrapping has started.
     *
     * @param string                                  $name
     * @param callable(ApplicationInterface): void    $callback
     * @param bool                                    $replace
     *
     * @return self
     */
    public function add(
        string $name,
        callable $callback,
        bool $replace = false
    ): self {
        $name = self::normalizeName($name);

        if ($this->bootstrapping) {
            throw FrameworkException::invalidLifecycle(
                'bootstrap.add',
                'Bootstrap operations cannot be changed while bootstrapping.'
            );
        }

        if ($this->bootstrapped) {
            throw FrameworkException::invalidLifecycle(
                'bootstrap.add',
                'Bootstrap operations cannot be added after completion.'
            );
        }

        if (
            !$replace
            && array_key_exists(
                $name,
                $this->callbacks
            )
        ) {
            throw FrameworkException::invalidBootstrapper(
                $name,
                'A bootstrap operation with this name is already registered.'
            );
        }

        if (!array_key_exists($name, $this->callbacks)) {
            $this->order[] = $name;
        }

        $this->callbacks[$name] = Closure::fromCallable(
            $callback
        );

        return $this;
    }

    /**
     * @brief Replace a registered bootstrap operation.
     *
     * @param string                               $name
     * @param callable(ApplicationInterface): void $callback
     *
     * @return self
     */
    public function replace(
        string $name,
        callable $callback
    ): self {
        $name = self::normalizeName($name);

        if (!$this->has($name)) {
            throw FrameworkException::invalidBootstrapper(
                $name,
                'The bootstrap operation is not registered.'
            );
        }

        return $this->add(
            $name,
            $callback,
            true
        );
    }

    /**
     * @brief Determine whether a bootstrap operation is registered.
     *
     * @param string $name
     *
     * @return bool
     */
    public function has(string $name): bool
    {
        $name = self::normalizeName($name);

        return array_key_exists(
            $name,
            $this->callbacks
        );
    }

    /**
     * @brief Retrieve a registered bootstrap callback.
     *
     * @param string $name
     *
     * @return Closure(ApplicationInterface): void
     */
    public function get(string $name): Closure
    {
        $name = self::normalizeName($name);

        if (!array_key_exists($name, $this->callbacks)) {
            throw FrameworkException::invalidBootstrapper(
                $name,
                'The bootstrap operation is not registered.'
            );
        }

        return $this->callbacks[$name];
    }

    /**
     * @brief Remove a registered bootstrap operation.
     *
     * Removing an unknown operation has no effect.
     *
     * Operations cannot be removed after bootstrap execution starts.
     *
     * @param string $name
     *
     * @return self
     */
    public function forget(string $name): self
    {
        $name = self::normalizeName($name);

        if ($this->bootstrapping || $this->bootstrapped) {
            throw FrameworkException::invalidLifecycle(
                'bootstrap.forget',
                'Bootstrap operations cannot be removed after execution starts.'
            );
        }

        if (!array_key_exists($name, $this->callbacks)) {
            return $this;
        }

        unset($this->callbacks[$name]);

        $this->order = array_values(
            array_filter(
                $this->order,
                static fn(string $registered): bool =>
                    $registered !== $name
            )
        );

        return $this;
    }

    /**
     * @brief Execute all registered bootstrap operations.
     *
     * Successfully completed operations are not executed again when this
     * method is called repeatedly.
     *
     * When one operation fails, execution stops and the failure is wrapped
     * in FrameworkException. Operations completed before the failure remain
     * marked as executed.
     *
     * @param ApplicationInterface $application
     *
     * @return void
     */
    public function bootstrap(
        ApplicationInterface $application
    ): void {
        if ($this->bootstrapped) {
            return;
        }

        if ($this->bootstrapping) {
            throw FrameworkException::invalidLifecycle(
                'bootstrap',
                'Recursive application bootstrapping is not allowed.'
            );
        }

        $this->bootstrapping = true;

        try {
            foreach ($this->order as $name) {
                if (isset($this->executed[$name])) {
                    continue;
                }

                $callback = $this->callbacks[$name]
                    ?? null;

                if (!$callback instanceof Closure) {
                    throw FrameworkException::invalidBootstrapper(
                        $name,
                        'The bootstrap callback is unavailable.'
                    );
                }

                $this->current = $name;

                try {
                    $callback($application);
                } catch (FrameworkException $exception) {
                    throw FrameworkException::bootstrapFailed(
                        $name,
                        $exception
                    );
                } catch (Throwable $exception) {
                    throw FrameworkException::bootstrapFailed(
                        $name,
                        $exception
                    );
                }

                $this->executed[$name] = true;
            }

            $this->bootstrapped = true;
        } finally {
            $this->current = null;
            $this->bootstrapping = false;
        }
    }

    /**
     * @brief Determine whether every operation has completed.
     *
     * @return bool
     */
    public function isBootstrapped(): bool
    {
        return $this->bootstrapped;
    }

    /**
     * @brief Determine whether bootstrap execution is active.
     *
     * @return bool
     */
    public function isBootstrapping(): bool
    {
        return $this->bootstrapping;
    }

    /**
     * @brief Determine whether one operation has completed.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasExecuted(string $name): bool
    {
        $name = self::normalizeName($name);

        return isset($this->executed[$name]);
    }

    /**
     * @brief Return the operation currently being executed.
     *
     * @return string|null
     */
    public function current(): ?string
    {
        return $this->current;
    }

    /**
     * @brief Return all bootstrap operation names in execution order.
     *
     * @return array<int, string>
     */
    public function names(): array
    {
        return $this->order;
    }

    /**
     * @brief Return all successfully executed operation names.
     *
     * @return array<int, string>
     */
    public function executed(): array
    {
        return array_values(
            array_filter(
                $this->order,
                fn(string $name): bool =>
                    isset($this->executed[$name])
            )
        );
    }

    /**
     * @brief Return the number of registered bootstrap operations.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->callbacks);
    }

    /**
     * @brief Determine whether no bootstrap operations are registered.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->callbacks === [];
    }

    /**
     * @brief Remove every registered bootstrap operation.
     *
     * The bootstrapper cannot be cleared after execution starts.
     *
     * @return self
     */
    public function clear(): self
    {
        if ($this->bootstrapping || $this->bootstrapped) {
            throw FrameworkException::invalidLifecycle(
                'bootstrap.clear',
                'The bootstrapper cannot be cleared after execution starts.'
            );
        }

        $this->callbacks = [];
        $this->order = [];
        $this->executed = [];

        return $this;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private static function normalizeName(
        string $name
    ): string {
        $name = trim($name);

        if ($name === '') {
            throw FrameworkException::invalidBootstrapper(
                $name,
                'The bootstrap operation name cannot be empty.'
            );
        }

        if (strlen($name) > 255) {
            throw FrameworkException::invalidBootstrapper(
                $name,
                'The bootstrap operation name cannot exceed 255 bytes.'
            );
        }

        if (
            preg_match(
                '/^[A-Za-z0-9_-]+(?:[.:][A-Za-z0-9_-]+)*$/',
                $name
            ) !== 1
        ) {
            throw FrameworkException::invalidBootstrapper(
                $name,
                'The bootstrap operation name contains invalid characters.'
            );
        }

        return $name;
    }
}
