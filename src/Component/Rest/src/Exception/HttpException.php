<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Exception;

class HttpException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(
        string $message = '',
        private readonly int $statusCode = 500,
        private readonly string $errorCode = 'rest_error',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
