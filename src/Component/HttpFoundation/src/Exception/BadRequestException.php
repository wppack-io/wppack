<?php

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation\Exception;

final class BadRequestException extends HttpException
{
    public function __construct(string $message = 'Bad request.', string $errorCode = 'http_bad_request', ?\Throwable $previous = null)
    {
        parent::__construct($message, 400, $errorCode, $previous);
    }
}
