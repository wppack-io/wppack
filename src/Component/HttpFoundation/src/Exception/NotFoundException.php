<?php

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation\Exception;

final class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Not found.', string $errorCode = 'http_not_found', ?\Throwable $previous = null)
    {
        parent::__construct($message, 404, $errorCode, $previous);
    }
}
