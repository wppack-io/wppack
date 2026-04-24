<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Debug\ErrorHandler;

use WPPack\Component\Debug\Exception\ExceptionInterface;

/**
 * Synthetic exception wrapping wp_die() data for rendering by ErrorRenderer.
 *
 * WordPress calls wp_die() for DB errors, permission failures, nonce failures, etc.
 * This exception captures that data so FlattenException::createFromThrowable()
 * can process it through the standard debug page pipeline.
 *
 * When the wp_die() was triggered by a WP_Error, the underlying error is
 * chained as the $previous exception (WpErrorException).
 */
final class WpDieException extends \RuntimeException implements ExceptionInterface
{
    /**
     * @param array<string, mixed> $wpDieArgs
     */
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly string $wpDieTitle,
        private readonly array $wpDieArgs,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Detected by FlattenException::createFromThrowable() via method_exists().
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Display class for the debug page header.
     *
     * Always returns "wp_die()" for a unified appearance,
     * regardless of whether the source was a WP_Error or plain string.
     * Detected by FlattenException::createFromThrowable() via method_exists().
     */
    public function getDisplayClass(): string
    {
        return 'wp_die()';
    }

    public function getWpDieTitle(): string
    {
        return $this->wpDieTitle;
    }

    /**
     * @return array<string, mixed>
     */
    public function getWpDieArgs(): array
    {
        return $this->wpDieArgs;
    }
}
