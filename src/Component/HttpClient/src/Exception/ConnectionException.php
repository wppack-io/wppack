<?php

declare(strict_types=1);

namespace WpPack\Component\HttpClient\Exception;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

final class ConnectionException extends \RuntimeException implements ExceptionInterface, NetworkExceptionInterface
{
    public function __construct(
        string $message,
        private readonly RequestInterface $request,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
