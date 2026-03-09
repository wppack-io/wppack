<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Exception;

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
