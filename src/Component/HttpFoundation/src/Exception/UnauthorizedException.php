<?php

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation\Exception;

final class UnauthorizedException extends HttpException
{
    public function __construct(string $message = 'Unauthorized.', string $errorCode = 'http_unauthorized', ?\Throwable $previous = null)
    {
        parent::__construct($message, 401, $errorCode, $previous);
    }
}
