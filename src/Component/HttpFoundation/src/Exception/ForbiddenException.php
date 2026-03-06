<?php

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation\Exception;

final class ForbiddenException extends HttpException
{
    public function __construct(string $message = 'Forbidden.', string $errorCode = 'http_forbidden', ?\Throwable $previous = null)
    {
        parent::__construct($message, 403, $errorCode, $previous);
    }
}
