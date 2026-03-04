<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Exception;

final class ConflictException extends HttpException
{
    public function __construct(string $message = 'Conflict.', string $errorCode = 'rest_conflict', ?\Throwable $previous = null)
    {
        parent::__construct($message, 409, $errorCode, $previous);
    }
}
