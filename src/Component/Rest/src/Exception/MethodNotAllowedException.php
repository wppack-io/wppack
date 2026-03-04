<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Exception;

final class MethodNotAllowedException extends HttpException
{
    public function __construct(string $message = 'Method not allowed.', string $errorCode = 'rest_method_not_allowed', ?\Throwable $previous = null)
    {
        parent::__construct($message, 405, $errorCode, $previous);
    }
}
