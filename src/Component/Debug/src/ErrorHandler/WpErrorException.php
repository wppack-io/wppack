<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Debug\ErrorHandler;

/**
 * Exception wrapping a WP_Error instance.
 *
 * Used as the $previous exception in WpDieException to represent
 * the underlying WP_Error that caused the wp_die() call.
 */
final class WpErrorException extends \RuntimeException
{
    /**
     * @param list<string>         $wpErrorCodes
     * @param array<string, mixed> $wpErrorData
     */
    public function __construct(
        string $message,
        private readonly array $wpErrorCodes = [],
        private readonly array $wpErrorData = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Display class for the debug page chain section.
     *
     * Shows "WP_Error (code1, code2)" for clear identification.
     * Detected by FlattenException::createFromThrowable() via method_exists().
     */
    public function getDisplayClass(): string
    {
        if ($this->wpErrorCodes !== []) {
            return 'WP_Error (' . implode(', ', $this->wpErrorCodes) . ')';
        }

        return 'WP_Error';
    }

    /**
     * @return list<string>
     */
    public function getWpErrorCodes(): array
    {
        return $this->wpErrorCodes;
    }

    /**
     * @return array<string, mixed>
     */
    public function getWpErrorData(): array
    {
        return $this->wpErrorData;
    }

}
