<?php

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation\Exception;

final class ConflictException extends HttpException
{
    public function __construct(string $message = 'Conflict.', string $errorCode = 'http_conflict', ?\Throwable $previous = null)
    {
        parent::__construct($message, 409, $errorCode, $previous);
    }
}
