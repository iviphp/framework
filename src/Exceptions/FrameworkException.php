<?php

declare(strict_types=1);

/**
 *
 * @file FrameworkException.php
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

namespace Ivi\Framework\Exceptions;

use Throwable;

/**
 * @class FrameworkException
 *
 * @brief Represents application bootstrap and service-provider failures.
 *
 * FrameworkException provides safe diagnostic context for application,
 * container, provider and bootstrap failures without exposing service
 * values, configuration secrets or request data.
 *
 * @since 0.1.0
 */
final class FrameworkException extends \RuntimeException
{
    /**
     * Safe diagnostic context associated with the failure.
     *
     * @var array<string, mixed>
     */
    private array $context;

    /**
     * @param string               $message
     * @param array<string, mixed> $context
     * @param Throwable|null       $previous
     */
    public function __construct(
        string $message,
        array $context = [],
        ?Throwable $previous = null
    ) {
        $message = trim($message);

        if ($message === '') {
            throw new \InvalidArgumentException(
                'The framework exception message cannot be empty.'
            );
        }

        $this->context = self::normalizeContext(
            $context
        );

        parent::__construct(
            $message,
            0,
            $previous
        );
    }

    /**
     * @brief Create an exception for an invalid application base path.
     *
     * @param string $path
     * @param string $reason
     *
     * @return self
     */
    public static function invalidBasePath(
        string $path,
        string $reason
    ): self {
        $reason = self::normalizeReason(
            $reason,
            'The invalid base path reason cannot be empty.'
        );

        return new self(
            "Invalid application base path: {$reason}",
            [
                'path' => $path,
            ]
        );
    }

    /**
     * @brief Create an exception for an invalid application path.
     *
     * @param string $path
     * @param string $reason
     *
     * @return self
     */
    public static function invalidPath(
        string $path,
        string $reason
    ): self {
        $reason = self::normalizeReason(
            $reason,
            'The invalid application path reason cannot be empty.'
        );

        return new self(
            "Invalid application path: {$reason}",
            [
                'path' => $path,
            ]
        );
    }

    /**
     * @brief Create an exception for an invalid environment name.
     *
     * @param string $environment
     * @param string $reason
     *
     * @return self
     */
    public static function invalidEnvironment(
        string $environment,
        string $reason
    ): self {
        $reason = self::normalizeReason(
            $reason,
            'The invalid environment reason cannot be empty.'
        );

        return new self(
            "Invalid application environment: {$reason}",
            [
                'environment' => $environment,
            ]
        );
    }

    /**
     * @brief Create an exception for an invalid service provider.
     *
     * @param string $provider
     * @param string $reason
     *
     * @return self
     */
    public static function invalidProvider(
        string $provider,
        string $reason
    ): self {
        $reason = self::normalizeReason(
            $reason,
            'The invalid service provider reason cannot be empty.'
        );

        return new self(
            "Invalid service provider {$provider}: {$reason}",
            [
                'provider' => $provider,
            ]
        );
    }

    /**
     * @brief Create an exception when a provider is already registered.
     *
     * @param string $provider
     *
     * @return self
     */
    public static function duplicateProvider(
        string $provider
    ): self {
        return new self(
            "Service provider is already registered: {$provider}",
            [
                'provider' => $provider,
            ]
        );
    }

    /**
     * @brief Create an exception when a provider is not registered.
     *
     * @param string $provider
     *
     * @return self
     */
    public static function providerNotFound(
        string $provider
    ): self {
        return new self(
            "Service provider is not registered: {$provider}",
            [
                'provider' => $provider,
            ]
        );
    }

    /**
     * @brief Create an exception when provider construction fails.
     *
     * @param string         $provider
     * @param Throwable|null $previous
     *
     * @return self
     */
    public static function providerCreationFailed(
        string $provider,
        ?Throwable $previous = null
    ): self {
        return new self(
            "Unable to create service provider: {$provider}",
            [
                'provider' => $provider,
            ],
            $previous
        );
    }

    /**
     * @brief Create an exception when provider registration fails.
     *
     * @param string         $provider
     * @param Throwable|null $previous
     *
     * @return self
     */
    public static function providerRegistrationFailed(
        string $provider,
        ?Throwable $previous = null
    ): self {
        return new self(
            "Unable to register service provider: {$provider}",
            [
                'provider' => $provider,
            ],
            $previous
        );
    }

    /**
     * @brief Create an exception when provider booting fails.
     *
     * @param string         $provider
     * @param Throwable|null $previous
     *
     * @return self
     */
    public static function providerBootFailed(
        string $provider,
        ?Throwable $previous = null
    ): self {
        return new self(
            "Unable to boot service provider: {$provider}",
            [
                'provider' => $provider,
            ],
            $previous
        );
    }

