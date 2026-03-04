<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Exception;

final class ForbiddenException extends HttpException
{
    public function __construct(string $message = 'Forbidden.', string $errorCode = 'rest_forbidden', ?\Throwable $previous = null)
    {
        parent::__construct($message, 403, $errorCode, $previous);
    }
}
