<?php

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation\Exception;

final class MethodNotAllowedException extends HttpException
{
    public function __construct(string $message = 'Method not allowed.', string $errorCode = 'http_method_not_allowed', ?\Throwable $previous = null)
    {
        parent::__construct($message, 405, $errorCode, $previous);
    }
}
