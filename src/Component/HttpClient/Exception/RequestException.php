<?php

declare(strict_types=1);

namespace WpPack\Component\HttpClient\Exception;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use WpPack\Component\HttpClient\Response;

final class RequestException extends \RuntimeException implements ExceptionInterface, ClientExceptionInterface
{
    public function __construct(
        public readonly Response $response,
        private readonly ?RequestInterface $request = null,
    ) {
        parent::__construct(
            sprintf('HTTP request returned status code %d.', $response->getStatusCode()),
        );
    }

    public function getRequest(): ?RequestInterface
    {
        return $this->request;
    }
}
