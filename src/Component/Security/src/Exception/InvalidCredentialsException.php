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

namespace WPPack\Component\Security\Exception;

/**
 * Thrown when the provided credentials are invalid.
 *
 * Uses a generic message to prevent credential enumeration.
 */
final class InvalidCredentialsException extends AuthenticationException
{
    public function __construct(
        string $message = 'Invalid credentials.',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