    /**
     * @brief Create an exception when a provider is booted too early.
     *
     * @param string $provider
     *
     * @return self
     */
    public static function providerNotRegistered(
        string $provider
    ): self {
        return new self(
            "Service provider must be registered before booting: {$provider}",
            [
                'provider' => $provider,
            ]
        );
    }

    /**
     * @brief Create an exception for an invalid bootstrapper.
     *
     * @param string $bootstrapper
     * @param string $reason
     *
     * @return self
     */
    public static function invalidBootstrapper(
        string $bootstrapper,
        string $reason
    ): self {
        $reason = self::normalizeReason(
            $reason,
            'The invalid bootstrapper reason cannot be empty.'
        );

        return new self(
            "Invalid application bootstrapper {$bootstrapper}: {$reason}",
            [
                'bootstrapper' => $bootstrapper,
            ]
        );
    }

    /**
     * @brief Create an exception when application bootstrapping fails.
     *
     * @param string         $bootstrapper
     * @param Throwable|null $previous
     *
     * @return self
     */
    public static function bootstrapFailed(
        string $bootstrapper,
        ?Throwable $previous = null
    ): self {
        return new self(
            "Application bootstrap failed: {$bootstrapper}",
            [
                'bootstrapper' => $bootstrapper,
            ],
            $previous
        );
    }

    /**
     * @brief Create an exception when the application cannot boot.
     *
     * @param Throwable|null $previous
     *
     * @return self
     */
    public static function applicationBootFailed(
        ?Throwable $previous = null
    ): self {
        return new self(
            'Unable to boot the application.',
            [],
            $previous
        );
    }

    /**
     * @brief Create an exception when a required service is unavailable.
     *
     * @param string $service
     *
     * @return self
     */
    public static function serviceNotFound(
        string $service
    ): self {
        return new self(
            "Application service is not available: {$service}",
            [
                'service' => $service,
            ]
        );
    }

    /**
     * @brief Create an exception when service resolution fails.
     *
     * @param string         $service
     * @param Throwable|null $previous
     *
     * @return self
     */
    public static function serviceResolutionFailed(
        string $service,
        ?Throwable $previous = null
    ): self {
        return new self(
            "Unable to resolve application service: {$service}",
            [
                'service' => $service,
            ],
            $previous
        );
    }

    /**
     * @brief Create an exception for invalid framework configuration.
     *
     * @param string $component
     * @param string $reason
     *
     * @return self
     */
    public static function invalidConfiguration(
        string $component,
        string $reason
    ): self {
        $component = trim($component);

        if ($component === '') {
            throw new \InvalidArgumentException(
                'The framework configuration component cannot be empty.'
            );
        }

        $reason = self::normalizeReason(
            $reason,
            'The invalid framework configuration reason cannot be empty.'
        );

        return new self(
            "Invalid framework configuration for {$component}: {$reason}",
            [
                'component' => $component,
            ]
        );
    }

    /**
     * @brief Create an exception for an invalid lifecycle operation.
     *
     * @param string $operation
     * @param string $reason
     *
     * @return self
     */
    public static function invalidLifecycle(
        string $operation,
        string $reason
    ): self {
        $operation = trim($operation);

        if ($operation === '') {
            throw new \InvalidArgumentException(
                'The lifecycle operation cannot be empty.'
            );
        }

        $reason = self::normalizeReason(
            $reason,
            'The invalid lifecycle reason cannot be empty.'
        );

        return new self(
            "Invalid application lifecycle operation {$operation}: {$reason}",
            [
                'operation' => $operation,
            ]
        );
    }

    /**
     * @brief Return safe diagnostic context.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * @param string $reason
     * @param string $emptyMessage
     *
     * @return string
     */
    private static function normalizeReason(
        string $reason,
        string $emptyMessage
    ): string {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException(
                $emptyMessage
            );
        }

        return $reason;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private static function normalizeContext(
        array $context
    ): array {
        $normalized = [];

        foreach ($context as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException(
                    'Framework exception context keys must be strings.'
                );
            }

            $key = trim($key);

            if (
                $key === ''
                || preg_match(
                    '/^[A-Za-z_][A-Za-z0-9_.-]*$/',
                    $key
                ) !== 1
            ) {
                throw new \InvalidArgumentException(
                    'A framework exception context key is invalid.'
                );
            }

            if (
                is_resource($value)
                || $value instanceof \Closure
            ) {
                throw new \InvalidArgumentException(
                    "Framework exception context value {$key} has an unsupported type."
                );
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
